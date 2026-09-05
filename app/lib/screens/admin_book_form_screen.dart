import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

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
  final _pagesCtrl = TextEditingController();
  final _yearCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _picker = ImagePicker();

  String? _bookId;
  String _condition = 'new';
  bool _isVisible = true;
  bool _isSold = false;
  String? _categoryId;
  String? _publisherId;
  String? _coverImage;
  String? _coverImageThumb;
  final Set<String> _publisherIds = {};
  final Set<String> _warehouseIds = {};
  final Set<String> _authorIds = {};

  List<Category> _categories = [];
  List<Author> _authors = [];
  List<Map<String, dynamic>> _warehouses = [];
  List<Map<String, dynamic>> _publishers = [];

  bool _loading = true;
  bool _saving = false;
  bool _coverBusy = false;
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
    _pagesCtrl.dispose();
    _yearCtrl.dispose();
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
        _pagesCtrl.text = book.pages?.toString() ?? '';
        _yearCtrl.text = book.publishYear?.toString() ?? '';
        _descCtrl.text = book.description ?? '';
        _condition = book.condition == 'used' ? 'used' : 'new';
        _isVisible = book.isVisible;
        _isSold = book.isSold;
        _categoryId = book.category?.id;
        _publisherId = book.publisher?.id;
        _coverImage = book.coverImage;
        _coverImageThumb = book.coverImageThumb;
        _publisherIds
          ..clear()
          ..addAll(book.publisherIds ?? const <String>[]);
        if (_publisherIds.isEmpty && _publisherId != null && _publisherId!.isNotEmpty) {
          _publisherIds.add(_publisherId!);
        }
        for (final p in book.publishers ?? const <Publisher>[]) {
          if (p.id.isNotEmpty) _publisherIds.add(p.id);
        }
        if (_publisherIds.isNotEmpty) {
          _publisherId = _publisherIds.first;
        }
        if (_categoryId != null &&
            !_categories.any((c) => c.id == _categoryId)) {
          _categoryId = null;
        }
        _publisherIds.removeWhere(
          (id) => !_publishers.any((p) => _mapId(p) == id),
        );
        _publisherId = _publisherIds.isEmpty ? null : _publisherIds.first;
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

  Future<void> _pickCover(ImageSource source) async {
    final t = AppLocalizations.of(context);
    try {
      final picked = await _picker.pickImage(
        source: source,
        imageQuality: 95,
        maxWidth: 3000,
      );
      if (picked == null) return;
      setState(() => _coverBusy = true);
      final res = await ApiService.instance.adminAnalyzeCover(
        picked.path,
        filename: picked.name,
      );
      if (!mounted) return;
      if (!res.success || res.data == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              res.message.isNotEmpty ? res.message : t.adminFailedAnalyzeCover,
            ),
          ),
        );
        setState(() => _coverBusy = false);
        return;
      }

      final filled = await _applyCoverResult(res.data!);
      if (!mounted) return;
      setState(() => _coverBusy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            filled ? t.adminCoverFilled : t.adminCoverSavedOnly,
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _coverBusy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${t.adminFailedAnalyzeCover}: $e')),
      );
    }
  }

  /// Applies analyze-cover response into form fields. Returns true if any text/selection filled.
  Future<bool> _applyCoverResult(Map<String, dynamic> data) async {
    final rawSuggested = data['suggested'];
    final suggested = rawSuggested is Map
        ? Map<String, dynamic>.from(rawSuggested)
        : <String, dynamic>{};

    final title = suggested['title']?.toString().trim();
    final isbn = suggested['isbn']?.toString().trim();
    final desc = suggested['description']?.toString().trim();
    final publisherName = suggested['publisher']?.toString().trim();
    final publisherNames = <String>[];
    if (suggested['publishers'] is List) {
      for (final e in suggested['publishers'] as List) {
        final n = e.toString().trim();
        if (n.isNotEmpty) publisherNames.add(n);
      }
    }
    if (publisherNames.isEmpty && publisherName != null && publisherName.isNotEmpty) {
      publisherNames.add(publisherName);
    }
    final pages = suggested['pages'];
    final year = suggested['publish_year'];

    final authorNames = (suggested['authors'] is List)
        ? (suggested['authors'] as List)
            .map((e) => e.toString().trim())
            .where((e) => e.isNotEmpty)
            .toList()
        : <String>[];

    List<String> authorIds = const [];
    if (authorNames.isNotEmpty) {
      authorIds = await _resolveAuthorIds(authorNames);
    }

    final matchedPublisherIds = <String>[];
    for (final name in publisherNames) {
      final id = await _resolvePublisherId(name);
      if (id != null && !matchedPublisherIds.contains(id)) {
        matchedPublisherIds.add(id);
      }
    }

    if (!mounted) return false;

    setState(() {
      _coverImage = data['cover_image']?.toString() ?? _coverImage;
      _coverImageThumb =
          data['cover_image_thumb']?.toString() ?? _coverImageThumb;

      if (title != null && title.isNotEmpty) _titleCtrl.text = title;
      if (isbn != null && isbn.isNotEmpty) _isbnCtrl.text = isbn;
      if (desc != null && desc.isNotEmpty) _descCtrl.text = desc;
      if (pages != null && pages.toString().trim().isNotEmpty) {
        _pagesCtrl.text = pages.toString().trim();
      }
      if (year != null && year.toString().trim().isNotEmpty) {
        _yearCtrl.text = year.toString().trim();
      }
      if (matchedPublisherIds.isNotEmpty) {
        _publisherIds
          ..clear()
          ..addAll(matchedPublisherIds);
        _publisherId = matchedPublisherIds.first;
      }
      if (authorIds.isNotEmpty) {
        _authorIds
          ..clear()
          ..addAll(authorIds);
      }
    });

    return (title != null && title.isNotEmpty) ||
        (isbn != null && isbn.isNotEmpty) ||
        (desc != null && desc.isNotEmpty) ||
        authorIds.isNotEmpty ||
        matchedPublisherIds.isNotEmpty ||
        (pages != null && pages.toString().trim().isNotEmpty) ||
        (year != null && year.toString().trim().isNotEmpty);
  }

  String? _matchPublisherId(String publisherName) {
    final needle = publisherName.trim().toLowerCase();
    if (needle.isEmpty) return null;

    Map<String, dynamic>? exact;
    Map<String, dynamic>? partial;
    var partialLen = 0;
    for (final p in _publishers) {
      final name = (p['name']?.toString() ?? '').trim().toLowerCase();
      if (name.isEmpty) continue;
      if (name == needle) {
        exact = p;
        break;
      }
      if ((name.contains(needle) || needle.contains(name)) &&
          name.length > partialLen) {
        partial = p;
        partialLen = name.length;
      }
    }
    final match = exact ?? partial;
    return match == null ? null : _mapId(match);
  }

  Future<String?> _resolvePublisherId(String publisherName) async {
    final existing = _matchPublisherId(publisherName);
    if (existing != null) return existing;
    final created = await ApiService.instance.adminPublishersCreate({
      'name': publisherName.trim(),
    });
    if (created.success && created.data != null) {
      _publishers = [..._publishers, created.data!];
      return _mapId(created.data!);
    }
    return null;
  }

  Future<List<String>> _resolveAuthorIds(List<String> names) async {
    final ids = <String>[];
    for (final name in names) {
      final existing = _authors.where(
        (a) => (a.name ?? '').trim().toLowerCase() == name.toLowerCase(),
      );
      if (existing.isNotEmpty) {
        ids.add(existing.first.id);
        continue;
      }
      final created = await ApiService.instance.adminAuthorsCreate(name);
      if (created.success && created.data != null) {
        _authors = [..._authors, created.data!];
        ids.add(created.data!.id);
      }
    }
    return ids;
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
    final pagesText = _pagesCtrl.text.trim();
    final yearText = _yearCtrl.text.trim();
    final pages = pagesText.isEmpty ? null : int.tryParse(pagesText);
    final year = yearText.isEmpty ? null : int.tryParse(yearText);
    if (price == null || stock == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.invalidNumber)),
      );
      return;
    }
    if (pagesText.isNotEmpty && pages == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.invalidNumber)),
      );
      return;
    }
    if (yearText.isNotEmpty && year == null) {
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
      'pages': ?pages,
      'publish_year': ?year,
      if (_publisherIds.isNotEmpty) 'publisher_ids': _publisherIds.toList(),
      if (_publisherIds.isNotEmpty)
        'publisher_id': _publisherIds.first
      else if (_publisherId != null && _publisherId!.isNotEmpty)
        'publisher_id': _publisherId,
      if (_coverImage != null && _coverImage!.isNotEmpty)
        'cover_image': _coverImage,
      if (_coverImageThumb != null && _coverImageThumb!.isNotEmpty)
        'cover_image_thumb': _coverImageThumb,
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
    final preview = (_coverImageThumb ?? _coverImage)?.trim();

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
                      Text(t.adminCoverImage,
                          style: Theme.of(context).textTheme.titleSmall),
                      const SizedBox(height: 4),
                      Text(t.adminCoverImageHint,
                          style: Theme.of(context).textTheme.bodySmall),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed:
                                  _coverBusy ? null : () => _pickCover(ImageSource.camera),
                              icon: const Icon(Icons.photo_camera),
                              label: Text(t.adminTakeCoverPhoto),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: FilledButton.tonalIcon(
                              onPressed:
                                  _coverBusy ? null : () => _pickCover(ImageSource.gallery),
                              icon: const Icon(Icons.photo_library),
                              label: Text(t.adminUploadCoverFile),
                            ),
                          ),
                        ],
                      ),
                      if (_coverBusy) ...[
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                            const SizedBox(width: 8),
                            Text(t.adminReadingCover),
                          ],
                        ),
                      ],
                      if (preview != null && preview.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.network(
                            preview,
                            height: 160,
                            fit: BoxFit.contain,
                            errorBuilder: (_, _, _) => const Icon(Icons.broken_image, size: 48),
                          ),
                        ),
                        TextButton(
                          onPressed: () => setState(() {
                            _coverImage = null;
                            _coverImageThumb = null;
                          }),
                          child: Text(t.adminRemoveCover),
                        ),
                      ],
                      const SizedBox(height: 16),
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
                        controller: _pagesCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(labelText: t.bookPages),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _yearCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(labelText: t.bookYear),
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
                        initialValue: _condition,
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
                        key: ValueKey('category_${_categoryId ?? 'none'}'),
                        initialValue: _categoryId,
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
                      Text(t.adminSelectPublisher,
                          style: Theme.of(context).textTheme.titleSmall),
                      ..._publishers.map((p) {
                        final id = _mapId(p);
                        final name = p['name']?.toString() ?? id;
                        return CheckboxListTile(
                          value: _publisherIds.contains(id),
                          title: Text(name),
                          controlAffinity: ListTileControlAffinity.leading,
                          onChanged: (checked) {
                            setState(() {
                              if (checked == true) {
                                _publisherIds.add(id);
                              } else {
                                _publisherIds.remove(id);
                              }
                              _publisherId =
                                  _publisherIds.isEmpty ? null : _publisherIds.first;
                            });
                          },
                        );
                      }),
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
