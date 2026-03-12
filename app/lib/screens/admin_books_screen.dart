import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../models/book.dart';

class AdminBooksScreen extends StatefulWidget {
  const AdminBooksScreen({super.key});

  @override
  State<AdminBooksScreen> createState() => _AdminBooksScreenState();
}

class _AdminBooksScreenState extends State<AdminBooksScreen> {
  List<Book> _books = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = true);
    final res = await ApiService.instance.adminBooksList();
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

  Future<void> _delete(Book book) async {
    if (!context.mounted) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete book'),
        content: Text('Delete "${book.title}"?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await ApiService.instance.adminBooksDelete(book.id);
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Books'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _books.length,
              itemBuilder: (context, i) {
                final b = _books[i];
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(b.title),
                    subtitle: Text(
                      '\$${b.price.toStringAsFixed(2)} • ${b.stockQuantity} in stock',
                    ),
                    trailing: PopupMenuButton<String>(
                      onSelected: (v) {
                        if (v == 'delete') _delete(b);
                      },
                      itemBuilder: (_) => [
                        const PopupMenuItem(
                          value: 'delete',
                          child: Text('Delete'),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Add book via web admin for full form'),
            ),
          );
        },
        child: const Icon(Icons.add),
      ),
    );
  }
}
