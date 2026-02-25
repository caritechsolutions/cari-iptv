#include "config.h"
#include "database.h"
#include "logger.h"
#include "inih/ini.h"
#include "cjson/cJSON.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sqlite3.h>

/* Resolution lookup table */
static const struct {
    const char *label;
    int width;
    int height;
} resolution_map[] = {
    { "360p",  640,  360  },
    { "480p",  854,  480  },
    { "576p",  1024, 576  },
    { "720p",  1280, 720  },
    { "1080p", 1920, 1080 },
    { "1440p", 2560, 1440 },
    { "2160p", 3840, 2160 },
    { NULL, 0, 0 }
};

static int lookup_resolution(const char *label, int *w, int *h)
{
    for (int i = 0; resolution_map[i].label; i++) {
        if (strcasecmp(resolution_map[i].label, label) == 0) {
            *w = resolution_map[i].width;
            *h = resolution_map[i].height;
            return 0;
        }
    }
    return -1;
}

/**
 * Parse a renditions string like "360p:400k,720p:2800k,1080p:5000k"
 */
static int parse_renditions(transcode_profile_t *profile, const char *value)
{
    char buf[MAX_VALUE_LEN];
    snprintf(buf, sizeof(buf), "%s", value);

    profile->rendition_count = 0;
    char *saveptr = NULL;
    char *token = strtok_r(buf, ",", &saveptr);

    while (token && profile->rendition_count < MAX_RENDITIONS) {
        /* Trim whitespace */
        while (*token == ' ') token++;

        char *colon = strchr(token, ':');
        if (!colon) {
            log_warn("Invalid rendition format: %s (expected label:bitrate)", token);
            token = strtok_r(NULL, ",", &saveptr);
            continue;
        }
        *colon = '\0';
        const char *label = token;
        const char *bitrate_str = colon + 1;

        rendition_t *r = &profile->renditions[profile->rendition_count];
        snprintf(r->label, sizeof(r->label), "%s", label);

        if (lookup_resolution(label, &r->width, &r->height) != 0) {
            log_warn("Unknown resolution: %s", label);
            token = strtok_r(NULL, ",", &saveptr);
            continue;
        }

        /* Parse bitrate: "2800k" -> 2800 */
        r->bitrate_kbps = atoi(bitrate_str);

        profile->rendition_count++;
        token = strtok_r(NULL, ",", &saveptr);
    }

    return profile->rendition_count > 0 ? 0 : -1;
}

/**
 * Find or create a profile by name.
 */
static transcode_profile_t *find_or_create_profile(vod_config_t *config, const char *name)
{
    /* Check existing */
    for (int i = 0; i < config->profile_count; i++) {
        if (strcmp(config->profiles[i].name, name) == 0) {
            return &config->profiles[i];
        }
    }
    /* Create new */
    if (config->profile_count >= MAX_PROFILES) {
        log_warn("Max profiles reached (%d), ignoring: %s", MAX_PROFILES, name);
        return NULL;
    }
    transcode_profile_t *p = &config->profiles[config->profile_count];
    memset(p, 0, sizeof(*p));
    snprintf(p->name, sizeof(p->name), "%s", name);
    config->profile_count++;
    return p;
}

/**
 * INI parser callback.
 */
