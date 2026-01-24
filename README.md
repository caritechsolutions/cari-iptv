# cari-iptv
Full IPTV and OTT Platform with enhanced features


# CARI-IPTV Platform Development Guide

## Project Vision

A Tier-1 IPTV/OTT platform built for the Caribbean market. Carrier-grade reliability, full-featured middleware, and designed for future multi-tenant/whitelabel expansion.

**Core Capabilities:**
- Live TV streaming with EPG
- Video on Demand (VOD) library
- Catch-up TV (time-shifted viewing)
- User profiles and parental controls
- Subscription and package management
- Ad integration (SSAI-ready)
- Analytics and telemetry
- AI-powered recommendations (future phase)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        WEB BROWSER                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │ PHP Pages   │  │ JavaScript  │  │ Video Player            │  │
│  │ (Structure) │  │ (UI Logic)  │  │ (Video.js/Shaka)        │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      PHP BACKEND (API)                          │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│  │ Auth     │ │ Channels │ │ VOD      │ │ EPG      │           │
│  │ Service  │ │ Service  │ │ Service  │ │ Service  │           │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘           │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│  │ Billing  │ │ Playback │ │ Analytics│ │ Admin    │           │
│  │ Service  │ │ Service  │ │ Service  │ │ Service  │           │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         MySQL DATABASE                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    STREAMING INFRASTRUCTURE                     │
│         (Nginx CDN + AES-128 Encryption - Separate)             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.x (Plain PHP, no framework) |
| Database | MySQL 8.x |
| Frontend | HTML5, CSS3, JavaScript (ES6+) |
| Video Player | Video.js with HLS support |
| Web Server | Nginx (or Apache) |
| Streaming | HLS with AES-128 encryption |
| CDN | Self-hosted Nginx |
| Session/Cache | File-based initially, Redis later |

---

## Folder Structure

```
cari-iptv/
│
├── public/                     # Web root (point Nginx/Apache here)
│   ├── index.php               # Main entry point
│   ├── assets/
│   │   ├── css/
│   │   │   ├── main.css
│   │   │   ├── player.css
│   │   │   └── admin.css
│   │   ├── js/
│   │   │   ├── app.js          # Core application logic
│   │   │   ├── player.js       # Video player wrapper
│   │   │   ├── epg.js          # EPG grid logic
│   │   │   ├── api.js          # API client helper
│   │   │   └── utils.js
│   │   └── images/
│   │       ├── logos/
│   │       └── icons/
│   └── admin/                  # Admin panel entry
│       └── index.php
│
├── src/                        # PHP application code
│   ├── Config/
│   │   ├── database.php
│   │   ├── app.php
│   │   └── streaming.php
│   │
│   ├── Core/
│   │   ├── Database.php        # Database connection class
│   │   ├── Router.php          # Simple routing
│   │   ├── Request.php         # Request handling
│   │   ├── Response.php        # Response helpers (JSON, etc.)
│   │   ├── Session.php         # Session management
│   │   └── Validator.php       # Input validation
│   │
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── UserService.php
│   │   ├── ChannelService.php
│   │   ├── VodService.php
│   │   ├── EpgService.php
│   │   ├── PlaybackService.php
│   │   ├── PackageService.php
│   │   ├── BillingService.php
│   │   └── AnalyticsService.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Profile.php
│   │   ├── Channel.php
│   │   ├── Category.php
│   │   ├── VodAsset.php
│   │   ├── Series.php
│   │   ├── Episode.php
│   │   ├── EpgProgram.php
│   │   ├── Package.php
│   │   ├── Subscription.php
│   │   └── WatchHistory.php
│   │
│   ├── Controllers/
│   │   ├── Api/                # JSON API endpoints
│   │   │   ├── AuthController.php
│   │   │   ├── ChannelController.php
│   │   │   ├── VodController.php
│   │   │   ├── EpgController.php
│   │   │   ├── PlaybackController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── SearchController.php
│   │   │   └── AnalyticsController.php
│   │   │
│   │   └── Web/                # Page controllers
│   │       ├── HomeController.php
│   │       ├── PlayerController.php
│   │       ├── AuthController.php
│   │       └── AccountController.php
│   │
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   ├── AdminMiddleware.php
│   │   └── RateLimitMiddleware.php
│   │
│   └── Helpers/
│       ├── TokenHelper.php     # Stream token generation
│       ├── EncryptionHelper.php
│       └── TimeHelper.php
│
├── templates/                  # PHP template files
│   ├── layouts/
│   │   ├── main.php            # Main site layout
│   │   ├── admin.php           # Admin layout
│   │   └── minimal.php         # Login/error pages
│   │
│   ├── pages/
│   │   ├── home.php
│   │   ├── live-tv.php
│   │   ├── vod.php
│   │   ├── vod-detail.php
│   │   ├── player.php
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── account.php
│   │   └── profiles.php
│   │
│   ├── partials/
│   │   ├── header.php
│   │   ├── footer.php
│   │   ├── nav.php
│   │   ├── channel-card.php
│   │   ├── vod-card.php
│   │   └── epg-grid.php
│   │
│   └── admin/
│       ├── dashboard.php
│       ├── channels/
│       ├── vod/
│       ├── epg/
│       ├── users/
│       ├── packages/
│       └── settings/
│
├── database/
│   ├── schema.sql              # Full database schema
│   ├── seed.sql                # Sample data
│   └── migrations/             # Future schema changes
│
├── storage/
│   ├── logs/
│   ├── cache/
│   └── sessions/
│
├── tests/                      # Future: automated tests
│
├── docs/
│   ├── API.md                  # API documentation
│   ├── DEPLOYMENT.md
│   └── STREAMING-SETUP.md
│
├── .htaccess                   # Apache config (if used)
├── nginx.conf.example          # Nginx config example
├── composer.json               # PHP dependencies (if needed)
└── README.md
```

