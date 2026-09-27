# Bookstore App

Flutter client (storefront and staff screens) for the Bookstore API.

**Full documentation:** [project.md](../project.md)

```bash
flutter pub get
flutter run                                          # emulator / simulator (10.0.2.2 or localhost:8000)
flutter run --dart-define=API_HOST=192.168.x.x       # physical device on the same Wi‑Fi
flutter build apk --dart-define=API_BASE_URL=https://api.example.com/api/v1
flutter analyze
```

The API URL is resolved from `--dart-define` in `lib/config.dart` (platform defaults in `config_io.dart` / `config_stub.dart`). Android allows plain HTTP only in debug builds.
