import 'dart:io' show Platform;

String getApiBaseUrl() {
  if (Platform.isAndroid) {
    return 'http://$_androidApiHost:8000/api/v1';
  }
  return 'http://localhost:8000/api/v1';
}

/// For physical device: use your machine's IP (same WiFi as phone).
/// For emulator: use 10.0.2.2
const String _androidApiHost = '192.168.1.109';
