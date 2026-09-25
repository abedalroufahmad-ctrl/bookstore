import 'dart:io' show Platform;

/// API base URL for native platforms, configured at build time:
///
///   flutter build apk --dart-define=API_BASE_URL=https://api.example.com/api/v1
///
/// For local development pass the host instead (HTTP is only allowed in debug builds):
///   Physical device (same Wi‑Fi):  flutter run --dart-define=API_HOST=192.168.x.x
///   Android emulator:              flutter run   (defaults to 10.0.2.2)
///   iOS simulator / desktop:       flutter run   (defaults to localhost)
String getApiBaseUrl() {
  if (_apiBaseUrl.isNotEmpty) {
    return _apiBaseUrl;
  }
  final host = _apiHost.isNotEmpty
      ? _apiHost
      : (Platform.isAndroid ? '10.0.2.2' : 'localhost');
  return '$_apiScheme://$host:$_apiPort/api/v1';
}

const String _apiBaseUrl = String.fromEnvironment('API_BASE_URL');
const String _apiHost = String.fromEnvironment('API_HOST');
const String _apiScheme = String.fromEnvironment('API_SCHEME', defaultValue: 'http');
const String _apiPort = String.fromEnvironment('API_PORT', defaultValue: '8000');
