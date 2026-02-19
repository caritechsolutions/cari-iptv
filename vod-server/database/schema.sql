-- VOD Server SQLite Schema
-- Version: 1

PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;

-- Schema version tracking
CREATE TABLE IF NOT EXISTS schema_version (
    version     INTEGER PRIMARY KEY,
    applied_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    description TEXT
);

-- Transcode job queue
CREATE TABLE IF NOT EXISTS jobs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    content_id      TEXT NOT NULL,
    title           TEXT,
    source_path     TEXT NOT NULL,
    source_type     TEXT DEFAULT 'file',          -- file, url, peer
    profile         TEXT DEFAULT 'standard',
    status          TEXT DEFAULT 'pending',        -- pending, downloading, processing, packaging, complete, failed, paused, cancelled
    progress        REAL DEFAULT 0.0,             -- 0.0 to 100.0
    current_step    TEXT,                          -- e.g. "Encoding 720p", "Packaging HLS"
    error_msg       TEXT,
    priority        INTEGER DEFAULT 5,            -- 1=highest, 10=lowest
    callback_url    TEXT,                          -- Webhook URL on completion
    metadata        TEXT,                          -- JSON: extra info from middleware
    pid             INTEGER DEFAULT 0,            -- FFmpeg process ID (when running)
    bytes_processed INTEGER DEFAULT 0,
    bytes_total     INTEGER DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at      DATETIME,
    completed_at    DATETIME
);

CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs(status);
CREATE INDEX IF NOT EXISTS idx_jobs_content_id ON jobs(content_id);
CREATE INDEX IF NOT EXISTS idx_jobs_priority_created ON jobs(priority, created_at);

-- Content catalog (what's on disk and ready to serve)
CREATE TABLE IF NOT EXISTS content (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    content_id      TEXT UNIQUE NOT NULL,          -- External reference (movie/episode ID)
    title           TEXT,
    path            TEXT NOT NULL,                 -- Relative to library_path
    source_file     TEXT,                          -- Original source filename
    codec           TEXT,                          -- Primary codec used (h264, hevc, av1)
    renditions      TEXT,                          -- JSON: [{"resolution":"1080p","bitrate":5000,"path":"1080p/"}]
    duration        REAL DEFAULT 0,               -- Seconds
    size_bytes      INTEGER DEFAULT 0,            -- Total size of all files
    has_thumbnails  INTEGER DEFAULT 0,
    has_subtitles   INTEGER DEFAULT 0,
    subtitle_tracks TEXT,                          -- JSON: [{"lang":"en","path":"subs/en.vtt"}]
    hls_path        TEXT,                          -- Path to master.m3u8
    dash_path       TEXT,                          -- Path to manifest.mpd
    metadata        TEXT,                          -- JSON: extra metadata
    status          TEXT DEFAULT 'ready',          -- ready, processing, error
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_content_content_id ON content(content_id);
CREATE INDEX IF NOT EXISTS idx_content_status ON content(status);

-- Known peer servers in the cluster
CREATE TABLE IF NOT EXISTS peers (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL,
    url             TEXT UNIQUE NOT NULL,          -- https://vod2:8090
    api_key         TEXT,                          -- Auth key for this peer
    status          TEXT DEFAULT 'unknown',        -- online, offline, unknown
    version         TEXT,                          -- Remote server version
    last_seen       DATETIME,
    last_error      TEXT,
    disk_total      INTEGER DEFAULT 0,            -- Bytes
    disk_free       INTEGER DEFAULT 0,            -- Bytes
    disk_used       INTEGER DEFAULT 0,            -- Bytes
    cpu_usage       REAL DEFAULT 0,               -- Percentage 0-100
    memory_usage    REAL DEFAULT 0,               -- Percentage 0-100
    content_count   INTEGER DEFAULT 0,            -- Number of content items
    active_jobs     INTEGER DEFAULT 0,            -- Running transcode jobs
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_peers_status ON peers(status);

-- Content migration jobs between nodes
CREATE TABLE IF NOT EXISTS migrations (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    content_id          TEXT NOT NULL,
    title               TEXT,
    source_peer         TEXT NOT NULL,             -- 'local' or peer URL
    dest_peer           TEXT NOT NULL,             -- 'local' or peer URL
    status              TEXT DEFAULT 'pending',    -- pending, transferring, complete, failed, cancelled
    progress            REAL DEFAULT 0.0,
    bytes_total         INTEGER DEFAULT 0,
    bytes_transferred   INTEGER DEFAULT 0,
    error_msg           TEXT,
    delete_source       INTEGER DEFAULT 0,        -- Remove from source after transfer
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at          DATETIME,
    completed_at        DATETIME
);

CREATE INDEX IF NOT EXISTS idx_migrations_status ON migrations(status);

-- Server settings (runtime-configurable via API/GUI)
CREATE TABLE IF NOT EXISTS settings (
    key         TEXT PRIMARY KEY,
    value       TEXT,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- DRM encryption keys (ClearKey / CENC)
CREATE TABLE IF NOT EXISTS drm_keys (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    content_id  TEXT UNIQUE NOT NULL,            -- External content reference
    key_hex     TEXT NOT NULL,                   -- AES-128 key in hex (32 chars)
    kid_hex     TEXT NOT NULL,                   -- Key ID in hex (32 chars)
    scheme      INTEGER DEFAULT 1,              -- 1=CENC (AES-CTR), 2=CBCS (AES-CBC)
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_drm_keys_content ON drm_keys(content_id);

-- API keys for authentication
CREATE TABLE IF NOT EXISTS api_keys (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    key_hash    TEXT UNIQUE NOT NULL,              -- SHA-256 hash of the key
    permissions TEXT DEFAULT 'read',               -- read, write, admin
    is_active   INTEGER DEFAULT 1,
    last_used   DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    action      TEXT NOT NULL,
    entity_type TEXT,                              -- job, content, peer, migration, config
    entity_id   TEXT,
    details     TEXT,                              -- JSON
    ip_address  TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_audit_log_created ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_log_entity ON audit_log(entity_type, entity_id);

-- Insert initial schema version
INSERT OR IGNORE INTO schema_version (version, description) VALUES (1, 'Initial schema');
