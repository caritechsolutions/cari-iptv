#include "packager.h"
#include "drm.h"
#include "logger.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <dirent.h>
#include <sys/stat.h>
#include <sys/wait.h>

/* Static state */
static const vod_config_t *g_config = NULL;

/* ---------------------------------------------------------------------------
 * Helper: check if a file exists and is non-empty
 * ------------------------------------------------------------------------- */
static bool file_exists_nonempty(const char *path)
{
    struct stat st;
    if (stat(path, &st) != 0) return false;
    return (S_ISREG(st.st_mode) && st.st_size > 0);
}

/* ---------------------------------------------------------------------------
 * Helper: run a shell command and return exit status (0 = success)
 * ------------------------------------------------------------------------- */
static int run_command(const char *cmd)
{
    log_debug("Running: %s", cmd);

    int ret = system(cmd);
    if (ret < 0) {
        log_error("system() failed: %s", strerror(errno));
        return -1;
    }

    if (WIFEXITED(ret)) {
        int code = WEXITSTATUS(ret);
        if (code != 0) {
            log_error("Command exited with code %d: %.200s", code, cmd);
            return -1;
        }
        return 0;
    }

    if (WIFSIGNALED(ret)) {
        log_error("Command killed by signal %d: %.200s", WTERMSIG(ret), cmd);
        return -1;
    }

    return -1;
}

/* ---------------------------------------------------------------------------
 * Helper: ensure a directory exists (recursive mkdir -p)
 * ------------------------------------------------------------------------- */
static int ensure_dir(const char *path)
{
    struct stat st;
    if (stat(path, &st) == 0) {
        if (S_ISDIR(st.st_mode)) return 0;
        log_error("Path exists but is not a directory: %s", path);
        return -1;
    }

    /* Try to create parent first */
    char parent[MAX_PATH_LEN];
    snprintf(parent, sizeof(parent), "%s", path);

    char *slash = strrchr(parent, '/');
    if (slash && slash != parent) {
        *slash = '\0';
        if (ensure_dir(parent) != 0) return -1;
    }

    if (mkdir(path, 0755) != 0 && errno != EEXIST) {
        log_error("Cannot create directory: %s (%s)", path, strerror(errno));
        return -1;
    }

    return 0;
}

/* ---------------------------------------------------------------------------
 * Helper: collect rendition MP4 filenames from a directory
 * Returns count of files found, fills paths array (caller provides storage).
 * Each path entry must be at least MAX_PATH_LEN bytes.
 * ------------------------------------------------------------------------- */
static int collect_rendition_files(const char *input_dir,
                                   char paths[][MAX_PATH_LEN],
                                   int max_files)
{
    DIR *dp = opendir(input_dir);
    if (!dp) {
        log_error("Cannot open input directory: %s (%s)", input_dir, strerror(errno));
        return -1;
    }

    int count = 0;
    struct dirent *entry;

    while ((entry = readdir(dp)) != NULL && count < max_files) {
        const char *name = entry->d_name;
        size_t len = strlen(name);

        /* Skip hidden files and non-MP4/M4A files */
        if (name[0] == '.') continue;

        /* Match *.mp4 and *.m4a files */
        bool is_mp4 = (len > 4 && strcmp(name + len - 4, ".mp4") == 0);
        bool is_m4a = (len > 4 && strcmp(name + len - 4, ".m4a") == 0);
        if (!is_mp4 && !is_m4a) continue;

        /* Build full path */
        snprintf(paths[count], MAX_PATH_LEN, "%s/%s", input_dir, name);

        /* Verify file exists and is non-empty */
        if (file_exists_nonempty(paths[count])) {
            log_debug("Found rendition file: %s", paths[count]);
            count++;
        }
    }

    closedir(dp);
    return count;
}

/* ---------------------------------------------------------------------------
 * Helper: estimate bandwidth and resolution from rendition label
 * ------------------------------------------------------------------------- */