---

## Database Schema Overview

### Core Tables

```
users
├── id (PK)
├── email (unique)
├── password_hash
├── status (active/suspended/pending)
├── max_streams (concurrent limit)
├── created_at
└── updated_at

profiles
├── id (PK)
├── user_id (FK)
├── name
├── avatar
├── is_kids (boolean)
├── pin (parental)
└── created_at

devices
├── id (PK)
├── user_id (FK)
├── device_type
├── device_name
├── device_token
├── last_active
└── created_at

channels
├── id (PK)
├── name
├── logo_url
├── stream_url
├── category_id (FK)
├── channel_number
├── is_hd (boolean)
├── is_active (boolean)
├── catchup_days (0 = disabled)
└── sort_order

categories
├── id (PK)
├── name
├── slug
├── type (live/vod)
├── parent_id (FK, nullable)
└── sort_order

vod_assets
├── id (PK)
├── title
├── description
├── type (movie/episode)
├── series_id (FK, nullable)
├── season_number
├── episode_number
├── duration
├── stream_url
├── poster_url
├── backdrop_url
├── year
├── rating
├── genre
├── is_active
├── created_at
└── updated_at

series
├── id (PK)
├── title
├── description
├── poster_url
├── backdrop_url
├── year
├── genre
├── total_seasons
└── is_active

epg_programs
├── id (PK)
├── channel_id (FK)
├── title
├── description
├── start_time
├── end_time
├── category
├── poster_url
├── is_catchup_available
└── catchup_url

packages
├── id (PK)
├── name
├── description
├── price
├── duration_days
├── is_active
└── created_at

package_channels
├── package_id (FK)
├── channel_id (FK)
└── PRIMARY KEY (package_id, channel_id)

package_vod_categories
├── package_id (FK)
├── category_id (FK)
└── PRIMARY KEY (package_id, category_id)

subscriptions
├── id (PK)
├── user_id (FK)
├── package_id (FK)
├── status (active/expired/cancelled)
├── start_date
├── end_date
├── auto_renew
└── created_at

watch_history
├── id (PK)
├── profile_id (FK)
├── content_type (channel/vod)
├── content_id
├── position (seconds)
├── duration
├── completed (boolean)
├── watched_at
└── updated_at

favorites
├── id (PK)
├── profile_id (FK)
├── content_type (channel/vod)
├── content_id
└── created_at

analytics_events
├── id (PK)
├── user_id (FK)
├── profile_id (FK)
├── event_type (play/pause/stop/error/buffer)
├── content_type
├── content_id
├── metadata (JSON)
├── created_at
└── INDEX on (created_at, event_type)
```

