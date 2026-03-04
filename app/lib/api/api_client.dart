import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

class ApiClient {
  ApiClient({String? baseUrl}) : _baseUrl = baseUrl ?? apiBaseUrl;

  final String _baseUrl;

  Future<Map<String, String>> _headers() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
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
      return _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: 'Connection error: $e', data: null);
    }
  }

  Future<ApiResponse<T>> post<T>(
    String path, {
    Map<String, dynamic>? body,
    T Function(dynamic)? fromJson,
  }) async {
    try {
      final uri = Uri.parse('$_baseUrl$path');
      print('ApiClient POST: $uri');
      final res = await http.post(
        uri,
        headers: await _headers(),
        body: body != null ? jsonEncode(body) : null,
      );
      return _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: 'Connection error: $e', data: null);
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
      return _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: 'Connection error: $e', data: null);
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
      return _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: 'Connection error: $e', data: null);
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
      return _parseResponse<T>(res, fromJson);
    } catch (e) {
      return ApiResponse(success: false, message: 'Connection error: $e', data: null);
    }
  }

  ApiResponse<T> _parseResponse<T>(http.Response res, T Function(dynamic)? fromJson) {
    try {
      final map = jsonDecode(res.body) as Map<String, dynamic>?;
      if (map == null) {
        return ApiResponse(success: false, message: 'Invalid response', data: null);
      }
      final success = map['success'] as bool? ?? false;
      final message = map['message'] as String? ?? '';
      dynamic data = map['data'];
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
      return ApiResponse(success: success, message: message, data: parsed);
    } catch (e) {
      return ApiResponse(
        success: false,
        message: 'Error parsing response: $e',
        data: null,
      );
    }
  }
}

class ApiResponse<T> {
  ApiResponse({required this.success, required this.message, this.data});
  final bool success;
  final String message;
  final T? data;
}
