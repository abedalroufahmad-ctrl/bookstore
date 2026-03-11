import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Holds customer profile data for shipping and communication (stored locally).
class ProfileProvider with ChangeNotifier {
  ProfileProvider() {
    _load();
  }

  static const _keyPrefix = 'profile_';
  String? _phone;
  String? _address;
  String? _city;
  String? _country;
  String? _postalCode;

  String? get phone => _phone;
  String? get address => _address;
  String? get city => _city;
  String? get country => _country;
  String? get postalCode => _postalCode;

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    _phone = prefs.getString('${_keyPrefix}phone');
    _address = prefs.getString('${_keyPrefix}address');
    _city = prefs.getString('${_keyPrefix}city');
    _country = prefs.getString('${_keyPrefix}country');
    _postalCode = prefs.getString('${_keyPrefix}postalCode');
    if (_phone == '') _phone = null;
    if (_address == '') _address = null;
    if (_city == '') _city = null;
    if (_country == '') _country = null;
    if (_postalCode == '') _postalCode = null;
    notifyListeners();
  }

  Future<void> save({
    String? phone,
    String? address,
    String? city,
    String? country,
    String? postalCode,
  }) async {
    if (phone != null) _phone = phone.isEmpty ? null : phone;
    if (address != null) _address = address.isEmpty ? null : address;
    if (city != null) _city = city.isEmpty ? null : city;
    if (country != null) _country = country.isEmpty ? null : country;
    if (postalCode != null) _postalCode = postalCode.isEmpty ? null : postalCode;

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('${_keyPrefix}phone', _phone ?? '');
    await prefs.setString('${_keyPrefix}address', _address ?? '');
    await prefs.setString('${_keyPrefix}city', _city ?? '');
    await prefs.setString('${_keyPrefix}country', _country ?? '');
    await prefs.setString('${_keyPrefix}postalCode', _postalCode ?? '');
    notifyListeners();
  }
}
