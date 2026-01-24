#!/bin/bash
#
# CARI-IPTV Platform Installer
# Carrier-grade IPTV/OTT Middleware for the Caribbean
#
# Usage: curl -sSL https://raw.githubusercontent.com/your-repo/cari-iptv/main/install.sh | sudo bash
#
# Or with options:
# curl -sSL https://url/install.sh | sudo bash -s -- --db-pass=yourpassword --admin-pass=adminpass
#

set -e

# ============================================
# Configuration
# ============================================
INSTALL_DIR="/var/www/cari-iptv"
WEB_USER="www-data"
WEB_GROUP="www-data"
DB_NAME="cari_iptv"
DB_USER="cari_iptv"
DB_PASS=""
ADMIN_EMAIL="admin@localhost"
ADMIN_PASS=""
PHP_VERSION="8.2"
NGINX_PORT="80"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================
# Helper Functions
# ============================================
print_banner() {
    echo -e "${BLUE}"
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║                                                           ║"
    echo "║     ██████╗ █████╗ ██████╗ ██╗      ██╗██████╗ ████████╗██╗   ██╗ ║"
    echo "║    ██╔════╝██╔══██╗██╔══██╗██║      ██║██╔══██╗╚══██╔══╝██║   ██║ ║"
    echo "║    ██║     ███████║██████╔╝██║█████╗██║██████╔╝   ██║   ██║   ██║ ║"
    echo "║    ██║     ██╔══██║██╔══██╗██║╚════╝██║██╔═══╝    ██║   ╚██╗ ██╔╝ ║"
    echo "║    ╚██████╗██║  ██║██║  ██║██║      ██║██║        ██║    ╚████╔╝  ║"
    echo "║     ╚═════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝      ╚═╝╚═╝        ╚═╝     ╚═══╝   ║"
    echo "║                                                           ║"
    echo "║           IPTV/OTT Middleware Platform Installer          ║"
    echo "║                    Caribbean Edition                      ║"
    echo "╚═══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}▶ $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"
}

generate_password() {
    openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24
}

# ============================================
# Parse Command Line Arguments
# ============================================
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --db-pass=*)
                DB_PASS="${1#*=}"
                shift
                ;;
            --admin-pass=*)
                ADMIN_PASS="${1#*=}"
                shift
                ;;
            --admin-email=*)
                ADMIN_EMAIL="${1#*=}"
                shift
                ;;
            --install-dir=*)
                INSTALL_DIR="${1#*=}"
                shift
                ;;
            --port=*)
                NGINX_PORT="${1#*=}"
                shift
                ;;
            --help)
                echo "CARI-IPTV Installer"
                echo ""
                echo "Usage: $0 [options]"
                echo ""
                echo "Options:"
                echo "  --db-pass=PASSWORD      MySQL database password (auto-generated if not set)"
                echo "  --admin-pass=PASSWORD   Admin user password (auto-generated if not set)"
                echo "  --admin-email=EMAIL     Admin email (default: admin@localhost)"
                echo "  --install-dir=PATH      Installation directory (default: /var/www/cari-iptv)"
                echo "  --port=PORT             HTTP port (default: 80)"
                echo "  --help                  Show this help message"
                exit 0
                ;;
            *)
                log_warn "Unknown option: $1"
                shift
                ;;
        esac
    done

    # Generate passwords if not provided
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(generate_password)
        log_info "Generated database password"
    fi
    if [ -z "$ADMIN_PASS" ]; then
        ADMIN_PASS=$(generate_password)
        log_info "Generated admin password"
    fi
}

# ============================================
# System Checks
# ============================================
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID
    else
        log_error "Cannot detect OS. /etc/os-release not found."
        exit 1
    fi

    case $OS in
        ubuntu|debian)
            PKG_MANAGER="apt-get"
            PKG_UPDATE="apt-get update"
            log_info "Detected: $OS $OS_VERSION"
            ;;
        centos|rhel|rocky|almalinux)
            PKG_MANAGER="dnf"
            PKG_UPDATE="dnf check-update || true"
            WEB_USER="nginx"
            WEB_GROUP="nginx"
            log_info "Detected: $OS $OS_VERSION"
            ;;
        *)
            log_error "Unsupported OS: $OS"
            log_error "Supported: Ubuntu, Debian, CentOS, RHEL, Rocky, AlmaLinux"
            exit 1
            ;;
    esac
}

