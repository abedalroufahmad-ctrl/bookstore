class Customer {
  Customer({required this.id, this.name, this.email});

  final String id;
  final String? name;
  final String? email;

  factory Customer.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Customer(
      id: id.toString(),
      name: json['name'],
      email: json['email'],
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
