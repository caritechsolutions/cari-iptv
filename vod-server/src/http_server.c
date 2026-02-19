#include "http_server.h"
#include "ssl.h"
#include "api_routes.h"
#include "web_routes.h"
#include "logger.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <time.h>
#include <unistd.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <errno.h>

/* ---------- module state ---------- */

static struct MHD_Daemon *g_daemon   = NULL;
const vod_config_t *g_http_config    = NULL;
static volatile bool g_running       = false;

/* Local alias */
#define g_config g_http_config

/* Initial allocation for accumulated POST body */
#define POST_BUF_INITIAL  4096

/* ---------- forward declarations ---------- */

static enum MHD_Result
request_handler(void *cls,
                struct MHD_Connection *connection,
                const char *url,
                const char *method,
                const char *version,
                const char *upload_data,
                size_t *upload_data_size,
                void **con_cls);

static void
request_completed(void *cls,
                  struct MHD_Connection *connection,
                  void **con_cls,
                  enum MHD_RequestTerminationCode toe);

static int add_cors_headers(struct MHD_Response *response);

/* ---------- content-type map ---------- */

typedef struct {
    const char *ext;
    const char *mime;
} mime_entry_t;

static const mime_entry_t mime_table[] = {
    { ".html",  CT_HTML  },
    { ".htm",   CT_HTML  },
    { ".css",   CT_CSS   },
    { ".js",    CT_JS    },
    { ".json",  CT_JSON  },
    { ".png",   CT_PNG   },
    { ".jpg",   CT_JPG   },
    { ".jpeg",  CT_JPG   },
    { ".gif",   "image/gif" },
    { ".svg",   CT_SVG   },
    { ".ico",   CT_ICON  },
    { ".woff",  "font/woff" },
    { ".woff2", CT_WOFF2 },
    { ".ttf",   "font/ttf" },
    { ".eot",   "application/vnd.ms-fontobject" },
    { ".webp",  "image/webp" },
    { ".mp4",   "video/mp4" },
    { ".webm",  "video/webm" },
    { ".m3u8",  "application/vnd.apple.mpegurl" },
    { ".mpd",   "application/dash+xml" },
    { ".ts",    "video/mp2t" },
    { ".m4s",   "video/iso.segment" },
    { ".vtt",   "text/vtt" },
    { ".srt",   "text/plain" },
    { ".xml",   "application/xml" },
    { ".txt",   CT_PLAIN },
    { ".map",   CT_JSON  },
    { NULL, NULL }
};

const char *http_content_type(const char *filepath)
{
    if (!filepath) return CT_OCTET;

    const char *dot = strrchr(filepath, '.');
    if (!dot) return CT_OCTET;

    for (int i = 0; mime_table[i].ext; i++) {
        if (strcasecmp(dot, mime_table[i].ext) == 0) {
            return mime_table[i].mime;
        }
    }
    return CT_OCTET;
}

/* ---------- query-string helper ---------- */

const char *http_get_param(struct MHD_Connection *conn, const char *key)
{
    return MHD_lookup_connection_value(conn, MHD_GET_ARGUMENT_KIND, key);
}

/* ---------- response helpers ---------- */

static int add_cors_headers(struct MHD_Response *response)
{
    MHD_add_response_header(response, "Access-Control-Allow-Origin", "*");
    MHD_add_response_header(response, "Access-Control-Allow-Methods",
                            "GET, POST, PUT, DELETE, OPTIONS");
    MHD_add_response_header(response, "Access-Control-Allow-Headers",
                            "Content-Type, X-API-Key, Authorization");
    MHD_add_response_header(response, "Access-Control-Max-Age", "86400");
    return 0;
}

int http_send_json(struct MHD_Connection *conn, int status, const char *json)
{
    if (!json) json = "{}";
    size_t len = strlen(json);

    struct MHD_Response *response =
        MHD_create_response_from_buffer(len, (void *)json, MHD_RESPMEM_MUST_COPY);
    if (!response) return MHD_NO;

    MHD_add_response_header(response, "Content-Type", CT_JSON);
    add_cors_headers(response);

    int ret = MHD_queue_response(conn, status, response);
    MHD_destroy_response(response);
    return ret;
}

int http_send_error(struct MHD_Connection *conn, int status, const char *message)
{
    char buf[512];
    snprintf(buf, sizeof(buf),
             "{\"error\":\"%s\",\"status\":%d}", message ? message : "Unknown error", status);
    return http_send_json(conn, status, buf);
}