---

## API Endpoints

### Authentication
```
POST   /api/auth/login          # Login, returns token
POST   /api/auth/register       # Register new user
POST   /api/auth/logout         # Invalidate session
POST   /api/auth/refresh        # Refresh token
GET    /api/auth/me             # Current user info
```

### Profiles
```
GET    /api/profiles            # List user profiles
POST   /api/profiles            # Create profile
PUT    /api/profiles/{id}       # Update profile
DELETE /api/profiles/{id}       # Delete profile
POST   /api/profiles/{id}/select  # Set active profile
```

### Channels (Live TV)
```
GET    /api/channels            # List all channels (filtered by subscription)
GET    /api/channels/{id}       # Single channel details
GET    /api/channels/category/{id}  # Channels by category
GET    /api/categories?type=live    # Live TV categories
```

### VOD
```
GET    /api/vod                 # VOD catalog (paginated)
GET    /api/vod/{id}            # Single asset details
GET    /api/vod/category/{id}   # VOD by category
GET    /api/series              # All series
GET    /api/series/{id}         # Series with episodes
GET    /api/categories?type=vod # VOD categories
```

### EPG
```
GET    /api/epg                 # EPG for all channels (date range)
GET    /api/epg/channel/{id}    # EPG for single channel
GET    /api/epg/now             # What's on now (all channels)
```

### Playback
```
GET    /api/playback/live/{channel_id}     # Get stream URL + token
GET    /api/playback/vod/{asset_id}        # Get VOD stream URL + token
GET    /api/playback/catchup/{program_id}  # Get catchup stream URL
POST   /api/playback/heartbeat             # Keep session alive
```

### Search
```
GET    /api/search?q={query}    # Search channels + VOD
GET    /api/search/vod?q={query}
GET    /api/search/channels?q={query}
```

### User Data
```
GET    /api/history             # Watch history
POST   /api/history             # Update watch position
GET    /api/favorites           # List favorites
POST   /api/favorites           # Add favorite
DELETE /api/favorites/{id}      # Remove favorite
GET    /api/continue-watching   # Resume list
```

### Analytics
```
POST   /api/analytics/event     # Log playback event
```

---

## Development Phases

### PHASE 0: Project Setup
**Goal: Foundation ready for development**

- [ ] **0.1** Initialize project folder structure
- [ ] **0.2** Create database schema (schema.sql)
- [ ] **0.3** Set up Database.php connection class
- [ ] **0.4** Create simple Router.php
- [ ] **0.5** Create Request.php and Response.php helpers
- [ ] **0.6** Set up basic config files
- [ ] **0.7** Create main layout template
- [ ] **0.8** Test: PHP serves a basic page

---

### PHASE 1: Authentication System
**Goal: Users can register, login, and manage sessions**

- [ ] **1.1** Create users table migration
- [ ] **1.2** Create User model
- [ ] **1.3** Build AuthService (register, login, logout, password hashing)
- [ ] **1.4** Create session management (Session.php)
- [ ] **1.5** Build AuthController (API endpoints)
- [ ] **1.6** Build AuthMiddleware (protect routes)
- [ ] **1.7** Create login page template
- [ ] **1.8** Create registration page template
- [ ] **1.9** JavaScript: API client helper (api.js)
- [ ] **1.10** Test: Full auth flow works

---

### PHASE 2: User Profiles
**Goal: Multiple profiles per account with parental controls**

- [ ] **2.1** Create profiles table
- [ ] **2.2** Create Profile model
- [ ] **2.3** Build ProfileService
- [ ] **2.4** Build ProfileController (API)
- [ ] **2.5** Create profile selection page
- [ ] **2.6** Create profile management page
- [ ] **2.7** Add parental PIN verification
- [ ] **2.8** Store active profile in session
- [ ] **2.9** Test: Multi-profile flow works

---

### PHASE 3: Channel Management (Admin)
**Goal: Admin can add/edit channels and categories**

- [ ] **3.1** Create channels table
- [ ] **3.2** Create categories table
- [ ] **3.3** Create Channel and Category models
- [ ] **3.4** Build ChannelService
- [ ] **3.5** Build admin layout template
- [ ] **3.6** Build admin authentication (AdminMiddleware)
- [ ] **3.7** Create admin channel list page
- [ ] **3.8** Create admin channel add/edit form
- [ ] **3.9** Create admin category management
- [ ] **3.10** Test: Admin can manage channels

