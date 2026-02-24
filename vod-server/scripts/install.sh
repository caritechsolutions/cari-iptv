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
        # VOD server build deps
        apt-get install -y -qq \
            build-essential cmake pkg-config \
            libmicrohttpd-dev libsqlite3-dev libgnutls28-dev \
            curl wget git xz-utils \
            > /dev/null 2>&1
        # FFmpeg 8 build deps
        apt-get install -y -qq \
            nasm yasm \
            libx264-dev libx265-dev libfdk-aac-dev libmp3lame-dev libopus-dev \
            libvpx-dev libsvtav1-dev libsvtav1enc-dev libdav1d-dev \
            libass-dev libfreetype6-dev libvorbis-dev \
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
            curl wget git xz \
            > /dev/null 2>&1
        # FFmpeg build deps (may need RPM Fusion / EPEL)
        $PKG install -y -q \
            nasm yasm \
            x264-devel x265-devel fdk-aac-devel lame-devel opus-devel \
            libvpx-devel svt-av1-devel dav1d-devel \
            libass-devel freetype-devel libvorbis-devel \
            > /dev/null 2>&1 || warn "Some FFmpeg codec libraries not available. FFmpeg may build without all codecs."
        # cmake3 alias
        if ! command -v cmake &>/dev/null && command -v cmake3 &>/dev/null; then
            ln -sf /usr/bin/cmake3 /usr/bin/cmake
        fi
        log "Build dependencies installed"
        ;;
    *)
        warn "Unsupported OS: $OS. Please install manually:"
        warn "  - cmake, gcc, pkg-config, nasm, yasm"
        warn "  - libmicrohttpd-dev, libsqlite3-dev, libgnutls-dev"
        warn "  - libx264-dev, libx265-dev, libfdk-aac-dev, libsvtav1-dev"
        ;;
esac

# ========================
# 2. Install FFmpeg 8.0.1
# ========================
FFMPEG_TARGET_VERSION="8"
FFMPEG_SOURCE_URL="https://ffmpeg.org/releases/ffmpeg-8.0.1.tar.xz"
FFMPEG_SOURCE_DIR="ffmpeg-8.0.1"

log "Checking FFmpeg..."

NEED_FFMPEG_BUILD=true
if command -v ffmpeg &>/dev/null; then
    FFMPEG_VERSION=$(ffmpeg -version 2>/dev/null | head -1 | grep -oP 'ffmpeg version \K[0-9]+' || echo "0")
    if [ "$FFMPEG_VERSION" -ge "$FFMPEG_TARGET_VERSION" ]; then
        log "FFmpeg $FFMPEG_VERSION already installed (>= $FFMPEG_TARGET_VERSION)"
        NEED_FFMPEG_BUILD=false
    else
        log "FFmpeg $FFMPEG_VERSION found, upgrading to 8.0.1..."
    fi
fi

