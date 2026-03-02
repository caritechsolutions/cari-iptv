#include "transcoder.h"
#include "logger.h"
#include "cjson/cJSON.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <errno.h>
#include <signal.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <sys/stat.h>
#include <fcntl.h>

/* Static config pointer */
static const vod_config_t *g_config = NULL;

/* ---------------------------------------------------------------------------
 * transcoder_init
 * Verify ffmpeg and ffprobe binaries exist and are executable.
 * ------------------------------------------------------------------------- */
int transcoder_init(const vod_config_t *config)
{
    if (!config) {
        log_error("transcoder_init: NULL config");
        return -1;
    }

    g_config = config;

    /* Check ffmpeg binary */
    if (access(config->ffmpeg_path, X_OK) != 0) {
        log_error("FFmpeg binary not found or not executable: %s (%s)",
                  config->ffmpeg_path, strerror(errno));
        return -1;
    }

    /* Check ffprobe binary */
    if (access(config->ffprobe_path, X_OK) != 0) {
        log_error("FFprobe binary not found or not executable: %s (%s)",
                  config->ffprobe_path, strerror(errno));
        return -1;
    }

    log_info("Transcoder initialized: ffmpeg=%s ffprobe=%s hwaccel=%s",
             config->ffmpeg_path, config->ffprobe_path, config->hwaccel);

    return 0;
}

/* ---------------------------------------------------------------------------
 * transcoder_probe
 * Run ffprobe with JSON output and parse media info using cJSON.
 * ------------------------------------------------------------------------- */