static int config_ini_callback(void *user, const char *section, const char *name, const char *value)
{
    vod_config_t *config = (vod_config_t *)user;

    #define MATCH(s, n) (strcmp(section, s) == 0 && strcmp(name, n) == 0)
    #define COPY_STR(dest) snprintf(dest, sizeof(dest), "%s", value)

    /* [server] */
    if (MATCH("server", "port"))          config->port = atoi(value);
    else if (MATCH("server", "bind_address")) COPY_STR(config->bind_address);
    else if (MATCH("server", "api_key"))      COPY_STR(config->api_key);
    else if (MATCH("server", "log_file"))     COPY_STR(config->log_file);
    else if (MATCH("server", "log_level"))    COPY_STR(config->log_level);
    else if (MATCH("server", "pid_file"))     COPY_STR(config->pid_file);
    else if (MATCH("server", "www_root"))     COPY_STR(config->www_root);

    /* [ssl] */
    else if (MATCH("ssl", "enabled"))         config->ssl_enabled = (atoi(value) || strcasecmp(value, "true") == 0);
    else if (MATCH("ssl", "cert_file"))       COPY_STR(config->ssl_cert_file);
    else if (MATCH("ssl", "key_file"))        COPY_STR(config->ssl_key_file);
    else if (MATCH("ssl", "auto_self_signed")) config->ssl_auto_self_signed = (atoi(value) || strcasecmp(value, "true") == 0);

    /* [storage] */
    else if (MATCH("storage", "library_path"))    COPY_STR(config->library_path);
    else if (MATCH("storage", "temp_path"))       COPY_STR(config->temp_path);
    else if (MATCH("storage", "min_free_space_gb")) config->min_free_space_gb = atoi(value);
    else if (MATCH("storage", "database_path"))   COPY_STR(config->database_path);

    /* [transcoding] */
    else if (MATCH("transcoding", "max_concurrent_jobs")) config->max_concurrent_jobs = atoi(value);
    else if (MATCH("transcoding", "ffmpeg_path"))     COPY_STR(config->ffmpeg_path);
    else if (MATCH("transcoding", "ffprobe_path"))    COPY_STR(config->ffprobe_path);
    else if (MATCH("transcoding", "mp4box_path"))     COPY_STR(config->mp4box_path);
    else if (MATCH("transcoding", "default_profile")) COPY_STR(config->default_profile);
    else if (MATCH("transcoding", "segment_duration")) config->segment_duration = atoi(value);
    else if (MATCH("transcoding", "gop_size"))        config->gop_size = atoi(value);
    else if (MATCH("transcoding", "poll_interval"))   config->poll_interval = atoi(value);
    else if (MATCH("transcoding", "hwaccel"))         COPY_STR(config->hwaccel);
    else if (MATCH("transcoding", "gpu_device"))      config->gpu_device = atoi(value);

    /* [thumbnails] */
    else if (MATCH("thumbnails", "enabled"))  config->thumbnails_enabled = (atoi(value) || strcasecmp(value, "true") == 0);
    else if (MATCH("thumbnails", "interval")) config->thumb_interval = atoi(value);
    else if (MATCH("thumbnails", "width"))    config->thumb_width = atoi(value);
    else if (MATCH("thumbnails", "height"))   config->thumb_height = atoi(value);
    else if (MATCH("thumbnails", "columns"))  config->thumb_columns = atoi(value);
    else if (MATCH("thumbnails", "quality"))  config->thumb_quality = atoi(value);

    /* [cluster] */
    else if (MATCH("cluster", "node_name"))              COPY_STR(config->node_name);
    else if (MATCH("cluster", "health_check_interval"))  config->health_check_interval = atoi(value);
    else if (MATCH("cluster", "offline_threshold"))      config->offline_threshold = atoi(value);
    else if (MATCH("cluster", "max_concurrent_migrations")) config->max_concurrent_migrations = atoi(value);

    /* [drm] */
    else if (MATCH("drm", "enabled"))          config->drm_enabled = (atoi(value) || strcasecmp(value, "true") == 0);
    else if (MATCH("drm", "scheme"))           COPY_STR(config->drm_scheme);
    else if (MATCH("drm", "key_server_url"))   COPY_STR(config->drm_key_server_url);
    else if (MATCH("drm", "auto_generate"))    config->drm_auto_generate = (atoi(value) || strcasecmp(value, "true") == 0);

    /* [acme] */
    else if (MATCH("acme", "enabled"))         config->acme_enabled = (atoi(value) || strcasecmp(value, "true") == 0);
    else if (MATCH("acme", "http_port"))       config->acme_http_port = atoi(value);
    else if (MATCH("acme", "webroot"))         COPY_STR(config->acme_webroot);
    else if (MATCH("acme", "domain"))          COPY_STR(config->acme_domain);
    else if (MATCH("acme", "https_port"))      config->acme_https_port = atoi(value);

    /* [profile:*] sections */
    else if (strncmp(section, "profile:", 8) == 0) {
        const char *profile_name = section + 8;
        transcode_profile_t *p = find_or_create_profile(config, profile_name);
        if (!p) return 1;

        if (strcmp(name, "codec") == 0)         snprintf(p->codec, sizeof(p->codec), "%s", value);
        else if (strcmp(name, "preset") == 0)   snprintf(p->preset, sizeof(p->preset), "%s", value);
        else if (strcmp(name, "crf") == 0)      p->crf = atoi(value);
        else if (strcmp(name, "audio_codec") == 0)   snprintf(p->audio_codec, sizeof(p->audio_codec), "%s", value);
        else if (strcmp(name, "audio_bitrate") == 0) snprintf(p->audio_bitrate, sizeof(p->audio_bitrate), "%s", value);
        else if (strcmp(name, "renditions") == 0)    parse_renditions(p, value);
    }

    #undef MATCH
    #undef COPY_STR

    return 1; /* 1 = success for inih */
}

