#ifndef VOD_DRM_H
#define VOD_DRM_H

#include "config.h"
#include <stdbool.h>
#include <stdint.h>

/* DRM key sizes (CENC standard) */
#define DRM_KEY_SIZE        16      /* AES-128 key = 16 bytes */
#define DRM_KEY_ID_SIZE     16      /* Key ID (UUID) = 16 bytes */
#define DRM_HEX_KEY_SIZE    33      /* Hex-encoded key + NUL */
#define DRM_HEX_KID_SIZE    33      /* Hex-encoded key ID + NUL */
#define DRM_UUID_SIZE       37      /* UUID string (with hyphens) + NUL */

/* ClearKey system ID (W3C standard) */
#define CLEARKEY_SYSTEM_ID  "1077efecc0b24d02ace33c1e52e2fb4b"

/* Widevine system ID (for future use) */
#define WIDEVINE_SYSTEM_ID  "edef8ba979d64acea3c827dcd51d21ed"

/* DRM encryption schemes */
typedef enum {
    DRM_SCHEME_NONE = 0,
    DRM_SCHEME_CENC,        /* AES-128-CTR (Widevine/PlayReady compatible) */
    DRM_SCHEME_CBCS         /* AES-128-CBC with pattern (FairPlay compatible) */
} drm_scheme_t;

/* DRM key record */
typedef struct {
    int     id;                             /* DB primary key */
    char    content_id[256];                /* External content reference */
    char    key_hex[DRM_HEX_KEY_SIZE];      /* AES-128 key in hex */
    char    kid_hex[DRM_HEX_KID_SIZE];      /* Key ID in hex */
    char    kid_uuid[DRM_UUID_SIZE];        /* Key ID as UUID (for manifests) */
    int     scheme;                         /* drm_scheme_t */
    char    created_at[32];
} drm_key_t;

/* DRM key list (for API responses) */
typedef struct {
    drm_key_t  *keys;
    int         count;
    int         capacity;
} drm_key_list_t;

/**
 * Initialize the DRM module.
 * Creates the drm_keys table if needed.
 * Returns 0 on success, -1 on error.
 */
int drm_init(void);

/**
 * Generate a new DRM key pair for a content item.
 * Generates a random AES-128 key and key ID using /dev/urandom.
 * Stores in the database and fills the provided drm_key_t.
 * scheme: DRM_SCHEME_CENC or DRM_SCHEME_CBCS.
 * Returns 0 on success, -1 on error.
 */
int drm_generate_key(const char *content_id, drm_scheme_t scheme, drm_key_t *out_key);

/**
 * Get the DRM key for a content item.
 * Returns 0 on success, -1 if not found or error.
 */
int drm_get_key(const char *content_id, drm_key_t *out_key);

/**
 * Delete the DRM key for a content item.
 * Returns 0 on success, -1 if not found or error.
 */
int drm_delete_key(const char *content_id);

/**
 * List all DRM keys.
 * Caller must free the returned list with drm_free_key_list().
 * Returns 0 on success, -1 on error.
 */
int drm_list_keys(drm_key_list_t *list);

/**
 * Free a DRM key list.
 */
void drm_free_key_list(drm_key_list_t *list);

/**
 * Build a ClearKey license response (W3C EME JSON format).
 * This is the standard format that browsers understand via EME.
 *
 * Input:  key_ids_json - JSON array of base64url-encoded key IDs
 *         e.g. [\"dGVzdC1raWQtMTIzNDU2Nzg=\"]
 * Output: Allocated JSON string (caller must free).
 *         e.g. {"keys":[{"kty":"oct","kid":"...","k":"..."}],"type":"temporary"}
 *
 * Returns allocated string on success, NULL on error.
 */
char *drm_build_clearkey_response(const char *key_ids_json);

/**
 * Build a ClearKey license response for a single content ID.
 * Simpler variant: looks up by content_id instead of parsing key IDs.
 * Returns allocated string on success, NULL on error.
 */
char *drm_build_clearkey_response_for_content(const char *content_id);

/**
 * Get MP4Box encryption arguments for a content item.
 * Builds the -crypt XML configuration file in a temp directory.
 * Returns path to the XML file on success, NULL if DRM not configured
 * for this content.
 *
 * xml_path_out: buffer to receive the XML file path (at least MAX_PATH_LEN).
 * Returns 0 on success, 1 if no DRM key (skip encryption), -1 on error.
 */
int drm_get_mp4box_crypt_config(const char *content_id, const char *temp_dir,
                                 char *xml_path_out, size_t xml_path_size);

/**
 * Get FFmpeg encryption arguments for HLS AES-128 encryption.
 * Writes the key file and key info file needed by FFmpeg's HLS muxer.
 *
 * key_file_out:     buffer to receive the key file path.
 * key_info_file_out: buffer to receive the key info file path.
 * Returns 0 on success, 1 if no DRM key (skip encryption), -1 on error.
 */
int drm_get_ffmpeg_hls_key_files(const char *content_id, const char *temp_dir,
                                  const char *key_server_url,
                                  char *key_file_out, size_t key_file_size,
                                  char *key_info_file_out, size_t key_info_size);

/**
 * Convert binary data to hex string.
 * out must have at least (len * 2 + 1) bytes.
 */
void drm_bin_to_hex(const uint8_t *bin, size_t len, char *out);

/**
 * Convert hex string to binary data.
 * out must have at least (strlen(hex) / 2) bytes.
 * Returns number of bytes written, or -1 on error.
 */
int drm_hex_to_bin(const char *hex, uint8_t *out, size_t out_size);

/**
 * Base64url-encode binary data (no padding, URL-safe).
 * out must have at least ((len * 4 / 3) + 4) bytes.
 */
void drm_base64url_encode(const uint8_t *data, size_t len, char *out, size_t out_size);

/**
 * Base64url-decode a string.
 * out must have at least (strlen(input) * 3 / 4 + 1) bytes.
 * Returns number of bytes decoded, or -1 on error.
 */
int drm_base64url_decode(const char *input, uint8_t *out, size_t out_size);

/**
 * Get the DRM scheme name as a string.
 */
const char *drm_scheme_name(drm_scheme_t scheme);

/**
 * Parse a DRM scheme name string.
 * Returns DRM_SCHEME_NONE if unrecognized.
 */
drm_scheme_t drm_parse_scheme(const char *name);

#endif /* VOD_DRM_H */