int transcoder_probe(const char *source_path, media_info_t *info)
{
    if (!g_config || !source_path || !info) {
        log_error("transcoder_probe: invalid arguments");
        return -1;
    }

    memset(info, 0, sizeof(*info));

    /* Check that source file exists (skip for HTTP/HTTPS URLs - ffprobe handles those) */
    if (strncmp(source_path, "http://", 7) != 0 &&
        strncmp(source_path, "https://", 8) != 0) {
        if (access(source_path, R_OK) != 0) {
            log_error("Source file not readable: %s (%s)", source_path, strerror(errno));
            return -1;
        }
    }

    /* Build ffprobe command — capture stderr alongside JSON stdout */
    char cmd[MAX_PATH_LEN * 2 + 256];
    snprintf(cmd, sizeof(cmd),
             "%s -v error -print_format json -show_format -show_streams \"%s\" 2>&1",
             g_config->ffprobe_path, source_path);

    log_debug("Running: %s", cmd);

    FILE *fp = popen(cmd, "r");
    if (!fp) {
        log_error("Failed to run ffprobe: %s", strerror(errno));
        return -1;
    }

    /* Read all output into a buffer */
    char *json_buf = NULL;
    size_t json_len = 0;
    size_t json_cap = 0;
    char read_buf[4096];
    size_t n;

    while ((n = fread(read_buf, 1, sizeof(read_buf), fp)) > 0) {
        if (json_len + n >= json_cap) {
            json_cap = (json_cap == 0) ? 8192 : json_cap * 2;
            char *tmp = realloc(json_buf, json_cap);
            if (!tmp) {
                log_error("Out of memory reading ffprobe output");
                free(json_buf);
                pclose(fp);
                return -1;
            }
            json_buf = tmp;
        }
        memcpy(json_buf + json_len, read_buf, n);
        json_len += n;
    }

    int ret = pclose(fp);
    int exit_code;

    if (ret == -1) {
        /* pclose() itself failed — e.g. waitpid() got ECHILD because
         * SIGCHLD was SIG_IGN, or interrupted by a signal.  If we got
         * output that looks like JSON, continue anyway. */
        log_warn("pclose() returned -1 (errno=%d: %s) for ffprobe on: %s",
                 errno, strerror(errno), source_path);
        exit_code = (json_buf && json_len > 0 && json_buf[0] == '{') ? 0 : -1;
    } else {
        exit_code = WIFEXITED(ret) ? WEXITSTATUS(ret) : -1;
    }

    if (exit_code != 0) {
        /* Null-terminate whatever we captured (may include stderr error messages) */
        if (json_buf && json_len > 0) {
            char *t = realloc(json_buf, json_len + 1);
            if (t) { json_buf = t; json_buf[json_len] = '\0'; }
            /* Truncate to first 500 chars for log readability */
            if (json_len > 500) json_buf[500] = '\0';
            log_error("ffprobe exited with code %d for: %s\n  Output: %s",
                      exit_code, source_path, json_buf);
        } else {
            log_error("ffprobe exited with code %d (no output) for: %s",
                      exit_code, source_path);
        }
        free(json_buf);
        return -1;
    }

    if (!json_buf || json_len == 0) {
        log_error("ffprobe returned no output for: %s", source_path);
        free(json_buf);
        return -1;
    }

    /* Null-terminate the buffer */
    char *tmp = realloc(json_buf, json_len + 1);
    if (!tmp) {
        free(json_buf);
        return -1;
    }
    json_buf = tmp;
    json_buf[json_len] = '\0';

    /* Parse JSON */
    cJSON *root = cJSON_Parse(json_buf);
    free(json_buf);

    if (!root) {
        log_error("Failed to parse ffprobe JSON output for: %s", source_path);
        return -1;
    }

    /* Parse format section */
    cJSON *format = cJSON_GetObjectItemCaseSensitive(root, "format");
    if (format) {
        cJSON *duration = cJSON_GetObjectItemCaseSensitive(format, "duration");
        if (duration && cJSON_IsString(duration)) {
            info->duration = atof(duration->valuestring);
        }

        cJSON *size = cJSON_GetObjectItemCaseSensitive(format, "size");
        if (size && cJSON_IsString(size)) {
            info->file_size = strtoll(size->valuestring, NULL, 10);
        }

        cJSON *bit_rate = cJSON_GetObjectItemCaseSensitive(format, "bit_rate");
        if (bit_rate && cJSON_IsString(bit_rate)) {
            info->bitrate = strtoll(bit_rate->valuestring, NULL, 10);
        }
    }

    /* Parse streams */
    cJSON *streams = cJSON_GetObjectItemCaseSensitive(root, "streams");
    if (streams && cJSON_IsArray(streams)) {
        int stream_count = cJSON_GetArraySize(streams);
        info->subtitle_count = 0;

        for (int i = 0; i < stream_count; i++) {
            cJSON *stream = cJSON_GetArrayItem(streams, i);
            if (!stream) continue;

            cJSON *codec_type = cJSON_GetObjectItemCaseSensitive(stream, "codec_type");
            if (!codec_type || !cJSON_IsString(codec_type)) continue;

            if (strcmp(codec_type->valuestring, "video") == 0 && !info->has_video) {
                /* Skip attached pictures (e.g. cover art in MKV files) */
                cJSON *disposition = cJSON_GetObjectItemCaseSensitive(stream, "disposition");
                if (disposition) {
                    cJSON *attached_pic = cJSON_GetObjectItemCaseSensitive(disposition, "attached_pic");
                    if (attached_pic && cJSON_IsNumber(attached_pic) && attached_pic->valueint == 1) {
                        continue;
                    }
                }
                info->has_video = true;

                cJSON *codec_name = cJSON_GetObjectItemCaseSensitive(stream, "codec_name");
                if (codec_name && cJSON_IsString(codec_name)) {
                    snprintf(info->video_codec, sizeof(info->video_codec),
                             "%s", codec_name->valuestring);
                }

                cJSON *width = cJSON_GetObjectItemCaseSensitive(stream, "width");
                if (width && cJSON_IsNumber(width)) {
                    info->width = width->valueint;
                }

                cJSON *height = cJSON_GetObjectItemCaseSensitive(stream, "height");
                if (height && cJSON_IsNumber(height)) {
                    info->height = height->valueint;
                }

                /* Parse frame rate from r_frame_rate (e.g., "24000/1001") */
                cJSON *r_frame_rate = cJSON_GetObjectItemCaseSensitive(stream, "r_frame_rate");
                if (r_frame_rate && cJSON_IsString(r_frame_rate)) {
                    int num = 0, den = 1;
                    if (sscanf(r_frame_rate->valuestring, "%d/%d", &num, &den) == 2 && den > 0) {
                        info->fps = (double)num / (double)den;
                    } else {
                        info->fps = atof(r_frame_rate->valuestring);
                    }
                }

                /* Stream-level duration if not in format */
                if (info->duration <= 0.0) {
                    cJSON *dur = cJSON_GetObjectItemCaseSensitive(stream, "duration");
                    if (dur && cJSON_IsString(dur)) {
                        info->duration = atof(dur->valuestring);
                    }
                }
            } else if (strcmp(codec_type->valuestring, "audio") == 0 && !info->has_audio) {
                info->has_audio = true;

                cJSON *codec_name = cJSON_GetObjectItemCaseSensitive(stream, "codec_name");
                if (codec_name && cJSON_IsString(codec_name)) {
                    snprintf(info->audio_codec, sizeof(info->audio_codec),
                             "%s", codec_name->valuestring);
                }

                cJSON *channels = cJSON_GetObjectItemCaseSensitive(stream, "channels");
                if (channels && cJSON_IsNumber(channels)) {
                    info->audio_channels = channels->valueint;
                }

                cJSON *sample_rate = cJSON_GetObjectItemCaseSensitive(stream, "sample_rate");
                if (sample_rate && cJSON_IsString(sample_rate)) {
                    info->audio_sample_rate = atoi(sample_rate->valuestring);
                }
            } else if (strcmp(codec_type->valuestring, "subtitle") == 0) {
                info->has_subtitles = true;
                if (info->subtitle_count < MAX_SUBTITLE_TRACKS) {
                    subtitle_track_info_t *st = &info->subtitle_tracks[info->subtitle_count];
                    st->stream_index = i;

                    /* Codec name */
                    cJSON *codec_name = cJSON_GetObjectItemCaseSensitive(stream, "codec_name");
                    if (codec_name && cJSON_IsString(codec_name)) {
                        snprintf(st->codec_name, sizeof(st->codec_name), "%s", codec_name->valuestring);
                        /* Determine if text-based (extractable to VTT) */
                        st->is_text_based = (
                            strcmp(codec_name->valuestring, "subrip") == 0 ||
                            strcmp(codec_name->valuestring, "srt") == 0 ||
                            strcmp(codec_name->valuestring, "ass") == 0 ||
                            strcmp(codec_name->valuestring, "ssa") == 0 ||
                            strcmp(codec_name->valuestring, "webvtt") == 0 ||
                            strcmp(codec_name->valuestring, "mov_text") == 0 ||
                            strcmp(codec_name->valuestring, "text") == 0
                        );
                    }

                    /* Language from tags */
                    cJSON *tags = cJSON_GetObjectItemCaseSensitive(stream, "tags");
                    if (tags) {
                        cJSON *lang = cJSON_GetObjectItemCaseSensitive(tags, "language");
                        if (lang && cJSON_IsString(lang)) {
                            snprintf(st->language, sizeof(st->language), "%s", lang->valuestring);
                        } else {
                            snprintf(st->language, sizeof(st->language), "und");
                        }
                        cJSON *title = cJSON_GetObjectItemCaseSensitive(tags, "title");
                        if (title && cJSON_IsString(title)) {
                            snprintf(st->language_name, sizeof(st->language_name), "%s", title->valuestring);
                        }
                    } else {
                        snprintf(st->language, sizeof(st->language), "und");
                    }

                    /* Forced flag from disposition */
                    cJSON *disposition = cJSON_GetObjectItemCaseSensitive(stream, "disposition");
                    if (disposition) {
                        cJSON *forced = cJSON_GetObjectItemCaseSensitive(disposition, "forced");
                        st->is_forced = (forced && cJSON_IsNumber(forced) && forced->valueint == 1);
                    }
                }
                info->subtitle_count++;
            }
        }
    }

    cJSON_Delete(root);

    log_info("Probed '%s': %.1fs, %dx%d, %.2f fps, vcodec=%s, acodec=%s, "
             "subs=%d, size=%lld bytes",
             source_path, info->duration, info->width, info->height, info->fps,
             info->video_codec, info->audio_codec, info->subtitle_count,
             (long long)info->file_size);

    return 0;
}