void config_set_defaults(vod_config_t *config)
{
    memset(config, 0, sizeof(*config));

    /* Server */
    config->port = 8090;
    snprintf(config->bind_address, sizeof(config->bind_address), "%s", "0.0.0.0");
    snprintf(config->api_key, sizeof(config->api_key), "%s", "change-me-on-first-run");
    snprintf(config->log_file, sizeof(config->log_file), "%s", "/var/log/vod-server/vod-server.log");
    snprintf(config->log_level, sizeof(config->log_level), "%s", "info");
    snprintf(config->pid_file, sizeof(config->pid_file), "%s", "/var/run/vod-server.pid");
    snprintf(config->www_root, sizeof(config->www_root), "%s", "/usr/local/share/vod-server/www");

    /* SSL */
    config->ssl_enabled = false;
    snprintf(config->ssl_cert_file, sizeof(config->ssl_cert_file), "%s", "/etc/vod-server/ssl/cert.pem");
    snprintf(config->ssl_key_file, sizeof(config->ssl_key_file), "%s", "/etc/vod-server/ssl/key.pem");
    config->ssl_auto_self_signed = true;

    /* Storage */
    snprintf(config->library_path, sizeof(config->library_path), "%s", "/var/lib/vod-server/library");
    snprintf(config->temp_path, sizeof(config->temp_path), "%s", "/var/lib/vod-server/tmp");
    config->min_free_space_gb = 10;
    snprintf(config->database_path, sizeof(config->database_path), "%s", "/var/lib/vod-server/vod-server.db");

    /* Transcoding */
    config->max_concurrent_jobs = 2;
    snprintf(config->ffmpeg_path, sizeof(config->ffmpeg_path), "%s", "/usr/local/bin/ffmpeg");
    snprintf(config->ffprobe_path, sizeof(config->ffprobe_path), "%s", "/usr/local/bin/ffprobe");
    snprintf(config->mp4box_path, sizeof(config->mp4box_path), "%s", "/usr/bin/MP4Box");
    snprintf(config->default_profile, sizeof(config->default_profile), "%s", "standard");
    config->segment_duration = 6;
    config->gop_size = 48;
    config->poll_interval = 10;
    snprintf(config->hwaccel, sizeof(config->hwaccel), "%s", "none");
    config->gpu_device = 0;

    /* Thumbnails */
    config->thumbnails_enabled = true;
    config->thumb_interval = 10;
    config->thumb_width = 160;
    config->thumb_height = 90;
    config->thumb_columns = 10;
    config->thumb_quality = 75;

    /* Cluster */
    snprintf(config->node_name, sizeof(config->node_name), "%s", "vod-node-1");
    config->health_check_interval = 30;
    config->offline_threshold = 3;
    config->max_concurrent_migrations = 1;

    /* DRM */
    config->drm_enabled = false;
    snprintf(config->drm_scheme, sizeof(config->drm_scheme), "%s", "cenc");
    config->drm_key_server_url[0] = '\0';
    config->drm_auto_generate = true;

    /* ACME */
    config->acme_enabled = false;
    config->acme_http_port = 80;
    snprintf(config->acme_webroot, sizeof(config->acme_webroot), "%s", "/var/lib/vod-server/acme");
    config->acme_domain[0] = '\0';
    config->acme_https_port = 443;
}

