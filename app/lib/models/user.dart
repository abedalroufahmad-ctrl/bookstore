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
  Employee({required this.id, this.name, this.email, this.role});

  final String id;
  final String? name;
  final String? email;
  final String? role;

  factory Employee.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Employee(
      id: id.toString(),
      name: json['name'],
      email: json['email'],
      role: json['role'],
    );
  }
}
