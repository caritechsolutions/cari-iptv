/*
 * api_routes.c - JSON API endpoint handlers for VOD Server
 *
 * Handles all /api/ routes: status, config, content, jobs, peers, migrations.
 * Uses cJSON for building/parsing JSON and sqlite3 for database access.
 */

#include "api_routes.h"
#include "http_server.h"
#include "config.h"
#include "database.h"
#include "drm.h"
#include "storage.h"
#include "job_processor.h"
#include "cluster.h"
#include "migration.h"
#include "logger.h"
#include "version.h"
#include "cjson/cJSON.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <unistd.h>
#include <signal.h>
#include <sys/stat.h>
#include <sys/sysinfo.h>
#include <dirent.h>
#include <sqlite3.h>

#include "ssl.h"

#include <errno.h>

/* ---------- module state ---------- */

static const vod_config_t *s_config = NULL;
static time_t              s_start_time = 0;

/* Forward declarations */
static vod_config_t *get_mutable_config(void);

/* ---------- helpers ---------- */

/**
 * Initialise module state on first use.
 * Called lazily from api_handle_request.
 */
/* Externally visible config from http_server.c */
extern const vod_config_t *g_http_config;

static void ensure_init(void)
{
    if (s_start_time == 0) {
        s_start_time = time(NULL);
    }
    /* Lazily grab config from http_server if not set explicitly */
    if (!s_config && g_http_config) {
        s_config = g_http_config;
    }
}

/**
 * Send a cJSON object as an HTTP JSON response, then free the cJSON tree.
 */
static int send_json_obj(struct MHD_Connection *conn, int status, cJSON *json)
{
    char *str = cJSON_PrintUnformatted(json);
    cJSON_Delete(json);
    if (!str) {
        return http_send_error(conn, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "JSON serialization failed");
    }
    int ret = http_send_json(conn, status, str);
    free(str);
    return ret;
}

/**
 * Parse a request body as JSON. Returns NULL on failure.
 * Caller must call cJSON_Delete on the returned object.
 */
static cJSON *parse_request_body(http_request_t *req)
{
    if (!req->post_data || req->post_data_len == 0) {
        return NULL;
    }
    return cJSON_ParseWithLength(req->post_data, req->post_data_len);
}

/**
 * Get a string value from a cJSON object, returning def if missing/null.
 */
static const char *json_str(cJSON *obj, const char *key, const char *def)
{
    cJSON *item = cJSON_GetObjectItemCaseSensitive(obj, key);
    if (cJSON_IsString(item) && item->valuestring) {
        return item->valuestring;
    }
    return def;
}

/**
 * Get an integer value from a cJSON object, returning def if missing/null.
 */
static int json_int(cJSON *obj, const char *key, int def)
{
    cJSON *item = cJSON_GetObjectItemCaseSensitive(obj, key);
    if (cJSON_IsNumber(item)) {
        return item->valueint;
    }
    return def;
}

/**
 * Get a boolean value from a cJSON object, returning def if missing.
 */
static int json_bool(cJSON *obj, const char *key, int def)
{
    cJSON *item = cJSON_GetObjectItemCaseSensitive(obj, key);
    if (cJSON_IsBool(item)) {
        return cJSON_IsTrue(item) ? 1 : 0;
    }
    return def;
}

/**
 * Read current CPU usage from /proc/loadavg (1-min average).
 * Returns percentage based on number of CPUs.
 */
static double get_cpu_usage(void)
{
    FILE *fp = fopen("/proc/loadavg", "r");
    if (!fp) return 0.0;

    double load1 = 0.0;
    if (fscanf(fp, "%lf", &load1) != 1) {
        fclose(fp);
        return 0.0;
    }
    fclose(fp);

    long ncpu = sysconf(_SC_NPROCESSORS_ONLN);
    if (ncpu <= 0) ncpu = 1;

    double pct = (load1 / (double)ncpu) * 100.0;
    if (pct > 100.0) pct = 100.0;
    return pct;
}

/**
 * Read memory usage from /proc/meminfo.
 * Returns usage as a percentage.
 */
static double get_memory_usage(void)
{
    FILE *fp = fopen("/proc/meminfo", "r");
    if (!fp) return 0.0;

    unsigned long mem_total = 0, mem_available = 0;
    char line[256];
    while (fgets(line, sizeof(line), fp)) {
        if (strncmp(line, "MemTotal:", 9) == 0) {
            sscanf(line + 9, " %lu", &mem_total);
        } else if (strncmp(line, "MemAvailable:", 13) == 0) {
            sscanf(line + 13, " %lu", &mem_available);
        }
        if (mem_total > 0 && mem_available > 0) break;
    }
    fclose(fp);

    if (mem_total == 0) return 0.0;

    double used = (double)(mem_total - mem_available);
    return (used / (double)mem_total) * 100.0;
}

/**
 * Format bytes into a human-readable string (e.g. "1.5 GB").
 * Writes into the provided buffer and returns it.
 */
static const char *format_bytes(uint64_t bytes, char *buf, size_t buflen)
{
    static const char *units[] = { "B", "KB", "MB", "GB", "TB", "PB" };
    int u = 0;
    double val = (double)bytes;
    while (val >= 1024.0 && u < 5) {
        val /= 1024.0;
        u++;
    }
    snprintf(buf, buflen, "%.2f %s", val, units[u]);
    return buf;
}

/* ---------- URL router ---------- */

