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

  Future<ApiResponse<PaginatedResult<Book>>> getBooksPaginated(
    int page, {
    int perPage = _defaultPerPage,
    String? search,
    String? condition,
    String? categoryId,
    String? warehouseId,
    String? publisherId,
  }) async {
    final params = <String, String>{'page': page.toString(), 'per_page': perPage.toString()};
    if (search != null && search.trim().isNotEmpty) {
      params['search'] = search.trim();
    }
    if (condition != null && (condition == 'new' || condition == 'used')) {
      params['condition'] = condition;
    }
    if (categoryId != null && categoryId.isNotEmpty) {
      params['category_id'] = categoryId;
    }
    if (warehouseId != null && warehouseId.isNotEmpty) {
      params['warehouse_id'] = warehouseId;
    }
    if (publisherId != null && publisherId.isNotEmpty) {
      params['publisher_id'] = publisherId;
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

  Future<ApiResponse<PaginatedResult<Warehouse>>> getWarehousesPaginated(int page, {int perPage = _defaultPerPage, String? search}) async {
    final params = <String, String>{'page': page.toString(), 'per_page': perPage.toString()};
    if (search != null && search.trim().isNotEmpty) {
      params['search'] = search.trim();
    }
    final res = await _client.get<dynamic>('/warehouses', params: params);
    return _parsePaginatedWarehouses(res);
  }

  Future<ApiResponse<PaginatedResult<Publisher>>> getPublishersPaginated(int page, {int perPage = _defaultPerPage, String? search}) async {
    final params = <String, String>{'page': page.toString(), 'per_page': perPage.toString()};
    if (search != null && search.trim().isNotEmpty) {
      params['search'] = search.trim();
    }
    final res = await _client.get<dynamic>('/publishers', params: params);
    return _parsePaginatedPublishers(res);
  }

  static ApiResponse<PaginatedResult<Publisher>> _parsePaginatedPublishers(ApiResponse<dynamic> res) {
    if (!res.success || res.data == null) {
      return ApiResponse(success: false, message: res.message, data: null);
    }
    final d = res.data;
    List<Publisher> list = [];
    int currentPage = 1;
    int lastPage = 1;
    int total = 0;
    int perPage = _defaultPerPage;
    if (d is Map) {
      final rawList = d['data'];
      if (rawList is List) {
        list = rawList.map((e) => Publisher.fromJson(e as Map<String, dynamic>)).toList();
      }
      currentPage = (d['current_page'] as num?)?.toInt() ?? 1;
      lastPage = (d['last_page'] as num?)?.toInt() ?? 1;
      total = (d['total'] as num?)?.toInt() ?? 0;
      perPage = (d['per_page'] as num?)?.toInt() ?? _defaultPerPage;
    }
    return ApiResponse(
      success: true,
      message: res.message,
      data: PaginatedResult<Publisher>(items: list, currentPage: currentPage, lastPage: lastPage, total: total, perPage: perPage),
    );
  }

  static ApiResponse<PaginatedResult<Warehouse>> _parsePaginatedWarehouses(ApiResponse<dynamic> res) {
    if (!res.success || res.data == null) {
      return ApiResponse(success: false, message: res.message, data: null);
    }
    final d = res.data;
    List<Warehouse> list = [];
    int currentPage = 1;
    int lastPage = 1;
    int total = 0;
    int perPage = _defaultPerPage;
    if (d is Map) {
      final rawList = d['data'];
      if (rawList is List) {
        list = rawList.map((e) => Warehouse.fromJson(e as Map<String, dynamic>)).toList();
      }
      currentPage = (d['current_page'] as num?)?.toInt() ?? 1;
      lastPage = (d['last_page'] as num?)?.toInt() ?? 1;
      total = (d['total'] as num?)?.toInt() ?? 0;
      perPage = (d['per_page'] as num?)?.toInt() ?? _defaultPerPage;
    }
    return ApiResponse(
      success: true,
      message: res.message,
      data: PaginatedResult<Warehouse>(items: list, currentPage: currentPage, lastPage: lastPage, total: total, perPage: perPage),
    );
  }

  Future<ApiResponse<Warehouse>> getWarehouse(String id) async {
    final res = await _client.get<Map<String, dynamic>>('/warehouses/$id');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Warehouse.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
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
      final token = '${d['token'] ?? ''}';
      if (token.isEmpty) {
        return ApiResponse(success: false, message: res.message, data: null);
      }
      final customer = d['customer'] is Map
          ? Customer.fromJson(Map<String, dynamic>.from(d['customer'] as Map))
          : null;
      return ApiResponse(
        success: true,
        message: res.message,
        data: AuthResult(token: token, customer: customer),
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
      final token = '${d['token'] ?? ''}';
      if (token.isEmpty) {
        return ApiResponse(success: false, message: res.message, data: null);
      }
      final employee = d['employee'] is Map
          ? Employee.fromJson(Map<String, dynamic>.from(d['employee'] as Map))
          : null;
      return ApiResponse(
        success: true,
        message: res.message,
        data: AuthResult(token: token, employee: employee),
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

  Future<ApiResponse<Map<String, dynamic>>> checkout(
    Map<String, dynamic> shippingAddress, {
    required String paymentMethod,
    Map<String, dynamic>? paymentInfo,
  }) async {
    return _client.post<Map<String, dynamic>>(
      '/customers/orders/checkout',
      body: {
        'shipping_address': shippingAddress,
        'payment_method': paymentMethod,
        ...? (paymentInfo != null ? {'payment_info': paymentInfo} : null),
      },
      fromJson: (d) => Map<String, dynamic>.from(d as Map),
    );
  }

  /// PayPal against warehouse-quoted totals. Call after checkout order is `awaiting_customer_confirmation`.
  Future<ApiResponse<Map<String, dynamic>>> paypalStartQuoted(List<String> orderIds) async {
    return _client.post<Map<String, dynamic>>(
      '/customers/orders/paypal/start',
      body: {'order_ids': orderIds},
      fromJson: (d) => Map<String, dynamic>.from(d as Map),
    );
  }

  Future<ApiResponse<Order>> customerOrderGet(String id) async {
    return _client.get<Order>(
      '/customers/orders/$id',
      fromJson: (dynamic d) => Order.fromJson(Map<String, dynamic>.from(d as Map)),
    );
  }

  Future<ApiResponse<Order>> customerConfirmQuote(String id) async {
    return _client.post<Order>(
      '/customers/orders/$id/confirm-quote',
      fromJson: (dynamic d) => Order.fromJson(Map<String, dynamic>.from(d as Map)),
    );
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

  Future<ApiResponse<Category>> adminCategoriesCreate({
    required String deweyCode,
    required String subjectTitleEn,
    String? subjectTitleAr,
  }) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/admin/categories',
      body: {
        'dewey_code': deweyCode,
        'subject_title_en': subjectTitleEn,
        if (subjectTitleAr != null && subjectTitleAr.isNotEmpty)
          'subject_title_ar': subjectTitleAr,
      },
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

  Future<ApiResponse<List<Map<String, dynamic>>>> adminWarehousesList({int perPage = 100}) async {
    final res = await _client.get<dynamic>(
      '/admin/warehouses',
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

  Future<ApiResponse<Map<String, dynamic>>> adminOrdersBulkDelete(List<String> ids) async {
    final res = await _client.post<Map<String, dynamic>>(
      '/admin/orders/bulk-delete',
      body: {'ids': ids},
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: res.data as Map<String, dynamic>,
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Order>> adminOrdersSubmitWarehouseQuote(
    String id, {
    required double shippingFee,
    String? shippingMethod,
    String? paymentMethod,
  }) async {
    final body = <String, dynamic>{'shipping_fee': shippingFee};
    final sm = shippingMethod?.trim();
    final pm = paymentMethod?.trim();
    if (sm != null && sm.isNotEmpty) body['shipping_method'] = sm;
    if (pm != null && pm.isNotEmpty) body['payment_method'] = pm;

    final res = await _client.post<Map<String, dynamic>>(
      '/admin/orders/$id/warehouse-quote',
      body: body,
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

  Future<ApiResponse<List<Order>>> employeeOrdersList({
    String? status,
    bool assignedToMe = false,
  }) async {
    final params = <String, String>{};
    if (status != null && status.isNotEmpty) params['status'] = status;
    if (assignedToMe) params['assigned_to_me'] = '1';

    final res = await _client.get<dynamic>(
      '/employees/orders',
      params: params.isEmpty ? null : params,
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

  Future<ApiResponse<Order>> employeeOrdersGet(String id) async {
    final res = await _client.get<Map<String, dynamic>>('/employees/orders/$id');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Order.fromJson(res.data as Map<String, dynamic>),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Order>> employeeOrdersUpdateStatus(String id, String status) async {
    final res = await _client.patch<Map<String, dynamic>>(
      '/employees/orders/$id/status',
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

  Future<ApiResponse<Order>> employeeOrdersSubmitWarehouseQuote(
    String id, {
    required double shippingFee,
    String? shippingMethod,
    String? paymentMethod,
  }) async {
    final body = <String, dynamic>{'shipping_fee': shippingFee};
    final sm = shippingMethod?.trim();
    final pm = paymentMethod?.trim();
    if (sm != null && sm.isNotEmpty) body['shipping_method'] = sm;
    if (pm != null && pm.isNotEmpty) body['payment_method'] = pm;

    final res = await _client.post<Map<String, dynamic>>(
      '/employees/orders/$id/warehouse-quote',
      body: body,
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

  Future<ApiResponse<List<Employee>>> adminEmployeesList({
    int page = 1,
    int perPage = 100,
    String? search,
  }) async {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
      if (search != null && search.isNotEmpty) 'search': search,
    };
    final res = await _client.get<dynamic>('/admin/employees', params: params);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: _extractEmployeeList(res.data),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Employee>> adminEmployeesCreate(Map<String, dynamic> data) async {
    final res = await _client.post<Map<String, dynamic>>('/admin/employees', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Employee.fromJson(Map<String, dynamic>.from(res.data as Map)),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Employee>> adminEmployeesUpdate(
    String id,
    Map<String, dynamic> data,
  ) async {
    final res = await _client.put<Map<String, dynamic>>('/admin/employees/$id', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Employee.fromJson(Map<String, dynamic>.from(res.data as Map)),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<dynamic>> adminEmployeesDelete(String id) async {
    return _client.delete('/admin/employees/$id');
  }

  Future<ApiResponse<List<Map<String, dynamic>>>> adminPublishersList({
    int perPage = 200,
  }) async {
    final res = await _client.get<dynamic>(
      '/admin/publishers',
      params: {'per_page': '$perPage'},
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: _extractMapList(res.data),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Map<String, dynamic>>> adminPublishersCreate(
    Map<String, dynamic> data,
  ) async {
    final res = await _client.post<Map<String, dynamic>>('/admin/publishers', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Map<String, dynamic>.from(res.data as Map),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Map<String, dynamic>>> adminPublishersUpdate(
    String id,
    Map<String, dynamic> data,
  ) async {
    final res = await _client.put<Map<String, dynamic>>('/admin/publishers/$id', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Map<String, dynamic>.from(res.data as Map),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<dynamic>> adminPublishersDelete(String id) async {
    return _client.delete('/admin/publishers/$id');
  }

  Future<ApiResponse<Map<String, dynamic>>> adminWarehousesCreate(
    Map<String, dynamic> data,
  ) async {
    final res = await _client.post<Map<String, dynamic>>('/admin/warehouses', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Map<String, dynamic>.from(res.data as Map),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Map<String, dynamic>>> adminWarehousesUpdate(
    String id,
    Map<String, dynamic> data,
  ) async {
    final res = await _client.put<Map<String, dynamic>>('/admin/warehouses/$id', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Map<String, dynamic>.from(res.data as Map),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<dynamic>> adminWarehousesDelete(String id) async {
    return _client.delete('/admin/warehouses/$id');
  }

  Future<ApiResponse<Author>> adminAuthorsUpdate(
    String id,
    Map<String, dynamic> data,
  ) async {
    final res = await _client.put<Map<String, dynamic>>('/admin/authors/$id', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Author.fromJson(Map<String, dynamic>.from(res.data as Map)),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Category>> adminCategoriesUpdate(
    String id,
    Map<String, dynamic> data,
  ) async {
    final res = await _client.put<Map<String, dynamic>>('/admin/categories/$id', body: data);
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Category.fromJson(Map<String, dynamic>.from(res.data as Map)),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<List<Map<String, dynamic>>>> adminCustomersList({
    int perPage = 100,
  }) async {
    final res = await _client.get<dynamic>(
      '/admin/customers',
      params: {'per_page': '$perPage'},
    );
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: _extractMapList(res.data),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  Future<ApiResponse<Map<String, dynamic>>> adminGetSettings() async {
    final res = await _client.get<Map<String, dynamic>>('/admin/settings');
    if (res.success && res.data != null) {
      return ApiResponse(
        success: true,
        message: res.message,
        data: Map<String, dynamic>.from(res.data as Map),
      );
    }
    return ApiResponse(success: false, message: res.message, data: null);
  }

  List<Employee> _extractEmployeeList(dynamic d) {
    if (d is Map && d['data'] is List) {
      return (d['data'] as List)
          .whereType<Map>()
          .map((e) => Employee.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    }
    if (d is List) {
      return d
          .whereType<Map>()
          .map((e) => Employee.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    }
    return [];
  }

  List<Map<String, dynamic>> _extractMapList(dynamic d) {
    if (d is Map && d['data'] is List) {
      return (d['data'] as List)
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    if (d is List) {
      return d.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    }
    return [];
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
