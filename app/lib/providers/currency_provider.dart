import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_service.dart';

class ShopCurrency {
  const ShopCurrency({
    required this.code,
    required this.symbol,
    this.name = '',
  });

  final String code;
  final String symbol;
  final String name;
}

/// Matches web `bookstore.currency` in localStorage.
class CurrencyProvider with ChangeNotifier {
  static const String prefKey = 'bookstore.currency';

  String _currencyCode = 'USD';
  Map<String, ShopCurrency> _options = {
    'USD': const ShopCurrency(code: 'USD', symbol: r'$', name: 'US Dollar'),
  };
  bool _loaded = false;

  String get currencyCode => _currencyCode;
  bool get isLoaded => _loaded;

  List<ShopCurrency> get options {
    final list = _options.values.toList()
      ..sort((a, b) => a.code.compareTo(b.code));
    if (!list.any((c) => c.code == _currencyCode)) {
      list.add(ShopCurrency(code: _currencyCode, symbol: _currencyCode, name: _currencyCode));
      list.sort((a, b) => a.code.compareTo(b.code));
    }
    return list;
  }

  String symbolFor(String? code) {
    final normalized = (code ?? _currencyCode).toUpperCase();
    return _options[normalized]?.symbol ??
        (normalized == 'USD' ? r'$' : normalized);
  }

  String get currentSymbol => symbolFor(_currencyCode);

  CurrencyProvider() {
    _init();
  }

  Future<void> _init() async {
    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getString(prefKey)?.toUpperCase();
    if (stored != null && RegExp(r'^[A-Z]{3}$').hasMatch(stored)) {
      _currencyCode = stored;
    }
    await refreshOptions();
    _loaded = true;
    notifyListeners();
  }

  Future<void> refreshOptions() async {
    final merged = <String, ShopCurrency>{
      'USD': const ShopCurrency(code: 'USD', symbol: r'$', name: 'US Dollar'),
    };

    final res = await ApiService.instance.getPublishersPaginated(1, perPage: 100);
    if (res.success && res.data != null) {
      for (final publisher in res.data!.items) {
        for (final currency in publisher.currencies) {
          merged[currency.code] = ShopCurrency(
            code: currency.code,
            symbol: currency.symbol,
            name: currency.name,
          );
        }
        final defaultCode = publisher.defaultCurrencyCode?.toUpperCase();
        if (defaultCode != null &&
            defaultCode.isNotEmpty &&
            !merged.containsKey(defaultCode)) {
          merged[defaultCode] = ShopCurrency(
            code: defaultCode,
            symbol: publisher.currencySymbol ?? defaultCode,
            name: defaultCode,
          );
        }
      }
    }

    _options = merged;
    notifyListeners();
  }

  Future<void> setCurrencyCode(String code) async {
    final normalized = code.toUpperCase();
    if (!RegExp(r'^[A-Z]{3}$').hasMatch(normalized)) return;
    if (_currencyCode == normalized) return;
    _currencyCode = normalized;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(prefKey, normalized);
    notifyListeners();
  }
}