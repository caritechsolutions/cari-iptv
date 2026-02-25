#ifndef VOD_SSL_H
#define VOD_SSL_H

#include "config.h"
#include <stdbool.h>

/**
 * Initialize SSL: load or generate certificates.
 * Returns 0 on success, -1 on error.
 */
int ssl_init(const vod_config_t *config);

/**
 * Clean up SSL resources.
 */
void ssl_cleanup(void);

/**
 * Check if SSL certificates are loaded.
 */
bool ssl_is_ready(void);

/**
 * Get the loaded certificate data (PEM).
 * Returns NULL if not loaded.
 */
const char *ssl_get_cert(void);

/**
 * Get the loaded private key data (PEM).
 * Returns NULL if not loaded.
 */
const char *ssl_get_key(void);

/**
 * Generate a self-signed certificate.
 * Writes cert and key to the specified paths.
 * Returns 0 on success, -1 on error.
 */
int ssl_generate_self_signed(const char *cert_path, const char *key_path);

/**
 * Load certificate and key from files.
 * Returns 0 on success, -1 on error.
 */
int ssl_load_files(const char *cert_path, const char *key_path);

#endif /* VOD_SSL_H */
