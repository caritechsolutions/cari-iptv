#include "migration.h"
#include "database.h"
#include "logger.h"
#include "storage.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <pthread.h>
#include <unistd.h>
#include <time.h>
#include <sys/stat.h>
#include <errno.h>
#include <sqlite3.h>

/* ---------- module state ---------- */

static const vod_config_t *g_config  = NULL;
static volatile int         g_running = 0;
static pthread_t            g_thread;

/* Poll interval for the migration processor loop (seconds) */
#define MIGRATION_POLL_INTERVAL 10

/* ---------- helpers ---------- */

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

/**
 * Get the file size at a given path, or 0 if not accessible.
 */
static long long file_size(const char *path)
{
    struct stat st;
    if (stat(path, &st) == 0 && S_ISREG(st.st_mode)) {
        return (long long)st.st_size;
    }
    return 0;
}

/**
 * Build the temp download path for a migration.
 * Pattern: {temp_path}/migration_{id}/{filename}
 */
static void migration_temp_path(int migration_id, const char *filename,
                                char *out, size_t out_len)
{
    snprintf(out, out_len, "%s/migration_%d/%s",
             g_config->temp_path, migration_id, filename);
}

/**
 * Build the temp directory path for a migration.
 */
static void migration_temp_dir(int migration_id, char *out, size_t out_len)
{
    snprintf(out, out_len, "%s/migration_%d",
             g_config->temp_path, migration_id);
}

/**
 * Ensure a directory exists (recursive mkdir via storage module).
 */
static int ensure_dir(const char *path)
{
    return storage_ensure_dir(path);
}

/**
 * Clean up temp directory for a migration.
 */
static void cleanup_migration_temp(int migration_id)
{
    char dir[MAX_PATH_LEN];
    migration_temp_dir(migration_id, dir, sizeof(dir));

    char cmd[MAX_PATH_LEN + 32];
    snprintf(cmd, sizeof(cmd), "rm -rf '%s'", dir);
    /* Best-effort cleanup; ignore errors */
    FILE *fp = popen(cmd, "r");
    if (fp) pclose(fp);
}

/**
 * Look up a peer's URL and API key by peer name (which may be "local" for this node,
 * or a peer name/URL stored in the peers table).
 * Returns 0 on success, -1 on failure.
 */
static int resolve_peer(const char *peer_name, char *url_out, size_t url_len,
                        char *key_out, size_t key_len)
{
    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    /* Try matching by name first, then by URL */
    int rc = sqlite3_prepare_v2(db,
        "SELECT url, COALESCE(api_key,'') FROM peers "
        "WHERE name = ? OR url = ? LIMIT 1",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("migration: failed to prepare resolve_peer: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_text(stmt, 1, peer_name, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 2, peer_name, -1, SQLITE_TRANSIENT);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        log_error("migration: peer '%s' not found", peer_name);
        return -1;
    }

    const char *u = (const char *)sqlite3_column_text(stmt, 0);
    const char *k = (const char *)sqlite3_column_text(stmt, 1);
    snprintf(url_out, url_len, "%s", u ? u : "");
    snprintf(key_out, key_len, "%s", k ? k : "");
    sqlite3_finalize(stmt);
    return 0;
}

/**
 * Update migration status and optionally set error message.
 */
static void update_migration_status(int id, const char *status, const char *error_msg)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    sqlite3_stmt *stmt = NULL;
    int rc;

    if (error_msg) {
        rc = sqlite3_prepare_v2(db,
            "UPDATE migrations SET status = ?, error_msg = ? WHERE id = ?",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_text(stmt, 1, status, -1, SQLITE_TRANSIENT);
            sqlite3_bind_text(stmt, 2, error_msg, -1, SQLITE_TRANSIENT);
            sqlite3_bind_int(stmt, 3, id);
            sqlite3_step(stmt);
            sqlite3_finalize(stmt);
        }
    } else {
        rc = sqlite3_prepare_v2(db,
            "UPDATE migrations SET status = ? WHERE id = ?",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_text(stmt, 1, status, -1, SQLITE_TRANSIENT);
            sqlite3_bind_int(stmt, 2, id);
            sqlite3_step(stmt);
            sqlite3_finalize(stmt);
        }
    }
}

/**
 * Update bytes_transferred for a migration.
 */
