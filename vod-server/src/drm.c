/*
 * drm.c - DRM key management and ClearKey license server
 *
 * Implements CENC (Common Encryption) key generation and storage,
 * plus W3C ClearKey license responses for EME-based players.
 *
 * The encryption uses the same AES-128 CENC standard as Widevine,
 * PlayReady, and FairPlay. Switching to a commercial DRM provider
 * later only requires changing the license delivery mechanism —
 * encrypted content stays the same.
 */

#include "drm.h"
#include "database.h"
#include "logger.h"
#include "cjson/cJSON.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <errno.h>
#include <sqlite3.h>

/* ---------------------------------------------------------------------------
 * Hex encoding/decoding
 * ------------------------------------------------------------------------- */

void drm_bin_to_hex(const uint8_t *bin, size_t len, char *out)
{
    static const char hex_chars[] = "0123456789abcdef";
    for (size_t i = 0; i < len; i++) {
        out[i * 2]     = hex_chars[(bin[i] >> 4) & 0x0F];
        out[i * 2 + 1] = hex_chars[bin[i] & 0x0F];
    }
    out[len * 2] = '\0';
}

int drm_hex_to_bin(const char *hex, uint8_t *out, size_t out_size)
{
    size_t hex_len = strlen(hex);
    if (hex_len % 2 != 0) return -1;

    size_t bin_len = hex_len / 2;
    if (bin_len > out_size) return -1;

    for (size_t i = 0; i < bin_len; i++) {
        unsigned int byte;
        if (sscanf(hex + (i * 2), "%2x", &byte) != 1) return -1;
        out[i] = (uint8_t)byte;
    }
    return (int)bin_len;
}

/* ---------------------------------------------------------------------------
 * Base64url encoding/decoding (RFC 4648 §5, no padding)
 * ------------------------------------------------------------------------- */

static const char b64url_table[] =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";

void drm_base64url_encode(const uint8_t *data, size_t len, char *out, size_t out_size)
{
    size_t i = 0, j = 0;
    size_t max_out = out_size - 1;

    while (i < len && j + 3 < max_out) {
        uint32_t octet_a = data[i++];
        uint32_t octet_b = (i < len) ? data[i++] : 0;
        uint32_t octet_c = (i < len) ? data[i++] : 0;

        uint32_t triple = (octet_a << 16) | (octet_b << 8) | octet_c;

        out[j++] = b64url_table[(triple >> 18) & 0x3F];
        out[j++] = b64url_table[(triple >> 12) & 0x3F];

        if (i > len + 1) break;
        out[j++] = b64url_table[(triple >> 6) & 0x3F];

        if (i > len) break;
        out[j++] = b64url_table[triple & 0x3F];
    }
    out[j] = '\0';
}

static int b64url_decode_char(char c)
{
    if (c >= 'A' && c <= 'Z') return c - 'A';
    if (c >= 'a' && c <= 'z') return c - 'a' + 26;
    if (c >= '0' && c <= '9') return c - '0' + 52;
    if (c == '-') return 62;
    if (c == '_') return 63;
    return -1;
}

int drm_base64url_decode(const char *input, uint8_t *out, size_t out_size)
{
    size_t input_len = strlen(input);
    size_t j = 0;

    for (size_t i = 0; i < input_len; ) {
        int a = (i < input_len) ? b64url_decode_char(input[i++]) : 0;
        int b = (i < input_len) ? b64url_decode_char(input[i++]) : 0;
        int c = (i < input_len) ? b64url_decode_char(input[i++]) : 0;
        int d = (i < input_len) ? b64url_decode_char(input[i++]) : 0;

        if (a < 0 || b < 0) return -1;

        uint32_t triple = ((uint32_t)a << 18) | ((uint32_t)b << 12) |
                           ((uint32_t)(c >= 0 ? c : 0) << 6) |
                           (uint32_t)(d >= 0 ? d : 0);

        if (j < out_size) out[j++] = (triple >> 16) & 0xFF;
        if (c >= 0 && j < out_size) out[j++] = (triple >> 8) & 0xFF;
        if (d >= 0 && j < out_size) out[j++] = triple & 0xFF;
    }
    return (int)j;
}

