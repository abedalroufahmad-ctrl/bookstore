import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import 'admin_warehouse_books_screen.dart';

/// Lists warehouses (admin API); tap opens books for that warehouse.
class AdminWarehousesBrowseScreen extends StatefulWidget {
  const AdminWarehousesBrowseScreen({super.key});

  @override
  State<AdminWarehousesBrowseScreen> createState() => _AdminWarehousesBrowseScreenState();
}

class _AdminWarehousesBrowseScreenState extends State<AdminWarehousesBrowseScreen> {
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await ApiService.instance.adminWarehousesList();
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _rows = res.data!;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminBooksByWarehouse),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _rows.length,
              itemBuilder: (context, i) {
                final w = _rows[i];
                final id = (w['_id'] ?? w['id'] ?? '').toString();
                final name = w['name']?.toString() ?? '';
                final city = w['city']?.toString();
                final country = w['country']?.toString();
                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: ListTile(
                    leading: const Text('🏭', style: TextStyle(fontSize: 28)),
                    title: Text(name),
                    subtitle: Text(
                      [city, country].whereType<String>().where((e) => e.isNotEmpty).join(', '),
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => AdminWarehouseBooksScreen(
                            warehouseId: id,
                            warehouseName: name,
                          ),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
    );
  }
}