check_existing_installation() {
    if [ -d "$INSTALL_DIR" ] && [ -f "$INSTALL_DIR/src/Config/app.php" ]; then
        log_warn "Existing installation detected at $INSTALL_DIR"
        read -p "Do you want to continue and overwrite? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Installation cancelled."
            exit 0
        fi
    fi
}

# ============================================
# Installation Functions
# ============================================
install_dependencies_debian() {
    log_step "Installing Dependencies (Debian/Ubuntu)"

    # Update package lists
    log_info "Updating package lists..."
    apt-get update -qq

    # Install prerequisites
    log_info "Installing prerequisites..."
    apt-get install -y -qq \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        curl \
        gnupg \
        lsb-release \
        unzip \
        git

    # Add PHP repository (for latest PHP)
    log_info "Adding PHP repository..."
    if [ "$OS" = "ubuntu" ]; then
        add-apt-repository -y ppa:ondrej/php
    else
        curl -sSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/php-archive-keyring.gpg
        echo "deb [signed-by=/usr/share/keyrings/php-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
    fi

    apt-get update -qq

    # Install Nginx with modules
    log_info "Installing Nginx..."
    apt-get install -y -qq \
        nginx \
        nginx-extras \
        libnginx-mod-http-geoip \
        libnginx-mod-http-geoip2 || apt-get install -y -qq nginx

    # Install PHP and extensions
    log_info "Installing PHP ${PHP_VERSION}..."
    apt-get install -y -qq \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-json \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-readline

    # Install MySQL
    log_info "Installing MySQL Server..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server

    # Install GeoIP databases
    log_info "Installing GeoIP databases..."
    apt-get install -y -qq geoip-database geoip-database-extra || true
}

install_dependencies_rhel() {
    log_step "Installing Dependencies (RHEL/CentOS)"

    # Enable EPEL and Remi repos
    log_info "Enabling repositories..."
    dnf install -y epel-release
    dnf install -y https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %rhel).rpm || true

    # Update
    dnf check-update || true

    # Install prerequisites
    log_info "Installing prerequisites..."
    dnf install -y \
        curl \
        unzip \
        git \
        openssl

    # Install Nginx
    log_info "Installing Nginx..."
    dnf install -y nginx nginx-mod-http-geoip || dnf install -y nginx

    # Install PHP
    log_info "Installing PHP ${PHP_VERSION}..."
    dnf module reset php -y || true
    dnf module enable php:remi-${PHP_VERSION} -y || true
    dnf install -y \
        php-fpm \
        php-mysqlnd \
        php-mbstring \
        php-xml \
        php-curl \
        php-zip \
        php-gd \
        php-intl \
        php-bcmath \
        php-json \
        php-opcache

    # Install MySQL
    log_info "Installing MySQL Server..."
    dnf install -y mysql-server

    # Install GeoIP
    dnf install -y GeoIP GeoIP-data || true
}

configure_mysql() {
    log_step "Configuring MySQL"

    # Start MySQL if not running
    systemctl start mysql || systemctl start mysqld
    systemctl enable mysql || systemctl enable mysqld

    # Secure installation and create database
    log_info "Creating database and user..."

    # Check if we can connect without password (fresh install)
    if mysql -u root -e "SELECT 1" &>/dev/null; then
        MYSQL_CMD="mysql -u root"
    else
        # Try with sudo
        MYSQL_CMD="mysql -u root"
    fi

    $MYSQL_CMD <<EOF
-- Create database
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user and grant privileges
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

    log_info "Database '${DB_NAME}' created successfully"
}

