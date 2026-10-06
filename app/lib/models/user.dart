class Customer {
  Customer({
    required this.id,
    this.name,
    this.email,
    this.address,
    this.city,
    this.country,
    this.postalCode,
    this.phone,
  });

  final String id;
  final String? name;
  final String? email;
  final String? address;
  final String? city;
  final String? country;
  final String? postalCode;
  final String? phone;

  factory Customer.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Customer(
      id: id.toString(),
      name: json['name'],
      email: json['email'],
      address: json['address']?.toString(),
      city: json['city']?.toString(),
      country: json['country']?.toString(),
      postalCode: json['postal_code']?.toString(),
      phone: json['phone']?.toString(),
    );
  }
}

class Employee {
  Employee({
    required this.id,
    this.name,
    this.email,
    this.role,
    this.warehouseId,
    this.warehouseIds,
    this.publisherId,
    this.warehouseName,
    this.publisherName,
  });

  final String id;
  final String? name;
  final String? email;
  final String? role;
  final String? warehouseId;
  final List<String>? warehouseIds;
  final String? publisherId;
  final String? warehouseName;
  final String? publisherName;

  factory Employee.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    final warehouse = json['warehouse'];
    final publisher = json['publisher'];
    List<String>? wids;
    final rawIds = json['warehouse_ids'];
    if (rawIds is List) {
      wids = rawIds.map((e) => e.toString()).toList();
    }
    return Employee(
      id: id.toString(),
      name: json['name']?.toString(),
      email: json['email']?.toString(),
      role: json['role']?.toString(),
      warehouseId: json['warehouse_id']?.toString(),
      warehouseIds: wids,
      publisherId: json['publisher_id']?.toString(),
      warehouseName: warehouse is Map ? warehouse['name']?.toString() : null,
      publisherName: publisher is Map ? publisher['name']?.toString() : null,
    );
  }
}
