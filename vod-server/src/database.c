#include "database.h"
#include "logger.h"
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdarg.h>

static sqlite3 *g_db = NULL;

/* Embedded schema (version 1) */
static const char *SCHEMA_V1 =
    "PRAGMA journal_mode=WAL;\n"
    "PRAGMA foreign_keys=ON;\n"

    "CREATE TABLE IF NOT EXISTS schema_version ("
    "  version INTEGER PRIMARY KEY,"
    "  applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,"
    "  description TEXT"
    ");\n"

    "CREATE TABLE IF NOT EXISTS jobs ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  content_id TEXT NOT NULL,"
    "  title TEXT,"
    "  source_path TEXT NOT NULL,"
    "  source_type TEXT DEFAULT 'file',"
    "  profile TEXT DEFAULT 'standard',"
    "  status TEXT DEFAULT 'pending',"
    "  progress REAL DEFAULT 0.0,"
    "  current_step TEXT,"
    "  error_msg TEXT,"
    "  priority INTEGER DEFAULT 5,"
    "  callback_url TEXT,"
    "  metadata TEXT,"
    "  pid INTEGER DEFAULT 0,"
    "  bytes_processed INTEGER DEFAULT 0,"
    "  bytes_total INTEGER DEFAULT 0,"
    "  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,"
    "  started_at DATETIME,"
    "  completed_at DATETIME"
    ");\n"

    "CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status);\n"
    "CREATE INDEX IF NOT EXISTS idx_jobs_content_id ON jobs(content_id);\n"
    "CREATE INDEX IF NOT EXISTS idx_jobs_priority_created ON jobs(priority, created_at);\n"

    "CREATE TABLE IF NOT EXISTS content ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  content_id TEXT UNIQUE NOT NULL,"
    "  title TEXT,"
    "  path TEXT NOT NULL,"
    "  source_file TEXT,"
    "  codec TEXT,"
    "  renditions TEXT,"
    "  duration REAL DEFAULT 0,"
    "  size_bytes INTEGER DEFAULT 0,"
    "  has_thumbnails INTEGER DEFAULT 0,"
    "  has_subtitles INTEGER DEFAULT 0,"
    "  subtitle_tracks TEXT,"
    "  hls_path TEXT,"
    "  dash_path TEXT,"
    "  metadata TEXT,"
    "  status TEXT DEFAULT 'ready',"
    "  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,"
    "  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ");\n"

    "CREATE INDEX IF NOT EXISTS idx_content_content_id ON content(content_id);\n"
    "CREATE INDEX IF NOT EXISTS idx_content_status ON content(status);\n"

    "CREATE TABLE IF NOT EXISTS peers ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  name TEXT NOT NULL,"
    "  url TEXT UNIQUE NOT NULL,"
    "  api_key TEXT,"
    "  status TEXT DEFAULT 'unknown',"
    "  version TEXT,"
    "  last_seen DATETIME,"
    "  last_error TEXT,"
    "  disk_total INTEGER DEFAULT 0,"
    "  disk_free INTEGER DEFAULT 0,"
    "  disk_used INTEGER DEFAULT 0,"
    "  cpu_usage REAL DEFAULT 0,"
    "  memory_usage REAL DEFAULT 0,"
    "  content_count INTEGER DEFAULT 0,"
    "  active_jobs INTEGER DEFAULT 0,"
    "  created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ");\n"

    "CREATE INDEX IF NOT EXISTS idx_peers_status ON peers(status);\n"

    "CREATE TABLE IF NOT EXISTS migrations ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  content_id TEXT NOT NULL,"
    "  title TEXT,"
    "  source_peer TEXT NOT NULL,"
    "  dest_peer TEXT NOT NULL,"
    "  status TEXT DEFAULT 'pending',"
    "  progress REAL DEFAULT 0.0,"
    "  bytes_total INTEGER DEFAULT 0,"
    "  bytes_transferred INTEGER DEFAULT 0,"
    "  error_msg TEXT,"
    "  delete_source INTEGER DEFAULT 0,"
    "  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,"
    "  started_at DATETIME,"
    "  completed_at DATETIME"
    ");\n"

    "CREATE INDEX IF NOT EXISTS idx_migrations_status ON migrations(status);\n"

    "CREATE TABLE IF NOT EXISTS settings ("
    "  key TEXT PRIMARY KEY,"
    "  value TEXT,"
    "  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ");\n"

    "CREATE TABLE IF NOT EXISTS api_keys ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  name TEXT NOT NULL,"
    "  key_hash TEXT UNIQUE NOT NULL,"
    "  permissions TEXT DEFAULT 'read',"
    "  is_active INTEGER DEFAULT 1,"
    "  last_used DATETIME,"
    "  created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ");\n"

    "CREATE TABLE IF NOT EXISTS audit_log ("
    "  id INTEGER PRIMARY KEY AUTOINCREMENT,"
    "  action TEXT NOT NULL,"
    "  entity_type TEXT,"
    "  entity_id TEXT,"
    "  details TEXT,"
    "  ip_address TEXT,"
    "  created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ");\n"

    "CREATE INDEX IF NOT EXISTS idx_audit_log_created ON audit_log(created_at);\n"
    "CREATE INDEX IF NOT EXISTS idx_audit_log_entity ON audit_log(entity_type, entity_id);\n"
