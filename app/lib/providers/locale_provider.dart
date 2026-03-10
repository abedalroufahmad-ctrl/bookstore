import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocaleProvider with ChangeNotifier {
  static const String _prefKey = 'app_locale';

  String _languageCode = 'ar';

  LocaleProvider() {
    _loadStored();
  }

  String get languageCode => _languageCode;
  bool get isArabic => _languageCode == 'ar';

  Future<void> _loadStored() async {
    final prefs = await SharedPreferences.getInstance();
    _languageCode = prefs.getString(_prefKey) ?? 'ar';
    notifyListeners();
  }

  Future<void> toggleLanguage() async {
    _languageCode = _languageCode == 'ar' ? 'en' : 'ar';
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefKey, _languageCode);
    notifyListeners();
  }

  Future<void> setLanguage(String code) async {
    if (_languageCode == code) return;
    _languageCode = code;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefKey, code);
    notifyListeners();
  }
}