configure_php() {
    log_step "Configuring PHP-FPM"

    # Find PHP-FPM config
    if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
        PHP_FPM_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
        PHP_INI="/etc/php/${PHP_VERSION}/fpm/php.ini"
        PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
    else
        PHP_FPM_CONF="/etc/php-fpm.d/www.conf"
        PHP_INI="/etc/php.ini"
        PHP_FPM_SERVICE="php-fpm"
    fi

    # Configure PHP settings
    log_info "Optimizing PHP settings..."
    if [ -f "$PHP_INI" ]; then
        sed -i 's/^upload_max_filesize.*/upload_max_filesize = 100M/' "$PHP_INI"
        sed -i 's/^post_max_size.*/post_max_size = 100M/' "$PHP_INI"
        sed -i 's/^memory_limit.*/memory_limit = 256M/' "$PHP_INI"
        sed -i 's/^max_execution_time.*/max_execution_time = 300/' "$PHP_INI"
        sed -i 's/^;date.timezone.*/date.timezone = UTC/' "$PHP_INI"
    fi

    # Restart PHP-FPM
    systemctl restart $PHP_FPM_SERVICE
    systemctl enable $PHP_FPM_SERVICE

    log_info "PHP-FPM configured and running"
}

create_directory_structure() {
    log_step "Creating Directory Structure"

    # Create main directories
    mkdir -p "$INSTALL_DIR"/{public,src,templates,database,storage,docs}
    mkdir -p "$INSTALL_DIR"/public/{admin,player,assets}
    mkdir -p "$INSTALL_DIR"/public/assets/{css,js,images}
    mkdir -p "$INSTALL_DIR"/public/assets/images/{logos,icons,avatars}
    mkdir -p "$INSTALL_DIR"/src/{Config,Core,Services,Models,Controllers,Middleware,Helpers}
    mkdir -p "$INSTALL_DIR"/src/Controllers/{Api,Web,Admin}
    mkdir -p "$INSTALL_DIR"/templates/{layouts,pages,partials,admin}
    mkdir -p "$INSTALL_DIR"/templates/admin/{dashboard,channels,vod,users,packages,settings}
    mkdir -p "$INSTALL_DIR"/database/migrations
    mkdir -p "$INSTALL_DIR"/storage/{logs,cache,sessions}

    # Set permissions
    chown -R $WEB_USER:$WEB_GROUP "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 775 "$INSTALL_DIR"/storage

    log_info "Directory structure created at $INSTALL_DIR"
}