/* ---------------------------------------------------------------------------
 * UUID formatting (for PSSH/manifest key IDs)
 * Format: 8-4-4-4-12 hex with hyphens
 * ------------------------------------------------------------------------- */

static void format_uuid(const char *hex32, char *uuid_out)
{
    /* hex32 is 32 hex chars, uuid_out needs 37 chars (36 + NUL) */
    snprintf(uuid_out, DRM_UUID_SIZE,
             "%.8s-%.4s-%.4s-%.4s-%.12s",
             hex32, hex32 + 8, hex32 + 12, hex32 + 16, hex32 + 20);
}

/* ---------------------------------------------------------------------------
 * Scheme name conversion
 * ------------------------------------------------------------------------- */

const char *drm_scheme_name(drm_scheme_t scheme)
{
    switch (scheme) {
        case DRM_SCHEME_CENC: return "cenc";
        case DRM_SCHEME_CBCS: return "cbcs";
        default:              return "none";
    }
}

drm_scheme_t drm_parse_scheme(const char *name)
{
    if (!name) return DRM_SCHEME_NONE;
    if (strcasecmp(name, "cenc") == 0) return DRM_SCHEME_CENC;
    if (strcasecmp(name, "cbcs") == 0) return DRM_SCHEME_CBCS;
    return DRM_SCHEME_NONE;
}

/* ---------------------------------------------------------------------------
 * Random key generation using /dev/urandom
 * ------------------------------------------------------------------------- */

static int generate_random_bytes(uint8_t *buf, size_t len)
{
    FILE *fp = fopen("/dev/urandom", "rb");
    if (!fp) {
        log_error("DRM: cannot open /dev/urandom: %s", strerror(errno));
        return -1;
    }

    size_t read = fread(buf, 1, len, fp);
    fclose(fp);

    if (read != len) {
        log_error("DRM: short read from /dev/urandom (%zu/%zu)", read, len);
        return -1;
    }
    return 0;
}

/* ---------------------------------------------------------------------------
 * Database operations
 * ------------------------------------------------------------------------- */

/* SQL to create drm_keys table */
static const char *DRM_SCHEMA =
    "CREATE TABLE IF NOT EXISTS drm_keys ("
    "  id          INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  content_id  TEXT UNIQUE NOT NULL,"
    "  key_hex     TEXT NOT NULL,"       /* AES-128 key in hex (32 chars) */
    "  kid_hex     TEXT NOT NULL,"       /* Key ID in hex (32 chars) */
    "  scheme      INTEGER DEFAULT 1,"   /* 1=cenc, 2=cbcs */
    "  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP"
    ");\n"
    "CREATE INDEX IF NOT EXISTS idx_drm_keys_content ON drm_keys(content_id);\n";

int drm_init(void)
{
    sqlite3 *db = db_handle();
    if (!db) {
        log_error("DRM: database not initialized");
        return -1;
    }

    char *errmsg = NULL;
    int rc = sqlite3_exec(db, DRM_SCHEMA, NULL, NULL, &errmsg);
    if (rc != SQLITE_OK) {
        log_error("DRM: failed to create schema: %s", errmsg);
        sqlite3_free(errmsg);
        return -1;
    }

    log_info("DRM module initialized (ClearKey + CENC)");
    return 0;
}

/* ---------------------------------------------------------------------------
 * Key generation
 * ------------------------------------------------------------------------- */