---

### PHASE 4: Live TV Player (Frontend)
**Goal: Users can watch live TV channels**

- [ ] **4.1** Build ChannelController (API)
- [ ] **4.2** Create PlaybackService (token generation, URL building)
- [ ] **4.3** Build PlaybackController (API)
- [ ] **4.4** Create live TV page template
- [ ] **4.5** Build channel list component (JavaScript)
- [ ] **4.6** Integrate Video.js player
- [ ] **4.7** Build player.js wrapper
- [ ] **4.8** Channel switching without page reload
- [ ] **4.9** Category filtering
- [ ] **4.10** Test: Live TV playback works

---

### PHASE 5: EPG System
**Goal: Program guide displays and syncs with live TV**

- [ ] **5.1** Create epg_programs table
- [ ] **5.2** Create EpgProgram model
- [ ] **5.3** Build EpgService
- [ ] **5.4** Build EpgController (API)
- [ ] **5.5** XMLTV import functionality
- [ ] **5.6** Admin EPG management page
- [ ] **5.7** Build EPG grid component (epg.js)
- [ ] **5.8** "Now playing" overlay on channels
- [ ] **5.9** EPG timeline navigation
- [ ] **5.10** Test: EPG displays correctly and updates

---

### PHASE 6: VOD System
**Goal: Users can browse and watch movies/series**

- [ ] **6.1** Create vod_assets table
- [ ] **6.2** Create series table
- [ ] **6.3** Create VodAsset and Series models
- [ ] **6.4** Build VodService
- [ ] **6.5** Build VodController (API)
- [ ] **6.6** Admin VOD management pages
- [ ] **6.7** Create VOD browse page template
- [ ] **6.8** Create VOD detail page template
- [ ] **6.9** Build VOD card component
- [ ] **6.10** Series/season/episode navigation
- [ ] **6.11** VOD playback integration
- [ ] **6.12** Test: Full VOD experience works

---

### PHASE 7: Watch History & Continue Watching
**Goal: Track viewing progress and resume playback**

- [ ] **7.1** Create watch_history table
- [ ] **7.2** Create favorites table
- [ ] **7.3** Create WatchHistory model
- [ ] **7.4** Build history tracking in PlaybackService
- [ ] **7.5** Build HistoryController (API)
- [ ] **7.6** Save position on pause/stop
- [ ] **7.7** "Continue Watching" row on home
- [ ] **7.8** Favorites add/remove functionality
- [ ] **7.9** My List page
- [ ] **7.10** Test: Resume and favorites work

---

### PHASE 8: Packages & Subscriptions
**Goal: Users have access based on subscription packages**

- [ ] **8.1** Create packages table
- [ ] **8.2** Create package_channels table
- [ ] **8.3** Create package_vod_categories table
- [ ] **8.4** Create subscriptions table
- [ ] **8.5** Create Package and Subscription models
- [ ] **8.6** Build PackageService
- [ ] **8.7** Build entitlement checking in services
- [ ] **8.8** Admin package management
- [ ] **8.9** Admin user subscription management
- [ ] **8.10** Filter content based on user subscription
- [ ] **8.11** "Upgrade" prompts for locked content
- [ ] **8.12** Test: Entitlements enforced correctly

---

### PHASE 9: Catch-up TV
**Goal: Watch past programs from EPG**

- [ ] **9.1** Add catchup_days to channels
- [ ] **9.2** Add catchup fields to epg_programs
- [ ] **9.3** Update EpgService for catchup URLs
- [ ] **9.4** Catchup playback endpoint
- [ ] **9.5** EPG: clickable past programs
- [ ] **9.6** Catchup player page
- [ ] **9.7** Test: Catchup playback works

---

### PHASE 10: Search
**Goal: Full-text search across all content**

- [ ] **10.1** Build SearchService
- [ ] **10.2** Build SearchController (API)
- [ ] **10.3** Create search results page
- [ ] **10.4** Search input in header
- [ ] **10.5** Search suggestions (optional)
- [ ] **10.6** Filter by type (channels/VOD)
- [ ] **10.7** Test: Search returns relevant results

---

