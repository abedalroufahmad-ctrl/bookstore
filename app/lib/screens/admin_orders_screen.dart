import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/order.dart';
import '../models/user.dart';

const _statuses = [
  'pending_warehouse_review',
  'awaiting_customer_confirmation',
  'resubmitted_to_warehouse',
  'processing_fulfillment',
  'shipped_collecting_payment',
  'completed',
  'cancelled',
  'pending_review',
  'confirmed',
  'preparing',
  'shipped',
  'delivered',
];

bool _needsWarehouseQuote(String status) =>
    status == 'pending_warehouse_review' || status == 'pending_review';

/// Admin JWT: [useEmployeeApi] is false (default). Employee JWT: pass true for staff / warehouse app.
class AdminOrdersScreen extends StatefulWidget {
  const AdminOrdersScreen({super.key, this.useEmployeeApi = false});

  /// When true, uses `/employees/orders` (assign UI hidden).
  final bool useEmployeeApi;

  @override
  State<AdminOrdersScreen> createState() => _AdminOrdersScreenState();
}

class _AdminOrdersScreenState extends State<AdminOrdersScreen> {
  List<Order> _orders = [];
  List<Employee> _employees = [];
  List<Map<String, dynamic>> _warehouses = [];
  final Set<String> _selectedIds = {};
  bool _loading = true;
  String? _statusFilter;

  @override
  void initState() {
    super.initState();
    _load();
    if (!widget.useEmployeeApi) {
      _loadEmployees();
    }
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = true);
    final res = widget.useEmployeeApi
        ? await ApiService.instance.employeeOrdersList(status: _statusFilter)
        : await ApiService.instance.adminOrdersList(status: _statusFilter);
    if (!widget.useEmployeeApi) {
      final wRes = await ApiService.instance.adminWarehousesList();
      if (mounted && wRes.success && wRes.data != null) {
        setState(() {
          _warehouses = List<Map<String, dynamic>>.from(wRes.data!);
        });
      }
    }
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _orders = res.data!;
      }
    });
  }

  Future<void> _loadEmployees() async {
    final res = await ApiService.instance.adminEmployeesList();
    if (!mounted) return;
    if (res.success && res.data != null) {
      setState(() => _employees = res.data!);
    }
  }

  Future<Order?> _fetchOrderDetail(String id) async {
    final res = widget.useEmployeeApi
        ? await ApiService.instance.employeeOrdersGet(id)
        : await ApiService.instance.adminOrdersGet(id);
    return res.success ? res.data : null;
  }

  Future<void> _handleBulkDelete() async {
    final t = AppLocalizations.of(context);
    if (_selectedIds.isEmpty) return;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(t.isAr ? 'حذف الطلبات' : 'Delete Orders'),
        content: Text(t.isAr ? 'هل أنت متأكد أنك تريد حذف هذه الطلبات؟' : 'Are you sure you want to delete these orders?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(t.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: Text(t.delete),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    setState(() => _loading = true);
    final res = await ApiService.instance.adminOrdersBulkDelete(_selectedIds.toList());
    if (mounted) {
      if (res.success) {
        setState(() => _selectedIds.clear());
        _load();
      } else {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final title = widget.useEmployeeApi ? t.staffOrdersTitle : 'Orders';

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Text('${t.isAr ? 'الحالة' : 'Status'}: '),
                const SizedBox(width: 8),
                Expanded(
                  child: DropdownButton<String?>(
                    isExpanded: true,
                    value: _statusFilter,
                    items: [
                      DropdownMenuItem<String?>(
                        value: null,
                        child: Text(t.isAr ? 'الكل' : 'All'),
                      ),
                      ..._statuses.map(
                        (s) => DropdownMenuItem(
                          value: s,
                          child: Text(s.replaceAll('_', ' ')),
                        ),
                      ),
                    ],
                    onChanged: (v) {
                      setState(() {
                        _statusFilter = v;
                        _loading = true;
                      });
                      _load();
                    },
                  ),
                ),
              ],
            ),
          ),
          if (!widget.useEmployeeApi && _selectedIds.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: [
                  Text(t.isAr ? '${_selectedIds.length} محدد' : '${_selectedIds.length} selected'),
                  const Spacer(),
                  TextButton.icon(
                    onPressed: _handleBulkDelete,
                    icon: const Icon(Icons.delete, color: Colors.red),
                    label: Text(t.delete, style: const TextStyle(color: Colors.red)),
                  ),
                ],
              ),
            ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _orders.isEmpty
                    ? Center(child: Text(t.noOrders))
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: _orders.length,
                        itemBuilder: (context, i) {
                          final o = _orders[i];
                          return Card(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: InkWell(
                              onTap: () => _openOrderDetail(context, o),
                              onLongPress: !widget.useEmployeeApi ? () {
                                setState(() {
                                  if (_selectedIds.contains(o.id)) {
                                    _selectedIds.remove(o.id);
                                  } else {
                                    _selectedIds.add(o.id);
                                  }
                                });
                              } : null,
                              child: Container(
                                color: _selectedIds.contains(o.id) ? Colors.amber.withValues(alpha: 0.1) : null,
                                padding: const EdgeInsets.all(16),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        Row(
                                          children: [
                                            if (!widget.useEmployeeApi)
                                              Checkbox(
                                                value: _selectedIds.contains(o.id),
                                                onChanged: (val) {
                                                  setState(() {
                                                    if (val == true) {
                                                      _selectedIds.add(o.id);
                                                    } else {
                                                      _selectedIds.remove(o.id);
                                                    }
                                                  });
                                                },
                                              ),
                                            Text(
                                              t.orderNumber(o.id.length > 8 ? o.id.substring(o.id.length - 8) : o.id),
                                              style: const TextStyle(
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ],
                                        ),
                                        Text(
                                          t.orderStatus(o.status),
                                          style: TextStyle(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .primary,
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      '${t.customerLabel}: ${o.customer?.name ?? o.customer?.email ?? '-'}',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall,
                                    ),
                                    if (o.warehouseId != null)
                                      Text(
                                        '${t.isAr ? 'المستودع' : 'Warehouse'}: ${_warehouses.cast<Map<String, dynamic>>().firstWhere((w) => w['_id'] == o.warehouseId, orElse: () => <String, dynamic>{})['name'] ?? o.warehouseId}',
                                        style: Theme.of(context).textTheme.bodySmall,
                                      ),
                                    Text(
                                      '${t.totalLabel}: \$${o.total.toStringAsFixed(2)}',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall,
                                    ),
                                    if (o.paymentStatus != null &&
                                        o.paymentStatus!.isNotEmpty)
                                      Text(
                                        '${t.paymentStatusLabel}: ${o.paymentStatus}',
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall,
                                      ),
                                    if (!widget.useEmployeeApi &&
                                        o.employee != null)
                                      Text(
                                        t.assignedTo(o.employee?.name ?? ''),
                                        style: Theme.of(context)
                                            .textTheme
                                            .bodySmall,
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
          ),
        ],
      ),
    );
  }

  Future<void> _openOrderDetail(BuildContext context, Order order) async {
    final t = AppLocalizations.of(context);
    final refreshed = await _fetchOrderDetail(order.id);
    if (!context.mounted) return;
    if (refreshed == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.error)),
      );
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(ctx).viewInsets.bottom,
        ),
        child: _OrderDetailSheet(
          order: refreshed,
          employees: _employees,
          useEmployeeApi: widget.useEmployeeApi,
          onUpdated: () {
            _load();
          },
        ),
      ),
    );
  }
}