configure_nginx() {
    log_step "Configuring Nginx"

    # Determine PHP-FPM socket path
    if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
        PHP_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
    else
        PHP_SOCKET="/var/run/php-fpm/www.sock"
    fi

    # Create Nginx configuration
    cat > /etc/nginx/sites-available/cari-iptv <<EOF
# CARI-IPTV Nginx Configuration
# Carrier-grade IPTV/OTT Middleware

server {
    listen ${NGINX_PORT};
    listen [::]:${NGINX_PORT};
    server_name _;

    root ${INSTALL_DIR}/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Logging
    access_log /var/log/nginx/cari-iptv-access.log;
    error_log /var/log/nginx/cari-iptv-error.log;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/json application/xml;

    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Admin portal
    location /admin {
        try_files \$uri \$uri/ /admin/index.php?\$query_string;
    }

    # API endpoints
    location /api {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Player routes
    location /player {
        try_files \$uri \$uri/ /player/index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCKET};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;

        # Timeouts for long operations
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Static assets caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /\.(ht|git|env) {
        deny all;
    }

    location ~ ^/(src|storage|database|templates)/ {
        deny all;
    }

    # Secure link for stream authentication (optional module)
    # location /streams/ {
    #     secure_link \$arg_md5,\$arg_expires;
    #     secure_link_md5 "\$secure_link_expires\$uri\$remote_addr secret_key";
    #     if (\$secure_link = "") { return 403; }
    #     if (\$secure_link = "0") { return 410; }
    # }

    # GeoIP variables (if module loaded)
    # geoip_country /usr/share/GeoIP/GeoIP.dat;
    # geoip_city /usr/share/GeoIP/GeoIPCity.dat;
}
EOF

    # Enable site
    if [ -d /etc/nginx/sites-enabled ]; then
        rm -f /etc/nginx/sites-enabled/default
        ln -sf /etc/nginx/sites-available/cari-iptv /etc/nginx/sites-enabled/
    else
        # RHEL style
        cp /etc/nginx/sites-available/cari-iptv /etc/nginx/conf.d/cari-iptv.conf
    fi

    # Test and restart Nginx
    nginx -t
    systemctl restart nginx
    systemctl enable nginx

    log_info "Nginx configured and running on port ${NGINX_PORT}"
}

create_database_schema() {
    log_step "Creating Database Schema"

    cat > "$INSTALL_DIR/database/schema.sql" <<'EOF'
-- CARI-IPTV Database Schema
-- Version: 1.0.0

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Admin Users & Permissions
-- ============================================

CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `avatar` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('super_admin', 'admin', 'manager', 'support', 'viewer') NOT NULL DEFAULT 'viewer',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `last_ip` VARCHAR(45) DEFAULT NULL,
    `two_factor_secret` VARCHAR(255) DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `password_changed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `module` VARCHAR(50) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
    `role` ENUM('super_admin', 'admin', 'manager', 'support', 'viewer') NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role`, `permission_id`),
    FOREIGN KEY (`permission_id`) REFERENCES `admin_permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `admin_user_id` INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500),
    `payload` TEXT,
    `last_activity` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`admin_user_id`),
    INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_activity_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_user_id` INT UNSIGNED,
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `target_type` VARCHAR(50),
    `target_id` INT UNSIGNED,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`admin_user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_module` (`module`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Subscribers (End Users)
-- ============================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `phone` VARCHAR(20),
    `status` ENUM('active', 'suspended', 'pending', 'cancelled') NOT NULL DEFAULT 'pending',
    `max_streams` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `max_profiles` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `email_verified_at` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `last_ip` VARCHAR(45) DEFAULT NULL,
    `notes` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `profiles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `is_kids` TINYINT(1) NOT NULL DEFAULT 0,
    `pin` VARCHAR(10) DEFAULT NULL,
    `language` VARCHAR(10) DEFAULT 'en',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `devices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `device_type` VARCHAR(50) NOT NULL,
    `device_name` VARCHAR(100),
    `device_token` VARCHAR(255) NOT NULL UNIQUE,
    `platform` VARCHAR(50),
    `app_version` VARCHAR(20),
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_active` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_token` (`device_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Content: Channels & Categories
-- ============================================

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `type` ENUM('live', 'vod', 'series') NOT NULL,
    `parent_id` INT UNSIGNED DEFAULT NULL,
    `icon` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_type` (`type`),
    INDEX `idx_parent` (`parent_id`),
    INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `channels` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `logo_url` VARCHAR(500) DEFAULT NULL,
    `stream_url` VARCHAR(1000) NOT NULL,
    `stream_url_backup` VARCHAR(1000) DEFAULT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `channel_number` INT UNSIGNED DEFAULT NULL,
    `is_hd` TINYINT(1) NOT NULL DEFAULT 0,
    `is_4k` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `catchup_days` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `epg_channel_id` VARCHAR(100) DEFAULT NULL,
    `country` VARCHAR(2) DEFAULT NULL,
    `language` VARCHAR(10) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_number` (`channel_number`),
    INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Content: VOD & Series
-- ============================================

CREATE TABLE IF NOT EXISTS `series` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `poster_url` VARCHAR(500) DEFAULT NULL,
    `backdrop_url` VARCHAR(500) DEFAULT NULL,
    `year` YEAR DEFAULT NULL,
    `genre` VARCHAR(255) DEFAULT NULL,
    `rating` VARCHAR(10) DEFAULT NULL,
    `total_seasons` TINYINT UNSIGNED DEFAULT 1,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_category` (`category_id`),
    INDEX `idx_year` (`year`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vod_assets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT,
    `type` ENUM('movie', 'episode') NOT NULL DEFAULT 'movie',
    `series_id` INT UNSIGNED DEFAULT NULL,
    `season_number` TINYINT UNSIGNED DEFAULT NULL,
    `episode_number` SMALLINT UNSIGNED DEFAULT NULL,
    `duration` INT UNSIGNED DEFAULT NULL COMMENT 'Duration in seconds',
    `stream_url` VARCHAR(1000) NOT NULL,
    `stream_url_backup` VARCHAR(1000) DEFAULT NULL,
    `poster_url` VARCHAR(500) DEFAULT NULL,
    `backdrop_url` VARCHAR(500) DEFAULT NULL,
    `trailer_url` VARCHAR(500) DEFAULT NULL,
    `year` YEAR DEFAULT NULL,
    `rating` VARCHAR(10) DEFAULT NULL,
    `genre` VARCHAR(255) DEFAULT NULL,
    `director` VARCHAR(255) DEFAULT NULL,
    `cast` TEXT,
    `language` VARCHAR(10) DEFAULT NULL,
    `subtitles` JSON DEFAULT NULL,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `views` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`series_id`) REFERENCES `series`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_type` (`type`),
    INDEX `idx_series` (`series_id`),
    INDEX `idx_category` (`category_id`),
    INDEX `idx_year` (`year`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EPG (Electronic Program Guide)
-- ============================================

CREATE TABLE IF NOT EXISTS `epg_programs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `channel_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `poster_url` VARCHAR(500) DEFAULT NULL,
    `is_catchup_available` TINYINT(1) NOT NULL DEFAULT 0,
    `catchup_url` VARCHAR(1000) DEFAULT NULL,
    `episode_info` VARCHAR(50) DEFAULT NULL,
    `rating` VARCHAR(10) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`channel_id`) REFERENCES `channels`(`id`) ON DELETE CASCADE,
    INDEX `idx_channel` (`channel_id`),
    INDEX `idx_start` (`start_time`),
    INDEX `idx_end` (`end_time`),
    INDEX `idx_channel_time` (`channel_id`, `start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Packages & Subscriptions
-- ============================================

CREATE TABLE IF NOT EXISTS `packages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `price` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
    `duration_days` INT UNSIGNED NOT NULL DEFAULT 30,
    `max_streams` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`),
    INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_channels` (
    `package_id` INT UNSIGNED NOT NULL,
    `channel_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`package_id`, `channel_id`),
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`channel_id`) REFERENCES `channels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `package_vod_categories` (
    `package_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`package_id`, `category_id`),
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `package_id` INT UNSIGNED NOT NULL,
    `status` ENUM('active', 'expired', 'cancelled', 'pending') NOT NULL DEFAULT 'pending',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `auto_renew` TINYINT(1) NOT NULL DEFAULT 0,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE RESTRICT,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_package` (`package_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- User Activity & History