/* ---------------------------------------------------------------------------
 * transcoder_build_command
 * Build a complete FFmpeg command for multi-rendition transcode.
 * ------------------------------------------------------------------------- */
int transcoder_build_command(char *out_cmd, size_t cmd_len,
                             const char *source_path, const char *output_dir,
                             const transcode_profile_t *profile,
                             const media_info_t *info,
                             const vod_config_t *config)
{
    if (!out_cmd || cmd_len == 0 || !source_path || !output_dir ||
        !profile || !info || !config) {
        log_error("transcoder_build_command: invalid arguments");
        return -1;
    }

    if (profile->rendition_count <= 0) {
        log_error("Profile '%s' has no renditions", profile->name);
        return -1;
    }

    /* We build the command in a large temporary buffer, then copy to out_cmd */
    char cmd[16384];
    int pos = 0;
    int remaining = (int)sizeof(cmd);

    /* --- Hardware acceleration input options --- */
    if (strcmp(config->hwaccel, "nvenc") == 0) {
        pos += snprintf(cmd + pos, remaining - pos,
                        "%s -hwaccel cuda -hwaccel_device %d ",
                        config->ffmpeg_path, config->gpu_device);
    } else if (strcmp(config->hwaccel, "vaapi") == 0) {
        pos += snprintf(cmd + pos, remaining - pos,
                        "%s -hwaccel vaapi -hwaccel_output_format vaapi "
                        "-vaapi_device /dev/dri/renderD%d ",
                        config->ffmpeg_path, 128 + config->gpu_device);
    } else {
        pos += snprintf(cmd + pos, remaining - pos, "%s ", config->ffmpeg_path);
    }

    /* Overwrite output, input file */
    pos += snprintf(cmd + pos, remaining - pos,
                    "-y -i \"%s\" ", source_path);

    /* Progress reporting */
    pos += snprintf(cmd + pos, remaining - pos,
                    "-progress pipe:1 ");

    /* --- Build filter_complex --- */
    int rcount = profile->rendition_count;

    /* Start filter_complex: split the video into N copies */
    pos += snprintf(cmd + pos, remaining - pos, "-filter_complex \"");

    if (strcmp(config->hwaccel, "vaapi") == 0) {
        /* VAAPI: upload to hardware surface, then split and scale in VAAPI */
        pos += snprintf(cmd + pos, remaining - pos,
                        "[0:v]format=nv12|vaapi,hwupload");
        if (rcount > 1) {
            pos += snprintf(cmd + pos, remaining - pos, ",split=%d", rcount);
            for (int i = 0; i < rcount; i++) {
                pos += snprintf(cmd + pos, remaining - pos, "[v%d]", i);
            }
            pos += snprintf(cmd + pos, remaining - pos, "; ");
            for (int i = 0; i < rcount; i++) {
                const rendition_t *r = &profile->renditions[i];
                pos += snprintf(cmd + pos, remaining - pos,
                                "[v%d]scale_vaapi=w=%d:h=%d[out%d]",
                                i, r->width, r->height, i);
                if (i < rcount - 1) {
                    pos += snprintf(cmd + pos, remaining - pos, "; ");
                }
            }
        } else {
            const rendition_t *r = &profile->renditions[0];
            pos += snprintf(cmd + pos, remaining - pos,
                            ",scale_vaapi=w=%d:h=%d[out0]", r->width, r->height);
        }
    } else {
        /* Software or NVENC: use standard split + scale filters */
        pos += snprintf(cmd + pos, remaining - pos, "[0:v]split=%d", rcount);
        for (int i = 0; i < rcount; i++) {
            pos += snprintf(cmd + pos, remaining - pos, "[v%d]", i);
        }
        pos += snprintf(cmd + pos, remaining - pos, "; ");

        for (int i = 0; i < rcount; i++) {
            const rendition_t *r = &profile->renditions[i];
            pos += snprintf(cmd + pos, remaining - pos,
                            "[v%d]scale=%d:%d[out%d]",
                            i, r->width, r->height, i);
            if (i < rcount - 1) {
                pos += snprintf(cmd + pos, remaining - pos, "; ");
            }
        }
    }

    pos += snprintf(cmd + pos, remaining - pos, "\" ");

    /* --- Per-rendition output options --- */
    /* Determine video encoder based on hwaccel and codec settings */
    const char *vcodec = profile->codec;
    if (strcmp(config->hwaccel, "nvenc") == 0) {
        if (strcmp(profile->codec, "libx265") == 0 ||
            strcmp(profile->codec, "hevc") == 0) {
            vcodec = "hevc_nvenc";
        } else {
            vcodec = "h264_nvenc";
        }
    } else if (strcmp(config->hwaccel, "vaapi") == 0) {
        if (strcmp(profile->codec, "libx265") == 0 ||
            strcmp(profile->codec, "hevc") == 0) {
            vcodec = "hevc_vaapi";
        } else {
            vcodec = "h264_vaapi";
        }
    }

    int gop = config->gop_size;

    for (int i = 0; i < rcount; i++) {
        const rendition_t *r = &profile->renditions[i];

        /* Map the filtered output */
        pos += snprintf(cmd + pos, remaining - pos,
                        "-map \"[out%d]\" ", i);

        /* Video codec and encoding settings */
        pos += snprintf(cmd + pos, remaining - pos,
                        "-c:v:%d %s ", i, vcodec);

        /* Bitrate */
        pos += snprintf(cmd + pos, remaining - pos,
                        "-b:v:%d %dk ", i, r->bitrate_kbps);

        /* Maxrate and bufsize for CBR-like behavior */
        pos += snprintf(cmd + pos, remaining - pos,
                        "-maxrate:v:%d %dk -bufsize:v:%d %dk ",
                        i, (int)(r->bitrate_kbps * 1.5),
                        i, r->bitrate_kbps * 2);

        /* Preset and CRF (software encoders) or quality (hardware encoders) */
        if (strcmp(config->hwaccel, "nvenc") == 0) {
            pos += snprintf(cmd + pos, remaining - pos,
                            "-preset:v:%d p4 -rc:v:%d vbr -cq:v:%d %d ",
                            i, i, i, profile->crf);
        } else if (strcmp(config->hwaccel, "vaapi") == 0) {
            pos += snprintf(cmd + pos, remaining - pos,
                            "-rc_mode:v:%d VBR -qp:v:%d %d ",
                            i, i, profile->crf);
        } else {
            pos += snprintf(cmd + pos, remaining - pos,
                            "-preset:v:%d %s -crf:v:%d %d ",
                            i, profile->preset, i, profile->crf);
        }

        /* GOP settings for consistent keyframes */
        pos += snprintf(cmd + pos, remaining - pos,
                        "-g:v:%d %d -keyint_min:v:%d %d -sc_threshold:v:%d 0 ",
                        i, gop, i, gop, i);

        /* Force pixel format for software encode */
        if (strcmp(config->hwaccel, "none") == 0 ||
            config->hwaccel[0] == '\0') {
            pos += snprintf(cmd + pos, remaining - pos,
                            "-pix_fmt:v:%d yuv420p ", i);
        }

        /* Output individual MP4 file per rendition */
        pos += snprintf(cmd + pos, remaining - pos,
                        "-an \"%s/%s.mp4\" ", output_dir, r->label);
    }

    /* --- Audio output as separate AAC file --- */
    if (info->has_audio) {
        const char *acodec = profile->audio_codec[0]
                                 ? profile->audio_codec : "aac";
        const char *abitrate = profile->audio_bitrate[0]
                                   ? profile->audio_bitrate : "128k";

        pos += snprintf(cmd + pos, remaining - pos,
                        "-map 0:a:0 -c:a %s -b:a %s -ac 2 -vn \"%s/audio.m4a\"",
                        acodec, abitrate, output_dir);
    }

    /* Safety check: did we overflow? */
    if (pos >= (int)sizeof(cmd) - 1) {
        log_error("FFmpeg command exceeds internal buffer size");
        return -1;
    }

    /* Copy to caller's buffer */
    if ((size_t)pos >= cmd_len) {
        log_error("FFmpeg command exceeds output buffer (%d >= %zu)", pos, cmd_len);
        return -1;
    }

    memcpy(out_cmd, cmd, pos + 1); /* includes null terminator from snprintf */

    log_debug("Built FFmpeg command (%d bytes): %s", pos, out_cmd);

    return 0;
}

