#!/bin/bash
#
# CARI VOD Server - Update Script
# Downloads latest source, rebuilds, and restarts the service.
# Preserves configuration and data.
#
set -eo pipefail

# Branch to pull updates from
BRANCH="claude/vod-server-setup-458YL"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[VOD-UPDATE]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Check root
[[ $EUID -ne 0 ]] && err "This script must be run as root (sudo)"

echo ""
log "CARI VOD Server - Update"
echo ""

# ========================
# 1. Check current version
# ========================
CURRENT_VERSION="unknown"
if command -v vod-server &>/dev/null; then
    CURRENT_VERSION=$(vod-server --version 2>/dev/null | head -1 | grep -oP '[0-9]+\.[0-9]+\.[0-9]+' || echo "unknown")
fi
log "Current version: $CURRENT_VERSION"

# ========================
# 2. Ensure build dependencies
# ========================
log "Checking build dependencies..."
MISSING_DEPS=""
for cmd in cmake make gcc; do
    if ! command -v "$cmd" &>/dev/null; then
        MISSING_DEPS="$MISSING_DEPS $cmd"
    fi
done

if [ -n "$MISSING_DEPS" ]; then
    log "Installing missing build tools:$MISSING_DEPS"
    if command -v apt-get &>/dev/null; then
        apt-get update -qq
        apt-get install -y -qq cmake build-essential pkg-config \
            libmicrohttpd-dev libsqlite3-dev libgnutls28-dev 2>/dev/null
    elif command -v dnf &>/dev/null; then
        dnf install -y cmake gcc make pkg-config \
            libmicrohttpd-devel sqlite-devel gnutls-devel 2>/dev/null
    elif command -v yum &>/dev/null; then
        yum install -y cmake gcc make pkg-config \
            libmicrohttpd-devel sqlite-devel gnutls-devel 2>/dev/null
    else
        err "Cannot install build dependencies: unknown package manager. Install cmake, gcc, make manually."
    fi
fi

# ========================
# 2b. Ensure certbot is installed (for Let's Encrypt SSL)
# ========================
if ! command -v certbot &>/dev/null; then
    log "Installing certbot for Let's Encrypt SSL..."
    if command -v snap &>/dev/null; then
        snap install certbot --classic > /dev/null 2>&1 || true
        snap set certbot trust-plugin-with-root=ok > /dev/null 2>&1 || true
        snap install certbot-dns-cloudflare > /dev/null 2>&1 || true
    fi
    if ! command -v certbot &>/dev/null; then
        # Snap failed or unavailable — try pip
        if command -v apt-get &>/dev/null; then
            apt-get install -y -qq python3-pip > /dev/null 2>&1 || true
        fi
        pip3 install certbot certbot-dns-cloudflare > /dev/null 2>&1 || true
    fi
    if command -v certbot &>/dev/null; then
        log "certbot installed: $(certbot --version 2>&1 | head -1)"
    else
        warn "certbot installation failed. Install manually: snap install certbot --classic"
    fi
else
    log "certbot already installed: $(certbot --version 2>&1 | head -1)"
fi

# Ensure certbot renewal hook exists
if [ ! -f /etc/letsencrypt/renewal-hooks/deploy/vod-server.sh ]; then
    mkdir -p /etc/letsencrypt/renewal-hooks/deploy
    cat > /etc/letsencrypt/renewal-hooks/deploy/vod-server.sh << 'HOOK_EOF'
#!/bin/bash
# Certbot renewal hook for VOD Server
VOD_SSL_DIR="/etc/vod-server/ssl"
VOD_CONF="/etc/vod-server/vod-server.conf"
[ -f "$VOD_CONF" ] || exit 0
if [ -n "$RENEWED_LINEAGE" ]; then
    cp -f "$RENEWED_LINEAGE/fullchain.pem" "$VOD_SSL_DIR/cert.pem"
    cp -f "$RENEWED_LINEAGE/privkey.pem" "$VOD_SSL_DIR/key.pem"
    chown vod-server:vod-server "$VOD_SSL_DIR/cert.pem" "$VOD_SSL_DIR/key.pem"
    chmod 644 "$VOD_SSL_DIR/cert.pem"
    chmod 600 "$VOD_SSL_DIR/key.pem"
    systemctl reload vod-server 2>/dev/null || systemctl restart vod-server 2>/dev/null || true
fi
HOOK_EOF
    chmod 755 /etc/letsencrypt/renewal-hooks/deploy/vod-server.sh
    log "Certbot renewal hook installed"
fi

# ========================
# 3. Download latest source
# ========================
TEMP_DIR=$(mktemp -d)
log "Downloading latest source from branch: $BRANCH"

RETRIES=4
DELAY=2
for i in $(seq 1 $RETRIES); do
    if git clone --depth 1 --branch "$BRANCH" \
        https://github.com/caritechsolutions/cari-iptv.git "$TEMP_DIR/cari-iptv" 2>/dev/null; then
        break
    fi
    if [ "$i" -eq "$RETRIES" ]; then
        rm -rf "$TEMP_DIR"
        err "Failed to download source after $RETRIES attempts"
    fi
    warn "Download failed, retrying in ${DELAY}s..."
    sleep $DELAY
    DELAY=$((DELAY * 2))
