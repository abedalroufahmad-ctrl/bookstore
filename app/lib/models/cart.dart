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
    this.subtotal = 0,
    this.book,
  });

  final String bookId;
  final int quantity;
  final double price;
  final double subtotal;
  final CartItemBook? book;

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      bookId: json['book_id']?.toString() ?? '',
      quantity: json['quantity'] ?? 0,
      price: (json['price'] ?? 0).toDouble(),
      subtotal: (json['subtotal'] ?? 0).toDouble(),
      book: json['book'] != null
          ? CartItemBook.fromJson(json['book'] as Map<String, dynamic>)
          : null,
    );
  }
}

class CartItemBook {
  CartItemBook({this.id, this.title, this.price = 0});

  final String? id;
  final String? title;
  final double price;

  factory CartItemBook.fromJson(Map<String, dynamic> json) {
    return CartItemBook(
      id: json['id']?.toString() ?? json['_id']?.toString(),
      title: json['title'],
      price: (json['price'] ?? 0).toDouble(),
    );
  }
}