int drm_generate_key(const char *content_id, drm_scheme_t scheme, drm_key_t *out_key)
{
    if (!content_id || !out_key) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    /* Generate random key and key ID */
    uint8_t key_bin[DRM_KEY_SIZE];
    uint8_t kid_bin[DRM_KEY_ID_SIZE];

    if (generate_random_bytes(key_bin, DRM_KEY_SIZE) != 0) return -1;
    if (generate_random_bytes(kid_bin, DRM_KEY_ID_SIZE) != 0) return -1;

    /* Set UUID version 4 bits on the key ID (RFC 4122) */
    kid_bin[6] = (kid_bin[6] & 0x0F) | 0x40;  /* Version 4 */
    kid_bin[8] = (kid_bin[8] & 0x3F) | 0x80;  /* Variant 1 */

    /* Convert to hex strings */
    char key_hex[DRM_HEX_KEY_SIZE];
    char kid_hex[DRM_HEX_KID_SIZE];
    drm_bin_to_hex(key_bin, DRM_KEY_SIZE, key_hex);
    drm_bin_to_hex(kid_bin, DRM_KEY_ID_SIZE, kid_hex);

    /* Insert into database (replace if already exists) */
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "INSERT OR REPLACE INTO drm_keys (content_id, key_hex, kid_hex, scheme) "
        "VALUES (?, ?, ?, ?)",
        -1, &stmt, NULL);

    if (rc != SQLITE_OK) {
        log_error("DRM: prepare insert failed: %s", sqlite3_errmsg(db));
        return -1;
    }

    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 2, key_hex, -1, SQLITE_TRANSIENT);
    sqlite3_bind_text(stmt, 3, kid_hex, -1, SQLITE_TRANSIENT);
    sqlite3_bind_int(stmt, 4, (int)scheme);

    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) {
        log_error("DRM: insert key failed: %s", sqlite3_errmsg(db));
        return -1;
    }

    /* Fill output */
    memset(out_key, 0, sizeof(*out_key));
    out_key->id = (int)sqlite3_last_insert_rowid(db);
    snprintf(out_key->content_id, sizeof(out_key->content_id), "%s", content_id);
    snprintf(out_key->key_hex, sizeof(out_key->key_hex), "%s", key_hex);
    snprintf(out_key->kid_hex, sizeof(out_key->kid_hex), "%s", kid_hex);
    format_uuid(kid_hex, out_key->kid_uuid);
    out_key->scheme = (int)scheme;

    log_info("DRM: generated %s key for content '%s' (kid=%s)",
             drm_scheme_name(scheme), content_id, out_key->kid_uuid);

    /* Clear sensitive data from stack */
    memset(key_bin, 0, sizeof(key_bin));

    return 0;
}

/* ---------------------------------------------------------------------------
 * Key lookup
 * ------------------------------------------------------------------------- */

int drm_get_key(const char *content_id, drm_key_t *out_key)
{
    if (!content_id || !out_key) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT id, content_id, key_hex, kid_hex, scheme, "
        "datetime(created_at) FROM drm_keys WHERE content_id = ?",
        -1, &stmt, NULL);

    if (rc != SQLITE_OK) return -1;

    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_TRANSIENT);

    rc = sqlite3_step(stmt);
    if (rc != SQLITE_ROW) {
        sqlite3_finalize(stmt);
        return -1;
    }

    memset(out_key, 0, sizeof(*out_key));
    out_key->id = sqlite3_column_int(stmt, 0);
    snprintf(out_key->content_id, sizeof(out_key->content_id),
             "%s", (const char *)sqlite3_column_text(stmt, 1));
    snprintf(out_key->key_hex, sizeof(out_key->key_hex),
             "%s", (const char *)sqlite3_column_text(stmt, 2));
    snprintf(out_key->kid_hex, sizeof(out_key->kid_hex),
             "%s", (const char *)sqlite3_column_text(stmt, 3));
    format_uuid(out_key->kid_hex, out_key->kid_uuid);
    out_key->scheme = sqlite3_column_int(stmt, 4);

    const char *created = (const char *)sqlite3_column_text(stmt, 5);
    if (created) {
        snprintf(out_key->created_at, sizeof(out_key->created_at), "%s", created);
    }

    sqlite3_finalize(stmt);
    return 0;
}

