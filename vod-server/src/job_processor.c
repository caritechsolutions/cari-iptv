/*
 * job_processor.c - Core job processing engine for VOD transcoding pipeline
 *
 * Manages a pool of concurrent transcode jobs, polling the database for
 * pending work, monitoring FFmpeg progress, and orchestrating the full
 * pipeline: transcode -> package -> thumbnails -> storage -> register.
 */

#include "job_processor.h"
#include "transcoder.h"
#include "packager.h"
#include "thumbnails.h"
#include "storage.h"
#include "database.h"
#include "logger.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <pthread.h>
#include <unistd.h>
#include <signal.h>
#include <time.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <errno.h>

/* Maximum concurrent jobs we can track */
#define MAX_ACTIVE_JOBS 16

/* Active job slot */
typedef struct {
    int                 in_use;
    transcode_state_t   state;
    media_info_t        media_info;
    char                content_id[256];
    char                title[512];
    char                profile_name[MAX_PROFILE_NAME];
    char                callback_url[1024];
} active_job_t;

/* Module state */
static const vod_config_t  *g_config       = NULL;
static volatile int         g_running      = 0;
static pthread_t            g_thread;
static active_job_t         g_jobs[MAX_ACTIVE_JOBS];
static int                  g_active_count = 0;

/* Forward declarations */
static void *processor_thread(void *arg);
static void  check_active_jobs(void);
static void  pick_up_new_jobs(void);
static void  handle_job_completed(active_job_t *job);
static void  handle_job_failed(active_job_t *job, const char *error_msg);
static void  remove_active_job(int slot);
static int   find_free_slot(void);
static void  interruptible_sleep(int seconds);

/* ------------------------------------------------------------------ */
/*  Public API                                                         */
/* ------------------------------------------------------------------ */

int job_processor_init(const vod_config_t *config)
{
    if (!config) {
        log_error("job_processor_init: NULL config");
        return -1;
    }

    g_config = config;
    g_running = 0;
    g_active_count = 0;

    memset(g_jobs, 0, sizeof(g_jobs));

    log_info("Job processor initialized (max_concurrent=%d, poll_interval=%ds)",
             g_config->max_concurrent_jobs, g_config->poll_interval);
    return 0;
}

int job_processor_start(void)
{
    if (!g_config) {
        log_error("job_processor_start: not initialized");
        return -1;
    }

    if (g_running) {
        log_warn("Job processor already running");
        return 0;
    }

    g_running = 1;

    int rc = pthread_create(&g_thread, NULL, processor_thread, NULL);
    if (rc != 0) {
        log_error("Failed to create processor thread: %s", strerror(rc));
        g_running = 0;
        return -1;
    }

    log_info("Job processor thread started");
    return 0;
}

void job_processor_stop(void)
{
    if (!g_running) {
        return;
    }

    log_info("Stopping job processor...");
    g_running = 0;

    /* Wait for the processor thread to exit */
    pthread_join(g_thread, NULL);

    /* Pause all active FFmpeg processes and mark them as paused in DB */
    sqlite3 *db = db_handle();
    for (int i = 0; i < MAX_ACTIVE_JOBS; i++) {
        if (!g_jobs[i].in_use) continue;

        transcode_state_t *st = &g_jobs[i].state;

        if (st->pid > 0) {
            log_info("Pausing job %d (pid %d) for shutdown", st->job_id, st->pid);
            kill(st->pid, SIGSTOP);
        }

        /* Update DB status to 'paused' */
        if (db) {
            sqlite3_stmt *stmt = NULL;
            int rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET status = 'paused', current_step = 'Paused for server shutdown' "
                "WHERE id = ?",
                -1, &stmt, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_int(stmt, 1, st->job_id);
                sqlite3_step(stmt);
                sqlite3_finalize(stmt);
            }
        }

        g_jobs[i].in_use = 0;
    }

    g_active_count = 0;
    log_info("Job processor stopped");
}

int job_processor_active_count(void)
{
    return g_active_count;
}

