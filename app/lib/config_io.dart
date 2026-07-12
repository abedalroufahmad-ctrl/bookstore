import 'dart:io' show Platform;

String getApiBaseUrl() {
  if (Platform.isAndroid) {
    return 'http://$_androidApiHost:8000/api/v1';
  }
  return 'http://localhost:8000/api/v1';
}

// For physical device: set _androidApiHost to your machine's LAN IP (same WiFi as phone).
// For Android emulator: 10.0.2.2 maps to the host machine's localhost.
const String _androidApiHost = '10.0.2.2';
