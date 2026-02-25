#ifndef VOD_WEB_ROUTES_H
#define VOD_WEB_ROUTES_H

#include "http_server.h"

/**
 * Handle a web GUI request (non-API).
 * Serves HTML pages or static files from www_root.
 * Returns MHD_YES if handled, MHD_NO if not.
 */
int web_handle_request(http_request_t *req);

#endif /* VOD_WEB_ROUTES_H */
