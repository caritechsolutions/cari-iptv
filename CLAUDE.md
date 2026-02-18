# CLAUDE.md - AI Assistant Guide for CARI-IPTV

This document provides essential context for AI assistants working with the CARI-IPTV codebase.

## Project Overview

CARI-IPTV is a carrier-grade IPTV/OTT middleware platform designed for the Caribbean market. It provides live TV streaming, VOD, EPG, and subscription management capabilities.

**Current Version:** 1.0.0
**Current Phase:** Phase 3 (Channel Management) - In Progress
**PHP Version:** 8.1+ required

## Quick Start

```bash
# Development server
php -S localhost:8000 -t public

# Access admin panel
http://localhost:8000/admin
```

## Architecture

```
Browser → PHP Templates → Controllers → Services → Database (MySQL)
                              ↓
                         Middleware
```

**Pattern:** MVC-style with service layer (custom PHP framework, no external dependencies)

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8.x (Plain PHP) |
| Database | MySQL 8.x (InnoDB) |
| Frontend | HTML5, CSS3, Vanilla JS |
| Icons | Lucide Icons (CDN) |
| Session | File-based (future: Redis) |
| VOD Server | C (libmicrohttpd + SQLite + FFmpeg) — in `vod-server/` directory |

## Directory Structure

```
cari-iptv/
├── public/                 # Web root (document root for Nginx/Apache)
│   ├── index.php          # Main entry point
│   ├── admin/index.php    # Admin panel entry point
│   ├── assets/            # Static assets
│   └── uploads/           # Dynamic uploads (logos, avatars)
│
├── src/                   # Application source code
│   ├── Config/
│   │   ├── app.php        # App config (roles, pagination, security)
│   │   └── database.php   # Database connection config
│   │
│   ├── Core/              # Framework core classes
│   │   ├── Router.php     # URL routing with middleware support
│   │   ├── Database.php   # PDO singleton with query helpers
│   │   ├── Session.php    # Session management with CSRF
│   │   └── Response.php   # Response rendering (view, JSON, redirect)
│   │
│   ├── Services/          # Business logic layer
│   │   ├── AdminAuthService.php   # Authentication, permissions, roles
│   │   ├── ChannelService.php     # Channel CRUD operations
│   │   ├── MovieService.php       # Movie CRUD, TMDB import, trailers
│   │   ├── SettingsService.php    # Database KV store for settings
│   │   ├── EmailService.php       # Pure PHP SMTP (no PHPMailer)
│   │   ├── ImageService.php       # Image processing (resize, WebP conversion)
│   │   ├── AIService.php          # AI integration (Ollama, OpenAI, Anthropic, DALL-E 3)
│   │   ├── AdService.php          # Ad business logic, targeting engine, reporting
│   │   └── MetadataService.php    # TMDB, Fanart.tv, YouTube API integration
│   │
│   ├── Controllers/Admin/  # Admin panel controllers
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── AdminUserController.php
│   │   ├── ChannelController.php
│   │   ├── MovieController.php
│   │   ├── AdController.php
│   │   ├── ProfileController.php
│   │   └── SettingsController.php
│   │
│   └── Middleware/
│       └── AdminAuthMiddleware.php
│
├── templates/             # PHP view templates
│   ├── layouts/admin.php  # Main admin layout (dark theme)
│   └── admin/             # Admin page templates
│
├── database/
│   └── migrations/        # Versioned SQL migrations (001-005)
│
├── vod-server/            # Standalone VOD transcoding server (C)
│   ├── src/               # C source files (main.c, api_routes.c, transcoder.c, etc.)
│   ├── include/           # Third-party headers (cJSON, inih)
│   ├── config/            # Default config (vod-server.conf with transcode profiles)
│   ├── database/          # SQLite schema
│   ├── www/               # Web GUI (HTML/CSS/JS)
│   ├── scripts/           # Systemd service, install scripts
│   └── CMakeLists.txt     # Build system
│
└── storage/               # Logs, cache, sessions
```

## Key Files to Know

| File | Purpose |
|------|---------|
| `public/admin/index.php` | Admin router with all routes defined |
| `src/Core/Database.php` | Database singleton - use `Database::getInstance()` |
| `src/Core/Session.php` | Session/CSRF management - `Session::csrf()`, `Session::validateCsrf()` |
| `src/Config/app.php` | App configuration (roles, security settings) |
| `templates/layouts/admin.php` | Main admin layout template |

## Code Conventions

### PHP Standards
- **Namespace:** `CariIPTV\` root namespace with PSR-4 autoloading
- **Classes:** `PascalCase` (e.g., `ChannelService`)
- **Methods:** `camelCase` (e.g., `getChannels()`)
- **Properties:** `$camelCase` with type hints
- **Constants:** `UPPERCASE_SNAKE_CASE`

### Design Patterns Used
1. **Singleton** - Database class
2. **Service Layer** - Business logic in Services/
3. **MVC** - Controllers orchestrate, Services contain logic
4. **Middleware** - Auth checks before controller actions

### Image Processing (WebP Conversion)

All images should be processed through `ImageService` for WebP conversion and optimization:

```php
use CariIPTV\Services\ImageService;

$imageService = new ImageService();

// Process image from URL (downloads, resizes, converts to WebP)
$result = $imageService->processFromUrl(
    $url,        // Remote image URL
    'vod',       // Context: 'vod', 'channel', 'avatar', 'logo'
    $entityId,   // Movie/channel ID
    'poster'     // Type: 'poster', 'backdrop', 'logo', etc.
);

// Returns: ['success' => true, 'variants' => ['poster' => '/uploads/vod/123/poster_poster.webp']]
```

**Predefined sizes by context:**
- `channel`: thumb (64x64), medium (200x200), large (400x400), landscape (500x296)
- `vod`: thumb (150x225), poster (342x513), backdrop (780x439)
- `avatar`: thumb (64x64), medium (200x200)
- `logo`: small (120x60), medium (200x100)
- `ad`: banner_large (728x90), banner_medium (468x60), banner_leaderboard (970x250), banner_square (300x250), full (1920x1080)

**IMPORTANT:** When saving movies or channels with remote image URLs (from TMDB, Fanart.tv, etc.), always call `processImages()` to download and convert to local WebP files. This improves performance and reduces external dependencies.

### Security Patterns (ALWAYS FOLLOW)

```php
// CSRF Protection - Always on forms
Session::csrf()                    // Generate token
Session::validateCsrf($token)      // Validate on POST

