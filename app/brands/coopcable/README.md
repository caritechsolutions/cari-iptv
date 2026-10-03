# Coop Cable (`coopcable`) — production brand

Application id `net.caritech.coopcable`, label "Coop Cable", API `https://player.caritech.net` (dev and prod), HTTPS only, `BILLING_UI` auto, legal pages on `player.caritech.net`. Primary colour `#01B1DD`, sampled from the square icon (85% of its pixels); the dark palette is the current theme (`#0F172A` / `#1E293B`, accent `#22C55E`).

## Artwork

Every PNG is rendered from vector sources in `source/`, so any size can be regenerated (`cd source && python3 gen_assets.py`; needs pillow, numpy, cairosvg).

| File | Content |
|---|---|
| `source/gen_mark.py` → `mark-*.svg`, `icon.svg` | The TV mark, measured sub-pixel from the supplied 192×192 icon (`logo_1769696262_0119378f.png`) and rebuilt as paths. Overlap with the source 0.91 at 192 px; the rest is edge antialiasing. |
| `source/gen_wordmark.py` → `lettering-*.svg` | "CABLE", measured from the supplied `320x180_white.png` and rebuilt as paths (overlap 0.99). No font is used: none of the open condensed faces tried (Anton, Bebas Neue, League Gothic, Oswald, Fjalla One, Antonio, Six Caps, Pathway Gothic One) matched. |
| `source/gen_assets.py` → `logo*.svg` and the PNGs | Composition and rendering. |
| `source/comparison.png` | Side-by-side of the redraws against both supplied files. |
| `icon.png` 1024² | White mark on `#01B1DD`, margins as in the square source (mark 54% of the width). |
| `icon_foreground.png` 1024², transparent | White mark only, scaled so its enclosing circle sits 4% inside the adaptive safe circle (66/108). Adaptive background `ICON_BACKGROUND_COLOR` = `#01B1DD`. |
| `splash.png` 1024² | White mark centred on `#01B1DD`; `SPLASH_COLOR` is the same blue, so the splash is a seamless white mark on blue. |
| `logo_dark.png` 1795×594 | Mark in `#01B1DD` + white CABLE, the supplied wordmark's layout (mark 96 : cap height 54.8 : gap 21). Used on the app's dark screens. |
| `logo.png` 1795×594 | Same with CABLE in `#0F172A` for light backgrounds. |

The square drawing of the mark is used everywhere. The supplied wordmark carries the same drawing in a darker blue (`#0098D2`); the icon blue `#01B1DD` wins, per the brand decision of 2026-10-03.

`LOGO_HAS_NAME` is true: the wordmark says CABLE, so the app prints no name beside it.

Build: `./tool/build_brand.sh coopcable apk prod` (release-signed when `key.properties` is present). CI builds the prod release APK on every push (`EXTRA_BRANDS` in `.github/workflows/android-debug.yml`) and attaches it to the `dev-<sha>` prerelease. Nothing is uploaded to any store.

## Signing key (not in the repo)

| | |
|---|---|
| Keystore | `app/brands/coopcable/upload-keystore.jks` (PKCS12, gitignored) |
| Alias | `coopcable` |
| Passwords | in `app/brands/coopcable/key.properties` (gitignored; `storePassword` = `keyPassword`) |
| Subject | `CN=Coop Cable, O=Coop Cable, C=TT` |
| Validity | 10 000 days from 2026-10-03 |
| Certificate SHA-256 | `1C:84:0D:63:C9:A6:CA:4C:FE:3C:1E:01:0A:17:D1:10:42:90:77:EC:D1:C9:1E:23:B8:0F:FB:20:0B:B0:4F:07` |

The key was generated in the build container and handed over as two files in the chat. The container is ephemeral: keep both files in the password manager / vault and put them back under `app/brands/coopcable/` on any machine that must produce a release-signed build. Losing them means losing the ability to update this brand once published (enable Play App Signing on the listing to limit the damage).

CI signing: add repository secrets `COOPCABLE_KEYSTORE_BASE64` (`base64 -w0 upload-keystore.jks`) and `COOPCABLE_KEY_PROPERTIES` (the four lines of `key.properties`). Without them the workflow builds the coopcable APK debug-signed and says so in `SIGNING.txt` on the release.
