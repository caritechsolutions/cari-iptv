# CARI TV — mobile app

Flutter client for the CARI-IPTV platform (`/api/v1`). Android first, iOS-ready.

_Full setup, configuration, build, signing and install instructions are completed in the release phase; see `docs/PLAN.md` for progress and `docs/API_DISCOVERY.md` for the API facts the app is built on._

## Quick start
```bash
flutter pub get
flutter run --flavor dev -t lib/main_dev.dart
flutter analyze && flutter test
flutter build apk --debug --flavor dev -t lib/main_dev.dart
```