int config_load(vod_config_t *config, const char *path)
{
    config_set_defaults(config);

    int result = ini_parse(path, config_ini_callback, config);
    if (result < 0) {
        fprintf(stderr, "Cannot load config file: %s\n", path);
        return -1;
    }
    if (result > 0) {
        fprintf(stderr, "Config parse error on line %d\n", result);
        return -1;
    }

    return 0;
}

const transcode_profile_t *config_get_profile(const vod_config_t *config, const char *name)
{
    for (int i = 0; i < config->profile_count; i++) {
        if (strcmp(config->profiles[i].name, name) == 0) {
            return &config->profiles[i];
        }
    }
    return NULL;
}

void config_dump(const vod_config_t *config)
{
    log_info("=== Configuration ===");
    log_info("Server: %s:%d", config->bind_address, config->port);
    log_info("SSL: %s", config->ssl_enabled ? "enabled" : "disabled");
    log_info("Library: %s", config->library_path);
    log_info("Temp: %s", config->temp_path);
    log_info("Database: %s", config->database_path);
    log_info("Max concurrent jobs: %d", config->max_concurrent_jobs);
    log_info("Default profile: %s", config->default_profile);
    log_info("FFmpeg: %s", config->ffmpeg_path);
    log_info("MP4Box: %s", config->mp4box_path);
    log_info("HW accel: %s", config->hwaccel);
    log_info("Profiles loaded: %d", config->profile_count);

    for (int i = 0; i < config->profile_count; i++) {
        const transcode_profile_t *p = &config->profiles[i];
        log_info("  Profile '%s': codec=%s preset=%s crf=%d renditions=%d",
                 p->name, p->codec, p->preset, p->crf, p->rendition_count);
        for (int j = 0; j < p->rendition_count; j++) {
            log_info("    %s: %dx%d @ %dk",
                     p->renditions[j].label, p->renditions[j].width,
                     p->renditions[j].height, p->renditions[j].bitrate_kbps);
        }
    }
    log_info("Cluster node: %s", config->node_name);
    log_info("DRM: %s (scheme=%s, auto_generate=%s)",
             config->drm_enabled ? "enabled" : "disabled",
             config->drm_scheme,
             config->drm_auto_generate ? "yes" : "no");
    if (config->drm_key_server_url[0]) {
        log_info("DRM key server: %s", config->drm_key_server_url);
    }
    log_info("ACME: %s (http_port=%d, webroot=%s)",
             config->acme_enabled ? "enabled" : "disabled",
             config->acme_http_port,
             config->acme_webroot);
    if (config->acme_enabled && config->acme_domain[0]) {
        log_info("ACME domain: %s (redirect to HTTPS port %d)",
                 config->acme_domain, config->acme_https_port);
    }
    log_info("=====================");
}

/* ================================================================
 * Profile CRUD (in-memory)
 * ================================================================ */

int config_set_profile(vod_config_t *config, const transcode_profile_t *profile)
{
    if (!config || !profile || profile->name[0] == '\0') return -1;

    /* Update existing */
    for (int i = 0; i < config->profile_count; i++) {
        if (strcmp(config->profiles[i].name, profile->name) == 0) {
            memcpy(&config->profiles[i], profile, sizeof(transcode_profile_t));
            return 0;
        }
    }

    /* Add new */
    if (config->profile_count >= MAX_PROFILES) {
        log_warn("Cannot add profile '%s': max profiles (%d) reached", profile->name, MAX_PROFILES);
        return -1;
    }

    memcpy(&config->profiles[config->profile_count], profile, sizeof(transcode_profile_t));
    config->profile_count++;
    return 0;
}

int config_delete_profile(vod_config_t *config, const char *name)
{
    if (!config || !name) return -1;

    for (int i = 0; i < config->profile_count; i++) {
        if (strcmp(config->profiles[i].name, name) == 0) {
            /* Shift remaining profiles down */
            for (int j = i; j < config->profile_count - 1; j++) {
                memcpy(&config->profiles[j], &config->profiles[j + 1], sizeof(transcode_profile_t));
            }
            config->profile_count--;
            memset(&config->profiles[config->profile_count], 0, sizeof(transcode_profile_t));
            return 0;
        }
    }
    return -1; /* Not found */
}

