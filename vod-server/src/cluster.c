#include "cluster.h"
#include "database.h"
#include "logger.h"
#include "cjson/cJSON.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <pthread.h>
#include <unistd.h>
#include <time.h>
#include <sqlite3.h>

/* ---------- module state ---------- */

static const vod_config_t *g_config = NULL;
static volatile int         g_running = 0;
static pthread_t            g_thread;

/* ---------- helpers ---------- */

/**
 * Run a curl command via popen and capture its stdout output.
 * Returns a malloc'd string (caller frees), or NULL on failure.
 */
static char *run_curl(const char *cmd)
{
    FILE *fp = popen(cmd, "r");
    if (!fp) {
        log_error("cluster: popen failed for curl command");
        return NULL;
    }

    size_t alloc = 4096;
    size_t len   = 0;
    char  *buf   = malloc(alloc);
    if (!buf) {
        pclose(fp);
        return NULL;
    }

    while (!feof(fp)) {
        size_t space = alloc - len - 1;
        if (space == 0) {
            alloc *= 2;
            char *tmp = realloc(buf, alloc);
            if (!tmp) {
                free(buf);
                pclose(fp);
                return NULL;
            }
            buf   = tmp;
            space = alloc - len - 1;
        }
        size_t n = fread(buf + len, 1, space, fp);
        len += n;
        if (n == 0) break;
    }
    buf[len] = '\0';

    int status = pclose(fp);
    if (status != 0) {
        /* curl returned non-zero -- treat as failure */
        free(buf);
        return NULL;
    }

    return buf;
}

/**
 * Get the current UTC time as an ISO 8601 string.
 */
static void now_iso8601(char *buf, size_t len)
{
    time_t t = time(NULL);
    struct tm tm;
    gmtime_r(&t, &tm);
    strftime(buf, len, "%Y-%m-%dT%H:%M:%SZ", &tm);
}

/* ---------- public API ---------- */

int cluster_init(const vod_config_t *config)
{
    if (!config) {
        log_error("cluster: NULL config");
        return -1;
    }
    g_config  = config;
    g_running = 0;
    log_info("Cluster module initialized (node: %s)", config->node_name);
    return 0;
}

/* ---------- health check thread ---------- */