int http_send_file(struct MHD_Connection *conn, const char *filepath)
{
    struct stat st;
    if (stat(filepath, &st) != 0) {
        log_debug("File not found: %s", filepath);
        return http_send_error(conn, MHD_HTTP_NOT_FOUND, "File not found");
    }

    if (!S_ISREG(st.st_mode)) {
        return http_send_error(conn, MHD_HTTP_FORBIDDEN, "Not a regular file");
    }

    FILE *fp = fopen(filepath, "rb");
    if (!fp) {
        log_error("Cannot open file: %s (%s)", filepath, strerror(errno));
        return http_send_error(conn, MHD_HTTP_INTERNAL_SERVER_ERROR, "Cannot read file");
    }

    /* Read file into memory.  For very large files (video segments, etc.)
     * a production server might use MHD_create_response_from_fd instead.
     * For the admin/web UI assets this is perfectly fine. */
    char *buf = malloc(st.st_size);
    if (!buf) {
        fclose(fp);
        return http_send_error(conn, MHD_HTTP_INTERNAL_SERVER_ERROR, "Out of memory");
    }

    size_t nread = fread(buf, 1, st.st_size, fp);
    fclose(fp);

    struct MHD_Response *response =
        MHD_create_response_from_buffer(nread, buf, MHD_RESPMEM_MUST_FREE);
    if (!response) {
        free(buf);
        return MHD_NO;
    }

    const char *ct = http_content_type(filepath);
    MHD_add_response_header(response, "Content-Type", ct);

    /* Cache static assets for 1 hour */
    MHD_add_response_header(response, "Cache-Control", "public, max-age=3600");

    int ret = MHD_queue_response(conn, MHD_HTTP_OK, response);
    MHD_destroy_response(response);
    return ret;
}

/**
 * Serve a content/library file (HLS/DASH segments, manifests, etc.).
 * Similar to http_send_file but uses fd-based streaming for large segments,
 * adds CORS headers for browser-based players, and uses appropriate caching
 * (no-cache for manifests, long cache for immutable segments).
 */
int http_send_content_file(struct MHD_Connection *conn, const char *filepath)
{
    struct stat st;
    if (stat(filepath, &st) != 0) {
        log_debug("Content file not found: %s", filepath);
        return http_send_error(conn, MHD_HTTP_NOT_FOUND, "File not found");
    }

    if (!S_ISREG(st.st_mode)) {
        return http_send_error(conn, MHD_HTTP_FORBIDDEN, "Not a regular file");
    }

    /* Use fd-based response for efficient serving of large media segments */
    int fd = open(filepath, O_RDONLY);
    if (fd < 0) {
        log_error("Cannot open content file: %s (%s)", filepath, strerror(errno));
        return http_send_error(conn, MHD_HTTP_INTERNAL_SERVER_ERROR, "Cannot read file");
    }

    struct MHD_Response *response =
        MHD_create_response_from_fd((uint64_t)st.st_size, fd);
    if (!response) {
        close(fd);
        return MHD_NO;
    }

    const char *ct = http_content_type(filepath);
    MHD_add_response_header(response, "Content-Type", ct);

    /* CORS headers — required for browser-based HLS/DASH players */
    MHD_add_response_header(response, "Access-Control-Allow-Origin", "*");
    MHD_add_response_header(response, "Access-Control-Allow-Methods", "GET, OPTIONS");
    MHD_add_response_header(response, "Access-Control-Allow-Headers",
                            "Content-Type, Range");
    MHD_add_response_header(response, "Access-Control-Expose-Headers",
                            "Content-Length, Content-Range");

    /* Caching: manifests must not be cached (they may update),
     * segments/init files are immutable and can be cached long-term */
    const char *dot = strrchr(filepath, '.');
    if (dot && (strcasecmp(dot, ".m3u8") == 0 || strcasecmp(dot, ".mpd") == 0)) {
        MHD_add_response_header(response, "Cache-Control", "no-cache, no-store, must-revalidate");
    } else {
        MHD_add_response_header(response, "Cache-Control", "public, max-age=86400, immutable");
    }

    int ret = MHD_queue_response(conn, MHD_HTTP_OK, response);
    MHD_destroy_response(response);
    return ret;
}

/* ---------- API key validation ---------- */

/**
 * Validate the X-API-Key header against the configured key.
 * Returns true if the key matches or if no key is configured.
 */
static bool validate_api_key(struct MHD_Connection *conn)
{
    /* If no API key configured, allow all */
    if (!g_config || g_config->api_key[0] == '\0') {
        return true;
    }

    const char *key = MHD_lookup_connection_value(conn, MHD_HEADER_KIND, "X-API-Key");
    if (!key) return false;

    return (strcmp(key, g_config->api_key) == 0);
}

/* ---------- request routing ---------- */

