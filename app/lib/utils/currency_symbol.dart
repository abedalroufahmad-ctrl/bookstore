import '../models/order.dart';

/// Display symbol for an order total from `currency_code` and warehouse publisher settings.
String orderCurrencySymbol(Order order) {
  final code = (order.currencyCode ?? 'USD').toUpperCase();
  final warehouse = order.warehouse;
  if (warehouse != null) {
    final publisher = warehouse['publisher'];
    if (publisher is Map) {
      final settings = publisher['public_settings'] ?? publisher['settings'];
      if (settings is Map) {
        final currencies = settings['currencies'];
        if (currencies is List) {
          for (final raw in currencies) {
            if (raw is Map) {
              final c = raw['code']?.toString().toUpperCase();
              if (c == code) {
                final symbol = raw['symbol']?.toString().trim();
                if (symbol != null && symbol.isNotEmpty) return symbol;
              }
            }
          }
        }
        final defaultCode =
            settings['default_currency_code']?.toString().toUpperCase();
        final fallbackSymbol = settings['currency_symbol']?.toString().trim();
        if (code == defaultCode &&
            fallbackSymbol != null &&
            fallbackSymbol.isNotEmpty) {
          return fallbackSymbol;
        }
        if (fallbackSymbol != null && fallbackSymbol.isNotEmpty) {
          return fallbackSymbol;
        }
      }
    }
  }
  return code == 'USD' ? r'$' : code;
}