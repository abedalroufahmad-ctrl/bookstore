import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../models/book.dart';

class BookListScreen extends StatefulWidget {
  const BookListScreen({super.key});

  @override
  State<BookListScreen> createState() => _BookListScreenState();
}

class _BookListScreenState extends State<BookListScreen> {
  List<Book> _books = [];
  bool _loading = true;
  String? _error;

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
    final res = await ApiService.instance.getBooks();
    setState(() => _loading = false);
    if (res.success && res.data != null) {
      final d = res.data;
      List<Book> list = [];
      if (d is Map && d['data'] != null) {
        list = (d['data'] as List)
            .map((e) => Book.fromJson(e as Map<String, dynamic>))
            .toList();
      } else if (d is List) {
        list = d.map((e) => Book.fromJson(e as Map<String, dynamic>)).toList();
      }
      setState(() => _books = list);
    } else {
      setState(() => _error = res.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    if (_error != null) {
      return Scaffold(
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _load,
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(
        title: const Text('Browse Books'),
        actions: [
          IconButton(
            icon: const Icon(Icons.shopping_cart),
            onPressed: () => Navigator.pushNamed(context, '/cart'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _books.isEmpty
            ? const Center(child: Text('No books found'))
            : ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _books.length,
                itemBuilder: (context, i) {
                  final b = _books[i];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(b.title),
                      subtitle: Text(
                        '\$${b.price.toStringAsFixed(2)}'
                        '${b.stockQuantity >= 0 ? " • ${b.stockQuantity} in stock" : ""}',
                      ),
                      trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                      onTap: () => Navigator.pushNamed(
                        context,
                        '/book/${b.id}',
                        arguments: b,
                      ),
                    ),
                  );
                },
              ),
      ),
    );
  }
}
