# TSDuck Packages

Place TSDuck `.deb` packages here for offline installation.

## Required Files

Download from: https://github.com/tsduck/tsduck/releases

For **Ubuntu 24.04** (amd64):
- `tsduck_3.43-4549.ubuntu24_amd64.deb`

For **Ubuntu 24.04** (arm64):
- `tsduck_3.43-4549.ubuntu24_arm64.deb`

For **Ubuntu 22.04** (amd64):
- `tsduck_3.33-3139.ubuntu22_amd64.deb`

## How it works

The install/update scripts will:
1. First check this `packages/` directory for local `.deb` files
2. If not found, attempt to download from the Caricoder2 repository
3. If that fails, attempt to download from TSDuck GitHub releases
4. If all downloads fail, display instructions for manual download

## Note

These packages are not included in the git repository due to their size (~30MB each).
Users should download the appropriate package for their architecture.
