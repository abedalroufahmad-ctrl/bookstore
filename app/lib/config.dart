// API base URL.
// - Linux/macOS/Windows desktop: localhost
// - Android emulator: 10.0.2.2
// - Physical Android device: your machine's IP (same WiFi as phone)
import 'config_stub.dart' if (dart.library.io) 'config_io.dart' as config;

String get apiBaseUrl => config.getApiBaseUrl();