// SQL Injection Prevention - Always use prepared statements
$this->db->fetch("SELECT * FROM channels WHERE id = ?", [$id]);

// Password Security
password_hash($password, PASSWORD_BCRYPT)  // Store
password_verify($input, $hash)              // Verify

// HTML Output - Always escape
htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
```

## Database

### Connection
```php
use CariIPTV\Core\Database;
$db = Database::getInstance();

// Query methods
$db->fetch($sql, $params);      // Single row
$db->fetchAll($sql, $params);   // All rows
$db->execute($sql, $params);    // INSERT/UPDATE/DELETE
$db->lastInsertId();            // Get last insert ID
```

### Key Tables

| Table | Purpose |
|-------|---------|
| `admin_users` | Admin accounts with roles |
| `admin_permissions` | Granular permissions |
| `channels` | TV channels with stream URLs |
| `movies` | Movie content with metadata |
| `movie_trailers` | YouTube trailer links for movies |
| `movie_artwork` | Fanart.tv artwork (posters, backdrops, logos) |
| `movie_cast` | Cast and crew from TMDB |
| `categories` | Channel/VOD categories (type: live, vod, series) |
| `series` | TV show content with metadata (slug, year, synopsis) |
| `settings` | Key-value configuration store (grouped by feature) |
| `app_layouts` | Visual content templates per platform |
| `app_layout_sections` | Ordered sections within a layout |
| `app_layout_items` | Content items within sections |
| `app_pages` | App screens per platform (linked to layouts) |
| `app_navigation` | Navigation menus per platform+position |
| `app_navigation_items` | Menu items linking to pages/URLs |
| `ad_campaigns` | Advertising campaigns with scheduling and budgets |
| `ad_creatives` | Ad content (text, banner, video) per campaign |
| `ad_zones` | Pre-defined ad placement locations |
| `ad_placements` | Links creatives to zones with targeting |
| `ad_targeting_rules` | Flexible targeting rules per placement |
| `ad_impressions` | Ad impression tracking |
| `ad_events` | Ad click/completion/skip event tracking |

### Admin Roles (Hierarchy)
1. `viewer` (level 1) - Read-only access
2. `support` (level 2) - Basic support actions
3. `manager` (level 3) - Content management
4. `admin` (level 4) - Full admin access
5. `super_admin` (level 5) - Complete system access

## Routing

Routes are defined in `public/admin/index.php`. Pattern:

```php
// Route definition
$router->get('/admin/channels', [ChannelController::class, 'index'], ['auth']);
$router->post('/admin/channels/store', [ChannelController::class, 'store'], ['auth']);

// Route with parameter
$router->get('/admin/channels/{id}/edit', [ChannelController::class, 'edit'], ['auth']);
```

### Key Admin Routes

| Route | Controller Method | Purpose |
|-------|-------------------|---------|
| `GET /admin/` | `DashboardController@index` | Dashboard |
| `GET /admin/channels` | `ChannelController@index` | Channel list |
| `GET /admin/channels/create` | `ChannelController@create` | New channel form |
| `POST /admin/channels/store` | `ChannelController@store` | Save new channel |
| `GET /admin/channels/{id}/edit` | `ChannelController@edit` | Edit channel form |
| `POST /admin/channels/{id}/update` | `ChannelController@update` | Update channel |
| `POST /admin/channels/{id}/delete` | `ChannelController@delete` | Delete channel |
| `GET /admin/admins` | `AdminUserController@index` | Admin user list |
| `GET /admin/settings` | `SettingsController@index` | System settings |

## Templates

### Layout Structure
Templates use PHP with `extract($data)` for variable injection:

```php
// In controller
Response::view('admin/channels/index', [
    'channels' => $channels,
    'title' => 'Channels'
]);

// In template - variables available directly
<h1><?= htmlspecialchars($title) ?></h1>
```

### Admin Layout Features
- Dark theme with custom CSS variables
- Fixed sidebar navigation (260px)
- Top header with user dropdown
- Toast notification support
- Chart.js integration

### Color Scheme
```css
--primary: #6366f1;      /* Indigo */
--success: #22c55e;      /* Green */
--warning: #f59e0b;      /* Amber */
--danger: #ef4444;       /* Red */
--bg-dark: #0f172a;      /* Background */
--card-bg: #1e293b;      /* Cards */
```

## Common Development Tasks

### Adding a New Admin Page

1. **Create Controller** (`src/Controllers/Admin/NewController.php`):
```php
namespace CariIPTV\Controllers\Admin;

use CariIPTV\Core\Response;

class NewController
{
    public function index(): void
    {
        Response::view('admin/new/index', ['title' => 'New Page']);
    }
}
```

2. **Add Route** (`public/admin/index.php`):
```php
$router->get('/admin/new-page', [NewController::class, 'index'], ['auth']);
```

3. **Create Template** (`templates/admin/new/index.php`):
```php
<?php $pageTitle = 'New Page'; ?>
<!-- Page content -->
```

### Adding a Database Migration

Create file in `database/migrations/` with incremented number:
```sql
-- 006_add_new_feature.sql
SET NAMES utf8mb4;

ALTER TABLE channels ADD COLUMN new_field VARCHAR(255) DEFAULT NULL;

CREATE INDEX idx_channels_new_field ON channels(new_field);
```

**IMPORTANT — MySQL Compatibility for ALTER TABLE:**

MySQL 8.x does **NOT** support `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` — that syntax is MariaDB-only. Using it will cause migration failures with `ERROR 1064 (42000)`.

For idempotent column additions (safe to re-run), use the `INFORMATION_SCHEMA` + prepared statement pattern:

```sql
-- Idempotent ADD COLUMN (MySQL 8.x compatible)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'your_table'
    AND COLUMN_NAME = 'your_column');
