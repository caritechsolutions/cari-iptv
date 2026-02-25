#ifndef VOD_HTTP_SERVER_H
#define VOD_HTTP_SERVER_H

#include "config.h"
#include <stdio.h>
#include <stdbool.h>
#include <microhttpd.h>

/* HTTP methods */
#define HTTP_GET    "GET"
#define HTTP_POST   "POST"
#define HTTP_PUT    "PUT"
#define HTTP_DELETE "DELETE"

/* Content types */
#define CT_JSON     "application/json"
#define CT_HTML     "text/html"
#define CT_CSS      "text/css"
#define CT_JS       "application/javascript"
#define CT_PNG      "image/png"
#define CT_JPG      "image/jpeg"
#define CT_SVG      "image/svg+xml"
#define CT_ICON     "image/x-icon"
#define CT_WOFF2    "font/woff2"
#define CT_PLAIN    "text/plain"
#define CT_OCTET    "application/octet-stream"

/* Request context passed to handlers */
typedef struct {
    struct MHD_Connection *connection;
    const char           *url;
    const char           *method;
    const char           *upload_data;
    size_t                upload_data_size;
    char                 *post_data;
    size_t                post_data_len;
    size_t                post_data_alloc;
    bool                  authenticated;

    /* File upload state (for POST /api/upload) */
    FILE                 *upload_fp;
    char                  upload_path[MAX_PATH_LEN];
    size_t                upload_size;
    bool                  is_upload;
} http_request_t;

/* Response helper */
typedef struct {
    int         status_code;
    const char *content_type;
    char       *body;
    size_t      body_len;
    bool        free_body;
} http_response_t;

/**
 * Start the main HTTP/HTTPS server.
 * Returns 0 on success, -1 on error.
 */
int http_server_start(const vod_config_t *config);

/**
 * Stop the main HTTP/HTTPS server.
 */
void http_server_stop(void);

/**
 * Check if the main server is running.
 */
bool http_server_is_running(void);

/**
 * Start the ACME HTTP listener (port 80).
 * Serves /.well-known/acme-challenge/ files from the configured webroot
 * and 301 redirects everything else to the HTTPS port.
 * Returns 0 on success, -1 on error.
 */
int http_server_start_acme(const vod_config_t *config);

/**
 * Stop the ACME HTTP listener.
 */
void http_server_stop_acme(void);

/**
 * Send a JSON response.
 */
int http_send_json(struct MHD_Connection *conn, int status, const char *json);

/**
 * Send a static file response.
 */
int http_send_file(struct MHD_Connection *conn, const char *filepath);

/**
 * Send a simple error response.
 */
int http_send_error(struct MHD_Connection *conn, int status, const char *message);

/**
 * Serve a content/library file (HLS/DASH manifests, segments, etc.).
 * Adds CORS headers for browser-based players and uses fd-based streaming.
 */
int http_send_content_file(struct MHD_Connection *conn, const char *filepath);

/**
 * Get a query parameter value.
 * Returns NULL if not found.
 */
const char *http_get_param(struct MHD_Connection *conn, const char *key);

/**
 * Determine content type from file extension.
 */
const char *http_content_type(const char *filepath);

#endif /* VOD_HTTP_SERVER_H */
