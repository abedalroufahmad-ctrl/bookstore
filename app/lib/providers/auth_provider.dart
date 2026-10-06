import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_service.dart';
import '../models/user.dart';

enum UserType { none, customer, employee }

class AuthProvider with ChangeNotifier {
  AuthProvider() {
    ApiService.instance.setOnUnauthenticated(() {
      if (_token != null) {
        _clearSession(callApiLogout: false).then((_) => notifyListeners());
      }
    });
    _loadStored();
  }

  String? _token;
  Customer? _customer;
  Employee? _employee;
  UserType _userType = UserType.none;
  bool _loading = true;

  String? get token => _token;
  Customer? get customer => _customer;
  Employee? get employee => _employee;
  UserType get userType => _userType;
  bool get loading => _loading;
  bool get isLoggedIn => _token != null && _token!.isNotEmpty;
  bool get isDirectSales =>
      _userType == UserType.employee && _employee?.role == 'direct_sales';
  bool get isEmployee => _userType == UserType.employee;

  Future<void> _loadStored() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('token');
    final type = prefs.getString('userType');
    if (type == 'customer') {
      _userType = UserType.customer;
    } else if (type == 'employee') {
      _userType = UserType.employee;
    } else {
      _userType = UserType.none;
    }

    if (_token != null && _token!.isNotEmpty) {
      // Stored session: clear if /me fails (expired/invalid token).
      await _fetchMe(logoutOnFailure: true);
    }
    _loading = false;
    notifyListeners();
  }

  /// Refresh profile from API. When [logoutOnFailure] is false (right after login),
  /// keep the session even if /me fails.
  Future<void> _fetchMe({required bool logoutOnFailure}) async {
    if (_userType == UserType.customer) {
      final res = await ApiService.instance.customerMe();
      if (res.success && res.data != null) {
        _customer = res.data;
      } else if (logoutOnFailure && res.statusCode == 401) {
        await _clearSession(callApiLogout: false);
      }
    } else if (_userType == UserType.employee) {
      final res = await ApiService.instance.employeeMe();
      if (res.success && res.data != null) {
        _employee = res.data;
      } else if (logoutOnFailure && res.statusCode == 401) {
        await _clearSession(callApiLogout: false);
      }
    }
  }

  Future<void> _saveToken(
    String token,
    String type, {
    Customer? customer,
    Employee? employee,
  }) async {
    _token = token;
    _userType = type == 'employee' ? UserType.employee : UserType.customer;
    if (customer != null) _customer = customer;
    if (employee != null) _employee = employee;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token);
    await prefs.setString('userType', type);

    // Soft refresh: do not wipe a successful login if /me briefly fails.
    await _fetchMe(logoutOnFailure: false);
    notifyListeners();
  }

  Future<String?> loginAsCustomer(
    String email,
    String password, {
    bool rememberMe = false,
  }) async {
    final res = await ApiService.instance.customerLogin(
      email,
      password,
      rememberMe: rememberMe,
    );
    final token = res.data?.token ?? '';
    if (res.success && token.isNotEmpty) {
      await _saveToken(token, 'customer', customer: res.data?.customer);
      if (!isLoggedIn) {
        return res.message.isNotEmpty ? res.message : 'Login failed';
      }
      return null;
    }
    return res.message.isNotEmpty ? res.message : 'Login failed';
  }

  Future<String?> loginAsEmployee(String email, String password) async {
    final res = await ApiService.instance.employeeLogin(email, password);
    final token = res.data?.token ?? '';
    if (res.success && token.isNotEmpty) {
      await _saveToken(token, 'employee', employee: res.data?.employee);
      if (!isLoggedIn) {
        return res.message.isNotEmpty ? res.message : 'Login failed';
      }
      return null;
    }
    return res.message.isNotEmpty ? res.message : 'Login failed';
  }

  Future<String?> register(Map<String, dynamic> data) async {
    final res = await ApiService.instance.customerRegister(data);
    final token = res.data?.token ?? '';
    if (res.success && token.isNotEmpty) {
      await _saveToken(token, 'customer', customer: res.data?.customer);
      if (!isLoggedIn) {
        return res.message.isNotEmpty ? res.message : 'Registration failed';
      }
      return null;
    }
    return res.message.isNotEmpty ? res.message : 'Registration failed';
  }

  Future<void> _clearSession({required bool callApiLogout}) async {
    if (callApiLogout) {
      if (_userType == UserType.customer) {
        await ApiService.instance.customerLogout();
      } else if (_userType == UserType.employee) {
        await ApiService.instance.employeeLogout();
      }
    }
    _token = null;
    _customer = null;
    _employee = null;
    _userType = UserType.none;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
    await prefs.remove('userType');
  }

  Future<void> logout() async {
    await _clearSession(callApiLogout: true);
    notifyListeners();
  }
}
