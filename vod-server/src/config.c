#include "config.h"
#include "logger.h"
#include "inih/ini.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>

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
    log_info("=====================");
}