if [ "$NEED_FFMPEG_BUILD" = true ]; then
    log "Building FFmpeg 8.0.1 from source (this will take a few minutes)..."

    FFMPEG_BUILD_DIR=$(mktemp -d)
    cd "$FFMPEG_BUILD_DIR"

    # Download
    log "Downloading FFmpeg 8.0.1..."
    wget -q "$FFMPEG_SOURCE_URL" -O ffmpeg.tar.xz || err "Failed to download FFmpeg source"
    tar -xf ffmpeg.tar.xz || err "Failed to extract FFmpeg source"
    cd "$FFMPEG_SOURCE_DIR"

    # Configure with commonly needed codecs
    log "Configuring FFmpeg (detecting available libraries)..."
    FFMPEG_CONFIGURE_FLAGS="
        --prefix=/usr/local
        --enable-gpl
        --enable-nonfree
        --enable-pthreads
        --enable-libx264
        --enable-libx265
        --enable-libmp3lame
        --enable-libopus
        --enable-libvorbis
        --enable-libvpx
        --enable-libass
        --enable-libfreetype
    "

    # Optional codecs — add if dev libs are available
    pkg-config --exists fdk-aac 2>/dev/null && FFMPEG_CONFIGURE_FLAGS="$FFMPEG_CONFIGURE_FLAGS --enable-libfdk-aac"
    pkg-config --exists SvtAv1Enc 2>/dev/null && FFMPEG_CONFIGURE_FLAGS="$FFMPEG_CONFIGURE_FLAGS --enable-libsvtav1"
    pkg-config --exists dav1d 2>/dev/null && FFMPEG_CONFIGURE_FLAGS="$FFMPEG_CONFIGURE_FLAGS --enable-libdav1d"

    ./configure $FFMPEG_CONFIGURE_FLAGS > /dev/null 2>&1 || {
        warn "Full configure failed, trying minimal configuration..."
        ./configure \
            --prefix=/usr/local \
            --enable-gpl \
            --enable-pthreads \
            --enable-libx264 \
            --enable-libx265 \
            > /dev/null 2>&1 || err "FFmpeg configure failed. Check build dependencies."
    }

    # Build
    NPROC=$(nproc 2>/dev/null || echo 2)
    log "Compiling FFmpeg with $NPROC threads..."
    make -j"$NPROC" > /dev/null 2>&1 || err "FFmpeg build failed"

    # Install
    log "Installing FFmpeg..."
    make install > /dev/null 2>&1 || err "FFmpeg install failed"

    # Update library cache
    ldconfig 2>/dev/null || true

    # Verify
    INSTALLED_VER=$(ffmpeg -version 2>/dev/null | head -1 || echo "unknown")
    log "FFmpeg installed: $INSTALLED_VER"

    # Cleanup
    cd /
    rm -rf "$FFMPEG_BUILD_DIR"
fi

# Verify ffprobe is also available
if ! command -v ffprobe &>/dev/null; then
    # Check /usr/local/bin
    if [ -f /usr/local/bin/ffprobe ]; then
        log "ffprobe found at /usr/local/bin/ffprobe"
    else
        warn "ffprobe not found. It should have been installed with FFmpeg."
    fi
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

log "Running cmake..."
cmake "$SOURCE_DIR" -DCMAKE_BUILD_TYPE=Release 2>&1 || err "CMake configuration failed"
log "Running make..."
make -j$(nproc) 2>&1 || err "Build failed"

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
# 7. Install certbot + Cloudflare DNS plugin (for Let's Encrypt behind NAT)
# ========================
log "Installing certbot for Let's Encrypt SSL..."

case "$OS" in
    ubuntu|debian)
        # Try snap first (recommended by certbot), fall back to pip
        if command -v snap &>/dev/null; then
            snap install certbot --classic > /dev/null 2>&1 || true
            snap set certbot trust-plugin-with-root=ok > /dev/null 2>&1 || true
            snap install certbot-dns-cloudflare > /dev/null 2>&1 || true
            if command -v certbot &>/dev/null; then
                log "certbot installed via snap with Cloudflare DNS plugin"
            else
                # Snap failed, try pip
                apt-get install -y -qq python3-pip > /dev/null 2>&1 || true
                pip3 install certbot certbot-dns-cloudflare > /dev/null 2>&1 || true
                log "certbot installed via pip with Cloudflare DNS plugin"
            fi
        else
            apt-get install -y -qq python3-pip > /dev/null 2>&1 || true
            pip3 install certbot certbot-dns-cloudflare > /dev/null 2>&1 || true
            log "certbot installed via pip with Cloudflare DNS plugin"
        fi
        ;;
    centos|rhel|rocky|almalinux|fedora)
        if command -v snap &>/dev/null; then
            snap install certbot --classic > /dev/null 2>&1 || true
            snap set certbot trust-plugin-with-root=ok > /dev/null 2>&1 || true
            snap install certbot-dns-cloudflare > /dev/null 2>&1 || true
        else
            pip3 install certbot certbot-dns-cloudflare > /dev/null 2>&1 || true
        fi
        log "certbot installed with Cloudflare DNS plugin"
        ;;
esac

if command -v certbot &>/dev/null; then
    log "certbot ready: $(certbot --version 2>&1 | head -1)"
else
    warn "certbot installation failed. You can install it manually later."
    warn "  snap install certbot --classic && snap install certbot-dns-cloudflare"
fi

# ========================
# 8. Start service
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
