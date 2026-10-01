# Free TV (`freetv`) — client brand

Install: `https://freetvapp.freetv.ng` (API at `/api/v1`, behind a TLS proxy). Application id `ng.freetv.app`, label "Free TV", primary colour `#ED0000` sampled from the logo. The logo on white is the source for all four assets (`icon.png` white background, `icon_foreground.png` transparent mark inside the adaptive safe circle, `logo.png` the original wordmark trimmed at full resolution, `splash.png` the mark on a white rounded square so the black lettering stays readable on the dark splash colour).

Build: `./tool/build_brand.sh freetv apk prod` (release-signed when `key.properties` is present, see below). CI builds the prod release APK alongside caritv on every push (`EXTRA_BRANDS` in `.github/workflows/android-debug.yml`) and attaches it to the `dev-<sha>` prerelease. Nothing is uploaded to any store.

## Signing key (not in the repo)

| | |
|---|---|
| Keystore | `app/brands/freetv/upload-keystore.jks` (PKCS12, gitignored) |
| Alias | `freetv` |
| Passwords | in `app/brands/freetv/key.properties` (gitignored; `storePassword` = `keyPassword`) |
| Validity | 10 000 days from 2026-10-01 |
| Certificate SHA-256 | `88:C4:22:05:87:5F:45:49:C9:58:EA:6D:70:BC:13:77:74:02:B5:44:4D:4B:81:02:AE:75:C1:6C:73:05:24:96` |

The key was generated in the build container and handed over as two files in the chat. The container is ephemeral: keep both files in the password manager / vault and put them back under `app/brands/freetv/` on any machine that must produce a release-signed build. Losing them means losing the ability to update this brand once published (enable Play App Signing on the listing to limit the damage).

CI signing: add repository secrets `FREETV_KEYSTORE_BASE64` (`base64 -w0 upload-keystore.jks`) and `FREETV_KEY_PROPERTIES` (the four lines of `key.properties`). Without them the workflow builds the freetv APK debug-signed and says so in `SIGNING.txt` on the release.

## Discovery facts (2026-10-01, read-only, test subscriber)

- 16 live channels, 0 movies, 0 series, 0 EPG rows (the guide shows placeholder blocks), no ads served for mobile pre-roll, no published mobile layout (the app uses its built-in home), navigation published with Home, Movies, Live TV, Search, My List.
- Every host is HTTPS: `freetvapp.freetv.ng` (API, logos), `streams.playtv.com.ng`, `streams2.playtv.com.ng`, `streams3.playtv.com.ng` (HLS masters with CODECS, no key tag), `media2.streambrothers.com:19360`. `CLEARTEXT_HOSTS` is therefore empty.
- API shape matches ours (`api_version 1.0`, envelopes, login/user/manifest/channel fields). Their install has no `/api/health` and no `features` block (both only exist in our unmerged backend commits), and one extra channel column, `is_4k`, that the app does not read.

## Items for the client

1. **Advocate Broadcasting Network** has an `srt://105.113.54.98:4001` stream URL. Neither Android's nor iOS's player supports SRT, so the channel fails with the "format this device cannot play" screen. It needs an HLS (or DASH/MP4) URL.
2. **Quest TV** (`https://media2.streambrothers.com:19360/...`) reset the TLS connection twice from the build environment, on the stream URL and on the bare host. Check it from a phone on a normal network; if it fails there too, the host or port is blocked on their side.
3. **Package content**: with the Basic package active on the test account, `has_subscription` is true but Basic's content group contains no channels, while 10 of the 16 channels are restricted (in some content group). Those 10 show a padlock and "Not included in your plan" for that account; the 6 unrestricted ones play. Either add the channels to Basic's group or remove them from the other group.
4. Legal pages: `brand.json` points at `/privacy`, `/privacy#terms` and `/delete-account` on `freetvapp.freetv.ng`. Those pages exist only once our `backend:` commits are deployed there.
