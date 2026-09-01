import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

class ApiClient {
  ApiClient({String? baseUrl}) : _baseUrl = baseUrl ?? apiBaseUrl;

  final String _baseUrl;
  String get baseUrl => _baseUrl;
  void Function()? onUnauthenticated;

  Future<bool> _isArabic() async {
    final prefs = await SharedPreferences.getInstance();
    final lang = prefs.getString('app_locale') ?? 'ar';
    return lang.startsWith('ar');
  }

  Future<Map<String, String>> _headers() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    final lang = prefs.getString('app_locale') ?? 'ar';
    final locale = lang.startsWith('ar') ? 'ar' : 'en';
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Accept-Language': locale,
      'X-Locale': locale,
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<String> _localizeStatusMessage(String message, int statusCode) async {
    final normalized = message.trim().replaceAll(RegExp(r'\.+$'), '').toLowerCase();
    if (statusCode == 429 || normalized == 'too many attempts') {
      final ar = await _isArabic();
      return ar
          ? 'محاولات كثيرة جداً. حاول مرة أخرى بعد دقيقة.'
          : 'Too many attempts. Please try again in a minute.';
    }
    return message;
  }

  Future<String> _connectionError(Object e) async {
    final ar = await _isArabic();
    final hint = ar
        ? 'تأكد أن الهاتف على نفس الشبكة وأن API يعمل على $_baseUrl'
        : 'Check phone is on the same Wi‑Fi and API is running at $_baseUrl';
    return ar ? 'خطأ في الاتصال: $e\n$hint' : 'Connection error: $e\n$hint';
  }

  Future<ApiResponse<T>> get<T>(
    String path, {
    Map<String, String>? params,
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final uri = params != null && params.isNotEmpty
          ? Uri.parse('$_baseUrl$path').replace(queryParameters: params)
          : Uri.parse('$_baseUrl$path');
      final res = await http.get(uri, headers: await _headers());
      return await _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: await _connectionError(e), data: null);
    }
  }

  Future<ApiResponse<T>> post<T>(
    String path, {
    Map<String, dynamic>? body,
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final uri = Uri.parse('$_baseUrl$path');
      final res = await http.post(
        uri,
        headers: await _headers(),
        body: body != null ? jsonEncode(body) : null,
      );
      return await _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: await _connectionError(e), data: null);
    }
  }

  Future<ApiResponse<T>> put<T>(
    String path, {
    Map<String, dynamic>? body,
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final res = await http.put(
        Uri.parse('$_baseUrl$path'),
        headers: await _headers(),
        body: body != null ? jsonEncode(body) : null,
      );
      return await _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: await _connectionError(e), data: null);
    }
  }

  Future<ApiResponse<T>> patch<T>(
    String path, {
    Map<String, dynamic>? body,
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final res = await http.patch(
        Uri.parse('$_baseUrl$path'),
        headers: await _headers(),
        body: body != null ? jsonEncode(body) : null,
      );
      return await _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: await _connectionError(e), data: null);
    }
  }

  Future<ApiResponse<T>> delete<T>(
    String path, {
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final res = await http.delete(
        Uri.parse('$_baseUrl$path'),
        headers: await _headers(),
      );
      return await _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: await _connectionError(e), data: null);
    }
  }

  Future<ApiResponse<T>> _parseResponse<T>(
    http.Response res,
    T Function(dynamic)? fromJson,
  ) async {
    try {
      final map = jsonDecode(res.body) as Map<String, dynamic>?;
      if (map == null) {
        final ar = await _isArabic();
        return ApiResponse(
          success: false,
          message: ar ? 'استجابة غير صالحة' : 'Invalid response',
          data: null,
          statusCode: res.statusCode,
        );
      }
      final success = map['success'] as bool? ?? false;
      var message = map['message'] as String? ?? '';
      message = await _localizeStatusMessage(message, res.statusCode);
      dynamic data = map['data'];
      if (!success && data is Map && data['errors'] != null) {
        message = _firstValidationErrorMessage(message, data['errors']);
      }
      T? parsed;
      if (data != null && fromJson != null) {
        try {
          parsed = fromJson(data);
        } catch (_) {
          parsed = data as T?;
        }
      } else {
        parsed = data as T?;
      }
      if (res.statusCode == 401) {
        onUnauthenticated?.call();
      }
      return ApiResponse(success: success, message: message, data: parsed, statusCode: res.statusCode);
    } catch (e) {
      final ar = await _isArabic();
      return ApiResponse(
        success: false,
        message: ar ? 'خطأ في تحليل الاستجابة: $e' : 'Error parsing response: $e',
        data: null,
      );
    }
  }

  /// Prefer Laravel field errors over generic "Validation failed." (better UX on checkout, etc.).
  static String _firstValidationErrorMessage(String fallback, dynamic errors) {
    if (errors is! Map) return fallback;
    final parts = <String>[];
    for (final e in errors.values) {
      if (e is List && e.isNotEmpty) {
        parts.add(e.first.toString());
      } else if (e is String && e.isNotEmpty) {
        parts.add(e);
      }
    }
    if (parts.isEmpty) return fallback;
    return parts.join(' ');
  }
}

class ApiResponse<T> {
  ApiResponse({required this.success, required this.message, this.data, this.statusCode});
  final bool success;
  final String message;
  final T? data;
  final int? statusCode;
}
