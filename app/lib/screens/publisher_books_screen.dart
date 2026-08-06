import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';
import '../widgets/book_card.dart';

class PublisherBooksScreen extends StatefulWidget {
  const PublisherBooksScreen({
    super.key,
    required this.publisherId,
    this.publisherName,
  });

  final String publisherId;
  final String? publisherName;

  @override
  State<PublisherBooksScreen> createState() => _PublisherBooksScreenState();
}

class _PublisherBooksScreenState extends State<PublisherBooksScreen> {
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
    final res = await ApiService.instance.getBooks(
      params: {'publisher_id': widget.publisherId},
    );
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
    final t = AppLocalizations.of(context);
    final title = widget.publisherName ??
        (t.isAr ? 'كتب الناشر' : 'Publisher books');
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error ?? ''))
              : _books.isEmpty
                  ? Center(
                      child: Text(
                        t.isAr ? 'لا توجد كتب لهذا الناشر' : 'No books for this publisher',
                      ),
                    )
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
