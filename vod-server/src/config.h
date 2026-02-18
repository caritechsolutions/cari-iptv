#ifndef VOD_CONFIG_H
#define VOD_CONFIG_H

#include <stdbool.h>

/* Maximum lengths */
#define MAX_PATH_LEN        4096
#define MAX_KEY_LEN         128
#define MAX_VALUE_LEN       512
#define MAX_RENDITIONS      8
#define MAX_PROFILES        16
#define MAX_PROFILE_NAME    64

/* Rendition definition */
typedef struct {
    char label[16];         /* e.g. "360p", "720p", "1080p", "2160p" */
    int  width;
    int  height;
    int  bitrate_kbps;      /* Video bitrate in kbps */
} rendition_t;

/* Transcode profile */
typedef struct {
    char         name[MAX_PROFILE_NAME];
    char         codec[32];           /* libx264, libx265, libsvtav1 */
    char         preset[32];          /* slow, medium, fast */
    int          crf;
    char         audio_codec[32];     /* aac */
    char         audio_bitrate[16];   /* 128k */
    int          rendition_count;
    rendition_t  renditions[MAX_RENDITIONS];
} transcode_profile_t;

/* Server configuration */
typedef struct {
    /* [server] */
    int          port;
    char         bind_address[64];
    char         api_key[256];
    char         log_file[MAX_PATH_LEN];
    char         log_level[16];
    char         pid_file[MAX_PATH_LEN];
    char         www_root[MAX_PATH_LEN];

    /* [ssl] */
    bool         ssl_enabled;
    char         ssl_cert_file[MAX_PATH_LEN];
    char         ssl_key_file[MAX_PATH_LEN];
    bool         ssl_auto_self_signed;

    /* [storage] */
    char         library_path[MAX_PATH_LEN];
    char         temp_path[MAX_PATH_LEN];
    int          min_free_space_gb;
    char         database_path[MAX_PATH_LEN];

    /* [transcoding] */
    int          max_concurrent_jobs;
    char         ffmpeg_path[MAX_PATH_LEN];
    char         ffprobe_path[MAX_PATH_LEN];
    char         mp4box_path[MAX_PATH_LEN];
    char         default_profile[MAX_PROFILE_NAME];
    int          segment_duration;
    int          gop_size;
    int          poll_interval;
    char         hwaccel[32];
    int          gpu_device;

    /* Profiles */
    int                  profile_count;
    transcode_profile_t  profiles[MAX_PROFILES];

    /* [thumbnails] */
    bool         thumbnails_enabled;
    int          thumb_interval;
    int          thumb_width;
    int          thumb_height;
    int          thumb_columns;
    int          thumb_quality;

    /* [cluster] */
    char         node_name[128];
    int          health_check_interval;
    int          offline_threshold;
    int          max_concurrent_migrations;
} vod_config_t;

/**
 * Load configuration from an INI file.
 * Returns 0 on success, -1 on error.
 */
int config_load(vod_config_t *config, const char *path);

/**
 * Set default values for all configuration fields.
 */
void config_set_defaults(vod_config_t *config);

/**
 * Get a transcode profile by name.
 * Returns NULL if not found.
 */
const transcode_profile_t *config_get_profile(const vod_config_t *config, const char *name);

/**
 * Dump configuration to log (for debugging).
 */
void config_dump(const vod_config_t *config);

/**
 * Add or update a profile in the config (in-memory).
 * Returns 0 on success, -1 if MAX_PROFILES reached and name is new.
 */
int config_set_profile(vod_config_t *config, const transcode_profile_t *profile);

/**
 * Delete a profile by name from the config (in-memory).
 * Returns 0 on success, -1 if not found.
 */
int config_delete_profile(vod_config_t *config, const char *name);

/**
 * Load profiles from SQLite settings table (key='profiles').
 * Merges with/overrides existing profiles from the INI file.
 * Call after db_init().  Returns number of profiles loaded, or -1 on error.
 */
int config_load_db_profiles(vod_config_t *config);

/**
 * Save all current profiles to SQLite settings table (key='profiles').
 * Returns 0 on success, -1 on error.
 */
int config_save_db_profiles(const vod_config_t *config);

#endif /* VOD_CONFIG_H */
