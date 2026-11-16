import 'package:flutter/material.dart';
import '../theme.dart';
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
  double _total = 0;
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
          _total = 0;
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
        _total = 0;
      }
    });
  }

  Future<void> _remove(String bookId) async {
    final code = context.read<CurrencyProvider>().currencyCode;
    await ApiService.instance.removeFromCart(bookId, currencyCode: code);
    _load(code);
  }

  Future<void> _updateQty(String bookId, int qty) async {
    if (qty < 1) return;
    final code = context.read<CurrencyProvider>().currencyCode;
    await ApiService.instance.updateCartItem(bookId, qty, currencyCode: code);
    _load(code);
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
    return Scaffold(
      appBar: widget.showAppBar ? AppBar(title: Text(t.cartTitle)) : null,
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _items.length,
              itemBuilder: (context, i) {
                final item = _items[i];
                final title = item.book?.title ?? 'Book';
                final lineSymbol = item.book?.currencySymbol ?? currency.currentSymbol;
                return GFCard(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: EdgeInsets.zero,
                  content: ListTile(
                    title: Text(title),
                    subtitle: Row(
                      children: [
                        Flexible(
                          child: Text(
                            '${formatBookPrice(item.price, lineSymbol)} × ${item.quantity}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (item.book != null && (item.book!.discountPercent ?? 0) > 0) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                            decoration: BoxDecoration(
                              color: AppColors.navy100,
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              ' خصم ${item.book!.discountPercent}%',
                              style: const TextStyle(fontSize: 10, color: AppColors.navy700, fontWeight: FontWeight.bold),
                            ),
                          ),
                        ],
                      ],
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          icon: const Icon(Icons.remove),
                          onPressed: item.quantity <= 1
                              ? null
                              : () => _updateQty(item.bookId, item.quantity - 1),
                        ),
                        Text('${item.quantity}'),
                        IconButton(
                          icon: const Icon(Icons.add),
                          onPressed: () =>
                              _updateQty(item.bookId, item.quantity + 1),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete, color: Colors.red),
                          onPressed: () => _remove(item.bookId),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.surfaceContainerHighest,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    '${t.totalLabel}: ${formatBookPrice(_total, currency.currentSymbol)}',
                    style: Theme.of(context).textTheme.titleLarge,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                const SizedBox(width: 8),
                GFButton(
                  onPressed: () => Navigator.pushNamed(context, '/checkout'),
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
}