int api_handle_request(http_request_t *req)
{
    ensure_init();

    const char *url    = req->url;
    const char *method = req->method;
    int id = 0;

    /* GET /api/status */
    if (strcmp(url, "/api/status") == 0 && strcmp(method, HTTP_GET) == 0) {
        return api_get_status(req);
    }

    /* GET|POST /api/config */
    if (strcmp(url, "/api/config") == 0) {
        if (strcmp(method, HTTP_GET) == 0)  return api_get_config(req);
        if (strcmp(method, HTTP_POST) == 0) return api_post_config(req);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* --- Profile routes --- */

    /* GET|PUT|DELETE /api/profiles/{name} */
    if (strncmp(url, "/api/profiles/", 14) == 0 && strlen(url) > 14) {
        const char *profile_name = url + 14;
        if (strcmp(method, HTTP_PUT) == 0)    return api_put_profile(req, profile_name);
        if (strcmp(method, HTTP_DELETE) == 0)  return api_delete_profile(req, profile_name);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* GET|POST /api/profiles */
    if (strcmp(url, "/api/profiles") == 0) {
        if (strcmp(method, HTTP_GET) == 0)  return api_get_profiles(req);
        if (strcmp(method, HTTP_POST) == 0) return api_post_profile(req);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* POST /api/health */
    if (strcmp(url, "/api/health") == 0 && strcmp(method, HTTP_POST) == 0) {
        return api_post_peer_health(req);
    }

    /* --- Content routes --- */

    /* GET|DELETE /api/content/{id} */
    if (strncmp(url, "/api/content/", 13) == 0 && strlen(url) > 13) {
        const char *content_id_str = url + 13;

        /* Check for /api/content/{id}/download */
        char cid_buf[256];
        const char *slash = strchr(content_id_str, '/');
        if (slash) {
            size_t cid_len = (size_t)(slash - content_id_str);
            if (cid_len >= sizeof(cid_buf)) cid_len = sizeof(cid_buf) - 1;
            memcpy(cid_buf, content_id_str, cid_len);
            cid_buf[cid_len] = '\0';

            if (strcmp(slash, "/download") == 0 && strcmp(method, HTTP_GET) == 0) {
                return api_get_content_download(req, cid_buf);
            }
            return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                                   "Not found");
        }

        if (strcmp(method, HTTP_GET) == 0)    return api_get_content(req, content_id_str);
        if (strcmp(method, HTTP_DELETE) == 0)  return api_delete_content(req, content_id_str);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* GET /api/content */
    if (strcmp(url, "/api/content") == 0) {
        if (strcmp(method, HTTP_GET) == 0)  return api_get_content_list(req);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* --- Job routes --- */

    /* GET|DELETE /api/jobs/{id} */
    if (strncmp(url, "/api/jobs/", 10) == 0 && strlen(url) > 10) {
        if (sscanf(url + 10, "%d", &id) == 1 && id > 0) {
            if (strcmp(method, HTTP_GET) == 0)    return api_get_job(req, id);
            if (strcmp(method, HTTP_DELETE) == 0)  return api_delete_job(req, id);
            return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                                   "Method not allowed");
        }
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid job ID");
    }

    /* GET|POST|DELETE /api/jobs */
    if (strcmp(url, "/api/jobs") == 0) {
        if (strcmp(method, HTTP_GET) == 0)    return api_get_jobs(req);
        if (strcmp(method, HTTP_POST) == 0)   return api_post_job(req);
        if (strcmp(method, HTTP_DELETE) == 0)  return api_bulk_delete_jobs(req);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* --- Peer routes --- */

    /* /api/peers/{id}/content */
    if (strncmp(url, "/api/peers/", 11) == 0) {
        const char *rest = url + 11;
        int peer_id = 0;
        int consumed = 0;

        if (sscanf(rest, "%d%n", &peer_id, &consumed) == 1 && peer_id > 0) {
            const char *tail = rest + consumed;

            if (strcmp(tail, "/content") == 0 && strcmp(method, HTTP_GET) == 0) {
                return api_get_peer_content(req, peer_id);
            }
            if (tail[0] == '\0') {
                if (strcmp(method, HTTP_DELETE) == 0) return api_delete_peer(req, peer_id);
                return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                                       "Method not allowed");
            }
            return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                                   "Not found");
        }
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid peer ID");
    }

    /* GET|POST /api/peers */
    if (strcmp(url, "/api/peers") == 0) {
        if (strcmp(method, HTTP_GET) == 0)  return api_get_peers(req);
        if (strcmp(method, HTTP_POST) == 0) return api_post_peer(req);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* --- Migration routes --- */

    /* GET|DELETE /api/migrations/{id} */
    if (strncmp(url, "/api/migrations/", 16) == 0 && strlen(url) > 16) {
        if (sscanf(url + 16, "%d", &id) == 1 && id > 0) {
            if (strcmp(method, HTTP_GET) == 0)    return api_get_migration(req, id);
            if (strcmp(method, HTTP_DELETE) == 0)  return api_delete_migration(req, id);
            return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                                   "Method not allowed");
        }
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid migration ID");
    }

    /* GET|POST /api/migrations */
    if (strcmp(url, "/api/migrations") == 0) {
        if (strcmp(method, HTTP_GET) == 0)  return api_get_migrations(req);
        if (strcmp(method, HTTP_POST) == 0) return api_post_migration(req);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* --- Utility routes --- */

    /* POST /api/upload?filename=... */
    if (strcmp(url, "/api/upload") == 0 && strcmp(method, HTTP_POST) == 0) {
        return api_post_upload(req);
    }

    /* GET|DELETE /api/uploads */
    if (strcmp(url, "/api/uploads") == 0) {
        if (strcmp(method, HTTP_GET) == 0)    return api_get_uploads(req);
        if (strcmp(method, HTTP_DELETE) == 0)  return api_delete_upload(req);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* GET /api/browse?path=... */
    if (strcmp(url, "/api/browse") == 0 && strcmp(method, HTTP_GET) == 0) {
        return api_get_browse(req);
    }

    /* GET /api/ssl/status */
    if (strcmp(url, "/api/ssl/status") == 0 && strcmp(method, HTTP_GET) == 0) {
        return api_get_ssl_status(req);
    }

    /* POST /api/ssl/letsencrypt */
    if (strcmp(url, "/api/ssl/letsencrypt") == 0 && strcmp(method, HTTP_POST) == 0) {
        return api_post_ssl_letsencrypt(req);
    }

    /* POST /api/ssl/self-signed */
    if (strcmp(url, "/api/ssl/self-signed") == 0 && strcmp(method, HTTP_POST) == 0) {
        return api_post_ssl_self_signed(req);
    }

    /* POST /api/ssl/upload — upload cert+key PEM */
    if (strcmp(url, "/api/ssl/upload") == 0 && strcmp(method, HTTP_POST) == 0) {
        return api_post_ssl_upload(req);
    }

    /* --- DRM routes --- */

    /* GET /api/drm/keys - List all DRM keys */
    if (strcmp(url, "/api/drm/keys") == 0 && strcmp(method, HTTP_GET) == 0) {
        return api_get_drm_keys(req);
    }

    /* POST /api/drm/keys - Generate a new DRM key */
    if (strcmp(url, "/api/drm/keys") == 0 && strcmp(method, HTTP_POST) == 0) {
        return api_post_drm_key(req);
    }

    /* GET /api/drm/keys/{content_id} */
    if (strncmp(url, "/api/drm/keys/", 14) == 0 && strlen(url) > 14) {
        const char *content_id = url + 14;
        if (strcmp(method, HTTP_GET) == 0)    return api_get_drm_key(req, content_id);
        if (strcmp(method, HTTP_DELETE) == 0)  return api_delete_drm_key(req, content_id);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* GET /api/drm/license?content_id=... - ClearKey license (EME format) */
    if (strcmp(url, "/api/drm/license") == 0 && strcmp(method, HTTP_GET) == 0) {
        return api_get_drm_license(req);
    }

    /* POST /api/drm/license - ClearKey license (EME key IDs in body) */
    if (strcmp(url, "/api/drm/license") == 0 && strcmp(method, HTTP_POST) == 0) {
        return api_post_drm_license(req);
    }

    /* GET /api/drm/key/{content_id} - Raw key delivery (for HLS EXT-X-KEY) */
    if (strncmp(url, "/api/drm/key/", 13) == 0 && strlen(url) > 13) {
        const char *content_id = url + 13;
        if (strcmp(method, HTTP_GET) == 0) return api_get_drm_raw_key(req, content_id);
        return http_send_error(req->connection, MHD_HTTP_METHOD_NOT_ALLOWED,
                               "Method not allowed");
    }

    /* No matching API route */
    return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                           "API endpoint not found");
}

/* ================================================================
 * GET /api/status
 * ================================================================ */

int api_get_status(http_request_t *req)
{
    sqlite3 *db = db_handle();
    cJSON *root = cJSON_CreateObject();

    /* Basic info */
    cJSON_AddStringToObject(root, "version", VOD_SERVER_VERSION);
    cJSON_AddStringToObject(root, "build_date", VOD_SERVER_BUILD_DATE);
    cJSON_AddStringToObject(root, "node_name",
                            s_config ? s_config->node_name : "unknown");

    /* Uptime */
    time_t now = time(NULL);
    long uptime_sec = (long)(now - s_start_time);
    cJSON_AddNumberToObject(root, "uptime_seconds", uptime_sec);

    /* Format uptime as human-readable */
    {
        int days  = (int)(uptime_sec / 86400);
        int hours = (int)((uptime_sec % 86400) / 3600);
        int mins  = (int)((uptime_sec % 3600) / 60);
        int secs  = (int)(uptime_sec % 60);
        char uptime_str[128];
        snprintf(uptime_str, sizeof(uptime_str), "%dd %dh %dm %ds",
                 days, hours, mins, secs);
        cJSON_AddStringToObject(root, "uptime", uptime_str);
    }

    /* CPU & memory */
    double cpu = get_cpu_usage();
    double mem = get_memory_usage();
    cJSON_AddNumberToObject(root, "cpu_usage", cpu);
    cJSON_AddNumberToObject(root, "memory_usage", mem);

    /* Disk */
    disk_info_t disk;
    if (storage_get_disk_info(&disk) == 0) {
        cJSON *disk_obj = cJSON_CreateObject();
        cJSON_AddNumberToObject(disk_obj, "total_bytes", (double)disk.total_bytes);
        cJSON_AddNumberToObject(disk_obj, "free_bytes",  (double)disk.free_bytes);
        cJSON_AddNumberToObject(disk_obj, "used_bytes",  (double)disk.used_bytes);
        cJSON_AddNumberToObject(disk_obj, "usage_percent", disk.usage_percent);

        char buf[64];
        cJSON_AddStringToObject(disk_obj, "total", format_bytes(disk.total_bytes, buf, sizeof(buf)));
        cJSON_AddStringToObject(disk_obj, "free",  format_bytes(disk.free_bytes,  buf, sizeof(buf)));
        cJSON_AddStringToObject(disk_obj, "used",  format_bytes(disk.used_bytes,  buf, sizeof(buf)));

        cJSON_AddItemToObject(root, "disk", disk_obj);
    }

    /* Active jobs */
    cJSON_AddNumberToObject(root, "active_jobs", job_processor_active_count());

    /* Content count from DB */
    int content_count = 0;
    if (db) {
        sqlite3_stmt *stmt;
        if (sqlite3_prepare_v2(db, "SELECT COUNT(*) FROM content", -1, &stmt, NULL) == SQLITE_OK) {
            if (sqlite3_step(stmt) == SQLITE_ROW) {
                content_count = sqlite3_column_int(stmt, 0);
            }
            sqlite3_finalize(stmt);
        }
    }
    cJSON_AddNumberToObject(root, "content_count", content_count);

    /* Active migrations */
    cJSON_AddNumberToObject(root, "active_migrations", migration_active_count());

    /* FFmpeg / MP4Box availability */
    {
        struct stat st;
        const char *ffmpeg_path  = s_config ? s_config->ffmpeg_path  : "/usr/local/bin/ffmpeg";
        const char *ffprobe_path = s_config ? s_config->ffprobe_path : "/usr/local/bin/ffprobe";
        const char *mp4box_path  = s_config ? s_config->mp4box_path  : "/usr/local/bin/MP4Box";

        cJSON_AddBoolToObject(root, "ffmpeg_available",
                              stat(ffmpeg_path, &st) == 0 && (st.st_mode & S_IXUSR));
        cJSON_AddBoolToObject(root, "ffprobe_available",
                              stat(ffprobe_path, &st) == 0 && (st.st_mode & S_IXUSR));
        cJSON_AddBoolToObject(root, "mp4box_available",
                              stat(mp4box_path, &st) == 0 && (st.st_mode & S_IXUSR));
    }

    /* SSL status */
    cJSON_AddBoolToObject(root, "ssl_enabled",
                          s_config ? s_config->ssl_enabled : false);

    /* DRM status */
    cJSON_AddBoolToObject(root, "drm_enabled",
                          s_config ? s_config->drm_enabled : false);
    if (db) {
        int drm_key_count = 0;
        sqlite3_stmt *drm_stmt;
        if (sqlite3_prepare_v2(db, "SELECT COUNT(*) FROM drm_keys", -1, &drm_stmt, NULL) == SQLITE_OK) {
            if (sqlite3_step(drm_stmt) == SQLITE_ROW) {
                drm_key_count = sqlite3_column_int(drm_stmt, 0);
            }
            sqlite3_finalize(drm_stmt);
        }
        cJSON_AddNumberToObject(root, "drm_key_count", drm_key_count);
    }

    /* Port */
    cJSON_AddNumberToObject(root, "port",
                            s_config ? s_config->port : 8090);

    cJSON_AddStringToObject(root, "status", "running");

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/config
 * ================================================================ */

int api_get_config(http_request_t *req)
{
    if (!s_config) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Configuration not available");
    }

    cJSON *root = cJSON_CreateObject();

    /* Server */
    cJSON *server = cJSON_CreateObject();
    cJSON_AddNumberToObject(server, "port", s_config->port);
    cJSON_AddStringToObject(server, "bind_address", s_config->bind_address);
    cJSON_AddStringToObject(server, "log_level", s_config->log_level);
    cJSON_AddBoolToObject(server, "ssl_enabled", s_config->ssl_enabled);
    cJSON_AddItemToObject(root, "server", server);

    /* Storage */
    cJSON *storage = cJSON_CreateObject();
    cJSON_AddStringToObject(storage, "library_path", s_config->library_path);
    cJSON_AddStringToObject(storage, "temp_path", s_config->temp_path);
    cJSON_AddNumberToObject(storage, "min_free_space_gb", s_config->min_free_space_gb);
    cJSON_AddItemToObject(root, "storage", storage);

    /* Transcoding */
    cJSON *transcoding = cJSON_CreateObject();
    cJSON_AddNumberToObject(transcoding, "max_concurrent_jobs", s_config->max_concurrent_jobs);
    cJSON_AddStringToObject(transcoding, "default_profile", s_config->default_profile);
    cJSON_AddNumberToObject(transcoding, "segment_duration", s_config->segment_duration);
    cJSON_AddNumberToObject(transcoding, "gop_size", s_config->gop_size);
    cJSON_AddStringToObject(transcoding, "hwaccel", s_config->hwaccel);
    cJSON_AddNumberToObject(transcoding, "gpu_device", s_config->gpu_device);
    cJSON_AddStringToObject(transcoding, "ffmpeg_path", s_config->ffmpeg_path);
    cJSON_AddStringToObject(transcoding, "ffprobe_path", s_config->ffprobe_path);
    cJSON_AddItemToObject(root, "transcoding", transcoding);

    /* Profiles */
    cJSON *profiles = cJSON_CreateArray();
    for (int i = 0; i < s_config->profile_count; i++) {
        const transcode_profile_t *p = &s_config->profiles[i];
        cJSON *prof = cJSON_CreateObject();
        cJSON_AddStringToObject(prof, "name", p->name);
        cJSON_AddStringToObject(prof, "codec", p->codec);
        cJSON_AddStringToObject(prof, "preset", p->preset);
        cJSON_AddNumberToObject(prof, "crf", p->crf);
        cJSON_AddStringToObject(prof, "audio_codec", p->audio_codec);
        cJSON_AddStringToObject(prof, "audio_bitrate", p->audio_bitrate);

        cJSON *renditions = cJSON_CreateArray();
        for (int j = 0; j < p->rendition_count; j++) {
            cJSON *r = cJSON_CreateObject();
            cJSON_AddStringToObject(r, "label", p->renditions[j].label);
            cJSON_AddNumberToObject(r, "width", p->renditions[j].width);
            cJSON_AddNumberToObject(r, "height", p->renditions[j].height);
            cJSON_AddNumberToObject(r, "bitrate_kbps", p->renditions[j].bitrate_kbps);
            cJSON_AddItemToArray(renditions, r);
        }
        cJSON_AddItemToObject(prof, "renditions", renditions);
        cJSON_AddItemToArray(profiles, prof);
    }
    cJSON_AddItemToObject(root, "profiles", profiles);

    /* Thumbnails */
    cJSON *thumbs = cJSON_CreateObject();
    cJSON_AddBoolToObject(thumbs, "enabled", s_config->thumbnails_enabled);
    cJSON_AddNumberToObject(thumbs, "interval", s_config->thumb_interval);
    cJSON_AddNumberToObject(thumbs, "width", s_config->thumb_width);
    cJSON_AddNumberToObject(thumbs, "height", s_config->thumb_height);
    cJSON_AddNumberToObject(thumbs, "columns", s_config->thumb_columns);
    cJSON_AddNumberToObject(thumbs, "quality", s_config->thumb_quality);
    cJSON_AddItemToObject(root, "thumbnails", thumbs);

    /* Cluster */
    cJSON *cluster = cJSON_CreateObject();
    cJSON_AddStringToObject(cluster, "node_name", s_config->node_name);
    cJSON_AddNumberToObject(cluster, "health_check_interval", s_config->health_check_interval);
    cJSON_AddNumberToObject(cluster, "offline_threshold", s_config->offline_threshold);
    cJSON_AddNumberToObject(cluster, "max_concurrent_migrations", s_config->max_concurrent_migrations);
    cJSON_AddItemToObject(root, "cluster", cluster);

    /* DRM */
    cJSON *drm = cJSON_CreateObject();
    cJSON_AddBoolToObject(drm, "enabled", s_config->drm_enabled);
    cJSON_AddStringToObject(drm, "scheme", s_config->drm_scheme);
    cJSON_AddStringToObject(drm, "key_server_url", s_config->drm_key_server_url);
    cJSON_AddBoolToObject(drm, "auto_generate", s_config->drm_auto_generate);
    cJSON_AddStringToObject(drm, "clearkey_system_id", CLEARKEY_SYSTEM_ID);
    cJSON_AddStringToObject(drm, "widevine_system_id", WIDEVINE_SYSTEM_ID);
    cJSON_AddItemToObject(root, "drm", drm);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/config
 * ================================================================ */

int api_post_config(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    sqlite3 *db = db_handle();
    if (!db) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /*
     * Check for DRM settings object — apply to runtime config immediately
     * and persist to DB via config_save_db_settings().
     */
    cJSON *drm = cJSON_GetObjectItemCaseSensitive(body, "drm");
    if (cJSON_IsObject(drm)) {
        vod_config_t *cfg = get_mutable_config();
        if (cfg) {
            cJSON *enabled = cJSON_GetObjectItemCaseSensitive(drm, "enabled");
            if (cJSON_IsBool(enabled)) cfg->drm_enabled = cJSON_IsTrue(enabled);

            cJSON *scheme = cJSON_GetObjectItemCaseSensitive(drm, "scheme");
            if (cJSON_IsString(scheme) && scheme->valuestring[0])
                snprintf(cfg->drm_scheme, sizeof(cfg->drm_scheme), "%s", scheme->valuestring);

            cJSON *key_url = cJSON_GetObjectItemCaseSensitive(drm, "key_server_url");
            if (cJSON_IsString(key_url))
                snprintf(cfg->drm_key_server_url, sizeof(cfg->drm_key_server_url), "%s", key_url->valuestring);

            cJSON *auto_gen = cJSON_GetObjectItemCaseSensitive(drm, "auto_generate");
            if (cJSON_IsBool(auto_gen)) cfg->drm_auto_generate = cJSON_IsTrue(auto_gen);

            config_save_db_settings(cfg);
            log_info("DRM settings updated via API (enabled=%s, scheme=%s, auto_generate=%s)",
                     cfg->drm_enabled ? "yes" : "no",
                     cfg->drm_scheme,
                     cfg->drm_auto_generate ? "yes" : "no");
        }
    }

    /*
     * Store each top-level key/value pair into the settings table.
     * This allows runtime-configurable settings to persist across restarts.
     * Skip the 'drm' key since it's handled above.
     */
    sqlite3_stmt *stmt;
    const char *sql = "INSERT OR REPLACE INTO settings (key, value, updated_at) "
                      "VALUES (?, ?, CURRENT_TIMESTAMP)";

    int saved = 0;
    cJSON *item = NULL;
    cJSON_ArrayForEach(item, body) {
        if (!item->string) continue;
        if (strcmp(item->string, "drm") == 0) { saved++; continue; }

        char *val_str = NULL;
        if (cJSON_IsString(item)) {
            val_str = strdup(item->valuestring);
        } else {
            val_str = cJSON_PrintUnformatted(item);
        }
        if (!val_str) continue;

        if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) == SQLITE_OK) {
            sqlite3_bind_text(stmt, 1, item->string, -1, SQLITE_STATIC);
            sqlite3_bind_text(stmt, 2, val_str, -1, SQLITE_STATIC);
            if (sqlite3_step(stmt) == SQLITE_DONE) {
                saved++;
            }
            sqlite3_finalize(stmt);
        }
        free(val_str);
    }

    cJSON_Delete(body);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddNumberToObject(root, "saved", saved);
    cJSON_AddStringToObject(root, "message", "Settings updated.");

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * Profile CRUD helpers
 * ================================================================ */

/**
 * Get a mutable pointer to the config.
 * Safe because the underlying g_config in main.c is non-const.
 */
static vod_config_t *get_mutable_config(void)
{
    return (vod_config_t *)s_config;
}

/**
 * Build a cJSON object from a transcode_profile_t.
 */
static cJSON *profile_to_json(const transcode_profile_t *p)
{
    cJSON *prof = cJSON_CreateObject();
    cJSON_AddStringToObject(prof, "name", p->name);
    cJSON_AddStringToObject(prof, "codec", p->codec);
    cJSON_AddStringToObject(prof, "preset", p->preset);
    cJSON_AddNumberToObject(prof, "crf", p->crf);
    cJSON_AddStringToObject(prof, "audio_codec", p->audio_codec);
    cJSON_AddStringToObject(prof, "audio_bitrate", p->audio_bitrate);

    cJSON *renditions = cJSON_CreateArray();
    for (int j = 0; j < p->rendition_count; j++) {
        cJSON *r = cJSON_CreateObject();
        cJSON_AddStringToObject(r, "label", p->renditions[j].label);
        cJSON_AddNumberToObject(r, "width", p->renditions[j].width);
        cJSON_AddNumberToObject(r, "height", p->renditions[j].height);
        cJSON_AddNumberToObject(r, "bitrate_kbps", p->renditions[j].bitrate_kbps);
        cJSON_AddItemToArray(renditions, r);
    }
    cJSON_AddItemToObject(prof, "renditions", renditions);
    return prof;
}

/**
 * Parse a cJSON body into a transcode_profile_t.
 * Returns 0 on success, -1 on error.
 */
static int json_to_profile(cJSON *body, transcode_profile_t *p)
{
    if (!body || !p) return -1;
    memset(p, 0, sizeof(*p));

    const char *name = json_str(body, "name", "");
    if (name[0] == '\0') return -1;
    snprintf(p->name, sizeof(p->name), "%s", name);

    snprintf(p->codec, sizeof(p->codec), "%s", json_str(body, "codec", "libx264"));
    snprintf(p->preset, sizeof(p->preset), "%s", json_str(body, "preset", "medium"));
    p->crf = json_int(body, "crf", 23);
    snprintf(p->audio_codec, sizeof(p->audio_codec), "%s", json_str(body, "audio_codec", "aac"));
    snprintf(p->audio_bitrate, sizeof(p->audio_bitrate), "%s", json_str(body, "audio_bitrate", "128k"));

    /* Parse renditions array */
    cJSON *rends = cJSON_GetObjectItemCaseSensitive(body, "renditions");
    if (cJSON_IsArray(rends)) {
        cJSON *ritem;
        cJSON_ArrayForEach(ritem, rends) {
            if (p->rendition_count >= MAX_RENDITIONS) break;
            rendition_t *r = &p->renditions[p->rendition_count];

            const char *label = json_str(ritem, "label", "");
            if (label[0] == '\0') continue;
            snprintf(r->label, sizeof(r->label), "%s", label);

            r->width = json_int(ritem, "width", 0);
            r->height = json_int(ritem, "height", 0);
            r->bitrate_kbps = json_int(ritem, "bitrate_kbps", 0);

            if (r->bitrate_kbps <= 0) continue;

            p->rendition_count++;
        }
    }

    /* Also accept renditions as a compact string: "360p:400k,720p:2800k" */
    if (p->rendition_count == 0) {
        const char *rend_str = json_str(body, "renditions_str", "");
        if (rend_str[0] != '\0') {
            /* Reuse existing parse_renditions from config loading */
            char buf[MAX_VALUE_LEN];
            snprintf(buf, sizeof(buf), "%s", rend_str);
            char *saveptr = NULL;
            char *token = strtok_r(buf, ",", &saveptr);
            while (token && p->rendition_count < MAX_RENDITIONS) {
                while (*token == ' ') token++;
                char *colon = strchr(token, ':');
                if (colon) {
                    *colon = '\0';
                    rendition_t *r = &p->renditions[p->rendition_count];
                    snprintf(r->label, sizeof(r->label), "%s", token);
                    r->bitrate_kbps = atoi(colon + 1);
                    /* Lookup resolution from label */
                    r->width = 0; r->height = 0;
                    if (strcasecmp(token, "360p") == 0)       { r->width = 640;  r->height = 360;  }
                    else if (strcasecmp(token, "480p") == 0)  { r->width = 854;  r->height = 480;  }
                    else if (strcasecmp(token, "576p") == 0)  { r->width = 1024; r->height = 576;  }
                    else if (strcasecmp(token, "720p") == 0)  { r->width = 1280; r->height = 720;  }
                    else if (strcasecmp(token, "1080p") == 0) { r->width = 1920; r->height = 1080; }
                    else if (strcasecmp(token, "1440p") == 0) { r->width = 2560; r->height = 1440; }
                    else if (strcasecmp(token, "2160p") == 0) { r->width = 3840; r->height = 2160; }
                    if (r->bitrate_kbps > 0) p->rendition_count++;
                }
                token = strtok_r(NULL, ",", &saveptr);
            }
        }
    }

    return 0;
}

/* ================================================================
 * GET /api/profiles — List all transcode profiles
 * ================================================================ */

int api_get_profiles(http_request_t *req)
{
    if (!s_config) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Configuration not available");
    }

    cJSON *root = cJSON_CreateObject();
    cJSON *profiles = cJSON_CreateArray();
    for (int i = 0; i < s_config->profile_count; i++) {
        cJSON_AddItemToArray(profiles, profile_to_json(&s_config->profiles[i]));
    }
    cJSON_AddItemToObject(root, "profiles", profiles);
    cJSON_AddNumberToObject(root, "count", s_config->profile_count);
    cJSON_AddNumberToObject(root, "max_profiles", MAX_PROFILES);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/profiles — Create a new transcode profile
 * ================================================================ */

int api_post_profile(http_request_t *req)
{
    vod_config_t *config = get_mutable_config();
    if (!config) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Configuration not available");
    }

    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    transcode_profile_t profile;
    if (json_to_profile(body, &profile) != 0) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid profile data: 'name' is required");
    }

    /* Check if name already exists */
    if (config_get_profile(config, profile.name) != NULL) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_CONFLICT,
                               "Profile with this name already exists");
    }

    if (config_set_profile(config, &profile) != 0) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to add profile (max profiles reached)");
    }

    /* Persist to DB */
    config_save_db_profiles(config);

    cJSON_Delete(body);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Profile created");
    cJSON_AddItemToObject(root, "profile", profile_to_json(&profile));

    log_info("Profile created: %s (codec=%s, %d renditions)",
             profile.name, profile.codec, profile.rendition_count);

    return send_json_obj(req->connection, MHD_HTTP_CREATED, root);
}