-- ============================================

CREATE TABLE IF NOT EXISTS `watch_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT UNSIGNED NOT NULL,
    `content_type` ENUM('channel', 'vod') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Position in seconds',
    `duration` INT UNSIGNED DEFAULT NULL,
    `completed` TINYINT(1) NOT NULL DEFAULT 0,
    `watched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_profile_content` (`profile_id`, `content_type`, `content_id`),
    INDEX `idx_profile` (`profile_id`),
    INDEX `idx_watched` (`watched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `favorites` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT UNSIGNED NOT NULL,
    `content_type` ENUM('channel', 'vod', 'series') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`profile_id`) REFERENCES `profiles`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_profile_content` (`profile_id`, `content_type`, `content_id`),
    INDEX `idx_profile` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Analytics & Events
-- ============================================

CREATE TABLE IF NOT EXISTS `analytics_events` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `profile_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(128) DEFAULT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `content_type` VARCHAR(20) DEFAULT NULL,
    `content_id` INT UNSIGNED DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `country` VARCHAR(2) DEFAULT NULL,
    `device_type` VARCHAR(50) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_event` (`event_type`),
    INDEX `idx_content` (`content_type`, `content_id`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `profile_id` INT UNSIGNED DEFAULT NULL,
    `device_id` INT UNSIGNED DEFAULT NULL,
    `content_type` ENUM('channel', 'vod', 'catchup') NOT NULL,
    `content_id` INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_heartbeat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_active` (`user_id`, `ended_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- System Settings & Configuration
-- ============================================

CREATE TABLE IF NOT EXISTS `settings` (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT,
    `type` ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
    `description` TEXT,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
EOF

    # Import schema
    log_info "Importing database schema..."
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$INSTALL_DIR/database/schema.sql"

    log_info "Database schema created successfully"
}

create_seed_data() {
    log_step "Seeding Demo Data"

    # Hash the admin password
    ADMIN_HASH=$(php -r "echo password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT);")

    cat > "$INSTALL_DIR/database/seed.sql" <<EOF
-- CARI-IPTV Demo Data
-- Generated: $(date)

USE ${DB_NAME};

-- Insert default admin user
INSERT INTO admin_users (username, email, password_hash, first_name, last_name, role, is_active)
VALUES ('admin', '${ADMIN_EMAIL}', '${ADMIN_HASH}', 'System', 'Administrator', 'super_admin', 1)
ON DUPLICATE KEY UPDATE password_hash = '${ADMIN_HASH}';

-- Insert permissions
INSERT INTO admin_permissions (name, slug, module, description) VALUES
('View Dashboard', 'dashboard.view', 'dashboard', 'View dashboard statistics'),
('Manage Channels', 'channels.manage', 'channels', 'Create, edit, and delete channels'),
('View Channels', 'channels.view', 'channels', 'View channel listings'),
('Manage VOD', 'vod.manage', 'vod', 'Create, edit, and delete VOD content'),
('View VOD', 'vod.view', 'vod', 'View VOD listings'),
('Manage Users', 'users.manage', 'users', 'Create, edit, and delete subscriber accounts'),
('View Users', 'users.view', 'users', 'View subscriber accounts'),
('Manage Packages', 'packages.manage', 'packages', 'Create, edit, and delete packages'),
('View Packages', 'packages.view', 'packages', 'View packages'),
('Manage EPG', 'epg.manage', 'epg', 'Import and manage EPG data'),
('View EPG', 'epg.view', 'epg', 'View EPG data'),
('View Analytics', 'analytics.view', 'analytics', 'View platform analytics'),
('Manage Settings', 'settings.manage', 'settings', 'Configure platform settings'),
('View Activity Log', 'activity.view', 'activity', 'View admin activity logs'),
('Manage Admins', 'admins.manage', 'admins', 'Manage admin users')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign all permissions to super_admin
INSERT INTO admin_role_permissions (role, permission_id)
SELECT 'super_admin', id FROM admin_permissions
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- Assign limited permissions to admin role
INSERT INTO admin_role_permissions (role, permission_id)
SELECT 'admin', id FROM admin_permissions WHERE slug NOT IN ('admins.manage', 'settings.manage')
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- Assign viewer permissions to manager
INSERT INTO admin_role_permissions (role, permission_id)
SELECT 'manager', id FROM admin_permissions WHERE slug LIKE '%.view' OR slug IN ('channels.manage', 'vod.manage', 'epg.manage')
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- Assign view-only permissions to support
INSERT INTO admin_role_permissions (role, permission_id)
SELECT 'support', id FROM admin_permissions WHERE slug LIKE '%.view'
ON DUPLICATE KEY UPDATE permission_id = permission_id;

-- Insert demo categories
INSERT INTO categories (name, slug, type, is_active, sort_order) VALUES
('Sports', 'sports', 'live', 1, 1),
('News', 'news', 'live', 1, 2),
('Entertainment', 'entertainment', 'live', 1, 3),
('Movies', 'movies', 'live', 1, 4),
('Kids', 'kids', 'live', 1, 5),
('Music', 'music', 'live', 1, 6),
('Documentary', 'documentary', 'live', 1, 7),
('Action Movies', 'action-movies', 'vod', 1, 1),
('Comedy', 'comedy', 'vod', 1, 2),
('Drama', 'drama', 'vod', 1, 3),
('Horror', 'horror', 'vod', 1, 4),
('Sci-Fi', 'sci-fi', 'vod', 1, 5),
('TV Series', 'tv-series', 'series', 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert demo channels
INSERT INTO channels (name, slug, stream_url, category_id, channel_number, is_hd, is_active, sort_order) VALUES
('Caribbean Sports 1', 'caribbean-sports-1', 'https://stream.example.com/sports1/index.m3u8', 1, 101, 1, 1, 1),
('Island News 24', 'island-news-24', 'https://stream.example.com/news24/index.m3u8', 2, 201, 1, 1, 2),
('Tropical Entertainment', 'tropical-entertainment', 'https://stream.example.com/tropical/index.m3u8', 3, 301, 1, 1, 3),
('Caribbean Kids', 'caribbean-kids', 'https://stream.example.com/kids/index.m3u8', 5, 501, 1, 1, 4),
('Reggae Vibes', 'reggae-vibes', 'https://stream.example.com/reggae/index.m3u8', 6, 601, 0, 1, 5)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert demo packages
INSERT INTO packages (name, slug, description, price, currency, duration_days, max_streams, is_active, is_featured, sort_order) VALUES
('Basic', 'basic', 'Essential channels package', 9.99, 'USD', 30, 1, 1, 0, 1),
('Standard', 'standard', 'Most popular channels + HD', 19.99, 'USD', 30, 2, 1, 1, 2),
('Premium', 'premium', 'All channels + VOD + 4K', 29.99, 'USD', 30, 4, 1, 0, 3),
('Annual Premium', 'annual-premium', 'Premium package - 12 months (2 months free)', 299.99, 'USD', 365, 4, 1, 0, 4)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert demo VOD
INSERT INTO vod_assets (title, slug, description, type, stream_url, poster_url, year, rating, genre, duration, category_id, is_active, is_featured) VALUES
('Island Adventure', 'island-adventure', 'An exciting adventure across Caribbean islands', 'movie', 'https://stream.example.com/vod/island-adventure.m3u8', '/assets/images/posters/island-adventure.jpg', 2024, 'PG-13', 'Action, Adventure', 7200, 8, 1, 1),
('Beach Romance', 'beach-romance', 'A romantic comedy set on tropical beaches', 'movie', 'https://stream.example.com/vod/beach-romance.m3u8', '/assets/images/posters/beach-romance.jpg', 2024, 'PG', 'Romance, Comedy', 6300, 9, 1, 0),
('Hurricane Season', 'hurricane-season', 'A thrilling disaster movie', 'movie', 'https://stream.example.com/vod/hurricane-season.m3u8', '/assets/images/posters/hurricane-season.jpg', 2023, 'PG-13', 'Action, Thriller', 7800, 8, 1, 1)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Insert demo users
INSERT INTO users (email, password_hash, first_name, last_name, status, max_streams) VALUES
('demo@example.com', '${ADMIN_HASH}', 'Demo', 'User', 'active', 2),
('john@example.com', '${ADMIN_HASH}', 'John', 'Smith', 'active', 2),
('jane@example.com', '${ADMIN_HASH}', 'Jane', 'Doe', 'active', 4),
('test@example.com', '${ADMIN_HASH}', 'Test', 'Account', 'pending', 2)
ON DUPLICATE KEY UPDATE first_name = VALUES(first_name);

-- Insert demo subscriptions
INSERT INTO subscriptions (user_id, package_id, status, start_date, end_date, auto_renew)
SELECT u.id, p.id, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1
FROM users u, packages p
WHERE u.email = 'demo@example.com' AND p.slug = 'premium'
ON DUPLICATE KEY UPDATE status = 'active';

-- Insert system settings
INSERT INTO settings (\`key\`, value, type, description) VALUES
('platform_name', 'CARI-IPTV', 'string', 'Platform display name'),
('platform_logo', '/assets/images/logo.png', 'string', 'Platform logo URL'),
('support_email', 'support@example.com', 'string', 'Support email address'),
('max_login_attempts', '5', 'number', 'Maximum failed login attempts before lockout'),
('session_timeout', '3600', 'number', 'Session timeout in seconds'),
('enable_registration', 'true', 'boolean', 'Allow new user registration'),
('default_language', 'en', 'string', 'Default platform language'),
('timezone', 'America/Jamaica', 'string', 'Platform timezone')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Insert some analytics demo data
INSERT INTO analytics_events (user_id, event_type, content_type, content_id, metadata, created_at) VALUES
(1, 'play', 'channel', 1, '{"duration": 3600}', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 'play', 'vod', 1, '{"duration": 1800}', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'play', 'channel', 2, '{"duration": 2400}', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 'play', 'channel', 1, '{"duration": 5400}', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(1, 'play', 'channel', 3, '{"duration": 1200}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'play', 'vod', 2, '{"duration": 5400}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'play', 'channel', 4, '{"duration": 3000}', DATE_SUB(NOW(), INTERVAL 2 DAY));
EOF

    # Import seed data
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$INSTALL_DIR/database/seed.sql"

    log_info "Demo data inserted successfully"
}

# Continue in next function...
install_application_files() {
    log_step "Installing Application Files"

    # This will be called from the main script
    # Application files are created separately for clarity
    log_info "Creating application files..."
}

save_credentials() {
    log_step "Saving Installation Details"

    cat > "$INSTALL_DIR/.env" <<EOF
# CARI-IPTV Environment Configuration
# Generated: $(date)
# WARNING: Keep this file secure!

APP_NAME="CARI-IPTV"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

ADMIN_EMAIL=${ADMIN_EMAIL}

SESSION_LIFETIME=120
CACHE_DRIVER=file

# Stream security
STREAM_SECRET_KEY=$(openssl rand -hex 32)
STREAM_TOKEN_EXPIRY=3600
EOF

    chmod 600 "$INSTALL_DIR/.env"
    chown $WEB_USER:$WEB_GROUP "$INSTALL_DIR/.env"

    # Save credentials to readable file for admin
    cat > "$INSTALL_DIR/INSTALL_CREDENTIALS.txt" <<EOF
╔═══════════════════════════════════════════════════════════════╗
║              CARI-IPTV Installation Complete!                  ║
╚═══════════════════════════════════════════════════════════════╝

Installation Directory: ${INSTALL_DIR}
Installation Date: $(date)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                    DATABASE CREDENTIALS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Database Name: ${DB_NAME}
Database User: ${DB_USER}
Database Pass: ${DB_PASS}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                    ADMIN LOGIN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Admin URL:   http://YOUR_SERVER_IP/admin
Username:    admin
Email:       ${ADMIN_EMAIL}
Password:    ${ADMIN_PASS}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                    IMPORTANT NOTES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Change the admin password after first login!
2. Delete this file after saving credentials securely.
3. Configure your streaming server separately.
4. Set up SSL/HTTPS for production use.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOF

    chmod 600 "$INSTALL_DIR/INSTALL_CREDENTIALS.txt"

    log_info "Credentials saved to: $INSTALL_DIR/INSTALL_CREDENTIALS.txt"
}

print_completion() {
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║          CARI-IPTV Installation Complete!                      ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${BLUE}Admin Panel:${NC}    http://YOUR_SERVER_IP/admin"
    echo -e "  ${BLUE}Username:${NC}       admin"
    echo -e "  ${BLUE}Password:${NC}       ${ADMIN_PASS}"
    echo ""
    echo -e "  ${YELLOW}Credentials saved to:${NC}"
    echo -e "  ${INSTALL_DIR}/INSTALL_CREDENTIALS.txt"
    echo ""
    echo -e "  ${RED}IMPORTANT:${NC} Delete credentials file after saving securely!"
    echo ""
}

# ============================================
# Main Installation Flow
# ============================================
main() {
    print_banner
    parse_args "$@"

    check_root
    detect_os
    check_existing_installation

    # Install based on OS
    case $OS in
        ubuntu|debian)
            install_dependencies_debian
            ;;
        centos|rhel|rocky|almalinux)
            install_dependencies_rhel
            ;;
    esac

    configure_mysql
    configure_php
    create_directory_structure
    configure_nginx
    create_database_schema
    create_seed_data

    # Create application files (called from external script or embedded)
    # The PHP files will be created by a companion script

    save_credentials
    print_completion
}

# Run main function with all arguments
main "$@"