int job_processor_pause_all(void)
{
    int paused = 0;

    for (int i = 0; i < MAX_ACTIVE_JOBS; i++) {
        if (!g_jobs[i].in_use) continue;

        transcode_state_t *st = &g_jobs[i].state;
        if (st->pid > 0) {
            log_info("Sending SIGSTOP to job %d (pid %d)", st->job_id, st->pid);
            if (kill(st->pid, SIGSTOP) == 0) {
                paused++;
            } else {
                log_warn("Failed to SIGSTOP pid %d: %s", st->pid, strerror(errno));
            }
        }
    }

    log_info("Paused %d active jobs", paused);
    return paused;
}

int job_processor_resume_all(void)
{
    int resumed = 0;

    for (int i = 0; i < MAX_ACTIVE_JOBS; i++) {
        if (!g_jobs[i].in_use) continue;

        transcode_state_t *st = &g_jobs[i].state;
        if (st->pid > 0) {
            log_info("Sending SIGCONT to job %d (pid %d)", st->job_id, st->pid);
            if (kill(st->pid, SIGCONT) == 0) {
                resumed++;
            } else {
                log_warn("Failed to SIGCONT pid %d: %s", st->pid, strerror(errno));
            }
        }
    }

    log_info("Resumed %d active jobs", resumed);
    return resumed;
}

/* ------------------------------------------------------------------ */
/*  Processor thread                                                   */
/* ------------------------------------------------------------------ */

static void *processor_thread(void *arg)
{
    (void)arg;

    log_info("Processor thread running (poll every %ds)", g_config->poll_interval);

    /* On startup, reset any jobs stuck in 'processing' or 'packaging' state
     * (from a previous unclean shutdown) back to 'pending' so they get retried */
    sqlite3 *db = db_handle();
    if (db) {
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db,
            "UPDATE jobs SET status = 'pending', pid = 0, progress = 0.0, "
            "current_step = 'Queued (recovered after restart)' "
            "WHERE status IN ('processing', 'packaging')",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_step(stmt);
            int changes = sqlite3_changes(db);
            sqlite3_finalize(stmt);
            if (changes > 0) {
                log_info("Reset %d stuck jobs to pending", changes);
            }
        }

        /* Also resume any 'paused' jobs from a previous graceful shutdown */
        stmt = NULL;
        rc = sqlite3_prepare_v2(db,
            "UPDATE jobs SET status = 'pending', pid = 0, progress = 0.0, "
            "current_step = 'Queued (resumed after restart)' "
            "WHERE status = 'paused'",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_step(stmt);
            int changes = sqlite3_changes(db);
            sqlite3_finalize(stmt);
            if (changes > 0) {
                log_info("Re-queued %d paused jobs", changes);
            }
        }
    }

    while (g_running) {
        /* Step 1: Check progress of all active jobs */
        check_active_jobs();

        /* Step 2: Pick up new jobs if we have capacity */
        if (g_active_count < g_config->max_concurrent_jobs) {
            pick_up_new_jobs();
        }

        /* Step 3: Sleep for poll_interval, waking up if g_running goes to 0 */
        interruptible_sleep(g_config->poll_interval);
    }

    log_info("Processor thread exiting");
    return NULL;
}

/* ------------------------------------------------------------------ */
/*  Check progress of active jobs                                      */
/* ------------------------------------------------------------------ */

static void check_active_jobs(void)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    for (int i = 0; i < MAX_ACTIVE_JOBS; i++) {
        if (!g_jobs[i].in_use) continue;

        transcode_state_t *st = &g_jobs[i].state;
        int result = transcoder_check_progress(st);

        if (result == 1) {
            /* Transcode completed successfully */
            log_info("Job %d transcode complete, starting post-processing", st->job_id);
            handle_job_completed(&g_jobs[i]);
            remove_active_job(i);

        } else if (result == -1) {
            /* Transcode failed */
            log_error("Job %d transcode failed", st->job_id);
            handle_job_failed(&g_jobs[i], "Transcoding failed");
            remove_active_job(i);

        } else {
            /* Still running -- update progress in DB */
            sqlite3_stmt *stmt = NULL;
            int rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET progress = ?, current_step = ? WHERE id = ?",
                -1, &stmt, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_double(stmt, 1, st->progress);
                sqlite3_bind_text(stmt, 2, st->current_step, -1, SQLITE_TRANSIENT);
                sqlite3_bind_int(stmt, 3, st->job_id);
                sqlite3_step(stmt);
                sqlite3_finalize(stmt);
            }

            log_debug("Job %d progress: %.1f%% - %s",
                      st->job_id, st->progress, st->current_step);
        }
    }
}

