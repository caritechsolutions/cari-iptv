#include "ssl.h"
#include "logger.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <errno.h>
#include <unistd.h>

#ifdef HAVE_GNUTLS
#include <gnutls/gnutls.h>
#include <gnutls/x509.h>
#include <gnutls/abstract.h>
#endif

/* ---------- module state ---------- */

static char *g_cert_pem = NULL;   /* PEM-encoded certificate */
static char *g_key_pem  = NULL;   /* PEM-encoded private key */
static bool  g_ready    = false;

/* ---------- helpers ---------- */

/**
 * Read an entire file into a newly-allocated string.
 * Returns NULL on error.  Caller must free().
 */
static char *read_file(const char *path)
{
    struct stat st;
    if (stat(path, &st) != 0) return NULL;
    if (st.st_size <= 0)      return NULL;

    FILE *fp = fopen(path, "r");
    if (!fp) return NULL;

    char *buf = malloc((size_t)st.st_size + 1);
    if (!buf) {
        fclose(fp);
        return NULL;
    }

    size_t n = fread(buf, 1, (size_t)st.st_size, fp);
    fclose(fp);

    buf[n] = '\0';
    return buf;
}

/**
 * Check whether a file exists and is a regular file.
 */
static bool file_exists(const char *path)
{
    struct stat st;
    return (stat(path, &st) == 0 && S_ISREG(st.st_mode));
}

/**
 * Ensure the directory for `path` exists, creating it if necessary.
 */
static int ensure_parent_dir(const char *path)
{
    char dir[1024];
    snprintf(dir, sizeof(dir), "%s", path);

    /* Strip the filename component */
    char *slash = strrchr(dir, '/');
    if (!slash) return 0;   /* No directory component */
    *slash = '\0';

    struct stat st;
    if (stat(dir, &st) == 0 && S_ISDIR(st.st_mode)) {
        return 0;  /* Already exists */
    }

    /* Simple one-level mkdir; for deep paths a recursive helper would
     * be needed, but SSL cert directories are typically shallow. */
    if (mkdir(dir, 0755) != 0 && errno != EEXIST) {
        log_error("Cannot create directory: %s (%s)", dir, strerror(errno));
        return -1;
    }
    return 0;
}

/* ---------- self-signed cert generation ---------- */

#ifdef HAVE_GNUTLS