static void *health_check_thread(void *arg)
{
    (void)arg;
    log_info("Cluster health check thread started (interval=%ds, threshold=%d)",
             g_config->health_check_interval, g_config->offline_threshold);

    while (g_running) {
        /* Sleep in 1-second increments so we can exit promptly */
        for (int s = 0; s < g_config->health_check_interval && g_running; s++) {
            sleep(1);
        }
        if (!g_running) break;

        log_debug("cluster: running health checks");

        sqlite3 *db = db_handle();
        if (!db) continue;

        /* Fetch all peer IDs */
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db, "SELECT id FROM peers", -1, &stmt, NULL);
        if (rc != SQLITE_OK) {
            log_error("cluster: failed to query peers: %s", sqlite3_errmsg(db));
            continue;
        }

        /* Collect IDs into a temporary array (avoid holding the statement open
         * while we do potentially slow curl calls inside cluster_ping_peer). */
        int  id_alloc = 32;
        int  id_count = 0;
        int *ids      = malloc(id_alloc * sizeof(int));
        if (!ids) {
            sqlite3_finalize(stmt);
            continue;
        }

        while (sqlite3_step(stmt) == SQLITE_ROW) {
            if (id_count >= id_alloc) {
                id_alloc *= 2;
                int *tmp = realloc(ids, id_alloc * sizeof(int));
                if (!tmp) break;
                ids = tmp;
            }
            ids[id_count++] = sqlite3_column_int(stmt, 0);
        }
        sqlite3_finalize(stmt);

        /* Ping each peer */
        for (int i = 0; i < id_count && g_running; i++) {
            int peer_id = ids[i];
            int result  = cluster_ping_peer(peer_id);

            if (result != 0) {
                /* Peer unreachable -- check consecutive failure count.
                 * We read last_error as a failure counter string. */
                sqlite3_stmt *upd = NULL;

                /* First read current consecutive fail count from last_error */
                int fails = 0;
                rc = sqlite3_prepare_v2(db,
                    "SELECT COALESCE(CAST("
                    "  CASE WHEN last_error GLOB '[0-9]*' THEN last_error ELSE '0' END"
                    " AS INTEGER), 0) FROM peers WHERE id = ?",
                    -1, &upd, NULL);
                if (rc == SQLITE_OK) {
                    sqlite3_bind_int(upd, 1, peer_id);
                    if (sqlite3_step(upd) == SQLITE_ROW) {
                        fails = sqlite3_column_int(upd, 0);
                    }
                    sqlite3_finalize(upd);
                }

                fails++;

                if (fails >= g_config->offline_threshold) {
                    /* Mark as offline */
                    rc = sqlite3_prepare_v2(db,
                        "UPDATE peers SET status = 'offline', last_error = ? WHERE id = ?",
                        -1, &upd, NULL);
                    if (rc == SQLITE_OK) {
                        char fail_str[32];
                        snprintf(fail_str, sizeof(fail_str), "%d", fails);
                        sqlite3_bind_text(upd, 1, fail_str, -1, SQLITE_TRANSIENT);
                        sqlite3_bind_int(upd, 2, peer_id);
                        sqlite3_step(upd);
                        sqlite3_finalize(upd);
                    }
                    log_warn("cluster: peer %d marked offline after %d consecutive failures",
                             peer_id, fails);
                } else {
                    /* Increment failure counter */
                    rc = sqlite3_prepare_v2(db,
                        "UPDATE peers SET last_error = ? WHERE id = ?",
                        -1, &upd, NULL);
                    if (rc == SQLITE_OK) {
                        char fail_str[32];
                        snprintf(fail_str, sizeof(fail_str), "%d", fails);
                        sqlite3_bind_text(upd, 1, fail_str, -1, SQLITE_TRANSIENT);
                        sqlite3_bind_int(upd, 2, peer_id);
                        sqlite3_step(upd);
                        sqlite3_finalize(upd);
                    }
                    log_debug("cluster: peer %d unreachable (%d/%d)",
                              peer_id, fails, g_config->offline_threshold);
                }
            }
            /* If ping succeeded, cluster_ping_peer already updated the row
             * and reset last_error to '0' */
        }

        free(ids);
    }

    log_info("Cluster health check thread stopped");
    return NULL;
}

int cluster_start(void)
{
    if (g_running) {
        log_warn("cluster: health check thread already running");
        return -1;
    }

    g_running = 1;
    int rc = pthread_create(&g_thread, NULL, health_check_thread, NULL);
    if (rc != 0) {
        g_running = 0;
        log_error("cluster: failed to create health check thread: %s", strerror(rc));
        return -1;
    }

    log_info("Cluster health check thread launched");
    return 0;
}

void cluster_stop(void)
{
    if (!g_running) return;

    log_info("Stopping cluster health check thread...");
    g_running = 0;
    pthread_join(g_thread, NULL);
    log_info("Cluster module stopped");
}

/* ---------- peer management ---------- */

int cluster_add_peer(const char *name, const char *url, const char *api_key)
{
    if (!name || !url) {
        log_error("cluster: name and url are required");
        return -1;
    }

    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "INSERT INTO peers (name, url, api_key, status) VALUES (?, ?, ?, 'unknown')",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("cluster: failed to prepare add_peer: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_text(stmt, 1, name, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 2, url, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 3, api_key ? api_key : "", -1, SQLITE_TRANSIENT);

    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        log_error("cluster: failed to insert peer '%s': %s", name, sqlite3_errmsg(db));
        return -1;
    }

    int new_id = (int)sqlite3_last_insert_rowid(db);
    log_info("cluster: added peer '%s' (%s) with id %d", name, url, new_id);
    return new_id;
}

int cluster_remove_peer(int peer_id)
{
    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "DELETE FROM peers WHERE id = ?", -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("cluster: failed to prepare remove_peer: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_int(stmt, 1, peer_id);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        log_error("cluster: failed to delete peer %d: %s", peer_id, sqlite3_errmsg(db));
        return -1;
    }

    int changes = sqlite3_changes(db);
    if (changes == 0) {
        log_warn("cluster: peer %d not found", peer_id);
        return -1;
    }

    log_info("cluster: removed peer %d", peer_id);
    return 0;
}