/* ------------------------------------------------------------------ */
/*  Handle a completed transcode (packaging, thumbnails, storage)      */
/* ------------------------------------------------------------------ */

static void handle_job_completed(active_job_t *job)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    transcode_state_t *st = &job->state;
    int job_id = st->job_id;

    /* Update status to 'packaging' */
    {
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db,
            "UPDATE jobs SET status = 'packaging', progress = 100.0, "
            "current_step = 'Packaging for streaming...' WHERE id = ?",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_int(stmt, 1, job_id);
            sqlite3_step(stmt);
            sqlite3_finalize(stmt);
        }
    }

    /* ---- Packaging ---- */
    log_info("Job %d: Packaging output for streaming", job_id);

    package_state_t pkg;
    memset(&pkg, 0, sizeof(pkg));
    pkg.job_id = job_id;
    snprintf(pkg.input_dir, sizeof(pkg.input_dir), "%s", st->output_dir);
    snprintf(pkg.output_dir, sizeof(pkg.output_dir), "%s", st->output_dir);

    int pkg_result = packager_run(&pkg, g_config);
    if (pkg_result != 0) {
        log_warn("Job %d: MP4Box packaging failed, trying FFmpeg fallback", job_id);
        pkg_result = packager_run_ffmpeg_fallback(&pkg, g_config);
    }

    if (pkg_result != 0) {
        handle_job_failed(job, "Packaging failed (both MP4Box and FFmpeg fallback)");
        return;
    }

    log_info("Job %d: Packaging complete", job_id);

    /* ---- Thumbnails ---- */
    int has_thumbnails = 0;
    if (g_config->thumbnails_enabled) {
        log_info("Job %d: Generating thumbnails", job_id);

        /* Update step in DB */
        {
            sqlite3_stmt *stmt = NULL;
            int rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET current_step = 'Generating thumbnails...' WHERE id = ?",
                -1, &stmt, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_int(stmt, 1, job_id);
                sqlite3_step(stmt);
                sqlite3_finalize(stmt);
            }
        }

        int thumb_result = thumbnails_generate(
            st->source_path, st->output_dir,
            job->media_info.duration, g_config
        );

        if (thumb_result == 0) {
            has_thumbnails = 1;
            log_info("Job %d: Thumbnails generated", job_id);
        } else {
            log_warn("Job %d: Thumbnail generation failed (non-fatal)", job_id);
        }
    }

    /* ---- Move output from temp to library ---- */
    log_info("Job %d: Moving output to library", job_id);

    {
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db,
            "UPDATE jobs SET current_step = 'Moving to library...' WHERE id = ?",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_int(stmt, 1, job_id);
            sqlite3_step(stmt);
            sqlite3_finalize(stmt);
        }
    }

    char library_dir[MAX_PATH_LEN];
    if (storage_create_content_dir(job->content_id, library_dir, sizeof(library_dir)) != 0) {
        handle_job_failed(job, "Failed to create content directory in library");
        return;
    }

    if (storage_move_dir(st->output_dir, library_dir) != 0) {
        handle_job_failed(job, "Failed to move output to library");
        return;
    }

    log_info("Job %d: Content moved to %s", job_id, library_dir);

    /* ---- Determine paths for DASH manifest ---- */
    char hls_path[MAX_PATH_LEN];
    char dash_path[MAX_PATH_LEN];
    hls_path[0] = '\0';  /* DASH-only output, no HLS */
    snprintf(dash_path, sizeof(dash_path), "%s/manifest.mpd", job->content_id);

    /* ---- Build renditions JSON ---- */
    const transcode_profile_t *profile = config_get_profile(g_config, job->profile_name);
    char renditions_json[2048] = "[]";
    if (profile && profile->rendition_count > 0) {
        char *p = renditions_json;
        int remaining = (int)sizeof(renditions_json);
        int written = snprintf(p, remaining, "[");
        p += written;
        remaining -= written;

        for (int r = 0; r < profile->rendition_count && remaining > 100; r++) {
            const rendition_t *rend = &profile->renditions[r];
            written = snprintf(p, remaining,
                "%s{\"resolution\":\"%s\",\"width\":%d,\"height\":%d,\"bitrate\":%d}",
                (r > 0) ? "," : "",
                rend->label, rend->width, rend->height, rend->bitrate_kbps);
            p += written;
            remaining -= written;
        }
        snprintf(p, remaining, "]");
    }

    /* ---- Calculate total output size ---- */
    uint64_t total_size = storage_dir_size(library_dir);

    /* ---- Register content in database ---- */
    log_info("Job %d: Registering content in database", job_id);

    {
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db,
            "INSERT OR REPLACE INTO content "
            "(content_id, title, path, source_file, codec, renditions, "
            " duration, size_bytes, has_thumbnails, has_subtitles, "
            " hls_path, dash_path, status, created_at, updated_at) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'ready', "
            " CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_text(stmt, 1, job->content_id, -1, SQLITE_TRANSIENT);
            sqlite3_bind_text(stmt, 2, job->title, -1, SQLITE_TRANSIENT);
            sqlite3_bind_text(stmt, 3, job->content_id, -1, SQLITE_TRANSIENT);
            sqlite3_bind_text(stmt, 4, st->source_path, -1, SQLITE_TRANSIENT);
            sqlite3_bind_text(stmt, 5, profile ? profile->codec : "libx264", -1, SQLITE_TRANSIENT);
            sqlite3_bind_text(stmt, 6, renditions_json, -1, SQLITE_TRANSIENT);
            sqlite3_bind_double(stmt, 7, job->media_info.duration);
            sqlite3_bind_int64(stmt, 8, (sqlite3_int64)total_size);
            sqlite3_bind_int(stmt, 9, has_thumbnails);
            sqlite3_bind_text(stmt, 10, hls_path, -1, SQLITE_TRANSIENT);
            sqlite3_bind_text(stmt, 11, dash_path, -1, SQLITE_TRANSIENT);
            rc = sqlite3_step(stmt);
            sqlite3_finalize(stmt);

            if (rc != SQLITE_DONE) {
                log_error("Job %d: Failed to register content: %s",
                          job_id, sqlite3_errmsg(db));
            }
        } else {
            log_error("Job %d: Failed to prepare content insert: %s",
                      job_id, sqlite3_errmsg(db));
        }
    }

    /* ---- Update job to 'complete' ---- */
    {
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db,
            "UPDATE jobs SET status = 'complete', progress = 100.0, "
            "current_step = 'Complete', completed_at = CURRENT_TIMESTAMP "
            "WHERE id = ?",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_int(stmt, 1, job_id);
            sqlite3_step(stmt);
            sqlite3_finalize(stmt);
        }
    }

    /* ---- Clean up temp directory ---- */
    storage_cleanup_temp(job_id);

    log_info("Job %d: Complete! Content '%s' is ready to serve",
             job_id, job->content_id);
}