int ssl_generate_self_signed(const char *cert_path, const char *key_path)
{
    int ret = -1;
    gnutls_x509_privkey_t gkey  = NULL;
    gnutls_x509_crt_t     gcert = NULL;

    log_info("Generating self-signed certificate using GnuTLS...");

    if (ensure_parent_dir(cert_path) != 0 || ensure_parent_dir(key_path) != 0) {
        return -1;
    }

    int rc;

    rc = gnutls_x509_privkey_init(&gkey);
    if (rc < 0) {
        log_error("gnutls_x509_privkey_init: %s", gnutls_strerror(rc));
        goto done;
    }

    /* Generate RSA 2048 key */
    log_info("Generating RSA 2048-bit private key (this may take a moment)...");
    rc = gnutls_x509_privkey_generate(gkey, GNUTLS_PK_RSA, 2048, 0);
    if (rc < 0) {
        log_error("gnutls_x509_privkey_generate: %s", gnutls_strerror(rc));
        goto done;
    }

    rc = gnutls_x509_crt_init(&gcert);
    if (rc < 0) {
        log_error("gnutls_x509_crt_init: %s", gnutls_strerror(rc));
        goto done;
    }

    /* Set certificate fields */
    gnutls_x509_crt_set_dn_by_oid(gcert, GNUTLS_OID_X520_COMMON_NAME, 0,
                                    "VOD-Server", strlen("VOD-Server"));
    gnutls_x509_crt_set_dn_by_oid(gcert, GNUTLS_OID_X520_ORGANIZATION_NAME, 0,
                                    "CARI IPTV", strlen("CARI IPTV"));

    /* Serial number */
    unsigned char serial[4] = { 0x00, 0x00, 0x00, 0x01 };
    gnutls_x509_crt_set_serial(gcert, serial, sizeof(serial));

    /* Validity: now to now + 10 years */
    time_t now = time(NULL);
    gnutls_x509_crt_set_activation_time(gcert, now);
    gnutls_x509_crt_set_expiration_time(gcert, now + (time_t)(10 * 365 * 24 * 3600));

    /* Version 3 certificate */
    gnutls_x509_crt_set_version(gcert, 3);

    /* Basic constraints: CA=false */
    gnutls_x509_crt_set_basic_constraints(gcert, 0, -1);

    /* Key usage: digital signature, key encipherment */
    gnutls_x509_crt_set_key_usage(gcert,
        GNUTLS_KEY_DIGITAL_SIGNATURE | GNUTLS_KEY_KEY_ENCIPHERMENT);

    /* Attach the key */
    gnutls_x509_crt_set_key(gcert, gkey);

    /* Self-sign */
    rc = gnutls_x509_crt_sign2(gcert, gcert, gkey, GNUTLS_DIG_SHA256, 0);
    if (rc < 0) {
        log_error("gnutls_x509_crt_sign2: %s", gnutls_strerror(rc));
        goto done;
    }

    /* Export private key to PEM */
    {
        gnutls_datum_t key_data = { NULL, 0 };
        rc = gnutls_x509_privkey_export2(gkey, GNUTLS_X509_FMT_PEM, &key_data);
        if (rc < 0) {
            log_error("Export key PEM: %s", gnutls_strerror(rc));
            goto done;
        }
        FILE *fp = fopen(key_path, "w");
        if (!fp) {
            log_error("Cannot write key file: %s (%s)", key_path, strerror(errno));
            gnutls_free(key_data.data);
            goto done;
        }
        fwrite(key_data.data, 1, key_data.size, fp);
        fclose(fp);
        /* Restrict permissions on private key */
        chmod(key_path, 0600);
        gnutls_free(key_data.data);
    }

    /* Export certificate to PEM */
    {
        gnutls_datum_t cert_data = { NULL, 0 };
        rc = gnutls_x509_crt_export2(gcert, GNUTLS_X509_FMT_PEM, &cert_data);
        if (rc < 0) {
            log_error("Export cert PEM: %s", gnutls_strerror(rc));
            goto done;
        }
        FILE *fp = fopen(cert_path, "w");
        if (!fp) {
            log_error("Cannot write cert file: %s (%s)", cert_path, strerror(errno));
            gnutls_free(cert_data.data);
            goto done;
        }
        fwrite(cert_data.data, 1, cert_data.size, fp);
        fclose(fp);
        chmod(cert_path, 0644);
        gnutls_free(cert_data.data);
    }

    log_info("Self-signed certificate generated:");
    log_info("  Certificate: %s", cert_path);
    log_info("  Private key: %s", key_path);

    ret = 0;

done:
    if (gcert) gnutls_x509_crt_deinit(gcert);
    if (gkey)  gnutls_x509_privkey_deinit(gkey);
    return ret;
}

#else /* !HAVE_GNUTLS -- fallback to openssl CLI */