/* ---------------------------------------------------------------------------
 * Key deletion
 * ------------------------------------------------------------------------- */

int drm_delete_key(const char *content_id)
{
    if (!content_id) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "DELETE FROM drm_keys WHERE content_id = ?",
        -1, &stmt, NULL);

    if (rc != SQLITE_OK) return -1;

    sqlite3_bind_text(stmt, 1, content_id, -1, SQLITE_TRANSIENT);
    rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    if (rc != SQLITE_DONE) return -1;

    int changes = sqlite3_changes(db);
    if (changes == 0) return -1;

    log_info("DRM: deleted key for content '%s'", content_id);
    return 0;
}

/* ---------------------------------------------------------------------------
 * List all keys
 * ------------------------------------------------------------------------- */

int drm_list_keys(drm_key_list_t *list)
{
    if (!list) return -1;

    sqlite3 *db = db_handle();
    if (!db) return -1;

    memset(list, 0, sizeof(*list));

    /* Count first */
    sqlite3_stmt *stmt = NULL;
    int rc = sqlite3_prepare_v2(db,
        "SELECT COUNT(*) FROM drm_keys", -1, &stmt, NULL);
    if (rc != SQLITE_OK) return -1;

    int count = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        count = sqlite3_column_int(stmt, 0);
    }
    sqlite3_finalize(stmt);

    if (count == 0) return 0;

    /* Allocate */
    list->keys = calloc((size_t)count, sizeof(drm_key_t));
    if (!list->keys) return -1;
    list->capacity = count;

    /* Fetch all */
    rc = sqlite3_prepare_v2(db,
        "SELECT id, content_id, key_hex, kid_hex, scheme, "
        "datetime(created_at) FROM drm_keys ORDER BY created_at DESC",
        -1, &stmt, NULL);
    if (rc != SQLITE_OK) {
        free(list->keys);
        memset(list, 0, sizeof(*list));
        return -1;
    }

    while (sqlite3_step(stmt) == SQLITE_ROW && list->count < list->capacity) {
        drm_key_t *k = &list->keys[list->count];
        k->id = sqlite3_column_int(stmt, 0);
        snprintf(k->content_id, sizeof(k->content_id),
                 "%s", (const char *)sqlite3_column_text(stmt, 1));
        snprintf(k->key_hex, sizeof(k->key_hex),
                 "%s", (const char *)sqlite3_column_text(stmt, 2));
        snprintf(k->kid_hex, sizeof(k->kid_hex),
                 "%s", (const char *)sqlite3_column_text(stmt, 3));
        format_uuid(k->kid_hex, k->kid_uuid);
        k->scheme = sqlite3_column_int(stmt, 4);

        const char *created = (const char *)sqlite3_column_text(stmt, 5);
        if (created) {
            snprintf(k->created_at, sizeof(k->created_at), "%s", created);
        }

        list->count++;
    }

    sqlite3_finalize(stmt);
    return 0;
}

void drm_free_key_list(drm_key_list_t *list)
{
    if (list && list->keys) {
        /* Zero out keys before freeing (security) */
        memset(list->keys, 0, (size_t)list->capacity * sizeof(drm_key_t));
        free(list->keys);
    }
    if (list) {
        memset(list, 0, sizeof(*list));
    }
}

/* ---------------------------------------------------------------------------
 * ClearKey license response (W3C EME format)
 *
 * The ClearKey response format is:
 * {
 *   "keys": [
 *     {
 *       "kty": "oct",
 *       "kid": "<base64url-encoded key ID>",
 *       "k":   "<base64url-encoded key>"
 *     }
 *   ],
 *   "type": "temporary"
 * }
 * ------------------------------------------------------------------------- */