### PHASE 11: Analytics & Telemetry
**Goal: Track viewing behavior for insights**

- [ ] **11.1** Create analytics_events table
- [ ] **11.2** Build AnalyticsService
- [ ] **11.3** Build AnalyticsController (API)
- [ ] **11.4** Player events: play, pause, stop, buffer, error
- [ ] **11.5** Admin analytics dashboard (basic)
- [ ] **11.6** Popular content tracking
- [ ] **11.7** Test: Events logged correctly

---

### PHASE 12: Home Page & Discovery
**Goal: Engaging home page with curated content**

- [ ] **12.1** Featured/hero banner section
- [ ] **12.2** "Continue Watching" row
- [ ] **12.3** "Live Now" row
- [ ] **12.4** "Popular" row
- [ ] **12.5** Category rows
- [ ] **12.6** "Recently Added" row
- [ ] **12.7** Admin: manage featured content
- [ ] **12.8** Test: Home page loads fast

---

### PHASE 13: Polish & Performance
**Goal: Production-ready quality**

- [ ] **13.1** Error handling throughout
- [ ] **13.2** Input validation (Validator.php)
- [ ] **13.3** Rate limiting (RateLimitMiddleware)
- [ ] **13.4** Caching strategy for EPG/catalogs
- [ ] **13.5** Image lazy loading
- [ ] **13.6** Mobile responsive CSS
- [ ] **13.7** Loading states/skeletons
- [ ] **13.8** 404 and error pages
- [ ] **13.9** Security audit (SQL injection, XSS)
- [ ] **13.10** Performance testing

---

### PHASE 14: Ad Integration (SSAI-Ready)
**Goal: Infrastructure for ad insertion**

- [ ] **14.1** Ad break markers in streams
- [ ] **14.2** VAST/VMAP support structure
- [ ] **14.3** Pre-roll ad support
- [ ] **14.4** Mid-roll markers
- [ ] **14.5** Ad-free tier flag
- [ ] **14.6** Test: Ad framework ready

---

### FUTURE PHASES

**Phase 15: Device Management**
- Limit concurrent streams
- Device registration
- Remote logout

**Phase 16: Billing Integration**
- Payment gateway
- Invoice generation
- Auto-renewal

**Phase 17: AI Recommendations**
- Watch pattern analysis
- Personalized suggestions
- "Because you watched..."

**Phase 18: Android/iOS Apps**
- React Native or native
- Offline downloads
- Push notifications

**Phase 19: Multi-tenant**
- Whitelabel support
- Operator isolation
- Custom branding per tenant

---

## Development Guidelines for Claude Code

### When Starting a Task

1. Read the task description from this guide
2. Check dependencies (previous tasks should be complete)
3. Create/modify files one at a time
4. Test after each significant change

### Code Standards

- **PHP**: PSR-12 style, type hints where possible
- **JavaScript**: ES6+, meaningful variable names
- **CSS**: BEM naming convention
- **SQL**: Uppercase keywords, lowercase table/column names

### File Creation Order

When building a new feature:
1. Database migration (if needed)
2. Model class
3. Service class
4. Controller class
5. Template/view files
6. JavaScript (if needed)
7. CSS (if needed)

### Testing Approach

After each task:
1. Manual test in browser
2. Check for PHP errors
3. Verify database operations
4. Test edge cases

### Security Checklist

- [ ] Prepared statements for all SQL
- [ ] HTML escape all output
- [ ] CSRF tokens on forms
- [ ] Validate all input
- [ ] Hash passwords with bcrypt
- [ ] Sanitize file paths

---

## Quick Reference

### Start Development Server
```bash
php -S localhost:8000 -t public
```

### Database Connection Test
```php
<?php
require_once '../src/Core/Database.php';
$db = Database::getInstance();
echo "Connected!";
```

### Common Commands
```bash
# Import schema
mysql -u root -p cari_iptv < database/schema.sql

# Import sample data
mysql -u root -p cari_iptv < database/seed.sql
```

---

## Notes

- Streaming infrastructure (Nginx CDN, AES-128 encryption) is separate from this middleware
- Stream URLs in database point to your CDN
- Token generation creates time-limited access keys
- This guide assumes streams are already available and working

---

*Last Updated: [Current Date]*
*Version: 1.0*