/* ================================================================
 * PUT /api/profiles/{name} — Update an existing profile
 * ================================================================ */

int api_put_profile(http_request_t *req, const char *name)
{
    vod_config_t *config = get_mutable_config();
    if (!config) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Configuration not available");
    }

    if (config_get_profile(config, name) == NULL) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Profile not found");
    }

    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    transcode_profile_t profile;
    if (json_to_profile(body, &profile) != 0) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid profile data");
    }

    /* Ensure name matches URL */
    snprintf(profile.name, sizeof(profile.name), "%s", name);

    config_set_profile(config, &profile);
    config_save_db_profiles(config);

    cJSON_Delete(body);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Profile updated");
    cJSON_AddItemToObject(root, "profile", profile_to_json(&profile));

    log_info("Profile updated: %s (codec=%s, %d renditions)",
             profile.name, profile.codec, profile.rendition_count);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * DELETE /api/profiles/{name} — Delete a profile
 * ================================================================ */

int api_delete_profile(http_request_t *req, const char *name)
{
    vod_config_t *config = get_mutable_config();
    if (!config) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Configuration not available");
    }

    if (config_delete_profile(config, name) != 0) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Profile not found");
    }

    config_save_db_profiles(config);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Profile deleted");

    log_info("Profile deleted: %s", name);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/content
 * ================================================================ */

int api_get_content_list(http_request_t *req)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /* Query parameters */
    const char *status_filter = http_get_param(req->connection, "status");
    const char *limit_str     = http_get_param(req->connection, "limit");
    const char *offset_str    = http_get_param(req->connection, "offset");
    const char *search        = http_get_param(req->connection, "search");

    int limit  = limit_str  ? atoi(limit_str)  : 50;
    int offset = offset_str ? atoi(offset_str) : 0;
    if (limit <= 0)  limit = 50;
    if (limit > 500) limit = 500;
    if (offset < 0)  offset = 0;

    /* Build query dynamically */
    char sql[1024];
    char count_sql[512];
    int param_idx = 0;

    if (status_filter && status_filter[0] != '\0') {
        if (search && search[0] != '\0') {
            snprintf(sql, sizeof(sql),
                     "SELECT id, content_id, title, path, codec, renditions, "
                     "duration, size_bytes, has_thumbnails, has_subtitles, "
                     "hls_path, dash_path, status, created_at, updated_at "
                     "FROM content WHERE status = ? AND (title LIKE ? OR content_id LIKE ?) "
                     "ORDER BY created_at DESC LIMIT ? OFFSET ?");
            snprintf(count_sql, sizeof(count_sql),
                     "SELECT COUNT(*) FROM content WHERE status = ? AND (title LIKE ? OR content_id LIKE ?)");
        } else {
            snprintf(sql, sizeof(sql),
                     "SELECT id, content_id, title, path, codec, renditions, "
                     "duration, size_bytes, has_thumbnails, has_subtitles, "
                     "hls_path, dash_path, status, created_at, updated_at "
                     "FROM content WHERE status = ? "
                     "ORDER BY created_at DESC LIMIT ? OFFSET ?");
            snprintf(count_sql, sizeof(count_sql),
                     "SELECT COUNT(*) FROM content WHERE status = ?");
        }
    } else {
        if (search && search[0] != '\0') {
            snprintf(sql, sizeof(sql),
                     "SELECT id, content_id, title, path, codec, renditions, "
                     "duration, size_bytes, has_thumbnails, has_subtitles, "
                     "hls_path, dash_path, status, created_at, updated_at "
                     "FROM content WHERE title LIKE ? OR content_id LIKE ? "
                     "ORDER BY created_at DESC LIMIT ? OFFSET ?");
            snprintf(count_sql, sizeof(count_sql),
                     "SELECT COUNT(*) FROM content WHERE title LIKE ? OR content_id LIKE ?");
        } else {
            snprintf(sql, sizeof(sql),
                     "SELECT id, content_id, title, path, codec, renditions, "
                     "duration, size_bytes, has_thumbnails, has_subtitles, "
                     "hls_path, dash_path, status, created_at, updated_at "
                     "FROM content ORDER BY created_at DESC LIMIT ? OFFSET ?");
            snprintf(count_sql, sizeof(count_sql),
                     "SELECT COUNT(*) FROM content");
        }
    }

    /* Get total count */
    int total = 0;
    {
        sqlite3_stmt *cnt_stmt;
        if (sqlite3_prepare_v2(db, count_sql, -1, &cnt_stmt, NULL) == SQLITE_OK) {
            param_idx = 1;
            if (status_filter && status_filter[0] != '\0') {
                sqlite3_bind_text(cnt_stmt, param_idx++, status_filter, -1, SQLITE_STATIC);
            }
            if (search && search[0] != '\0') {
                char like_buf[256];
                snprintf(like_buf, sizeof(like_buf), "%%%s%%", search);
                sqlite3_bind_text(cnt_stmt, param_idx++, like_buf, -1, SQLITE_TRANSIENT);
                sqlite3_bind_text(cnt_stmt, param_idx++, like_buf, -1, SQLITE_TRANSIENT);
            }
            if (sqlite3_step(cnt_stmt) == SQLITE_ROW) {
                total = sqlite3_column_int(cnt_stmt, 0);
            }
            sqlite3_finalize(cnt_stmt);
        }
    }

    /* Execute main query */
    sqlite3_stmt *stmt;
    int rc = sqlite3_prepare_v2(db, sql, -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("SQL prepare error (content list): %s", sqlite3_errmsg(db));
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }

    param_idx = 1;
    if (status_filter && status_filter[0] != '\0') {
        sqlite3_bind_text(stmt, param_idx++, status_filter, -1, SQLITE_STATIC);
    }
    if (search && search[0] != '\0') {
        char like_buf[256];
        snprintf(like_buf, sizeof(like_buf), "%%%s%%", search);
        sqlite3_bind_text(stmt, param_idx++, like_buf, -1, SQLITE_TRANSIENT);
        sqlite3_bind_text(stmt, param_idx++, like_buf, -1, SQLITE_TRANSIENT);
    }
    sqlite3_bind_int(stmt, param_idx++, limit);
    sqlite3_bind_int(stmt, param_idx++, offset);

    cJSON *items = cJSON_CreateArray();
    while (sqlite3_step(stmt) == SQLITE_ROW) {
        cJSON *obj = cJSON_CreateObject();
        cJSON_AddNumberToObject(obj, "id",
                                sqlite3_column_int(stmt, 0));
        cJSON_AddStringToObject(obj, "content_id",
                                (const char *)sqlite3_column_text(stmt, 1) ?: "");
        cJSON_AddStringToObject(obj, "title",
                                (const char *)sqlite3_column_text(stmt, 2) ?: "");
        cJSON_AddStringToObject(obj, "path",
                                (const char *)sqlite3_column_text(stmt, 3) ?: "");
        cJSON_AddStringToObject(obj, "codec",
                                (const char *)sqlite3_column_text(stmt, 4) ?: "");

        /* Renditions stored as JSON string in DB */
        const char *rend_str = (const char *)sqlite3_column_text(stmt, 5);
        if (rend_str && rend_str[0] == '[') {
            cJSON *rend = cJSON_Parse(rend_str);
            if (rend) {
                cJSON_AddItemToObject(obj, "renditions", rend);
            } else {
                cJSON_AddStringToObject(obj, "renditions", rend_str);
            }
        } else {
            cJSON_AddStringToObject(obj, "renditions", rend_str ?: "");
        }

        cJSON_AddNumberToObject(obj, "duration",
                                sqlite3_column_double(stmt, 6));
        cJSON_AddNumberToObject(obj, "size_bytes",
                                (double)sqlite3_column_int64(stmt, 7));

        char size_buf[64];
        format_bytes((uint64_t)sqlite3_column_int64(stmt, 7), size_buf, sizeof(size_buf));
        cJSON_AddStringToObject(obj, "size_human", size_buf);

        cJSON_AddBoolToObject(obj, "has_thumbnails",
                              sqlite3_column_int(stmt, 8));
        cJSON_AddBoolToObject(obj, "has_subtitles",
                              sqlite3_column_int(stmt, 9));
        cJSON_AddStringToObject(obj, "hls_path",
                                (const char *)sqlite3_column_text(stmt, 10) ?: "");
        cJSON_AddStringToObject(obj, "dash_path",
                                (const char *)sqlite3_column_text(stmt, 11) ?: "");
        cJSON_AddStringToObject(obj, "status",
                                (const char *)sqlite3_column_text(stmt, 12) ?: "");
        cJSON_AddStringToObject(obj, "created_at",
                                (const char *)sqlite3_column_text(stmt, 13) ?: "");
        cJSON_AddStringToObject(obj, "updated_at",
                                (const char *)sqlite3_column_text(stmt, 14) ?: "");

        cJSON_AddItemToArray(items, obj);
    }
    sqlite3_finalize(stmt);

    /* Build response envelope */
    cJSON *root = cJSON_CreateObject();
    cJSON_AddItemToObject(root, "items", items);
    cJSON_AddNumberToObject(root, "total", total);
    cJSON_AddNumberToObject(root, "limit", limit);
    cJSON_AddNumberToObject(root, "offset", offset);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/content/{id}
 * ================================================================ */

int api_get_content(http_request_t *req, const char *content_id)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    const char *sql =
        "SELECT id, content_id, title, path, source_file, codec, renditions, "
        "duration, size_bytes, has_thumbnails, has_subtitles, subtitle_tracks, "
        "hls_path, dash_path, metadata, status, created_at, updated_at "
        "FROM content WHERE content_id = ?";

    sqlite3_stmt *stmt;
    int rc = sqlite3_prepare_v2(db, sql, -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("SQL prepare error (get content): %s", sqlite3_errmsg(db));
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_STATIC);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Content not found");
    }

    cJSON *obj = cJSON_CreateObject();
    cJSON_AddNumberToObject(obj, "id",
                            sqlite3_column_int(stmt, 0));
    cJSON_AddStringToObject(obj, "content_id",
                            (const char *)sqlite3_column_text(stmt, 1) ?: "");
    cJSON_AddStringToObject(obj, "title",
                            (const char *)sqlite3_column_text(stmt, 2) ?: "");
    cJSON_AddStringToObject(obj, "path",
                            (const char *)sqlite3_column_text(stmt, 3) ?: "");
    cJSON_AddStringToObject(obj, "source_file",
                            (const char *)sqlite3_column_text(stmt, 4) ?: "");
    cJSON_AddStringToObject(obj, "codec",
                            (const char *)sqlite3_column_text(stmt, 5) ?: "");

    /* Renditions - parse JSON string */
    const char *rend_str = (const char *)sqlite3_column_text(stmt, 6);
    if (rend_str && rend_str[0] == '[') {
        cJSON *rend = cJSON_Parse(rend_str);
        if (rend) {
            cJSON_AddItemToObject(obj, "renditions", rend);
        } else {
            cJSON_AddStringToObject(obj, "renditions", rend_str);
        }
    } else {
        cJSON_AddNullToObject(obj, "renditions");
    }

    cJSON_AddNumberToObject(obj, "duration",
                            sqlite3_column_double(stmt, 7));
    cJSON_AddNumberToObject(obj, "size_bytes",
                            (double)sqlite3_column_int64(stmt, 8));

    char size_buf[64];
    format_bytes((uint64_t)sqlite3_column_int64(stmt, 8), size_buf, sizeof(size_buf));
    cJSON_AddStringToObject(obj, "size_human", size_buf);

    cJSON_AddBoolToObject(obj, "has_thumbnails",
                          sqlite3_column_int(stmt, 9));
    cJSON_AddBoolToObject(obj, "has_subtitles",
                          sqlite3_column_int(stmt, 10));

    /* Subtitle tracks - parse JSON */
    const char *sub_str = (const char *)sqlite3_column_text(stmt, 11);
    if (sub_str && sub_str[0] == '[') {
        cJSON *sub = cJSON_Parse(sub_str);
        if (sub) {
            cJSON_AddItemToObject(obj, "subtitle_tracks", sub);
        } else {
            cJSON_AddNullToObject(obj, "subtitle_tracks");
        }
    } else {
        cJSON_AddNullToObject(obj, "subtitle_tracks");
    }

    cJSON_AddStringToObject(obj, "hls_path",
                            (const char *)sqlite3_column_text(stmt, 12) ?: "");
    cJSON_AddStringToObject(obj, "dash_path",
                            (const char *)sqlite3_column_text(stmt, 13) ?: "");

    /* Metadata - parse JSON */
    const char *meta_str = (const char *)sqlite3_column_text(stmt, 14);
    if (meta_str && (meta_str[0] == '{' || meta_str[0] == '[')) {
        cJSON *meta = cJSON_Parse(meta_str);
        if (meta) {
            cJSON_AddItemToObject(obj, "metadata", meta);
        } else {
            cJSON_AddStringToObject(obj, "metadata", meta_str);
        }
    } else {
        cJSON_AddNullToObject(obj, "metadata");
    }

    cJSON_AddStringToObject(obj, "status",
                            (const char *)sqlite3_column_text(stmt, 15) ?: "");
    cJSON_AddStringToObject(obj, "created_at",
                            (const char *)sqlite3_column_text(stmt, 16) ?: "");
    cJSON_AddStringToObject(obj, "updated_at",
                            (const char *)sqlite3_column_text(stmt, 17) ?: "");

    sqlite3_finalize(stmt);

    return send_json_obj(req->connection, MHD_HTTP_OK, obj);
}

