#include "web_routes.h"
#include "http_server.h"
#include "config.h"
#include "logger.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <errno.h>

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
 * Serve index.html with the API key injected as a JS variable.
 * This allows the built-in web GUI to authenticate API requests.
 * The response is not cached (contains credentials).
 */
static int serve_index_html(struct MHD_Connection *conn, const char *filepath)
{
    struct stat st;
    if (stat(filepath, &st) != 0 || !S_ISREG(st.st_mode)) {
        return MHD_NO;
    }

    FILE *fp = fopen(filepath, "rb");
    if (!fp) {
        log_error("Cannot open index.html: %s (%s)", filepath, strerror(errno));
        return MHD_NO;
    }

    char *html = malloc(st.st_size + 1);
    if (!html) {
        fclose(fp);
        return MHD_NO;
    }

    size_t nread = fread(html, 1, st.st_size, fp);
    fclose(fp);
    html[nread] = '\0';

    /* Build the script tag to inject */
    const char *api_key = (g_http_config && g_http_config->api_key[0])
                          ? g_http_config->api_key : "";

    char script_tag[512];
    snprintf(script_tag, sizeof(script_tag),
             "<script>window.VOD_API_KEY=\"%s\";</script>\n</head>",
             api_key);

    /* Find </head> and replace it with script + </head> */
    char *head_end = strstr(html, "</head>");
    if (!head_end) {
        /* No </head> found, serve as-is */
        struct MHD_Response *response =
            MHD_create_response_from_buffer(nread, html, MHD_RESPMEM_MUST_FREE);
        if (!response) { free(html); return MHD_NO; }

        MHD_add_response_header(response, "Content-Type", CT_HTML);
        MHD_add_response_header(response, "Cache-Control", "no-cache, no-store, must-revalidate");
        int ret = MHD_queue_response(conn, MHD_HTTP_OK, response);
        MHD_destroy_response(response);
        return ret;
    }

    /* Build new HTML with injected script */
    size_t prefix_len = (size_t)(head_end - html);
    size_t script_len = strlen(script_tag);
    size_t suffix_len = nread - prefix_len - 7; /* 7 = strlen("</head>") */
    size_t new_len = prefix_len + script_len + suffix_len;

    char *new_html = malloc(new_len + 1);
    if (!new_html) {
        free(html);
        return MHD_NO;
    }

    memcpy(new_html, html, prefix_len);
    memcpy(new_html + prefix_len, script_tag, script_len);
    memcpy(new_html + prefix_len + script_len, head_end + 7, suffix_len);
    new_html[new_len] = '\0';

    free(html);

    struct MHD_Response *response =
        MHD_create_response_from_buffer(new_len, new_html, MHD_RESPMEM_MUST_FREE);
    if (!response) {
        free(new_html);
        return MHD_NO;
    }

    MHD_add_response_header(response, "Content-Type", CT_HTML);
    /* Don't cache - contains API key and we want updates to take effect immediately */
    MHD_add_response_header(response, "Cache-Control", "no-cache, no-store, must-revalidate");

    int ret = MHD_queue_response(conn, MHD_HTTP_OK, response);
    MHD_destroy_response(response);
    return ret;
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

    char filepath[MAX_PATH_LEN + 256];
    const char *www_root = g_http_config ? g_http_config->www_root : "/usr/local/share/vod-server/www";

    /* SPA routes -> serve index.html with injected API key */
    if (is_spa_route(req->url)) {
        snprintf(filepath, sizeof(filepath), "%s/index.html", www_root);

        int result = serve_index_html(req->connection, filepath);
        if (result == MHD_NO) {
            log_warn("Web GUI index.html not found at: %s", filepath);
            return http_send_error(req->connection, 404,
                "Web GUI not installed. Place files in www_root directory.");
        }
        return result;
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
        /* File not found - serve index.html for potential SPA deep links */
        snprintf(filepath, sizeof(filepath), "%s/index.html", www_root);

        result = serve_index_html(req->connection, filepath);
        if (result != MHD_NO) return result;

        return http_send_error(req->connection, 404, "Not found");
    }

    return result;
}