;

int db_init(const char *db_path)
{
    int rc = sqlite3_open(db_path, &g_db);
    if (rc != SQLITE_OK) {
        log_error("Cannot open database '%s': %s", db_path, sqlite3_errmsg(g_db));
        return -1;
    }

    /* Enable WAL mode and foreign keys */
    sqlite3_exec(g_db, "PRAGMA journal_mode=WAL;", NULL, NULL, NULL);
    sqlite3_exec(g_db, "PRAGMA foreign_keys=ON;", NULL, NULL, NULL);

    /* Set busy timeout to 5 seconds */
    sqlite3_busy_timeout(g_db, 5000);

    log_info("Database opened: %s", db_path);

    /* Run migrations */
    return db_migrate();
}

void db_close(void)
{
    if (g_db) {
        sqlite3_close(g_db);
        g_db = NULL;
        log_info("Database closed");
    }
}

sqlite3 *db_handle(void)
{
    return g_db;
}

int db_execute(const char *sql, ...)
{
    if (!g_db) return -1;

    char *errmsg = NULL;
    int rc = sqlite3_exec(g_db, sql, NULL, NULL, &errmsg);
    if (rc != SQLITE_OK) {
        log_error("SQL error: %s (query: %.200s)", errmsg, sql);
        sqlite3_free(errmsg);
        return -1;
    }
    return 0;
}

int db_query(const char *sql, int (*callback)(void*, int, char**, char**), void *userdata)
{
    if (!g_db) return -1;

    char *errmsg = NULL;
    int rc = sqlite3_exec(g_db, sql, callback, userdata, &errmsg);
    if (rc != SQLITE_OK) {
        log_error("SQL query error: %s (query: %.200s)", errmsg, sql);
        sqlite3_free(errmsg);
        return -1;
    }
    return 0;
}

long long db_last_insert_id(void)
{
    if (!g_db) return -1;
    return sqlite3_last_insert_rowid(g_db);
}

int db_get_schema_version(void)
{
    if (!g_db) return 0;

    /* Check if schema_version table exists */
    sqlite3_stmt *stmt;
    int rc = sqlite3_prepare_v2(g_db,
        "SELECT MAX(version) FROM schema_version", -1, &stmt, NULL);
    if (rc != SQLITE_OK) return 0;

    int version = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        version = sqlite3_column_int(stmt, 0);
    }
    sqlite3_finalize(stmt);
    return version;
}

int db_migrate(void)
{
    int current_version = db_get_schema_version();
    log_info("Database schema version: %d", current_version);

    /* Apply version 1 if needed */
    if (current_version < 1) {
        log_info("Applying schema version 1...");

        /* Execute schema in individual statements */
        char *errmsg = NULL;
        int rc = sqlite3_exec(g_db, SCHEMA_V1, NULL, NULL, &errmsg);
        if (rc != SQLITE_OK) {
            log_error("Schema migration failed: %s", errmsg);
            sqlite3_free(errmsg);
            return -1;
        }

        /* Record the migration */
        sqlite3_exec(g_db,
            "INSERT OR IGNORE INTO schema_version (version, description) "
            "VALUES (1, 'Initial schema')",
            NULL, NULL, NULL);

        log_info("Schema version 1 applied successfully");
    }

    /* Future migrations go here:
     * if (current_version < 2) { ... }
     */

    return 0;
}

int db_begin(void)
{
    return db_execute("BEGIN TRANSACTION");
}

int db_commit(void)
{
    return db_execute("COMMIT");
}

int db_rollback(void)
{
    return db_execute("ROLLBACK");
}
