#!/bin/bash
#
# CARI-IPTV Platform Updater
# Updates an existing CARI-IPTV installation to the latest version
#
# Usage: curl -sSL https://raw.githubusercontent.com/caritechsolutions/cari-iptv/main/update.sh | sudo bash
#
# Or with options:
# curl -sSL https://url/update.sh | sudo bash -s -- --install-dir=/var/www/cari-iptv --backup
#

set -e

# ============================================
# Configuration
# ============================================
INSTALL_DIR="/var/www/cari-iptv"
REPO_URL="https://github.com/caritechsolutions/cari-iptv.git"
BRANCH="claude/plan-rollout-strategy-eoaxF"
BACKUP_ENABLED=false
BACKUP_DIR="/var/backups/cari-iptv"
WEB_USER="www-data"
WEB_GROUP="www-data"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============================================
# Helper Functions
# ============================================
print_banner() {
    echo -e "${BLUE}"
    echo "╔═══════════════════════════════════════════════════════════╗"
    echo "║           CARI-IPTV Platform Updater                      ║"
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

# ============================================
# Parse Arguments
# ============================================
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --install-dir=*)
                INSTALL_DIR="${1#*=}"
                shift
                ;;
            --branch=*)
                BRANCH="${1#*=}"
                shift
                ;;
            --backup)
                BACKUP_ENABLED=true
                shift
                ;;
            --backup-dir=*)
                BACKUP_DIR="${1#*=}"
                BACKUP_ENABLED=true
                shift
                ;;
            --help)
                echo "CARI-IPTV Updater"
                echo ""
                echo "Usage: $0 [options]"
                echo ""
                echo "Options:"
                echo "  --install-dir=PATH    Installation directory (default: /var/www/cari-iptv)"
                echo "  --branch=BRANCH       Git branch to update from (default: main)"
                echo "  --backup              Create backup before updating"
                echo "  --backup-dir=PATH     Backup directory (default: /var/backups/cari-iptv)"
                echo "  --help                Show this help message"
                exit 0
                ;;
            *)
                log_warn "Unknown option: $1"
                shift
                ;;
        esac
    done
}

# ============================================
# Pre-flight Checks
# ============================================
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

check_installation() {
    if [ ! -d "$INSTALL_DIR" ]; then
        log_error "Installation not found at $INSTALL_DIR"
        log_error "Please run the installer first or specify --install-dir"
        exit 1
    fi

    if [ ! -f "$INSTALL_DIR/.env" ]; then
        log_error "Configuration file not found: $INSTALL_DIR/.env"
        log_error "This doesn't appear to be a valid CARI-IPTV installation"
        exit 1
    fi

    log_info "Found installation at: $INSTALL_DIR"
}

detect_web_user() {
    # Try to detect web user from existing files
    if [ -f "$INSTALL_DIR/public/index.php" ]; then
        WEB_USER=$(stat -c '%U' "$INSTALL_DIR/public/index.php" 2>/dev/null || echo "www-data")
        WEB_GROUP=$(stat -c '%G' "$INSTALL_DIR/public/index.php" 2>/dev/null || echo "www-data")
    fi
    log_info "Web user: $WEB_USER:$WEB_GROUP"
}

get_current_version() {
    if [ -f "$INSTALL_DIR/version.txt" ]; then
        CURRENT_VERSION=$(cat "$INSTALL_DIR/version.txt")
    else
        CURRENT_VERSION="unknown"
    fi
    log_info "Current version: $CURRENT_VERSION"
}