class _OrderDetailSheet extends StatefulWidget {
  const _OrderDetailSheet({
    required this.order,
    required this.employees,
    required this.useEmployeeApi,
    required this.onUpdated,
  });

  final Order order;
  final List<Employee> employees;
  final bool useEmployeeApi;
  final VoidCallback onUpdated;

  @override
  State<_OrderDetailSheet> createState() => _OrderDetailSheetState();
}

class _OrderDetailSheetState extends State<_OrderDetailSheet> {
  late Order _order;
  late TextEditingController _shippingFeeController;
  late TextEditingController _shippingMethodController;
  late TextEditingController _paymentMethodController;
  String _assignEmployeeId = '';
  bool _submittingQuote = false;
  bool _updatingStatus = false;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
    _assignEmployeeId = _order.employee?.id ?? '';
    _syncQuoteControllersFromOrder();
  }

  void _syncQuoteControllersFromOrder() {
    final fee = _order.shippingFee ?? 0;
    _shippingFeeController = TextEditingController(
      text: fee == 0 ? '' : fee.toString(),
    );
    _shippingMethodController = TextEditingController(
      text: _order.shippingMethod ?? '',
    );
    _paymentMethodController = TextEditingController(
      text: _order.paymentMethod ?? '',
    );
  }

  @override
  void dispose() {
    _shippingFeeController.dispose();
    _shippingMethodController.dispose();
    _paymentMethodController.dispose();
    super.dispose();
  }

  Future<void> _submitWarehouseQuote() async {
    final t = AppLocalizations.of(context);
    final raw = _shippingFeeController.text.trim().replaceAll(',', '.');
    final fee = double.tryParse(raw);
    if (fee == null || fee < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.invalidShippingFee)),
      );
      return;
    }

    setState(() => _submittingQuote = true);
    final api = ApiService.instance;
    final res = widget.useEmployeeApi
        ? await api.employeeOrdersSubmitWarehouseQuote(
            _order.id,
            shippingFee: fee,
            shippingMethod: _shippingMethodController.text,
            paymentMethod: _paymentMethodController.text,
          )
        : await api.adminOrdersSubmitWarehouseQuote(
            _order.id,
            shippingFee: fee,
            shippingMethod: _shippingMethodController.text,
            paymentMethod: _paymentMethodController.text,
          );
    if (!mounted) return;
    setState(() => _submittingQuote = false);

    if (res.success && res.data != null) {
      widget.onUpdated();
      if (context.mounted) Navigator.pop(context);
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(res.message.isNotEmpty ? res.message : t.error)),
    );
  }

  Future<void> _changeStatus(String status) async {
    if (_updatingStatus || status == _order.status) return;
    setState(() => _updatingStatus = true);
    final api = ApiService.instance;
    final res = widget.useEmployeeApi
        ? await api.employeeOrdersUpdateStatus(_order.id, status)
        : await api.adminOrdersUpdateStatus(_order.id, status);
    if (!mounted) return;
    setState(() => _updatingStatus = false);

    final t = AppLocalizations.of(context);
    if (res.success && res.data != null) {
      setState(() {
        _order = res.data!;
        _assignEmployeeId = _order.employee?.id ?? '';
        final fee = _order.shippingFee ?? 0;
        _shippingFeeController.text =
            fee == 0 ? '' : fee.toString();
        _shippingMethodController.text = _order.shippingMethod ?? '';
        _paymentMethodController.text = _order.paymentMethod ?? '';
      });
      widget.onUpdated();
      if (context.mounted) Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message.isNotEmpty ? res.message : t.error)),
      );
    }
  }

  Future<void> _assign(String employeeId) async {
    if (widget.useEmployeeApi || employeeId.isEmpty) return;
    final t = AppLocalizations.of(context);
    final res = await ApiService.instance.adminOrdersAssign(
      _order.id,
      employeeId,
    );
    if (!mounted) return;
    if (res.success && res.data != null) {
      setState(() => _order = res.data!);
      widget.onUpdated();
      if (context.mounted) Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message.isNotEmpty ? res.message : t.error)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final o = _order;
    final needsQuote = _needsWarehouseQuote(o.status);

    return DraggableScrollableSheet(
      initialChildSize: 0.65,
      maxChildSize: 0.92,
      expand: false,
      builder: (_, scrollController) {
        return SingleChildScrollView(
          controller: scrollController,
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Order #${o.id.length > 8 ? o.id.substring(o.id.length - 8) : o.id}',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              Text('${t.customerLabel}: ${o.customer?.name ?? o.customer?.email ?? '-'}'),
              if (o.warehouseId != null)
                Text('${t.isAr ? 'المستودع' : 'Warehouse'}: ${o.warehouseId}'),
              Text('${t.paymentStatusLabel}: ${o.paymentStatus ?? '—'}'),
              if (o.paymentMethod != null && o.paymentMethod!.isNotEmpty)
                Text('${t.paymentMethodLabel}: ${o.paymentMethod}'),
              Text('${t.totalLabel}: \$${o.total.toStringAsFixed(2)}'),
              if (o.booksSubtotal != null)
                Text(
                  '${t.booksSubtotalLabel}: \$${o.booksSubtotal!.toStringAsFixed(2)} + ${t.shippingFeeLabel}: \$${(o.shippingFee ?? 0).toStringAsFixed(2)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              const SizedBox(height: 8),
              Text('${t.statusLabel}: ${t.orderStatus(o.status)}'),
              const SizedBox(height: 12),
              Text(t.shippingAddress, style: Theme.of(context).textTheme.titleSmall),
              const SizedBox(height: 4),
              Text(
                o.shippingAddress != null
                    ? [
                        o.shippingAddress!['address'],
                        o.shippingAddress!['city'],
                        o.shippingAddress!['country'],
                        o.shippingAddress!['postal_code'],
                      ].whereType<String>().where((s) => s.isNotEmpty).join(', ')
                    : '—',
              ),
              const SizedBox(height: 16),
              Text(
                t.updateStatus,
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 8),
              if (_updatingStatus)
                const LinearProgressIndicator()
              else
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: _statuses.map((s) {
                    return FilterChip(
                      label: Text(
                        t.orderStatus(s),
                        style: const TextStyle(fontSize: 12),
                      ),
                      selected: o.status == s,
                      onSelected: (_) => _changeStatus(s),
                    );
                  }).toList(),
                ),
              if (needsQuote) ...[
                const SizedBox(height: 20),
                Material(
                  color: Theme.of(context).colorScheme.primaryContainer.withValues(alpha: 0.35),
                  borderRadius: BorderRadius.circular(12),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          t.warehouseQuote,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          t.warehouseQuoteHint,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _shippingFeeController,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: InputDecoration(
                            labelText: t.shippingFeeLabel,
                          ),
                        ),
                        const SizedBox(height: 10),
                        TextField(
                          controller: _shippingMethodController,
                          decoration: InputDecoration(
                            labelText: t.shippingMethodLabel,
                            hintText: t.shippingMethodHint,
                          ),
                        ),
                        const SizedBox(height: 10),
                        TextField(
                          controller: _paymentMethodController,
                          decoration: InputDecoration(
                            labelText: t.paymentMethodLabel,
                            hintText: t.paymentMethodHint,
                          ),
                        ),
                        const SizedBox(height: 14),
                        FilledButton(
                          onPressed:
                              _submittingQuote ? null : _submitWarehouseQuote,
                          child: _submittingQuote
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : Text(t.saveQuote),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
              if (!widget.useEmployeeApi) ...[
                const SizedBox(height: 20),
                Text(
                  t.assignTo,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            t.employeeLabel,
                            style: Theme.of(context).textTheme.labelMedium,
                          ),
                          const SizedBox(height: 4),
                          DropdownButton<String?>(
                            isExpanded: true,
                            value: _assignEmployeeId.isEmpty
                                ? null
                                : _assignEmployeeId,
                            hint: Text(t.unassigned),
                            items: [
                              DropdownMenuItem<String?>(
                                value: null,
                                child: Text(t.unassigned),
                              ),
                              ...widget.employees.map(
                                (e) => DropdownMenuItem(
                                  value: e.id,
                                  child: Text('${e.name} (${e.role ?? ''})'),
                                ),
                              ),
                            ],
                            onChanged: (v) =>
                                setState(() => _assignEmployeeId = v ?? ''),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    FilledButton.tonal(
                      onPressed: _assignEmployeeId.isNotEmpty
                          ? () => _assign(_assignEmployeeId)
                          : null,
                      child: Text(t.assign),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 16),
              Text(t.itemsLabel,
                  style: Theme.of(context).textTheme.titleSmall),
              const SizedBox(height: 8),
              Table(
                columnWidths: const {
                  0: FlexColumnWidth(2),
                  1: FlexColumnWidth(1),
                },
                border: TableBorder.all(
                  color: Theme.of(context).colorScheme.outline.withValues(alpha: 0.35),
                ),
                children: [
                  TableRow(
                    decoration: BoxDecoration(
                      color: Theme.of(context)
                          .colorScheme
                          .surfaceContainerHighest
                          .withValues(alpha: 0.6),
                    ),
                    children: [
                      Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 8,
                        ),
                        child: Text(
                          t.ordersItemTitleCol,
                          style: Theme.of(context).textTheme.labelLarge,
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 8,
                        ),
                        child: Text(
                          t.ordersItemPriceCol,
                          textAlign: TextAlign.end,
                          style: Theme.of(context).textTheme.labelLarge,
                        ),
                      ),
                    ],
                  ),
                  ...o.items.map((item) {
                    final shortId = item.bookId.length > 8
                        ? item.bookId.substring(item.bookId.length - 8)
                        : item.bookId;
                    final title = (item.bookTitle != null &&
                            item.bookTitle!.trim().isNotEmpty)
                        ? item.bookTitle!.trim()
                        : 'Book …$shortId';
                    final linePrice =
                        '${item.quantity} × \$${item.price.toStringAsFixed(2)}';
                    return TableRow(
                      children: [
                        Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 8,
                          ),
                          child: Text(title),
                        ),
                        Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 8,
                          ),
                          child: Text(
                            linePrice,
                            textAlign: TextAlign.end,
                          ),
                        ),
                      ],
                    );
                  }),
                ],
              ),
              const SizedBox(height: 24),
              OutlinedButton(
                onPressed: () => Navigator.pop(context),
                child: Text(t.isAr ? 'إغلاق' : 'Close'),
              ),
              SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
            ],
          ),
        );
      },
    );
  }
}