SET @s = IF(@col_exists = 0,
    'ALTER TABLE `your_table` ADD COLUMN `your_column` VARCHAR(50) DEFAULT ''value'' AFTER `existing_column`',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

For idempotent index additions:

```sql
-- Idempotent ADD INDEX (MySQL 8.x compatible)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'your_table'
    AND INDEX_NAME = 'idx_name');
SET @s = IF(@idx_exists = 0,
    'ALTER TABLE `your_table` ADD INDEX `idx_name` (`your_column`)',
    'SELECT 1');
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

**Rule of thumb:** If the migration only adds new tables, `CREATE TABLE IF NOT EXISTS` works fine in MySQL. But for `ALTER TABLE` operations (adding columns, indexes), always use the prepared statement pattern above. Never use `ADD COLUMN IF NOT EXISTS` or `DROP COLUMN IF EXISTS` — these are MariaDB extensions.

### Creating a New Service

```php
namespace CariIPTV\Services;

use CariIPTV\Core\Database;

class NewService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getData(): array
    {
        return $this->db->fetchAll("SELECT * FROM table_name");
    }
}
```

## Testing

Currently manual testing only:
1. Test in browser after changes
2. Check PHP error log: `storage/logs/php-error.log`
3. Verify database operations work
4. Test edge cases (empty states, validation errors)

## Deployment

- **Directory:** `/var/www/cari-iptv`
- **Web root:** `public/` directory
- **Permissions:** `storage/` and `public/uploads/` need write access (0775)
- **Config:** Environment variables in `.env` file

### Install & Update Scripts

The platform uses bash scripts for installation and updates. **IMPORTANT: After making changes, update the branch name in these scripts before pushing.**

**Files to update:**
- `install.sh` - Line ~1140: `BRANCH="your-branch-name"`
- `update.sh` - Line ~19: `BRANCH="your-branch-name"`

**Running updates on test/production:**
```bash
# Update command (use the current branch name)
curl -sSL "https://raw.githubusercontent.com/caritechsolutions/cari-iptv/BRANCH_NAME/update.sh?$(date +%s)" | sudo bash

# Example with specific branch:
curl -sSL "https://raw.githubusercontent.com/caritechsolutions/cari-iptv/claude/add-movies-menu-OxBkb/update.sh?$(date +%s)" | sudo bash

# Fresh install command:
curl -sSL "https://raw.githubusercontent.com/caritechsolutions/cari-iptv/BRANCH_NAME/install.sh?$(date +%s)" | sudo bash
```

**What the update script does:**
1. Creates backup (if --backup flag used)
2. Enables maintenance mode
3. Downloads latest code from the specified branch
4. Copies files: `src/`, `public/`, `templates/`, `database/migrations/`
5. Runs pending database migrations (tracked in `_migrations` table)
6. Fixes file permissions
7. Installs/updates Ollama for AI features
8. Clears cache and restarts PHP-FPM/Nginx
9. Disables maintenance mode

**Important notes:**
- The `$(date +%s)` cache-buster ensures you get the latest script
- Migrations are idempotent - they track which have been run
- The script requires root/sudo access
- Always test on development/staging first

## Important Guidelines for AI Assistants

### DO
- Always use prepared statements for SQL queries
- Include CSRF tokens on all forms
- Escape HTML output with `htmlspecialchars()`
- Follow existing code patterns and naming conventions
- Create database migrations for schema changes
- Test changes manually in the browser
- Keep controllers thin, put logic in services

### DON'T
- Use raw SQL queries without parameter binding
- Skip CSRF validation on POST requests
- Output unescaped user data
- Add external PHP dependencies (pure PHP approach)
- Create new folders without following the structure
- Skip the middleware for authenticated routes

### When Making Changes
1. Read existing similar code first
2. Follow established patterns
3. Use the service layer for business logic
4. Add proper validation and error handling
5. Test the changes thoroughly

## App Layout System (Pages, Navigation & Layout Builder)

The App Layout system controls what end-users see in the IPTV app. It has three layers:

```
Navigation (menus) → Pages (screens) → Layouts (visual content)
                                            ↓
                                      Sections (rows/grids)
                                            ↓
                                      Content Items (movies, series, channels)