/* ================================================================
 * GET /api/content/{id}/download
 * ================================================================ */

int api_get_content_download(http_request_t *req, const char *content_id)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /* Look up the content path */
    const char *sql = "SELECT path FROM content WHERE content_id = ?";
    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_STATIC);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Content not found");
    }

    const char *path = (const char *)sqlite3_column_text(stmt, 0);
    if (!path || path[0] == '\0') {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Content path not available");
    }

    /* Determine which file to serve - prefer HLS master playlist */
    char filepath[MAX_PATH_LEN];
    snprintf(filepath, sizeof(filepath), "%s/master.m3u8", path);

    /* If no HLS, try DASH */
    struct stat st;
    if (stat(filepath, &st) != 0) {
        snprintf(filepath, sizeof(filepath), "%s/manifest.mpd", path);
    }

    /* If no DASH, try any mp4 */
    if (stat(filepath, &st) != 0) {
        snprintf(filepath, sizeof(filepath), "%s/output.mp4", path);
    }

    sqlite3_finalize(stmt);

    return http_send_file(req->connection, filepath);
}

/* ================================================================
 * DELETE /api/content/{id}
 * ================================================================ */

int api_delete_content(http_request_t *req, const char *content_id)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /* Check the content exists */
    sqlite3_stmt *stmt;
    const char *check_sql = "SELECT id, title FROM content WHERE content_id = ?";
    if (sqlite3_prepare_v2(db, check_sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_STATIC);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Content not found");
    }
    int db_id = sqlite3_column_int(stmt, 0);
    const char *title = (const char *)sqlite3_column_text(stmt, 1);
    log_info("Deleting content: %s (id=%d, content_id=%s)",
             title ?: "untitled", db_id, content_id);
    sqlite3_finalize(stmt);

    /* Remove files from storage */
    storage_remove_content(content_id);

    /* Delete from database */
    const char *del_sql = "DELETE FROM content WHERE content_id = ?";
    if (sqlite3_prepare_v2(db, del_sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database delete failed");
    }
    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_STATIC);
    int rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        log_error("Failed to delete content %s from database: %s",
                  content_id, sqlite3_errmsg(db));
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database delete failed");
    }

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Content deleted successfully");
    cJSON_AddStringToObject(root, "content_id", content_id);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/jobs
 * ================================================================ */

int api_get_jobs(http_request_t *req)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    const char *status_filter = http_get_param(req->connection, "status");
    const char *limit_str     = http_get_param(req->connection, "limit");
    const char *offset_str    = http_get_param(req->connection, "offset");

    int limit  = limit_str  ? atoi(limit_str)  : 50;
    int offset = offset_str ? atoi(offset_str) : 0;
    if (limit <= 0)  limit = 50;
    if (limit > 500) limit = 500;
    if (offset < 0)  offset = 0;

    char sql[512];
    if (status_filter && status_filter[0] != '\0') {
        snprintf(sql, sizeof(sql),
                 "SELECT id, content_id, title, source_path, source_type, profile, "
                 "status, progress, current_step, error_msg, priority, callback_url, "
                 "metadata, pid, bytes_processed, bytes_total, created_at, started_at, completed_at "
                 "FROM jobs WHERE status = ? ORDER BY priority ASC, created_at DESC "
                 "LIMIT ? OFFSET ?");
    } else {
        snprintf(sql, sizeof(sql),
                 "SELECT id, content_id, title, source_path, source_type, profile, "
                 "status, progress, current_step, error_msg, priority, callback_url, "
                 "metadata, pid, bytes_processed, bytes_total, created_at, started_at, completed_at "
                 "FROM jobs ORDER BY priority ASC, created_at DESC "
                 "LIMIT ? OFFSET ?");
    }

    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) != SQLITE_OK) {
        log_error("SQL prepare error (get jobs): %s", sqlite3_errmsg(db));
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }

    int param_idx = 1;
    if (status_filter && status_filter[0] != '\0') {
        sqlite3_bind_text(stmt, param_idx++, status_filter, -1, SQLITE_STATIC);
    }
    sqlite3_bind_int(stmt, param_idx++, limit);
    sqlite3_bind_int(stmt, param_idx++, offset);

    cJSON *items = cJSON_CreateArray();
    while (sqlite3_step(stmt) == SQLITE_ROW) {
        cJSON *obj = cJSON_CreateObject();
        cJSON_AddNumberToObject(obj, "id",              sqlite3_column_int(stmt, 0));
        cJSON_AddStringToObject(obj, "content_id",      (const char *)sqlite3_column_text(stmt, 1) ?: "");
        cJSON_AddStringToObject(obj, "title",           (const char *)sqlite3_column_text(stmt, 2) ?: "");
        cJSON_AddStringToObject(obj, "source_path",     (const char *)sqlite3_column_text(stmt, 3) ?: "");
        cJSON_AddStringToObject(obj, "source_type",     (const char *)sqlite3_column_text(stmt, 4) ?: "file");
        cJSON_AddStringToObject(obj, "profile",         (const char *)sqlite3_column_text(stmt, 5) ?: "");
        cJSON_AddStringToObject(obj, "status",          (const char *)sqlite3_column_text(stmt, 6) ?: "");
        cJSON_AddNumberToObject(obj, "progress",        sqlite3_column_double(stmt, 7));
        cJSON_AddStringToObject(obj, "current_step",    (const char *)sqlite3_column_text(stmt, 8) ?: "");
        cJSON_AddStringToObject(obj, "error_msg",       (const char *)sqlite3_column_text(stmt, 9) ?: "");
        cJSON_AddNumberToObject(obj, "priority",        sqlite3_column_int(stmt, 10));
        cJSON_AddStringToObject(obj, "callback_url",    (const char *)sqlite3_column_text(stmt, 11) ?: "");

        /* Metadata JSON */
        const char *meta_str = (const char *)sqlite3_column_text(stmt, 12);
        if (meta_str && (meta_str[0] == '{' || meta_str[0] == '[')) {
            cJSON *meta = cJSON_Parse(meta_str);
            if (meta) cJSON_AddItemToObject(obj, "metadata", meta);
            else      cJSON_AddNullToObject(obj, "metadata");
        } else {
            cJSON_AddNullToObject(obj, "metadata");
        }

        cJSON_AddNumberToObject(obj, "pid",             sqlite3_column_int(stmt, 13));
        cJSON_AddNumberToObject(obj, "bytes_processed",  (double)sqlite3_column_int64(stmt, 14));
        cJSON_AddNumberToObject(obj, "bytes_total",      (double)sqlite3_column_int64(stmt, 15));
        cJSON_AddStringToObject(obj, "created_at",      (const char *)sqlite3_column_text(stmt, 16) ?: "");
        cJSON_AddStringToObject(obj, "started_at",      (const char *)sqlite3_column_text(stmt, 17) ?: "");
        cJSON_AddStringToObject(obj, "completed_at",    (const char *)sqlite3_column_text(stmt, 18) ?: "");

        cJSON_AddItemToArray(items, obj);
    }
    sqlite3_finalize(stmt);

    /* Get total count */
    int total = 0;
    {
        char cnt_sql[256];
        if (status_filter && status_filter[0] != '\0') {
            snprintf(cnt_sql, sizeof(cnt_sql),
                     "SELECT COUNT(*) FROM jobs WHERE status = ?");
        } else {
            snprintf(cnt_sql, sizeof(cnt_sql),
                     "SELECT COUNT(*) FROM jobs");
        }
        sqlite3_stmt *cnt_stmt;
        if (sqlite3_prepare_v2(db, cnt_sql, -1, &cnt_stmt, NULL) == SQLITE_OK) {
            if (status_filter && status_filter[0] != '\0') {
                sqlite3_bind_text(cnt_stmt, 1, status_filter, -1, SQLITE_STATIC);
            }
            if (sqlite3_step(cnt_stmt) == SQLITE_ROW) {
                total = sqlite3_column_int(cnt_stmt, 0);
            }
            sqlite3_finalize(cnt_stmt);
        }
    }

    cJSON *root = cJSON_CreateObject();
    cJSON_AddItemToObject(root, "items", items);
    cJSON_AddNumberToObject(root, "total", total);
    cJSON_AddNumberToObject(root, "limit", limit);
    cJSON_AddNumberToObject(root, "offset", offset);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/jobs
 * ================================================================ */

int api_post_job(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    sqlite3 *db = db_handle();
    if (!db) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /* Required fields */
    const char *content_id  = json_str(body, "content_id", NULL);
    const char *source_path = json_str(body, "source_path", NULL);

    if (!content_id || content_id[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: content_id");
    }
    if (!source_path || source_path[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: source_path");
    }

    /* Optional fields */
    const char *title        = json_str(body, "title", content_id);
    const char *source_type  = json_str(body, "source_type", "file");
    const char *profile      = json_str(body, "profile",
                                        s_config ? s_config->default_profile : "standard");
    int         priority     = json_int(body, "priority", 5);
    const char *callback_url = json_str(body, "callback_url", NULL);

    /* Validate priority range */
    if (priority < 1)  priority = 1;
    if (priority > 10) priority = 10;

    /* Validate profile exists */
    if (s_config && config_get_profile(s_config, profile) == NULL) {
        log_warn("Job submitted with unknown profile '%s', using default", profile);
        profile = s_config->default_profile;
    }

    /* Check disk space */
    if (!storage_has_space()) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INSUFFICIENT_STORAGE,
                               "Insufficient disk space for new job");
    }

    /* Serialise metadata to JSON string */
    cJSON *meta_obj = cJSON_GetObjectItemCaseSensitive(body, "metadata");
    char *meta_str = NULL;
    if (meta_obj) {
        meta_str = cJSON_PrintUnformatted(meta_obj);
    }

    /* Insert into database */
    const char *sql =
        "INSERT INTO jobs (content_id, title, source_path, source_type, profile, "
        "priority, callback_url, metadata, status, created_at) "
        "VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)";

    sqlite3_stmt *stmt;
    int rc = sqlite3_prepare_v2(db, sql, -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("SQL prepare error (post job): %s", sqlite3_errmsg(db));
        free(meta_str);
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database insert failed");
    }

    sqlite3_bind_text(stmt, 1, content_id,    -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 2, title,         -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 3, source_path,   -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 4, source_type,   -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 5, profile,       -1, SQLITE_STATIC);
    sqlite3_bind_int(stmt,  6, priority);
    sqlite3_bind_text(stmt, 7, callback_url ? callback_url : "", -1, SQLITE_STATIC);
    sqlite3_bind_text(stmt, 8, meta_str ? meta_str : "{}", -1, SQLITE_TRANSIENT);

    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    free(meta_str);

    if (rc != SQLITE_DONE) {
        log_error("Failed to insert job: %s", sqlite3_errmsg(db));
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to create job");
    }

    long long job_id = db_last_insert_id();
    log_info("Created job %lld: content_id=%s, profile=%s, priority=%d",
             job_id, content_id, profile, priority);

    cJSON_Delete(body);

    /* Return the created job */
    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Job created successfully");
    cJSON *job = cJSON_CreateObject();
    cJSON_AddNumberToObject(job, "id", (double)job_id);
    cJSON_AddStringToObject(job, "content_id", content_id);
    cJSON_AddStringToObject(job, "title", title);
    cJSON_AddStringToObject(job, "source_path", source_path);
    cJSON_AddStringToObject(job, "source_type", source_type);
    cJSON_AddStringToObject(job, "profile", profile);
    cJSON_AddNumberToObject(job, "priority", priority);
    cJSON_AddStringToObject(job, "status", "pending");
    cJSON_AddNumberToObject(job, "progress", 0.0);
    cJSON_AddItemToObject(root, "job", job);

    return send_json_obj(req->connection, MHD_HTTP_CREATED, root);
}

/* ================================================================
 * GET /api/jobs/{id}
 * ================================================================ */

int api_get_job(http_request_t *req, int job_id)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    const char *sql =
        "SELECT id, content_id, title, source_path, source_type, profile, "
        "status, progress, current_step, error_msg, priority, callback_url, "
        "metadata, pid, bytes_processed, bytes_total, created_at, started_at, completed_at "
        "FROM jobs WHERE id = ?";

    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_int(stmt, 1, job_id);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Job not found");
    }

    cJSON *obj = cJSON_CreateObject();
    cJSON_AddNumberToObject(obj, "id",              sqlite3_column_int(stmt, 0));
    cJSON_AddStringToObject(obj, "content_id",      (const char *)sqlite3_column_text(stmt, 1) ?: "");
    cJSON_AddStringToObject(obj, "title",           (const char *)sqlite3_column_text(stmt, 2) ?: "");
    cJSON_AddStringToObject(obj, "source_path",     (const char *)sqlite3_column_text(stmt, 3) ?: "");
    cJSON_AddStringToObject(obj, "source_type",     (const char *)sqlite3_column_text(stmt, 4) ?: "file");
    cJSON_AddStringToObject(obj, "profile",         (const char *)sqlite3_column_text(stmt, 5) ?: "");
    cJSON_AddStringToObject(obj, "status",          (const char *)sqlite3_column_text(stmt, 6) ?: "");
    cJSON_AddNumberToObject(obj, "progress",        sqlite3_column_double(stmt, 7));
    cJSON_AddStringToObject(obj, "current_step",    (const char *)sqlite3_column_text(stmt, 8) ?: "");
    cJSON_AddStringToObject(obj, "error_msg",       (const char *)sqlite3_column_text(stmt, 9) ?: "");
    cJSON_AddNumberToObject(obj, "priority",        sqlite3_column_int(stmt, 10));
    cJSON_AddStringToObject(obj, "callback_url",    (const char *)sqlite3_column_text(stmt, 11) ?: "");

    /* Metadata JSON */
    const char *meta_str = (const char *)sqlite3_column_text(stmt, 12);
    if (meta_str && (meta_str[0] == '{' || meta_str[0] == '[')) {
        cJSON *meta = cJSON_Parse(meta_str);
        if (meta) cJSON_AddItemToObject(obj, "metadata", meta);
        else      cJSON_AddNullToObject(obj, "metadata");
    } else {
        cJSON_AddNullToObject(obj, "metadata");
    }

    cJSON_AddNumberToObject(obj, "pid",             sqlite3_column_int(stmt, 13));
    cJSON_AddNumberToObject(obj, "bytes_processed",  (double)sqlite3_column_int64(stmt, 14));
    cJSON_AddNumberToObject(obj, "bytes_total",      (double)sqlite3_column_int64(stmt, 15));
    cJSON_AddStringToObject(obj, "created_at",      (const char *)sqlite3_column_text(stmt, 16) ?: "");
    cJSON_AddStringToObject(obj, "started_at",      (const char *)sqlite3_column_text(stmt, 17) ?: "");
    cJSON_AddStringToObject(obj, "completed_at",    (const char *)sqlite3_column_text(stmt, 18) ?: "");

    sqlite3_finalize(stmt);

    return send_json_obj(req->connection, MHD_HTTP_OK, obj);
}