int cluster_get_peers(peer_info_t **out_peers)
{
    if (!out_peers) return -1;
    *out_peers = NULL;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    /* First count the rows */
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT COUNT(*) FROM peers", -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("cluster: failed to count peers: %s", sqlite3_errmsg(db));
        return -1;
    }

    int count = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        count = sqlite3_column_int(stmt, 0);
    }
    sqlite3_finalize(stmt);

    if (count == 0) {
        *out_peers = NULL;
        return 0;
    }

    /* Allocate the array */
    peer_info_t *peers = calloc(count, sizeof(peer_info_t));
    if (!peers) {
        log_error("cluster: out of memory for %d peers", count);
        return -1;
    }

    /* Fetch all rows */
    rc = sqlite3_prepare_v2(db,
        "SELECT id, name, url, status, COALESCE(version,''), "
        "cpu_usage, memory_usage, disk_total, disk_free, "
        "content_count, active_jobs, COALESCE(last_seen,'') "
        "FROM peers ORDER BY id",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("cluster: failed to query peers: %s", sqlite3_errmsg(db));
        free(peers);
        return -1;
    }

    int idx = 0;
    while (sqlite3_step(stmt) == SQLITE_ROW && idx < count) {
        peer_info_t *p = &peers[idx];
        p->id = sqlite3_column_int(stmt, 0);

        const char *name = (const char *)sqlite3_column_text(stmt, 1);
        snprintf(p->name, sizeof(p->name), "%s", name ? name : "");

        const char *url = (const char *)sqlite3_column_text(stmt, 2);
        snprintf(p->url, sizeof(p->url), "%s", url ? url : "");

        const char *status = (const char *)sqlite3_column_text(stmt, 3);
        snprintf(p->status, sizeof(p->status), "%s", status ? status : "unknown");

        const char *version = (const char *)sqlite3_column_text(stmt, 4);
        snprintf(p->version, sizeof(p->version), "%s", version ? version : "");

        p->cpu_usage     = sqlite3_column_double(stmt, 5);
        p->memory_usage  = sqlite3_column_double(stmt, 6);
        p->disk_total    = sqlite3_column_int64(stmt, 7);
        p->disk_free     = sqlite3_column_int64(stmt, 8);
        p->content_count = sqlite3_column_int(stmt, 9);
        p->active_jobs   = sqlite3_column_int(stmt, 10);

        const char *last_seen = (const char *)sqlite3_column_text(stmt, 11);
        snprintf(p->last_seen, sizeof(p->last_seen), "%s", last_seen ? last_seen : "");

        idx++;
    }
    sqlite3_finalize(stmt);

    *out_peers = peers;
    return idx;
}

int cluster_get_peer(int peer_id, peer_info_t *out_peer)
{
    if (!out_peer) return -1;
    memset(out_peer, 0, sizeof(*out_peer));

    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT id, name, url, status, COALESCE(version,''), "
        "cpu_usage, memory_usage, disk_total, disk_free, "
        "content_count, active_jobs, COALESCE(last_seen,'') "
        "FROM peers WHERE id = ?",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("cluster: failed to prepare get_peer: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_int(stmt, 1, peer_id);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return -1;
    }

    out_peer->id = sqlite3_column_int(stmt, 0);

    const char *name = (const char *)sqlite3_column_text(stmt, 1);
    snprintf(out_peer->name, sizeof(out_peer->name), "%s", name ? name : "");

    const char *url = (const char *)sqlite3_column_text(stmt, 2);
    snprintf(out_peer->url, sizeof(out_peer->url), "%s", url ? url : "");

    const char *status = (const char *)sqlite3_column_text(stmt, 3);
    snprintf(out_peer->status, sizeof(out_peer->status), "%s", status ? status : "unknown");

    const char *version = (const char *)sqlite3_column_text(stmt, 4);
    snprintf(out_peer->version, sizeof(out_peer->version), "%s", version ? version : "");

    out_peer->cpu_usage     = sqlite3_column_double(stmt, 5);
    out_peer->memory_usage  = sqlite3_column_double(stmt, 6);
    out_peer->disk_total    = sqlite3_column_int64(stmt, 7);
    out_peer->disk_free     = sqlite3_column_int64(stmt, 8);
    out_peer->content_count = sqlite3_column_int(stmt, 9);
    out_peer->active_jobs   = sqlite3_column_int(stmt, 10);

    const char *last_seen = (const char *)sqlite3_column_text(stmt, 11);
    snprintf(out_peer->last_seen, sizeof(out_peer->last_seen), "%s", last_seen ? last_seen : "");

    sqlite3_finalize(stmt);
    return 0;
}