/* ------------------------------------------------------------------ */
/*  Handle a failed job                                                */
/* ------------------------------------------------------------------ */

static void handle_job_failed(active_job_t *job, const char *error_msg)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    int job_id = job->state.job_id;

    log_error("Job %d FAILED: %s", job_id, error_msg);

    /* Update DB status to 'failed' */
    {
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db,
            "UPDATE jobs SET status = 'failed', error_msg = ?, "
            "current_step = 'Failed', completed_at = CURRENT_TIMESTAMP "
            "WHERE id = ?",
            -1, &stmt, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_text(stmt, 1, error_msg, -1, SQLITE_TRANSIENT);
            sqlite3_bind_int(stmt, 2, job_id);
            sqlite3_step(stmt);
            sqlite3_finalize(stmt);
        }
    }

    /* Clean up temp directory */
    storage_cleanup_temp(job_id);
}

/* ------------------------------------------------------------------ */
/*  Download an HTTP/HTTPS source to a local file                      */
/* ------------------------------------------------------------------ */

static int download_http_source(const char *url, const char *dest_dir,
                                char *local_path, size_t path_len,
                                int job_id, sqlite3 *db)
{
    /* Extract filename from URL */
    const char *basename = strrchr(url, '/');
    if (!basename || basename[1] == '\0') {
        basename = "source.mp4";
    } else {
        basename++; /* skip the '/' */
    }

    /* Copy filename and strip query parameters */
    char filename[256];
    snprintf(filename, sizeof(filename), "%s", basename);
    char *q = strchr(filename, '?');
    if (q) *q = '\0';

    /* If no file extension, default to .mp4 */
    if (!strchr(filename, '.')) {
        snprintf(filename, sizeof(filename), "source.mp4");
    }

    /* Build local destination path */
    snprintf(local_path, path_len, "%s/%s", dest_dir, filename);

    /* Update job step in DB */
    if (db) {
        sqlite3_stmt *upd = NULL;
        int rc = sqlite3_prepare_v2(db,
            "UPDATE jobs SET current_step = 'Downloading source file...' WHERE id = ?",
            -1, &upd, NULL);
        if (rc == SQLITE_OK) {
            sqlite3_bind_int(upd, 1, job_id);
            sqlite3_step(upd);
            sqlite3_finalize(upd);
        }
    }

    /* Build curl command: follow redirects, fail on HTTP errors, 1 hour timeout */
    char cmd[MAX_PATH_LEN * 3];
    snprintf(cmd, sizeof(cmd),
             "curl -sS -f -L --max-time 3600 -o '%s' '%s' 2>&1",
             local_path, url);

    log_info("Job %d: Downloading source from %s", job_id, url);

    FILE *fp = popen(cmd, "r");
    if (!fp) {
        log_error("Job %d: Failed to start curl: %s", job_id, strerror(errno));
        return -1;
    }

    /* Capture any error output from curl */
    char output[1024] = {0};
    size_t total = 0;
    char buf[256];
    while (fgets(buf, sizeof(buf), fp)) {
        size_t len = strlen(buf);
        if (total + len < sizeof(output) - 1) {
            memcpy(output + total, buf, len);
            total += len;
        }
    }
    output[total] = '\0';

    int ret = pclose(fp);
    if (ret != 0) {
        int exit_code = WIFEXITED(ret) ? WEXITSTATUS(ret) : -1;
        log_error("Job %d: curl download failed (exit %d): %s",
                  job_id, exit_code, output);
        return -1;
    }

    /* Verify the downloaded file exists and is non-empty */
    struct stat st;
    if (stat(local_path, &st) != 0 || st.st_size == 0) {
        log_error("Job %d: Downloaded file is missing or empty: %s",
                  job_id, local_path);
        return -1;
    }

    log_info("Job %d: Downloaded %lld bytes to %s",
             job_id, (long long)st.st_size, local_path);
    return 0;
}

