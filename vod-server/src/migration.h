#ifndef VOD_MIGRATION_H
#define VOD_MIGRATION_H

#include "config.h"

/* Migration direction */
typedef enum {
    MIGRATE_PULL = 0,   /* Download from remote peer to local */
    MIGRATE_PUSH = 1    /* Upload from local to remote peer */
} migrate_direction_t;

/**
 * Initialize the migration module.
 */
int migration_init(const vod_config_t *config);

/**
 * Start the migration processor thread.
 */
int migration_start(void);

/**
 * Stop the migration processor thread.
 */
void migration_stop(void);

/**
 * Create a new migration job.
 * Returns migration ID, or -1 on error.
 */
int migration_create(const char *content_id, const char *title,
                     const char *source_peer, const char *dest_peer,
                     int delete_source);

/**
 * Cancel a pending or active migration.
 */
int migration_cancel(int migration_id);

/**
 * Get the count of active migrations.
 */
int migration_active_count(void);

#endif /* VOD_MIGRATION_H */
