#include "version.h"
#include "config.h"
#include "logger.h"
#include "database.h"
#include "http_server.h"
#include "ssl.h"
#include "storage.h"
#include "job_processor.h"
#include "cluster.h"
#include "migration.h"
#include "transcoder.h"
#include "packager.h"
#include "drm.h"

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <signal.h>
#include <unistd.h>
#include <getopt.h>
#include <sys/stat.h>
#include <errno.h>

/* Global config */
static vod_config_t g_config;
static volatile int g_running = 1;
static const char  *g_config_path = "/etc/vod-server/vod-server.conf";

static void signal_handler(int sig)
{
    switch (sig) {
    case SIGINT:
    case SIGTERM:
        log_info("Received signal %d, shutting down...", sig);
        g_running = 0;
        break;
    case SIGHUP:
        log_info("Received SIGHUP, reloading configuration...");
        if (config_load(&g_config, g_config_path) == 0) {
            log_info("Configuration reloaded successfully");
        } else {
            log_error("Failed to reload configuration");
        }
        break;
    }
}

static void setup_signals(void)
{
    struct sigaction sa;
    memset(&sa, 0, sizeof(sa));
    sa.sa_handler = signal_handler;
    sigemptyset(&sa.sa_mask);

    sigaction(SIGINT,  &sa, NULL);
    sigaction(SIGTERM, &sa, NULL);
    sigaction(SIGHUP,  &sa, NULL);

    /* Ignore SIGPIPE (broken pipe from HTTP connections) */
    signal(SIGPIPE, SIG_IGN);
}

static int write_pidfile(const char *path)
{
    FILE *f = fopen(path, "w");
    if (!f) {
        log_error("Cannot write PID file: %s (%s)", path, strerror(errno));
        return -1;
    }
    fprintf(f, "%d\n", getpid());
    fclose(f);
    return 0;
}

static void remove_pidfile(const char *path)
{
    unlink(path);
}

static void print_version(void)
{
    printf("VOD Server %s (built %s %s)\n",
           VOD_SERVER_VERSION, VOD_SERVER_BUILD_DATE, VOD_SERVER_BUILD_TIME);
}

static void print_usage(const char *prog)
{
    printf("Usage: %s [OPTIONS]\n\n", prog);
    printf("CARI VOD Server - Standalone VOD transcoding and serving daemon\n\n");
    printf("Options:\n");
    printf("  -c, --config PATH   Config file path (default: %s)\n", g_config_path);
    printf("  -d, --daemon        Run as daemon (background)\n");
    printf("  -v, --version       Print version and exit\n");
    printf("  -h, --help          Print this help and exit\n");
    printf("  -t, --test          Test configuration and exit\n");
    printf("\n");
    printf("Signals:\n");
    printf("  SIGHUP    Reload configuration\n");
    printf("  SIGTERM   Graceful shutdown\n");
    printf("  SIGINT    Graceful shutdown (Ctrl+C)\n");
}

static int daemonize(void)
{
    pid_t pid = fork();
    if (pid < 0) {
        perror("fork");
        return -1;
    }
    if (pid > 0) {
        /* Parent exits */
        exit(0);
    }
    /* Child becomes session leader */
    setsid();

    /* Fork again to prevent terminal reattach */
    pid = fork();
    if (pid < 0) {
        perror("fork");
        return -1;
    }
    if (pid > 0) {
        exit(0);
    }

    /* Redirect stdio to /dev/null */
    if (!freopen("/dev/null", "r", stdin))  (void)0;
    if (!freopen("/dev/null", "w", stdout)) (void)0;
    if (!freopen("/dev/null", "w", stderr)) (void)0;

    /* Change working directory */
    if (chdir("/") < 0) {
        return -1;
    }

    /* Reset file mask */
    umask(0022);

    return 0;
}