int ssl_generate_self_signed(const char *cert_path, const char *key_path)
{
    log_info("Generating self-signed certificate using openssl CLI...");

    if (ensure_parent_dir(cert_path) != 0 || ensure_parent_dir(key_path) != 0) {
        return -1;
    }

    /*
     * Build an openssl command to generate a self-signed RSA 2048 cert
     * valid for 3650 days (~10 years) with CN=VOD-Server.
     */
    char cmd[2048];
    snprintf(cmd, sizeof(cmd),
        "openssl req -x509 -newkey rsa:2048 -nodes "
        "-keyout '%s' -out '%s' "
        "-days 3650 "
        "-subj '/CN=VOD-Server/O=CARI IPTV' "
        "2>&1",
        key_path, cert_path);

    log_debug("Running: %s", cmd);

    FILE *proc = popen(cmd, "r");
    if (!proc) {
        log_error("Failed to run openssl: %s", strerror(errno));
        return -1;
    }

    /* Capture output for logging */
    char line[512];
    while (fgets(line, sizeof(line), proc)) {
        /* Strip trailing newline */
        size_t len = strlen(line);
        if (len > 0 && line[len - 1] == '\n') line[len - 1] = '\0';
        if (line[0]) log_debug("openssl: %s", line);
    }

    int status = pclose(proc);
    if (status != 0) {
        log_error("openssl command failed (exit code %d)", status);
        return -1;
    }

    /* Restrict permissions on private key */
    chmod(key_path, 0600);
    chmod(cert_path, 0644);

    log_info("Self-signed certificate generated:");
    log_info("  Certificate: %s", cert_path);
    log_info("  Private key: %s", key_path);

    return 0;
}

#endif /* HAVE_GNUTLS */

/* ---------- load PEM files ---------- */

int ssl_load_files(const char *cert_path, const char *key_path)
{
    /* Free any previously loaded data */
    if (g_cert_pem) { free(g_cert_pem); g_cert_pem = NULL; }
    if (g_key_pem)  { free(g_key_pem);  g_key_pem  = NULL; }
    g_ready = false;

    g_cert_pem = read_file(cert_path);
    if (!g_cert_pem) {
        log_error("Failed to read certificate file: %s (%s)", cert_path, strerror(errno));
        return -1;
    }

    g_key_pem = read_file(key_path);
    if (!g_key_pem) {
        log_error("Failed to read private key file: %s (%s)", key_path, strerror(errno));
        free(g_cert_pem);
        g_cert_pem = NULL;
        return -1;
    }

    g_ready = true;
    log_info("SSL certificates loaded from files");
    log_debug("  Certificate: %s (%zu bytes)", cert_path, strlen(g_cert_pem));
    log_debug("  Private key: %s (%zu bytes)", key_path, strlen(g_key_pem));

    return 0;
}

/* ---------- public API ---------- */

int ssl_init(const vod_config_t *config)
{
    if (!config->ssl_enabled) {
        log_debug("SSL is disabled in configuration");
        return 0;
    }

    const char *cert_path = config->ssl_cert_file;
    const char *key_path  = config->ssl_key_file;

    bool have_cert = file_exists(cert_path);
    bool have_key  = file_exists(key_path);

    if (have_cert && have_key) {
        log_info("Found existing SSL certificate and key");
        return ssl_load_files(cert_path, key_path);
    }

    /* One exists but not the other -- that's a misconfiguration */
    if (have_cert != have_key) {
        log_warn("Only one of cert/key exists: cert=%s key=%s",
                 have_cert ? "yes" : "no", have_key ? "yes" : "no");
    }

    /* Try to auto-generate if configured */
    if (config->ssl_auto_self_signed) {
        log_info("Auto-generating self-signed certificate...");

        if (ssl_generate_self_signed(cert_path, key_path) != 0) {
            log_error("Failed to generate self-signed certificate");
            return -1;
        }

        return ssl_load_files(cert_path, key_path);
    }

    log_error("SSL certificates not found and auto_self_signed is disabled");
    log_error("  Expected cert: %s", cert_path);
    log_error("  Expected key:  %s", key_path);
    return -1;
}

void ssl_cleanup(void)
{
    if (g_cert_pem) {
        /* Zero out key material before freeing */
        memset(g_cert_pem, 0, strlen(g_cert_pem));
        free(g_cert_pem);
        g_cert_pem = NULL;
    }
    if (g_key_pem) {
        memset(g_key_pem, 0, strlen(g_key_pem));
        free(g_key_pem);
        g_key_pem = NULL;
    }
    g_ready = false;
    log_debug("SSL resources cleaned up");
}

bool ssl_is_ready(void)
{
    return g_ready;
}

const char *ssl_get_cert(void)
{
    return g_cert_pem;
}

const char *ssl_get_key(void)
{
    return g_key_pem;
}