/* ================================================================
 * Profile persistence (SQLite settings table)
 * ================================================================ */

int config_save_db_profiles(const vod_config_t *config)
{
    if (!config) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    /* Build JSON array of profiles */
    cJSON *arr = cJSON_CreateArray();
    for (int i = 0; i < config->profile_count; i++) {
        const transcode_profile_t *p = &config->profiles[i];
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
        cJSON_AddItemToArray(arr, prof);
    }

    char *json_str = cJSON_PrintUnformatted(arr);
    cJSON_Delete(arr);
    if (!json_str) return -1;

    sqlite3_stmt *stmt;
    const char *sql = "INSERT OR REPLACE INTO settings (key, value, updated_at) "
                      "VALUES ('profiles', ?, CURRENT_TIMESTAMP)";
    int rc = sqlite3_prepare_v2(db, sql, -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        free(json_str);
        return -1;
    }
    sqlite3_bind_text(stmt, 1, json_str, -1, SQLITE_STATIC);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    free(json_str);

    return (rc == SQLITE_DONE) ? 0 : -1;
}

int config_load_db_profiles(vod_config_t *config)
{
    if (!config) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt;
    const char *sql = "SELECT value FROM settings WHERE key = 'profiles'";
    if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) != SQLITE_OK) {
        return -1;
    }

    int loaded = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        const char *json_str = (const char *)sqlite3_column_text(stmt, 0);
        if (json_str) {
            cJSON *arr = cJSON_Parse(json_str);
            if (cJSON_IsArray(arr)) {
                /* Replace all profiles with DB version */
                config->profile_count = 0;

                cJSON *item;
                cJSON_ArrayForEach(item, arr) {
                    if (config->profile_count >= MAX_PROFILES) break;

                    transcode_profile_t *p = &config->profiles[config->profile_count];
                    memset(p, 0, sizeof(*p));

                    cJSON *jname = cJSON_GetObjectItemCaseSensitive(item, "name");
                    if (!cJSON_IsString(jname) || !jname->valuestring[0]) continue;

                    snprintf(p->name, sizeof(p->name), "%s", jname->valuestring);

                    cJSON *jcodec = cJSON_GetObjectItemCaseSensitive(item, "codec");
                    if (cJSON_IsString(jcodec)) snprintf(p->codec, sizeof(p->codec), "%s", jcodec->valuestring);

                    cJSON *jpreset = cJSON_GetObjectItemCaseSensitive(item, "preset");
                    if (cJSON_IsString(jpreset)) snprintf(p->preset, sizeof(p->preset), "%s", jpreset->valuestring);

                    cJSON *jcrf = cJSON_GetObjectItemCaseSensitive(item, "crf");
                    if (cJSON_IsNumber(jcrf)) p->crf = jcrf->valueint;

                    cJSON *jacodec = cJSON_GetObjectItemCaseSensitive(item, "audio_codec");
                    if (cJSON_IsString(jacodec)) snprintf(p->audio_codec, sizeof(p->audio_codec), "%s", jacodec->valuestring);

                    cJSON *jabitrate = cJSON_GetObjectItemCaseSensitive(item, "audio_bitrate");
                    if (cJSON_IsString(jabitrate)) snprintf(p->audio_bitrate, sizeof(p->audio_bitrate), "%s", jabitrate->valuestring);

                    cJSON *jrends = cJSON_GetObjectItemCaseSensitive(item, "renditions");
                    if (cJSON_IsArray(jrends)) {
                        p->rendition_count = 0;
                        cJSON *ritem;
                        cJSON_ArrayForEach(ritem, jrends) {
                            if (p->rendition_count >= MAX_RENDITIONS) break;
                            rendition_t *r = &p->renditions[p->rendition_count];

                            cJSON *rl = cJSON_GetObjectItemCaseSensitive(ritem, "label");
                            if (cJSON_IsString(rl)) snprintf(r->label, sizeof(r->label), "%s", rl->valuestring);

                            cJSON *rw = cJSON_GetObjectItemCaseSensitive(ritem, "width");
                            if (cJSON_IsNumber(rw)) r->width = rw->valueint;

                            cJSON *rh = cJSON_GetObjectItemCaseSensitive(ritem, "height");
                            if (cJSON_IsNumber(rh)) r->height = rh->valueint;

                            cJSON *rb = cJSON_GetObjectItemCaseSensitive(ritem, "bitrate_kbps");
                            if (cJSON_IsNumber(rb)) r->bitrate_kbps = rb->valueint;

                            /* If width/height missing, try lookup from label */
                            if (r->width == 0 && r->label[0]) {
                                lookup_resolution(r->label, &r->width, &r->height);
                            }

                            p->rendition_count++;
                        }
                    }

                    config->profile_count++;
                    loaded++;
                }
            }
            cJSON_Delete(arr);
        }
    }

    sqlite3_finalize(stmt);
    log_info("Loaded %d profiles from database", loaded);
    return loaded;
}