static void update_migration_bytes(int id, long long bytes_transferred)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    sqlite3_stmt *stmt = NULL;
    double progress = 0.0;

    /* Also compute progress percentage from bytes_total */
    int rc = sqlite3_prepare_v2(db,
        "SELECT bytes_total FROM migrations WHERE id = ?",
        -1, &stmt, NULL);
    if (rc == SQLITE_OK) {
        sqlite3_bind_int(stmt, 1, id);
        if (sqlite3_step(stmt) == SQLITE_ROW) {
            long long total = sqlite3_column_int64(stmt, 0);
            if (total > 0) {
                progress = (double)bytes_transferred / (double)total * 100.0;
                if (progress > 100.0) progress = 100.0;
            }
        }
        sqlite3_finalize(stmt);
    }

    rc = sqlite3_prepare_v2(db,
        "UPDATE migrations SET bytes_transferred = ?, progress = ? WHERE id = ?",
        -1, &stmt, NULL);
    if (rc == SQLITE_OK) {
        sqlite3_bind_int64(stmt, 1, bytes_transferred);
        sqlite3_bind_double(stmt, 2, progress);
        sqlite3_bind_int(stmt, 3, id);
        sqlite3_step(stmt);
        sqlite3_finalize(stmt);
    }
}

/**
 * Mark a migration as complete.
 */
static void complete_migration(int id)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    char now[32];
    now_iso8601(now, sizeof(now));

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "UPDATE migrations SET status = 'complete', progress = 100.0, "
        "completed_at = ? WHERE id = ?",
        -1, &stmt, NULL);
    if (rc == SQLITE_OK) {
        sqlite3_bind_text(stmt, 1, now, -1, SQLITE_TRANSIENT);
        sqlite3_bind_int(stmt, 2, id);
        sqlite3_step(stmt);
        sqlite3_finalize(stmt);
    }
}

/**
 * Determine the migration direction based on source_peer / dest_peer.
 * If source_peer matches our node name, it's PUSH (upload to remote).
 * If dest_peer matches our node name (or is "local"), it's PULL (download from remote).
 * Otherwise, default to PULL (source is the remote).
 */
static migrate_direction_t get_direction(const char *source_peer, const char *dest_peer)
{
    /* "local" or matching our node_name means this node */
    if (strcmp(dest_peer, "local") == 0 ||
        strcmp(dest_peer, g_config->node_name) == 0) {
        return MIGRATE_PULL;
    }
    if (strcmp(source_peer, "local") == 0 ||
        strcmp(source_peer, g_config->node_name) == 0) {
        return MIGRATE_PUSH;
    }
    /* Default: assume we are the destination, pulling from source */
    return MIGRATE_PULL;
}

/**
 * Execute a PULL migration: download content from a remote peer to local storage.
 * Returns 0 on success, -1 on failure.
 */
