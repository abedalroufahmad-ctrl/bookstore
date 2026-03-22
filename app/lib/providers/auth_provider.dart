import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_service.dart';
import '../models/user.dart';

enum UserType { none, customer, employee }

class AuthProvider with ChangeNotifier {
  AuthProvider() {
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
      await _fetchMe();
    }
    _loading = false;
    notifyListeners();
  }

  Future<void> _fetchMe() async {
    if (_userType == UserType.customer) {
      final res = await ApiService.instance.customerMe();
      if (res.success && res.data != null) {
        _customer = res.data;
      }
    } else if (_userType == UserType.employee) {
      final res = await ApiService.instance.employeeMe();
      if (res.success && res.data != null) {
        _employee = res.data;
      }
    }
  }

  Future<void> _saveToken(String token, String type) async {
    _token = token;
    _userType = type == 'employee' ? UserType.employee : UserType.customer;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token);
    await prefs.setString('userType', type);
    await _fetchMe();
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
    if (res.success && res.data != null) {
      await _saveToken(res.data!.token, 'customer');
      return null;
    }
    return res.message;
  }

  Future<String?> loginAsEmployee(String email, String password) async {
    final res = await ApiService.instance.employeeLogin(email, password);
    if (res.success && res.data != null) {
      await _saveToken(res.data!.token, 'employee');
      return null;
    }
    return res.message;
  }

  Future<String?> register(Map<String, dynamic> data) async {
    final res = await ApiService.instance.customerRegister(data);
    if (res.success && res.data != null) {
      await _saveToken(res.data!.token, 'customer');
      return null;
    }
    return res.message;
  }

  Future<void> logout() async {
    if (_userType == UserType.customer) {
      await ApiService.instance.customerLogout();
    } else if (_userType == UserType.employee) {
      await ApiService.instance.employeeLogout();
    }
    _token = null;
    _customer = null;
    _employee = null;
    _userType = UserType.none;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
    await prefs.remove('userType');
    notifyListeners();
  }
}