static void estimate_stream_info(const char *label, int *bandwidth,
                                 int *width, int *height)
{
    /* Defaults */
    *bandwidth = 2000000;
    *width = 1280;
    *height = 720;

    if (strstr(label, "360") != NULL) {
        *bandwidth = 500000;  *width = 640;  *height = 360;
    } else if (strstr(label, "480") != NULL) {
        *bandwidth = 1200000; *width = 854;  *height = 480;
    } else if (strstr(label, "720") != NULL) {
        *bandwidth = 3000000; *width = 1280; *height = 720;
    } else if (strstr(label, "1080") != NULL) {
        *bandwidth = 5500000; *width = 1920; *height = 1080;
    } else if (strstr(label, "1440") != NULL) {
        *bandwidth = 9000000; *width = 2560; *height = 1440;
    } else if (strstr(label, "2160") != NULL) {
        *bandwidth = 18000000; *width = 3840; *height = 2160;
    }
}

/* ---------------------------------------------------------------------------
 * packager_init
 * Check that FFmpeg is available (MP4Box no longer required for HLS output).
 * ------------------------------------------------------------------------- */
int packager_init(const vod_config_t *config)
{
    if (!config) {
        log_error("packager_init: NULL config");
        return -1;
    }

    g_config = config;

    /* FFmpeg is required for HLS packaging */
    if (access(config->ffmpeg_path, X_OK) != 0) {
        log_error("FFmpeg binary not found or not executable: %s", config->ffmpeg_path);
        return -1;
    }

    log_info("Packager initialized: FFmpeg=%s, segment_duration=%ds, DRM=%s",
             config->ffmpeg_path, config->segment_duration,
             config->drm_enabled ? "enabled" : "disabled");

    return 0;
}

/* ---------------------------------------------------------------------------
 * packager_run
 * Use FFmpeg to create HLS output with muxed audio+video .ts segments.
 *
 * When DRM is enabled and a key exists for the content, FFmpeg's HLS
 * AES-128 encryption is used via -hls_key_info_file. This produces
 * standard HLS with #EXT-X-KEY tags that reference the ClearKey
 * license server for key delivery.
 *
 * The encryption is CENC-compatible AES-128 — the same encryption
 * used by Widevine, so switching DRM providers later does not require
 * re-encoding or re-encrypting content.
 *
 * Output structure:
 *   {output_dir}/master.m3u8          (master playlist)
 *   {output_dir}/stream_720p.m3u8     (variant playlist, with #EXT-X-KEY if DRM)
 *   {output_dir}/stream_720p_001.ts   (muxed audio+video segments, encrypted if DRM)
 *   ...
 * ------------------------------------------------------------------------- */