```

### How It All Connects

1. **Navigation** menus contain items that link to **Pages**
2. **Pages** are app screens (Home, Movies, Live TV, etc.) — some link to a **Layout**
3. **Layouts** contain ordered **Sections** (hero slideshow, content rows, etc.)
4. **Sections** contain **Content Items** (movies, series, channels, or custom images)

Each layer is platform-specific (`web`, `mobile`, `tv`, `stb`) so each platform can have its own navigation style, pages, and layouts.

### Key Files

| File | Purpose |
|------|---------|
| `src/Controllers/Admin/AppLayoutController.php` | All AJAX endpoints (36 routes) |
| `src/Services/AppLayoutService.php` | Business logic (30+ methods) |
| `templates/admin/app-layout/index.php` | Layout listing page |
| `templates/admin/app-layout/builder.php` | Visual layout builder |
| `templates/admin/app-layout/pages.php` | Pages & Navigation management |
| `database/migrations/010_create_app_layout_tables.sql` | Layouts, sections, items tables |
| `database/migrations/011_create_app_pages_navigation.sql` | Pages, navigation tables + seed data |

### Database Tables

#### `app_layouts` — Visual content templates
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `name` | VARCHAR(255) | Layout name |
| `platform` | ENUM(web,mobile,tv,stb) | Target platform |
| `status` | ENUM(draft,published,archived) | Lifecycle state |
| `is_default` | TINYINT | Active default for platform (1 per platform) |
| `schedule_start/end` | DATETIME | Optional scheduled activation |

#### `app_layout_sections` — Rows within a layout
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `layout_id` | INT FK→app_layouts | Parent layout (CASCADE delete) |
| `section_type` | VARCHAR(50) | Type key (see section types below) |
| `title` | VARCHAR(255) | Display heading |
| `settings` | JSON | Type-specific configuration |
| `sort_order` | INT | Display order |
| `is_active` | TINYINT | Toggle |

#### `app_layout_items` — Content within a section
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `section_id` | INT FK→app_layout_sections | Parent section (CASCADE delete) |
| `content_type` | ENUM(movie,series,channel,category,custom) | What type of content |
| `content_id` | INT | FK to movies/series/channels/categories (NULL for custom) |
| `settings` | JSON | For custom items: `{image_url, title, link_url}` |
| `sort_order` | INT | Display order |

#### `app_pages` — App screens
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `name` | VARCHAR(255) | Display name (e.g. "Movies") |
| `slug` | VARCHAR(100) | URL path (e.g. "movies") |
| `page_type` | ENUM(11 types) | See page types below |
| `platform` | ENUM(web,mobile,tv,stb) | Target platform |
| `layout_id` | INT FK→app_layouts | Linked layout (SET NULL on delete) |
| `icon` | VARCHAR(50) | Lucide icon class (e.g. `lucide-film`) |
| `is_system` | TINYINT | System pages cannot be deleted |
| `sort_order` | INT | Display order |

#### `app_navigation` — Navigation menus
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `platform` | ENUM(web,mobile,tv,stb) | Target platform |
| `position` | ENUM(main,footer,sidebar,top) | Menu position |
| `settings` | JSON | Style config: `{style, show_icons, show_labels}` |

Unique constraint on `(platform, position)`.

#### `app_navigation_items` — Menu items
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `navigation_id` | INT FK→app_navigation | Parent menu (CASCADE delete) |
| `page_id` | INT FK→app_pages | Target page (when target=page) |
| `label` | VARCHAR(100) | Display text |
| `icon` | VARCHAR(50) | Lucide icon class |
| `target` | ENUM(page,url,deeplink) | Link type |
| `url` | VARCHAR(500) | For url/deeplink targets |
| `sort_order` | INT | Display order |

### Page Types

Defined in `AppLayoutService::getPageTypes()`:

| Type | Name | has_layout | Description |
|------|------|-----------|-------------|
| `home` | Home | YES | Main landing page |
| `movies` | Movies | YES | Movie browsing & listing |
| `series` | TV Shows | YES | TV series browsing |
| `live_tv` | Live TV | YES | Live channel guide & player |
| `categories` | Categories | YES | Browse by genre/category |
| `custom` | Custom Page | YES | User-defined page |
| `search` | Search | NO | Content search |
| `watchlist` | My List | NO | User watchlist/favourites |
| `settings` | Settings | NO | App settings & preferences |
| `player` | Player | NO | Media player page |
| `details` | Details | NO | Content detail view |

Pages with `has_layout: true` can be linked to a layout via `layout_id`. Pages with `has_layout: false` are standalone screens that need their own implementation.

### Section Types (10 types)

Defined in `AppLayoutService::getSectionTypes()`:

| Type Key | Name | Category | Supports Items | Max/Layout | Description |
|----------|------|----------|---------------|------------|-------------|
| `hero_slideshow` | Hero Slideshow | featured | YES | 1 | Full-width billboard with auto-rotation |
| `content_row` | Content Row | content | YES | 20 | Horizontal scrollable rail of cards |
| `continue_watching` | Continue Watching | personalized | NO (auto) | 1 | User's resume-watching row |
| `live_now` | Live Now | live | NO (auto/EPG) | 2 | Currently airing programmes |
| `epg_schedule` | TV Guide | live | NO (auto/EPG) | 1 | Mini programme guide grid |
| `channel_grid` | Channel Grid | live | YES | 3 | Featured channels grid |
| `category_grid` | Category Grid | navigation | NO (auto) | 2 | Browse-by-genre grid |
| `banner` | Promo Banner | promotional | NO (settings) | 5 | Promotional image with link |
| `spotlight` | Spotlight | featured | YES | 3 | Single featured item with details |
| `text_divider` | Section Divider | utility | NO | 10 | Heading text or separator |

**Sections that accept manual content items:** `hero_slideshow`, `content_row`, `channel_grid`, `spotlight`

**Auto-populated sections:** `continue_watching` (per user), `live_now` (from EPG), `epg_schedule` (from EPG), `category_grid` (from categories table)

### Section Settings (defaults)

Each section type has configurable settings stored as JSON. Key ones:

**hero_slideshow**: `auto_rotate` (bool), `interval` (seconds), `height` (small/medium/large), `show_play_button`, `show_info_button`

**content_row**: `source` (curated/latest/popular/top_rated/featured/category), `content_type` (movie/series/mixed), `card_style` (poster/backdrop/square), `max_items`, `category_id`, `sort_by`

**channel_grid**: `source` (curated/popular/category), `columns`, `max_items`, `show_now_playing`, `category_id`

**banner**: `image_url`, `link_url`, `link_type` (url/movie/series/channel), `aspect_ratio` (21:9/16:9/3:1)

### Content Items — 3 Ways to Add

1. **Local Library**: Search existing movies, series, channels, categories via `GET /admin/app-layout/search-content?type=movie&q=batman`
2. **TMDB Import**: Search TMDB via `GET /admin/app-layout/search-tmdb?q=batman&type=movie`, then import via `POST /admin/app-layout/import-tmdb-item` — creates a local record with `status=draft, source=tmdb`
3. **Custom Image Upload**: Upload via `POST /admin/app-layout/upload-item-image` — processed through `ImageService` with context `layout`, creates a custom item with `{image_url, title, link_url}`

### Navigation Styles

Each platform has a default navigation style:
- **Web**: `sidebar` — vertical side menu
- **Mobile**: `bottom_tab` — bottom tab bar (max 5 items)
- **TV**: `top_bar` — horizontal top menu
- **STB**: `sidebar` — vertical side menu

Settings JSON: `{"style": "bottom_tab", "show_icons": true, "show_labels": true, "max_items": 5}`

### API Routes Summary (36 routes)

All routes require auth middleware. All POST endpoints are AJAX (return JSON).

```
# Layout CRUD
GET  /admin/app-layout                                → index listing
POST /admin/app-layout/store                           → create layout
GET  /admin/app-layout/{id}/builder                    → visual builder
POST /admin/app-layout/{id}/update                     → update layout
POST /admin/app-layout/{id}/delete                     → delete layout
POST /admin/app-layout/{id}/duplicate                  → deep-copy layout
POST /admin/app-layout/{id}/publish                    → publish + set default

