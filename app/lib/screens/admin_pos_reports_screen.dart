import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../widgets/pos_section_nav.dart';

class AdminPosReportsScreen extends StatefulWidget {
  const AdminPosReportsScreen({super.key});

  @override
  State<AdminPosReportsScreen> createState() => _AdminPosReportsScreenState();
}

class _AdminPosReportsScreenState extends State<AdminPosReportsScreen> {
  String _warehouseId = '';
  List<dynamic> _warehouses = [];
  String _reportType = 'daily';
  Map<String, dynamic> _summary = {};
  List<dynamic> _periods = [];
  List<dynamic> _invoices = [];
  bool _loading = false;
  int _invoicePage = 1;
  int _invoiceLastPage = 1;

  @override
  void initState() {
    super.initState();
    _loadWarehouses();
  }

  Future<void> _loadWarehouses() async {
    final res = await ApiService.instance.adminWarehousesList();
    if (res.success && res.data != null) {
      if (!mounted) return;
      setState(() => _warehouses = res.data!);
    }
    await _loadReports();
  }

  Future<void> _loadReports() async {
    setState(() => _loading = true);
    final reportsRes = await ApiService.instance.adminPosReports(
      type: _reportType,
      warehouseId: _warehouseId.isEmpty ? null : _warehouseId,
    );
    final invoicesRes = await ApiService.instance.adminPosInvoices(
      page: _invoicePage,
      warehouseId: _warehouseId.isEmpty ? null : _warehouseId,
    );
    if (!mounted) return;

    Map<String, dynamic> summary = {};
    List<dynamic> periods = [];
    if (reportsRes.success && reportsRes.data is Map) {
      final data = Map<String, dynamic>.from(reportsRes.data as Map);
      final inner = data['summary'] != null ? data : (data['data'] is Map ? Map<String, dynamic>.from(data['data'] as Map) : data);
      summary = inner['summary'] is Map ? Map<String, dynamic>.from(inner['summary'] as Map) : {};
      periods = inner['periods'] is List ? inner['periods'] as List : [];
    }

    List<dynamic> invoices = [];
    var lastPage = 1;
    var currentPage = _invoicePage;
    if (invoicesRes.success && invoicesRes.data != null) {
      final d = invoicesRes.data;
      if (d is Map) {
        final map = Map<String, dynamic>.from(d);
        if (map['data'] is List) {
          invoices = map['data'] as List;
          lastPage = (map['last_page'] as num?)?.toInt() ?? 1;
          currentPage = (map['current_page'] as num?)?.toInt() ?? _invoicePage;
        } else if (map['data'] is Map) {
          final nested = Map<String, dynamic>.from(map['data'] as Map);
          if (nested['data'] is List) invoices = nested['data'] as List;
          lastPage = (nested['last_page'] as num?)?.toInt() ?? 1;
          currentPage = (nested['current_page'] as num?)?.toInt() ?? _invoicePage;
        }
      } else if (d is List) {
        invoices = d;
      }
    }

    setState(() {
      _loading = false;
      _summary = summary;
      _periods = periods;
      _invoices = invoices;
      _invoiceLastPage = lastPage < 1 ? 1 : lastPage;
      _invoicePage = currentPage < 1 ? 1 : currentPage;
    });
  }

  double _bucketTotal(String key) {
    final raw = _summary[key];
    if (raw is Map) {
      final t = raw['total'];
      if (t is num) return t.toDouble();
    }
    return 0;
  }

  int _bucketCount(String key) {
    final raw = _summary[key];
    if (raw is Map) {
      final c = raw['count'];
      if (c is num) return c.toInt();
    }
    return 0;
  }

  String _money(num value) => '\$${value.toDouble().toStringAsFixed(2)}';

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

  String _warehouseName(Map<String, dynamic> inv) {
    final nested = inv['warehouse'];
    if (nested is Map && nested['name'] != null) return nested['name'].toString();
    final id = inv['warehouse_id']?.toString();
    final match = _warehouses.where((w) => w is Map && w['_id']?.toString() == id);
    if (match.isNotEmpty) return (match.first['name'] ?? id ?? '').toString();
    return id ?? '-';
  }

