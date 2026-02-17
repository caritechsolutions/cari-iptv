#!/bin/bash
#
# CARI VOD Server - Installation Script
# Installs dependencies, builds from source, and configures the service.
#
set -eo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${GREEN}[VOD-SERVER]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# Check root
[[ $EUID -ne 0 ]] && err "This script must be run as root (sudo)"

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    OS_VERSION=$VERSION_ID
else
    err "Cannot detect operating system"
fi

log "Installing CARI VOD Server on $OS $OS_VERSION"
echo ""

# ========================
# 1. Install dependencies
# ========================
log "Installing build dependencies..."

case "$OS" in
    ubuntu|debian)
        export DEBIAN_FRONTEND=noninteractive
        apt-get update -qq
        apt-get install -y -qq \
            build-essential cmake pkg-config \
            libmicrohttpd-dev libsqlite3-dev libgnutls28-dev \
            curl wget git \
            > /dev/null 2>&1
        log "Build dependencies installed"
        ;;
    centos|rhel|rocky|almalinux|fedora)
        if command -v dnf &>/dev/null; then
            PKG="dnf"
        else
            PKG="yum"
        fi
        $PKG install -y -q \
            gcc gcc-c++ cmake3 pkgconfig \
            libmicrohttpd-devel sqlite-devel gnutls-devel \
            curl wget git \
            > /dev/null 2>&1
        # cmake3 alias
        if ! command -v cmake &>/dev/null && command -v cmake3 &>/dev/null; then
            ln -sf /usr/bin/cmake3 /usr/bin/cmake
        fi
        log "Build dependencies installed"
        ;;
    *)
        warn "Unsupported OS: $OS. Please install manually:"
        warn "  - cmake, gcc, pkg-config"
        warn "  - libmicrohttpd-dev, libsqlite3-dev, libgnutls-dev"
        ;;
esac

# ========================
# 2. Install FFmpeg 8
# ========================
log "Checking FFmpeg..."

FFMPEG_INSTALLED=false
if command -v ffmpeg &>/dev/null; then
    FFMPEG_VERSION=$(ffmpeg -version 2>/dev/null | head -1 | grep -oP 'ffmpeg version \K[0-9]+' || echo "0")
    if [ "$FFMPEG_VERSION" -ge 7 ]; then
        log "FFmpeg $FFMPEG_VERSION already installed"
        FFMPEG_INSTALLED=true
    else
        warn "FFmpeg $FFMPEG_VERSION found but version 7+ recommended"
        FFMPEG_INSTALLED=true
    fi
fi

if [ "$FFMPEG_INSTALLED" = false ]; then
    log "Installing FFmpeg..."
    case "$OS" in
        ubuntu|debian)
            apt-get install -y -qq ffmpeg > /dev/null 2>&1 || {
                warn "Could not install FFmpeg from repos. Please install FFmpeg 7+ manually."
                warn "See: https://ffmpeg.org/download.html"
            }
            ;;
        centos|rhel|rocky|almalinux|fedora)
            # Try RPM Fusion
            $PKG install -y -q ffmpeg ffmpeg-devel > /dev/null 2>&1 || {
                warn "Could not install FFmpeg from repos. Please install FFmpeg 7+ manually."
            }
            ;;
    esac
fi

# ========================
# 3. Install GPAC/MP4Box
# ========================
log "Checking MP4Box (GPAC)..."

if ! command -v MP4Box &>/dev/null; then
    log "Installing GPAC..."
    case "$OS" in
        ubuntu|debian)
            apt-get install -y -qq gpac > /dev/null 2>&1 || {
                warn "Could not install GPAC/MP4Box. CMAF packaging will use FFmpeg fallback."
            }
            ;;
        centos|rhel|rocky|almalinux|fedora)
            $PKG install -y -q gpac > /dev/null 2>&1 || {
                warn "Could not install GPAC/MP4Box. CMAF packaging will use FFmpeg fallback."
            }
            ;;
    esac
else
    log "MP4Box already installed"
fi

# ========================
# 4. Build VOD Server
# ========================
log "Building VOD Server..."

# Branch to pull from
BRANCH="${BRANCH:-claude/vod-server-setup-458YL}"

