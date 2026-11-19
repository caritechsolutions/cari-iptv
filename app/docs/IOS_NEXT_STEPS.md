# iOS — how it is built and shipped

iOS is built on GitHub's macOS runners (`.github/workflows/ios.yml`), never on a
developer Mac. Nothing in the Xcode project is edited by hand: `tool/apply_brand.dart`
writes the bundle id, display name, ATS exceptions, the Google URL scheme and the
Sign in with Apple entitlement from `brands/<brand>/brand.json`.

## Running a build

Actions → **iOS** → Run workflow → brand. (Or push with `[ios]` in the commit
message.) macOS minutes cost 10× Linux ones, so it is not run on every push.

- Without Apple secrets for the brand: unsigned build, `Runner.app` attached as an
  artifact. Proves the iOS target compiles.
- With secrets: signed archive uploaded straight to App Store Connect; the build
  shows in **TestFlight** a few minutes later. Testers install the TestFlight app
  and get builds from an invite link.

## Per-brand secrets (repository secrets, `<BRAND>_` prefix, e.g. `FREETV_`)

| Secret | Where it comes from |
|---|---|
| `FREETV_APPLE_TEAM_ID` | developer.apple.com → Membership details → Team ID |
| `FREETV_ASC_KEY_ID` / `FREETV_ASC_ISSUER_ID` / `FREETV_ASC_KEY_P8` | App Store Connect → Users and Access → Integrations → App Store Connect API → Team keys → generate (role **App Manager** or Admin). Key ID and Issuer ID are shown; the `.p8` downloads once — paste its full contents as `ASC_KEY_P8`. |

Signing is Xcode "automatic" with a cloud-managed Apple Distribution certificate,
authenticated by that API key (`xcodebuild -allowProvisioningUpdates`), so no
certificate or profile is exported from anywhere.

## One-time setup in the operator's Apple account

1. developer.apple.com → Identifiers → App ID for the bundle id (`com.freetv.ng`),
   capability **Sign in with Apple** ticked.
2. App Store Connect → Apps → **New App**: platform iOS, that bundle id, name, SKU.
3. (Google sign-in on iOS) Google Cloud → Clients → **iOS** client with the bundle
   id; put its client id in `brand.json` `IOS_GOOGLE_CLIENT_ID`. Add it to the
   backend's Sign-in tab "Additional accepted client IDs" only if the ID tokens
   fail audience checks (they should not: `serverClientId` is the web client).
4. (Sign in with Apple, required alongside Google) Identifiers → Services ID for
   the web player + Keys → Sign in with Apple key; enter Team ID, Services ID,
   Key ID and the `.p8` in the backend Settings → Sign-in → Apple.

## Store submission checklist

Privacy policy `/privacy`, terms `/terms`, in-app account deletion (exists),
App Privacy answers (see STORE_READINESS.md), screenshots (6.7" and 6.1" iPhone,
13" iPad if iPad is enabled), a demo account with content for App Review, and
HTTPS streams only (ATS exceptions are declared from `CLEARTEXT_HOSTS` but
reviewers question them).
