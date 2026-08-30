import 'dart:io' show Platform;

/// API base URL for native platforms.
///
/// Physical Android device (same Wi‑Fi as the PC):
///   flutter run --dart-define=API_HOST=192.168.x.x
/// Android emulator:
///   flutter run --dart-define=API_HOST=10.0.2.2
///   (or omit — default is the machine LAN IP below for physical phones)
String getApiBaseUrl() {
  if (Platform.isAndroid) {
    return 'http://$_androidApiHost:8000/api/v1';
  }
  if (Platform.isIOS) {
    // iOS simulator can use localhost; physical iPhone needs LAN IP via API_HOST.
    return 'http://$_iosApiHost:8000/api/v1';
  }
  return 'http://localhost:8000/api/v1';
}

/// Host of the Laravel API from the device's perspective.
/// Override: `--dart-define=API_HOST=YOUR_LAN_IP` or `10.0.2.2` for emulator.
const String _androidApiHost = String.fromEnvironment(
  'API_HOST',
  defaultValue: '192.168.117.197',
);

const String _iosApiHost = String.fromEnvironment(
  'API_HOST',
  defaultValue: 'localhost',
);
