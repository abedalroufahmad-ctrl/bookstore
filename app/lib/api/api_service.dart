import '../models/book.dart';
import '../models/cart.dart';
import '../models/order.dart';
import '../models/user.dart';
import 'api_client.dart';

/// Result of a paginated API response (Laravel-style).
class PaginatedResult<T> {
  PaginatedResult({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.perPage,
  });
  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;
  final int perPage;
  bool get hasMore => currentPage < lastPage;
}

class ApiService {
  ApiService(this._client);
  final ApiClient _client;

  static const int _defaultPerPage = 20;

  static final ApiService instance = ApiService(ApiClient());

  // Public catalog
  Future<ApiResponse<dynamic>> getBooks({Map<String, String>? params}) async {
    final res = await _client.get<dynamic>('/books', params: params);
    return res;
  }

  Future<ApiResponse<PaginatedResult<Book>>> getBooksPaginated(int page, {int perPage = _defaultPerPage, String? search}) async {
    final params = <String, String>{'page': page.toString(), 'per_page': perPage.toString()};
    if (search != null && search.trim().isNotEmpty) {
      params['search'] = search.trim();
    }
    final res = await _client.get<dynamic>('/books', params: params);
    return _parsePaginatedBooks(res);
  }

  static ApiResponse<PaginatedResult<Book>> _parsePaginatedBooks(ApiResponse<dynamic> res) {
    if (!res.success || res.data == null) {
      return ApiResponse(success: false, message: res.message, data: null);
    }
    final d = res.data;
    List<Book> list = [];
    int currentPage = 1;
    int lastPage = 1;
    int total = 0;
    int perPage = _defaultPerPage;
    if (d is Map) {
      final rawList = d['data'];
      if (rawList is List) {
        list = rawList.map((e) => Book.fromJson(e as Map<String, dynamic>)).toList();
      }
      currentPage = (d['current_page'] as num?)?.toInt() ?? 1;
      lastPage = (d['last_page'] as num?)?.toInt() ?? 1;
      total = (d['total'] as num?)?.toInt() ?? 0;
      perPage = (d['per_page'] as num?)?.toInt() ?? _defaultPerPage;
    }
    return ApiResponse(
      success: true,
      message: res.message,
      data: PaginatedResult<Book>(items: list, currentPage: currentPage, lastPage: lastPage, total: total, perPage: perPage),
    );
  }