# Sections
POST /admin/app-layout/{id}/sections/add               → add section
POST /admin/app-layout/{id}/sections/reorder            → reorder sections
POST /admin/app-layout/{id}/sections/{sid}/update       → update section
POST /admin/app-layout/{id}/sections/{sid}/delete       → delete section

# Items
POST /admin/app-layout/{id}/sections/{sid}/items/add    → add item
POST /admin/app-layout/{id}/sections/{sid}/items/reorder → reorder items
POST /admin/app-layout/{id}/sections/{sid}/items/{iid}/remove → remove item

# Content search & import
GET  /admin/app-layout/search-content                  → search local library
GET  /admin/app-layout/search-tmdb                     → search TMDB
POST /admin/app-layout/import-tmdb-item                → import from TMDB
POST /admin/app-layout/upload-item-image               → upload custom image

# Pages
GET  /admin/app-layout/pages                           → pages & nav management
POST /admin/app-layout/pages/store                     → create page
POST /admin/app-layout/pages/reorder                   → reorder pages
POST /admin/app-layout/pages/{id}/update               → update page
POST /admin/app-layout/pages/{id}/delete               → delete page

# Navigation
POST /admin/app-layout/navigation/save                 → upsert nav menu
POST /admin/app-layout/navigation/items/add            → add nav item
POST /admin/app-layout/navigation/items/reorder        → reorder items
POST /admin/app-layout/navigation/items/{id}/update    → update item
POST /admin/app-layout/navigation/items/{id}/remove    → remove item
```

### Building a Player / Frontend App

To render the app for an end-user, a player/frontend needs to:

1. **Get navigation** for the platform — query `app_navigation` + `app_navigation_items` joined with `app_pages` to build the menu
2. **Get pages** for the platform — query `app_pages` to know what screens exist and their page_type
3. **For each page with a layout** — query `app_layouts` → `app_layout_sections` → `app_layout_items`, resolve items via their `content_type` + `content_id`
4. **Render sections** based on `section_type` and `settings`:
   - `hero_slideshow`: Full-width carousel of items
   - `content_row`: Horizontal scrollable cards with `card_style` (poster/backdrop/square)
   - `continue_watching`: Query user's watch history, show progress bars
   - `live_now`: Query EPG for current programmes
   - `channel_grid`: Grid of channel logos/cards
   - `category_grid`: Grid of genre tiles
   - `banner`: Single promotional image
   - `spotlight`: Featured single item
   - `text_divider`: Heading or separator
5. **For pages without layouts** (search, watchlist, settings, player, details): Build standalone UI

### Content Caching & Versioning

The player uses a manifest-based cache invalidation system to detect admin changes at runtime.

**Architecture:**
```
Admin makes change → DB updated → Manifest version hash changes
                                        ↓
Player polls manifest every 30s → Detects version mismatch
                                        ↓
                              bustAllCaches() → _v=timestamp appended to API calls
                                        ↓
                              CariRouter.refresh() → Re-renders current page
```

**Key files:**
| File | Purpose |
|------|---------|
| `src/Services/ContentApiService.php:getManifest()` | Generates version hashes from DB state |
| `src/Controllers/Api/BaseApiController.php` | Sets `Cache-Control: max-age=30` on API responses |
| `public/assets/js/player/api.js` | `bustAllCaches()` adds `_v=timestamp` to bypass browser cache |
| `public/assets/js/player/app.js` | `checkForUpdates()` polls manifest, `beforeEach` busts cache on navigation |

**How versioning works:**
- Each content type (movies, series, channels, categories, layouts, navigation) has a version hash
- Version = `md5(count + ':' + max_updated_at)` — changes when content is added, removed, or updated
- Movie/series versions also include cast table counts and timestamps
- Player compares stored baseline against fresh manifest to detect changes

**Cache layers:**
1. **Browser HTTP cache**: `max-age=30` on API responses (30 seconds)
2. **SPA navigation cache bust**: `CariAPI.bustAllCaches()` runs on every route change via `beforeEach` hook, appending `_v=timestamp` to bypass browser cache
3. **Manifest polling**: Every 30 seconds, detects backend changes and triggers page refresh if not watching video

**IMPORTANT for future development:**
- When adding new content tables, include them in `getManifest()` version hashes
- Always use `MovieService::importFromTmdb()` or `SeriesService::importFromTmdb()` for TMDB imports — never direct DB inserts — to ensure cast, categories, and images are saved
- The TMDB API key is stored in settings with group `'metadata'`, key `'tmdb_api_key'`
- Cast data (actors, directors, creators) is saved to `movie_cast` / `series_cast` tables during import

### Icons

Lucide icon font is hosted locally at `public/assets/fonts/lucide/`. The CSS uses class prefix `lucide-` (e.g., `<i class="lucide-film"></i>`). The font files (woff2, woff, ttf) are in the same directory. Loaded via `<link rel="stylesheet" href="/assets/fonts/lucide/lucide.css">` in the admin layout.

**Important:** The `[data-icon]` CSS selector in lucide.css conflicts with `data-icon` HTML attributes — use `data-value` instead when storing icon names on elements.

## Advertising System

The advertising system supports 4 ad types with flexible targeting, scheduling, and performance tracking.

### Architecture

```
Campaign → Creatives → Placements → Zones
                          ↓
                    Targeting Rules
                          ↓
              (package, channel, category,
               content_type, platform, geo, schedule)