# ============================================
# Backup Functions
# ============================================
create_backup() {
    if [ "$BACKUP_ENABLED" = false ]; then
        return
    fi

    log_step "Creating Backup"

    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_PATH="$BACKUP_DIR/backup_$TIMESTAMP"

    mkdir -p "$BACKUP_PATH"

    # Backup application files (excluding storage contents)
    log_info "Backing up application files..."
    rsync -a --exclude='storage/logs/*' \
             --exclude='storage/cache/*' \
             --exclude='storage/sessions/*' \
             "$INSTALL_DIR/" "$BACKUP_PATH/files/"

    # Backup database
    log_info "Backing up database..."
    if [ -f "$INSTALL_DIR/.env" ]; then
        # Extract database credentials from .env
        DB_NAME=$(grep "^DB_DATABASE=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
        DB_USER=$(grep "^DB_USERNAME=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
        DB_PASS=$(grep "^DB_PASSWORD=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")

        if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
            mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_PATH/database.sql" 2>/dev/null || {
                log_warn "Database backup failed - continuing without it"
            }
        fi
    fi

    # Create backup manifest
    cat > "$BACKUP_PATH/manifest.txt" <<EOF
CARI-IPTV Backup
================
Date: $(date)
Version: $CURRENT_VERSION
Install Dir: $INSTALL_DIR
EOF

    log_info "Backup created: $BACKUP_PATH"

    # Cleanup old backups (keep last 5)
    cd "$BACKUP_DIR"
    ls -t | tail -n +6 | xargs -r rm -rf
}

# ============================================
# Update Functions
# ============================================
enable_maintenance_mode() {
    log_info "Enabling maintenance mode..."
    cat > "$INSTALL_DIR/public/maintenance.html" <<'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Maintenance - CARI-IPTV</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { text-align: center; }
        h1 { color: #6366f1; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Under Maintenance</h1>
        <p>We're updating the platform. Please check back in a few minutes.</p>
    </div>
</body>
</html>
EOF

    # Create maintenance flag
    touch "$INSTALL_DIR/.maintenance"
}

disable_maintenance_mode() {
    log_info "Disabling maintenance mode..."
    rm -f "$INSTALL_DIR/public/maintenance.html"
    rm -f "$INSTALL_DIR/.maintenance"
}

download_update() {
    log_step "Downloading Update"

    # Create temp directory
    TEMP_DIR=$(mktemp -d)
    cd "$TEMP_DIR"

    # Download the repository as a tarball (no git needed, avoids caching)
    # Add timestamp to URL to bypass any caching
    local TARBALL_URL="${REPO_URL}/archive/refs/heads/${BRANCH}.tar.gz?$(date +%s)"
    log_info "Fetching latest code from $BRANCH branch..."

    local DOWNLOAD_OK=false
    if command -v wget &> /dev/null; then
        # Use wget with timeout and progress bar
        if wget --no-check-certificate --timeout=60 --tries=2 --progress=bar:force -O repo.tar.gz "$TARBALL_URL" 2>&1; then
            DOWNLOAD_OK=true
        fi
    else
        # Use curl with timeout and progress bar
        if curl -k -L -f --connect-timeout 30 --max-time 120 --progress-bar -o repo.tar.gz "$TARBALL_URL"; then
            DOWNLOAD_OK=true
        fi
    fi

    if [[ "$DOWNLOAD_OK" = false ]] || [[ ! -f repo.tar.gz ]] || [[ ! -s repo.tar.gz ]]; then
        log_error "Failed to download update from repository (check network connection)"
        log_error "Tried URL: $TARBALL_URL"
        rm -rf "$TEMP_DIR"
        exit 1
    fi

    # Extract
    tar -xzf repo.tar.gz
    EXTRACTED_DIR=$(ls -d cari-iptv-* 2>/dev/null | head -1)

    if [[ -z "$EXTRACTED_DIR" || ! -d "$EXTRACTED_DIR" ]]; then
        log_error "Failed to extract repository"
        rm -rf "$TEMP_DIR"
        exit 1
    fi

    mv "$EXTRACTED_DIR" cari-iptv

    # Verify extraction succeeded
    if [ ! -f "cari-iptv/public/index.php" ]; then
        log_error "Download succeeded but files are missing"
        rm -rf "$TEMP_DIR"
        exit 1
    fi

    # Get new version
    if [ -f "cari-iptv/version.txt" ]; then
        NEW_VERSION=$(cat cari-iptv/version.txt)
    else
        NEW_VERSION="$(date +%Y.%m.%d)"
    fi

    log_info "Download complete - Version: $NEW_VERSION"
}

apply_update() {
    log_step "Applying Update"

    cd "$TEMP_DIR/cari-iptv"

    # Update application files
    log_info "Updating application files..."

    # Use rsync if available, otherwise fall back to cp
    if command -v rsync &> /dev/null; then
        # Copy new public files (preserve .htaccess if exists)
        rsync -a --exclude='.htaccess' public/ "$INSTALL_DIR/public/"
        rsync -a src/ "$INSTALL_DIR/src/"
        rsync -a templates/ "$INSTALL_DIR/templates/"
    else
        # Fallback to cp
        cp -r public/* "$INSTALL_DIR/public/" 2>/dev/null || true
        cp -r src/* "$INSTALL_DIR/src/" 2>/dev/null || true
        cp -r templates/* "$INSTALL_DIR/templates/" 2>/dev/null || true
    fi

    # Copy new database migrations (if any) - delete old ones first to ensure clean copy
    if [ -d "database/migrations" ]; then
        log_info "Found migrations in download, copying to $INSTALL_DIR/database/migrations/"
        rm -rf "$INSTALL_DIR/database/migrations"
        mkdir -p "$INSTALL_DIR/database/migrations"
        cp -rv database/migrations/* "$INSTALL_DIR/database/migrations/"
        log_info "Migration file content check (line 19):"
        sed -n '19p' "$INSTALL_DIR/database/migrations/002_create_settings_table.sql" 2>/dev/null || echo "File not found"
    else
        log_warn "No database/migrations directory found in download"
        log_info "Current directory: $(pwd)"
        log_info "Contents: $(ls -la)"
    fi

    # Update schema file
    if [ -f "database/schema.sql" ]; then
        cp database/schema.sql "$INSTALL_DIR/database/schema.sql"
    fi

    # Copy other root files (excluding sensitive ones)
    for file in composer.json install.sh update.sh; do
        if [ -f "$file" ]; then
            cp "$file" "$INSTALL_DIR/"
        fi
    done

    # Update version file
    echo "$NEW_VERSION" > "$INSTALL_DIR/version.txt"

    log_info "Files updated successfully"
}

run_migrations() {
    log_step "Running Database Migrations"

    MIGRATIONS_DIR="$INSTALL_DIR/database/migrations"

    if [ ! -d "$MIGRATIONS_DIR" ]; then
        log_info "No migrations directory found - skipping"
        return
    fi

    # Get database credentials
    DB_NAME=$(grep "^DB_DATABASE=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    DB_USER=$(grep "^DB_USERNAME=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    DB_PASS=$(grep "^DB_PASSWORD=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")

    # Create migrations tracking table if not exists
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF 2>/dev/null || true
CREATE TABLE IF NOT EXISTS _migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
EOF

    # Clean up incomplete migrations before running
    # Check for settings table migration - if table exists but empty, remove record to retry
    SETTINGS_EXISTS=$(mysql -u "$DB_USER" -p"$DB_PASS" -N -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='settings'" "$DB_NAME" 2>/dev/null || echo "0")

    if [ "$SETTINGS_EXISTS" = "1" ]; then
        SETTINGS_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -N -e \
            "SELECT COUNT(*) FROM settings" "$DB_NAME" 2>/dev/null || echo "0")
        if [ "$SETTINGS_COUNT" = "0" ]; then
            log_info "Detected incomplete settings migration - will retry"
            mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
                "DELETE FROM _migrations WHERE filename LIKE '%settings%'" 2>/dev/null || true
        fi
    fi

    # Run pending migrations
    MIGRATION_COUNT=0
    for migration in $(ls -1 "$MIGRATIONS_DIR"/*.sql 2>/dev/null | sort); do
        FILENAME=$(basename "$migration")

        # Check if already executed
        EXECUTED=$(mysql -u "$DB_USER" -p"$DB_PASS" -N -e \
            "SELECT COUNT(*) FROM _migrations WHERE filename='$FILENAME'" "$DB_NAME" 2>/dev/null || echo "0")

        if [ "$EXECUTED" = "0" ]; then
            log_info "Running migration: $FILENAME"

            # Temporarily disable exit on error for migration
            set +e
            MIGRATION_OUTPUT=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$migration" 2>&1)
            MIGRATION_RESULT=$?
            set -e

            if [ $MIGRATION_RESULT -ne 0 ]; then
                log_error "Migration failed: $FILENAME"
                log_error "$MIGRATION_OUTPUT"
                # Don't record failed migrations - they'll be retried next time
                continue
            fi

            # Record migration only after successful execution
            mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e \
                "INSERT IGNORE INTO _migrations (filename) VALUES ('$FILENAME')" 2>/dev/null || true

            MIGRATION_COUNT=$((MIGRATION_COUNT + 1))
            log_info "Migration completed: $FILENAME"
        fi
    done

    if [ "$MIGRATION_COUNT" -gt 0 ]; then
        log_info "Executed $MIGRATION_COUNT migration(s)"
    else
        log_info "No pending migrations"
    fi
}

fix_permissions() {
    log_step "Fixing Permissions"

    chown -R "$WEB_USER:$WEB_GROUP" "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 775 "$INSTALL_DIR/storage"

    # Make scripts executable
    chmod +x "$INSTALL_DIR/install.sh" 2>/dev/null || true
    chmod +x "$INSTALL_DIR/update.sh" 2>/dev/null || true

    log_info "Permissions updated"
}

clear_cache() {
    log_step "Clearing Cache"

    # Clear application cache
    rm -rf "$INSTALL_DIR/storage/cache/"* 2>/dev/null || true

    # Clear OPcache if PHP-FPM is running
    if command -v php &> /dev/null; then
        php -r "if(function_exists('opcache_reset')) opcache_reset();" 2>/dev/null || true
    fi

    # Restart PHP-FPM to clear any cached bytecode
    systemctl restart php*-fpm 2>/dev/null || true

    log_info "Cache cleared"
}

restart_services() {
    log_step "Restarting Services"

    # Restart PHP-FPM
    if systemctl is-active --quiet php8.2-fpm 2>/dev/null; then
        systemctl restart php8.2-fpm
        log_info "Restarted php8.2-fpm"
    elif systemctl is-active --quiet php8.1-fpm 2>/dev/null; then
        systemctl restart php8.1-fpm
        log_info "Restarted php8.1-fpm"
    elif systemctl is-active --quiet php-fpm 2>/dev/null; then
        systemctl restart php-fpm
        log_info "Restarted php-fpm"
    fi

    # Reload Nginx
    if systemctl is-active --quiet nginx 2>/dev/null; then
        nginx -t && systemctl reload nginx
        log_info "Reloaded nginx"
    fi
}

cleanup() {
    log_info "Cleaning up temporary files..."
    rm -rf "$TEMP_DIR" 2>/dev/null || true
}

print_completion() {
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║              CARI-IPTV Update Complete!                        ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${BLUE}Previous Version:${NC}  $CURRENT_VERSION"
    echo -e "  ${BLUE}New Version:${NC}       $NEW_VERSION"
    echo -e "  ${BLUE}Install Directory:${NC} $INSTALL_DIR"
    if [ "$BACKUP_ENABLED" = true ]; then
        echo -e "  ${BLUE}Backup Location:${NC}   $BACKUP_PATH"
    fi
    echo ""
    echo -e "  ${YELLOW}Please verify the update by logging into the admin panel.${NC}"
    echo ""
}

# ============================================
# Rollback Function
# ============================================
rollback() {
    log_error "Update failed! Attempting rollback..."

    if [ "$BACKUP_ENABLED" = true ] && [ -d "$BACKUP_PATH/files" ]; then
        rsync -a "$BACKUP_PATH/files/" "$INSTALL_DIR/"
        log_info "Files restored from backup"

        if [ -f "$BACKUP_PATH/database.sql" ]; then
            DB_NAME=$(grep "^DB_DATABASE=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
            DB_USER=$(grep "^DB_USERNAME=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
            DB_PASS=$(grep "^DB_PASSWORD=" "$INSTALL_DIR/.env" | cut -d'=' -f2 | tr -d '"' | tr -d "'")
            mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_PATH/database.sql"
            log_info "Database restored from backup"
        fi
    else
        log_error "No backup available for rollback!"
    fi

    disable_maintenance_mode
    cleanup
    exit 1
}

# ============================================
# Main
# ============================================
main() {
    print_banner
    parse_args "$@"

    check_root
    check_installation
    detect_web_user
    get_current_version

    create_backup
    enable_maintenance_mode

    # Set trap for rollback on failure
    trap rollback ERR

    download_update
    apply_update
    run_migrations
    fix_permissions
    clear_cache

    disable_maintenance_mode
    restart_services
    cleanup

    # Remove trap
    trap - ERR

    print_completion
}

main "$@"
