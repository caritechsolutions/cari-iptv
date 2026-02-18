#ifndef VOD_API_ROUTES_H
#define VOD_API_ROUTES_H

#include "http_server.h"

/**
 * Handle an API request under /api/.
 * Returns MHD_YES if handled, MHD_NO if not an API route.
 */
int api_handle_request(http_request_t *req);

/* Individual route handlers */
int api_get_status(http_request_t *req);
int api_get_config(http_request_t *req);
int api_post_config(http_request_t *req);

/* Content routes */
int api_get_content_list(http_request_t *req);
int api_get_content(http_request_t *req, const char *content_id);
int api_delete_content(http_request_t *req, const char *content_id);
int api_get_content_download(http_request_t *req, const char *content_id);

/* Job routes */
int api_get_jobs(http_request_t *req);
int api_post_job(http_request_t *req);
int api_get_job(http_request_t *req, int job_id);
int api_delete_job(http_request_t *req, int job_id);
int api_bulk_delete_jobs(http_request_t *req);

/* Peer routes */
int api_get_peers(http_request_t *req);
int api_post_peer(http_request_t *req);
int api_delete_peer(http_request_t *req, int peer_id);
int api_get_peer_content(http_request_t *req, int peer_id);

/* Migration routes */
int api_get_migrations(http_request_t *req);
int api_post_migration(http_request_t *req);
int api_get_migration(http_request_t *req, int migration_id);
int api_delete_migration(http_request_t *req, int migration_id);

/* Peer health endpoint (called by other peers) */
int api_post_peer_health(http_request_t *req);

/* Upload routes */
int api_post_upload(http_request_t *req);
int api_get_uploads(http_request_t *req);
int api_delete_upload(http_request_t *req);

/* Utility routes */
int api_get_browse(http_request_t *req);

/* SSL routes */
int api_get_ssl_status(http_request_t *req);
int api_post_ssl_letsencrypt(http_request_t *req);
int api_post_ssl_self_signed(http_request_t *req);

/**
 * Set the configuration pointer used by API routes.
 * Must be called before handling requests (typically at startup).
 */
void api_routes_set_config(const vod_config_t *config);

#endif /* VOD_API_ROUTES_H */