/* ---------- peer communication ---------- */

int cluster_ping_peer(int peer_id)
{
    sqlite3 *db = db_handle();
    if (!db) return -1;

    /* Look up peer URL and API key */
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT url, COALESCE(api_key,'') FROM peers WHERE id = ?",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("cluster: failed to prepare ping lookup: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_int(stmt, 1, peer_id);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        log_warn("cluster: peer %d not found for ping", peer_id);
        return -1;
    }

    char peer_url[512];
    char api_key[256];
    const char *u = (const char *)sqlite3_column_text(stmt, 0);
    const char *k = (const char *)sqlite3_column_text(stmt, 1);
    snprintf(peer_url, sizeof(peer_url), "%s", u ? u : "");
    snprintf(api_key, sizeof(api_key), "%s", k ? k : "");
    sqlite3_finalize(stmt);

    /* Build curl command: GET {peer_url}/api/status with X-API-Key header */
    char cmd[2048];
    if (api_key[0] != '\0') {
        snprintf(cmd, sizeof(cmd),
            "curl -s -m 10 -H 'X-API-Key: %s' '%s/api/status' 2>/dev/null",
            api_key, peer_url);
    } else {
        snprintf(cmd, sizeof(cmd),
            "curl -s -m 10 '%s/api/status' 2>/dev/null",
            peer_url);
    }

    char *response = run_curl(cmd);
    if (!response) {
        log_debug("cluster: peer %d unreachable at %s", peer_id, peer_url);
        return -1;
    }

    /* Parse JSON response */
    cJSON *root = cJSON_Parse(response);
    free(response);

    if (!root) {
        log_debug("cluster: peer %d returned invalid JSON", peer_id);
        return -1;
    }

    /* Extract fields from the status response */
    const char *version_str = "";
    double cpu = 0.0;
    long long disk_total = 0, disk_free = 0, disk_used = 0;
    double mem_usage = 0.0;
    int content_count = 0;
    int active_jobs = 0;

    cJSON *jversion = cJSON_GetObjectItemCaseSensitive(root, "version");
    if (cJSON_IsString(jversion) && jversion->valuestring) {
        version_str = jversion->valuestring;
    }

    cJSON *jcpu = cJSON_GetObjectItemCaseSensitive(root, "cpu_usage");
    if (cJSON_IsNumber(jcpu)) {
        cpu = jcpu->valuedouble;
    }

    cJSON *jmem = cJSON_GetObjectItemCaseSensitive(root, "memory_usage");
    if (cJSON_IsNumber(jmem)) {
        mem_usage = jmem->valuedouble;
    }

    /* Disk info may be nested in a "disk" object or at top level */
    cJSON *jdisk = cJSON_GetObjectItemCaseSensitive(root, "disk");
    if (cJSON_IsObject(jdisk)) {
        cJSON *jdt = cJSON_GetObjectItemCaseSensitive(jdisk, "total");
        cJSON *jdf = cJSON_GetObjectItemCaseSensitive(jdisk, "free");
        cJSON *jdu = cJSON_GetObjectItemCaseSensitive(jdisk, "used");
        if (cJSON_IsNumber(jdt)) disk_total = (long long)jdt->valuedouble;
        if (cJSON_IsNumber(jdf)) disk_free  = (long long)jdf->valuedouble;
        if (cJSON_IsNumber(jdu)) disk_used  = (long long)jdu->valuedouble;
    } else {
        /* Try top-level fields */
        cJSON *jdt = cJSON_GetObjectItemCaseSensitive(root, "disk_total");
        cJSON *jdf = cJSON_GetObjectItemCaseSensitive(root, "disk_free");
        cJSON *jdu = cJSON_GetObjectItemCaseSensitive(root, "disk_used");
        if (cJSON_IsNumber(jdt)) disk_total = (long long)jdt->valuedouble;
        if (cJSON_IsNumber(jdf)) disk_free  = (long long)jdf->valuedouble;
        if (cJSON_IsNumber(jdu)) disk_used  = (long long)jdu->valuedouble;
    }

    cJSON *jcontent = cJSON_GetObjectItemCaseSensitive(root, "content_count");
    if (cJSON_IsNumber(jcontent)) {
        content_count = (int)jcontent->valuedouble;
    }

    cJSON *jjobs = cJSON_GetObjectItemCaseSensitive(root, "active_jobs");
    if (cJSON_IsNumber(jjobs)) {
        active_jobs = (int)jjobs->valuedouble;
    }

    /* Update the peer record in the database */
    char now[32];
    now_iso8601(now, sizeof(now));

    rc = sqlite3_prepare_v2(db,
        "UPDATE peers SET "
        "status = 'online', "
        "version = ?, "
        "cpu_usage = ?, "
        "memory_usage = ?, "
        "disk_total = ?, "
        "disk_free = ?, "
        "disk_used = ?, "
        "content_count = ?, "
        "active_jobs = ?, "
        "last_seen = ?, "
        "last_error = '0' "
        "WHERE id = ?",
        -1, &stmt, NULL);

    if (rc != SQLITE_OK) {
        log_error("cluster: failed to prepare ping update: %s", sqlite3_errmsg(db));
        cJSON_Delete(root);
        return -1;
    }

    sqlite3_bind_text(stmt, 1, version_str, -1, SQLITE_TRANSIENT);
    sqlite3_bind_double(stmt, 2, cpu);
    sqlite3_bind_double(stmt, 3, mem_usage);
    sqlite3_bind_int64(stmt, 4, disk_total);
    sqlite3_bind_int64(stmt, 5, disk_free);
    sqlite3_bind_int64(stmt, 6, disk_used);
    sqlite3_bind_int(stmt, 7, content_count);
    sqlite3_bind_int(stmt, 8, active_jobs);
    sqlite3_bind_text(stmt, 9, now, -1, SQLITE_TRANSIENT);
    sqlite3_bind_int(stmt, 10, peer_id);

    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    cJSON_Delete(root);

    if (rc != SQLITE_DONE) {
        log_error("cluster: failed to update peer %d: %s", peer_id, sqlite3_errmsg(db));
        return -1;
    }

    log_debug("cluster: peer %d online (v%s, cpu=%.1f%%)", peer_id, version_str, cpu);
    return 0;
}

