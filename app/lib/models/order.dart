import 'user.dart';

class Order {
  Order({
    required this.id,
    required this.status,
    required this.total,
    this.items = const [],
    this.shippingAddress,
    this.customer,
    this.employee,
    this.createdAt,
  });

  final String id;
  final String status;
  final double total;
  final List<OrderItem> items;
  final Map<String, dynamic>? shippingAddress;
  final Customer? customer;
  final Employee? employee;
  final String? createdAt;

  factory Order.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Order(
      id: id.toString(),
      status: json['status'] ?? '',
      total: (json['total'] ?? 0).toDouble(),
      items: json['items'] != null
          ? (json['items'] as List)
              .map((i) => OrderItem.fromJson(i as Map<String, dynamic>))
              .toList()
          : [],
      shippingAddress: json['shipping_address'] as Map<String, dynamic>?,
      customer: json['customer'] != null
          ? Customer.fromJson(json['customer'] as Map<String, dynamic>)
          : null,
      employee: json['employee'] != null
          ? Employee.fromJson(json['employee'] as Map<String, dynamic>)
          : null,
      createdAt: json['created_at']?.toString(),
    );
  }
}

class OrderItem {
  OrderItem({
    required this.bookId,
    required this.quantity,
    required this.price,
  });

  final String bookId;
  final int quantity;
  final double price;

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      bookId: json['book_id']?.toString() ?? '',
      quantity: json['quantity'] ?? 0,
      price: (json['price'] ?? 0).toDouble(),
    );
  }
}