char *drm_build_clearkey_response_for_content(const char *content_id)
{
    if (!content_id) return NULL;

    drm_key_t key;
    if (drm_get_key(content_id, &key) != 0) {
        log_warn("DRM: no key found for content '%s'", content_id);
        return NULL;
    }

    /* Decode hex to binary */
    uint8_t key_bin[DRM_KEY_SIZE];
    uint8_t kid_bin[DRM_KEY_ID_SIZE];

    if (drm_hex_to_bin(key.key_hex, key_bin, sizeof(key_bin)) != DRM_KEY_SIZE) {
        return NULL;
    }
    if (drm_hex_to_bin(key.kid_hex, kid_bin, sizeof(kid_bin)) != DRM_KEY_ID_SIZE) {
        memset(key_bin, 0, sizeof(key_bin));
        return NULL;
    }

    /* Base64url encode */
    char key_b64[32];
    char kid_b64[32];
    drm_base64url_encode(key_bin, DRM_KEY_SIZE, key_b64, sizeof(key_b64));
    drm_base64url_encode(kid_bin, DRM_KEY_ID_SIZE, kid_b64, sizeof(kid_b64));

    /* Clear sensitive binary key */
    memset(key_bin, 0, sizeof(key_bin));

    /* Build JSON response */
    cJSON *root = cJSON_CreateObject();
    cJSON *keys_arr = cJSON_CreateArray();
    cJSON *key_obj = cJSON_CreateObject();

    cJSON_AddStringToObject(key_obj, "kty", "oct");
    cJSON_AddStringToObject(key_obj, "kid", kid_b64);
    cJSON_AddStringToObject(key_obj, "k", key_b64);
    cJSON_AddItemToArray(keys_arr, key_obj);

    cJSON_AddItemToObject(root, "keys", keys_arr);
    cJSON_AddStringToObject(root, "type", "temporary");

    char *json_str = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);

    return json_str;
}

char *drm_build_clearkey_response(const char *key_ids_json)
{
    if (!key_ids_json) return NULL;

    /* Parse the key IDs array */
    cJSON *kids_array = cJSON_Parse(key_ids_json);
    if (!kids_array || !cJSON_IsArray(kids_array)) {
        if (kids_array) cJSON_Delete(kids_array);
        return NULL;
    }

    sqlite3 *db = db_handle();
    if (!db) {
        cJSON_Delete(kids_array);
        return NULL;
    }

    cJSON *root = cJSON_CreateObject();
    cJSON *keys_arr = cJSON_CreateArray();

    /* For each requested key ID, look it up */
    cJSON *kid_item;
    cJSON_ArrayForEach(kid_item, kids_array) {
        if (!cJSON_IsString(kid_item)) continue;

        /* Decode base64url key ID to binary */
        uint8_t kid_bin[DRM_KEY_ID_SIZE + 4];
        int kid_len = drm_base64url_decode(kid_item->valuestring, kid_bin, sizeof(kid_bin));
        if (kid_len != DRM_KEY_ID_SIZE) continue;

        /* Convert to hex for DB lookup */
        char kid_hex[DRM_HEX_KID_SIZE];
        drm_bin_to_hex(kid_bin, DRM_KEY_ID_SIZE, kid_hex);

        /* Look up in database */
        sqlite3_stmt *stmt = NULL;
        int rc = sqlite3_prepare_v2(db,
            "SELECT key_hex FROM drm_keys WHERE kid_hex = ?",
            -1, &stmt, NULL);
        if (rc != SQLITE_OK) continue;

        sqlite3_bind_text(stmt, 1, kid_hex, -1, SQLITE_TRANSIENT);

        if (sqlite3_step(stmt) == SQLITE_ROW) {
            const char *key_hex = (const char *)sqlite3_column_text(stmt, 0);

            /* Decode key hex to binary */
            uint8_t key_bin[DRM_KEY_SIZE];
            if (drm_hex_to_bin(key_hex, key_bin, sizeof(key_bin)) == DRM_KEY_SIZE) {
                /* Base64url encode both */
                char key_b64[32];
                char kid_b64[32];
                drm_base64url_encode(key_bin, DRM_KEY_SIZE, key_b64, sizeof(key_b64));
                drm_base64url_encode(kid_bin, DRM_KEY_ID_SIZE, kid_b64, sizeof(kid_b64));

                cJSON *key_obj = cJSON_CreateObject();
                cJSON_AddStringToObject(key_obj, "kty", "oct");
                cJSON_AddStringToObject(key_obj, "kid", kid_b64);
                cJSON_AddStringToObject(key_obj, "k", key_b64);
                cJSON_AddItemToArray(keys_arr, key_obj);

                memset(key_bin, 0, sizeof(key_bin));
            }
        }

        sqlite3_finalize(stmt);
    }

    cJSON_Delete(kids_array);

    cJSON_AddItemToObject(root, "keys", keys_arr);
    cJSON_AddStringToObject(root, "type", "temporary");

    char *json_str = cJSON_PrintUnformatted(root);
    cJSON_Delete(root);

    return json_str;
}

