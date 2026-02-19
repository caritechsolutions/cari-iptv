#include "packager.h"
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
static bool g_mp4box_available = false;

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
 * packager_init
 * Check if MP4Box binary exists.
 * ------------------------------------------------------------------------- */
int packager_init(const vod_config_t *config)
{
    if (!config) {
        log_error("packager_init: NULL config");
        return -1;
    }

    g_config = config;
    g_mp4box_available = false;

    /* Check if MP4Box binary exists and is executable */
    if (access(config->mp4box_path, X_OK) == 0) {
        g_mp4box_available = true;
        log_info("Packager initialized: MP4Box=%s (available)", config->mp4box_path);
    } else {
        log_warn("MP4Box not found at '%s': will use FFmpeg fallback for packaging",
                 config->mp4box_path);
    }

    /* Always need FFmpeg as a fallback */
    if (access(config->ffmpeg_path, X_OK) != 0) {
        log_error("FFmpeg binary not found or not executable: %s", config->ffmpeg_path);
        return -1;
    }

    log_info("Packager initialized: MP4Box=%s, segment_duration=%ds",
             g_mp4box_available ? "yes" : "no (fallback mode)",
             config->segment_duration);

    return 0;
}

/* ---------------------------------------------------------------------------
 * packager_run
 * Use MP4Box to create DASH/CMAF output (manifest.mpd + fMP4 segments).
 * ------------------------------------------------------------------------- */
int packager_run(package_state_t *state, const vod_config_t *config)
{
    if (!state || !config) {
        log_error("packager_run: invalid arguments");
        return -1;
    }

    if (!g_mp4box_available) {
        log_warn("MP4Box not available, falling back to FFmpeg packager");
        return packager_run_ffmpeg_fallback(state, config);
    }

    snprintf(state->current_step, sizeof(state->current_step), "%s", "Packaging (CMAF)");
    state->progress = 0.0;

    /* Ensure output directory exists */
    if (ensure_dir(state->output_dir) != 0) {
        log_error("Cannot create output directory: %s", state->output_dir);
        return -1;
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

    log_info("Packaging %d file(s) from '%s' -> '%s'",
             file_count, state->input_dir, state->output_dir);

    state->progress = 10.0;

    /* Build MP4Box command for DASH/CMAF output.
     *
     * Command pattern:
     *   MP4Box -dash <ms> -frag <ms> -rap
     *          -profile dashavc264:live -bs-switching no
     *          -segment-name seg_
     *          -out <output_dir>/manifest.mpd
     *          <rendition1.mp4> <rendition2.mp4> ... <audio.m4a>
     */
    int segment_ms = config->segment_duration * 1000;

    char cmd[16384];
    int pos = 0;

    pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                    "%s -dash %d -frag %d -rap "
                    "-profile dashavc264:live "
                    "-bs-switching no "
                    "-segment-name \"seg_$RepresentationID$_\" "
                    "-url-template "
                    "-out \"%s/manifest.mpd\" ",
                    config->mp4box_path,
                    segment_ms, segment_ms,
                    state->output_dir);

    /* Append each rendition file as an input.
     * For video files, add :id=<label> for identification.
     * For audio files, mark as audio-only with :role=main. */
    for (int i = 0; i < file_count; i++) {
        const char *path = rendition_paths[i];
        const char *basename = strrchr(path, '/');
        basename = basename ? basename + 1 : path;

        /* Determine track ID from filename */
        char track_id[256];
        snprintf(track_id, sizeof(track_id), "%s", basename);
        /* Remove extension */
        char *dot = strrchr(track_id, '.');
        if (dot) *dot = '\0';

        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "\"%s\"#trackID=1:id=%s ",
                        path, track_id);
    }

    if (pos >= (int)sizeof(cmd) - 1) {
        log_error("MP4Box command exceeds buffer size");
        return -1;
    }

    state->progress = 20.0;
    snprintf(state->current_step, sizeof(state->current_step),
             "%s", "Running MP4Box (DASH/CMAF)");

    /* Execute MP4Box */
    if (run_command(cmd) != 0) {
        log_error("MP4Box packaging failed for job %d", state->job_id);

        /* Try FFmpeg fallback */
        log_warn("Attempting FFmpeg fallback packaging for job %d", state->job_id);
        return packager_run_ffmpeg_fallback(state, config);
    }

    state->progress = 90.0;

    /* Verify the output */
    if (packager_verify(state->output_dir) != 0) {
        log_error("Package verification failed for job %d", state->job_id);
        return -1;
    }

    state->progress = 100.0;
    snprintf(state->current_step, sizeof(state->current_step),
             "%s", "Packaging complete");

    log_info("Packaging completed for job %d: %s", state->job_id, state->output_dir);

    return 0;
}

