/* Required for popen()/pclose() on glibc with strict C11 */
#define _XOPEN_SOURCE 500

#include "thumbnails.h"
#include "logger.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>
#include <sys/stat.h>
#include <errno.h>

/**
 * Recursive mkdir, equivalent to `mkdir -p`.
 * Returns 0 on success, -1 on error.
 */
static int ensure_dir(const char *path)
{
    char tmp[MAX_PATH_LEN];
    char *p = NULL;
    size_t len;

    snprintf(tmp, sizeof(tmp), "%s", path);
    len = strlen(tmp);

    if (len > 0 && tmp[len - 1] == '/')
        tmp[len - 1] = '\0';

    for (p = tmp + 1; *p; p++) {
        if (*p == '/') {
            *p = '\0';
            if (mkdir(tmp, 0755) != 0 && errno != EEXIST)
                return -1;
            *p = '/';
        }
    }

    if (mkdir(tmp, 0755) != 0 && errno != EEXIST)
        return -1;

    return 0;
}

/**
 * Format seconds into HH:MM:SS.mmm timestamp string.
 */
static void format_vtt_time(double seconds, char *buf, size_t buf_len)
{
    int total_ms = (int)(seconds * 1000.0);
    int hours    = total_ms / 3600000;
    int minutes  = (total_ms % 3600000) / 60000;
    int secs     = (total_ms % 60000) / 1000;
    int ms       = total_ms % 1000;

    snprintf(buf, buf_len, "%02d:%02d:%02d.%03d", hours, minutes, secs, ms);
}

int thumbnails_generate(const char *source_path, const char *output_dir,
                        double duration, const vod_config_t *config)
{
    if (!source_path || !output_dir || !config) {
        log_error("thumbnails_generate: invalid parameters");
        return -1;
    }

    if (!config->thumbnails_enabled) {
        log_info("Thumbnail generation is disabled");
        return 0;
    }

    if (duration <= 0.0) {
        log_error("thumbnails_generate: invalid duration %.2f", duration);
        return -1;
    }

    int interval = config->thumb_interval;
    int width    = config->thumb_width;
    int height   = config->thumb_height;
    int columns  = config->thumb_columns;
    int quality  = config->thumb_quality;

    if (interval <= 0) interval = 10;
    if (width <= 0)    width = 160;
    if (height <= 0)   height = 90;
    if (columns <= 0)  columns = 10;
    if (quality <= 0 || quality > 100) quality = 75;

    /* Calculate grid dimensions */
    int thumb_count = (int)ceil(duration / (double)interval);
    if (thumb_count <= 0) thumb_count = 1;

    int rows = (int)ceil((double)thumb_count / (double)columns);

    log_info("Generating thumbnail sprite: %d thumbs (%dx%d grid), %dx%d px each, interval=%ds",
             thumb_count, columns, rows, width, height, interval);

    /* Create thumbnails output directory */
    char thumb_dir[MAX_PATH_LEN];
    snprintf(thumb_dir, sizeof(thumb_dir), "%s/thumbnails", output_dir);

    if (ensure_dir(thumb_dir) != 0) {
        log_error("Failed to create thumbnails directory: %s", thumb_dir);
        return -1;
    }

    /* Build sprite sheet path */
    char sprite_path[MAX_PATH_LEN];
    snprintf(sprite_path, sizeof(sprite_path), "%s/sprite.jpg", thumb_dir);

    /* Build FFmpeg command:
     * fps=1/<interval>  : extract one frame every <interval> seconds
     * scale=<w>:<h>     : resize each frame
     * tile=<cols>x<rows>: arrange into a grid
     * -frames:v 1       : output a single image
     * -q:v <quality>    : JPEG quality (1=best, 31=worst; we map our 1-100 scale)
     */
    /* FFmpeg JPEG quality is 1-31 where 1 is best.
     * Our quality is 1-100 where 100 is best.
     * Map: ffmpeg_q = 31 - (quality * 30 / 100)  => quality 100 -> 1, quality 1 -> 31 */
    int ffmpeg_quality = 31 - (quality * 30 / 100);
    if (ffmpeg_quality < 1)  ffmpeg_quality = 1;
    if (ffmpeg_quality > 31) ffmpeg_quality = 31;

    char cmd[4096];
    snprintf(cmd, sizeof(cmd),
             "%s -y -i \"%s\" "
             "-vf \"fps=1/%d,scale=%d:%d,tile=%dx%d\" "
             "-frames:v 1 -q:v %d "
             "\"%s\" 2>&1",
             config->ffmpeg_path, source_path,
             interval, width, height, columns, rows,
             ffmpeg_quality, sprite_path);

    log_debug("FFmpeg command: %s", cmd);

    /* Execute FFmpeg */
    FILE *proc = popen(cmd, "r");
    if (!proc) {
        log_error("Failed to execute FFmpeg: %s", strerror(errno));
        return -1;
    }

    /* Read and log FFmpeg output */
    char line[1024];
    while (fgets(line, sizeof(line), proc) != NULL) {
        /* Strip trailing newline */
        size_t len = strlen(line);
        if (len > 0 && line[len - 1] == '\n')
            line[len - 1] = '\0';
        if (len > 0)
            log_debug("ffmpeg: %s", line);
    }

    int exit_status = pclose(proc);
    if (exit_status != 0) {
        log_error("FFmpeg exited with status %d", exit_status);
        return -1;
    }

    /* Verify sprite file was created */
    struct stat st;
    if (stat(sprite_path, &st) != 0 || st.st_size == 0) {
        log_error("Sprite sheet was not created or is empty: %s", sprite_path);
        return -1;
    }

    log_info("Sprite sheet created: %s (%.1f KB)", sprite_path,
             (double)st.st_size / 1024.0);

    /* Generate WebVTT file */
    char vtt_path[MAX_PATH_LEN];
    snprintf(vtt_path, sizeof(vtt_path), "%s/thumbnails.vtt", thumb_dir);

    FILE *vtt = fopen(vtt_path, "w");
    if (!vtt) {
        log_error("Failed to create WebVTT file '%s': %s", vtt_path, strerror(errno));
        return -1;
    }

    fprintf(vtt, "WEBVTT\n\n");

    for (int i = 0; i < thumb_count; i++) {
        double start_time = (double)i * (double)interval;
        double end_time   = start_time + (double)interval;

        /* Clamp end time to duration */
        if (end_time > duration)
            end_time = duration;

        /* Calculate x,y position in the sprite grid */
        int col = i % columns;
        int row = i / columns;
        int x = col * width;
        int y = row * height;

        /* Format timestamps */
        char start_str[32];
        char end_str[32];
        format_vtt_time(start_time, start_str, sizeof(start_str));
        format_vtt_time(end_time, end_str, sizeof(end_str));

        fprintf(vtt, "%s --> %s\n", start_str, end_str);
        fprintf(vtt, "sprite.jpg#xywh=%d,%d,%d,%d\n\n", x, y, width, height);
    }

    fclose(vtt);

    log_info("WebVTT file created: %s (%d cues)", vtt_path, thumb_count);

    return 0;
}