/* ---------------------------------------------------------------------------
 * FFmpeg HLS encryption key files
 *
 * FFmpeg's HLS muxer uses -hls_key_info_file which references:
 *   Line 1: Key URI (URL the player fetches the key from)
 *   Line 2: Path to local key file (binary 16 bytes)
 *   Line 3: IV in hex (optional, defaults to segment sequence number)
 *
 * The key file contains the raw 16-byte AES key.
 * ------------------------------------------------------------------------- */

int drm_get_ffmpeg_hls_key_files(const char *content_id, const char *temp_dir,
                                  const char *key_server_url,
                                  char *key_file_out, size_t key_file_size,
                                  char *key_info_file_out, size_t key_info_size)
{
    if (!content_id || !temp_dir) return -1;

    drm_key_t drm_key;
    if (drm_get_key(content_id, &drm_key) != 0) {
        return 1; /* No key = skip encryption */
    }

    /* Write binary key file */
    snprintf(key_file_out, key_file_size, "%s/drm_key.bin", temp_dir);

    uint8_t key_bin[DRM_KEY_SIZE];
    if (drm_hex_to_bin(drm_key.key_hex, key_bin, sizeof(key_bin)) != DRM_KEY_SIZE) {
        return -1;
    }

    FILE *fp = fopen(key_file_out, "wb");
    if (!fp) {
        log_error("DRM: cannot create key file: %s", key_file_out);
        memset(key_bin, 0, sizeof(key_bin));
        return -1;
    }
    fwrite(key_bin, 1, DRM_KEY_SIZE, fp);
    fclose(fp);
    memset(key_bin, 0, sizeof(key_bin));

    /* Write key info file */
    snprintf(key_info_file_out, key_info_size, "%s/drm_key_info.txt", temp_dir);

    fp = fopen(key_info_file_out, "w");
    if (!fp) {
        log_error("DRM: cannot create key info file: %s", key_info_file_out);
        return -1;
    }

    /* Line 1: Key URI that the player will use to fetch the key.
     *
     * When key_server_url is set (e.g. https://iptv.example.com), the URI
     * points through the IPTV middleware proxy at /api/v1/drm/key/{id}.
     * This keeps the VOD server internal and ensures same-origin delivery.
     *
     * When key_server_url is empty, a relative /api/drm/key/{id} is used
     * which resolves against the VOD server's own URL.
     */
    if (key_server_url && key_server_url[0]) {
        fprintf(fp, "%s/api/v1/drm/key/%s\n", key_server_url, content_id);
    } else {
        /* Relative URI - resolves against VOD server base URL */
        fprintf(fp, "/api/drm/key/%s\n", content_id);
    }

    /* Line 2: Local path to key file (for FFmpeg during packaging) */
    fprintf(fp, "%s\n", key_file_out);

    /* Line 3: IV (use key ID as IV for deterministic encryption) */
    fprintf(fp, "%s\n", drm_key.kid_hex);

    fclose(fp);

    log_info("DRM: wrote HLS key files for content '%s' (scheme=%s)",
             content_id, drm_scheme_name((drm_scheme_t)drm_key.scheme));

    return 0;
}

