import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../utils/print_page.dart' if (dart.library.js_interop) '../utils/print_page_web.dart';
import '../utils/weight_format.dart';
import '../widgets/pos_section_nav.dart';

class AdminPosInvoiceScreen extends StatefulWidget {
  const AdminPosInvoiceScreen({super.key, required this.invoiceId, this.initialInvoice});

  final String invoiceId;
  final Map<String, dynamic>? initialInvoice;

  @override
  State<AdminPosInvoiceScreen> createState() => _AdminPosInvoiceScreenState();
}

class _AdminPosInvoiceScreenState extends State<AdminPosInvoiceScreen> {
  Map<String, dynamic>? _invoice;
  List<dynamic> _warehouses = [];
  bool _loading = true;
  String? _error;
  String _weightUnit = 'kg';

  @override
  void initState() {
    super.initState();
    _invoice = widget.initialInvoice;
    _loading = widget.initialInvoice == null;
    _load();
  }

  Future<void> _load() async {
    setState(() {
      if (widget.initialInvoice == null) {
        _loading = true;
      }
      _error = null;
    });
    final whRes = await ApiService.instance.adminWarehousesList();
    final settingsRes = await ApiService.instance.getSettings();
    final res = await ApiService.instance.adminPosGetInvoice(widget.invoiceId);
    if (!mounted) return;
    Map<String, dynamic>? invoice = widget.initialInvoice;
    if (res.success && res.data is Map) {
      final data = Map<String, dynamic>.from(res.data as Map);
      invoice = data['data'] is Map ? Map<String, dynamic>.from(data['data'] as Map) : data;
    } else if (invoice == null) {
      _error = res.message;
    }
    setState(() {
      _loading = false;
      _invoice = invoice;
      if (whRes.success && whRes.data != null) {
        _warehouses = whRes.data!;
      }
      if (settingsRes.success && settingsRes.data != null) {
        final unit = settingsRes.data!['weight_unit']?.toString();
        if (unit == 'kg' || unit == 'g' || unit == 'lb' || unit == 'oz') {
          _weightUnit = unit!;
        }
      }
      if (invoice == null && _error == null) {
        _error = AppLocalizations.of(context).adminInvoiceNotFound;
      }
    });
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

  double _asMoney(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _money(num value) => '\$${value.toDouble().toStringAsFixed(2)}';

  Map<String, dynamic>? _warehouseMap(Map<String, dynamic> inv) {
    final nested = inv['warehouse'];
    if (nested is Map) return Map<String, dynamic>.from(nested);
    final id = inv['warehouse_id']?.toString();
    final match = _warehouses.cast<dynamic>().where((w) => w is Map && w['_id']?.toString() == id);
    if (match.isNotEmpty) return Map<String, dynamic>.from(match.first as Map);
    return null;
  }

  String _warehouseName(Map<String, dynamic> inv) {
    return _warehouseMap(inv)?['name']?.toString() ?? inv['warehouse_id']?.toString() ?? '-';
  }

  String? _publisherName(Map<String, dynamic> inv) {
    final warehouse = _warehouseMap(inv);
    if (warehouse == null) return null;
    final publisher = warehouse['publisher'];
    if (publisher is Map) {
      final name = publisher['name']?.toString();
      if (name != null && name.isNotEmpty) return name;
    }
    if (publisher is String && publisher.isNotEmpty) return publisher;
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminInvoiceId),
        actions: [
          IconButton(
            icon: const Icon(Icons.print),
            tooltip: t.adminPrint,
            onPressed: _invoice == null ? null : printPage,
          ),
        ],
      ),
      body: Column(
        children: [
          const PosSectionNav(reportsActive: true),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _invoice == null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error ?? t.adminInvoiceNotFound, style: TextStyle(color: theme.colorScheme.error)),
                            const SizedBox(height: 16),
                            TextButton(
                              onPressed: () => Navigator.of(context).pushReplacementNamed('/admin/pos/reports'),
                              child: Text(t.adminBackToInvoices),
                            ),
                          ],
                        ),
                      )
                    : _buildInvoice(t, theme, _invoice!),
          ),
        ],
      ),
    );
  }

  Widget _buildInvoice(AppLocalizations t, ThemeData theme, Map<String, dynamic> inv) {
    final total = _asMoney(inv['total']);
    final name = (inv['customer_name']?.toString().isNotEmpty == true) ? inv['customer_name'].toString() : t.adminPosWalkIn;
    final items = (inv['items'] as List?) ?? [];
    final created = inv['created_at']?.toString();

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Center(
          child: Column(
            children: [
              Text(t.adminInvoiceId, style: theme.textTheme.headlineSmall),
              const SizedBox(height: 8),
              Text('#${inv['_id'] ?? widget.invoiceId}', style: theme.textTheme.bodySmall),
            ],
          ),
        ),
        const SizedBox(height: 24),
        Text('${t.adminDate}: ${_formatDate(created)}'),
        const SizedBox(height: 4),
        Text('${t.adminWarehouse}: ${_warehouseName(inv)}'),
        if (_publisherName(inv) case final publisherName?) ...[
          const SizedBox(height: 4),
          Text('${t.adminPublisher}: $publisherName'),
        ],
        const SizedBox(height: 4),
        Text('${t.customerLabel}: $name'),
        const Divider(height: 32),
        Row(
          children: [
            Expanded(child: Text(t.adminItemTitle, style: theme.textTheme.labelLarge)),
            SizedBox(
              width: 72,
              child: Text(t.bookWeight, style: theme.textTheme.labelLarge, textAlign: TextAlign.end),
            ),
            const SizedBox(width: 12),
            SizedBox(
              width: 72,
              child: Text(t.adminItemPrice, style: theme.textTheme.labelLarge, textAlign: TextAlign.end),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ...items.map((raw) {
          final item = Map<String, dynamic>.from(raw as Map);
          final qty = item['quantity'] as num? ?? 1;
          final price = _asMoney(item['price']);
          final lineW = lineWeightGrams(item['weight'] as num?, qty);
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Row(
              children: [
                Expanded(child: Text('${item['book_title'] ?? item['book_id']}  x$qty')),
                SizedBox(
                  width: 72,
                  child: Text(
                    lineW > 0 ? formatWeight(lineW, _weightUnit) : '—',
                    textAlign: TextAlign.end,
                    style: theme.textTheme.bodySmall,
                  ),
                ),
                const SizedBox(width: 12),
                SizedBox(
                  width: 72,
                  child: Text(_money(price * qty), textAlign: TextAlign.end),
                ),
              ],
            ),
          );
        }),
        const Divider(height: 32),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(t.booksSubtotalLabel),
            Text(_money(inv['books_subtotal'] != null
                ? _asMoney(inv['books_subtotal'])
                : items.fold<double>(0, (s, raw) {
                    final item = Map<String, dynamic>.from(raw as Map);
                    return s + _asMoney(item['price']) * (item['quantity'] as num? ?? 1);
                  }))),
          ],
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(t.shippingFeeLabel),
            Text(_money(_asMoney(inv['shipping_fee']))),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(t.ordersTotalLabel, style: theme.textTheme.titleLarge),
            Text(_money(total), style: theme.textTheme.titleLarge?.copyWith(color: theme.colorScheme.primary)),
          ],
        ),
        Builder(
          builder: (context) {
            final totalW = items.fold<double>(0, (s, raw) {
              final item = Map<String, dynamic>.from(raw as Map);
              return s + lineWeightGrams(item['weight'] as num?, item['quantity'] as num?);
            });
            if (totalW <= 0) return const SizedBox.shrink();
            return Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(t.totalWeightLabel),
                  Text(formatWeight(totalW, _weightUnit)),
                ],
              ),
            );
          },
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
                onPressed: () => Navigator.of(context).pushReplacementNamed('/admin/pos/reports'),
                child: Text(t.adminBackToInvoices),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
