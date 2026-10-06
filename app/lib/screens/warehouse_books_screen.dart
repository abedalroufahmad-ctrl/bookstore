import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';
import '../utils/book_grid.dart';
import '../widgets/book_card.dart';

class WarehouseBooksScreen extends StatefulWidget {
  const WarehouseBooksScreen({
    super.key,
    required this.warehouseId,
    this.warehouseName,
  });

  final String warehouseId;
  final String? warehouseName;

  @override
  State<WarehouseBooksScreen> createState() => _WarehouseBooksScreenState();
}

class _WarehouseBooksScreenState extends State<WarehouseBooksScreen> {
  List<Book> _books = [];
  bool _loading = true;
  String? _error;
  int _perRow = 5;

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
    final settingsRes = await ApiService.instance.getSettings();
    final layout = catalogLayoutFromSettings(settingsRes.data, 'books');
    _perRow = layout.perRow;
    final res = await ApiService.instance.getBooks(
      params: {'warehouse_id': widget.warehouseId, 'per_page': '${layout.perPage}'},
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
    final title = widget.warehouseName ?? t.warehouseBooksTitle;
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error ?? ''))
              : _books.isEmpty
                  ? Center(child: Text(t.noBooksInWarehouse))
                  : GridView.builder(
                      padding: const EdgeInsets.all(12),
                      gridDelegate: catalogGridDelegate(
                        width: MediaQuery.sizeOf(context).width,
                        perRow: _perRow,
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
