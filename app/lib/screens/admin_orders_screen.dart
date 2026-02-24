import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../models/order.dart';
import '../models/user.dart';

const _statuses = [
  'pending_review',
  'confirmed',
  'preparing',
  'shipped',
  'delivered',
  'cancelled',
];

class AdminOrdersScreen extends StatefulWidget {
  const AdminOrdersScreen({super.key});

  @override
  State<AdminOrdersScreen> createState() => _AdminOrdersScreenState();
}

class _AdminOrdersScreenState extends State<AdminOrdersScreen> {
  List<Order> _orders = [];
  List<Employee> _employees = [];
  bool _loading = true;
  String? _statusFilter;

  @override
  void initState() {
    super.initState();
    _load();
    _loadEmployees();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await ApiService.instance.adminOrdersList(status: _statusFilter);
    setState(() => _loading = false);
    if (res.success && res.data != null) {
      setState(() => _orders = res.data!);
    }
  }

  Future<void> _loadEmployees() async {
    final res = await ApiService.instance.adminEmployeesList();
    if (res.success && res.data != null) {
      setState(() => _employees = res.data!);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Orders'),
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
                const Text('Status: '),
                DropdownButton<String?>(
                  value: _statusFilter,
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All')),
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
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: _orders.length,
                    itemBuilder: (context, i) {
                      final o = _orders[i];
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: InkWell(
                          onTap: () => _showOrderDetail(context, o),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      'Order #${o.id.length > 8 ? o.id.substring(o.id.length - 8) : o.id}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    Text(
                                      o.status.replaceAll('_', ' '),
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
                                  'Customer: ${o.customer?.name ?? o.id}',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                                Text(
                                  'Total: \$${o.total.toStringAsFixed(2)}',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                                if (o.employee != null)
                                  Text(
                                    'Assigned: ${o.employee!.name}',
                                    style: Theme.of(context).textTheme.bodySmall,
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

  Future<void> _showOrderDetail(BuildContext context, Order order) async {
    final ord = await ApiService.instance.adminOrdersGet(order.id);
    if (!context.mounted || ord.data == null) return;
    final o = ord.data!;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.6,
        maxChildSize: 0.9,
        expand: false,
        builder: (_, controller) => Padding(
          padding: const EdgeInsets.all(16),
          child: ListView(
            controller: controller,
            children: [
              Text(
                'Order #${o.id.length > 8 ? o.id.substring(o.id.length - 8) : o.id}',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              Text('Customer: ${o.customer?.name ?? '-'}'),
              Text('Total: \$${o.total.toStringAsFixed(2)}'),
              Text('Status: ${o.status}'),
              const SizedBox(height: 16),
              const Text('Shipping address:'),
              Text(
                o.shippingAddress != null
                    ? [
                        o.shippingAddress!['address'],
                        o.shippingAddress!['city'],
                        o.shippingAddress!['country'],
                      ].whereType<String>().join(', ')
                    : '-',
              ),
              const SizedBox(height: 16),
              const Text('Update status:'),
              Wrap(
                spacing: 8,
                children: _statuses.map((s) {
                  return ChoiceChip(
                    label: Text(s.replaceAll('_', ' ')),
                    selected: o.status == s,
                    onSelected: (sel) {
                      if (sel) {
                        ApiService.instance.adminOrdersUpdateStatus(o.id, s);
                        Navigator.pop(ctx);
                        _load();
                      }
                    },
                  );
                }).toList(),
              ),
              const SizedBox(height: 16),
              const Text('Assign to:'),
              DropdownButton<String>(
                value: o.employee?.id ?? '',
                items: [
                  const DropdownMenuItem(value: '', child: Text('Unassigned')),
                  ..._employees.map(
                    (e) => DropdownMenuItem(
                      value: e.id,
                      child: Text('${e.name} (${e.role ?? ''})'),
                    ),
                  ),
                ],
                onChanged: (v) {
                  if (v != null && v.isNotEmpty) {
                    ApiService.instance.adminOrdersAssign(o.id, v);
                    Navigator.pop(ctx);
                    _load();
                  }
                },
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