static enum MHD_Result
request_handler(void *cls,
                struct MHD_Connection *connection,
                const char *url,
                const char *method,
                const char *version,
                const char *upload_data,
                size_t *upload_data_size,
                void **con_cls)
{
    (void)cls;
    (void)version;

    /* --- First call: allocate per-request context --- */
    if (*con_cls == NULL) {
        http_request_t *req = calloc(1, sizeof(http_request_t));
        if (!req) return MHD_NO;

        req->connection = connection;
        req->url        = url;
        req->method     = method;

        /* Check if this is a file upload route — stream to disk instead of RAM */
        if (strcmp(method, HTTP_POST) == 0 &&
            strcmp(url, "/api/upload") == 0) {

            /* Validate API key early for uploads */
            if (g_config && g_config->api_key[0] != '\0') {
                const char *key = MHD_lookup_connection_value(
                    connection, MHD_HEADER_KIND, "X-API-Key");
                if (!key || strcmp(key, g_config->api_key) != 0) {
                    free(req);
                    return http_send_error(connection, MHD_HTTP_UNAUTHORIZED,
                                           "Invalid or missing API key");
                }
            }
            req->authenticated = true;

            /* Get the original filename from query param */
            const char *fname = MHD_lookup_connection_value(
                connection, MHD_GET_ARGUMENT_KIND, "filename");

            /* Determine file extension */
            const char *ext = "mp4";
            if (fname) {
                const char *dot = strrchr(fname, '.');
                if (dot && dot[1]) ext = dot + 1;
            }

            /* Build upload path: {temp_path}/uploads/upload_{time}_{rand}.{ext} */
            char upload_dir[MAX_PATH_LEN];
            snprintf(upload_dir, sizeof(upload_dir), "%s/uploads",
                     g_config ? g_config->temp_path : "/var/lib/vod-server/tmp");

            /* Ensure uploads directory exists */
            mkdir(upload_dir, 0775);

            unsigned int rnd = 0;
            FILE *urand = fopen("/dev/urandom", "rb");
            if (urand) {
                fread(&rnd, sizeof(rnd), 1, urand);
                fclose(urand);
            }
            rnd &= 0xFFFFFF;

            snprintf(req->upload_path, sizeof(req->upload_path),
                     "%s/upload_%lu_%06x.%s",
                     upload_dir, (unsigned long)time(NULL), rnd, ext);

            req->upload_fp = fopen(req->upload_path, "wb");
            if (!req->upload_fp) {
                log_error("Failed to open upload file: %s (%s)",
                          req->upload_path, strerror(errno));
                free(req);
                return http_send_error(connection, MHD_HTTP_INTERNAL_SERVER_ERROR,
                                       "Failed to create upload file");
            }

            req->is_upload = true;
            req->upload_size = 0;
            log_info("Upload started: %s", req->upload_path);

        } else if (strcmp(method, HTTP_POST) == 0 ||
                   strcmp(method, HTTP_PUT)  == 0) {
            /* Normal JSON POST — buffer in memory */
            req->post_data = malloc(POST_BUF_INITIAL);
            if (req->post_data) {
                req->post_data_alloc = POST_BUF_INITIAL;
                req->post_data_len   = 0;
                req->post_data[0]    = '\0';
            }
        }

        *con_cls = req;
        return MHD_YES;  /* Tell MHD to keep calling us with data */
    }

    http_request_t *req = (http_request_t *)*con_cls;

    /* --- Accumulate POST / PUT body --- */
    if (*upload_data_size > 0) {

        /* File upload: stream directly to disk */
        if (req->is_upload && req->upload_fp) {
            size_t written = fwrite(upload_data, 1, *upload_data_size, req->upload_fp);
            if (written != *upload_data_size) {
                log_error("Upload write error: wrote %zu of %zu bytes",
                          written, *upload_data_size);
                fclose(req->upload_fp);
                req->upload_fp = NULL;
                unlink(req->upload_path);
                *upload_data_size = 0;
                return MHD_NO;
            }
            req->upload_size += written;
            *upload_data_size = 0;
            return MHD_YES;
        }

        /* Normal JSON: buffer in memory */
        size_t needed = req->post_data_len + *upload_data_size + 1;
        if (needed > req->post_data_alloc) {
            size_t new_alloc = req->post_data_alloc;
            while (new_alloc < needed) {
                new_alloc *= 2;
            }
            char *tmp = realloc(req->post_data, new_alloc);
            if (!tmp) {
                log_error("Failed to reallocate POST buffer (%zu bytes)", new_alloc);
                return MHD_NO;
            }
            req->post_data       = tmp;
            req->post_data_alloc = new_alloc;
        }
        memcpy(req->post_data + req->post_data_len, upload_data, *upload_data_size);
        req->post_data_len += *upload_data_size;
        req->post_data[req->post_data_len] = '\0';

        *upload_data_size = 0;  /* Signal that we consumed it */
        return MHD_YES;
    }

    /* Close upload file handle before routing */
    if (req->is_upload && req->upload_fp) {
        fclose(req->upload_fp);
        req->upload_fp = NULL;
        log_info("Upload complete: %s (%zu bytes)", req->upload_path, req->upload_size);
    }

    /* --- All data received: route the request --- */

    log_debug("%s %s", method, url);

    /* Handle CORS preflight for all routes (API, content, library) */
    if (strcmp(method, "OPTIONS") == 0) {
        struct MHD_Response *response =
            MHD_create_response_from_buffer(0, NULL, MHD_RESPMEM_PERSISTENT);
        add_cors_headers(response);
        int ret = MHD_queue_response(connection, MHD_HTTP_NO_CONTENT, response);
        MHD_destroy_response(response);
        return ret;
    }

    /* --- API routes (/api/...) --- */
    if (strncmp(url, "/api/", 5) == 0) {
        /* /api/status is public (no key needed) */
        bool needs_auth = (strcmp(url, "/api/status") != 0);

        if (needs_auth) {
            if (!validate_api_key(connection)) {
                log_warn("Unauthorized API request: %s %s", method, url);
                return http_send_error(connection, MHD_HTTP_UNAUTHORIZED,
                                       "Invalid or missing API key");
            }
            req->authenticated = true;
        }

        return api_handle_request(req);
    }

    /* --- Web / static routes (everything else) --- */
    return web_handle_request(req);
}