/* ================================================================
 * DELETE /api/jobs/{id}
 * ================================================================ */

int api_delete_job(http_request_t *req, int job_id)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /* Check if caller wants source files cleaned up too */
    const char *cleanup_param = http_get_param(req->connection, "cleanup");
    int cleanup_source = (cleanup_param && strcmp(cleanup_param, "files") == 0);

    /* Fetch job status, PID, and source path */
    const char *check_sql = "SELECT status, pid, source_path FROM jobs WHERE id = ?";
    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, check_sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_int(stmt, 1, job_id);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Job not found");
    }

    const char *status = (const char *)sqlite3_column_text(stmt, 0);
    int pid = sqlite3_column_int(stmt, 1);
    const char *source_raw = (const char *)sqlite3_column_text(stmt, 2);
    int is_active = (status && (strcmp(status, "processing") == 0 ||
                                strcmp(status, "running") == 0));

    /* Copy source_path before finalizing (SQLite memory) */
    char source_path[2048] = {0};
    if (source_raw && source_raw[0]) {
        snprintf(source_path, sizeof(source_path), "%s", source_raw);
    }
    sqlite3_finalize(stmt);

    /* If the job is currently active, try to kill the FFmpeg process */
    if (is_active && pid > 0) {
        log_info("Cancelling active job %d (PID %d)", job_id, pid);
        kill(pid, SIGTERM);
        /* Give it a moment to die gracefully */
        usleep(100000); /* 100ms */
    }

    /* Update status to cancelled (or delete if pending) */
    const char *update_sql;
    if (is_active) {
        update_sql = "UPDATE jobs SET status = 'cancelled', "
                     "error_msg = 'Cancelled by API request', "
                     "completed_at = CURRENT_TIMESTAMP WHERE id = ?";
    } else if (status && strcmp(status, "pending") == 0) {
        update_sql = "DELETE FROM jobs WHERE id = ?";
    } else {
        /* Completed/failed/cancelled - delete the record */
        update_sql = "DELETE FROM jobs WHERE id = ?";
    }

    if (sqlite3_prepare_v2(db, update_sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database update failed");
    }
    sqlite3_bind_int(stmt, 1, job_id);
    int rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to cancel/delete job");
    }

    /* Clean up temp files */
    storage_cleanup_temp(job_id);

    /* Delete source file if cleanup=files and it's in the uploads dir */
    int source_deleted = 0;
    if (cleanup_source && source_path[0]) {
        /* Safety: only delete files inside the uploads directory */
        if (strstr(source_path, "/uploads/") != NULL &&
            strstr(source_path, "..") == NULL) {
            struct stat st;
            if (stat(source_path, &st) == 0 && S_ISREG(st.st_mode)) {
                if (unlink(source_path) == 0) {
                    log_info("Deleted source file: %s", source_path);
                    source_deleted = 1;
                } else {
                    log_warn("Failed to delete source file: %s (%s)",
                             source_path, strerror(errno));
                }
            }
        }
    }

    log_info("Job %d %s", job_id, is_active ? "cancelled" : "deleted");

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message",
                            is_active ? "Job cancelled" : "Job deleted");
    cJSON_AddNumberToObject(root, "job_id", job_id);
    cJSON_AddBoolToObject(root, "source_deleted", source_deleted);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * DELETE /api/jobs?status=failed&cleanup=files
 * Bulk-delete jobs by status. Optionally remove source files.
 * ================================================================ */

int api_bulk_delete_jobs(http_request_t *req)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    const char *status_filter = http_get_param(req->connection, "status");
    if (!status_filter || status_filter[0] == '\0') {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required parameter: status (e.g. failed, complete, cancelled)");
    }

    /* Only allow bulk delete of terminal states */
    if (strcmp(status_filter, "failed") != 0 &&
        strcmp(status_filter, "complete") != 0 &&
        strcmp(status_filter, "cancelled") != 0) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Can only bulk-delete failed, complete, or cancelled jobs");
    }

    const char *cleanup_param = http_get_param(req->connection, "cleanup");
    int cleanup_source = (cleanup_param && strcmp(cleanup_param, "files") == 0);

    /* First collect the jobs to delete (need source_path for cleanup) */
    const char *select_sql = "SELECT id, source_path FROM jobs WHERE status = ?";
    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, select_sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_text(stmt, 1, status_filter, -1, SQLITE_STATIC);

    int deleted = 0;
    int files_deleted = 0;

    /* Collect job IDs and source paths */
    typedef struct { int id; char source[2048]; } job_entry_t;
    job_entry_t entries[500];  /* max 500 at a time */
    int count = 0;

    while (sqlite3_step(stmt) == SQLITE_ROW && count < 500) {
        entries[count].id = sqlite3_column_int(stmt, 0);
        const char *sp = (const char *)sqlite3_column_text(stmt, 1);
        if (sp) {
            snprintf(entries[count].source, sizeof(entries[count].source), "%s", sp);
        } else {
            entries[count].source[0] = '\0';
        }
        count++;
    }
    sqlite3_finalize(stmt);

    if (count == 0) {
        cJSON *root = cJSON_CreateObject();
        cJSON_AddBoolToObject(root, "success", 1);
        cJSON_AddStringToObject(root, "message", "No jobs to delete");
        cJSON_AddNumberToObject(root, "deleted", 0);
        return send_json_obj(req->connection, MHD_HTTP_OK, root);
    }

    /* Delete the records */
    const char *del_sql = "DELETE FROM jobs WHERE status = ?";
    if (sqlite3_prepare_v2(db, del_sql, -1, &stmt, NULL) == SQLITE_OK) {
        sqlite3_bind_text(stmt, 1, status_filter, -1, SQLITE_STATIC);
        if (sqlite3_step(stmt) == SQLITE_DONE) {
            deleted = count;
        }
        sqlite3_finalize(stmt);
    }

    /* Clean up temp dirs and optionally source files */
    for (int i = 0; i < count; i++) {
        storage_cleanup_temp(entries[i].id);

        if (cleanup_source && entries[i].source[0] &&
            strstr(entries[i].source, "/uploads/") != NULL &&
            strstr(entries[i].source, "..") == NULL) {
            struct stat st;
            if (stat(entries[i].source, &st) == 0 && S_ISREG(st.st_mode)) {
                if (unlink(entries[i].source) == 0) {
                    files_deleted++;
                }
            }
        }
    }

    log_info("Bulk deleted %d %s jobs (%d source files removed)",
             deleted, status_filter, files_deleted);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Jobs deleted");
    cJSON_AddNumberToObject(root, "deleted", deleted);
    cJSON_AddNumberToObject(root, "files_deleted", files_deleted);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/peers
 * ================================================================ */

int api_get_peers(http_request_t *req)
{
    peer_info_t *peers = NULL;
    int count = cluster_get_peers(&peers);

    if (count < 0) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to get peer list");
    }

    cJSON *items = cJSON_CreateArray();
    for (int i = 0; i < count; i++) {
        cJSON *obj = cJSON_CreateObject();
        cJSON_AddNumberToObject(obj, "id",             peers[i].id);
        cJSON_AddStringToObject(obj, "name",           peers[i].name);
        cJSON_AddStringToObject(obj, "url",            peers[i].url);
        cJSON_AddStringToObject(obj, "status",         peers[i].status);
        cJSON_AddStringToObject(obj, "version",        peers[i].version);
        cJSON_AddNumberToObject(obj, "cpu_usage",      peers[i].cpu_usage);
        cJSON_AddNumberToObject(obj, "memory_usage",   peers[i].memory_usage);
        cJSON_AddNumberToObject(obj, "disk_total",     (double)peers[i].disk_total);
        cJSON_AddNumberToObject(obj, "disk_free",      (double)peers[i].disk_free);
        cJSON_AddNumberToObject(obj, "content_count",  peers[i].content_count);
        cJSON_AddNumberToObject(obj, "active_jobs",    peers[i].active_jobs);
        cJSON_AddStringToObject(obj, "last_seen",      peers[i].last_seen);
        cJSON_AddItemToArray(items, obj);
    }
    free(peers);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddItemToObject(root, "peers", items);
    cJSON_AddNumberToObject(root, "count", count);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/peers
 * ================================================================ */

int api_post_peer(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    const char *name    = json_str(body, "name", NULL);
    const char *url     = json_str(body, "url", NULL);
    const char *api_key = json_str(body, "api_key", "");

    if (!name || name[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: name");
    }
    if (!url || url[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: url");
    }

    int peer_id = cluster_add_peer(name, url, api_key);
    cJSON_Delete(body);

    if (peer_id < 0) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to add peer (may already exist)");
    }

    log_info("Added peer %d: %s (%s)", peer_id, name, url);

    /* Return the new peer info */
    peer_info_t info;
    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Peer added successfully");

    if (cluster_get_peer(peer_id, &info) == 0) {
        cJSON *peer = cJSON_CreateObject();
        cJSON_AddNumberToObject(peer, "id",    info.id);
        cJSON_AddStringToObject(peer, "name",  info.name);
        cJSON_AddStringToObject(peer, "url",   info.url);
        cJSON_AddStringToObject(peer, "status", info.status);
        cJSON_AddItemToObject(root, "peer", peer);
    } else {
        cJSON *peer = cJSON_CreateObject();
        cJSON_AddNumberToObject(peer, "id", peer_id);
        cJSON_AddStringToObject(peer, "name", name);
        cJSON_AddStringToObject(peer, "url", url);
        cJSON_AddStringToObject(peer, "status", "unknown");
        cJSON_AddItemToObject(root, "peer", peer);
    }

    return send_json_obj(req->connection, MHD_HTTP_CREATED, root);
}

/* ================================================================
 * DELETE /api/peers/{id}
 * ================================================================ */

int api_delete_peer(http_request_t *req, int peer_id)
{
    /* Verify peer exists */
    peer_info_t info;
    if (cluster_get_peer(peer_id, &info) != 0) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Peer not found");
    }

    log_info("Removing peer %d: %s", peer_id, info.name);

    if (cluster_remove_peer(peer_id) != 0) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to remove peer");
    }

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Peer removed successfully");
    cJSON_AddNumberToObject(root, "peer_id", peer_id);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/peers/{id}/content
 * ================================================================ */

int api_get_peer_content(http_request_t *req, int peer_id)
{
    /* Verify peer exists */
    peer_info_t info;
    if (cluster_get_peer(peer_id, &info) != 0) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Peer not found");
    }

    char *content_json = cluster_fetch_remote_content(peer_id);
    if (!content_json) {
        return http_send_error(req->connection, MHD_HTTP_BAD_GATEWAY,
                               "Failed to fetch content from peer (peer may be offline)");
    }

    /* The content_json is already a JSON string from the remote peer.
     * Parse it and wrap it in our response envelope. */
    cJSON *remote = cJSON_Parse(content_json);
    free(content_json);

    if (!remote) {
        return http_send_error(req->connection, MHD_HTTP_BAD_GATEWAY,
                               "Invalid response from peer");
    }

    cJSON *root = cJSON_CreateObject();
    cJSON_AddNumberToObject(root, "peer_id", peer_id);
    cJSON_AddStringToObject(root, "peer_name", info.name);
    cJSON_AddStringToObject(root, "peer_status", info.status);
    cJSON_AddItemToObject(root, "content", remote);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/migrations
 * ================================================================ */

