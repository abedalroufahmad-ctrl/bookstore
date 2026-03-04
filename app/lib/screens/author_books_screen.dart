import 'package:flutter/material.dart';
import '../api/api_service.dart';
import '../models/book.dart';
import '../widgets/book_card.dart';

class AuthorBooksScreen extends StatefulWidget {
  final String authorId;
  final String? authorName;

  const AuthorBooksScreen({
    super.key,
    required this.authorId,
    this.authorName,
  });

  @override
  State<AuthorBooksScreen> createState() => _AuthorBooksScreenState();
}

class _AuthorBooksScreenState extends State<AuthorBooksScreen> {
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
    final res = await ApiService.instance.getBooks(params: {'author_id': widget.authorId});
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
      appBar: AppBar(title: Text(widget.authorName ?? 'كتب المؤلف')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : _books.isEmpty
                  ? const Center(child: Text('لا توجد كتب لهذا المؤلف'))
                  : GridView.builder(
                      padding: const EdgeInsets.all(16),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: 16,
                        crossAxisSpacing: 16,
                        childAspectRatio: 0.6,
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