  void _openInvoice(Map<String, dynamic> inv) {
    final id = inv['_id']?.toString() ?? inv['id']?.toString() ?? '';
    if (id.isEmpty) return;
    Navigator.of(context).pushNamed('/admin/pos/invoices/$id', arguments: inv);
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(t.adminPosReports)),
      body: Column(
        children: [
          const PosSectionNav(reportsActive: true),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: DropdownButton<String>(
                value: _warehouseId.isEmpty ? '' : _warehouseId,
                items: [
                  DropdownMenuItem(value: '', child: Text(t.adminAllWarehouses)),
                  ..._warehouses.map((w) {
                    return DropdownMenuItem<String>(
                      value: w['_id']?.toString() ?? '',
                      child: Text(w['name'] ?? ''),
                    );
                  }),
                ],
                onChanged: (val) {
                  if (val == null) return;
                  setState(() {
                    _warehouseId = val;
                    _invoicePage = 1;
                  });
                  _loadReports();
                },
              ),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _loadReports,
                    child: ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        Wrap(
                          spacing: 12,
                          runSpacing: 12,
                          children: [
                            _summaryCard(theme, t, t.adminTotalToday, _bucketTotal('today'), _bucketCount('today')),
                            _summaryCard(theme, t, t.adminTotalThisMonth, _bucketTotal('month'), _bucketCount('month')),
                            _summaryCard(theme, t, t.adminTotalThisYear, _bucketTotal('year'), _bucketCount('year')),
                            _summaryCard(theme, t, t.adminTotalAllTime, _bucketTotal('all'), _bucketCount('all')),
                          ],
                        ),
                        const SizedBox(height: 16),
                        SegmentedButton<String>(
                          segments: [
                            ButtonSegment(value: 'daily', label: Text(t.adminReportDaily)),
                            ButtonSegment(value: 'monthly', label: Text(t.adminReportMonthly)),
                            ButtonSegment(value: 'yearly', label: Text(t.adminReportYearly)),
                          ],
                          selected: {_reportType},
                          onSelectionChanged: (Set<String> newSelection) {
                            setState(() => _reportType = newSelection.first);
                            _loadReports();
                          },
                        ),
                        const SizedBox(height: 16),
                        if (_periods.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 24),
                            child: Center(child: Text(t.adminNoReports)),
                          )
                        else
                          Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: _periods.map((r) {
                              final map = Map<String, dynamic>.from(r as Map);
                              final total = (map['total'] as num?)?.toDouble() ?? 0;
                              final count = (map['count'] as num?)?.toInt() ?? 0;
                              return SizedBox(
                                width: 160,
                                child: Card(
                                  child: Padding(
                                    padding: const EdgeInsets.all(16),
                                    child: Column(
                                      children: [
                                        Text('${map['period'] ?? ''}', style: theme.textTheme.bodyMedium),
                                        const SizedBox(height: 8),
                                        Text(_money(total), style: theme.textTheme.titleLarge?.copyWith(color: theme.colorScheme.primary)),
                                        Text(t.invoicesCount(count), style: theme.textTheme.bodySmall),
                                      ],
                                    ),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        const SizedBox(height: 24),
                        Text(t.adminRecentInvoices, style: theme.textTheme.titleLarge),
                        const SizedBox(height: 8),
                        if (_invoices.isEmpty)
                          Text(t.adminNoInvoices)
                        else
                          ..._invoices.map((raw) {
                            final inv = Map<String, dynamic>.from(raw as Map);
                            final total = (inv['total'] as num?)?.toDouble() ?? 0;
                            final name = (inv['customer_name']?.toString().isNotEmpty == true)
                                ? inv['customer_name'].toString()
                                : t.adminPosWalkIn;
                            final invoiceId = inv['_id']?.toString() ?? inv['id']?.toString() ?? '';
                            return Card(
                              child: InkWell(
                                onTap: () => _openInvoice(inv),
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Expanded(
                                            child: Text(
                                              invoiceId,
                                              style: theme.textTheme.bodySmall,
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ),
                                          Text(_money(total), style: theme.textTheme.titleSmall),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                      Text(name, style: theme.textTheme.titleMedium),
                                      const SizedBox(height: 4),
                                      Text('${t.adminDate}: ${_formatDate(inv['created_at'])}'),
                                      Text('${t.adminWarehouse}: ${_warehouseName(inv)}'),
                                      Align(
                                        alignment: AlignmentDirectional.centerEnd,
                                        child: TextButton(
                                          onPressed: () => _openInvoice(inv),
                                          child: Text(t.adminOpenInvoice),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            );
                          }),
                        if (_invoiceLastPage > 1)
                          Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              IconButton(
                                onPressed: _invoicePage > 1
                                    ? () {
                                        setState(() => _invoicePage--);
                                        _loadReports();
                                      }
                                    : null,
                                icon: const Icon(Icons.chevron_left),
                              ),
                              Text('$_invoicePage / $_invoiceLastPage'),
                              IconButton(
                                onPressed: _invoicePage < _invoiceLastPage
                                    ? () {
                                        setState(() => _invoicePage++);
                                        _loadReports();
                                      }
                                    : null,
                                icon: const Icon(Icons.chevron_right),
                              ),
                            ],
                          ),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _summaryCard(ThemeData theme, AppLocalizations t, String label, double total, int count) {
    return SizedBox(
      width: 160,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Text(label, style: theme.textTheme.bodyMedium),
              const SizedBox(height: 8),
              Text(_money(total), style: theme.textTheme.titleLarge?.copyWith(color: theme.colorScheme.primary)),
              Text(t.invoicesCount(count), style: theme.textTheme.bodySmall),
            ],
          ),
        ),
      ),
    );
  }
}