/* Check if a path is an HTTP/HTTPS URL */
static int is_http_url(const char *path)
{
    return (strncmp(path, "http://", 7) == 0 ||
            strncmp(path, "https://", 8) == 0);
}

/* ------------------------------------------------------------------ */
/*  Pick up new pending jobs from the database                         */
/* ------------------------------------------------------------------ */

static void pick_up_new_jobs(void)
{
    sqlite3 *db = db_handle();
    if (!db) return;

    /* Only pick up one job per poll cycle to avoid overwhelming the system */
    int max_to_pick = g_config->max_concurrent_jobs - g_active_count;
    if (max_to_pick <= 0) return;

    /* Find the next pending job, ordered by priority (1=highest) then creation time */
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT id, content_id, title, source_path, profile, callback_url, source_type "
        "FROM jobs WHERE status = 'pending' "
        "ORDER BY priority ASC, created_at ASC LIMIT 1",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        log_error("Failed to query pending jobs: %s", sqlite3_errmsg(db));
        return;
    }

    while (sqlite3_step(stmt) == SQLITE_ROW && max_to_pick > 0) {
        int job_id              = sqlite3_column_int(stmt, 0);
        const char *cid         = (const char *)sqlite3_column_text(stmt, 1);
        const char *title       = (const char *)sqlite3_column_text(stmt, 2);
        const char *source      = (const char *)sqlite3_column_text(stmt, 3);
        const char *profile     = (const char *)sqlite3_column_text(stmt, 4);
        const char *cb_url      = (const char *)sqlite3_column_text(stmt, 5);
        const char *source_type = (const char *)sqlite3_column_text(stmt, 6);

        if (!cid || !source) {
            log_error("Job %d: missing content_id or source_path, skipping", job_id);
            continue;
        }

        log_info("Job %d: Picked up for processing (content_id=%s, profile=%s)",
                 job_id, cid, profile ? profile : "default");

        /* Check storage space */
        if (!storage_has_space()) {
            log_warn("Job %d: Insufficient disk space, deferring", job_id);

            /* Mark it so we don't spam the log every poll cycle */
            sqlite3_stmt *upd = NULL;
            rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET current_step = 'Waiting for disk space' WHERE id = ?",
                -1, &upd, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_int(upd, 1, job_id);
                sqlite3_step(upd);
                sqlite3_finalize(upd);
            }
            break;
        }

        /* Get or create temp directory */
        char temp_dir[MAX_PATH_LEN];
        if (storage_get_temp_dir(job_id, temp_dir, sizeof(temp_dir)) != 0) {
            log_error("Job %d: Failed to get temp directory", job_id);
            handle_job_failed(&(active_job_t){
                .state = { .job_id = job_id },
                .content_id = "",
            }, "Failed to create temp directory");
            /* Copy content_id for the error handler */
            continue;
        }
        storage_ensure_dir(temp_dir);

        /* If source is an HTTP URL, download it to temp directory first */
        char local_source[MAX_PATH_LEN + 256];
        const char *actual_source = source;

        if ((source_type && strcmp(source_type, "http") == 0) || is_http_url(source)) {
            log_info("Job %d: Source is HTTP, downloading first", job_id);

            if (download_http_source(source, temp_dir, local_source,
                                     sizeof(local_source), job_id, db) != 0) {
                log_error("Job %d: Failed to download source from %s", job_id, source);

                sqlite3_stmt *fail_stmt = NULL;
                char err_msg[512];
                snprintf(err_msg, sizeof(err_msg),
                         "Failed to download source file from URL: %.400s", source);
                rc = sqlite3_prepare_v2(db,
                    "UPDATE jobs SET status = 'failed', "
                    "error_msg = ?, "
                    "current_step = 'Failed', completed_at = CURRENT_TIMESTAMP "
                    "WHERE id = ?",
                    -1, &fail_stmt, NULL);
                if (rc == SQLITE_OK) {
                    sqlite3_bind_text(fail_stmt, 1, err_msg, -1, SQLITE_TRANSIENT);
                    sqlite3_bind_int(fail_stmt, 2, job_id);
                    sqlite3_step(fail_stmt);
                    sqlite3_finalize(fail_stmt);
                }
                storage_cleanup_temp(job_id);
                continue;
            }

            actual_source = local_source;
            log_info("Job %d: Using downloaded source: %s", job_id, actual_source);
        }

        /* Probe source file */
        media_info_t info;
        memset(&info, 0, sizeof(info));

        if (transcoder_probe(actual_source, &info) != 0) {
            log_error("Job %d: Failed to probe source file: %s", job_id, actual_source);

            /* Mark job as failed in DB */
            sqlite3_stmt *fail_stmt = NULL;
            rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET status = 'failed', "
                "error_msg = 'Failed to probe source file - file may be missing or corrupted', "
                "current_step = 'Failed', completed_at = CURRENT_TIMESTAMP "
                "WHERE id = ?",
                -1, &fail_stmt, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_int(fail_stmt, 1, job_id);
                sqlite3_step(fail_stmt);
                sqlite3_finalize(fail_stmt);
            }
            storage_cleanup_temp(job_id);
            continue;
        }

        log_info("Job %d: Source probed - duration=%.1fs, %dx%d, codec=%s",
                 job_id, info.duration, info.width, info.height, info.video_codec);

        /* Look up the transcode profile */
        const char *profile_name = (profile && profile[0]) ? profile : g_config->default_profile;
        const transcode_profile_t *prof = config_get_profile(g_config, profile_name);
        if (!prof) {
            log_warn("Job %d: Profile '%s' not found, trying default '%s'",
                     job_id, profile_name, g_config->default_profile);
            prof = config_get_profile(g_config, g_config->default_profile);
        }
        if (!prof) {
            log_error("Job %d: No valid transcode profile found", job_id);

            sqlite3_stmt *fail_stmt = NULL;
            rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET status = 'failed', "
                "error_msg = 'No valid transcode profile configured', "
                "current_step = 'Failed', completed_at = CURRENT_TIMESTAMP "
                "WHERE id = ?",
                -1, &fail_stmt, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_int(fail_stmt, 1, job_id);
                sqlite3_step(fail_stmt);
                sqlite3_finalize(fail_stmt);
            }
            storage_cleanup_temp(job_id);
            continue;
        }

        /* Find a free slot */
        int slot = find_free_slot();
        if (slot < 0) {
            log_warn("Job %d: No free slots available (should not happen)", job_id);
            break;
        }

        /* Set up the active job */
        active_job_t *aj = &g_jobs[slot];
        memset(aj, 0, sizeof(*aj));
        aj->in_use = 1;
        aj->media_info = info;

        snprintf(aj->content_id, sizeof(aj->content_id), "%s", cid);
        snprintf(aj->title, sizeof(aj->title), "%s", title ? title : cid);
        snprintf(aj->profile_name, sizeof(aj->profile_name), "%s", prof->name);
        if (cb_url) {
            snprintf(aj->callback_url, sizeof(aj->callback_url), "%s", cb_url);
        }

        /* Set up transcode state */
        transcode_state_t *ts = &aj->state;
        ts->job_id = job_id;
        ts->pid = 0;
        ts->progress = 0.0;
        ts->duration = info.duration;
        ts->cancelled = false;
        snprintf(ts->source_path, sizeof(ts->source_path), "%s", actual_source);
        snprintf(ts->output_dir, sizeof(ts->output_dir), "%s", temp_dir);
        snprintf(ts->profile_name, sizeof(ts->profile_name), "%s", prof->name);
        snprintf(ts->current_step, sizeof(ts->current_step), "Starting transcode...");

        /* Start the transcode */
        if (transcoder_start(ts, prof, g_config) != 0) {
            log_error("Job %d: Failed to start transcoder", job_id);

            sqlite3_stmt *fail_stmt = NULL;
            rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET status = 'failed', "
                "error_msg = 'Failed to start FFmpeg process', "
                "current_step = 'Failed', completed_at = CURRENT_TIMESTAMP "
                "WHERE id = ?",
                -1, &fail_stmt, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_int(fail_stmt, 1, job_id);
                sqlite3_step(fail_stmt);
                sqlite3_finalize(fail_stmt);
            }
            storage_cleanup_temp(job_id);
            aj->in_use = 0;
            continue;
        }

        g_active_count++;
        max_to_pick--;

        /* Update DB: status='processing', started_at=now, pid */
        {
            sqlite3_stmt *upd = NULL;
            rc = sqlite3_prepare_v2(db,
                "UPDATE jobs SET status = 'processing', "
                "started_at = CURRENT_TIMESTAMP, "
                "pid = ?, "
                "current_step = 'Transcoding started', "
                "bytes_total = ? "
                "WHERE id = ?",
                -1, &upd, NULL);
            if (rc == SQLITE_OK) {
                sqlite3_bind_int(upd, 1, ts->pid);
                sqlite3_bind_int64(upd, 2, info.file_size);
                sqlite3_bind_int(upd, 3, job_id);
                sqlite3_step(upd);
                sqlite3_finalize(upd);
            }
        }

        log_info("Job %d: Transcoding started (pid=%d, profile=%s, duration=%.1fs)",
                 job_id, ts->pid, prof->name, info.duration);
    }

    sqlite3_finalize(stmt);
}

/* ------------------------------------------------------------------ */
/*  Helper: find a free slot in the active jobs array                  */
/* ------------------------------------------------------------------ */

static int find_free_slot(void)
{
    for (int i = 0; i < MAX_ACTIVE_JOBS; i++) {
        if (!g_jobs[i].in_use) {
            return i;
        }
    }
    return -1;
}

/* ------------------------------------------------------------------ */
/*  Helper: remove an active job from the array                        */
/* ------------------------------------------------------------------ */

static void remove_active_job(int slot)
{
    if (slot >= 0 && slot < MAX_ACTIVE_JOBS && g_jobs[slot].in_use) {
        g_jobs[slot].in_use = 0;
        g_active_count--;
        if (g_active_count < 0) {
            g_active_count = 0;
        }
    }
}

/* ------------------------------------------------------------------ */
/*  Helper: sleep that checks g_running each second                    */
/* ------------------------------------------------------------------ */

static void interruptible_sleep(int seconds)
{
    for (int i = 0; i < seconds && g_running; i++) {
        sleep(1);
    }
}