static int execute_pull(int migration_id, const char *content_id,
                        const char *title, const char *source_peer,
                        int delete_source)
{
    (void)delete_source; /* delete_source on remote not implemented for pull */

    char peer_url[512];
    char api_key[256];

    if (resolve_peer(source_peer, peer_url, sizeof(peer_url),
                     api_key, sizeof(api_key)) != 0) {
        update_migration_status(migration_id, "failed",
                                "Source peer not found in cluster");
        return -1;
    }

    /* Create temp directory for this migration */
    char temp_dir[MAX_PATH_LEN];
    migration_temp_dir(migration_id, temp_dir, sizeof(temp_dir));
    if (ensure_dir(temp_dir) != 0) {
        update_migration_status(migration_id, "failed",
                                "Failed to create temp directory");
        return -1;
    }

    /* Build temp file path */
    char temp_file[MAX_PATH_LEN];
    migration_temp_path(migration_id, "download.tar.gz", temp_file, sizeof(temp_file));

    /* Mark as transferring with started_at timestamp */
    {
        sqlite3 *db = db_handle();
        if (db) {
            char now[32];
            now_iso8601(now, sizeof(now));
            sqlite3_stmt *stmt = NULL;
            int rc = sqlite3_prepare_v2(db,
                "UPDATE migrations SET status = 'transferring', started_at = ? WHERE id = ?",
                -1, &stmt, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_text(stmt, 1, now, -1, SQLITE_TRANSIENT);
                sqlite3_bind_int(stmt, 2, migration_id);
                sqlite3_step(stmt);
                sqlite3_finalize(stmt);
            }
        }
    }

    /* Build curl download command:
     * GET {peer_url}/api/content/{content_id}/download
     * with X-API-Key header, output to temp file */
    char cmd[4096];
    if (api_key[0] != '\0') {
        snprintf(cmd, sizeof(cmd),
            "curl -s -f -m 3600 -H 'X-API-Key: %s' "
            "-o '%s' '%s/api/content/%s/download' 2>&1",
            api_key, temp_file, peer_url, content_id);
    } else {
        snprintf(cmd, sizeof(cmd),
            "curl -s -f -m 3600 "
            "-o '%s' '%s/api/content/%s/download' 2>&1",
            temp_file, peer_url, content_id);
    }

    log_info("migration %d: downloading content '%s' from %s",
             migration_id, content_id, peer_url);

    FILE *fp = popen(cmd, "r");
    if (!fp) {
        update_migration_status(migration_id, "failed",
                                "Failed to execute curl download");
        cleanup_migration_temp(migration_id);
        return -1;
    }

    /* Read any curl error output */
    char errbuf[1024] = {0};
    size_t errlen = 0;
    while (!feof(fp) && errlen < sizeof(errbuf) - 1) {
        size_t n = fread(errbuf + errlen, 1, sizeof(errbuf) - errlen - 1, fp);
        errlen += n;
        if (n == 0) break;
    }
    errbuf[errlen] = '\0';

    int status = pclose(fp);

    if (status != 0) {
        char msg[1280];
        snprintf(msg, sizeof(msg), "Download failed (exit %d): %.1024s",
                 status, errbuf);
        log_error("migration %d: %s", migration_id, msg);
        update_migration_status(migration_id, "failed", msg);
        cleanup_migration_temp(migration_id);
        return -1;
    }

    /* Verify the downloaded file exists and has size */
    long long downloaded_size = file_size(temp_file);
    if (downloaded_size <= 0) {
        update_migration_status(migration_id, "failed",
                                "Downloaded file is empty or missing");
        cleanup_migration_temp(migration_id);
        return -1;
    }

    log_info("migration %d: download complete (%lld bytes)", migration_id, downloaded_size);
    update_migration_bytes(migration_id, downloaded_size);

    /* Create the content directory in the library */
    char content_dir[MAX_PATH_LEN];
    if (storage_create_content_dir(content_id, content_dir, sizeof(content_dir)) != 0) {
        update_migration_status(migration_id, "failed",
                                "Failed to create content directory");
        cleanup_migration_temp(migration_id);
        return -1;
    }

    /* Extract or move the downloaded file into the content directory.
     * Try tar extraction first; if that fails, just move the raw file. */
    char extract_cmd[4096];
    snprintf(extract_cmd, sizeof(extract_cmd),
        "tar -xzf '%s' -C '%s' 2>/dev/null", temp_file, content_dir);

    FILE *efp = popen(extract_cmd, "r");
    int extract_status = -1;
    if (efp) {
        extract_status = pclose(efp);
    }

    if (extract_status != 0) {
        /* Not a tar archive -- move the raw file directly */
        char dest_file[MAX_PATH_LEN];
        snprintf(dest_file, sizeof(dest_file), "%s/content.bin", content_dir);
        if (storage_move_file(temp_file, dest_file) != 0) {
            update_migration_status(migration_id, "failed",
                                    "Failed to move downloaded file to library");
            cleanup_migration_temp(migration_id);
            return -1;
        }
        log_info("migration %d: moved raw file to %s", migration_id, dest_file);
    } else {
        log_info("migration %d: extracted archive to %s", migration_id, content_dir);
    }

    /* Insert into content table if not already present */
    {
        sqlite3 *db = db_handle();
        if (db) {
            sqlite3_stmt *stmt = NULL;
            int rc = sqlite3_prepare_v2(db,
                "SELECT COUNT(*) FROM content WHERE content_id = ?",
                -1, &stmt, NULL);
            int exists = 0;
            if (rc == SQLITE_OK) {
                sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_TRANSIENT);
                if (sqlite3_step(stmt) == SQLITE_ROW) {
                    exists = sqlite3_column_int(stmt, 0);
                }
                sqlite3_finalize(stmt);
            }

            if (exists == 0) {
                rc = sqlite3_prepare_v2(db,
                    "INSERT INTO content (content_id, title, path, size_bytes, status) "
                    "VALUES (?, ?, ?, ?, 'ready')",
                    -1, &stmt, NULL);
                if (rc == SQLITE_OK) {
                    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_TRANSIENT);
                    sqlite3_bind_text(stmt, 2, title ? title : content_id, -1, SQLITE_TRANSIENT);
                    sqlite3_bind_text(stmt, 3, content_dir, -1, SQLITE_TRANSIENT);
                    sqlite3_bind_int64(stmt, 4, downloaded_size);
                    rc = sqlite3_step(stmt);
                    sqlite3_finalize(stmt);

                    if (rc == SQLITE_DONE) {
                        log_info("migration %d: inserted content '%s' into database",
                                 migration_id, content_id);
                    } else {
                        log_warn("migration %d: failed to insert content record: %s",
                                 migration_id, sqlite3_errmsg(db));
                    }
                }
            } else {
                /* Update existing record path and size */
                rc = sqlite3_prepare_v2(db,
                    "UPDATE content SET path = ?, size_bytes = ?, "
                    "updated_at = CURRENT_TIMESTAMP WHERE content_id = ?",
                    -1, &stmt, NULL);
                if (rc == SQLITE_OK) {
                    sqlite3_bind_text(stmt, 1, content_dir, -1, SQLITE_TRANSIENT);
                    sqlite3_bind_int64(stmt, 2, downloaded_size);
                    sqlite3_bind_text(stmt, 3, content_id, -1, SQLITE_TRANSIENT);
                    sqlite3_step(stmt);
                    sqlite3_finalize(stmt);
                }
                log_info("migration %d: updated existing content '%s'",
                         migration_id, content_id);
            }
        }
    }

    /* Mark migration complete */
    complete_migration(migration_id);
    cleanup_migration_temp(migration_id);

    log_info("migration %d: completed successfully (content '%s')",
             migration_id, content_id);
    return 0;
}