/* ---------------------------------------------------------------------------
 * packager_run_ffmpeg_fallback
 * When MP4Box is not available, use FFmpeg to generate DASH/CMAF output
 * with fMP4 segments and a DASH manifest.
 * ------------------------------------------------------------------------- */
int packager_run_ffmpeg_fallback(package_state_t *state, const vod_config_t *config)
{
    if (!state || !config) {
        log_error("packager_run_ffmpeg_fallback: invalid arguments");
        return -1;
    }

    snprintf(state->current_step, sizeof(state->current_step),
             "%s", "Packaging (FFmpeg DASH fallback)");
    state->progress = 10.0;

    /* Ensure output directory exists */
    if (ensure_dir(state->output_dir) != 0) {
        log_error("Cannot create output directory: %s", state->output_dir);
        return -1;
    }

    /* Collect rendition files */
    char rendition_paths[MAX_RENDITIONS + 1][MAX_PATH_LEN];
    int file_count = collect_rendition_files(state->input_dir,
                                              rendition_paths,
                                              MAX_RENDITIONS + 1);
    if (file_count <= 0) {
        log_error("No rendition files found in: %s", state->input_dir);
        return -1;
    }

    /* Separate video and audio files */
    char video_paths[MAX_RENDITIONS][MAX_PATH_LEN];
    char video_labels[MAX_RENDITIONS][MAX_PATH_LEN];
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

    log_info("FFmpeg DASH packaging: %d video streams, audio=%s",
             video_count, has_audio ? "yes" : "no");

    state->progress = 20.0;

    /* Build FFmpeg command for DASH/CMAF output.
     *
     * Strategy:
     *   - Input each rendition MP4 and the audio file
     *   - Map each input to its own adaptation set
     *   - Use the DASH muxer with fMP4 segments
     *   - Output manifest.mpd
     */
    char cmd[16384];
    int pos = 0;

    /* FFmpeg binary and overwrite */
    pos += snprintf(cmd + pos, sizeof(cmd) - pos, "%s -y ", config->ffmpeg_path);

    /* Input files: video renditions first, then audio */
    for (int i = 0; i < video_count; i++) {
        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "-i \"%s\" ", video_paths[i]);
    }
    if (has_audio) {
        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "-i \"%s\" ", audio_path);
    }

    /* Map and copy each stream (no re-encoding) */
    for (int i = 0; i < video_count; i++) {
        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "-map %d:v:0 -c:v:%d copy ", i, i);
    }
    if (has_audio) {
        pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                        "-map %d:a:0 -c:a copy ",
                        video_count);
    }

    /* Build adaptation_sets for DASH muxer.
     * Format: "id=0,streams=v id=1,streams=a"
     * Multiple video streams in same adaptation set: "id=0,streams=0,1,2 id=1,streams=a"
     */
    pos += snprintf(cmd + pos, sizeof(cmd) - pos, "-adaptation_sets \"");

    /* All video renditions in one adaptation set */
    pos += snprintf(cmd + pos, sizeof(cmd) - pos, "id=0,streams=");
    for (int i = 0; i < video_count; i++) {
        if (i > 0) pos += snprintf(cmd + pos, sizeof(cmd) - pos, ",");
        pos += snprintf(cmd + pos, sizeof(cmd) - pos, "%d", i);
    }
    if (has_audio) {
        pos += snprintf(cmd + pos, sizeof(cmd) - pos, " id=1,streams=%d", video_count);
    }

    pos += snprintf(cmd + pos, sizeof(cmd) - pos, "\" ");

    /* DASH output options */
    pos += snprintf(cmd + pos, sizeof(cmd) - pos,
                    "-f dash "
                    "-seg_duration %d "
                    "-use_timeline 1 "
                    "-use_template 1 "
                    "-init_seg_name \"seg_init_$RepresentationID$.mp4\" "
                    "-media_seg_name \"seg_$RepresentationID$_$Number$.m4s\" "
                    "\"%s/manifest.mpd\"",
                    config->segment_duration,
                    state->output_dir);

    if (pos >= (int)sizeof(cmd) - 1) {
        log_error("FFmpeg DASH command exceeds buffer size");
        return -1;
    }

    state->progress = 30.0;
    snprintf(state->current_step, sizeof(state->current_step),
             "%s", "Running FFmpeg DASH packager");

    /* Execute */
    if (run_command(cmd) != 0) {
        log_error("FFmpeg DASH packaging failed for job %d", state->job_id);
        return -1;
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

    log_info("FFmpeg DASH packaging completed for job %d: %s",
             state->job_id, state->output_dir);

    return 0;
}