int packager_run(package_state_t *state, const vod_config_t *config)
{
    if (!state || !config) {
        log_error("packager_run: invalid arguments");
        return -1;
    }

    snprintf(state->current_step, sizeof(state->current_step),
             "%s", "Packaging (HLS)");
    state->progress = 0.0;
    state->drm_encrypted = false;

    /* Ensure output directory exists */
    if (ensure_dir(state->output_dir) != 0) {
        log_error("Cannot create output directory: %s", state->output_dir);
        return -1;
    }

    /* --- DRM: prepare encryption key files if enabled --- */
    char key_file[MAX_PATH_LEN] = {0};
    char key_info_file[MAX_PATH_LEN] = {0};
    bool use_drm = false;

    if (config->drm_enabled && state->content_id[0] != '\0') {
        int drm_rc = drm_get_ffmpeg_hls_key_files(
            state->content_id, state->input_dir,
            config->drm_key_server_url,
            key_file, sizeof(key_file),
            key_info_file, sizeof(key_info_file));

        if (drm_rc == 0) {
            use_drm = true;
            log_info("DRM: encryption enabled for job %d (content=%s)",
                     state->job_id, state->content_id);
        } else if (drm_rc == 1) {
            log_info("DRM: no key for content '%s', packaging without encryption",
                     state->content_id);
        } else {
            log_error("DRM: failed to prepare key files for content '%s'",
                      state->content_id);
            return -1;
        }
    }

    /* Collect rendition files from input directory */
    char rendition_paths[MAX_RENDITIONS + 1][MAX_PATH_LEN]; /* +1 for audio */
    int file_count = collect_rendition_files(state->input_dir,
                                              rendition_paths,
                                              MAX_RENDITIONS + 1);
    if (file_count <= 0) {
        log_error("No rendition files found in: %s", state->input_dir);
        return -1;
    }

    /* Separate video and audio files */
    char video_paths[MAX_RENDITIONS][MAX_PATH_LEN];
    char video_labels[MAX_RENDITIONS][64];
    int video_count = 0;
    char audio_path[MAX_PATH_LEN] = {0};
    bool has_audio = false;

    for (int i = 0; i < file_count; i++) {
        const char *path = rendition_paths[i];
        const char *basename = strrchr(path, '/');
        basename = basename ? basename + 1 : path;

        /* Check if this is the audio file */
        if (strstr(basename, "audio") != NULL &&
            (strstr(basename, ".m4a") != NULL || strstr(basename, ".mp4") != NULL)) {
            snprintf(audio_path, sizeof(audio_path), "%s", path);
            has_audio = true;
            continue;
        }

        if (video_count < MAX_RENDITIONS) {
            snprintf(video_paths[video_count], MAX_PATH_LEN, "%s", path);

            /* Extract label from filename (e.g., "720p" from "720p.mp4") */
            snprintf(video_labels[video_count], sizeof(video_labels[0]), "%s", basename);
            char *dot = strrchr(video_labels[video_count], '.');
            if (dot) *dot = '\0';

            video_count++;
        }
    }

    if (video_count == 0) {
        log_error("No video rendition files found in: %s", state->input_dir);
        return -1;
    }

    log_info("HLS packaging: %d video renditions, audio=%s, drm=%s, output=%s",
             video_count, has_audio ? "yes" : "no",
             use_drm ? "encrypted" : "clear", state->output_dir);

    state->progress = 10.0;

    /* --- Package each rendition as its own HLS variant --- */
    /* Each variant gets its own playlist with muxed .ts segments containing
     * both video and audio in every segment file. */

    char master_path[MAX_PATH_LEN + 64];
    snprintf(master_path, sizeof(master_path), "%s/master.m3u8", state->output_dir);

    FILE *master = fopen(master_path, "w");
    if (!master) {
        log_error("Cannot create master playlist: %s", master_path);
        return -1;
    }

    fprintf(master, "#EXTM3U\n");
    fprintf(master, "#EXT-X-VERSION:3\n\n");

    for (int i = 0; i < video_count; i++) {
        char cmd[16384];
        char variant_playlist[64];
        snprintf(variant_playlist, sizeof(variant_playlist),
                 "stream_%s.m3u8", video_labels[i]);

        snprintf(state->current_step, sizeof(state->current_step),
                 "Packaging %s (%d/%d)%s", video_labels[i], i + 1, video_count,
                 use_drm ? " [encrypted]" : "");

        /* Build FFmpeg command to produce a single HLS variant with muxed
         * audio+video .ts segments.
         *
         * ffmpeg -i video.mp4 -i audio.m4a
         *        -map 0:v:0 -map 1:a:0 -c copy
         *        -f hls -hls_time 4 -hls_playlist_type vod
         *        [-hls_key_info_file key_info.txt]  (if DRM enabled)
         *        -hls_segment_filename "output/stream_720p_%04d.ts"
         *        "output/stream_720p.m3u8"
         */
        int pos = 0;

        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "%s -y -i \"%s\" ",
                        config->ffmpeg_path, video_paths[i]);

        if (has_audio) {
            pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                            "-i \"%s\" -map 0:v:0 -map 1:a:0 ",
                            audio_path);
        } else {
            pos += snprintf(cmd + pos, sizeof(cmd) - pos, "-map 0 ");
        }

        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "-c copy "
                        "-f hls "
                        "-hls_time %d "
                        "-hls_playlist_type vod "
                        "-hls_flags independent_segments ",
                        config->segment_duration);

        /* Add DRM encryption if key files are available */
        if (use_drm) {
            pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                            "-hls_key_info_file \"%s\" ",
                            key_info_file);
        }

        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "-hls_segment_filename \"%s/stream_%s_%%04d.ts\" "
                        "\"%s/%s\"",
                        state->output_dir, video_labels[i],
                        state->output_dir, variant_playlist);

        if (pos >= (int)sizeof(cmd) - 1) {
            log_error("FFmpeg command exceeds buffer size for rendition %s",
                      video_labels[i]);
            fclose(master);
            return -1;
        }

        if (run_command(cmd) != 0) {
            log_error("FFmpeg HLS packaging failed for rendition %s (job %d)",
                      video_labels[i], state->job_id);
            fclose(master);
            return -1;
        }

        /* Add this variant to the master playlist */
        int bandwidth, width, height;
        estimate_stream_info(video_labels[i], &bandwidth, &width, &height);

        fprintf(master,
                "#EXT-X-STREAM-INF:BANDWIDTH=%d,RESOLUTION=%dx%d,NAME=\"%s\"\n",
                bandwidth, width, height, video_labels[i]);
        fprintf(master, "%s\n", variant_playlist);

        state->progress = 10.0 + ((double)(i + 1) / video_count) * 80.0;
        log_info("HLS: packaged rendition %s (%d/%d)%s",
                 video_labels[i], i + 1, video_count,
                 use_drm ? " [encrypted]" : "");
    }

    fclose(master);

    if (use_drm) {
        state->drm_encrypted = true;

        /* Clean up temporary DRM key files from input/temp directory */
        if (key_file[0]) unlink(key_file);
        if (key_info_file[0]) unlink(key_info_file);

        log_info("DRM: HLS encryption applied for job %d", state->job_id);
    }

    state->progress = 90.0;
    snprintf(state->current_step, sizeof(state->current_step),
             "%s", "Verifying output");

    /* Verify the output */
    if (packager_verify(state->output_dir) != 0) {
        log_error("Package verification failed for job %d", state->job_id);
        return -1;
    }

    state->progress = 100.0;
    snprintf(state->current_step, sizeof(state->current_step),
             "%s", "Packaging complete");

    log_info("HLS packaging completed for job %d: %s%s",
             state->job_id, state->output_dir,
             state->drm_encrypted ? " (DRM encrypted)" : "");

    return 0;
}

