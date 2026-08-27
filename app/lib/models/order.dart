import 'user.dart';

double? _numToDouble(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString());
}

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
    this.booksSubtotal,
    this.shippingFee,
    this.shippingMethod,
    this.paymentStatus,
    this.paymentMethod,
    this.warehouseId,
  });

  final String id;
  final String status;
  final double total;
  final List<OrderItem> items;
  final Map<String, dynamic>? shippingAddress;
  final Customer? customer;
  final Employee? employee;
  final String? createdAt;
  final double? booksSubtotal;
  final double? shippingFee;
  final String? shippingMethod;
  final String? paymentStatus;
  final String? paymentMethod;
  final String? warehouseId;

  factory Order.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Order(
      id: id.toString(),
      status: json['status'] ?? '',
      total: _numToDouble(json['total']) ?? 0,
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
      booksSubtotal: _numToDouble(json['books_subtotal']),
      shippingFee: _numToDouble(json['shipping_fee']),
      shippingMethod: json['shipping_method']?.toString(),
      paymentStatus: json['payment_status']?.toString(),
      paymentMethod: json['payment_method']?.toString(),
      warehouseId: json['warehouse_id']?.toString(),
    );
  }
}

class OrderItem {
  OrderItem({
    required this.bookId,
    required this.quantity,
    required this.price,
    this.bookTitle,
  });

  final String bookId;
  final int quantity;
  final double price;
  final String? bookTitle;

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      bookId: json['book_id']?.toString() ?? '',
      quantity: json['quantity'] ?? 0,
      price: _numToDouble(json['price']) ?? 0,
      bookTitle: json['book_title']?.toString(),
    );
  }
}