char *cluster_fetch_remote_content(int peer_id)
{
    sqlite3 *db = db_handle();
    if (!db) return NULL;

    /* Look up peer URL and API key */
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT url, COALESCE(api_key,'') FROM peers WHERE id = ?",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("cluster: failed to prepare fetch_remote lookup: %s", sqlite3_errmsg(db));
        return NULL;
    }

    sqlite3_bind_int(stmt, 1, peer_id);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        log_warn("cluster: peer %d not found for fetch_remote_content", peer_id);
        return NULL;
    }

    char peer_url[512];
    char api_key[256];
    const char *u = (const char *)sqlite3_column_text(stmt, 0);
    const char *k = (const char *)sqlite3_column_text(stmt, 1);
    snprintf(peer_url, sizeof(peer_url), "%s", u ? u : "");
    snprintf(api_key, sizeof(api_key), "%s", k ? k : "");
    sqlite3_finalize(stmt);

    /* Build curl command: GET {peer_url}/api/content with X-API-Key header */
    char cmd[2048];
    if (api_key[0] != '\0') {
        snprintf(cmd, sizeof(cmd),
            "curl -s -m 30 -H 'X-API-Key: %s' '%s/api/content' 2>/dev/null",
            api_key, peer_url);
    } else {
        snprintf(cmd, sizeof(cmd),
            "curl -s -m 30 '%s/api/content' 2>/dev/null",
            peer_url);
    }

    char *response = run_curl(cmd);
    if (!response) {
        log_error("cluster: failed to fetch content list from peer %d (%s)",
                  peer_id, peer_url);
        return NULL;
    }

    log_debug("cluster: fetched content list from peer %d (%zu bytes)",
              peer_id, strlen(response));
    return response;
}
