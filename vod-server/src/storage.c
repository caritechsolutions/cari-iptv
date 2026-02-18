/* Required for nftw(), FTW_DEPTH, FTW_PHYS, FTW_DP on glibc */
#define _XOPEN_SOURCE 500

#include "storage.h"
#include "logger.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stddef.h>
#include <errno.h>
#include <unistd.h>
#include <fcntl.h>
#include <dirent.h>
#include <sys/stat.h>
#include <sys/statvfs.h>
#include <ftw.h>

/* Copy buffer size: 64 KB */
#define COPY_BUF_SIZE (64 * 1024)

/* Static config pointer */
static const vod_config_t *g_config = NULL;

/* ── Helpers ──────────────────────────────────────────────────────── */

/**
 * Recursive mkdir, equivalent to `mkdir -p`.
 * Returns 0 on success, -1 on error.
 */
static int mkdir_recursive(const char *path, mode_t mode)
{
    char tmp[MAX_PATH_LEN];
    char *p = NULL;
    size_t len;

    snprintf(tmp, sizeof(tmp), "%s", path);
    len = strlen(tmp);

    /* Strip trailing slash */
    if (len > 0 && tmp[len - 1] == '/')
        tmp[len - 1] = '\0';

    for (p = tmp + 1; *p; p++) {
        if (*p == '/') {
            *p = '\0';
            if (mkdir(tmp, mode) != 0 && errno != EEXIST) {
                log_error("mkdir failed for '%s': %s", tmp, strerror(errno));
                return -1;
            }
            *p = '/';
        }
    }

    if (mkdir(tmp, mode) != 0 && errno != EEXIST) {
        log_error("mkdir failed for '%s': %s", tmp, strerror(errno));
        return -1;
    }

    return 0;
}

/* Thread-local accumulator for nftw callbacks */
static __thread uint64_t g_dir_size_acc;

/**
 * nftw callback for summing file sizes.
 */
static int dir_size_cb(const char *fpath, const struct stat *sb,
                       int typeflag, struct FTW *ftwbuf)
{
    (void)fpath;
    (void)ftwbuf;

    if (typeflag == FTW_F)
        g_dir_size_acc += (uint64_t)sb->st_size;

    return 0; /* continue */
}

/**
 * nftw callback for recursive removal (depth-first).
 */
static int remove_cb(const char *fpath, const struct stat *sb,
                     int typeflag, struct FTW *ftwbuf)
{
    (void)sb;
    (void)ftwbuf;

    int rv;

    if (typeflag == FTW_D || typeflag == FTW_DP)
        rv = rmdir(fpath);
    else
        rv = unlink(fpath);

    if (rv != 0)
        log_warn("Failed to remove '%s': %s", fpath, strerror(errno));

    return 0; /* continue even on error */
}

/**
 * Recursively remove a directory tree.
 */
static int remove_recursive(const char *path)
{
    /* FTW_DEPTH: process directory contents before the directory itself
     * FTW_PHYS:  do not follow symlinks
     * 64:        max open file descriptors for the walk */
    return nftw(path, remove_cb, 64, FTW_DEPTH | FTW_PHYS);
}

/**
 * Recursively copy a directory from src to dst.
 * Returns 0 on success, -1 on error.
 */
static int copy_dir_recursive(const char *src, const char *dst);

