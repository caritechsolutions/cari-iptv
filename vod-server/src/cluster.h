#ifndef VOD_CLUSTER_H
#define VOD_CLUSTER_H

#include "config.h"
#include <stdbool.h>

/* Peer status info */
typedef struct {
    int         id;
    char        name[128];
    char        url[512];
    char        status[16];     /* online, offline, unknown */
    char        version[32];
    double      cpu_usage;
    double      memory_usage;
    long long   disk_total;
    long long   disk_free;
    int         content_count;
    int         active_jobs;
    char        last_seen[32];  /* ISO 8601 timestamp */
} peer_info_t;

/**
 * Initialize the cluster module.
 * Returns 0 on success, -1 on error.
 */
int cluster_init(const vod_config_t *config);

/**
 * Start the cluster health check thread.
 * Periodically pings all known peers.
 */
int cluster_start(void);

/**
 * Stop the cluster health check thread.
 */
void cluster_stop(void);

/**
 * Add a new peer to the cluster.
 * Returns the peer ID, or -1 on error.
 */
int cluster_add_peer(const char *name, const char *url, const char *api_key);

/**
 * Remove a peer from the cluster.
 */
int cluster_remove_peer(int peer_id);

/**
 * Get info about all peers.
 * Caller must free the returned array.
 * Returns count, or -1 on error.
 */
int cluster_get_peers(peer_info_t **out_peers);

/**
 * Get info about a single peer.
 * Returns 0 on success, -1 if not found.
 */
int cluster_get_peer(int peer_id, peer_info_t *out_peer);

/**
 * Ping a specific peer and update its status.
 * Returns 0 if online, -1 if unreachable.
 */
int cluster_ping_peer(int peer_id);

/**
 * Fetch content list from a remote peer.
 * Returns a JSON string that the caller must free.
 */
char *cluster_fetch_remote_content(int peer_id);

#endif /* VOD_CLUSTER_H */
