String formatBookPrice(num price, String? currencySymbol) {
  final symbol = currencySymbol?.trim();
  final displaySymbol = symbol == null || symbol.isEmpty ? r'$' : symbol;
  final separator = displaySymbol.length > 1 ? ' ' : '';
  return '$displaySymbol$separator${price.toDouble().toStringAsFixed(2)}';
}