/* ================================================================
 * DRM settings persistence (SQLite settings table)
 * ================================================================ */

int config_save_db_settings(const vod_config_t *config)
{
    if (!config) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    cJSON *obj = cJSON_CreateObject();
    cJSON_AddBoolToObject(obj, "enabled", config->drm_enabled);
    cJSON_AddStringToObject(obj, "scheme", config->drm_scheme);
    cJSON_AddStringToObject(obj, "key_server_url", config->drm_key_server_url);
    cJSON_AddBoolToObject(obj, "auto_generate", config->drm_auto_generate);

    char *json_str = cJSON_PrintUnformatted(obj);
    cJSON_Delete(obj);
    if (!json_str) return -1;

    sqlite3_stmt *stmt;
    const char *sql = "INSERT OR REPLACE INTO settings (key, value, updated_at) "
                      "VALUES ('drm_settings', ?, CURRENT_TIMESTAMP)";
    int rc = sqlite3_prepare_v2(db, sql, -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        free(json_str);
        return -1;
    }
    sqlite3_bind_text(stmt, 1, json_str, -1, SQLITE_STATIC);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);
    free(json_str);

    return (rc == SQLITE_DONE) ? 0 : -1;
}

int config_load_db_settings(vod_config_t *config)
{
    if (!config) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt;
    const char *sql = "SELECT value FROM settings WHERE key = 'drm_settings'";
    if (sqlite3_prepare_v2(db, sql, -1, &stmt, NULL) != SQLITE_OK) {
        return -1;
    }

    int result = -1;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        const char *json_str = (const char *)sqlite3_column_text(stmt, 0);
        if (json_str) {
            cJSON *obj = cJSON_Parse(json_str);
            if (cJSON_IsObject(obj)) {
                cJSON *enabled = cJSON_GetObjectItemCaseSensitive(obj, "enabled");
                if (cJSON_IsBool(enabled)) config->drm_enabled = cJSON_IsTrue(enabled);

                cJSON *scheme = cJSON_GetObjectItemCaseSensitive(obj, "scheme");
                if (cJSON_IsString(scheme) && scheme->valuestring[0])
                    snprintf(config->drm_scheme, sizeof(config->drm_scheme), "%s", scheme->valuestring);

                cJSON *key_url = cJSON_GetObjectItemCaseSensitive(obj, "key_server_url");
                if (cJSON_IsString(key_url))
                    snprintf(config->drm_key_server_url, sizeof(config->drm_key_server_url), "%s", key_url->valuestring);

                cJSON *auto_gen = cJSON_GetObjectItemCaseSensitive(obj, "auto_generate");
                if (cJSON_IsBool(auto_gen)) config->drm_auto_generate = cJSON_IsTrue(auto_gen);

                result = 0;
            }
            cJSON_Delete(obj);
        }
    }

    sqlite3_finalize(stmt);
    if (result == 0) {
        log_info("DRM settings loaded from database (enabled=%s, scheme=%s, auto_generate=%s)",
                 config->drm_enabled ? "yes" : "no",
                 config->drm_scheme,
                 config->drm_auto_generate ? "yes" : "no");
    }
    return result;
}
