# Subtitle Integration Plan

## Overview
Add subtitle support across the platform with OpenSubtitles API as the primary source and FFmpeg embedded subtitle extraction as fallback. Subtitles are managed per-movie in the admin panel, stored as WebVTT files, and included in HLS playlists.

## Architecture

```
Movie Form (Admin)
  ├─ "Fetch Subtitles" → SubtitleService → OpenSubtitles API (by TMDB ID/IMDB ID)
  │                                           ↓
  │                            Download .srt → Convert to .vtt → Store locally
  │
  ├─ "Extract from Source" → VOD Server API → FFmpeg extracts embedded subs
  │                                           ↓
  │                            Auto-detected language → .vtt files in content dir
  │
  └─ "Upload Subtitle" → Direct .srt/.vtt upload → Convert & store
```

## Changes by Layer

### 1. Database Migration (`database/migrations/029_create_subtitle_tables.sql`)

**PHP side - `movie_subtitles` table:**
```sql
CREATE TABLE IF NOT EXISTS `movie_subtitles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `movie_id` INT UNSIGNED NOT NULL,
    `language_code` VARCHAR(10) NOT NULL,       -- ISO 639-1 (en, es, fr)
    `language_name` VARCHAR(100) NOT NULL,       -- English, Spanish, French
    `source` VARCHAR(30) DEFAULT 'upload',       -- upload, opensubtitles, extracted
    `external_id` VARCHAR(255) DEFAULT NULL,     -- OpenSubtitles file ID
    `file_path` VARCHAR(500) NOT NULL,           -- /uploads/vod/{movieId}/subtitles/en.vtt
    `format` VARCHAR(10) DEFAULT 'vtt',          -- vtt, srt
    `is_default` TINYINT(1) DEFAULT 0,
    `is_forced` TINYINT(1) DEFAULT 0,            -- For forced/SDH subtitles
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_movie_subtitles_movie` (`movie_id`),
    UNIQUE KEY `idx_movie_subtitles_lang` (`movie_id`, `language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

No FK constraint (matches existing pattern in movie_trailers/movie_artwork).

### 2. Settings - OpenSubtitles Integration

**Files to modify:**
- `src/Controllers/Admin/SettingsController.php` - Add `updateSubtitles()` and `testOpenSubtitles()` methods
- `templates/admin/settings/index.php` - Add OpenSubtitles card in the Integrations tab
- `public/admin/index.php` - Add routes for subtitle settings

**Settings stored (group: 'subtitles'):**
- `opensubtitles_api_key` - API key from opensubtitles.com
- `opensubtitles_username` - API username (required for downloads)
- `opensubtitles_password` - API password
- `auto_fetch_subtitles` - Auto-fetch when adding movies (0/1)
- `preferred_languages` - Comma-separated language codes (e.g., "en,es,fr")

**UI:** Add a new card in the Integrations tab (after YouTube), matching the existing pattern with API key input, Test button, and connection status badge.

### 3. SubtitleService.php (`src/Services/SubtitleService.php`)

New service with these methods:

```php
class SubtitleService {
    // CRUD
    getMovieSubtitles(int $movieId): array
    addSubtitle(int $movieId, array $data): int
    deleteSubtitle(int $subtitleId): bool
    setDefault(int $movieId, int $subtitleId): bool

    // OpenSubtitles API
    isConfigured(): bool
    testConnection(): bool
    searchSubtitles(int $movieId, ?string $tmdbId, ?string $imdbId, ?string $title): array
    downloadSubtitle(int $fileId, int $movieId, string $languageCode, string $languageName): array

    // FFmpeg extraction
    extractFromSource(string $sourcePath, int $movieId): array  // Extract embedded subs

    // File handling
    uploadSubtitle(array $file, int $movieId, string $languageCode, string $languageName): array
    convertSrtToVtt(string $srtPath, string $vttPath): bool

    // Internal: OpenSubtitles REST API calls
    private apiLogin(): ?string           // POST /api/v1/login → JWT token
    private apiRequest(string $method, string $endpoint, ?array $body): ?array
}
```

**OpenSubtitles API flow:**
1. Login → get JWT token (cache for 24h in settings)
2. Search → `GET /api/v1/subtitles?tmdb_id=X&languages=en,es`
3. Download → `POST /api/v1/download` with file_id → returns download link
4. Save → Download .srt, convert to .vtt, store in `/uploads/vod/{movieId}/subtitles/`

**FFmpeg extraction flow:**
1. Run `ffprobe` to detect subtitle streams and their languages
2. For each subtitle stream: `ffmpeg -i source -map 0:s:N -c:s webvtt output.vtt`
3. Store extracted .vtt files, create DB records

### 4. Movie Controller Updates (`src/Controllers/Admin/MovieController.php`)

Add AJAX endpoints:
- `POST /admin/movies/{id}/subtitles/upload` - Upload subtitle file
- `POST /admin/movies/{id}/subtitles/fetch` - Fetch from OpenSubtitles
- `POST /admin/movies/{id}/subtitles/extract` - Extract from source via FFmpeg
- `POST /admin/movies/{id}/subtitles/{sid}/delete` - Remove subtitle
- `POST /admin/movies/{id}/subtitles/{sid}/default` - Set as default

### 5. Movie Form Template (`templates/admin/movies/form.php`)