```

**Flow:** Admin creates a Campaign, adds Creatives (the actual ads), then creates Placements that link Creatives to Zones (where ads appear). Each Placement can have Targeting Rules that control who sees the ad.

### Ad Types

| Type | Key | Description | Fields |
|------|-----|-------------|--------|
| Text Scroller | `text_scroller` | Scrolling text ticker overlay | scroll_text, scroll_speed, text_color, bg_color, bg_opacity |
| Banner Image | `banner` | Static/animated image overlay | image_url, dimensions, position, click_url |
| Pre-Roll Video | `pre_roll` | Video ad before content | video_url or vast_tag_url, duration, skip_after |
| Mid-Roll Video | `mid_roll` | Video ad during content | video_url or vast_tag_url, midroll_offset_type/value |

### Database Tables

#### `ad_campaigns` — Campaign container
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `name` | VARCHAR(255) | Campaign name |
| `advertiser` | VARCHAR(255) | Client/advertiser name |
| `status` | ENUM(draft,active,paused,completed,archived) | Lifecycle state |
| `priority` | INT 1-10 | 1=highest priority |
| `start_date/end_date` | DATETIME | Schedule window |
| `daily_budget/total_budget` | DECIMAL | Spend limits |
| `daily_impressions_cap` | INT | Max impressions per day |
| `total_impressions_cap` | INT | Max total impressions |
| `frequency_cap` | INT | Max times per user per day |
| `total_impressions/clicks/spend` | Counters | Running totals |

#### `ad_creatives` — Ad content
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `campaign_id` | INT FK→ad_campaigns | Parent campaign (CASCADE) |
| `type` | ENUM(text_scroller,banner,pre_roll,mid_roll) | Ad format |
| `scroll_text/speed/colors` | Various | Text scroller config |
| `image_url/width/height/position` | Various | Banner config |
| `video_url/vast_tag_url/duration/skip_after` | Various | Video config |
| `midroll_offset_type/value` | Various | Mid-roll insertion point |
| `click_url/click_target` | VARCHAR | Click-through destination |
| `weight` | INT | Rotation weight within campaign |

#### `ad_zones` — Pre-defined placement locations
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `slug` | VARCHAR(100) UNIQUE | API identifier (e.g. `preroll-vod`) |
| `zone_type` | ENUM | Matches creative types |
| `default_settings` | JSON | Zone-specific config |

**Default zones seeded:** live-text-scroller, vod-text-scroller, live-banner, vod-banner, app-banner-top, app-banner-bottom, preroll-vod, preroll-live, midroll-vod, midroll-live

#### `ad_placements` — Links creatives to zones
| Column | Type | Purpose |
|--------|------|---------|
| `id` | INT PK | |
| `campaign_id` | INT FK | Parent campaign |
| `creative_id` | INT FK | Which creative to show |
| `zone_id` | INT FK | Where to show it |
| `status` | ENUM(active,paused) | Toggle |
| `priority` | INT | Within-zone priority |

#### `ad_targeting_rules` — Flexible targeting per placement
| Column | Type | Purpose |
|--------|------|---------|
| `placement_id` | INT FK | Parent placement (CASCADE) |
| `rule_type` | ENUM | package, channel, category, content_type, platform, geo, schedule |
| `rule_operator` | ENUM(include,exclude) | Include or exclude matches |
| `rule_value` | JSON | Array of IDs or values |

**Targeting examples:**
- Show ads only to free-tier users: `{type: "package", operator: "include", value: [1]}` (package ID 1 = free)
- Show ads only on specific channels: `{type: "channel", operator: "include", value: [5, 12, 18]}`
- Exclude premium categories: `{type: "category", operator: "exclude", value: [3, 7]}`
- Only show on mobile: `{type: "platform", operator: "include", value: ["mobile"]}`
- Only during daytime: `{type: "schedule", operator: "include", value: ["06:00-18:00"]}`

#### `ad_impressions` — Impression tracking
Tracks every ad view with campaign_id, creative_id, zone_id, user_id, session, IP, platform, channel_id, content_type, revenue.

#### `ad_events` — Click/completion tracking
Tracks: click, complete, skip, error, quartile_25/50/75, mute, unmute, pause, resume, fullscreen.

### Key Files

| File | Purpose |
|------|---------|
| `src/Services/AdService.php` | Ad business logic, targeting engine, reporting |
| `src/Controllers/Admin/AdController.php` | Admin CRUD + ad serving API |
| `templates/admin/ads/index.php` | Campaign listing |
| `templates/admin/ads/form.php` | Campaign create/edit + creatives + placements |
| `templates/admin/ads/zones.php` | Zone management |
| `templates/admin/ads/reports.php` | Performance reports with charts |
| `database/migrations/012_create_advertising_tables.sql` | All 7 tables |

### Ad Serving API

The player/app calls these endpoints to get and track ads:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/admin/ads/api/serve?zone_type=pre_roll&channel_id=5&platform=web&package_id=1` | GET | Get ads for context |
| `/admin/ads/api/vast?zone_type=pre_roll&channel_id=5` | GET | Get VAST 4.2 XML for video players |
| `/admin/ads/api/impression` | POST | Record ad impression |
| `/admin/ads/api/event` | POST | Record click/complete/skip/etc. |

**Serve API parameters:**
- `zone_type` — text_scroller, banner, pre_roll, mid_roll
- `zone_slug` — specific zone slug
- `channel_id` — current channel
- `content_type` — live, vod, series
- `content_id` — movie/episode ID
- `category_id` — content category
- `package_id` — user's subscription package
- `platform` — web, mobile, tv, stb
- `user_id` — logged-in user
- `limit` — max ads to return

**VAST XML support:** The `/admin/ads/api/vast` endpoint generates VAST 4.2 XML for video player integration. It supports InLine ads with MediaFiles, TrackingEvents (start, quartiles, complete), VideoClicks, companion banners, and skip offsets.

### Admin Routes (38 routes)

