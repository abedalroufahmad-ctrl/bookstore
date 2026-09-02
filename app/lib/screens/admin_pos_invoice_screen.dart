import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../utils/print_page.dart' if (dart.library.js_interop) '../utils/print_page_web.dart';
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

  String _warehouseName(Map<String, dynamic> inv) {
    final nested = inv['warehouse'];
    if (nested is Map && nested['name'] != null) return nested['name'].toString();
    final id = inv['warehouse_id']?.toString();
    final match = _warehouses.cast<dynamic>().where((w) => w is Map && w['_id']?.toString() == id);
    if (match.isNotEmpty) return (match.first['name'] ?? id ?? '').toString();
    return id ?? '-';
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
        const SizedBox(height: 4),
        Text('${t.customerLabel}: $name'),
        const Divider(height: 32),
        Row(
          children: [
            Expanded(child: Text(t.adminItemTitle, style: theme.textTheme.labelLarge)),
            Text(t.adminItemPrice, style: theme.textTheme.labelLarge),
          ],
        ),
        const SizedBox(height: 8),
        ...items.map((raw) {
          final item = Map<String, dynamic>.from(raw as Map);
          final qty = item['quantity'] as num? ?? 1;
          final price = _asMoney(item['price']);
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Row(
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
        if (inv['publisher_payout_amount'] != null) ...[
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(t.adminPublisherPayoutAmount, style: theme.textTheme.bodySmall),
              Text(_money(_asMoney(inv['publisher_payout_amount'])), style: theme.textTheme.bodySmall),
            ],
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '${t.adminPlatformCommissionAmount}${inv['platform_commission_percent'] != null ? ' (${inv['platform_commission_percent']}%)' : ''}',
                style: theme.textTheme.bodySmall,
              ),
              Text(_money(_asMoney(inv['platform_commission_amount'])), style: theme.textTheme.bodySmall),
            ],
          ),
        ],
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