/* ---------------------------------------------------------------------------
 * transcoder_start
 * Fork a child process, exec FFmpeg, store PID.
 * Redirect FFmpeg's stdout (progress) to a file in the temp directory.
 * ------------------------------------------------------------------------- */
int transcoder_start(transcode_state_t *state, const transcode_profile_t *profile,
                     const vod_config_t *config)
{
    if (!state || !profile || !config) {
        log_error("transcoder_start: invalid arguments");
        return -1;
    }

    /* Probe the source to get duration */
    media_info_t info;
    if (transcoder_probe(state->source_path, &info) != 0) {
        log_error("Failed to probe source: %s", state->source_path);
        return -1;
    }
    state->duration = info.duration;

    /* Create the output directory */
    struct stat st;
    if (stat(state->output_dir, &st) != 0) {
        if (mkdir(state->output_dir, 0755) != 0 && errno != EEXIST) {
            log_error("Cannot create output directory: %s (%s)",
                      state->output_dir, strerror(errno));
            return -1;
        }
    }

    /* Build the FFmpeg command */
    char ffmpeg_cmd[16384];
    if (transcoder_build_command(ffmpeg_cmd, sizeof(ffmpeg_cmd),
                                 state->source_path, state->output_dir,
                                 profile, &info, config) != 0) {
        log_error("Failed to build FFmpeg command");
        return -1;
    }

    /* Progress file path: <output_dir>/ffmpeg_progress.log */
    char progress_file[MAX_PATH_LEN + 64];
    snprintf(progress_file, sizeof(progress_file),
             "%s/ffmpeg_progress.log", state->output_dir);

    log_info("Starting transcode job %d: %s -> %s (profile=%s)",
             state->job_id, state->source_path, state->output_dir, profile->name);

    /* Fork */
    pid_t pid = fork();

    if (pid < 0) {
        log_error("fork() failed: %s", strerror(errno));
        return -1;
    }

    if (pid == 0) {
        /* ---- Child process ---- */

        /* Redirect stdout to the progress file (FFmpeg writes -progress pipe:1 here) */
        int fd = open(progress_file, O_WRONLY | O_CREAT | O_TRUNC, 0644);
        if (fd >= 0) {
            dup2(fd, STDOUT_FILENO);
            close(fd);
        }

        /* Redirect stderr to a log file */
        char stderr_file[MAX_PATH_LEN + 64];
        snprintf(stderr_file, sizeof(stderr_file),
                 "%s/ffmpeg_stderr.log", state->output_dir);
        int fd_err = open(stderr_file, O_WRONLY | O_CREAT | O_TRUNC, 0644);
        if (fd_err >= 0) {
            dup2(fd_err, STDERR_FILENO);
            close(fd_err);
        }

        /* Close stdin */
        close(STDIN_FILENO);

        /* Execute FFmpeg via shell to handle the complex command */
        execl("/bin/sh", "sh", "-c", ffmpeg_cmd, (char *)NULL);

        /* If exec fails */
        _exit(127);
    }

    /* ---- Parent process ---- */
    state->pid = pid;
    state->progress = 0.0;
    state->cancelled = false;
    snprintf(state->current_step, sizeof(state->current_step), "%s", "Transcoding");
    snprintf(state->profile_name, sizeof(state->profile_name), "%s", profile->name);

    log_info("FFmpeg started with PID %d for job %d", pid, state->job_id);

    return 0;
}