/**
 * Check progress of active (transferring) migrations by inspecting
 * partially downloaded file sizes on disk.
 */
static void check_active_progress(void)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT id, content_id FROM migrations WHERE status = 'transferring'",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) return;

    while (sqlite3_step(stmt) == SQLITE_ROW) {
        int id = sqlite3_column_int(stmt, 0);
        /* Check the temp file size for this migration */
        char temp_file[MAX_PATH_LEN];
        migration_temp_path(id, "download.tar.gz", temp_file, sizeof(temp_file));
        long long sz = file_size(temp_file);
        if (sz > 0) {
            update_migration_bytes(id, sz);
        }
    }
    sqlite3_finalize(stmt);
}

/**
 * Pick the next pending migration and execute it.
 * Returns 0 if a migration was started, -1 if none available or error.
 */
static int process_next_migration(void)
{
    sqlite3 *db = db_handle();
    if (!db) return -1;

    /* Check if we have capacity */
    int active = migration_active_count();
    if (active < 0) return -1;
    if (active >= g_config->max_concurrent_migrations) {
        log_debug("migration: %d/%d slots in use, waiting",
                  active, g_config->max_concurrent_migrations);
        return -1;
    }

    /* Pick the oldest pending migration */
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT id, content_id, title, source_peer, dest_peer, delete_source "
        "FROM migrations WHERE status = 'pending' "
        "ORDER BY created_at ASC LIMIT 1",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("migration: failed to query pending: %s", sqlite3_errmsg(db));
        return -1;
    }

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return -1; /* No pending migrations */
    }

    int  id            = sqlite3_column_int(stmt, 0);
    const char *cid    = (const char *)sqlite3_column_text(stmt, 1);
    const char *ttl    = (const char *)sqlite3_column_text(stmt, 2);
    const char *src    = (const char *)sqlite3_column_text(stmt, 3);
    const char *dst    = (const char *)sqlite3_column_text(stmt, 4);
    int  del_src       = sqlite3_column_int(stmt, 5);

    /* Copy strings before finalizing the statement */
    char content_id[256], title[256], source_peer[256], dest_peer[256];
    snprintf(content_id, sizeof(content_id), "%s", cid ? cid : "");
    snprintf(title, sizeof(title), "%s", ttl ? ttl : "");
    snprintf(source_peer, sizeof(source_peer), "%s", src ? src : "");
    snprintf(dest_peer, sizeof(dest_peer), "%s", dst ? dst : "");

    sqlite3_finalize(stmt);

    /* Determine direction */
    migrate_direction_t dir = get_direction(source_peer, dest_peer);

    log_info("migration %d: starting %s for content '%s' (%s -> %s)",
             id, dir == MIGRATE_PULL ? "PULL" : "PUSH",
             content_id, source_peer, dest_peer);

    if (dir == MIGRATE_PULL) {
        return execute_pull(id, content_id, title, source_peer, del_src);
    } else {
        /* PUSH not yet implemented */
        log_warn("migration %d: PUSH migrations are not yet implemented", id);
        update_migration_status(id, "failed",
                                "PUSH migration not implemented; use PULL from the destination node");
        return -1;
    }
}