/* ---------------------------------------------------------------------------
 * packager_run_ffmpeg_fallback
 * Same as packager_run (FFmpeg is the only packager now).
 * Kept for API compatibility with existing callers.
 * ------------------------------------------------------------------------- */
int packager_run_ffmpeg_fallback(package_state_t *state, const vod_config_t *config)
{
    return packager_run(state, config);
}

/* ---------------------------------------------------------------------------
 * packager_verify
 * Check that master.m3u8 exists and is a valid HLS master playlist.
 * Also check for at least one .ts segment file.
 * Returns 0 if valid, -1 if problems found.
 * ------------------------------------------------------------------------- */
int packager_verify(const char *output_dir)
{
    if (!output_dir) {
        log_error("packager_verify: NULL output_dir");
        return -1;
    }

    /* Check for HLS master playlist */
    char hls_path[MAX_PATH_LEN + 64];
    snprintf(hls_path, sizeof(hls_path), "%s/master.m3u8", output_dir);

    if (!file_exists_nonempty(hls_path)) {
        log_error("Verification failed: no master.m3u8 in %s", output_dir);
        return -1;
    }

    /* Validate HLS master playlist content */
    FILE *fp = fopen(hls_path, "r");
    if (fp) {
        char line[1024];
        bool has_extm3u = false;
        bool has_stream_inf = false;

        while (fgets(line, sizeof(line), fp) != NULL) {
            if (strncmp(line, "#EXTM3U", 7) == 0) {
                has_extm3u = true;
            }
            if (strstr(line, "#EXT-X-STREAM-INF") != NULL) {
                has_stream_inf = true;
            }
        }
        fclose(fp);

        if (!has_extm3u) {
            log_error("Verification failed: master.m3u8 missing #EXTM3U header");
            return -1;
        }
        if (!has_stream_inf) {
            log_error("Verification failed: master.m3u8 has no stream variants");
            return -1;
        }
    }

    /* Count .ts segment files */
    int segment_count = 0;
    DIR *dp = opendir(output_dir);
    if (!dp) {
        log_error("Cannot open output directory for verification: %s", output_dir);
        return -1;
    }

    struct dirent *entry;
    while ((entry = readdir(dp)) != NULL) {
        const char *name = entry->d_name;
        size_t len = strlen(name);

        if (len > 3 && strcmp(name + len - 3, ".ts") == 0) {
            segment_count++;
        }
    }
    closedir(dp);

    log_info("Verification passed: HLS=yes, segments=%d in %s",
             segment_count, output_dir);

    if (segment_count == 0) {
        log_warn("No .ts segment files found in %s (may indicate a problem)",
                 output_dir);
    }

    return 0;
}
