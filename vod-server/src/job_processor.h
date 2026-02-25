#ifndef VOD_JOB_PROCESSOR_H
#define VOD_JOB_PROCESSOR_H

#include "config.h"

/**
 * Initialize the job processor.
 * Returns 0 on success, -1 on error.
 */
int job_processor_init(const vod_config_t *config);

/**
 * Start the job processor thread.
 * The processor polls the database for pending jobs
 * and processes them based on max_concurrent_jobs.
 * Returns 0 on success, -1 on error.
 */
int job_processor_start(void);

/**
 * Stop the job processor thread.
 * Waits for active jobs to reach a safe pause point.
 */
void job_processor_stop(void);

/**
 * Get the count of currently running jobs.
 */
int job_processor_active_count(void);

/**
 * Pause all active jobs (for server update).
 * Running FFmpeg processes are sent SIGSTOP.
 */
int job_processor_pause_all(void);

/**
 * Resume all paused jobs.
 * Paused FFmpeg processes are sent SIGCONT.
 */
int job_processor_resume_all(void);

#endif /* VOD_JOB_PROCESSOR_H */
