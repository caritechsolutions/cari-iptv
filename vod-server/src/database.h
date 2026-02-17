#ifndef VOD_DATABASE_H
#define VOD_DATABASE_H

#include <sqlite3.h>
#include <stdbool.h>

/**
 * Initialize the database connection and create/migrate schema.
 * Returns 0 on success, -1 on error.
 */
int db_init(const char *db_path);

/**
 * Close the database connection.
 */
void db_close(void);

/**
 * Get the raw SQLite handle (for direct queries).
 */
sqlite3 *db_handle(void);

/**
 * Execute a SQL statement with no results (INSERT, UPDATE, DELETE).
 * Returns 0 on success, -1 on error.
 */
int db_execute(const char *sql, ...);

/**
 * Execute a SQL statement with a callback for each row.
 * Returns 0 on success, -1 on error.
 */
int db_query(const char *sql, int (*callback)(void*, int, char**, char**), void *userdata);

/**
 * Get the last inserted row ID.
 */
long long db_last_insert_id(void);

/**
 * Get the current schema version.
 */
int db_get_schema_version(void);

/**
 * Run schema migrations (embedded in code).
 * Returns 0 on success, -1 on error.
 */
int db_migrate(void);

/**
 * Begin a transaction.
 */
int db_begin(void);

/**
 * Commit a transaction.
 */
int db_commit(void);

/**
 * Rollback a transaction.
 */
int db_rollback(void);

#endif /* VOD_DATABASE_H */
