import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/order.dart';

/// Customer view of one order (`GET /customers/orders/{id}`), mirrors web OrderDetail.tsx.
class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Order? _order;
  bool _loading = true;
  String? _error;
  bool _confirmBusy = false;
  bool _paypalBusy = false;
  String? _actionMessage;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await ApiService.instance.customerOrderGet(widget.orderId);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _order = res.data!;
      } else {
        _order = null;
        _error = res.message.isNotEmpty ? res.message : null;
      }
    });
  }

  static String _idSuffix(String id) {
    if (id.length <= 8) return id;
    return id.substring(id.length - 8);
  }

  String _shippingOneLine(Order o) {
    final m = o.shippingAddress;
    if (m == null || m.isEmpty) return '';
    final parts = <String>[
      m['address']?.toString() ?? '',
      m['city']?.toString() ?? '',
      m['country']?.toString() ?? '',
      m['postal_code']?.toString() ?? '',
    ].where((s) => s.trim().isNotEmpty).toList();
    return parts.join(', ');
  }

  String _itemLineTitle(AppLocalizations t, OrderItem item) {
    if (item.bookTitle != null && item.bookTitle!.trim().isNotEmpty) {
      return item.bookTitle!;
    }
    final id = item.bookId.trim();
    if (id.length > 8) return '${t.orderBookFallback} (${id.substring(id.length - 8)})';
    return id.isEmpty ? '—' : id;
  }

  Future<void> _confirmQuote() async {
    if (_confirmBusy || _order == null) return;
    setState(() {
      _confirmBusy = true;
      _actionMessage = null;
    });
    final res = await ApiService.instance.customerConfirmQuote(_order!.id);
    if (!mounted) return;
    setState(() => _confirmBusy = false);
    if (res.success && res.data != null) {
      setState(() => _order = res.data);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
    } else {
      setState(() => _actionMessage = res.message);
    }
  }

  Future<void> _startPayPal() async {
    if (_paypalBusy || _order == null) return;
    setState(() {
      _paypalBusy = true;
      _actionMessage = null;
    });
    final res = await ApiService.instance.paypalStartQuoted([_order!.id]);
    if (!mounted) return;
    setState(() => _paypalBusy = false);

    String? approvalUrl;
    if (res.success && res.data != null && res.data is Map) {
      final m = Map<String, dynamic>.from(res.data! as Map);
      final raw = m['approval_url'];
      approvalUrl =
          raw == null || raw.toString().trim().isEmpty ? null : raw.toString();
    }

    if (res.success &&
        approvalUrl != null &&
        approvalUrl.isNotEmpty) {
      final uri = Uri.tryParse(approvalUrl);
      if (uri != null &&
          await canLaunchUrl(uri) &&
          await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        if (mounted) await _load();
        return;
      }
      setState(() => _actionMessage = AppLocalizations.of(context).error);
      return;
    }

    setState(() => _actionMessage = res.message);
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);

    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: Text(t.orderDetailHeading)),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null || _order == null) {
      return Scaffold(
        appBar: AppBar(title: Text(t.orderDetailHeading)),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  _error ?? t.orderNotFound,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: theme.colorScheme.error),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => Navigator.pop(context),
                  child: Text(t.ordersBackToList),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final o = _order!;
    final canConfirmCod =
        o.status == 'awaiting_customer_confirmation' && (o.paymentMethod ?? '') != 'paypal';
    final canPayPal =
        o.status == 'awaiting_customer_confirmation' && o.paymentMethod == 'paypal';
    final ship = _shippingOneLine(o);

    return Scaffold(
      appBar: AppBar(title: Text('${t.orderDetailHeading} #${_idSuffix(o.id)}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              TextButton.icon(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.arrow_back),
                label: Text(t.ordersBackToList),
              ),
            ],
          ),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    '${t.ordersStatusLabel}: ${t.orderStatus(o.status)}',
                    style: theme.textTheme.bodyLarge,
                  ),
                  if (o.booksSubtotal != null || o.shippingFee != null) ...[
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(t.ordersBooksSubtotal),
                        Text('\$${(o.booksSubtotal ?? 0).toStringAsFixed(2)}'),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(t.shippingFeeLabel),
                        Text('\$${(o.shippingFee ?? 0).toStringAsFixed(2)}'),
                      ],
                    ),
                  ],
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        t.ordersTotalLabel,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        '\$${o.total.toStringAsFixed(2)}',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  if ((o.shippingMethod ?? '').isNotEmpty) ...[
                    Text('${t.shippingMethodLabel}: ${o.shippingMethod}', style: theme.textTheme.bodyMedium),
                    const SizedBox(height: 12),
                  ],
                  Text(
                    '${t.paymentMethodLabel}: ${o.paymentMethod ?? '—'}',
                    style: theme.textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${t.paymentStatusLabel}: ${o.paymentStatus ?? '—'}',
                    style: theme.textTheme.bodyMedium,
                  ),
                  if (ship.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    Text(t.shippingAddressSection, style: theme.textTheme.titleSmall),
                    const SizedBox(height: 4),
                    Text(ship),
                  ],
                  if (o.status == 'awaiting_customer_confirmation') ...[
                    const SizedBox(height: 16),
                    DecoratedBox(
                      decoration: BoxDecoration(
                        color: theme.colorScheme.primaryContainer.withValues(alpha: 0.35),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Text(
                          t.ordersAwaitingQuoteCustomerHint,
                          style: theme.textTheme.bodySmall,
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  Text(t.ordersLineItemsHeading, style: theme.textTheme.titleSmall),
                  const SizedBox(height: 8),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(minWidth: 340),
                        child: Table(
                          border: TableBorder.all(
                            color: theme.colorScheme.outline.withValues(alpha: 0.4),
                          ),
                          columnWidths: const {
                            0: FlexColumnWidth(2),
                            1: FlexColumnWidth(),
                          },
                          children: [
                            TableRow(
                              decoration: BoxDecoration(
                                color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
                              ),
                              children: [
                                Padding(
                                  padding: const EdgeInsets.all(10),
                                  child: Text(
                                    t.ordersItemTitleCol,
                                    style: theme.textTheme.labelLarge,
                                  ),
                                ),
                                Padding(
                                  padding: const EdgeInsets.all(10),
                                  child: Align(
                                    alignment: AlignmentDirectional.centerEnd,
                                    child: Directionality(
                                      textDirection: TextDirection.ltr,
                                      child: Text(
                                        t.ordersItemPriceCol,
                                        style: theme.textTheme.labelLarge,
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            ...o.items.map((item) {
                              final lineQty =
                                  '${item.quantity} × \$${item.price.toStringAsFixed(2)}';
                              return TableRow(
                                children: [
                                  Padding(
                                    padding: const EdgeInsets.all(10),
                                    child: Text(
                                      _itemLineTitle(t, item),
                                      softWrap: true,
                                    ),
                                  ),
                                  Padding(
                                    padding: const EdgeInsets.all(10),
                                    child: Align(
                                      alignment: AlignmentDirectional.centerEnd,
                                      child: Directionality(
                                        textDirection: TextDirection.ltr,
                                        child: Text(lineQty),
                                      ),
                                    ),
                                  ),
                                ],
                              );
                            }),
                          ],
                        ),
                      ),
                    ),
                  ),
                  if (_actionMessage != null && _actionMessage!.trim().isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      _actionMessage!,
                      style: TextStyle(color: theme.colorScheme.error, fontSize: 13),
                    ),
                  ],
                  if (canConfirmCod || canPayPal) ...[
                    const SizedBox(height: 20),
                    if (canConfirmCod)
                      FilledButton(
                        onPressed: _confirmBusy ? null : _confirmQuote,
                        child: _confirmBusy
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : Text(t.ordersConfirmWithWarehouse),
                      ),
                    if (canPayPal) ...[
                      if (canConfirmCod) const SizedBox(height: 12),
                      FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: const Color(0xFF00457C),
                          foregroundColor: Colors.white,
                        ),
                        onPressed: _paypalBusy ? null : _startPayPal,
                        child: _paypalBusy
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : Text(t.ordersPayWithPayPal),
                      ),
                    ],
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