```
# Campaigns
GET  /admin/ads                              → campaign listing
GET  /admin/ads/create                       → create form
POST /admin/ads/store                        → save new campaign
GET  /admin/ads/{id}/edit                    → edit form
POST /admin/ads/{id}/update                  → update campaign
POST /admin/ads/{id}/delete                  → delete campaign
POST /admin/ads/{id}/toggle-status           → pause/activate
POST /admin/ads/bulk                         → bulk actions

# Creatives
POST /admin/ads/{id}/creatives/add           → add creative
POST /admin/ads/{id}/creatives/{cid}/update  → update creative
POST /admin/ads/{id}/creatives/{cid}/delete  → delete creative

# Placements
POST /admin/ads/{id}/placements/add          → add placement
POST /admin/ads/{id}/placements/{pid}/update → update placement
POST /admin/ads/{id}/placements/{pid}/delete → delete placement

# Zones
GET  /admin/ads/zones                        → zone management
POST /admin/ads/zones/store                  → create zone
POST /admin/ads/zones/{id}/update            → update zone
POST /admin/ads/zones/{id}/toggle            → enable/disable zone
POST /admin/ads/zones/{id}/delete            → delete zone

# Reports
GET  /admin/ads/reports                      → performance dashboard
GET  /admin/ads/{id}/report                  → campaign report (AJAX)

# Ad Serving API
GET  /admin/ads/api/serve                    → get ads for context
POST /admin/ads/api/impression               → record impression
POST /admin/ads/api/event                    → record event
GET  /admin/ads/api/vast                     → VAST 4.2 XML

# AI Generation & File Uploads
POST /admin/ads/ai/generate-text             → AI text generation
POST /admin/ads/ai/generate-image            → DALL-E 3 image generation
POST /admin/ads/upload/image                 → banner image upload + WebP
POST /admin/ads/upload/video                 → video file upload
```

### Building a Player with Ads

To integrate ads in a player/frontend:

1. **Before content playback** — Call `/admin/ads/api/serve?zone_type=pre_roll&channel_id=X&package_id=Y&platform=Z` to get pre-roll ads
2. **For VAST-compatible players** — Use `/admin/ads/api/vast?zone_type=pre_roll&...` as the VAST tag URL
3. **During playback** — Call `/admin/ads/api/serve?zone_type=mid_roll&...` for mid-roll insertion points
4. **Overlay ads** — Call `/admin/ads/api/serve?zone_type=banner&...` or `zone_type=text_scroller&...` for overlay ads
5. **Track impressions** — POST to `/admin/ads/api/impression` when ad is displayed
6. **Track events** — POST to `/admin/ads/api/event` for clicks, completions, skips
7. **Respect targeting** — Pass `package_id` so free-tier users see ads while premium users don't

### AI-Powered Ad Content Generation & File Uploads

The ad system integrates with `AIService` and `ImageService` to provide AI text generation, AI image generation (DALL-E 3), banner image upload with WebP compression, and video file upload.

#### AI Text Generation

Uses the existing multi-provider `AIService` (Ollama/OpenAI/Anthropic) to generate ad copy. Each ad type gets a tailored prompt:

```php
$aiService = new AIService();
$text = $aiService->generateAdText($userPrompt, $adType, $context);
```

- **text_scroller**: Short ticker text, under 120 characters
- **banner**: HEADLINE | TAGLINE format
- **pre_roll/mid_roll**: HOOK, BODY, CTA script format

Context parameters (advertiser name, campaign name) are injected into the prompt for relevance.

#### AI Image Generation (DALL-E 3)

Generates banner images via OpenAI's DALL-E 3 API. Requires an OpenAI API key configured in Settings > AI.

```php
$aiService = new AIService();
$result = $aiService->generateImage($prompt, [
    'size' => '1792x1024',    // landscape (1792x1024), square (1024x1024), portrait (1024x1792)
    'quality' => 'standard',   // standard or hd
]);
// Returns: ['success' => true, 'url' => 'https://...', 'revised_prompt' => '...']
```

The controller (`AdController::generateAdImage()`) downloads the DALL-E URL and processes it through `ImageService` with context `ad` for local WebP storage. The final image path is returned to the frontend.

#### Banner Image Upload

File uploads are processed through `ImageService` with the `ad` context:

```php
$imageService = new ImageService();
$result = $imageService->processUpload($uploadedFile, 'ad', $entityId, 'banner');
// Creates WebP variants: banner_large (728x90), banner_medium (468x60), etc.
```

The `AdController::uploadAdImage()` endpoint accepts multipart file uploads, validates the image, processes it through ImageService, and returns the full-size image path along with dimensions.

#### Video File Upload

Video ads (pre-roll/mid-roll) support direct file upload:

- **Allowed formats**: MP4, WebM, OGG, MOV
- **Max size**: 100MB
- **Storage**: `/uploads/ad/{entityId}/video_{timestamp}.{ext}`
- **Endpoint**: `POST /admin/ads/upload/video`

Videos are stored as-is (no transcoding). For VAST-compatible players, the `vast_tag_url` field can be used instead of direct video upload.

#### Ad Content API Routes (4 endpoints)

```
POST /admin/ads/ai/generate-text     → AI text generation (AIService)
POST /admin/ads/ai/generate-image    → DALL-E 3 image generation + WebP
POST /admin/ads/upload/image         → Banner image upload + WebP compression
POST /admin/ads/upload/video         → Video file upload (MP4/WebM/OGG/MOV)
```

All endpoints return JSON and require auth middleware.

#### Frontend Integration (form.php)

The ad creation/edit modal provides:
- **Text scroller**: AI generate button with prompt input field
- **Banner**: Three image source options — Upload file, Enter URL, or AI Generate (DALL-E 3) with size picker (landscape/square/portrait)
- **Pre-roll/Mid-roll**: Upload video button with progress indicator, or enter video URL / VAST tag URL

#### Future: Sora 2 Video Generation

OpenAI's Sora 2 API integration is planned for AI-generated video ads. This would allow generating short video ad clips from text prompts, similar to how DALL-E 3 generates images. Implementation is pending Sora 2 API availability and cost evaluation.

## VOD Server (Transcoding)

The VOD server is a **standalone C daemon** located in `vod-server/` that handles video transcoding and HLS/DASH packaging. It runs as a separate process and communicates with the IPTV admin panel via REST API.

### Architecture

```
IPTV Admin (PHP) ──REST API──▶ VOD Server (C daemon, port 8090)
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
                 FFmpeg          MP4Box          SQLite
              (transcode)      (package)       (job queue)
```

### Tech Stack

| Component | Technology |
|-----------|-----------|
| Language | C (C11 standard) |
| HTTP | libmicrohttpd |
| Database | SQLite3 (WAL mode) |
| Build | CMake 3.10+ |
| Transcoding | FFmpeg (libx264, libx265, libsvtav1) |
| Packaging | MP4Box (CMAF) with FFmpeg fallback |
| HW Accel | NVIDIA NVENC, Intel VAAPI/QSV |

### Key Source Files