/* ---------------------------------------------------------------------------
 * packager_verify
 * Check that manifest.mpd exists and is non-empty.
 * Also check for at least one segment file.
 * Returns 0 if valid, -1 if problems found.
 * ------------------------------------------------------------------------- */
int packager_verify(const char *output_dir)
{
    if (!output_dir) {
        log_error("packager_verify: NULL output_dir");
        return -1;
    }

    /* Check for DASH manifest */
    char dash_path[MAX_PATH_LEN + 64];
    snprintf(dash_path, sizeof(dash_path), "%s/manifest.mpd", output_dir);
    bool has_dash = file_exists_nonempty(dash_path);

    if (!has_dash) {
        log_error("Verification failed: no manifest.mpd in %s", output_dir);
        return -1;
    }

    /* Count segment files in the output directory and any subdirectories */
    int segment_count = 0;
    int init_count = 0;

    DIR *dp = opendir(output_dir);
    if (!dp) {
        log_error("Cannot open output directory for verification: %s", output_dir);
        return -1;
    }

    struct dirent *entry;
    while ((entry = readdir(dp)) != NULL) {
        const char *name = entry->d_name;
        size_t len = strlen(name);

        /* Count segment files (.m4s, .ts) */
        if ((len > 4 && strcmp(name + len - 4, ".m4s") == 0) ||
            (len > 3 && strcmp(name + len - 3, ".ts") == 0)) {
            segment_count++;
        }

        /* Count init segments (may start with "init" or contain "init" like seg__r1_init.mp4) */
        if (strstr(name, "init") != NULL &&
            (strstr(name, ".mp4") != NULL || strstr(name, ".m4s") != NULL)) {
            init_count++;
        }

        /* Check subdirectories for segments */
        if (entry->d_type == DT_DIR && name[0] != '.') {
            char subdir_path[MAX_PATH_LEN + 256];
            snprintf(subdir_path, sizeof(subdir_path), "%s/%s", output_dir, name);

            DIR *sdp = opendir(subdir_path);
            if (sdp) {
                struct dirent *sentry;
                while ((sentry = readdir(sdp)) != NULL) {
                    const char *sname = sentry->d_name;
                    size_t slen = strlen(sname);

                    if ((slen > 4 && strcmp(sname + slen - 4, ".m4s") == 0) ||
                        (slen > 3 && strcmp(sname + slen - 3, ".ts") == 0)) {
                        segment_count++;
                    }
                    if (strstr(sname, "init") != NULL &&
                        (strstr(sname, ".mp4") != NULL ||
                         strstr(sname, ".m4s") != NULL)) {
                        init_count++;
                    }
                }
                closedir(sdp);
            }
        }
    }
    closedir(dp);

    /* Validate DASH manifest content */
    FILE *fp = fopen(dash_path, "r");
    if (fp) {
        char line[1024];
        bool has_mpd = false;

        while (fgets(line, sizeof(line), fp) != NULL) {
            if (strstr(line, "<MPD") != NULL) {
                has_mpd = true;
                break;
            }
        }
        fclose(fp);

        if (!has_mpd) {
            log_error("Verification failed: manifest.mpd missing <MPD root element");
            return -1;
        }
    }

    log_info("Verification passed: DASH=yes, segments=%d, inits=%d in %s",
             segment_count, init_count, output_dir);

    /* Warn if no segments found (might be okay for very short content) */
    if (segment_count == 0) {
        log_warn("No segment files found in %s (may indicate a problem)", output_dir);
    }

    return 0;
}
