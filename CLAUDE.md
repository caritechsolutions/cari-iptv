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
│   │   ├── SettingsService.php    # Database KV store for settings
│   │   ├── EmailService.php       # Pure PHP SMTP (no PHPMailer)
│   │   ├── ImageService.php       # Image processing (resize, WebP)
│   │   ├── AIService.php          # AI integration (Ollama, OpenAI, Anthropic)
│   │   └── MetadataService.php    # External API integration
│   │
│   ├── Controllers/Admin/  # Admin panel controllers
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── AdminUserController.php
│   │   ├── ChannelController.php
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
| `categories` | Channel/VOD categories |
| `streaming_servers` | Stream server configuration |
| `content_owners` | Content provider tracking |
| `settings` | Key-value configuration store |

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

## Project Status

### Completed (Phases 0-1.7)
- Admin authentication system
- Role-based access control
- Admin user management
- Settings system
- Email service
- Profile management

### In Progress (Phase 3)
- Channel management (CRUD complete)
- Category management (pending)

### Future Phases
- User profiles with parental controls
- Live TV player integration
- EPG system
- VOD management
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