| File | Purpose |
|------|---------|
| `vod-server/src/main.c` | Entry point, daemon init, signal handlers |
| `vod-server/src/config.c` | INI config parsing, profile loading/saving |
| `vod-server/src/config.h` | Config structs (`vod_config_t`, `transcode_profile_t`) |
| `vod-server/src/api_routes.c` | All JSON API endpoint handlers |
| `vod-server/src/transcoder.c` | FFmpeg command building, media probing |
| `vod-server/src/packager.c` | HLS/DASH packaging (MP4Box + FFmpeg fallback) |
| `vod-server/src/job_processor.c` | Job queue, concurrent transcode execution |
| `vod-server/src/http_server.c` | libmicrohttpd setup, auth, request routing |
| `vod-server/src/database.c` | SQLite operations |
| `vod-server/config/vod-server.conf` | Default config with profile definitions |
| `vod-server/database/schema.sql` | SQLite schema (jobs, content, peers, settings) |

### Transcode Pipeline

The current pipeline is **two-step**: encode intermediates, then package.

```
1. ENCODE: FFmpeg → per-rendition MP4 files (360p.mp4, 720p.mp4, 1080p.mp4, audio.m4a)
2. PACKAGE: MP4Box → CMAF segments + master.m3u8 + manifest.mpd
```

Job status flow: `pending → downloading → processing → packaging → complete`

### Transcode Profiles

Profiles are defined in `vod-server.conf` as `[profile:name]` sections and can also be created/edited via the API (stored in SQLite `settings` table). DB profiles override INI profiles on startup.

```ini
[profile:standard]
codec = libx264
preset = slow
crf = 23
renditions = 360p:400k,480p:1000k,720p:2800k,1080p:5000k
audio_codec = aac
audio_bitrate = 128k
```

**Profile struct** (`config.h`): max 16 profiles, max 8 renditions each.

### VOD Server API Endpoints

All require `X-API-Key` header. Base URL: `http://vod-server:8090/api/`

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/status` | Server status (uptime, CPU, memory, disk) |
| GET | `/api/config` | Current config + profiles |
| POST | `/api/config` | Update settings (persisted to SQLite) |
| GET | `/api/profiles` | List all transcode profiles |
| POST | `/api/profiles` | Create new profile |
| PUT | `/api/profiles/{name}` | Update existing profile |
| DELETE | `/api/profiles/{name}` | Delete profile |
| GET | `/api/jobs` | List transcode jobs (filterable) |
| POST | `/api/jobs` | Submit new transcode job |
| GET | `/api/jobs/{id}` | Job details + progress |
| DELETE | `/api/jobs/{id}` | Cancel/delete job |
| GET | `/api/content` | List transcoded content |
| DELETE | `/api/content/{id}` | Delete content from library |
| POST | `/api/upload` | Upload source file |
| GET | `/api/browse` | Browse server filesystem |

### IPTV Admin Integration

The PHP admin panel proxies VOD server API calls through `VodServerService` + `VodServerController`:

| IPTV Admin Route | Proxied To |
|------------------|-----------|
| `GET /admin/vod-server/profiles` | `GET /api/profiles` |
| `POST /admin/vod-server/profiles/create` | `POST /api/profiles` |
| `POST /admin/vod-server/profiles/update` | `PUT /api/profiles/{name}` |
| `POST /admin/vod-server/profiles/delete` | `DELETE /api/profiles/{name}` |
| `GET /admin/vod-server/config` | `GET /api/config` |
| `GET /admin/vod-server/status` | `GET /api/status` |
| `POST /admin/vod-server/jobs/submit` | `POST /api/jobs` |
| `GET /admin/vod-server/job-status` | `GET /api/jobs/{id}` |

### Movie VOD Fields

The `movies` table tracks per-movie VOD state:

| Column | Type | Purpose |
|--------|------|---------|
| `vod_server_id` | INT | Which VOD server processed this |
| `vod_job_id` | INT | Transcode job ID on that server |
| `vod_status` | VARCHAR(20) | pending/processing/packaging/complete/failed/cancelled |
| `vod_progress` | DECIMAL(5,2) | 0-100% |
| `vod_error` | TEXT | Error message if failed |
| `stream_url` | VARCHAR(500) | HLS URL (e.g. `http://vod:8090/content/movie-123/master.m3u8`) |

### Building the VOD Server

```bash
cd vod-server
mkdir -p build && cd build
cmake ..
make -j$(nproc)
sudo make install
```

**Dependencies:** libmicrohttpd-dev, libsqlite3-dev, libgnutls28-dev, FFmpeg, MP4Box (GPAC)

## Project Status

### Completed (Phases 0-5)
- Admin authentication system
- Role-based access control
- Admin user management
- Settings system (with TMDB, Fanart.tv, YouTube API integration)
- Email service
- Profile management
- Channel management (CRUD, bulk actions, logo search)
- Movies management (CRUD, TMDB/Fanart.tv metadata, trailers, YouTube import)
- Series/TV Shows management
- Category management
- EPG system
- App Layout builder (sections, items, TMDB import, image upload)
- Pages & Navigation system (per-platform pages, navigation menus, icon picker)
- Advertising system (campaigns, creatives, zones, placements, targeting, reporting, VAST)
- AI-powered ad content generation (text via Ollama/OpenAI/Anthropic, images via DALL-E 3)
- Ad file uploads (banner images with WebP compression, video files for pre-roll/mid-roll)

### In Progress (Phase 6)
- Player integration
- Frontend app rendering

### Future Phases
- Sora 2 AI video generation for video ads
- User profiles with parental controls
- Package/subscription management
- Analytics dashboard

## Useful Commands

```bash
# Start development server
php -S localhost:8000 -t public

# Run database migration manually
mysql -u cari_iptv -p cari_iptv < database/migrations/006_new_migration.sql

# Check PHP syntax
php -l src/Services/NewService.php

# View recent logs
tail -f storage/logs/php-error.log
```

## Environment Variables (.env)

```
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cari_iptv
DB_USERNAME=cari_iptv
DB_PASSWORD=your_password
```

---

*This file helps AI assistants understand the CARI-IPTV codebase structure and conventions.*
