#ifndef VOD_PACKAGER_H
#define VOD_PACKAGER_H

#include "config.h"
#include <stdbool.h>

/* Packaging state */
typedef struct {
    int     job_id;
    char    input_dir[MAX_PATH_LEN];    /* Dir with encoded files */
    char    output_dir[MAX_PATH_LEN];   /* Final content directory */
    double  progress;           /* 0.0 to 100.0 */
    char    current_step[256];
    bool    cancelled;
} package_state_t;

/**
 * Initialize the packager module.
 */
int packager_init(const vod_config_t *config);

/**
 * Package encoded files into CMAF (HLS + DASH).
 * Uses MP4Box (GPAC) to create:
 *   - master.m3u8 (HLS master playlist)
 *   - manifest.mpd (DASH manifest)
 *   - Shared fMP4 segments
 *
 * input_dir should contain the encoded MP4 files from the transcoder.
 * output_dir is the final content directory in the library.
 *
 * Returns 0 on success, -1 on error.
 */
int packager_run(package_state_t *state, const vod_config_t *config);

/**
 * Fallback: Package using FFmpeg HLS muxer instead of MP4Box.
 * Creates HLS-only output (no DASH).
 * Used when MP4Box is not available.
 */
int packager_run_ffmpeg_fallback(package_state_t *state, const vod_config_t *config);

/**
 * Verify packaged output integrity.
 * Checks that manifests and segments exist and are valid.
 * Returns 0 if valid, -1 if problems found.
 */
int packager_verify(const char *output_dir);

#endif /* VOD_PACKAGER_H */
