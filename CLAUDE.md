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
│   │   ├── AIService.php          # AI integration (Ollama, OpenAI, Anthropic)
│   │   └── MetadataService.php    # TMDB, Fanart.tv, YouTube API integration
│   │
│   ├── Controllers/Admin/  # Admin panel controllers
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── AdminUserController.php
│   │   ├── ChannelController.php
│   │   ├── MovieController.php
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

### Icons

Lucide icon font is hosted locally at `public/assets/fonts/lucide/`. The CSS uses class prefix `lucide-` (e.g., `<i class="lucide-film"></i>`). The font files (woff2, woff, ttf) are in the same directory. Loaded via `<link rel="stylesheet" href="/assets/fonts/lucide/lucide.css">` in the admin layout.

**Important:** The `[data-icon]` CSS selector in lucide.css conflicts with `data-icon` HTML attributes — use `data-value` instead when storing icon names on elements.

## Project Status

### Completed (Phases 0-4)
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

### In Progress (Phase 5)
- Player integration
- Frontend app rendering

### Future Phases
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