# Determine source directory
SCRIPT_DIR="$(cd "$(dirname "$0")" 2>/dev/null && pwd || echo /tmp)"
SOURCE_DIR="$(dirname "$SCRIPT_DIR")"

# Check if we're running from the repo
if [ ! -f "$SOURCE_DIR/CMakeLists.txt" ]; then
    # Download from git
    TEMP_DIR=$(mktemp -d)
    log "Downloading source from branch: $BRANCH"
    git clone --depth 1 --branch "$BRANCH" \
        https://github.com/caritechsolutions/cari-iptv.git "$TEMP_DIR/cari-iptv" 2>/dev/null || \
        err "Failed to clone repository"
    SOURCE_DIR="$TEMP_DIR/cari-iptv/vod-server"
fi

BUILD_DIR="$SOURCE_DIR/build"
mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

cmake "$SOURCE_DIR" -DCMAKE_BUILD_TYPE=Release > /dev/null 2>&1 || err "CMake configuration failed"
make -j$(nproc) > /dev/null 2>&1 || err "Build failed"

log "Build successful"

# ========================
# 5. Install binary and files
# ========================
log "Installing..."

# Binary
install -m 755 "$BUILD_DIR/vod-server" /usr/local/bin/vod-server

# Web GUI
mkdir -p /usr/local/share/vod-server/www
cp -r "$SOURCE_DIR/www/"* /usr/local/share/vod-server/www/

# Config (don't overwrite existing)
mkdir -p /etc/vod-server/ssl
if [ ! -f /etc/vod-server/vod-server.conf ]; then
    install -m 640 "$SOURCE_DIR/config/vod-server.conf" /etc/vod-server/vod-server.conf
    log "Default config installed at /etc/vod-server/vod-server.conf"
else
    log "Config file already exists, not overwriting"
fi

# Create user
if ! id -u vod-server &>/dev/null; then
    useradd --system --no-create-home --shell /usr/sbin/nologin vod-server
    log "Created system user: vod-server"
fi

# Create directories
mkdir -p /var/lib/vod-server/{library,tmp}
mkdir -p /var/log/vod-server
chown -R vod-server:vod-server /var/lib/vod-server
chown -R vod-server:vod-server /var/log/vod-server
chown -R vod-server:vod-server /etc/vod-server

# Systemd service
install -m 644 "$SOURCE_DIR/scripts/vod-server.service" /etc/systemd/system/vod-server.service
systemctl daemon-reload

# ========================
# 6. Generate API key
# ========================
if grep -q "change-me-on-first-run" /etc/vod-server/vod-server.conf; then
    API_KEY=$(openssl rand -hex 32)
    sed -i "s/change-me-on-first-run/$API_KEY/" /etc/vod-server/vod-server.conf
    log "Generated API key: $API_KEY"
    echo ""
    echo -e "${CYAN}=========================================${NC}"
    echo -e "${CYAN}  SAVE YOUR API KEY:${NC}"
    echo -e "${CYAN}  $API_KEY${NC}"
    echo -e "${CYAN}=========================================${NC}"
    echo ""
fi

# ========================
# 7. Start service
# ========================
log "Starting VOD Server..."
systemctl enable vod-server > /dev/null 2>&1
systemctl start vod-server

sleep 2

if systemctl is-active --quiet vod-server; then
    log "VOD Server is running!"
else
    warn "Service failed to start. Check: journalctl -u vod-server -n 20"
fi

# Clean up temp if we downloaded
[ -n "${TEMP_DIR:-}" ] && rm -rf "$TEMP_DIR"

echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  CARI VOD Server Installation Complete!${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "  Web GUI:  http://$(hostname -I | awk '{print $1}'):8090"
echo "  Config:   /etc/vod-server/vod-server.conf"
echo "  Logs:     /var/log/vod-server/vod-server.log"
echo "  Library:  /var/lib/vod-server/library"
echo ""
echo "  Commands:"
echo "    systemctl status vod-server    # Check status"
echo "    systemctl restart vod-server   # Restart"
echo "    journalctl -u vod-server -f    # Follow logs"
echo ""
echo "  To change library path:"
echo "    1. Edit /etc/vod-server/vod-server.conf"
echo "    2. Update library_path to your mount point"
echo "    3. Add ReadWritePaths= to the systemd service"
echo "    4. systemctl daemon-reload && systemctl restart vod-server"
echo ""
