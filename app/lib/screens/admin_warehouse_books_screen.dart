import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../models/book.dart';

class AdminWarehouseBooksScreen extends StatefulWidget {
  const AdminWarehouseBooksScreen({
    super.key,
    required this.warehouseId,
    this.warehouseName,
  });

  final String warehouseId;
  final String? warehouseName;

  @override
  State<AdminWarehouseBooksScreen> createState() => _AdminWarehouseBooksScreenState();
}

class _AdminWarehouseBooksScreenState extends State<AdminWarehouseBooksScreen> {
  List<Book> _books = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await ApiService.instance.adminBooksList(
      params: {'warehouse_id': widget.warehouseId, 'per_page': '100'},
    );
    if (!mounted) return;
    List<Book> list = [];
    if (res.success && res.data != null) {
      final d = res.data;
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Book.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Book.fromJson(e as Map<String, dynamic>)).toList();
      }
    }
    setState(() {
      _loading = false;
      _books = list;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.warehouseName ?? 'Warehouse books'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _books.isEmpty
              ? const Center(child: Text('No books in this warehouse'))
              : ListView.builder(
                  itemCount: _books.length,
                  itemBuilder: (context, i) {
                    final b = _books[i];
                    return ListTile(
                      title: Text(b.title),
                      subtitle: Text(b.isbn ?? ''),
                      trailing: Text('\$${b.price.toStringAsFixed(2)}'),
                    );
                  },
                ),
    );
  }
}