  Future<ApiResponse<Book>> getBook(String id) async {
    final res = await _client.get<Map<String, dynamic>>('/books/$id');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Book.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<List<Category>>> getCategories() async {
    final res = await _client.get<dynamic>('/categories');
    if (res.success && res.data != null) {
      final d = res.data;
      List<Category> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Category.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Category.fromJson(e as Map<String, dynamic>)).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<PaginatedResult<Category>>> getCategoriesPaginated(int page, {int perPage = _defaultPerPage, String? search}) async {
    final params = <String, String>{'page': page.toString(), 'per_page': perPage.toString()};
    if (search != null && search.trim().isNotEmpty) {
      params['search'] = search.trim();
    }
    final res = await _client.get<dynamic>('/categories', params: params);
    return _parsePaginatedCategories(res);
  }

  static ApiResponse<PaginatedResult<Category>> _parsePaginatedCategories(ApiResponse<dynamic> res) {
    if (!res.success || res.data == null) {
      return ApiResponse(success: false, message: res.message, data: null);
    }
    final d = res.data;
    List<Category> list = [];
    int currentPage = 1;
    int lastPage = 1;
    int total = 0;
    int perPage = _defaultPerPage;
    if (d is Map) {
      final rawList = d['data'];
      if (rawList is List) {
        list = rawList.map((e) => Category.fromJson(e as Map<String, dynamic>)).toList();
      }
      currentPage = (d['current_page'] as num?)?.toInt() ?? 1;
      lastPage = (d['last_page'] as num?)?.toInt() ?? 1;
      total = (d['total'] as num?)?.toInt() ?? 0;
      perPage = (d['per_page'] as num?)?.toInt() ?? _defaultPerPage;
    }
    return ApiResponse(
      success: true,
      message: res.message,
      data: PaginatedResult<Category>(items: list, currentPage: currentPage, lastPage: lastPage, total: total, perPage: perPage),
    );
  }

  Future<ApiResponse<List<Author>>> getAuthors() async {
    final res = await _client.get<dynamic>('/authors');
    if (res.success && res.data != null) {
      final d = res.data;
      List<Author> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Author.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Author.fromJson(e as Map<String, dynamic>)).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<PaginatedResult<Author>>> getAuthorsPaginated(int page, {int perPage = _defaultPerPage, String? search}) async {
    final params = <String, String>{'page': page.toString(), 'per_page': perPage.toString()};
    if (search != null && search.trim().isNotEmpty) {
      params['search'] = search.trim();
    }
    final res = await _client.get<dynamic>('/authors', params: params);
    return _parsePaginatedAuthors(res);
  }

  static ApiResponse<PaginatedResult<Author>> _parsePaginatedAuthors(ApiResponse<dynamic> res) {
    if (!res.success || res.data == null) {
      return ApiResponse(success: false, message: res.message, data: null);
    }
    final d = res.data;
    List<Author> list = [];
    int currentPage = 1;
    int lastPage = 1;
    int total = 0;
    int perPage = _defaultPerPage;
    if (d is Map) {
      final rawList = d['data'];
      if (rawList is List) {
        list = rawList.map((e) => Author.fromJson(e as Map<String, dynamic>)).toList();
      }
      currentPage = (d['current_page'] as num?)?.toInt() ?? 1;
      lastPage = (d['last_page'] as num?)?.toInt() ?? 1;
      total = (d['total'] as num?)?.toInt() ?? 0;
      perPage = (d['per_page'] as num?)?.toInt() ?? _defaultPerPage;
    }
    return ApiResponse(
      success: true,
      message: res.message,
      data: PaginatedResult<Author>(items: list, currentPage: currentPage, lastPage: lastPage, total: total, perPage: perPage),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> getSettings() async {
    final res = await _client.get<Map<String, dynamic>>('/settings');
    return res;
  }

  Future<ApiResponse<dynamic>> adminUpdateSettings(Map<String, dynamic> data) async {
    return _client.put('/admin/settings', body: data);
  }

  // Customer auth
  Future<ApiResponse<AuthResult>> customerLogin(
    String email,
    String password, {
    bool rememberMe = false,
  }) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/customers/login',
      body: {'email': email, 'password': password, 'remember_me': rememberMe},
    );
    if (res.success && res.data != null) {
      final d = res.data!;
      final token = d['token'] as String?;
      final customer = d['customer'] != null
          ? Customer.fromJson(d['customer'] as Map<String, dynamic>)
          : null;
      return ApiResponse(
        success: true,
        message: res.message,
        data: AuthResult(token: token ?? '', customer: customer),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<AuthResult>> customerRegister(Map<String, dynamic> data) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/customers/register',
      body: data,
    );
    if (res.success && res.data != null) {
      final d = res.data!;
      final token = d['token'] as String?;
      final customer = d['customer'] != null
          ? Customer.fromJson(d['customer'] as Map<String, dynamic>)
          : null;
      return ApiResponse(
        success: true,
        message: res.message,
        data: AuthResult(token: token ?? '', customer: customer),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<AuthResult>> employeeLogin(String email, String password) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/employees/login',
      body: {'email': email, 'password': password},
    );
    if (res.success && res.data != null) {
      final d = res.data!;
      final token = d['token'] as String?;
      final employee = d['employee'] != null
          ? Employee.fromJson(d['employee'] as Map<String, dynamic>)
          : null;
      return ApiResponse(
        success: true,
        message: res.message,
        data: AuthResult(token: token ?? '', employee: employee),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<void>> customerLogout() async {
    return _client.post<void>('/customers/logout');
  }

  Future<ApiResponse<void>> employeeLogout() async {
    return _client.post<void>('/employees/logout');
  }

  Future<ApiResponse<Customer>> customerMe() async {
    final res = await _client.get<Map<String, dynamic>>('/customers/me');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Customer.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Customer>> updateCustomerProfile({
    String? name,
    String? email,
    String? address,
    String? city,
    String? country,
    String? postalCode,
    String? phone,
  }) async {
    final body = <String, dynamic>{};
    if (name != null) body['name'] = name;
    if (email != null) body['email'] = email;
    if (address != null) body['address'] = address;
    if (city != null) body['city'] = city;
    if (country != null) body['country'] = country;
    if (postalCode != null) body['postal_code'] = postalCode;
    if (phone != null) body['phone'] = phone;
    final res = await _client.put<Map<String, dynamic>>(
      '/customers/profile',
      body: body.isNotEmpty ? body : null,
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Customer.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Employee>> employeeMe() async {
    final res = await _client.get<Map<String, dynamic>>('/employees/me');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Employee.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  // Cart
  Future<ApiResponse<CartData>> getCart() async {
    final res = await _client.get<Map<String, dynamic>>('/customers/cart');
    if (res.success && res.data != null) {
      final d = res.data as Map<String, dynamic>;
      final items = (d['items'] as List?)
              ?.map((e) => CartItem.fromJson(e as Map<String, dynamic>))
              .toList() ??
          [];
      final total = (d['total'] ?? 0).toDouble();
      return ApiResponse(
        success: true,
        message: res.message,
        data: CartData(items: items, total: total),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<dynamic>> addToCart(String bookId, {int quantity = 1}) async {
    return _client.post('/customers/cart/items', body: {
      'book_id': bookId,
      'quantity': quantity,
    });
  }

  Future<ApiResponse<dynamic>> removeFromCart(String bookId) async {
    return _client.delete('/customers/cart/items/$bookId');
  }

  Future<ApiResponse<dynamic>> updateCartItem(String bookId, int quantity) async {
    return _client.patch('/customers/cart/items/$bookId', body: {'quantity': quantity});
  }

  // Orders
  Future<ApiResponse<List<Order>>> getOrders() async {
    final res = await _client.get<dynamic>('/customers/orders');
    if (res.success && res.data != null) {
      final d = res.data;
      List<Order> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Order.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Order>> checkout(
    Map<String, dynamic> shippingAddress, {
    required String paymentMethod,
    Map<String, dynamic>? paymentInfo,
  }) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/customers/orders/checkout',
      body: {
        'shipping_address': shippingAddress,
        'payment_method': paymentMethod,
        ...? (paymentInfo != null ? {'payment_info': paymentInfo} : null),
      },
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Order.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  // Admin
  Future<ApiResponse<dynamic>> adminBooksList({Map<String, String>? params}) async {
    return _client.get<dynamic>('/admin/books', params: params);
  }

  Future<ApiResponse<Book>> adminBooksGet(String id) async {
    final res = await _client.get<Map<String, dynamic>>('/admin/books/$id');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Book.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Book>> adminBooksCreate(Map<String, dynamic> data) async {
    final res = await _client.post<Map<String, dynamic>>('/admin/books', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Book.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Book>> adminBooksUpdate(String id, Map<String, dynamic> data) async {
    final res = await _client.put<Map<String, dynamic>>('/admin/books/$id', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Book.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<dynamic>> adminBooksDelete(String id) async {
    return _client.delete('/admin/books/$id');
  }

  Future<ApiResponse<List<Author>>> adminAuthorsList() async {
    final res = await _client.get<dynamic>('/admin/authors');
    if (res.success && res.data != null) {
      final d = res.data;
      List<Author> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Author.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Author.fromJson(e as Map<String, dynamic>)).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Author>> adminAuthorsCreate(String name) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/admin/authors',
      body: {'name': name},
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Author.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<dynamic>> adminAuthorsDelete(String id) async {
    return _client.delete('/admin/authors/$id');
  }

  Future<ApiResponse<List<Category>>> adminCategoriesList() async {
    final res = await _client.get<dynamic>('/admin/categories');
    if (res.success && res.data != null) {
      final d = res.data;
      List<Category> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Category.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Category.fromJson(e as Map<String, dynamic>)).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Category>> adminCategoriesCreate(
      String deweyCode, String subjectTitle) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/admin/categories',
      body: {'dewey_code': deweyCode, 'subject_title': subjectTitle},
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Category.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<dynamic>> adminCategoriesDelete(String id) async {
    return _client.delete('/admin/categories/$id');
  }

  Future<ApiResponse<List<Map<String, dynamic>>>> adminWarehousesList() async {
    final res = await _client.get<dynamic>('/admin/warehouses');
    if (res.success && res.data != null) {
      final d = res.data;
      List<Map<String, dynamic>> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => e as Map<String, dynamic>)
            .toList();
      } else if (d is List) {
        list = d.map((e) => e as Map<String, dynamic>).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<List<Map<String, dynamic>>>> adminCountriesList(
      {int perPage = 50}) async {
    final res = await _client.get<dynamic>(
      '/admin/countries',
      params: {'per_page': perPage.toString()},
    );
    if (res.success && res.data != null) {
      final d = res.data;
      List<Map<String, dynamic>> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => e as Map<String, dynamic>)
            .toList();
      } else if (d is List) {
        list = d.map((e) => e as Map<String, dynamic>).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<List<Order>>> adminOrdersList({String? status}) async {
    final res = await _client.get<dynamic>(
      '/admin/orders',
      params: status != null ? {'status': status} : null,
    );
    if (res.success && res.data != null) {
      final d = res.data;
      List<Order> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Order.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Order>> adminOrdersGet(String id) async {
    final res = await _client.get<Map<String, dynamic>>('/admin/orders/$id');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Order.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Order>> adminOrdersUpdateStatus(String id, String status) async {
    final res = await _client.patch<Map<String, dynamic>>(
      '/admin/orders/$id/status',
      body: {'status': status},
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Order.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Order>> adminOrdersAssign(String id, String employeeId) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/admin/orders/$id/assign',
      body: {'employee_id': employeeId},
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Order.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<List<Employee>>> adminEmployeesList() async {
    final res = await _client.get<dynamic>('/admin/employees');
    if (res.success && res.data != null) {
      final d = res.data;
      List<Employee> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Employee.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Employee.fromJson(e as Map<String, dynamic>)).toList();
      }
      return ApiResponse(success: true, message: res.message, data: list);
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }
}

class AuthResult {
  AuthResult({required this.token, this.customer, this.employee});
  final String token;
  final Customer? customer;
  final Employee? employee;
}

class CartData {
  CartData({required this.items, required this.total});
  final List<CartItem> items;
  final double total;
}