static int copy_dir_recursive(const char *src, const char *dst)
{
    DIR *dir = opendir(src);
    if (!dir) {
        log_error("Failed to open directory '%s': %s", src, strerror(errno));
        return -1;
    }

    if (mkdir_recursive(dst, 0755) != 0) {
        closedir(dir);
        return -1;
    }

    struct dirent *entry;
    int result = 0;

    while ((entry = readdir(dir)) != NULL) {
        /* Skip . and .. */
        if (strcmp(entry->d_name, ".") == 0 || strcmp(entry->d_name, "..") == 0)
            continue;

        char src_path[MAX_PATH_LEN + 256];
        char dst_path[MAX_PATH_LEN + 256];
        snprintf(src_path, sizeof(src_path), "%s/%s", src, entry->d_name);
        snprintf(dst_path, sizeof(dst_path), "%s/%s", dst, entry->d_name);

        struct stat st;
        if (stat(src_path, &st) != 0) {
            log_error("stat failed for '%s': %s", src_path, strerror(errno));
            result = -1;
            continue;
        }

        if (S_ISDIR(st.st_mode)) {
            if (copy_dir_recursive(src_path, dst_path) != 0)
                result = -1;
        } else {
            if (storage_copy_file(src_path, dst_path) != 0)
                result = -1;
        }
    }

    closedir(dir);
    return result;
}

/* ── Public API ───────────────────────────────────────────────────── */

int storage_init(const vod_config_t *config)
{
    if (!config) {
        log_error("storage_init: NULL config");
        return -1;
    }

    g_config = config;

    log_info("Initializing storage: library=%s temp=%s",
             config->library_path, config->temp_path);

    /* Create library directory */
    if (mkdir_recursive(config->library_path, 0755) != 0) {
        log_error("Failed to create library directory: %s", config->library_path);
        return -1;
    }

    /* Create temp directory */
    if (mkdir_recursive(config->temp_path, 0755) != 0) {
        log_error("Failed to create temp directory: %s", config->temp_path);
        return -1;
    }

    log_info("Storage initialized successfully");
    return 0;
}

int storage_get_disk_info(disk_info_t *info)
{
    if (!g_config || !info) return -1;

    struct statvfs vfs;
    if (statvfs(g_config->library_path, &vfs) != 0) {
        log_error("statvfs failed for '%s': %s",
                  g_config->library_path, strerror(errno));
        return -1;
    }

    info->total_bytes = (uint64_t)vfs.f_blocks * vfs.f_frsize;
    info->free_bytes  = (uint64_t)vfs.f_bavail * vfs.f_frsize;
    info->used_bytes  = info->total_bytes - info->free_bytes;

    if (info->total_bytes > 0)
        info->usage_percent = (double)info->used_bytes / (double)info->total_bytes * 100.0;
    else
        info->usage_percent = 0.0;

    return 0;
}

bool storage_has_space(void)
{
    if (!g_config) return false;

    disk_info_t info;
    if (storage_get_disk_info(&info) != 0)
        return false;

    uint64_t min_free = (uint64_t)g_config->min_free_space_gb * 1024ULL * 1024ULL * 1024ULL;
    bool has_space = info.free_bytes > min_free;

    if (!has_space) {
        log_warn("Insufficient disk space: %.2f GB free, %.2f GB required",
                 (double)info.free_bytes / (1024.0 * 1024.0 * 1024.0),
                 (double)g_config->min_free_space_gb);
    }

    return has_space;
}

int storage_create_content_dir(const char *content_id, char *out_path, size_t out_len)
{
    if (!g_config || !content_id || !out_path) return -1;

    snprintf(out_path, out_len, "%s/%s", g_config->library_path, content_id);

    if (mkdir_recursive(out_path, 0755) != 0) {
        log_error("Failed to create content directory: %s", out_path);
        return -1;
    }

    log_debug("Created content directory: %s", out_path);
    return 0;
}

int storage_get_temp_dir(int job_id, char *out_path, size_t out_len)
{
    if (!g_config || !out_path) return -1;

    snprintf(out_path, out_len, "%s/job_%d", g_config->temp_path, job_id);

    if (mkdir_recursive(out_path, 0755) != 0) {
        log_error("Failed to create temp directory: %s", out_path);
        return -1;
    }

    log_debug("Created temp directory: %s", out_path);
    return 0;
}

