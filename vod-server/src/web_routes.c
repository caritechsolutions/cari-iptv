#include "web_routes.h"
#include "http_server.h"
#include "config.h"
#include "logger.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>

/* Global config reference (set by http_server) */
extern const vod_config_t *g_http_config;

/**
 * Map SPA routes to index.html.
 * The web GUI is a single-page app; all HTML routes serve the same index.html.
 */
static int is_spa_route(const char *url)
{
    /* Root */
    if (strcmp(url, "/") == 0) return 1;

    /* Known SPA routes */
    const char *spa_routes[] = {
        "/content",
        "/jobs",
        "/cluster",
        "/settings",
        "/login",
        NULL
    };

    for (int i = 0; spa_routes[i]; i++) {
        if (strcmp(url, spa_routes[i]) == 0) return 1;
    }

    return 0;
}

/**
 * Try to serve a static file from www_root.
 * Returns MHD_YES if file found and served, MHD_NO if not found.
 */
static int serve_static_file(http_request_t *req, const char *filepath)
{
    struct stat st;
    if (stat(filepath, &st) != 0 || !S_ISREG(st.st_mode)) {
        return MHD_NO;
    }

    return http_send_file(req->connection, filepath);
}

int web_handle_request(http_request_t *req)
{
    /* Only handle GET requests for web routes */
    if (strcmp(req->method, HTTP_GET) != 0) {
        return http_send_error(req->connection, 405, "Method not allowed");
    }

    char filepath[2048];
    const char *www_root = g_http_config ? g_http_config->www_root : "/usr/local/share/vod-server/www";

    /* SPA routes -> serve index.html */
    if (is_spa_route(req->url)) {
        snprintf(filepath, sizeof(filepath), "%s/index.html", www_root);

        struct stat st;
        if (stat(filepath, &st) != 0) {
            log_warn("Web GUI index.html not found at: %s", filepath);
            return http_send_error(req->connection, 404,
                "Web GUI not installed. Place files in www_root directory.");
        }

        return http_send_file(req->connection, filepath);
    }

    /* Static asset files (css, js, images, fonts) */
    /* Security: prevent directory traversal */
    if (strstr(req->url, "..") != NULL) {
        log_warn("Directory traversal attempt: %s", req->url);
        return http_send_error(req->connection, 403, "Forbidden");
    }

    snprintf(filepath, sizeof(filepath), "%s%s", www_root, req->url);

    int result = serve_static_file(req, filepath);
    if (result == MHD_NO) {
        /* File not found — serve index.html for potential SPA deep links */
        snprintf(filepath, sizeof(filepath), "%s/index.html", www_root);
        struct stat st;
        if (stat(filepath, &st) == 0) {
            return http_send_file(req->connection, filepath);
        }
        return http_send_error(req->connection, 404, "Not found");
    }

    return result;
}