int api_get_migrations(http_request_t *req)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    const char *status_filter = http_get_param(req->connection, "status");
    const char *limit_str     = http_get_param(req->connection, "limit");
    const char *offset_str    = http_get_param(req->connection, "offset");

    int limit  = limit_str  ? atoi(limit_str)  : 50;
    int offset = offset_str ? atoi(offset_str) : 0;
    if (limit <= 0)  limit = 50;
    if (limit > 500) limit = 500;
    if (offset < 0)  offset = 0;

    char sql[512];
    if (status_filter && status_filter[0] != '\0') {
        snprintf(sql, sizeof(sql),
                 "SELECT id, content_id, title, source_peer, dest_peer, status, "
                 "progress, bytes_total, bytes_transferred, error_msg, delete_source, "
                 "created_at, started_at, completed_at "
                 "FROM migrations WHERE status = ? "
                 "ORDER BY created_at DESC LIMIT ? OFFSET ?");
    } else {
        snprintf(sql, sizeof(sql),
                 "SELECT id, content_id, title, source_peer, dest_peer, status, "
                 "progress, bytes_total, bytes_transferred, error_msg, delete_source, "
                 "created_at, started_at, completed_at "
                 "FROM migrations ORDER BY created_at DESC LIMIT ? OFFSET ?");
    }

    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) != SQLITE_OK) {
        log_error("SQL prepare error (get migrations): %s", sqlite3_errmsg(db));
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }

    int param_idx = 1;
    if (status_filter && status_filter[0] != '\0') {
        sqlite3_bind_text(stmt, param_idx++, status_filter, -1, SQLITE_STATIC);
    }
    sqlite3_bind_int(stmt, param_idx++, limit);
    sqlite3_bind_int(stmt, param_idx++, offset);

    cJSON *items = cJSON_CreateArray();
    while (sqlite3_step(stmt) == SQLITE_ROW) {
        cJSON *obj = cJSON_CreateObject();
        cJSON_AddNumberToObject(obj, "id",                sqlite3_column_int(stmt, 0));
        cJSON_AddStringToObject(obj, "content_id",        (const char *)sqlite3_column_text(stmt, 1) ?: "");
        cJSON_AddStringToObject(obj, "title",             (const char *)sqlite3_column_text(stmt, 2) ?: "");
        cJSON_AddStringToObject(obj, "source_peer",       (const char *)sqlite3_column_text(stmt, 3) ?: "");
        cJSON_AddStringToObject(obj, "dest_peer",         (const char *)sqlite3_column_text(stmt, 4) ?: "");
        cJSON_AddStringToObject(obj, "status",            (const char *)sqlite3_column_text(stmt, 5) ?: "");
        cJSON_AddNumberToObject(obj, "progress",          sqlite3_column_double(stmt, 6));
        cJSON_AddNumberToObject(obj, "bytes_total",       (double)sqlite3_column_int64(stmt, 7));
        cJSON_AddNumberToObject(obj, "bytes_transferred", (double)sqlite3_column_int64(stmt, 8));
        cJSON_AddStringToObject(obj, "error_msg",         (const char *)sqlite3_column_text(stmt, 9) ?: "");
        cJSON_AddBoolToObject(obj,   "delete_source",     sqlite3_column_int(stmt, 10));
        cJSON_AddStringToObject(obj, "created_at",        (const char *)sqlite3_column_text(stmt, 11) ?: "");
        cJSON_AddStringToObject(obj, "started_at",        (const char *)sqlite3_column_text(stmt, 12) ?: "");
        cJSON_AddStringToObject(obj, "completed_at",      (const char *)sqlite3_column_text(stmt, 13) ?: "");

        /* Human-readable transfer info */
        int64_t total = sqlite3_column_int64(stmt, 7);
        int64_t xfer  = sqlite3_column_int64(stmt, 8);
        if (total > 0) {
            char buf[64];
            cJSON_AddStringToObject(obj, "bytes_total_human",
                                    format_bytes((uint64_t)total, buf, sizeof(buf)));
            cJSON_AddStringToObject(obj, "bytes_transferred_human",
                                    format_bytes((uint64_t)xfer, buf, sizeof(buf)));
        }

        cJSON_AddItemToArray(items, obj);
    }
    sqlite3_finalize(stmt);

    /* Total count */
    int total_count = 0;
    {
        char cnt_sql[256];
        if (status_filter && status_filter[0] != '\0') {
            snprintf(cnt_sql, sizeof(cnt_sql),
                     "SELECT COUNT(*) FROM migrations WHERE status = ?");
        } else {
            snprintf(cnt_sql, sizeof(cnt_sql),
                     "SELECT COUNT(*) FROM migrations");
        }
        sqlite3_stmt *cnt_stmt;
        if (sqlite3_prepare_v2(db, cnt_sql, -1, &cnt_stmt, NULL) == SQLITE_OK) {
            if (status_filter && status_filter[0] != '\0') {
                sqlite3_bind_text(cnt_stmt, 1, status_filter, -1, SQLITE_STATIC);
            }
            if (sqlite3_step(cnt_stmt) == SQLITE_ROW) {
                total_count = sqlite3_column_int(cnt_stmt, 0);
            }
            sqlite3_finalize(cnt_stmt);
        }
    }

    cJSON *root = cJSON_CreateObject();
    cJSON_AddItemToObject(root, "items", items);
    cJSON_AddNumberToObject(root, "total", total_count);
    cJSON_AddNumberToObject(root, "limit", limit);
    cJSON_AddNumberToObject(root, "offset", offset);
    cJSON_AddNumberToObject(root, "active_migrations", migration_active_count());

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/migrations
 * ================================================================ */

int api_post_migration(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    const char *content_id   = json_str(body, "content_id", NULL);
    const char *title        = json_str(body, "title", "");
    const char *source_peer  = json_str(body, "source_peer", NULL);
    const char *dest_peer    = json_str(body, "dest_peer", NULL);
    int         delete_source = json_bool(body, "delete_source", 0);

    if (!content_id || content_id[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: content_id");
    }
    if (!source_peer || source_peer[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: source_peer");
    }
    if (!dest_peer || dest_peer[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: dest_peer");
    }

    int migration_id = migration_create(content_id, title, source_peer,
                                         dest_peer, delete_source);
    cJSON_Delete(body);

    if (migration_id < 0) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to create migration");
    }

    log_info("Created migration %d: %s from %s to %s (delete_source=%d)",
             migration_id, content_id, source_peer, dest_peer, delete_source);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "Migration created successfully");

    cJSON *mig = cJSON_CreateObject();
    cJSON_AddNumberToObject(mig, "id", migration_id);
    cJSON_AddStringToObject(mig, "content_id", content_id);
    cJSON_AddStringToObject(mig, "title", title);
    cJSON_AddStringToObject(mig, "source_peer", source_peer);
    cJSON_AddStringToObject(mig, "dest_peer", dest_peer);
    cJSON_AddBoolToObject(mig, "delete_source", delete_source);
    cJSON_AddStringToObject(mig, "status", "pending");
    cJSON_AddNumberToObject(mig, "progress", 0.0);
    cJSON_AddItemToObject(root, "migration", mig);

    return send_json_obj(req->connection, MHD_HTTP_CREATED, root);
}

/* ================================================================
 * GET /api/migrations/{id}
 * ================================================================ */

int api_get_migration(http_request_t *req, int migration_id)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    const char *sql =
        "SELECT id, content_id, title, source_peer, dest_peer, status, "
        "progress, bytes_total, bytes_transferred, error_msg, delete_source, "
        "created_at, started_at, completed_at "
        "FROM migrations WHERE id = ?";

    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_int(stmt, 1, migration_id);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Migration not found");
    }

    cJSON *obj = cJSON_CreateObject();
    cJSON_AddNumberToObject(obj, "id",                sqlite3_column_int(stmt, 0));
    cJSON_AddStringToObject(obj, "content_id",        (const char *)sqlite3_column_text(stmt, 1) ?: "");
    cJSON_AddStringToObject(obj, "title",             (const char *)sqlite3_column_text(stmt, 2) ?: "");
    cJSON_AddStringToObject(obj, "source_peer",       (const char *)sqlite3_column_text(stmt, 3) ?: "");
    cJSON_AddStringToObject(obj, "dest_peer",         (const char *)sqlite3_column_text(stmt, 4) ?: "");
    cJSON_AddStringToObject(obj, "status",            (const char *)sqlite3_column_text(stmt, 5) ?: "");
    cJSON_AddNumberToObject(obj, "progress",          sqlite3_column_double(stmt, 6));
    cJSON_AddNumberToObject(obj, "bytes_total",       (double)sqlite3_column_int64(stmt, 7));
    cJSON_AddNumberToObject(obj, "bytes_transferred", (double)sqlite3_column_int64(stmt, 8));
    cJSON_AddStringToObject(obj, "error_msg",         (const char *)sqlite3_column_text(stmt, 9) ?: "");
    cJSON_AddBoolToObject(obj,   "delete_source",     sqlite3_column_int(stmt, 10));
    cJSON_AddStringToObject(obj, "created_at",        (const char *)sqlite3_column_text(stmt, 11) ?: "");
    cJSON_AddStringToObject(obj, "started_at",        (const char *)sqlite3_column_text(stmt, 12) ?: "");
    cJSON_AddStringToObject(obj, "completed_at",      (const char *)sqlite3_column_text(stmt, 13) ?: "");

    /* Human-readable transfer info */
    int64_t total = sqlite3_column_int64(stmt, 7);
    int64_t xfer  = sqlite3_column_int64(stmt, 8);
    char buf[64];
    if (total > 0) {
        cJSON_AddStringToObject(obj, "bytes_total_human",
                                format_bytes((uint64_t)total, buf, sizeof(buf)));
        cJSON_AddStringToObject(obj, "bytes_transferred_human",
                                format_bytes((uint64_t)xfer, buf, sizeof(buf)));
    }

    sqlite3_finalize(stmt);

    return send_json_obj(req->connection, MHD_HTTP_OK, obj);
}

/* ================================================================
 * DELETE /api/migrations/{id}
 * ================================================================ */

int api_delete_migration(http_request_t *req, int migration_id)
{
    sqlite3 *db = db_handle();
    if (!db) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /* Check if migration exists and get its status */
    sqlite3_stmt *stmt;
    const char *check_sql = "SELECT status FROM migrations WHERE id = ?";
    if (sqlite3_prepare_v2(db, check_sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database query failed");
    }
    sqlite3_bind_int(stmt, 1, migration_id);

    if (sqlite3_step(stmt) != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "Migration not found");
    }

    const char *status = (const char *)sqlite3_column_text(stmt, 0);
    int is_active = (status && (strcmp(status, "pending") == 0 ||
                                strcmp(status, "running") == 0 ||
                                strcmp(status, "transferring") == 0));
    sqlite3_finalize(stmt);

    /* If active, cancel it first */
    if (is_active) {
        if (migration_cancel(migration_id) != 0) {
            log_warn("Failed to cancel migration %d before deletion", migration_id);
        }
    }

    /* Delete from database */
    const char *del_sql = "DELETE FROM migrations WHERE id = ?";
    if (sqlite3_prepare_v2(db, del_sql, -1, &stmt, NULL) != SQLITE_OK) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database delete failed");
    }
    sqlite3_bind_int(stmt, 1, migration_id);
    int rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to delete migration");
    }

    log_info("Migration %d %s", migration_id,
             is_active ? "cancelled and deleted" : "deleted");

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message",
                            is_active ? "Migration cancelled and deleted"
                                      : "Migration deleted");
    cJSON_AddNumberToObject(root, "migration_id", migration_id);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/health  (peer health report)
 * ================================================================ */

int api_post_peer_health(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    sqlite3 *db = db_handle();
    if (!db) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database not available");
    }

    /* Extract peer info from the health report */
    const char *node_name     = json_str(body, "node_name", NULL);
    const char *version       = json_str(body, "version", "");
    double      cpu_usage     = 0.0;
    double      memory_usage  = 0.0;
    long long   disk_total    = 0;
    long long   disk_free     = 0;
    int         content_count = 0;
    int         active_jobs   = 0;

    cJSON *item;
    item = cJSON_GetObjectItemCaseSensitive(body, "cpu_usage");
    if (cJSON_IsNumber(item)) cpu_usage = item->valuedouble;

    item = cJSON_GetObjectItemCaseSensitive(body, "memory_usage");
    if (cJSON_IsNumber(item)) memory_usage = item->valuedouble;

    item = cJSON_GetObjectItemCaseSensitive(body, "disk_total");
    if (cJSON_IsNumber(item)) disk_total = (long long)item->valuedouble;

    item = cJSON_GetObjectItemCaseSensitive(body, "disk_free");
    if (cJSON_IsNumber(item)) disk_free = (long long)item->valuedouble;

    item = cJSON_GetObjectItemCaseSensitive(body, "content_count");
    if (cJSON_IsNumber(item)) content_count = item->valueint;

    item = cJSON_GetObjectItemCaseSensitive(body, "active_jobs");
    if (cJSON_IsNumber(item)) active_jobs = item->valueint;

    if (!node_name || node_name[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required field: node_name");
    }

    /* Update the peer record if it exists (match by name) */
    const char *update_sql =
        "UPDATE peers SET status = 'online', version = ?, "
        "cpu_usage = ?, memory_usage = ?, disk_total = ?, disk_free = ?, "
        "content_count = ?, active_jobs = ?, last_seen = CURRENT_TIMESTAMP, "
        "last_error = NULL "
        "WHERE name = ?";

    sqlite3_stmt *stmt;
    if (sqlite3_prepare_v2(db, update_sql, -1, &stmt, NULL) != SQLITE_OK) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Database update failed");
    }

    sqlite3_bind_text(stmt,   1, version, -1, SQLITE_STATIC);
    sqlite3_bind_double(stmt, 2, cpu_usage);
    sqlite3_bind_double(stmt, 3, memory_usage);
    sqlite3_bind_int64(stmt,  4, disk_total);
    sqlite3_bind_int64(stmt,  5, disk_free);
    sqlite3_bind_int(stmt,    6, content_count);
    sqlite3_bind_int(stmt,    7, active_jobs);
    sqlite3_bind_text(stmt,   8, node_name, -1, SQLITE_STATIC);

    int rc = sqlite3_step(stmt);
    int changes = sqlite3_changes(db);
    sqlite3_finalize(stmt);

    cJSON_Delete(body);

    if (rc != SQLITE_DONE) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to update peer health");
    }

    log_debug("Health report from %s: cpu=%.1f%% mem=%.1f%% jobs=%d content=%d%s",
              node_name, cpu_usage, memory_usage, active_jobs, content_count,
              changes == 0 ? " (unknown peer)" : "");

    /* Respond with our own status so the remote peer knows we're alive */
    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "node_name",
                            s_config ? s_config->node_name : "unknown");
    cJSON_AddStringToObject(root, "version", VOD_SERVER_VERSION);
    cJSON_AddNumberToObject(root, "active_jobs", job_processor_active_count());
    cJSON_AddBoolToObject(root, "peer_known", changes > 0);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/upload  — receive a file upload, streamed to disk
 * Returns the local file path for use in job submission.
 * ================================================================ */

int api_post_upload(http_request_t *req)
{
    /* The file was already streamed to disk by http_server.c */
    if (!req->is_upload || req->upload_path[0] == '\0') {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "No file uploaded");
    }

    if (req->upload_size == 0) {
        unlink(req->upload_path);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Uploaded file is empty");
    }

    log_info("Upload received: %s (%zu bytes)", req->upload_path, req->upload_size);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "File uploaded successfully");
    cJSON_AddStringToObject(root, "path", req->upload_path);
    cJSON_AddNumberToObject(root, "size", (double)req->upload_size);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/uploads  — list files in the uploads directory
 * ================================================================ */

int api_get_uploads(http_request_t *req)
{
    char upload_dir[MAX_PATH_LEN];
    snprintf(upload_dir, sizeof(upload_dir), "%s/uploads",
             s_config ? s_config->temp_path : "/var/lib/vod-server/tmp");

    DIR *dir = opendir(upload_dir);
    if (!dir) {
        /* Directory doesn't exist yet — return empty list */
        cJSON *root = cJSON_CreateObject();
        cJSON_AddBoolToObject(root, "success", 1);
        cJSON_AddItemToObject(root, "files", cJSON_CreateArray());
        cJSON_AddNumberToObject(root, "total_size", 0);
        return send_json_obj(req->connection, MHD_HTTP_OK, root);
    }

    cJSON *files = cJSON_CreateArray();
    uint64_t total_size = 0;
    struct dirent *entry;

    while ((entry = readdir(dir)) != NULL) {
        if (entry->d_name[0] == '.') continue;

        char filepath[MAX_PATH_LEN];
        snprintf(filepath, sizeof(filepath), "%s/%s", upload_dir, entry->d_name);

        struct stat st;
        if (stat(filepath, &st) != 0 || !S_ISREG(st.st_mode)) continue;

        cJSON *file = cJSON_CreateObject();
        cJSON_AddStringToObject(file, "name", entry->d_name);
        cJSON_AddStringToObject(file, "path", filepath);
        cJSON_AddNumberToObject(file, "size", (double)st.st_size);
        cJSON_AddNumberToObject(file, "modified", (double)st.st_mtime);
        cJSON_AddItemToArray(files, file);

        total_size += (uint64_t)st.st_size;
    }
    closedir(dir);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddItemToObject(root, "files", files);
    cJSON_AddNumberToObject(root, "total_size", (double)total_size);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * DELETE /api/uploads?file=filename  — delete an uploaded file
 * ================================================================ */

int api_delete_upload(http_request_t *req)
{
    const char *filename = http_get_param(req->connection, "file");
    if (!filename || filename[0] == '\0') {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing required parameter: file");
    }

    /* Prevent directory traversal */
    if (strstr(filename, "..") || strchr(filename, '/') || strchr(filename, '\\')) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid filename");
    }

    char filepath[MAX_PATH_LEN];
    snprintf(filepath, sizeof(filepath), "%s/uploads/%s",
             s_config ? s_config->temp_path : "/var/lib/vod-server/tmp",
             filename);

    struct stat st;
    if (stat(filepath, &st) != 0) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "File not found");
    }

    if (unlink(filepath) != 0) {
        log_error("Failed to delete upload: %s (%s)", filepath, strerror(errno));
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to delete file");
    }

    log_info("Deleted upload: %s (%lld bytes)", filepath, (long long)st.st_size);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "File deleted");
    cJSON_AddNumberToObject(root, "freed", (double)st.st_size);

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/browse  — list directories at a given path
 * ================================================================ */