int storage_cleanup_temp(int job_id)
{
    if (!g_config) return -1;

    char path[MAX_PATH_LEN + 256];
    snprintf(path, sizeof(path), "%s/job_%d", g_config->temp_path, job_id);

    struct stat st;
    if (stat(path, &st) != 0) {
        /* Directory doesn't exist, nothing to clean */
        log_debug("Temp directory doesn't exist, skipping cleanup: %s", path);
        return 0;
    }

    log_info("Cleaning up temp directory: %s", path);

    if (remove_recursive(path) != 0) {
        log_error("Failed to clean up temp directory: %s", path);
        return -1;
    }

    return 0;
}

int storage_remove_content(const char *content_id)
{
    if (!g_config || !content_id) return -1;

    char path[MAX_PATH_LEN + 256];
    snprintf(path, sizeof(path), "%s/%s", g_config->library_path, content_id);

    struct stat st;
    if (stat(path, &st) != 0) {
        log_warn("Content directory doesn't exist: %s", path);
        return 0;
    }

    log_info("Removing content directory: %s", path);

    if (remove_recursive(path) != 0) {
        log_error("Failed to remove content directory: %s", path);
        return -1;
    }

    return 0;
}

uint64_t storage_dir_size(const char *path)
{
    if (!path) return 0;

    struct stat st;
    if (stat(path, &st) != 0) return 0;

    g_dir_size_acc = 0;

    if (nftw(path, dir_size_cb, 64, FTW_PHYS) != 0) {
        log_warn("nftw failed for '%s': %s", path, strerror(errno));
        return 0;
    }

    return g_dir_size_acc;
}

int storage_list_content(content_dir_t **out_dirs)
{
    if (!g_config || !out_dirs) return -1;

    *out_dirs = NULL;

    DIR *dir = opendir(g_config->library_path);
    if (!dir) {
        log_error("Failed to open library directory '%s': %s",
                  g_config->library_path, strerror(errno));
        return -1;
    }

    /* First pass: count subdirectories */
    int count = 0;
    struct dirent *entry;

    while ((entry = readdir(dir)) != NULL) {
        if (entry->d_name[0] == '.') continue;

        char full_path[MAX_PATH_LEN + 256];
        snprintf(full_path, sizeof(full_path), "%s/%s",
                 g_config->library_path, entry->d_name);

        struct stat st;
        if (stat(full_path, &st) == 0 && S_ISDIR(st.st_mode))
            count++;
    }

    if (count == 0) {
        closedir(dir);
        return 0;
    }

    /* Allocate result array */
    content_dir_t *dirs = calloc((size_t)count, sizeof(content_dir_t));
    if (!dirs) {
        log_error("Failed to allocate memory for content directory list");
        closedir(dir);
        return -1;
    }

    /* Second pass: populate entries */
    rewinddir(dir);
    int idx = 0;

    while ((entry = readdir(dir)) != NULL && idx < count) {
        if (entry->d_name[0] == '.') continue;

        char full_path[MAX_PATH_LEN + 256];
        snprintf(full_path, sizeof(full_path), "%s/%s",
                 g_config->library_path, entry->d_name);

        struct stat st;
        if (stat(full_path, &st) != 0 || !S_ISDIR(st.st_mode))
            continue;

        snprintf(dirs[idx].content_id, sizeof(dirs[idx].content_id), "%s", entry->d_name);
        snprintf(dirs[idx].path, sizeof(dirs[idx].path), "%s", full_path);
        dirs[idx].size_bytes = storage_dir_size(full_path);

        /* Count files in this content directory */
        int file_count = 0;
        DIR *subdir = opendir(full_path);
        if (subdir) {
            struct dirent *sub_entry;
            while ((sub_entry = readdir(subdir)) != NULL) {
                if (sub_entry->d_name[0] == '.') continue;

                char sub_path[MAX_PATH_LEN + 512];
                snprintf(sub_path, sizeof(sub_path), "%s/%s",
                         full_path, sub_entry->d_name);

                struct stat sub_st;
                if (stat(sub_path, &sub_st) == 0 && S_ISREG(sub_st.st_mode))
                    file_count++;
            }
            closedir(subdir);
        }
        dirs[idx].file_count = file_count;

        idx++;
    }

    closedir(dir);

    *out_dirs = dirs;
    return idx;
}

