import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';
import '../utils/weight_format.dart';
import '../widgets/entity_typeahead.dart';

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
  final _sizeCtrl = TextEditingController();
  final _weightCtrl = TextEditingController();
  final _editionCtrl = TextEditingController();
  final _discountCtrl = TextEditingController();
  final _coverUrlCtrl = TextEditingController();
  final _picker = ImagePicker();

  String? _bookId;
  String _condition = 'new';
  bool _isVisible = true;
  bool _isSold = false;
  String? _categoryId;
  String? _publisherId;
  String? _coverImage;
  String? _coverImageThumb;
  String _weightUnit = 'kg';
  final Set<String> _publisherIds = {};
  final Set<String> _warehouseIds = {};
  final Set<String> _authorIds = {};
  final Map<String, String> _authorLabels = {};
  final Map<String, String> _publisherLabels = {};
  String? _categoryLabel;

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
    _sizeCtrl.dispose();
    _weightCtrl.dispose();
    _editionCtrl.dispose();
    _discountCtrl.dispose();
    _coverUrlCtrl.dispose();
    super.dispose();
  }

  String _mapId(Map<String, dynamic> m) =>
      (m['_id'] ?? m['id'] ?? '').toString();

  String _categoryLabelOf(Category c) {
    final title = (c.subjectTitleEn ?? c.subjectTitleAr ?? '').trim();
    final code = (c.deweyCode ?? '').trim();
    if (title.isNotEmpty && code.isNotEmpty) return '$title ($code)';
    if (title.isNotEmpty) return title;
    if (code.isNotEmpty) return code;
    return c.id;
  }

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final catsF = ApiService.instance.adminCategoriesList();
    final authorsF = ApiService.instance.adminAuthorsList();
    final warehousesF = ApiService.instance.adminWarehousesList();
    final publishersF = ApiService.instance.adminPublishersList();
    final settingsF = ApiService.instance.getSettings();
    final bookF =
        _bookId != null ? ApiService.instance.adminBooksGet(_bookId!) : null;

    final cats = await catsF;
    final authors = await authorsF;
    final warehouses = await warehousesF;
    final publishers = await publishersF;
    final settings = await settingsF;
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
    if (settings.success && settings.data != null) {
      final unit = settings.data!['weight_unit']?.toString();
      if (unit != null && unit.isNotEmpty) _weightUnit = unit;
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
        _sizeCtrl.text = book.size ?? '';
        _weightCtrl.text = gramsToDisplayInput(book.weight, _weightUnit);
        _editionCtrl.text = book.editionNumber?.toString() ?? '';
        _discountCtrl.text = book.discountPercent?.toString() ?? '';
        _condition = book.condition == 'used' ? 'used' : 'new';
        _isVisible = book.isVisible;
        _isSold = book.isSold;
        _categoryId = book.category?.id;
        _categoryLabel =
            book.category != null ? _categoryLabelOf(book.category!) : null;
        _publisherId = book.publisher?.id;
        _coverImage = book.coverImage;
        _coverImageThumb = book.coverImageThumb;
        _coverUrlCtrl.text = book.coverImage ?? '';
        _publisherIds
          ..clear()
          ..addAll(book.publisherIds ?? const <String>[]);
        if (_publisherIds.isEmpty && _publisherId != null && _publisherId!.isNotEmpty) {
          _publisherIds.add(_publisherId!);
        }
        for (final p in book.publishers ?? const <Publisher>[]) {
          if (p.id.isNotEmpty) {
            _publisherIds.add(p.id);
            _publisherLabels[p.id] = (p.name ?? '').trim();
          }
        }
        if (_publisherId != null &&
            book.publisher?.name != null &&
            book.publisher!.name!.trim().isNotEmpty) {
          _publisherLabels[_publisherId!] = book.publisher!.name!.trim();
        }
        if (_publisherIds.isNotEmpty) {
          _publisherId = _publisherIds.first;
        }
        if (_categoryId != null &&
            !_categories.any((c) => c.id == _categoryId) &&
            book.category != null) {
          _categories = [..._categories, book.category!];
        }
        for (final id in _publisherIds) {
          if (_publisherLabels[id]?.isNotEmpty == true) continue;
          final match = _publishers.where((p) => _mapId(p) == id);
          if (match.isNotEmpty) {
            _publisherLabels[id] = match.first['name']?.toString() ?? id;
          }
        }
        if (book.warehouse?.id != null && book.warehouse!.id.isNotEmpty) {
          _warehouseIds.add(book.warehouse!.id);
        }
        _authorIds.clear();
        _authorLabels.clear();
        for (final a in book.authors ?? const <Author>[]) {
          if (a.id.isNotEmpty) {
            _authorIds.add(a.id);
            _authorLabels[a.id] = (a.name ?? '').trim();
          }
        }
        await _preloadWarehousesForIsbn(book.isbn);
      } else {
        _error = bookRes.message;
      }
    }

    setState(() => _loading = false);
  }

  Future<void> _preloadWarehousesForIsbn(String? isbn) async {
    final needle = isbn?.trim() ?? '';
    if (needle.isEmpty) return;
    final res = await ApiService.instance.adminBooksList(params: {
      'search': needle,
      'per_page': '100',
    });
    if (!res.success || res.data == null) return;
    final d = res.data;
    List items = const [];
    if (d is Map && d['data'] is List) {
      items = d['data'] as List;
    } else if (d is Map && d['data'] is Map && (d['data'] as Map)['data'] is List) {
      items = (d['data'] as Map)['data'] as List;
    } else if (d is List) {
      items = d;
    }
    for (final raw in items) {
      if (raw is! Map) continue;
      final m = Map<String, dynamic>.from(raw);
      if ((m['isbn']?.toString() ?? '').trim() != needle) continue;
      final wid = (m['warehouse_id'] ??
              (m['warehouse'] is Map
                  ? (m['warehouse']['_id'] ?? m['warehouse']['id'])
                  : null))
          ?.toString();
      if (wid != null && wid.isNotEmpty) _warehouseIds.add(wid);
    }
  }

  Future<void> _pickCover(ImageSource source) async {
    final t = AppLocalizations.of(context);
    // Show progress immediately — camera/gallery encode can take several seconds.
    setState(() => _coverBusy = true);
    try {
      final picked = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 2000,
        requestFullMetadata: false,
      );
      if (picked == null) {
        if (mounted) setState(() => _coverBusy = false);
        return;
      }
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
      if (_coverImage != null && _coverImage!.isNotEmpty) {
        _coverUrlCtrl.text = _coverImage!;
      }

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
        for (final id in matchedPublisherIds) {
          final match = _publishers.where((p) => _mapId(p) == id);
          if (match.isNotEmpty) {
            _publisherLabels[id] = match.first['name']?.toString() ?? id;
          }
        }
      }
      if (authorIds.isNotEmpty) {
        _authorIds
          ..clear()
          ..addAll(authorIds);
        for (final id in authorIds) {
          final match = _authors.where((a) => a.id == id);
          if (match.isNotEmpty) {
            _authorLabels[id] = match.first.name ?? id;
          }
        }
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
        _authorLabels[existing.first.id] = existing.first.name ?? name;
        continue;
      }
      final created = await ApiService.instance.adminAuthorsCreate(name);
      if (created.success && created.data != null) {
        _authors = [..._authors, created.data!];
        ids.add(created.data!.id);
        _authorLabels[created.data!.id] = created.data!.name ?? name;
      }
    }
    return ids;
  }

  Future<List<({String id, String label})>> _searchAuthors(String query) async {
    final res = await ApiService.instance.adminAuthorsList(
      search: query.isEmpty ? null : query,
      perPage: query.isEmpty ? 40 : 50,
    );
    if (!res.success || res.data == null) return const [];
    for (final a in res.data!) {
      if (!_authors.any((x) => x.id == a.id)) {
        _authors = [..._authors, a];
      }
    }
    return res.data!
        .map((a) => (id: a.id, label: (a.name ?? a.id).trim()))
        .toList();
  }

  Future<List<({String id, String label})>> _searchPublishers(String query) async {
    final res = await ApiService.instance.adminPublishersList(
      search: query.isEmpty ? null : query,
      perPage: query.isEmpty ? 40 : 50,
    );
    if (!res.success || res.data == null) return const [];
    for (final p in res.data!) {
      final id = _mapId(p);
      if (id.isEmpty) continue;
      if (!_publishers.any((x) => _mapId(x) == id)) {
        _publishers = [..._publishers, p];
      }
    }
    return res.data!
        .map((p) {
          final id = _mapId(p);
          return (id: id, label: (p['name']?.toString() ?? id).trim());
        })
        .where((e) => e.id.isNotEmpty)
        .toList();
  }

  Future<List<({String id, String label})>> _searchCategories(String query) async {
    final res = await ApiService.instance.adminCategoriesList(
      search: query.isEmpty ? null : query,
      perPage: query.isEmpty ? 40 : 50,
    );
    if (!res.success || res.data == null) return const [];
    for (final c in res.data!) {
      if (!_categories.any((x) => x.id == c.id)) {
        _categories = [..._categories, c];
      }
    }
    return res.data!
        .map((c) => (id: c.id, label: _categoryLabelOf(c)))
        .toList();
  }

  Future<void> _createAuthor(String name) async {
    final created = await ApiService.instance.adminAuthorsCreate(name);
    if (!mounted) return;
    if (!created.success || created.data == null) {
      final t = AppLocalizations.of(context);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            created.message.isNotEmpty ? created.message : t.adminFailedSave,
          ),
        ),
      );
      return;
    }
    final a = created.data!;
    setState(() {
      _authors = [..._authors, a];
      _authorIds.add(a.id);
      _authorLabels[a.id] = (a.name ?? name).trim();
    });
  }

  Future<void> _createCategoryNamed(String name) async {
    final t = AppLocalizations.of(context);
    final deweyCtrl = TextEditingController();
    final titleCtrl = TextEditingController(text: name);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(t.adminAddCategory),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: titleCtrl,
              decoration: InputDecoration(labelText: t.adminSubjectTitle),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: deweyCtrl,
              decoration: InputDecoration(labelText: t.adminDeweyCode),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(t.cancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(t.adminSave),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final title = titleCtrl.text.trim();
    final dewey = deweyCtrl.text.trim();
    if (title.isEmpty || dewey.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.fieldRequired)),
      );
      return;
    }
    final created = await ApiService.instance.adminCategoriesCreate(
      deweyCode: dewey,
      subjectTitleEn: title,
    );
    if (!mounted) return;
    if (!created.success || created.data == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            created.message.isNotEmpty ? created.message : t.adminFailedSave,
          ),
        ),
      );
      return;
    }
    final c = created.data!;
    setState(() {
      _categories = [..._categories, c];
      _categoryId = c.id;
      _categoryLabel = _categoryLabelOf(c);
    });
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
    final editionText = _editionCtrl.text.trim();
    final discountText = _discountCtrl.text.trim();
    final edition = editionText.isEmpty ? null : int.tryParse(editionText);
    final discount =
        discountText.isEmpty ? null : int.tryParse(discountText.split('.').first);
    final weightGrams = displayToGrams(_weightCtrl.text, _weightUnit);
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
    if (editionText.isNotEmpty && edition == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.invalidNumber)),
      );
      return;
    }
    if (discountText.isNotEmpty &&
        (discount == null || discount < 0 || discount > 100)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.mustBeBetween0And100)),
      );
      return;
    }
    if (_weightCtrl.text.trim().isNotEmpty && weightGrams == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.invalidNumber)),
      );
      return;
    }

    final coverUrl = _coverUrlCtrl.text.trim();
    if (coverUrl.isNotEmpty) {
      _coverImage = coverUrl;
    }

    final body = <String, dynamic>{
      'title': _titleCtrl.text.trim(),
      'isbn': _isbnCtrl.text.trim(),
      'price': price,
      'stock_quantity': stock,
      'description': _descCtrl.text.trim(),
      'condition': _condition,
      'is_visible': _isVisible,
      'is_sold': _condition == 'used' ? _isSold : false,
      'warehouse_ids': _warehouseIds.toList(),
      'category_id': _categoryId,
      'author_ids': _authorIds.toList(),
      'pages': ?pages,
      'publish_year': ?year,
      'size': _sizeCtrl.text.trim(),
      'weight': ?weightGrams,
      'edition_number': ?edition,
      'discount_percent': ?discount,
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
                            _coverUrlCtrl.clear();
                          }),
                          child: Text(t.adminRemoveCover),
                        ),
                      ],
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _coverUrlCtrl,
                        decoration: InputDecoration(labelText: t.adminCoverUrl),
                        onChanged: (v) {
                          final url = v.trim();
                          setState(() {
                            _coverImage = url.isEmpty ? null : url;
                            if (url.isEmpty) _coverImageThumb = null;
                          });
                        },
                      ),
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
                        controller: _sizeCtrl,
                        decoration: InputDecoration(labelText: t.bookSize),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _weightCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: InputDecoration(
                          labelText: t.weightWithUnit(_weightUnit),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _editionCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(labelText: t.adminEditionNumber),
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
                        controller: _discountCtrl,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(
                          labelText: t.adminSpecialDiscount,
                          helperText: t.adminGlobalDiscountHint,
                        ),
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
                          if (v != null) {
                            setState(() {
                              _condition = v;
                              if (v != 'used') _isSold = false;
                            });
                          }
                        },
                      ),
                      SwitchListTile(
                        title: Text(t.adminVisible),
                        value: _isVisible,
                        onChanged: (v) => setState(() => _isVisible = v),
                      ),
                      if (_condition == 'used')
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
                      const SizedBox(height: 12),
                      EntityTypeahead(
                        label: '${t.adminSelectCategory} *',
                        hint: t.adminSearchCategoryHint,
                        selected: _categoryId == null
                            ? const []
                            : [
                                (
                                  id: _categoryId!,
                                  label: _categoryLabel ??
                                      _categories
                                          .where((c) => c.id == _categoryId)
                                          .map(_categoryLabelOf)
                                          .firstOrNull ??
                                      _categoryId!,
                                ),
                              ],
                        onSearch: _searchCategories,
                        onSelect: (id, label) {
                          setState(() {
                            _categoryId = id;
                            _categoryLabel = label;
                          });
                        },
                        onRemove: (_) {
                          setState(() {
                            _categoryId = null;
                            _categoryLabel = null;
                          });
                        },
                        onCreate: _createCategoryNamed,
                        createLabelBuilder: t.createNewCategoryNamed,
                      ),
                      const SizedBox(height: 12),
                      EntityTypeahead(
                        label: t.adminSelectPublisher,
                        hint: t.adminSearchPublisherHint,
                        selected: _publisherIds
                            .map(
                              (id) => (
                                id: id,
                                label: _publisherLabels[id] ??
                                    _publishers
                                        .where((p) => _mapId(p) == id)
                                        .map((p) => p['name']?.toString() ?? id)
                                        .firstOrNull ??
                                    id,
                              ),
                            )
                            .toList(),
                        onSearch: _searchPublishers,
                        onSelect: (id, label) {
                          setState(() {
                            _publisherIds.add(id);
                            _publisherLabels[id] = label;
                            _publisherId = _publisherIds.first;
                          });
                        },
                        onRemove: (id) {
                          setState(() {
                            _publisherIds.remove(id);
                            _publisherLabels.remove(id);
                            _publisherId =
                                _publisherIds.isEmpty ? null : _publisherIds.first;
                          });
                        },
                      ),
                      const SizedBox(height: 12),
                      EntityTypeahead(
                        label: '${t.bookAuthors} *',
                        hint: t.adminSearchAuthorHint,
                        selected: _authorIds
                            .map(
                              (id) => (
                                id: id,
                                label: _authorLabels[id] ??
                                    _authors
                                        .where((a) => a.id == id)
                                        .map((a) => a.name ?? id)
                                        .firstOrNull ??
                                    id,
                              ),
                            )
                            .toList(),
                        onSearch: _searchAuthors,
                        onSelect: (id, label) {
                          setState(() {
                            _authorIds.add(id);
                            _authorLabels[id] = label;
                          });
                        },
                        onRemove: (id) {
                          setState(() {
                            _authorIds.remove(id);
                            _authorLabels.remove(id);
                          });
                        },
                        onCreate: _createAuthor,
                        createLabelBuilder: t.createNewAuthorNamed,
                      ),
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