Add a "Subtitles" card (after the VOD Transcode card when editing), containing:
- Table listing current subtitles (language, source, default badge, delete button)
- "Fetch from OpenSubtitles" button (searches by TMDB ID, shows results in modal)
- "Extract from Source" button (only shown when movie has a stream_url or VOD content)
- "Upload Subtitle" button with file picker (.srt, .vtt) and language selector
- Language dropdown with common languages (en, es, fr, pt, de, it, nl, etc.)

### 6. VOD Server C Changes

**6a. Extend `transcoder.h` - subtitle_info struct:**
```c
#define MAX_SUBTITLE_TRACKS 32

typedef struct {
    int   stream_index;         /* FFmpeg stream index */
    char  language[16];         /* ISO 639 code */
    char  language_name[64];    /* Full name */
    char  codec_name[32];       /* subrip, ass, webvtt, dvd_subtitle, etc. */
    bool  is_forced;
    bool  is_text_based;        /* true for SRT/ASS/VTT, false for bitmap subs */
} subtitle_track_info_t;
```

Add to `media_info_t`:
```c
subtitle_track_info_t subtitle_tracks[MAX_SUBTITLE_TRACKS];
```

**6b. Extend `transcoder_probe()` in `transcoder.c`:**
When parsing subtitle streams, also extract:
- `tags.language` (ISO 639 code)
- `tags.title` (subtitle name, if any)
- `codec_name` (to determine if text-based → extractable)
- `disposition.forced` flag

**6c. Implement `transcoder_extract_subtitles()` in `transcoder.c`:**
Already declared in header but not implemented. This function:
1. Probes the source to get subtitle track info
2. For each text-based subtitle track, runs:
   `ffmpeg -i source -map 0:s:N -c:s webvtt {output_dir}/subtitles/{lang}.vtt`
3. Creates the `subtitles/` subdirectory in the output
4. Returns count of extracted tracks

**6d. Extend `config.h` - add subtitle settings:**
```c
/* [subtitles] */
bool         subtitles_enabled;
bool         subtitles_auto_extract;    /* Extract embedded subs during transcode */
```

**6e. Extend `config.c` - parse [subtitles] section from INI:**
```ini
[subtitles]
enabled = true
auto_extract = true
```

**6f. Extend `job_processor.c` - `handle_job_completed()`:**
After thumbnails but before storage move, add subtitle extraction step:
1. If `subtitles_enabled && subtitles_auto_extract && media_info.has_subtitles`:
2. Call `transcoder_extract_subtitles(source_path, output_dir)`
3. Scan `output_dir/subtitles/` for .vtt files
4. Build `subtitle_tracks` JSON: `[{"lang":"en","name":"English","path":"subtitles/en.vtt"}]`
5. Set `has_subtitles = 1` in content registration
6. Include subtitle_tracks JSON in content INSERT

**6g. Extend `packager.c` - `packager_run()`:**
After writing master.m3u8 stream variants, add subtitle media entries:
```
#EXT-X-MEDIA:TYPE=SUBTITLES,GROUP-ID="subs",NAME="English",DEFAULT=YES,AUTOSELECT=YES,LANGUAGE="en",URI="subtitles/en.vtt"
```
And add `SUBTITLES="subs"` to each `#EXT-X-STREAM-INF` line.

**6h. Add subtitle API endpoint in `api_routes.c`:**
- `POST /api/jobs/{id}/extract-subtitles` - Trigger subtitle extraction for an existing job/content
- Response includes extracted subtitle tracks

### 7. VodServerService.php Updates

Add method:
```php
public function extractSubtitles(int $serverId, int $jobId): array
```
Calls `POST /api/jobs/{jobId}/extract-subtitles` on the VOD server.

### 8. Route Registration (`public/admin/index.php`)

New routes:
```php
// Subtitle settings
$router->post('/admin/settings/subtitles', [SettingsController::class, 'updateSubtitles'], ['auth']);
$router->post('/admin/settings/test-opensubtitles', [SettingsController::class, 'testOpenSubtitles'], ['auth']);

// Movie subtitles
$router->post('/admin/movies/{id}/subtitles/upload', [MovieController::class, 'uploadSubtitle'], ['auth']);
$router->post('/admin/movies/{id}/subtitles/fetch', [MovieController::class, 'fetchSubtitles'], ['auth']);
$router->post('/admin/movies/{id}/subtitles/extract', [MovieController::class, 'extractSubtitles'], ['auth']);
$router->post('/admin/movies/{id}/subtitles/search', [MovieController::class, 'searchSubtitles'], ['auth']);
$router->post('/admin/movies/{id}/subtitles/{sid}/delete', [MovieController::class, 'deleteSubtitle'], ['auth']);
$router->post('/admin/movies/{id}/subtitles/{sid}/default', [MovieController::class, 'setDefaultSubtitle'], ['auth']);
```

### 9. Update Scripts

Update branch names in:
- `install.sh`
- `update.sh`
- `vod-server/scripts/install.sh`
- `vod-server/scripts/update.sh`

## Implementation Order

1. Database migration (029)
2. SubtitleService.php (core logic)
3. Settings integration (controller + template + routes)
4. Movie controller endpoints (AJAX handlers)
5. Movie form template (subtitle card UI)
6. VOD server C changes (config, transcoder, packager, job_processor, api_routes)
7. VodServerService.php proxy method
8. Update scripts (branch names)
9. Test & verify
