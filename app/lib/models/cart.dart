class Cart {
  Cart({required this.id, this.items = const []});

  final String id;
  final List<CartItem> items;

  factory Cart.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Cart(
      id: id.toString(),
      items: json['items'] != null
          ? (json['items'] as List)
              .map((i) => CartItem.fromJson(i as Map<String, dynamic>))
              .toList()
          : [],
    );
  }
}

class CartItem {
  CartItem({
    required this.bookId,
    required this.quantity,
    required this.price,
    this.subtotal,
    this.currencyCode = 'USD',
    this.currencyAvailable = true,
    this.book,
  });

  final String bookId;
  final int quantity;
  final double? price;
  final double? subtotal;
  final String currencyCode;
  final bool currencyAvailable;
  final CartItemBook? book;

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      bookId: json['book_id']?.toString() ?? '',
      quantity: json['quantity'] ?? 0,
      price: _cartAmount(json['price']),
      subtotal: _cartAmount(json['subtotal']),
      currencyCode: (json['currency_code'] ?? 'USD').toString().toUpperCase(),
      currencyAvailable: json['currency_available'] != false,
      book: json['book'] != null
          ? CartItemBook.fromJson(json['book'] as Map<String, dynamic>)
          : null,
    );
  }
}

double? _cartAmount(dynamic value) {
  if (value == null) return null;
  if (value is num) return value.toDouble();
  return double.tryParse(value.toString());
}

class CartItemBook {
  CartItemBook({
    this.id,
    this.title,
    this.price = 0,
    this.discountPercent,
    this.currencySymbol,
    this.warehouseId,
    this.warehouseName,
    this.warehouseCurrencyCode,
  });

  final String? id;
  final String? title;
  final double price;
  final int? discountPercent;
  final String? currencySymbol;
  final String? warehouseId;
  final String? warehouseName;
  final String? warehouseCurrencyCode;

  factory CartItemBook.fromJson(Map<String, dynamic> json) {
    String? currencySymbol;
    final publisher = json['publisher'];
    if (publisher is Map) {
      final settings = publisher['settings'] ?? publisher['public_settings'];
      if (settings is Map) {
        currencySymbol = settings['currency_symbol']?.toString();
      }
    }
    String? warehouseId;
    String? warehouseName;
    String? warehouseCurrencyCode;
    final warehouse = json['warehouse'];
    if (warehouse is Map) {
      warehouseId = warehouse['id']?.toString();
      warehouseName = warehouse['name']?.toString();
      final code = warehouse['currency_code']?.toString();
      if (code != null && code.isNotEmpty) {
        warehouseCurrencyCode = code.toUpperCase();
      }
    }
    return CartItemBook(
      id: json['id']?.toString() ?? json['_id']?.toString(),
      title: json['title'],
      price: (json['price'] ?? 0).toDouble(),
      discountPercent: json['discount_percent'],
      currencySymbol: currencySymbol,
      warehouseId: warehouseId,
      warehouseName: warehouseName,
      warehouseCurrencyCode: warehouseCurrencyCode,
    );
  }
}