/* ---------------------------------------------------------------------------
 * transcoder_check_progress
 * Read the FFmpeg progress output file, parse "out_time_us=" to calculate
 * percentage based on known duration.
 * Returns 0 if running, 1 if complete, -1 if failed.
 * ------------------------------------------------------------------------- */
int transcoder_check_progress(transcode_state_t *state)
{
    if (!state) return -1;

    /* Check if the child process is still running */
    if (state->pid > 0) {
        int status = 0;
        pid_t result = waitpid(state->pid, &status, WNOHANG);

        if (result < 0) {
            if (errno == ECHILD) {
                /* Child already reaped, treat as completed */
                log_warn("Job %d: child process %d already reaped",
                         state->job_id, state->pid);
                state->pid = 0;
                state->progress = 100.0;
                snprintf(state->current_step, sizeof(state->current_step),
                         "%s", "Complete");
                return 1;
            }
            log_error("waitpid failed for job %d: %s",
                      state->job_id, strerror(errno));
            return -1;
        }

        if (result > 0) {
            /* Child has exited */
            state->pid = 0;

            if (WIFEXITED(status)) {
                int exit_code = WEXITSTATUS(status);
                if (exit_code == 0) {
                    state->progress = 100.0;
                    snprintf(state->current_step, sizeof(state->current_step),
                             "%s", "Complete");
                    log_info("Job %d: transcode completed successfully",
                             state->job_id);
                    return 1;
                } else {
                    snprintf(state->current_step, sizeof(state->current_step),
                             "Failed (exit code %d)", exit_code);
                    log_error("Job %d: FFmpeg exited with code %d",
                              state->job_id, exit_code);
                    return -1;
                }
            } else if (WIFSIGNALED(status)) {
                int sig = WTERMSIG(status);
                if (state->cancelled) {
                    snprintf(state->current_step, sizeof(state->current_step),
                             "Cancelled");
                    log_info("Job %d: transcode cancelled (signal %d)",
                             state->job_id, sig);
                } else {
                    snprintf(state->current_step, sizeof(state->current_step),
                             "Killed (signal %d)", sig);
                    log_error("Job %d: FFmpeg killed by signal %d",
                              state->job_id, sig);
                }
                return -1;
            }
        }
    } else {
        /* No PID, process is not running */
        return -1;
    }

    /* Process is still running -- parse progress file */
    char progress_file[MAX_PATH_LEN + 64];
    snprintf(progress_file, sizeof(progress_file),
             "%s/ffmpeg_progress.log", state->output_dir);

    FILE *fp = fopen(progress_file, "r");
    if (!fp) {
        /* Progress file may not exist yet at start */
        return 0;
    }

    /* Read the file and find the last "out_time_us=" line */
    int64_t last_out_time_us = 0;
    char line[512];

    while (fgets(line, sizeof(line), fp) != NULL) {
        /* Parse out_time_us=NNNNN */
        if (strncmp(line, "out_time_us=", 12) == 0) {
            int64_t val = strtoll(line + 12, NULL, 10);
            if (val > last_out_time_us) {
                last_out_time_us = val;
            }
        }
        /* Check for "progress=end" which indicates completion */
        if (strncmp(line, "progress=end", 12) == 0) {
            fclose(fp);
            /* Don't mark complete yet -- waitpid will catch the exit */
            state->progress = 99.9;
            snprintf(state->current_step, sizeof(state->current_step),
                     "%s", "Finalizing");
            return 0;
        }
    }
    fclose(fp);

    /* Calculate progress percentage */
    if (state->duration > 0.0 && last_out_time_us > 0) {
        double elapsed_sec = (double)last_out_time_us / 1000000.0;
        double pct = (elapsed_sec / state->duration) * 100.0;

        /* Clamp to reasonable range */
        if (pct < 0.0) pct = 0.0;
        if (pct > 99.9) pct = 99.9;

        state->progress = pct;

        snprintf(state->current_step, sizeof(state->current_step),
                 "Transcoding (%.1f%%)", pct);
    }

    return 0;
}