/* ---------------------------------------------------------------------------
 * MP4Box CENC encryption config (for CMAF packaging)
 *
 * Generates a GPAC DRM config XML file:
 * <GPAC>
 *   <DRMInfo type="pssh" systemId="1077...">
 *     <BS bits="32" value="..."/>  (key IDs)
 *   </DRMInfo>
 *   <CrypTrack trackID="1" scheme="cenc" keyID="..." key="..."/>
 * </GPAC>
 * ------------------------------------------------------------------------- */

int drm_get_mp4box_crypt_config(const char *content_id, const char *temp_dir,
                                 char *xml_path_out, size_t xml_path_size)
{
    if (!content_id || !temp_dir || !xml_path_out) return -1;

    drm_key_t drm_key;
    if (drm_get_key(content_id, &drm_key) != 0) {
        return 1; /* No key = skip encryption */
    }

    snprintf(xml_path_out, xml_path_size, "%s/drm_crypt.xml", temp_dir);

    const char *scheme_str = (drm_key.scheme == DRM_SCHEME_CBCS) ? "cbcs" : "cenc";

    /* Build base64 key ID for PSSH data */
    uint8_t kid_bin[DRM_KEY_ID_SIZE];
    if (drm_hex_to_bin(drm_key.kid_hex, kid_bin, sizeof(kid_bin)) != DRM_KEY_ID_SIZE) {
        return -1;
    }

    char kid_b64[32];
    drm_base64url_encode(kid_bin, DRM_KEY_ID_SIZE, kid_b64, sizeof(kid_b64));

    FILE *fp = fopen(xml_path_out, "w");
    if (!fp) {
        log_error("DRM: cannot create crypt config: %s", xml_path_out);
        return -1;
    }

    fprintf(fp, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
    fprintf(fp, "<GPAC>\n");

    /* ClearKey PSSH box (W3C standard) */
    fprintf(fp, "  <DRMInfo type=\"pssh\" systemId=\"%s\">\n", CLEARKEY_SYSTEM_ID);
    fprintf(fp, "    <!-- ClearKey PSSH: key ID = %s -->\n", drm_key.kid_uuid);
    fprintf(fp, "  </DRMInfo>\n");

    /* Encrypt all tracks with the same key */
    /* Track 1 = video */
    fprintf(fp, "  <CrypTrack trackID=\"1\"\n");
    fprintf(fp, "    IsEncrypted=\"1\"\n");
    fprintf(fp, "    IV_size=\"16\"\n");
    fprintf(fp, "    saiSavedBox=\"senc\"\n");
    fprintf(fp, "    scheme=\"%s\"\n", scheme_str);
    fprintf(fp, "    keyID=\"0x%s\"\n", drm_key.kid_hex);
    fprintf(fp, "    key=\"0x%s\"\n", drm_key.key_hex);
    fprintf(fp, "  />\n");

    /* Track 2 = audio */
    fprintf(fp, "  <CrypTrack trackID=\"2\"\n");
    fprintf(fp, "    IsEncrypted=\"1\"\n");
    fprintf(fp, "    IV_size=\"16\"\n");
    fprintf(fp, "    saiSavedBox=\"senc\"\n");
    fprintf(fp, "    scheme=\"%s\"\n", scheme_str);
    fprintf(fp, "    keyID=\"0x%s\"\n", drm_key.kid_hex);
    fprintf(fp, "    key=\"0x%s\"\n", drm_key.key_hex);
    fprintf(fp, "  />\n");

    fprintf(fp, "</GPAC>\n");
    fclose(fp);

    log_info("DRM: wrote MP4Box crypt config for content '%s' (scheme=%s)",
             content_id, scheme_str);

    return 0;
}
