import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';
import '../widgets/book_card.dart';

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
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(t.booksTitle)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        _error!,
                        style: TextStyle(color: Theme.of(context).colorScheme.error),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: _load,
                        child: Text(t.retry),
                      ),
                    ],
                  ),
                )
              : _books.isEmpty
                  ? Center(child: Text(t.noBooks))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: GridView.builder(
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
                    ),
    );
  }
}