int api_get_browse(http_request_t *req)
{
    const char *path = http_get_param(req->connection, "path");
    if (!path || path[0] == '\0') path = "/";

    /* Security: reject traversal */
    if (strstr(path, "..") != NULL) {
        return http_send_error(req->connection, MHD_HTTP_FORBIDDEN,
                               "Path traversal not allowed");
    }

    DIR *dir = opendir(path);
    if (!dir) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Cannot open directory");
    }

    cJSON *root  = cJSON_CreateObject();
    cJSON *dirs  = cJSON_CreateArray();
    cJSON_AddStringToObject(root, "path", path);

    struct dirent *entry;
    while ((entry = readdir(dir)) != NULL) {
        if (entry->d_name[0] == '.') continue; /* skip hidden + . and .. */

        char full[MAX_PATH_LEN + 256];
        snprintf(full, sizeof(full), "%s/%s", path, entry->d_name);

        struct stat st;
        if (stat(full, &st) == 0 && S_ISDIR(st.st_mode)) {
            cJSON *d = cJSON_CreateObject();
            cJSON_AddStringToObject(d, "name", entry->d_name);
            cJSON_AddStringToObject(d, "path", full);
            cJSON_AddItemToArray(dirs, d);
        }
    }
    closedir(dir);

    cJSON_AddItemToObject(root, "directories", dirs);
    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * GET /api/ssl/status  — current SSL/TLS certificate info
 * ================================================================ */

int api_get_ssl_status(http_request_t *req)
{
    cJSON *root = cJSON_CreateObject();

    cJSON_AddBoolToObject(root, "enabled",
                          s_config ? s_config->ssl_enabled : false);
    cJSON_AddBoolToObject(root, "ready", ssl_is_ready());
    cJSON_AddStringToObject(root, "cert_file",
                            s_config ? s_config->ssl_cert_file : "");
    cJSON_AddStringToObject(root, "key_file",
                            s_config ? s_config->ssl_key_file : "");

    /* Check if certbot/letsencrypt is available */
    {
        struct stat st;
        bool certbot = (stat("/usr/bin/certbot", &st) == 0 && (st.st_mode & S_IXUSR))
                    || (stat("/usr/local/bin/certbot", &st) == 0 && (st.st_mode & S_IXUSR))
                    || (stat("/snap/bin/certbot", &st) == 0 && (st.st_mode & S_IXUSR));
        cJSON_AddBoolToObject(root, "certbot_available", certbot);
    }

    /* ACME HTTP listener status */
    {
        cJSON *acme = cJSON_CreateObject();
        cJSON_AddBoolToObject(acme, "enabled",
                              s_config ? s_config->acme_enabled : false);
        cJSON_AddNumberToObject(acme, "http_port",
                                s_config ? s_config->acme_http_port : 80);
        cJSON_AddStringToObject(acme, "webroot",
                                s_config ? s_config->acme_webroot : "");
        cJSON_AddStringToObject(acme, "domain",
                                s_config ? s_config->acme_domain : "");
        cJSON_AddNumberToObject(acme, "https_port",
                                s_config ? s_config->acme_https_port : 443);
        cJSON_AddItemToObject(root, "acme", acme);
    }

    /* Try to get certificate details if loaded */
    if (ssl_is_ready() && s_config && s_config->ssl_cert_file[0]) {
        /* Use openssl to read cert details */
        char cmd[MAX_PATH_LEN + 128];
        snprintf(cmd, sizeof(cmd),
                 "openssl x509 -in '%s' -noout -subject -issuer -dates -serial 2>/dev/null",
                 s_config->ssl_cert_file);

        FILE *fp = popen(cmd, "r");
        if (fp) {
            cJSON *cert = cJSON_CreateObject();
            char line[512];
            while (fgets(line, sizeof(line), fp)) {
                size_t len = strlen(line);
                if (len > 0 && line[len - 1] == '\n') line[len - 1] = '\0';

                if (strncmp(line, "subject=", 8) == 0) {
                    cJSON_AddStringToObject(cert, "subject", line + 8);
                } else if (strncmp(line, "issuer=", 7) == 0) {
                    cJSON_AddStringToObject(cert, "issuer", line + 7);
                } else if (strncmp(line, "notBefore=", 10) == 0) {
                    cJSON_AddStringToObject(cert, "not_before", line + 10);
                } else if (strncmp(line, "notAfter=", 9) == 0) {
                    cJSON_AddStringToObject(cert, "not_after", line + 9);
                } else if (strncmp(line, "serial=", 7) == 0) {
                    cJSON_AddStringToObject(cert, "serial", line + 7);
                }
            }

            /* Check if it's self-signed */
            cJSON *subj = cJSON_GetObjectItemCaseSensitive(cert, "subject");
            cJSON *iss  = cJSON_GetObjectItemCaseSensitive(cert, "issuer");
            if (subj && iss && cJSON_IsString(subj) && cJSON_IsString(iss)) {
                cJSON_AddBoolToObject(cert, "self_signed",
                                      strcmp(subj->valuestring, iss->valuestring) == 0);
            }

            pclose(fp);
            cJSON_AddItemToObject(root, "certificate", cert);
        }
    }

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/ssl/letsencrypt  — one-click SSL via Let's Encrypt
 * Body: { "domain": "vod.example.com",
 *         "email":  "admin@example.com"  (optional),
 *         "cloudflare_token": "xxx"      (for DNS-01, works behind NAT) }
 *
 * Flow: get cert → enable SSL in config → install renewal timer → restart
 * ================================================================ */

int api_post_ssl_letsencrypt(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    const char *domain           = json_str(body, "domain", "");
    const char *email            = json_str(body, "email", "");
    const char *cloudflare_token = json_str(body, "cloudflare_token", "");

    if (domain[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Domain is required");
    }

    /* Security: validate domain — alphanumeric, dots, hyphens only */
    for (const char *p = domain; *p; p++) {
        if (!((*p >= 'a' && *p <= 'z') || (*p >= 'A' && *p <= 'Z') ||
              (*p >= '0' && *p <= '9') || *p == '.' || *p == '-')) {
            cJSON_Delete(body);
            return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                                   "Invalid domain name");
        }
    }

    /* Determine cert output paths */
    char cert_path[MAX_PATH_LEN];
    char key_path[MAX_PATH_LEN];

    if (s_config && s_config->ssl_cert_file[0]) {
        snprintf(cert_path, sizeof(cert_path), "%s", s_config->ssl_cert_file);
        snprintf(key_path,  sizeof(key_path),  "%s", s_config->ssl_key_file);
    } else {
        snprintf(cert_path, sizeof(cert_path), "/etc/vod-server/ssl/cert.pem");
        snprintf(key_path,  sizeof(key_path),  "/etc/vod-server/ssl/key.pem");
    }

    char cmd[4096];
    bool use_cloudflare = (cloudflare_token[0] != '\0');
    const char *creds_file = "/etc/vod-server/ssl/cloudflare.ini";

    if (use_cloudflare) {
        /* Write Cloudflare API token to credentials file (kept for renewals) */
        FILE *cfp = fopen(creds_file, "w");
        if (!cfp) {
            log_error("Cannot write Cloudflare credentials: %s", strerror(errno));
            cJSON_Delete(body);
            return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                                   "Cannot write Cloudflare credentials file");
        }
        fprintf(cfp, "dns_cloudflare_api_token = %s\n", cloudflare_token);
        fclose(cfp);
        chmod(creds_file, 0600);

        /* DNS-01 via Cloudflare — works behind NAT, fully automatic.
         * certbot places certs in /etc/letsencrypt/live/<domain>/
         * deploy-hook copies them to our paths after issue + renewal. */
        snprintf(cmd, sizeof(cmd),
                 "certbot certonly --non-interactive --agree-tos "
                 "--dns-cloudflare "
                 "--dns-cloudflare-credentials '%s' "
                 "-d '%s' %s%s "
                 "--deploy-hook '"
                 "cp -fL /etc/letsencrypt/live/%s/fullchain.pem %s && "
                 "cp -fL /etc/letsencrypt/live/%s/privkey.pem %s && "
                 "chmod 644 %s && chmod 600 %s && "
                 "chown vod-server:vod-server %s %s"
                 "' 2>&1",
                 creds_file, domain,
                 email[0] ? "--email '" : "--register-unsafely-without-email",
                 email[0] ? email : "",
                 domain, cert_path, domain, key_path,
                 cert_path, key_path, cert_path, key_path);
        /* Close the email quote if used */
        if (email[0]) {
            /* Rebuild with proper quoting */
            snprintf(cmd, sizeof(cmd),
                     "certbot certonly --non-interactive --agree-tos "
                     "--dns-cloudflare "
                     "--dns-cloudflare-credentials '%s' "
                     "-d '%s' --email '%s' "
                     "--deploy-hook '"
                     "cp -fL /etc/letsencrypt/live/%s/fullchain.pem %s && "
                     "cp -fL /etc/letsencrypt/live/%s/privkey.pem %s && "
                     "chmod 644 %s && chmod 600 %s && "
                     "chown vod-server:vod-server %s %s"
                     "' 2>&1",
                     creds_file, domain, email,
                     domain, cert_path, domain, key_path,
                     cert_path, key_path, cert_path, key_path);
        } else {
            snprintf(cmd, sizeof(cmd),
                     "certbot certonly --non-interactive --agree-tos "
                     "--dns-cloudflare --register-unsafely-without-email "
                     "--dns-cloudflare-credentials '%s' "
                     "-d '%s' "
                     "--deploy-hook '"
                     "cp -fL /etc/letsencrypt/live/%s/fullchain.pem %s && "
                     "cp -fL /etc/letsencrypt/live/%s/privkey.pem %s && "
                     "chmod 644 %s && chmod 600 %s && "
                     "chown vod-server:vod-server %s %s"
                     "' 2>&1",
                     creds_file, domain,
                     domain, cert_path, domain, key_path,
                     cert_path, key_path, cert_path, key_path);
        }
    } else if (s_config && s_config->acme_enabled) {
        /* HTTP-01 via webroot — ACME listener on port 80 serves challenges.
         * Works behind NAT/NPM when port 80 is proxied to this server. */
        const char *webroot = s_config->acme_webroot;
        if (email[0] != '\0') {
            snprintf(cmd, sizeof(cmd),
                     "certbot certonly --webroot --non-interactive --agree-tos "
                     "-w '%s' "
                     "-d '%s' --email '%s' "
                     "--deploy-hook '"
                     "cp -fL /etc/letsencrypt/live/%s/fullchain.pem %s && "
                     "cp -fL /etc/letsencrypt/live/%s/privkey.pem %s && "
                     "chmod 644 %s && chmod 600 %s && "
                     "chown vod-server:vod-server %s %s"
                     "' 2>&1",
                     webroot, domain, email,
                     domain, cert_path, domain, key_path,
                     cert_path, key_path, cert_path, key_path);
        } else {
            snprintf(cmd, sizeof(cmd),
                     "certbot certonly --webroot --non-interactive --agree-tos "
                     "--register-unsafely-without-email "
                     "-w '%s' "
                     "-d '%s' "
                     "--deploy-hook '"
                     "cp -fL /etc/letsencrypt/live/%s/fullchain.pem %s && "
                     "cp -fL /etc/letsencrypt/live/%s/privkey.pem %s && "
                     "chmod 644 %s && chmod 600 %s && "
                     "chown vod-server:vod-server %s %s"
                     "' 2>&1",
                     webroot, domain,
                     domain, cert_path, domain, key_path,
                     cert_path, key_path, cert_path, key_path);
        }
    } else {
        /* HTTP-01 standalone — certbot runs its own server on port 80 */
        if (email[0] != '\0') {
            snprintf(cmd, sizeof(cmd),
                     "certbot certonly --standalone --non-interactive --agree-tos "
                     "--preferred-challenges http "
                     "-d '%s' --email '%s' "
                     "--cert-path '%s' --key-path '%s' "
                     "--fullchain-path '%s' "
                     "2>&1",
                     domain, email, cert_path, key_path, cert_path);
        } else {
            snprintf(cmd, sizeof(cmd),
                     "certbot certonly --standalone --non-interactive --agree-tos "
                     "--preferred-challenges http --register-unsafely-without-email "
                     "-d '%s' "
                     "--cert-path '%s' --key-path '%s' "
                     "--fullchain-path '%s' "
                     "2>&1",
                     domain, cert_path, key_path, cert_path);
        }
    }

    cJSON_Delete(body);

    log_info("Requesting Let's Encrypt certificate for %s (%s)",
             domain, use_cloudflare ? "dns-cloudflare" :
             (s_config && s_config->acme_enabled) ? "webroot" : "standalone");

    FILE *fp = popen(cmd, "r");
    if (!fp) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to run certbot");
    }

    /* Capture output */
    char output[8192] = {0};
    size_t olen = 0;
    while (!feof(fp) && olen < sizeof(output) - 1) {
        size_t n = fread(output + olen, 1, sizeof(output) - olen - 1, fp);
        olen += n;
        if (n == 0) break;
    }
    output[olen] = '\0';

    int status = pclose(fp);

    cJSON *root = cJSON_CreateObject();

    if (status == 0) {
        log_info("Let's Encrypt certificate obtained for %s", domain);

        /* Load certs into memory */
        ssl_load_files(cert_path, key_path);

        /* Enable SSL in vod-server.conf */
        system("sed -i 's/^\\s*enabled\\s*=.*$/enabled = true/' "
               "/etc/vod-server/vod-server.conf 2>/dev/null");
        log_info("SSL auto-enabled in vod-server.conf");

        /* Create renewal script that copies certs and restarts service */
        FILE *sfp = fopen("/etc/vod-server/ssl/renew.sh", "w");
        if (sfp) {
            fprintf(sfp,
                "#!/bin/bash\n"
                "certbot renew --quiet --deploy-hook '"
                "cp -fL /etc/letsencrypt/live/%s/fullchain.pem %s; "
                "cp -fL /etc/letsencrypt/live/%s/privkey.pem %s; "
                "chmod 644 %s; chmod 600 %s; "
                "chown vod-server:vod-server %s %s; "
                "systemctl restart vod-server"
                "'\n",
                domain, cert_path, domain, key_path,
                cert_path, key_path, cert_path, key_path);
            fclose(sfp);
            chmod("/etc/vod-server/ssl/renew.sh", 0755);
        }

        /* Install systemd timer for auto-renewal (twice daily) */
        FILE *tfp = fopen("/etc/systemd/system/vod-server-renew.timer", "w");
        if (tfp) {
            fputs("[Unit]\n"
                  "Description=VOD Server SSL certificate renewal\n\n"
                  "[Timer]\n"
                  "OnCalendar=*-*-* 02,14:30:00\n"
                  "RandomizedDelaySec=3600\n"
                  "Persistent=true\n\n"
                  "[Install]\n"
                  "WantedBy=timers.target\n", tfp);
            fclose(tfp);
        }
        tfp = fopen("/etc/systemd/system/vod-server-renew.service", "w");
        if (tfp) {
            fputs("[Unit]\n"
                  "Description=VOD Server SSL certificate renewal\n\n"
                  "[Service]\n"
                  "Type=oneshot\n"
                  "ExecStart=/etc/vod-server/ssl/renew.sh\n", tfp);
            fclose(tfp);
        }

        system("systemctl daemon-reload 2>/dev/null");
        system("systemctl enable --now vod-server-renew.timer 2>/dev/null");
        log_info("Auto-renewal timer installed (twice daily)");

        cJSON_AddBoolToObject(root, "success", 1);
        cJSON_AddStringToObject(root, "message",
                                "Certificate obtained! SSL enabled. Server restarting...");
        cJSON_AddStringToObject(root, "cert_path", cert_path);
        cJSON_AddStringToObject(root, "key_path", key_path);

        /* Schedule restart after we send the response */
        system("(sleep 2 && systemctl restart vod-server) &");

    } else {
        log_error("certbot failed for %s (exit %d): %.512s", domain, status, output);
        cJSON_AddBoolToObject(root, "success", 0);
        cJSON_AddStringToObject(root, "error", "certbot failed");
        cJSON_AddStringToObject(root, "output", output);
    }

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/ssl/self-signed  — generate a self-signed certificate
 * ================================================================ */

