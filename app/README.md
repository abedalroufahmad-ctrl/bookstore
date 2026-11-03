# Bookstore App

Flutter client for the bookstore platform. It handles customer browsing and checkout flows, guest access, and staff screens such as admin tools and direct-sales POS views, with a navy light/dark theme matching the web app.

**Project docs:** [project.md](../project.md)

```bash
flutter pub get
flutter run                                          # emulator / simulator (10.0.2.2 or localhost:8000)
flutter run --dart-define=API_HOST=192.168.x.x       # physical device on the same Wi‑Fi
flutter build apk --dart-define=API_BASE_URL=https://api.example.com/api/v1
flutter analyze
```

The API URL is resolved from `--dart-define` in `lib/config.dart` (platform defaults in `config_io.dart` and `config_stub.dart`). Other defines include `API_SCHEME` (`http` by default) and `API_PORT` (`8000` by default). Android allows plain HTTP only in debug builds; CI runs `dart analyze --fatal-infos`.