/**
 * Called by MHD when a request is fully completed (after response sent).
 * Free per-request resources.
 */
static void
request_completed(void *cls,
                  struct MHD_Connection *connection,
                  void **con_cls,
                  enum MHD_RequestTerminationCode toe)
{
    (void)cls;
    (void)connection;
    (void)toe;

    if (!con_cls || !*con_cls) return;

    http_request_t *req = (http_request_t *)*con_cls;

    /* Clean up upload file if connection was aborted mid-upload */
    if (req->upload_fp) {
        fclose(req->upload_fp);
        req->upload_fp = NULL;
        if (req->upload_path[0]) {
            log_warn("Cleaning up aborted upload: %s", req->upload_path);
            unlink(req->upload_path);
        }
    }

    if (req->post_data) {
        free(req->post_data);
    }
    free(req);
    *con_cls = NULL;
}

/* ---------- server lifecycle ---------- */

int http_server_start(const vod_config_t *config)
{
    if (g_daemon) {
        log_warn("HTTP server already running");
        return -1;
    }

    g_config = config;

    /* Share config with API routes module */
    api_routes_set_config(config);

    unsigned int flags = MHD_USE_INTERNAL_POLLING_THREAD | MHD_USE_ERROR_LOG;

    /* Build the options array dynamically */
    struct MHD_OptionItem ops[8];
    int nops = 0;

    ops[nops].option = MHD_OPTION_CONNECTION_TIMEOUT;
    ops[nops].value  = 30;   /* 30 seconds */
    ops[nops].ptr_value = NULL;
    nops++;

    ops[nops].option = MHD_OPTION_NOTIFY_COMPLETED;
    ops[nops].value  = 0;
    ops[nops].ptr_value = (void *)request_completed;
    nops++;

    /* TLS setup */
    if (config->ssl_enabled && ssl_is_ready()) {
        const char *cert_pem = ssl_get_cert();
        const char *key_pem  = ssl_get_key();

        if (!cert_pem || !key_pem) {
            log_error("SSL enabled but certificates not loaded");
            return -1;
        }

        flags |= MHD_USE_TLS;

        ops[nops].option = MHD_OPTION_HTTPS_MEM_CERT;
        ops[nops].value  = 0;
        ops[nops].ptr_value = (void *)cert_pem;
        nops++;

        ops[nops].option = MHD_OPTION_HTTPS_MEM_KEY;
        ops[nops].value  = 0;
        ops[nops].ptr_value = (void *)key_pem;
        nops++;

        log_info("TLS enabled for HTTP server");
    }

    /* Terminator */
    ops[nops].option = MHD_OPTION_END;
    ops[nops].value  = 0;
    ops[nops].ptr_value = NULL;
    nops++;

    g_daemon = MHD_start_daemon(
        flags,
        (uint16_t)config->port,
        NULL, NULL,                   /* accept policy (allow all) */
        &request_handler, NULL,       /* access handler, extra arg */
        MHD_OPTION_ARRAY, ops,
        MHD_OPTION_END
    );

    if (!g_daemon) {
        log_error("Failed to start MHD daemon on port %d", config->port);
        return -1;
    }

    g_running = true;
    log_info("HTTP server started on port %d", config->port);
    return 0;
}

void http_server_stop(void)
{
    if (g_daemon) {
        log_info("Stopping HTTP server...");
        MHD_stop_daemon(g_daemon);
        g_daemon  = NULL;
        g_running = false;
        log_info("HTTP server stopped");
    }
}

bool http_server_is_running(void)
{
    return g_running;
}