int main(int argc, char *argv[])
{
    int daemon_mode = 0;
    int test_mode = 0;

    static struct option long_options[] = {
        { "config",  required_argument, NULL, 'c' },
        { "daemon",  no_argument,       NULL, 'd' },
        { "version", no_argument,       NULL, 'v' },
        { "help",    no_argument,       NULL, 'h' },
        { "test",    no_argument,       NULL, 't' },
        { NULL, 0, NULL, 0 }
    };

    int opt;
    while ((opt = getopt_long(argc, argv, "c:dvht", long_options, NULL)) != -1) {
        switch (opt) {
        case 'c':
            g_config_path = optarg;
            break;
        case 'd':
            daemon_mode = 1;
            break;
        case 'v':
            print_version();
            return 0;
        case 'h':
            print_usage(argv[0]);
            return 0;
        case 't':
            test_mode = 1;
            break;
        default:
            print_usage(argv[0]);
            return 1;
        }
    }

    /* Load configuration */
    if (config_load(&g_config, g_config_path) != 0) {
        fprintf(stderr, "Failed to load configuration from: %s\n", g_config_path);
        fprintf(stderr, "Using default configuration.\n");
        config_set_defaults(&g_config);
    }

    if (test_mode) {
        printf("Configuration test successful.\n");
        config_dump(&g_config);
        return 0;
    }

    /* Initialize logger (before daemonizing so we see early errors) */
    if (!daemon_mode) {
        /* In foreground mode, don't require the log directory to exist */
        logger_init(NULL, g_config.log_level);
    } else {
        logger_init(g_config.log_file, g_config.log_level);
    }

    print_version();
    log_info("Starting VOD Server %s", VOD_SERVER_VERSION);
    log_info("Config: %s", g_config_path);
    config_dump(&g_config);

    /* Daemonize if requested */
    if (daemon_mode) {
        log_info("Daemonizing...");
        if (daemonize() != 0) {
            log_error("Failed to daemonize");
            return 1;
        }
        /* Re-init logger after daemonizing */
        logger_init(g_config.log_file, g_config.log_level);
    }

    /* Set up signal handlers */
    setup_signals();

    /* Write PID file */
    write_pidfile(g_config.pid_file);

    /* Initialize storage */
    if (storage_init(&g_config) != 0) {
        log_error("Failed to initialize storage");
        goto cleanup;
    }

    /* Initialize database */
    if (db_init(g_config.database_path) != 0) {
        log_error("Failed to initialize database");
        goto cleanup;
    }

    /* Load profiles saved via API (overrides INI profiles if present) */
    if (config_load_db_profiles(&g_config) > 0) {
        log_info("Profiles loaded from database (overrides config file)");
    }

    /* Load DRM settings from DB (overrides INI if saved via GUI) */
    config_load_db_settings(&g_config);

    /* Initialize DRM module (creates drm_keys table) */
    if (drm_init() != 0) {
        log_warn("DRM module initialization failed (non-fatal)");
    }

    /* Initialize SSL if enabled */
    if (g_config.ssl_enabled) {
        if (ssl_init(&g_config) != 0) {
            log_error("Failed to initialize SSL");
            goto cleanup;
        }
    }

    /* Initialize transcoder */
    if (transcoder_init(&g_config) != 0) {
        log_error("Failed to initialize transcoder");
        goto cleanup;
    }

    /* Initialize packager */
    if (packager_init(&g_config) != 0) {
        log_warn("Packager initialization failed (MP4Box may not be installed)");
        /* Non-fatal: will use FFmpeg fallback */
    }

    /* Start HTTP server */
    if (http_server_start(&g_config) != 0) {
        log_error("Failed to start HTTP server");
        goto cleanup;
    }

    log_info("HTTP server listening on %s://%s:%d",
             g_config.ssl_enabled ? "https" : "http",
             g_config.bind_address, g_config.port);

    /* Start job processor */
    if (job_processor_init(&g_config) != 0) {
        log_error("Failed to initialize job processor");
        goto cleanup;
    }
    job_processor_start();

    /* Start cluster manager */
    if (cluster_init(&g_config) == 0) {
        cluster_start();
    }

    /* Start migration processor */
    if (migration_init(&g_config) == 0) {
        migration_start();
    }

    log_info("VOD Server is ready");

    /* Main loop */
    while (g_running) {
        sleep(1);
    }

    log_info("Shutting down...");

cleanup:
    /* Stop all subsystems in reverse order */
    migration_stop();
    cluster_stop();
    job_processor_stop();
    http_server_stop();
    ssl_cleanup();
    db_close();
    remove_pidfile(g_config.pid_file);
    logger_close();

    return 0;
}
