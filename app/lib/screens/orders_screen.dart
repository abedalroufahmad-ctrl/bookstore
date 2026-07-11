import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/order.dart';
import '../providers/auth_provider.dart';
import 'order_detail_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  List<Order> _orders = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = true);
    final res = await ApiService.instance.getOrders();
    if (!mounted) return;
    setState(() {
      _loading = false;
      _orders = res.success && res.data != null ? res.data! : [];
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final auth = context.watch<AuthProvider>();
    if (auth.userType != UserType.customer) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        Navigator.pushReplacementNamed(context, '/login');
      });
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: Text(t.ordersTitle)),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    return Scaffold(
      appBar: AppBar(title: Text(t.ordersTitle)),
      body: _orders.isEmpty
          ? Center(child: Text(t.noOrders))
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _orders.length,
                itemBuilder: (context, i) {
                  final o = _orders[i];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      isThreeLine: true,
                      title: SelectableText(
                        '#${o.id}',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      subtitle: Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text(
                          '\$${o.total.toStringAsFixed(2)} • ${o.status}'
                          '${(o.paymentStatus != null && o.paymentStatus!.trim().isNotEmpty) ? '\n${t.paymentStatusLabel}: ${o.paymentStatus}' : ''}',
                          style: Theme.of(context).textTheme.bodySmall,
                          maxLines: 4,
                          softWrap: true,
                        ),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () async {
                        await Navigator.push<void>(
                          context,
                          MaterialPageRoute<void>(
                            builder: (_) => OrderDetailScreen(orderId: o.id),
                          ),
                        );
                        if (mounted) await _load();
                      },
                    ),
                  );
                },
              ),
            ),
    );
  }
}