int storage_copy_file(const char *src, const char *dst)
{
    if (!src || !dst) return -1;

    int src_fd = open(src, O_RDONLY);
    if (src_fd < 0) {
        log_error("Failed to open source file '%s': %s", src, strerror(errno));
        return -1;
    }

    /* Get source file permissions */
    struct stat st;
    if (fstat(src_fd, &st) != 0) {
        log_error("fstat failed for '%s': %s", src, strerror(errno));
        close(src_fd);
        return -1;
    }

    int dst_fd = open(dst, O_WRONLY | O_CREAT | O_TRUNC, st.st_mode);
    if (dst_fd < 0) {
        log_error("Failed to create destination file '%s': %s", dst, strerror(errno));
        close(src_fd);
        return -1;
    }

    char *buf = malloc(COPY_BUF_SIZE);
    if (!buf) {
        log_error("Failed to allocate copy buffer");
        close(src_fd);
        close(dst_fd);
        return -1;
    }

    ssize_t bytes_read;
    int result = 0;

    while ((bytes_read = read(src_fd, buf, COPY_BUF_SIZE)) > 0) {
        ssize_t bytes_written = 0;
        while (bytes_written < bytes_read) {
            ssize_t written = write(dst_fd, buf + bytes_written,
                                    (size_t)(bytes_read - bytes_written));
            if (written < 0) {
                log_error("Write failed for '%s': %s", dst, strerror(errno));
                result = -1;
                goto cleanup;
            }
            bytes_written += written;
        }
    }

    if (bytes_read < 0) {
        log_error("Read failed for '%s': %s", src, strerror(errno));
        result = -1;
    }

cleanup:
    free(buf);
    close(src_fd);
    close(dst_fd);

    if (result != 0)
        unlink(dst); /* Clean up partial file on error */

    return result;
}

int storage_move_file(const char *src, const char *dst)
{
    if (!src || !dst) return -1;

    /* Try rename first (works if same filesystem) */
    if (rename(src, dst) == 0) {
        log_debug("Moved file (rename): %s -> %s", src, dst);
        return 0;
    }

    /* rename failed (likely cross-device), fall back to copy + delete */
    if (errno != EXDEV) {
        log_debug("rename failed for '%s' -> '%s': %s, trying copy+delete",
                  src, dst, strerror(errno));
    }

    if (storage_copy_file(src, dst) != 0) {
        log_error("Failed to copy file '%s' -> '%s'", src, dst);
        return -1;
    }

    if (unlink(src) != 0) {
        log_warn("Copied file but failed to remove source '%s': %s",
                 src, strerror(errno));
        /* File was copied successfully, so we return success */
    }

    log_debug("Moved file (copy+delete): %s -> %s", src, dst);
    return 0;
}

int storage_move_dir(const char *src, const char *dst)
{
    if (!src || !dst) return -1;

    /* Try rename first (works if same filesystem) */
    if (rename(src, dst) == 0) {
        log_debug("Moved directory (rename): %s -> %s", src, dst);
        return 0;
    }

    /* rename failed, fall back to recursive copy + delete */
    if (errno != EXDEV) {
        log_debug("rename failed for dir '%s' -> '%s': %s, trying copy+delete",
                  src, dst, strerror(errno));
    }

    if (copy_dir_recursive(src, dst) != 0) {
        log_error("Failed to copy directory '%s' -> '%s'", src, dst);
        return -1;
    }

    if (remove_recursive(src) != 0) {
        log_warn("Copied directory but failed to remove source '%s'", src);
    }

    log_debug("Moved directory (copy+delete): %s -> %s", src, dst);
    return 0;
}

int storage_ensure_dir(const char *path)
{
    if (!path) return -1;
    return mkdir_recursive(path, 0755);
}