/* ---------------------------------------------------------------------------
 * transcoder_cancel
 * Send SIGTERM to FFmpeg PID, then waitpid.
 * ------------------------------------------------------------------------- */
int transcoder_cancel(transcode_state_t *state)
{
    if (!state) return -1;

    if (state->pid <= 0) {
        log_warn("Job %d: no process to cancel", state->job_id);
        return 0;
    }

    state->cancelled = true;

    log_info("Cancelling job %d (PID %d)", state->job_id, state->pid);

    /* Send SIGTERM to the process group (kills FFmpeg and any children) */
    if (kill(state->pid, SIGTERM) != 0) {
        if (errno == ESRCH) {
            log_warn("Job %d: process %d already gone", state->job_id, state->pid);
            state->pid = 0;
            return 0;
        }
        log_error("Failed to send SIGTERM to PID %d: %s",
                  state->pid, strerror(errno));
        return -1;
    }

    /* Wait for the child to exit (with timeout via loop) */
    int status = 0;
    int waited = 0;
    pid_t result;

    while (waited < 10) {
        result = waitpid(state->pid, &status, WNOHANG);
        if (result > 0) {
            log_info("Job %d: FFmpeg process %d terminated", state->job_id, state->pid);
            state->pid = 0;
            snprintf(state->current_step, sizeof(state->current_step),
                     "%s", "Cancelled");
            return 0;
        }
        if (result < 0) {
            if (errno == ECHILD) {
                state->pid = 0;
                return 0;
            }
            break;
        }
        /* Still running, wait 500ms */
        usleep(500000);
        waited++;
    }

    /* If still alive after 5 seconds, send SIGKILL */
    if (state->pid > 0) {
        log_warn("Job %d: SIGTERM did not work, sending SIGKILL to PID %d",
                 state->job_id, state->pid);
        kill(state->pid, SIGKILL);
        waitpid(state->pid, &status, 0);
        state->pid = 0;
    }

    snprintf(state->current_step, sizeof(state->current_step), "%s", "Cancelled");
    return 0;
}

