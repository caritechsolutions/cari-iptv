#ifndef VOD_LOGGER_H
#define VOD_LOGGER_H

/* Log levels */
typedef enum {
    LOG_DEBUG = 0,
    LOG_INFO  = 1,
    LOG_WARN  = 2,
    LOG_ERROR = 3
} log_level_t;

/**
 * Initialize the logger.
 * If file_path is NULL, logs to stdout only.
 * Returns 0 on success, -1 on error.
 */
int logger_init(const char *file_path, const char *level_str);

/**
 * Close the logger and release resources.
 */
void logger_close(void);

/**
 * Log a message at the given level.
 * Uses printf-style formatting.
 */
void log_msg(log_level_t level, const char *fmt, ...);

/* Convenience macros */
#define log_debug(...) log_msg(LOG_DEBUG, __VA_ARGS__)
#define log_info(...)  log_msg(LOG_INFO,  __VA_ARGS__)
#define log_warn(...)  log_msg(LOG_WARN,  __VA_ARGS__)
#define log_error(...) log_msg(LOG_ERROR, __VA_ARGS__)

#endif /* VOD_LOGGER_H */
