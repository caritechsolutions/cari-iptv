#include "logger.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdarg.h>
#include <time.h>
#include <pthread.h>

static FILE       *g_log_file = NULL;
static log_level_t g_log_level = LOG_INFO;
static pthread_mutex_t g_log_mutex = PTHREAD_MUTEX_INITIALIZER;

static const char *level_names[] = {
    "DEBUG", "INFO", "WARN", "ERROR"
};

static const char *level_colors[] = {
    "\033[36m",   /* DEBUG: cyan */
    "\033[32m",   /* INFO:  green */
    "\033[33m",   /* WARN:  yellow */
    "\033[31m"    /* ERROR: red */
};

static log_level_t parse_level(const char *str)
{
    if (!str) return LOG_INFO;
    if (strcasecmp(str, "debug") == 0) return LOG_DEBUG;
    if (strcasecmp(str, "info")  == 0) return LOG_INFO;
    if (strcasecmp(str, "warn")  == 0) return LOG_WARN;
    if (strcasecmp(str, "error") == 0) return LOG_ERROR;
    return LOG_INFO;
}

int logger_init(const char *file_path, const char *level_str)
{
    g_log_level = parse_level(level_str);

    if (file_path && file_path[0]) {
        g_log_file = fopen(file_path, "a");
        if (!g_log_file) {
            fprintf(stderr, "Failed to open log file: %s\n", file_path);
            return -1;
        }
        /* Line-buffered for real-time tailing */
        setvbuf(g_log_file, NULL, _IOLBF, 0);
    }

    return 0;
}

void logger_close(void)
{
    pthread_mutex_lock(&g_log_mutex);
    if (g_log_file) {
        fclose(g_log_file);
        g_log_file = NULL;
    }
    pthread_mutex_unlock(&g_log_mutex);
}

void log_msg(log_level_t level, const char *fmt, ...)
{
    if (level < g_log_level) return;

    va_list args;
    char    timestamp[32];
    char    message[4096];
    time_t  now;
    struct tm tm_info;

    /* Format the message */
    va_start(args, fmt);
    vsnprintf(message, sizeof(message), fmt, args);
    va_end(args);

    /* Get timestamp */
    time(&now);
    localtime_r(&now, &tm_info);
    strftime(timestamp, sizeof(timestamp), "%Y-%m-%d %H:%M:%S", &tm_info);

    pthread_mutex_lock(&g_log_mutex);

    /* Write to stdout with colors */
    fprintf(stdout, "%s[%s] [%-5s]\033[0m %s\n",
            level_colors[level], timestamp, level_names[level], message);
    fflush(stdout);

    /* Write to file without colors */
    if (g_log_file) {
        fprintf(g_log_file, "[%s] [%-5s] %s\n",
                timestamp, level_names[level], message);
    }

    pthread_mutex_unlock(&g_log_mutex);
}
