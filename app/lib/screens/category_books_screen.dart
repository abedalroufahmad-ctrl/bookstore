import 'package:flutter/material.dart';
import '../api/api_service.dart';
import '../models/book.dart';
import '../widgets/book_card.dart';

class CategoryBooksScreen extends StatefulWidget {
  final String categoryId;
  final String? categoryTitle;

  const CategoryBooksScreen({
    super.key,
    required this.categoryId,
    this.categoryTitle,
  });

  @override
  State<CategoryBooksScreen> createState() => _CategoryBooksScreenState();
}

class _CategoryBooksScreenState extends State<CategoryBooksScreen> {
  List<Book> _books = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await ApiService.instance.getBooks(params: {'category_id': widget.categoryId});
    if (!mounted) return;
    setState(() {
      _loading = false;
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
        _books = list;
      } else {
        _error = res.message;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.categoryTitle ?? 'كتب التصنيف')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error ?? ''))
              : _books.isEmpty
                  ? const Center(child: Text('لا توجد كتب في هذا التصنيف'))
                  : GridView.builder(
                      padding: const EdgeInsets.all(12),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 8,
                        crossAxisSpacing: 8,
                        childAspectRatio: 0.64,
                      ),
                      itemCount: _books.length,
                      itemBuilder: (context, i) {
                        return BookCard(
                          book: _books[i],
                          onTap: () => Navigator.pushNamed(
                            context,
                            '/book/${_books[i].id}',
                            arguments: _books[i],
                          ),
                        );
                      },
                    ),
    );
  }
}