/* ---------------------------------------------------------------------------
 * transcoder_extract_subtitles
 * Use FFmpeg to extract subtitle tracks to WebVTT files.
 * Returns count of extracted subtitle tracks, or -1 on error.
 * ------------------------------------------------------------------------- */
int transcoder_extract_subtitles(const char *source_path, const char *output_dir)
{
    if (!g_config || !source_path || !output_dir) {
        log_error("transcoder_extract_subtitles: invalid arguments");
        return -1;
    }

    /* First, probe to find subtitle streams */
    media_info_t info;
    if (transcoder_probe(source_path, &info) != 0) {
        log_error("Failed to probe for subtitles: %s", source_path);
        return -1;
    }

    if (!info.has_subtitles || info.subtitle_count <= 0) {
        log_info("No subtitle tracks found in: %s", source_path);
        return 0;
    }

    log_info("Extracting %d subtitle track(s) from: %s",
             info.subtitle_count, source_path);

    /* We need to identify the subtitle stream indices. Run ffprobe again
     * to get the stream mapping. */
    char cmd[MAX_PATH_LEN * 2 + 256];
    snprintf(cmd, sizeof(cmd),
             "%s -v quiet -print_format json -show_streams -select_streams s \"%s\"",
             g_config->ffprobe_path, source_path);

    FILE *fp = popen(cmd, "r");
    if (!fp) {
        log_error("Failed to run ffprobe for subtitles: %s", strerror(errno));
        return -1;
    }

    char *json_buf = NULL;
    size_t json_len = 0;
    size_t json_cap = 0;
    char read_buf[4096];
    size_t n;

    while ((n = fread(read_buf, 1, sizeof(read_buf), fp)) > 0) {
        if (json_len + n >= json_cap) {
            json_cap = (json_cap == 0) ? 8192 : json_cap * 2;
            char *tmp = realloc(json_buf, json_cap);
            if (!tmp) {
                free(json_buf);
                pclose(fp);
                return -1;
            }
            json_buf = tmp;
        }
        memcpy(json_buf + json_len, read_buf, n);
        json_len += n;
    }
    pclose(fp);

    if (!json_buf || json_len == 0) {
        free(json_buf);
        return 0;
    }

    char *tmp = realloc(json_buf, json_len + 1);
    if (!tmp) {
        free(json_buf);
        return -1;
    }
    json_buf = tmp;
    json_buf[json_len] = '\0';

    cJSON *root = cJSON_Parse(json_buf);
    free(json_buf);

    if (!root) {
        log_error("Failed to parse subtitle stream JSON");
        return -1;
    }

    cJSON *streams = cJSON_GetObjectItemCaseSensitive(root, "streams");
    if (!streams || !cJSON_IsArray(streams)) {
        cJSON_Delete(root);
        return 0;
    }

    int extracted = 0;
    int stream_count = cJSON_GetArraySize(streams);

    for (int i = 0; i < stream_count; i++) {
        cJSON *stream = cJSON_GetArrayItem(streams, i);
        if (!stream) continue;

        /* Get the stream index */
        cJSON *index_item = cJSON_GetObjectItemCaseSensitive(stream, "index");
        if (!index_item || !cJSON_IsNumber(index_item)) continue;
        int stream_index = index_item->valueint;

        /* Try to get language tag */
        const char *lang = "und";
        cJSON *tags = cJSON_GetObjectItemCaseSensitive(stream, "tags");
        if (tags) {
            cJSON *language = cJSON_GetObjectItemCaseSensitive(tags, "language");
            if (language && cJSON_IsString(language) && language->valuestring[0]) {
                lang = language->valuestring;
            }
        }

        /* Build output filename: sub_0_eng.vtt */
        char out_file[MAX_PATH_LEN + 64];
        snprintf(out_file, sizeof(out_file),
                 "%s/sub_%d_%s.vtt", output_dir, i, lang);

        /* Build extraction command */
        char extract_cmd[MAX_PATH_LEN * 3];
        snprintf(extract_cmd, sizeof(extract_cmd),
                 "%s -y -i \"%s\" -map 0:%d -c:s webvtt \"%s\"",
                 g_config->ffmpeg_path, source_path, stream_index, out_file);

        log_debug("Extracting subtitle stream %d (%s): %s", stream_index, lang, extract_cmd);

        int ret = system(extract_cmd);
        if (ret == 0 || (WIFEXITED(ret) && WEXITSTATUS(ret) == 0)) {
            /* Verify the file was created and is non-empty */
            struct stat st;
            if (stat(out_file, &st) == 0 && st.st_size > 0) {
                log_info("Extracted subtitle track %d (%s) -> %s",
                         stream_index, lang, out_file);
                extracted++;
            } else {
                log_warn("Subtitle extraction produced empty file: %s", out_file);
                unlink(out_file);
            }
        } else {
            log_warn("Failed to extract subtitle stream %d (%s) from %s",
                     stream_index, lang, source_path);
        }
    }

    cJSON_Delete(root);

    log_info("Extracted %d/%d subtitle tracks from: %s",
             extracted, info.subtitle_count, source_path);

    return extracted;
}