done

SOURCE_DIR="$TEMP_DIR/cari-iptv/vod-server"

if [ ! -f "$SOURCE_DIR/CMakeLists.txt" ]; then
    rm -rf "$TEMP_DIR"
    err "Source directory missing CMakeLists.txt"
fi

# Get new version
NEW_VERSION=$(grep 'VOD_SERVER_VERSION_MAJOR\|VOD_SERVER_VERSION_MINOR\|VOD_SERVER_VERSION_PATCH' "$SOURCE_DIR/CMakeLists.txt" | \
    grep -oP '[0-9]+' | tr '\n' '.' | sed 's/\.$//')
log "New version: ${NEW_VERSION:-unknown}"

# ========================
# 3. Stop service & pause jobs
# ========================
if systemctl is-active --quiet vod-server; then
    log "Pausing active transcode jobs..."
    kill -USR1 "$(cat /var/run/vod-server.pid 2>/dev/null)" 2>/dev/null || true
    sleep 2
    log "Stopping VOD Server..."
    systemctl stop vod-server 2>/dev/null || true
    sleep 1
fi

# ========================
# 4. Backup current binary
# ========================
if [ -f /usr/local/bin/vod-server ]; then
    cp /usr/local/bin/vod-server /usr/local/bin/vod-server.bak
    log "Current binary backed up to vod-server.bak"
fi

# ========================
# 5. Build
# ========================
log "Building..."

BUILD_DIR="$SOURCE_DIR/build"
mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

BUILD_LOG="$BUILD_DIR/build.log"

cmake "$SOURCE_DIR" -DCMAKE_BUILD_TYPE=Release > "$BUILD_LOG" 2>&1 || {
    warn "CMake configuration failed. Build log:"
    cat "$BUILD_LOG"
    warn "Restoring backup..."
    [ -f /usr/local/bin/vod-server.bak ] && cp /usr/local/bin/vod-server.bak /usr/local/bin/vod-server
    systemctl start vod-server 2>/dev/null || true
    rm -rf "$TEMP_DIR"
    err "CMake configuration failed"
}

make -j$(nproc) >> "$BUILD_LOG" 2>&1 || {
    warn "Compilation failed."
    warn "=== ERRORS ==="
    grep -i "error:" "$BUILD_LOG" || echo "(no lines matching 'error:')"
    warn "=== FULL BUILD LOG ==="
    cat "$BUILD_LOG"
    warn "Restoring backup..."
    [ -f /usr/local/bin/vod-server.bak ] && cp /usr/local/bin/vod-server.bak /usr/local/bin/vod-server
    systemctl start vod-server 2>/dev/null || true
    rm -rf "$TEMP_DIR"
    err "Compilation failed"
}

log "Build successful"

# ========================
# 7. Install new files
# ========================
log "Installing new binary..."
install -m 755 "$BUILD_DIR/vod-server" /usr/local/bin/vod-server

log "Updating Web GUI..."
mkdir -p /usr/local/share/vod-server/www
cp -r "$SOURCE_DIR/www/"* /usr/local/share/vod-server/www/

# Update systemd service (if changed)
install -m 644 "$SOURCE_DIR/scripts/vod-server.service" /etc/systemd/system/vod-server.service
systemctl daemon-reload

# ========================
# 8. Run database migrations
# ========================
log "Running database migrations..."
# The server runs migrations automatically on startup
# No manual step needed here

# ========================
# 9. Restart service
# ========================
log "Starting VOD Server..."
systemctl start vod-server

sleep 2

if systemctl is-active --quiet vod-server; then
    # Verify it responds
    RETRIES=3
    for i in $(seq 1 $RETRIES); do
        if curl -s -o /dev/null -w "%{http_code}" http://localhost:8090/api/status 2>/dev/null | grep -q "200"; then
            log "VOD Server is healthy!"
            break
        fi
        sleep 1
    done
else
    warn "Service failed to start. Restoring backup..."
    if [ -f /usr/local/bin/vod-server.bak ]; then
        cp /usr/local/bin/vod-server.bak /usr/local/bin/vod-server
        systemctl start vod-server
        warn "Reverted to previous version"
    fi
    rm -rf "$TEMP_DIR"
    err "Update failed. Check: journalctl -u vod-server -n 30"
fi

# ========================
# 10. Cleanup
# ========================
rm -rf "$TEMP_DIR"

# Show result
INSTALLED_VERSION=$(vod-server --version 2>/dev/null | head -1 | grep -oP '[0-9]+\.[0-9]+\.[0-9]+' || echo "unknown")

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  VOD Server Update Complete!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "  Previous: $CURRENT_VERSION"
echo "  Current:  $INSTALLED_VERSION"
echo ""
echo "  Status: $(systemctl is-active vod-server)"
echo "  Rollback: cp /usr/local/bin/vod-server.bak /usr/local/bin/vod-server && systemctl restart vod-server"
echo ""
