#ifndef VOD_STORAGE_H
#define VOD_STORAGE_H

#include "config.h"
#include <stdbool.h>
#include <stddef.h>
#include <stdint.h>

/* Disk usage info */
typedef struct {
    uint64_t total_bytes;
    uint64_t free_bytes;
    uint64_t used_bytes;
    double   usage_percent;
} disk_info_t;

/* Content directory info */
typedef struct {
    char     content_id[256];
    char     path[MAX_PATH_LEN + 256];
    uint64_t size_bytes;
    int      file_count;
} content_dir_t;

/**
 * Initialize storage manager.
 * Creates library and temp directories if they don't exist.
 * Returns 0 on success, -1 on error.
 */
int storage_init(const vod_config_t *config);

/**
 * Get disk usage information for the library path.
 */
int storage_get_disk_info(disk_info_t *info);

/**
 * Check if there's enough free space for a new job.
 * Returns true if free space > min_free_space_gb.
 */
bool storage_has_space(void);

/**
 * Create the content output directory.
 * Returns the full path in out_path.
 */
int storage_create_content_dir(const char *content_id, char *out_path, size_t out_len);

/**
 * Get the temp directory for a job.
 * Returns the full path in out_path.
 */
int storage_get_temp_dir(int job_id, char *out_path, size_t out_len);

/**
 * Clean up temp directory for a job.
 */
int storage_cleanup_temp(int job_id);

/**
 * Remove all files for a content item.
 */
int storage_remove_content(const char *content_id);

/**
 * Get the total size of a directory recursively.
 */
uint64_t storage_dir_size(const char *path);

/**
 * List all content directories in the library.
 * Caller must free the returned array.
 * Returns count, or -1 on error.
 */
int storage_list_content(content_dir_t **out_dirs);

/**
 * Copy a file from source to destination.
 * Returns 0 on success, -1 on error.
 */
int storage_copy_file(const char *src, const char *dst);

/**
 * Move a file from source to destination.
 * Returns 0 on success, -1 on error.
 */
int storage_move_file(const char *src, const char *dst);

/**
 * Move a directory from source to destination.
 * Returns 0 on success, -1 on error.
 */
int storage_move_dir(const char *src, const char *dst);

/**
 * Ensure a directory exists (recursive mkdir).
 * Returns 0 on success, -1 on error.
 */
int storage_ensure_dir(const char *path);

#endif /* VOD_STORAGE_H */