/* ---------- migration processor thread ---------- */

static void *migration_thread(void *arg)
{
    (void)arg;
    log_info("Migration processor thread started (max_concurrent=%d)",
             g_config->max_concurrent_migrations);

    while (g_running) {
        /* Sleep in 1-second increments for prompt shutdown */
        for (int s = 0; s < MIGRATION_POLL_INTERVAL && g_running; s++) {
            sleep(1);
        }
        if (!g_running) break;

        /* Step 1: Update progress of active transfers */
        check_active_progress();

        /* Step 2: Start new migrations if we have capacity */
        int active = migration_active_count();
        if (active >= 0 && active < g_config->max_concurrent_migrations) {
            process_next_migration();
        }
    }

    log_info("Migration processor thread stopped");
    return NULL;
}

/* ---------- public API ---------- */

int migration_init(const vod_config_t *config)
{
    if (!config) {
        log_error("migration: NULL config");
        return -1;
    }
    g_config  = config;
    g_running = 0;
    log_info("Migration module initialized (max_concurrent=%d)",
             config->max_concurrent_migrations);
    return 0;
}

int migration_start(void)
{
    if (g_running) {
        log_warn("migration: processor thread already running");
        return -1;
    }

    g_running = 1;
    int rc = pthread_create(&g_thread, NULL, migration_thread, NULL);
    if (rc != 0) {
        g_running = 0;
        log_error("migration: failed to create processor thread: %s", strerror(rc));
        return -1;
    }

    log_info("Migration processor thread launched");
    return 0;
}

void migration_stop(void)
{
    if (!g_running) return;

    log_info("Stopping migration processor thread...");
    g_running = 0;
    pthread_join(g_thread, NULL);
    log_info("Migration module stopped");
}

int migration_create(const char *content_id, const char *title,
                     const char *source_peer, const char *dest_peer,
                     int delete_source)
{
    if (!content_id || !source_peer || !dest_peer) {
        log_error("migration: content_id, source_peer, and dest_peer are required");
        return -1;
    }

    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "INSERT INTO migrations "
        "(content_id, title, source_peer, dest_peer, status, delete_source) "
        "VALUES (?, ?, ?, ?, 'pending', ?)",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("migration: failed to prepare create: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 2, title ? title : "", -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 3, source_peer, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 4, dest_peer, -1, SQLITE_TRANSIENT);
    sqlite3_bind_int(stmt, 5, delete_source ? 1 : 0);

    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        log_error("migration: failed to insert: %s", sqlite3_errmsg(db));
        return -1;
    }

    int new_id = (int)sqlite3_last_insert_rowid(db);
    log_info("migration: created job %d (content='%s', %s -> %s)",
             new_id, content_id, source_peer, dest_peer);
    return new_id;
}

int migration_cancel(int migration_id)
{
    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "UPDATE migrations SET status = 'cancelled' "
        "WHERE id = ? AND status IN ('pending', 'transferring')",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("migration: failed to prepare cancel: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_int(stmt, 1, migration_id);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        log_error("migration: failed to cancel %d: %s", migration_id, sqlite3_errmsg(db));
        return -1;
    }

    int changes = sqlite3_changes(db);
    if (changes == 0) {
        log_warn("migration: %d not found or not cancellable", migration_id);
        return -1;
    }

    /* Clean up any temp files */
    cleanup_migration_temp(migration_id);

    log_info("migration: cancelled job %d", migration_id);
    return 0;
}

int migration_active_count(void)
{
    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT COUNT(*) FROM migrations WHERE status = 'transferring'",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("migration: failed to count active: %s", sqlite3_errmsg(db));
        return -1;
    }

    int count = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        count = sqlite3_column_int(stmt, 0);
    }
    sqlite3_finalize(stmt);

    return count;
}
