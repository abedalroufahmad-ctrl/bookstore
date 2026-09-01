import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../config.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';
import '../providers/auth_provider.dart';
import '../utils/print_page.dart' if (dart.library.html) '../utils/print_page_web.dart';
import '../widgets/pos_section_nav.dart';

class AdminPosScreen extends StatefulWidget {
  const AdminPosScreen({super.key});

  @override
  State<AdminPosScreen> createState() => _AdminPosScreenState();
}

class _AdminPosScreenState extends State<AdminPosScreen> {
  String _warehouseId = '';
  String _publisherId = '';
  List<dynamic> _warehouses = [];
  List<Publisher> _publishers = [];
  List<Book> _books = [];
  bool _loadingBooks = false;
  String _searchQuery = '';
  int _page = 1;
  int _lastPage = 1;

  final List<_PosCartItem> _cart = [];
  final TextEditingController _customerNameCtrl = TextEditingController();
  final TextEditingController _searchCtrl = TextEditingController();
  bool _submitting = false;
  Map<String, dynamic>? _createdInvoice;

  @override
  void initState() {
    super.initState();
    _loadLookups();
  }

  @override
  void dispose() {
    _customerNameCtrl.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  String _warehousePublisherId(dynamic w) {
    if (w is! Map) return '';
    if (w['publisher_id'] != null) return w['publisher_id'].toString();
    final publisher = w['publisher'];
    if (publisher is Map && publisher['_id'] != null) {
      return publisher['_id'].toString();
    }
    return '';
  }

  List<dynamic> get _filteredWarehouses {
    if (_publisherId.isEmpty) return _warehouses;
    return _warehouses.where((w) => _warehousePublisherId(w) == _publisherId).toList();
  }

  String? _defaultWarehouseId() {
    final emp = context.read<AuthProvider>().employee;
    if (emp?.warehouseId != null && emp!.warehouseId!.isNotEmpty) {
      return emp.warehouseId;
    }
    if (emp?.warehouseIds != null && emp!.warehouseIds!.isNotEmpty) {
      return emp.warehouseIds!.first;
    }
    return null;
  }

  Future<void> _loadLookups() async {
    final res = await ApiService.instance.adminWarehousesList();
    final pubRes = await ApiService.instance.getPublishersPaginated(1, perPage: 100);
    if (!mounted) return;
    setState(() {
      if (res.success && res.data != null) {
        _warehouses = res.data!;
      }
      if (pubRes.success && pubRes.data != null) {
        _publishers = pubRes.data!.items;
      }
      final defaultWh = _defaultWarehouseId();
      final list = _filteredWarehouses;
      if (defaultWh != null && list.any((w) => w['_id']?.toString() == defaultWh)) {
        _warehouseId = defaultWh;
      } else if (list.isNotEmpty) {
        _warehouseId = list.first['_id']?.toString() ?? '';
      }
    });
    if (_warehouseId.isNotEmpty) {
      await _loadBooks();
    }
  }

  Future<void> _loadBooks() async {
    if (_warehouseId.isEmpty) return;
    setState(() => _loadingBooks = true);
    final res = await ApiService.instance.adminPosBooks(
      page: _page,
      search: _searchQuery.trim().isEmpty ? null : _searchQuery.trim(),
      perPage: 12,
      warehouseId: _warehouseId,
      publisherId: _publisherId.isEmpty ? null : _publisherId,
    );
    if (!mounted) return;
    setState(() {
      _loadingBooks = false;
      _books = res.data?.items ?? [];
      _lastPage = res.data?.lastPage ?? 1;
    });
  }

  String _coverUrl(Book book) {
    final path = (book.coverImageThumb ?? book.coverImage)?.trim();
    if (path == null || path.isEmpty) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    final origin = Uri.parse(apiBaseUrl).origin;
    return path.startsWith('/') ? '$origin$path' : '$origin/$path';
  }

  String _warehouseLabel(dynamic w) {
    if (w is! Map) return '';
    final name = w['name']?.toString() ?? '';
    final publisher = w['publisher'];
    final pubName = publisher is Map ? publisher['name']?.toString() : null;
    if (pubName != null && pubName.isNotEmpty) return '$name — $pubName';
    return name;
  }

  String _warehouseNameById(String? id) {
    final match = _warehouses.where((w) => w is Map && w['_id']?.toString() == id);
    if (match.isNotEmpty) return (match.first['name'] ?? id ?? '').toString();
    return id ?? '-';
  }

  double _unitPrice(Book book) {
    final discount = book.discountPercent ?? 0;
    return book.price - (book.price * discount / 100);
  }

  void _addToCart(Book book) {
    if (book.stockQuantity <= 0) return;
    setState(() {
      final existingIdx = _cart.indexWhere((i) => i.book.id == book.id);
      if (existingIdx >= 0) {
        if (_cart[existingIdx].quantity < book.stockQuantity) {
          _cart[existingIdx].quantity++;
        }
      } else {
        _cart.add(_PosCartItem(book: book, quantity: 1));
      }
    });
  }

  void _updateQuantity(int index, int delta) {
    setState(() {
      final item = _cart[index];
      final newQ = item.quantity + delta;
      if (newQ <= 0) {
        _cart.removeAt(index);
      } else if (newQ <= item.book.stockQuantity) {
        item.quantity = newQ;
      }
    });
  }

  double get _subtotal => _cart.fold(0, (sum, item) => sum + (_unitPrice(item.book) * item.quantity));

  Future<void> _checkout() async {
    if (_cart.isEmpty || _warehouseId.isEmpty || _submitting) return;
    setState(() => _submitting = true);
    final items = _cart.map((i) => {'book_id': i.book.id, 'quantity': i.quantity}).toList();
    final res = await ApiService.instance.adminPosCreateInvoice(
      items: items,
      warehouseId: _warehouseId,
      customerName: _customerNameCtrl.text.trim(),
    );
    if (!mounted) return;
    setState(() => _submitting = false);
    if (res.success && res.data != null) {
      final data = res.data;
      Map<String, dynamic>? invoice;
      if (data is Map) {
        final map = Map<String, dynamic>.from(data);
        final nested = map['data'];
        if (nested is Map && (nested.containsKey('_id') || nested.containsKey('items'))) {
          invoice = Map<String, dynamic>.from(nested);
        } else {
          invoice = map;
        }
      }
      setState(() {
        _createdInvoice = invoice;
        _cart.clear();
        _customerNameCtrl.clear();
      });
      _loadBooks();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
    }
  }

  String _formatDate(dynamic raw) {
    final parsed = DateTime.tryParse(raw?.toString() ?? '');
    if (parsed == null) return raw?.toString() ?? '-';
    final l = parsed.toLocal();
    final mm = l.month.toString().padLeft(2, '0');
    final dd = l.day.toString().padLeft(2, '0');
    final hh = l.hour.toString().padLeft(2, '0');
    final min = l.minute.toString().padLeft(2, '0');
    return '${l.year}-$mm-$dd $hh:$min';
  }

  String _money(num value) => '\$${value.toDouble().toStringAsFixed(2)}';

  Widget _buildInvoiceView() {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final total = (_createdInvoice!['total'] as num?)?.toDouble() ?? 0;
    final created = _createdInvoice!['created_at']?.toString();
    final warehouseId = _createdInvoice!['warehouse_id']?.toString();
    return Scaffold(
      appBar: AppBar(title: Text(t.adminPosTerminal)),
      body: Column(
        children: [
          const PosSectionNav(reportsActive: false),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(24),
              children: [
                Center(
                  child: Column(
                    children: [
                      Text(t.adminPosInvoiceCreated, style: theme.textTheme.headlineSmall),
                      const SizedBox(height: 8),
                      Text('#${_createdInvoice!['_id']}', style: theme.textTheme.bodySmall),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Text('${t.adminDate}: ${_formatDate(created)}'),
                const SizedBox(height: 4),
                Text('${t.adminWarehouse}: ${_warehouseNameById(warehouseId)}'),
                const SizedBox(height: 4),
                Text(
                  '${t.customerLabel}: ${(_createdInvoice!['customer_name']?.toString().isNotEmpty == true) ? _createdInvoice!['customer_name'] : t.adminPosWalkIn}',
                ),
                const Divider(height: 32),
                Row(
                  children: [
                    Expanded(child: Text(t.adminItemTitle, style: theme.textTheme.labelLarge)),
                    Text(t.adminItemPrice, style: theme.textTheme.labelLarge),
                  ],
                ),
                const SizedBox(height: 8),
                ...((_createdInvoice!['items'] as List?) ?? []).map((raw) {
                  final item = Map<String, dynamic>.from(raw as Map);
                  final qty = item['quantity'] as num? ?? 1;
                  final price = (item['price'] as num?)?.toDouble() ?? 0;
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Expanded(child: Text('${item['book_title'] ?? item['book_id']}  x$qty')),
                        Text(_money(price * qty)),
                      ],
                    ),
                  );
                }),
                const Divider(height: 32),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(t.ordersTotalLabel, style: theme.textTheme.titleLarge),
                    Text(_money(total), style: theme.textTheme.titleLarge?.copyWith(color: theme.colorScheme.primary)),
                  ],
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: printPage,
                        icon: const Icon(Icons.print),
                        label: Text(t.adminPrint),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => setState(() => _createdInvoice = null),
                        child: Text(t.adminPosNewSale),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_createdInvoice != null) {
      return _buildInvoiceView();
    }
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final warehouseItems = _filteredWarehouses;
    final warehouseValue = warehouseItems.any((w) => w['_id']?.toString() == _warehouseId)
        ? _warehouseId
        : (warehouseItems.isNotEmpty ? warehouseItems.first['_id']?.toString() : null);

    return Scaffold(
      appBar: AppBar(title: Text(t.adminPosTerminal)),
      body: Column(
        children: [
          const PosSectionNav(reportsActive: false),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                if (_publishers.isNotEmpty)
                  DropdownButton<String>(
                    value: _publisherId.isEmpty ? '' : _publisherId,
                    items: [
                      DropdownMenuItem(value: '', child: Text(t.adminAllPublishers)),
                      ..._publishers.map((p) => DropdownMenuItem(value: p.id, child: Text(p.name ?? p.id))),
                    ],
                    onChanged: (val) {
                      if (val == null) return;
                      setState(() {
                        _publisherId = val;
                        _page = 1;
                        _cart.clear();
                        final list = _filteredWarehouses;
                        final defaultWh = _defaultWarehouseId();
                        if (defaultWh != null && list.any((w) => w['_id']?.toString() == defaultWh)) {
                          _warehouseId = defaultWh;
                        } else {
                          _warehouseId = list.isNotEmpty ? (list.first['_id']?.toString() ?? '') : '';
                        }
                      });
                      _loadBooks();
                    },
                  ),
                if (warehouseItems.isNotEmpty)
                  DropdownButton<String>(
                    value: warehouseValue,
                    items: warehouseItems.map((w) {
                      return DropdownMenuItem<String>(
                        value: w['_id']?.toString() ?? '',
                        child: Text(_warehouseLabel(w)),
                      );
                    }).toList(),
                    onChanged: (val) {
                      if (val != null) {
                        setState(() {
                          _warehouseId = val;
                          _page = 1;
                          _cart.clear();
                        });
                        _loadBooks();
                      }
                    },
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(t.adminPosOwnWarehouseDefault, style: theme.textTheme.bodySmall),
            ),
          ),
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final stacked = constraints.maxWidth < 720;
                final catalog = _buildCatalog(t, theme);
                final cart = _buildCart(t, theme);
                if (stacked) {
                  return Column(
                    children: [
                      Expanded(flex: 3, child: catalog),
                      Expanded(flex: 2, child: cart),
                    ],
                  );
                }
                return Row(
                  children: [
                    Expanded(flex: 3, child: catalog),
                    SizedBox(width: 320, child: cart),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCatalog(AppLocalizations t, ThemeData theme) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(8.0),
          child: TextField(
            controller: _searchCtrl,
            decoration: InputDecoration(
              hintText: t.searchHint,
              suffixIcon: IconButton(
                icon: const Icon(Icons.search),
                onPressed: () {
                  setState(() {
                    _searchQuery = _searchCtrl.text;
                    _page = 1;
                  });
                  _loadBooks();
                },
              ),
              border: const OutlineInputBorder(),
            ),
            onSubmitted: (val) {
              setState(() {
                _searchQuery = val;
                _page = 1;
              });
              _loadBooks();
            },
          ),
        ),
        Expanded(
          child: _loadingBooks
              ? const Center(child: CircularProgressIndicator())
              : GridView.builder(
                  padding: const EdgeInsets.all(8),
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 200,
                    childAspectRatio: 0.65,
                    crossAxisSpacing: 8,
                    mainAxisSpacing: 8,
                  ),
                  itemCount: _books.length,
                  itemBuilder: (ctx, i) {
                    final b = _books[i];
                    final cover = _coverUrl(b);
                    return InkWell(
                      onTap: () => _addToCart(b),
                      child: Card(
                        clipBehavior: Clip.antiAlias,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Expanded(
                              child: cover.isNotEmpty
                                  ? Image.network(cover, fit: BoxFit.cover, errorBuilder: (_, _, _) => const Center(child: Icon(Icons.book, size: 48)))
                                  : const Center(child: Icon(Icons.book, size: 48)),
                            ),
                            Padding(
                              padding: const EdgeInsets.all(8.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(b.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: theme.textTheme.bodySmall),
                                  const SizedBox(height: 4),
                                  Text(_money(_unitPrice(b)), style: theme.textTheme.titleSmall?.copyWith(color: theme.colorScheme.primary)),
                                  Text('Qty: ${b.stockQuantity}', style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.secondary)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ),
        if (_lastPage > 1)
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                onPressed: _page > 1
                    ? () {
                        setState(() => _page--);
                        _loadBooks();
                      }
                    : null,
                icon: const Icon(Icons.chevron_left),
              ),
              Text('$_page / $_lastPage'),
              IconButton(
                onPressed: _page < _lastPage
                    ? () {
                        setState(() => _page++);
                        _loadBooks();
                      }
                    : null,
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
      ],
    );
  }

  Widget _buildCart(AppLocalizations t, ThemeData theme) {
    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border(left: BorderSide(color: theme.dividerColor)),
      ),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            color: theme.colorScheme.primary,
            child: Text(t.adminCurrentSale, style: theme.textTheme.titleMedium?.copyWith(color: theme.colorScheme.onPrimary)),
          ),
          Expanded(
            child: _cart.isEmpty
                ? Center(child: Text(t.adminPosCartEmpty))
                : ListView.builder(
                    itemCount: _cart.length,
                    itemBuilder: (ctx, i) {
                      final item = _cart[i];
                      final finalPrice = _unitPrice(item.book);
                      return Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(item.book.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                                  Text(_money(finalPrice), style: theme.textTheme.bodySmall),
                                ],
                              ),
                            ),
                            IconButton(
                              icon: const Icon(Icons.remove, size: 16),
                              onPressed: () => _updateQuantity(i, -1),
                            ),
                            Text('${item.quantity}'),
                            IconButton(
                              icon: const Icon(Icons.add, size: 16),
                              onPressed: item.quantity >= item.book.stockQuantity ? null : () => _updateQuantity(i, 1),
                            ),
                            SizedBox(
                              width: 64,
                              child: Text(_money(finalPrice * item.quantity), textAlign: TextAlign.end),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextField(
                  controller: _customerNameCtrl,
                  decoration: InputDecoration(
                    labelText: t.adminPosCustomerOptional,
                    hintText: t.adminPosWalkIn,
                    border: const OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(t.ordersTotalLabel, style: theme.textTheme.titleLarge),
                    Text(_money(_subtotal), style: theme.textTheme.titleLarge?.copyWith(color: theme.colorScheme.primary)),
                  ],
                ),
                const SizedBox(height: 16),
                FilledButton(
                  style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                  onPressed: _cart.isEmpty || _submitting ? null : _checkout,
                  child: _submitting ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2)) : Text(t.adminPosCompleteSale),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PosCartItem {
  _PosCartItem({required this.book, required this.quantity});
  final Book book;
  int quantity;
}
