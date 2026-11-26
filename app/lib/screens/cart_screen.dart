import 'package:flutter/material.dart';
import 'package:getwidget/getwidget.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/cart.dart';
import '../providers/auth_provider.dart';
import '../providers/currency_provider.dart';
import '../utils/price_format.dart';

class CartScreen extends StatefulWidget {
  final bool showAppBar;

  const CartScreen({super.key, this.showAppBar = true});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  List<CartItem> _items = [];
  double? _total;
  bool _loading = true;
  UserType? _loadedFor;
  String? _loadedCurrency;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final type = context.watch<AuthProvider>().userType;
    final currency = context.watch<CurrencyProvider>().currencyCode;
    if (_loadedFor != type || _loadedCurrency != currency) {
      _loadedFor = type;
      _loadedCurrency = currency;
      if (type == UserType.customer) {
        _load(currency);
      } else if (mounted) {
        setState(() {
          _loading = false;
          _items = [];
          _total = null;
        });
      }
    }
  }

  Future<void> _load([String? currencyCode]) async {
    if (!mounted) return;
    final code =
        currencyCode ?? context.read<CurrencyProvider>().currencyCode;
    setState(() => _loading = true);
    final res = await ApiService.instance.getCart(currencyCode: code);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _items = res.data!.items;
        _total = res.data!.total;
      } else {
        _items = [];
        _total = null;
      }
    });
  }

  Future<void> _remove(String bookId) async {
    final code = context.read<CurrencyProvider>().currencyCode;
    await ApiService.instance.removeFromCart(bookId, currencyCode: code);
    _load(code);
  }

  Future<void> _updateQty(String bookId, int qty, String currencyCode) async {
    if (qty < 1) return;
    final shopper = context.read<CurrencyProvider>().currencyCode;
    await ApiService.instance.updateCartItem(bookId, qty, currencyCode: currencyCode);
    if (!mounted) return;
    _load(shopper);
  }

  String _lineCurrency(CartItem item, String shopperCurrency) {
    if (item.currencyAvailable) return shopperCurrency.toUpperCase();
    return (item.book?.warehouseCurrencyCode ?? item.currencyCode).toUpperCase();
  }

  Widget _scaffold({required Widget body}) {
    return Scaffold(
      appBar: widget.showAppBar ? AppBar(title: Text(AppLocalizations.of(context).cartTitle)) : null,
      body: body,
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final auth = context.watch<AuthProvider>();
    final currency = context.watch<CurrencyProvider>();

    // Cart lives in the bottom-nav IndexedStack — never replace the root route with /login.
    if (auth.userType != UserType.customer) {
      final isStaff = auth.userType == UserType.employee;
      return _scaffold(
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  isStaff
                      ? (t.isAr
                          ? 'سلة المشتريات للعملاء فقط'
                          : 'Cart is for customers only')
                      : t.cartLoginMsg,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
                if (!isStaff) ...[
                  const SizedBox(height: 24),
                  GFButton(
                    onPressed: () => Navigator.pushNamed(context, '/login'),
                    text: t.navLogin,
                    fullWidthButton: true,
                    size: GFSize.LARGE,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ],
              ],
            ),
          ),
        ),
      );
    }

    if (_loading) {
      return _scaffold(
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    if (_items.isEmpty) {
      return _scaffold(
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(t.cartEmpty),
              const SizedBox(height: 16),
              GFButton(
                onPressed: () => Navigator.pushReplacementNamed(context, '/'),
                text: t.viewAll,
                color: Theme.of(context).colorScheme.primary,
              ),
            ],
          ),
        ),
      );
    }
    final shopper = currency.currencyCode.toUpperCase();
    final groups = _groupByWarehouse(t, shopper);
    final checkoutBlocked = _items.any((item) => item.price == null);
    final mixedCurrencies = _items.map((item) => _lineCurrency(item, shopper)).toSet().length > 1;

    return Scaffold(
      appBar: widget.showAppBar ? AppBar(title: Text(t.cartTitle)) : null,
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: const Color(0xFFBFDBFE)),
                  ),
                  child: Text(
                    t.cartWarehouseSplitNotice,
                    style: const TextStyle(color: Color(0xFF1E40AF), fontSize: 13),
                  ),
                ),
                for (final group in groups) ...[
                  Card(
                    margin: const EdgeInsets.only(bottom: 16),
                    clipBehavior: Clip.antiAlias,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Container(
                          color: Theme.of(context).colorScheme.surfaceContainerHighest,
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  '${group.name}  ${group.warehouseCurrency}',
                                  style: Theme.of(context).textTheme.titleSmall,
                                ),
                              ),
                              Text(
                                group.priced
                                    ? formatBookPrice(group.subtotal, group.priceCurrency)
                                    : t.cartCurrencyUnavailable(group.priceCurrency),
                                style: TextStyle(
                                  color: group.priced
                                      ? Theme.of(context).colorScheme.primary
                                      : Theme.of(context).colorScheme.error,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                        ),
                        for (final item in group.items)
                          ListTile(
                            title: Text(item.book?.title ?? 'Book'),
                            subtitle: Text(
                              item.price == null
                                  ? t.cartCurrencyUnavailable(_lineCurrency(item, shopper))
                                  : '${formatBookPrice(item.price!, _lineCurrency(item, shopper))} × ${item.quantity}'
                                      '${(item.book?.discountPercent ?? 0) > 0 ? '  ${t.discountBadge(item.book!.discountPercent!)}' : ''}',
                            ),
                            trailing: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                IconButton(
                                  icon: const Icon(Icons.remove),
                                  onPressed: item.quantity <= 1 || item.price == null
                                      ? null
                                      : () => _updateQty(
                                            item.bookId,
                                            item.quantity - 1,
                                            _lineCurrency(item, shopper),
                                          ),
                                ),
                                Text('${item.quantity}'),
                                IconButton(
                                  icon: const Icon(Icons.add),
                                  onPressed: item.price == null
                                      ? null
                                      : () => _updateQty(
                                            item.bookId,
                                            item.quantity + 1,
                                            _lineCurrency(item, shopper),
                                          ),
                                ),
                                IconButton(
                                  icon: const Icon(Icons.delete, color: Colors.red),
                                  onPressed: () => _remove(item.bookId),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(t.cartGrandTotal, style: Theme.of(context).textTheme.bodySmall),
                      Text(
                        _total == null ? '—' : formatBookPrice(_total!, shopper),
                        style: Theme.of(context).textTheme.titleLarge,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (mixedCurrencies)
                        Text(
                          t.cartWarehouseCurrencyNote,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                GFButton(
                  onPressed: checkoutBlocked
                      ? null
                      : () => Navigator.pushNamed(context, '/checkout'),
                  text: t.checkout,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  List<_CartWarehouseGroup> _groupByWarehouse(AppLocalizations t, String shopper) {
    final groups = <_CartWarehouseGroup>[];
    for (final item in _items) {
      final id = item.book?.warehouseId ?? 'unknown';
      final lineCurrency = _lineCurrency(item, shopper);
      final existing = groups.where((group) => group.id == id);
      final group = existing.isEmpty
          ? _CartWarehouseGroup(
              id: id,
              name: item.book?.warehouseName ?? t.cartUnknownWarehouse,
              warehouseCurrency: (item.book?.warehouseCurrencyCode ?? lineCurrency).toUpperCase(),
              priceCurrency: lineCurrency,
            )
          : existing.first;
      if (existing.isEmpty) groups.add(group);
      group.items.add(item);
      if (item.price == null || item.subtotal == null || lineCurrency != group.priceCurrency) {
        group.priced = false;
      } else {
        group.subtotal += item.subtotal!;
      }
    }
    return groups;
  }
}

class _CartWarehouseGroup {
  _CartWarehouseGroup({
    required this.id,
    required this.name,
    required this.warehouseCurrency,
    required this.priceCurrency,
  });

  final String id;
  final String name;
  final String warehouseCurrency;
  final String priceCurrency;
  final List<CartItem> items = [];
  double subtotal = 0;
  bool priced = true;
}
