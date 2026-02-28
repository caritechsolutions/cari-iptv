#ifndef VOD_TRANSCODER_H
#define VOD_TRANSCODER_H

#include "config.h"
#include <stdbool.h>
#include <sys/types.h>

/* Transcode job state */
typedef struct {
    int         job_id;
    pid_t       pid;            /* FFmpeg process ID */
    char        source_path[MAX_PATH_LEN];
    char        output_dir[MAX_PATH_LEN];
    char        profile_name[64];
    double      progress;       /* 0.0 to 100.0 */
    double      duration;       /* Source duration in seconds */
    char        current_step[256];
    bool        cancelled;
} transcode_state_t;

/* Maximum subtitle tracks we track */
#define MAX_SUBTITLE_TRACKS 32

/* Subtitle track info from ffprobe */
typedef struct {
    int         stream_index;       /* FFmpeg stream index */
    char        language[16];       /* ISO 639 code (en, es, fr) */
    char        language_name[64];  /* Full name if available */
    char        codec_name[32];     /* subrip, ass, webvtt, mov_text, etc. */
    bool        is_text_based;      /* true for SRT/ASS/VTT, false for bitmap subs */
    bool        is_forced;          /* Forced/SDH subtitle */
} subtitle_track_info_t;

/* Media info from ffprobe */
typedef struct {
    double      duration;       /* Seconds */
    int         width;
    int         height;
    double      fps;
    char        video_codec[64];
    char        audio_codec[64];
    int         audio_channels;
    int         audio_sample_rate;
    int64_t     file_size;      /* Bytes */
    int64_t     bitrate;        /* bps */
    bool        has_video;
    bool        has_audio;
    bool        has_subtitles;
    int         subtitle_count;
    subtitle_track_info_t subtitle_tracks[MAX_SUBTITLE_TRACKS];
} media_info_t;

/**
 * Initialize the transcoder module.
 */
int transcoder_init(const vod_config_t *config);

/**
 * Probe a media file and return info.
 * Returns 0 on success, -1 on error.
 */
int transcoder_probe(const char *source_path, media_info_t *info);

/**
 * Start a transcode job. Returns immediately.
 * The transcoding runs in a child process.
 * Returns 0 on success, -1 on error.
 */
int transcoder_start(transcode_state_t *state, const transcode_profile_t *profile,
                     const vod_config_t *config);

/**
 * Check the progress of a running transcode.
 * Updates state->progress and state->current_step.
 * Returns 0 if still running, 1 if complete, -1 if failed.
 */
int transcoder_check_progress(transcode_state_t *state);

/**
 * Cancel a running transcode.
 */
int transcoder_cancel(transcode_state_t *state);

/**
 * Build the FFmpeg command line for a transcode job.
 * Writes the command to out_cmd.
 */
int transcoder_build_command(char *out_cmd, size_t cmd_len,
                             const char *source_path, const char *output_dir,
                             const transcode_profile_t *profile,
                             const media_info_t *info,
                             const vod_config_t *config);

/**
 * Extract subtitles from source to WebVTT format.
 * Returns count of extracted subtitle tracks, or -1 on error.
 */
int transcoder_extract_subtitles(const char *source_path, const char *output_dir);

#endif /* VOD_TRANSCODER_H */