int api_post_ssl_self_signed(http_request_t *req)
{
    (void)req;

    char cert_path[MAX_PATH_LEN];
    char key_path[MAX_PATH_LEN];

    if (s_config && s_config->ssl_cert_file[0]) {
        snprintf(cert_path, sizeof(cert_path), "%s", s_config->ssl_cert_file);
        snprintf(key_path,  sizeof(key_path),  "%s", s_config->ssl_key_file);
    } else {
        snprintf(cert_path, sizeof(cert_path), "/etc/vod-server/ssl/cert.pem");
        snprintf(key_path,  sizeof(key_path),  "/etc/vod-server/ssl/key.pem");
    }

    log_info("Generating self-signed certificate...");

    int rc = ssl_generate_self_signed(cert_path, key_path);

    cJSON *root = cJSON_CreateObject();

    if (rc == 0) {
        cJSON_AddBoolToObject(root, "success", 1);
        cJSON_AddStringToObject(root, "message",
                                "Self-signed certificate generated. Restart to activate SSL.");
        cJSON_AddStringToObject(root, "cert_path", cert_path);
        cJSON_AddStringToObject(root, "key_path", key_path);

        if (ssl_load_files(cert_path, key_path) == 0) {
            cJSON_AddStringToObject(root, "note",
                                    "Certificate loaded. Full activation requires restart.");
        }
    } else {
        cJSON_AddBoolToObject(root, "success", 0);
        cJSON_AddStringToObject(root, "error", "Failed to generate certificate");
    }

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * POST /api/ssl/upload  — upload cert + key PEM text
 * Body: { "cert": "-----BEGIN CERTIFICATE-----\n...",
 *         "key":  "-----BEGIN PRIVATE KEY-----\n..." }
 * ================================================================ */

int api_post_ssl_upload(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    const char *cert_pem = json_str(body, "cert", "");
    const char *key_pem  = json_str(body, "key", "");

    if (cert_pem[0] == '\0' || key_pem[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Both 'cert' and 'key' PEM fields are required");
    }

    /* Basic validation — check PEM markers */
    if (!strstr(cert_pem, "-----BEGIN CERTIFICATE-----") &&
        !strstr(cert_pem, "-----BEGIN TRUSTED CERTIFICATE-----")) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "cert does not look like PEM certificate data");
    }
    if (!strstr(key_pem, "-----BEGIN") || !strstr(key_pem, "KEY-----")) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "key does not look like PEM private key data");
    }

    /* Determine output paths */
    char cert_path[MAX_PATH_LEN];
    char key_path[MAX_PATH_LEN];

    if (s_config && s_config->ssl_cert_file[0]) {
        snprintf(cert_path, sizeof(cert_path), "%s", s_config->ssl_cert_file);
        snprintf(key_path,  sizeof(key_path),  "%s", s_config->ssl_key_file);
    } else {
        snprintf(cert_path, sizeof(cert_path), "/etc/vod-server/ssl/cert.pem");
        snprintf(key_path,  sizeof(key_path),  "/etc/vod-server/ssl/key.pem");
    }

    log_info("Uploading SSL certificate and key...");

    /* Write key file first (more restrictive permissions) */
    FILE *fp = fopen(key_path, "w");
    if (!fp) {
        log_error("Cannot write key file: %s (%s)", key_path, strerror(errno));
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Cannot write key file");
    }
    fputs(key_pem, fp);
    fclose(fp);
    chmod(key_path, 0600);

    /* Write cert file */
    fp = fopen(cert_path, "w");
    if (!fp) {
        log_error("Cannot write cert file: %s (%s)", cert_path, strerror(errno));
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Cannot write cert file");
    }
    fputs(cert_pem, fp);
    fclose(fp);
    chmod(cert_path, 0644);

    cJSON_Delete(body);

    log_info("SSL certificate and key written:");
    log_info("  Certificate: %s", cert_path);
    log_info("  Private key: %s", key_path);

    /* Reload into memory */
    cJSON *root = cJSON_CreateObject();
    if (ssl_load_files(cert_path, key_path) == 0) {
        cJSON_AddBoolToObject(root, "success", 1);
        cJSON_AddStringToObject(root, "message",
                                "Certificate uploaded and loaded. Restart to activate SSL.");
        cJSON_AddStringToObject(root, "cert_path", cert_path);
        cJSON_AddStringToObject(root, "key_path", key_path);
    } else {
        cJSON_AddBoolToObject(root, "success", 0);
        cJSON_AddStringToObject(root, "error",
                                "Files written but failed to load. Check PEM format.");
    }

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * Module initialisation (called from main or http_server)
 * ================================================================ */

/* ================================================================
 * DRM Key Management Endpoints
 * ================================================================ */

/* GET /api/drm/keys - List all DRM keys */
int api_get_drm_keys(http_request_t *req)
{
    drm_key_list_t list;
    if (drm_list_keys(&list) != 0) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to list DRM keys");
    }

    cJSON *root = cJSON_CreateObject();
    cJSON *items = cJSON_CreateArray();

    for (int i = 0; i < list.count; i++) {
        drm_key_t *k = &list.keys[i];
        cJSON *obj = cJSON_CreateObject();
        cJSON_AddNumberToObject(obj, "id", k->id);
        cJSON_AddStringToObject(obj, "content_id", k->content_id);
        cJSON_AddStringToObject(obj, "kid", k->kid_uuid);
        cJSON_AddStringToObject(obj, "kid_hex", k->kid_hex);
        cJSON_AddStringToObject(obj, "scheme", drm_scheme_name((drm_scheme_t)k->scheme));
        cJSON_AddStringToObject(obj, "created_at", k->created_at);
        /* Note: key_hex is intentionally NOT exposed in list view for security */
        cJSON_AddItemToArray(items, obj);
    }

    cJSON_AddItemToObject(root, "items", items);
    cJSON_AddNumberToObject(root, "total", list.count);
    cJSON_AddBoolToObject(root, "drm_enabled", s_config ? s_config->drm_enabled : false);
    cJSON_AddStringToObject(root, "default_scheme",
                            s_config ? s_config->drm_scheme : "cenc");

    drm_free_key_list(&list);
    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* POST /api/drm/keys - Generate a new DRM key */
int api_post_drm_key(http_request_t *req)
{
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    const char *content_id = json_str(body, "content_id", NULL);
    if (!content_id || content_id[0] == '\0') {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "content_id is required");
    }

    const char *scheme_str = json_str(body, "scheme",
                                       s_config ? s_config->drm_scheme : "cenc");
    drm_scheme_t scheme = drm_parse_scheme(scheme_str);
    if (scheme == DRM_SCHEME_NONE) scheme = DRM_SCHEME_CENC;

    drm_key_t key;
    if (drm_generate_key(content_id, scheme, &key) != 0) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Failed to generate DRM key");
    }

    cJSON_Delete(body);

    cJSON *root = cJSON_CreateObject();
    cJSON_AddNumberToObject(root, "id", key.id);
    cJSON_AddStringToObject(root, "content_id", key.content_id);
    cJSON_AddStringToObject(root, "kid", key.kid_uuid);
    cJSON_AddStringToObject(root, "kid_hex", key.kid_hex);
    cJSON_AddStringToObject(root, "key_hex", key.key_hex);  /* Shown on creation only */
    cJSON_AddStringToObject(root, "scheme", drm_scheme_name(scheme));
    cJSON_AddStringToObject(root, "system_id", CLEARKEY_SYSTEM_ID);

    return send_json_obj(req->connection, MHD_HTTP_CREATED, root);
}

/* GET /api/drm/keys/{content_id} - Get DRM key details */
int api_get_drm_key(http_request_t *req, const char *content_id)
{
    drm_key_t key;
    if (drm_get_key(content_id, &key) != 0) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "No DRM key found for this content");
    }

    cJSON *root = cJSON_CreateObject();
    cJSON_AddNumberToObject(root, "id", key.id);
    cJSON_AddStringToObject(root, "content_id", key.content_id);
    cJSON_AddStringToObject(root, "kid", key.kid_uuid);
    cJSON_AddStringToObject(root, "kid_hex", key.kid_hex);
    cJSON_AddStringToObject(root, "key_hex", key.key_hex);
    cJSON_AddStringToObject(root, "scheme", drm_scheme_name((drm_scheme_t)key.scheme));
    cJSON_AddStringToObject(root, "system_id", CLEARKEY_SYSTEM_ID);
    cJSON_AddStringToObject(root, "created_at", key.created_at);

    /* Include base64url-encoded versions for player integration */
    uint8_t key_bin[DRM_KEY_SIZE], kid_bin[DRM_KEY_ID_SIZE];
    char key_b64[32], kid_b64[32];
    if (drm_hex_to_bin(key.key_hex, key_bin, sizeof(key_bin)) == DRM_KEY_SIZE &&
        drm_hex_to_bin(key.kid_hex, kid_bin, sizeof(kid_bin)) == DRM_KEY_ID_SIZE) {
        drm_base64url_encode(key_bin, DRM_KEY_SIZE, key_b64, sizeof(key_b64));
        drm_base64url_encode(kid_bin, DRM_KEY_ID_SIZE, kid_b64, sizeof(kid_b64));
        cJSON_AddStringToObject(root, "key_b64url", key_b64);
        cJSON_AddStringToObject(root, "kid_b64url", kid_b64);
        memset(key_bin, 0, sizeof(key_bin));
    }

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* DELETE /api/drm/keys/{content_id} - Delete DRM key */
int api_delete_drm_key(http_request_t *req, const char *content_id)
{
    if (drm_delete_key(content_id) != 0) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "No DRM key found for this content");
    }

    cJSON *root = cJSON_CreateObject();
    cJSON_AddBoolToObject(root, "success", 1);
    cJSON_AddStringToObject(root, "message", "DRM key deleted");

    return send_json_obj(req->connection, MHD_HTTP_OK, root);
}

/* ================================================================
 * ClearKey License Server Endpoints
 * ================================================================ */

/* GET /api/drm/license?content_id=... - Simple ClearKey license */
int api_get_drm_license(http_request_t *req)
{
    const char *content_id = http_get_param(req->connection, "content_id");
    if (!content_id || content_id[0] == '\0') {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "content_id parameter required");
    }

    char *license_json = drm_build_clearkey_response_for_content(content_id);
    if (!license_json) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "No DRM key found for this content");
    }

    int ret = http_send_json(req->connection, MHD_HTTP_OK, license_json);
    free(license_json);
    return ret;
}

/* POST /api/drm/license - Standard ClearKey license (EME key IDs in body) */
int api_post_drm_license(http_request_t *req)
{
    /*
     * The EME ClearKey license request format is:
     * { "kids": ["<base64url-encoded key ID>", ...], "type": "temporary" }
     *
     * We extract the kids array and look up each key in our database.
     */
    cJSON *body = parse_request_body(req);
    if (!body) {
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Invalid JSON body");
    }

    cJSON *kids = cJSON_GetObjectItemCaseSensitive(body, "kids");
    if (!kids || !cJSON_IsArray(kids)) {
        cJSON_Delete(body);
        return http_send_error(req->connection, MHD_HTTP_BAD_REQUEST,
                               "Missing 'kids' array in request");
    }

    /* Convert kids array back to JSON string for drm_build_clearkey_response */
    char *kids_json = cJSON_PrintUnformatted(kids);
    cJSON_Delete(body);

    if (!kids_json) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "JSON serialization failed");
    }

    char *license_json = drm_build_clearkey_response(kids_json);
    free(kids_json);

    if (!license_json) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "No matching DRM keys found");
    }

    int ret = http_send_json(req->connection, MHD_HTTP_OK, license_json);
    free(license_json);
    return ret;
}

/* GET /api/drm/key/{content_id} - Raw binary key (for HLS #EXT-X-KEY) */
int api_get_drm_raw_key(http_request_t *req, const char *content_id)
{
    drm_key_t key;
    if (drm_get_key(content_id, &key) != 0) {
        return http_send_error(req->connection, MHD_HTTP_NOT_FOUND,
                               "No DRM key found");
    }

    /* Convert hex key to binary */
    uint8_t key_bin[DRM_KEY_SIZE];
    if (drm_hex_to_bin(key.key_hex, key_bin, sizeof(key_bin)) != DRM_KEY_SIZE) {
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Key decode error");
    }

    /* Send raw binary key with appropriate content type */
    struct MHD_Response *response = MHD_create_response_from_buffer(
        DRM_KEY_SIZE, key_bin, MHD_RESPMEM_MUST_COPY);

    if (!response) {
        memset(key_bin, 0, sizeof(key_bin));
        return http_send_error(req->connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                               "Response creation failed");
    }

    MHD_add_response_header(response, "Content-Type", "application/octet-stream");
    MHD_add_response_header(response, "Access-Control-Allow-Origin", "*");
    MHD_add_response_header(response, "Cache-Control", "no-store");

    int ret = MHD_queue_response(req->connection, MHD_HTTP_OK, response);
    MHD_destroy_response(response);

    /* Clear sensitive data */
    memset(key_bin, 0, sizeof(key_bin));

    return ret;
}

/*
 * The config pointer is obtained from the extern declared at file scope.
 * In practice, main.c sets up the global config before starting the HTTP
 * server, and the http_server passes requests to api_handle_request.
 * We lazily initialise s_config the first time we need it by reading
 * the pointer from the global config variable.
 *
 * We provide a way for external code to set our config pointer:
 */
void api_routes_set_config(const vod_config_t *config)
{
    s_config = config;
    if (s_start_time == 0) {
        s_start_time = time(NULL);
    }
}
