import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';

class AdminBookFormScreen extends StatefulWidget {
  const AdminBookFormScreen({super.key});

  @override
  State<AdminBookFormScreen> createState() => _AdminBookFormScreenState();
}

class _AdminBookFormScreenState extends State<AdminBookFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleCtrl = TextEditingController();
  final _isbnCtrl = TextEditingController();
  final _priceCtrl = TextEditingController();
  final _stockCtrl = TextEditingController(text: '0');
  final _descCtrl = TextEditingController();

  String? _bookId;
  String _condition = 'new';
  bool _isVisible = true;
  bool _isSold = false;
  String? _categoryId;
  String? _publisherId;
  final Set<String> _warehouseIds = {};
  final Set<String> _authorIds = {};

  List<Category> _categories = [];
  List<Author> _authors = [];
  List<Map<String, dynamic>> _warehouses = [];
  List<Map<String, dynamic>> _publishers = [];

  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final args = ModalRoute.of(context)?.settings.arguments;
      if (args is Map && args['bookId'] != null) {
        _bookId = args['bookId'].toString();
      }
      _bootstrap();
    });
  }

  @override
  void dispose() {
    _titleCtrl.dispose();
    _isbnCtrl.dispose();
    _priceCtrl.dispose();
    _stockCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  String _mapId(Map<String, dynamic> m) =>
      (m['_id'] ?? m['id'] ?? '').toString();

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final catsF = ApiService.instance.adminCategoriesList();
    final authorsF = ApiService.instance.adminAuthorsList();
    final warehousesF = ApiService.instance.adminWarehousesList();
    final publishersF = ApiService.instance.adminPublishersList();
    final bookF =
        _bookId != null ? ApiService.instance.adminBooksGet(_bookId!) : null;

    final cats = await catsF;
    final authors = await authorsF;
    final warehouses = await warehousesF;
    final publishers = await publishersF;
    final bookRes = bookF != null ? await bookF : null;

    if (!mounted) return;

    if (cats.success && cats.data != null) {
      _categories = cats.data!;
    }
    if (authors.success && authors.data != null) {
      _authors = authors.data!;
    }
    if (warehouses.success && warehouses.data != null) {
      _warehouses = warehouses.data!;
    }
    if (publishers.success && publishers.data != null) {
      _publishers = publishers.data!;
    }

    if (bookRes != null) {
      if (bookRes.success && bookRes.data != null) {
        final book = bookRes.data!;
        _titleCtrl.text = book.title;
        _isbnCtrl.text = book.isbn ?? '';
        _priceCtrl.text = book.price.toString();
        _stockCtrl.text = book.stockQuantity.toString();
        _descCtrl.text = book.description ?? '';
        _condition = book.condition == 'used' ? 'used' : 'new';
        _isVisible = book.isVisible;
        _isSold = book.isSold;
        _categoryId = book.category?.id;
        _publisherId = book.publisher?.id;
        if (_categoryId != null &&
            !_categories.any((c) => c.id == _categoryId)) {
          _categoryId = null;
        }
        if (_publisherId != null &&
            !_publishers.any((p) => _mapId(p) == _publisherId)) {
          _publisherId = null;
        }
        if (book.warehouse?.id != null && book.warehouse!.id.isNotEmpty) {
          _warehouseIds.add(book.warehouse!.id);
        }
        for (final a in book.authors ?? const <Author>[]) {
          if (a.id.isNotEmpty) _authorIds.add(a.id);
        }
      } else {
        _error = bookRes.message;
      }
    }

    setState(() => _loading = false);
  }

  Future<void> _save() async {
    final t = AppLocalizations.of(context);
    if (!_formKey.currentState!.validate()) return;
    if (_warehouseIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.adminSelectWarehouse)),
      );
      return;
    }
    if (_authorIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.isAr ? 'اختر مؤلفاً' : 'Select at least one author')),
      );
      return;
    }
    if (_categoryId == null || _categoryId!.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.adminSelectCategory)),
      );
      return;
    }

    final price = double.tryParse(_priceCtrl.text.trim().replaceAll(',', '.'));
    final stock = int.tryParse(_stockCtrl.text.trim());
    if (price == null || stock == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.invalidNumber)),
      );
      return;
    }

    final body = <String, dynamic>{
      'title': _titleCtrl.text.trim(),
      'isbn': _isbnCtrl.text.trim(),
      'price': price,
      'stock_quantity': stock,
      'description': _descCtrl.text.trim(),
      'condition': _condition,
      'is_visible': _isVisible,
      'is_sold': _isSold,
      'warehouse_ids': _warehouseIds.toList(),
      'category_id': _categoryId,
      'author_ids': _authorIds.toList(),
      if (_publisherId != null && _publisherId!.isNotEmpty)
        'publisher_id': _publisherId,
    };

    setState(() => _saving = true);
    final res = _bookId == null
        ? await ApiService.instance.adminBooksCreate(body)
        : await ApiService.instance.adminBooksUpdate(_bookId!, body);
    if (!mounted) return;
    setState(() => _saving = false);

    if (res.success) {
      Navigator.pop(context, true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            res.message.isNotEmpty ? res.message : t.adminFailedSave,
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final isEdit = _bookId != null;

    return Scaffold(
      appBar: AppBar(
        title: Text(isEdit ? t.adminEditBook : t.adminAddBook),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _loading
          ? Center(child: Text(t.loading))
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!.isNotEmpty ? _error! : t.error),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _bootstrap, child: Text(t.retry)),
                    ],
                  ),
                )
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      TextFormField(
                        controller: _titleCtrl,
                        decoration: InputDecoration(labelText: t.isAr ? 'العنوان' : 'Title'),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? t.fieldRequired : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _isbnCtrl,
                        decoration: InputDecoration(labelText: t.bookIsbn),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? t.fieldRequired : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _priceCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: InputDecoration(
                          labelText: t.isAr ? 'السعر' : 'Price',
                        ),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? t.fieldRequired : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _stockCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: t.isAr ? 'الكمية' : 'Stock quantity',
                        ),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? t.fieldRequired : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _descCtrl,
                        maxLines: 3,
                        decoration: InputDecoration(labelText: t.bookDescription),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        value: _condition,
                        decoration: InputDecoration(labelText: t.adminCondition),
                        items: [
                          DropdownMenuItem(
                            value: 'new',
                            child: Text(t.adminNewCondition),
                          ),
                          DropdownMenuItem(
                            value: 'used',
                            child: Text(t.adminUsedCondition),
                          ),
                        ],
                        onChanged: (v) {
                          if (v != null) setState(() => _condition = v);
                        },
                      ),
                      SwitchListTile(
                        title: Text(t.adminVisible),
                        value: _isVisible,
                        onChanged: (v) => setState(() => _isVisible = v),
                      ),
                      SwitchListTile(
                        title: Text(t.adminSold),
                        value: _isSold,
                        onChanged: (v) => setState(() => _isSold = v),
                      ),
                      const SizedBox(height: 8),
                      Text(t.adminSelectWarehouse,
                          style: Theme.of(context).textTheme.titleSmall),
                      ..._warehouses.map((w) {
                        final id = _mapId(w);
                        final name = w['name']?.toString() ?? id;
                        return CheckboxListTile(
                          value: _warehouseIds.contains(id),
                          title: Text(name),
                          controlAffinity: ListTileControlAffinity.leading,
                          onChanged: (checked) {
                            setState(() {
                              if (checked == true) {
                                _warehouseIds.add(id);
                              } else {
                                _warehouseIds.remove(id);
                              }
                            });
                          },
                        );
                      }),
                      const SizedBox(height: 8),
                      DropdownButtonFormField<String?>(
                        value: _categoryId,
                        decoration: InputDecoration(labelText: t.adminSelectCategory),
                        items: [
                          DropdownMenuItem<String?>(
                            value: null,
                            child: Text(t.adminSelectCategory),
                          ),
                          ..._categories.map(
                            (c) => DropdownMenuItem(
                              value: c.id,
                              child: Text(
                                c.subjectTitleEn ??
                                    c.subjectTitleAr ??
                                    c.deweyCode ??
                                    c.id,
                              ),
                            ),
                          ),
                        ],
                        onChanged: (v) => setState(() => _categoryId = v),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String?>(
                        value: _publisherId,
                        decoration: InputDecoration(labelText: t.adminSelectPublisher),
                        items: [
                          DropdownMenuItem<String?>(
                            value: null,
                            child: Text(t.adminSelectPublisher),
                          ),
                          ..._publishers.map((p) {
                            final id = _mapId(p);
                            return DropdownMenuItem(
                              value: id,
                              child: Text(p['name']?.toString() ?? id),
                            );
                          }),
                        ],
                        onChanged: (v) => setState(() => _publisherId = v),
                      ),
                      const SizedBox(height: 12),
                      Text(t.bookAuthors,
                          style: Theme.of(context).textTheme.titleSmall),
                      ..._authors.map((a) {
                        return CheckboxListTile(
                          value: _authorIds.contains(a.id),
                          title: Text(a.name ?? a.id),
                          controlAffinity: ListTileControlAffinity.leading,
                          onChanged: (checked) {
                            setState(() {
                              if (checked == true) {
                                _authorIds.add(a.id);
                              } else {
                                _authorIds.remove(a.id);
                              }
                            });
                          },
                        );
                      }),
                      const SizedBox(height: 20),
                      FilledButton(
                        onPressed: _saving ? null : _save,
                        child: _saving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : Text(t.adminSave),
                      ),
                    ],
                  ),
                ),
    );
  }
}
