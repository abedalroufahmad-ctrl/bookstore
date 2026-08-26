import 'dart:async';

import 'package:flutter/material.dart';
import 'package:getwidget/getwidget.dart';

import '../api/api_client.dart';
import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';
import '../widgets/book_card.dart';

class BookListScreen extends StatefulWidget {
  /// When embedded in [MainShell], the shell provides the AppBar — hide this one.
  final bool showAppBar;

  const BookListScreen({super.key, this.showAppBar = true});

  @override
  State<BookListScreen> createState() => _BookListScreenState();
}

class _BookListScreenState extends State<BookListScreen> {
  final List<Book> _books = [];
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;
  String? _lastSearchQuery;
  /// null = all, 'new' | 'used'
  String? _condition;
  String? _categoryId;
  String? _warehouseId;
  String? _publisherId;

  List<Category> _categories = [];
  List<Warehouse> _warehouses = [];
  List<Publisher> _publishers = [];

  int _page = 1;
  bool _hasMore = true;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  static const _searchDebounceDuration = Duration(milliseconds: 400);

  @override
  void initState() {
    super.initState();
    _loadFilterOptions();
    _loadFirst();
    _scrollController.addListener(_onScroll);
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _scrollController.dispose();
    _searchController.removeListener(_onSearchChanged);
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadFilterOptions() async {
    final results = await Future.wait([
      ApiService.instance.getCategoriesPaginated(1, perPage: 200),
      ApiService.instance.getWarehousesPaginated(1, perPage: 200),
      ApiService.instance.getPublishersPaginated(1, perPage: 200),
    ]);
    if (!mounted) return;
    setState(() {
      final cats = results[0] as ApiResponse<PaginatedResult<Category>>;
      final whs = results[1] as ApiResponse<PaginatedResult<Warehouse>>;
      final pubs = results[2] as ApiResponse<PaginatedResult<Publisher>>;
      if (cats.success && cats.data != null) _categories = cats.data!.items;
      if (whs.success && whs.data != null) _warehouses = whs.data!.items;
      if (pubs.success && pubs.data != null) _publishers = pubs.data!.items;
    });
  }

  void _onSearchChanged() {
    setState(() {});
    _searchDebounce?.cancel();
    _searchDebounce = Timer(_searchDebounceDuration, () {
      final query = _searchController.text.trim();
      if (query == (_lastSearchQuery ?? '')) return;
      _loadFirst(search: query.isEmpty ? null : query);
    });
  }

  void _onScroll() {
    if (!_hasMore || _loadingMore || _loading) return;
    final pos = _scrollController.position;
    if (pos.pixels >= pos.maxScrollExtent - 200) {
      _loadMore();
    }
  }

  void _setCondition(String? condition) {
    if (_condition == condition) return;
    setState(() => _condition = condition);
    _loadFirst(search: _lastSearchQuery);
  }

  void _applyCatalogFilter({
    String? categoryId,
    String? warehouseId,
    String? publisherId,
    bool clearCategory = false,
    bool clearWarehouse = false,
    bool clearPublisher = false,
  }) {
    setState(() {
      if (clearCategory) {
        _categoryId = null;
      } else if (categoryId != null) {
        _categoryId = categoryId.isEmpty ? null : categoryId;
      }
      if (clearWarehouse) {
        _warehouseId = null;
      } else if (warehouseId != null) {
        _warehouseId = warehouseId.isEmpty ? null : warehouseId;
      }
      if (clearPublisher) {
        _publisherId = null;
      } else if (publisherId != null) {
        _publisherId = publisherId.isEmpty ? null : publisherId;
      }
    });
    _loadFirst(search: _lastSearchQuery);
  }

  void _clearFilters() {
    setState(() {
      _condition = null;
      _categoryId = null;
      _warehouseId = null;
      _publisherId = null;
    });
    _loadFirst(search: _lastSearchQuery);
  }

  bool get _hasActiveFilters =>
      _condition != null ||
      _categoryId != null ||
      _warehouseId != null ||
      _publisherId != null;

  Future<void> _loadFirst({String? search}) async {
    setState(() {
      _loading = true;
      _error = null;
      _page = 1;
      _books.clear();
      _hasMore = true;
      _lastSearchQuery = search;
    });
    final res = await ApiService.instance.getBooksPaginated(
      1,
      search: search,
      condition: _condition,
      categoryId: _categoryId,
      warehouseId: _warehouseId,
      publisherId: _publisherId,
    );
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _books.addAll(res.data!.items);
        _hasMore = res.data!.hasMore;
        _page = 1;
      } else {
        _error = res.message;
      }
    });
  }

  Future<void> _loadMore() async {
    if (!_hasMore || _loadingMore) return;
    setState(() => _loadingMore = true);
    final nextPage = _page + 1;
    final res = await ApiService.instance.getBooksPaginated(
      nextPage,
      search: _lastSearchQuery,
      condition: _condition,
      categoryId: _categoryId,
      warehouseId: _warehouseId,
      publisherId: _publisherId,
    );
    if (!mounted) return;
    setState(() {
      _loadingMore = false;
      if (res.success && res.data != null) {
        _books.addAll(res.data!.items);
        _hasMore = res.data!.hasMore;
        _page = nextPage;
      } else {
        _hasMore = false;
      }
    });
  }

  String _categoryLabel(Category c, AppLocalizations t) {
    final title = t.isAr && (c.subjectTitleAr?.isNotEmpty ?? false)
        ? c.subjectTitleAr!
        : (c.subjectTitleEn ?? c.deweyCode ?? '');
    final code = c.deweyCode;
    if (code != null && code.isNotEmpty) return '$title ($code)';
    return title;
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: widget.showAppBar ? AppBar(title: Text(t.booksTitle)) : null,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: SearchBar(
              controller: _searchController,
              hintText: t.searchBooksHint,
              leading: Icon(Icons.search, color: theme.colorScheme.outline),
              trailing: _searchController.text.isNotEmpty
                  ? [
                      IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          _loadFirst();
                        },
                      ),
                    ]
                  : null,
              onChanged: (_) => setState(() {}),
              padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(horizontal: 16, vertical: 12)),
              elevation: const WidgetStatePropertyAll(0),
              backgroundColor: WidgetStatePropertyAll(theme.cardColor),
              shape: WidgetStatePropertyAll(
                RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Wrap(
              spacing: 8,
              children: [
                ChoiceChip(
                  label: Text(t.filterAllBooks),
                  selected: _condition == null,
                  onSelected: (_) => _setCondition(null),
                ),
                ChoiceChip(
                  label: Text(t.conditionNew),
                  selected: _condition == 'new',
                  onSelected: (_) => _setCondition('new'),
                ),
                ChoiceChip(
                  label: Text(t.conditionUsed),
                  selected: _condition == 'used',
                  onSelected: (_) => _setCondition('used'),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String?>(
                        initialValue: _categoryId,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: t.navCategories,
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                        items: [
                          DropdownMenuItem(value: null, child: Text(t.filterAllBooks)),
                          ..._categories.map(
                            (c) => DropdownMenuItem(
                              value: c.id,
                              child: Text(_categoryLabel(c, t), overflow: TextOverflow.ellipsis),
                            ),
                          ),
                        ],
                        onChanged: (v) => _applyCatalogFilter(
                          categoryId: v ?? '',
                          clearCategory: v == null,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String?>(
                        initialValue: _publisherId,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: t.navPublishers,
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                        items: [
                          DropdownMenuItem(value: null, child: Text(t.filterAllBooks)),
                          ..._publishers.map(
                            (p) => DropdownMenuItem(
                              value: p.id,
                              child: Text(p.name ?? '', overflow: TextOverflow.ellipsis),
                            ),
                          ),
                        ],
                        onChanged: (v) => _applyCatalogFilter(
                          publisherId: v ?? '',
                          clearPublisher: v == null,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: DropdownButtonFormField<String?>(
                        initialValue: _warehouseId,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: t.navWarehouses,
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        ),
                        items: [
                          DropdownMenuItem(value: null, child: Text(t.filterAllBooks)),
                          ..._warehouses.map(
                            (w) => DropdownMenuItem(
                              value: w.id,
                              child: Text(w.name ?? '', overflow: TextOverflow.ellipsis),
                            ),
                          ),
                        ],
                        onChanged: (v) => _applyCatalogFilter(
                          warehouseId: v ?? '',
                          clearWarehouse: v == null,
                        ),
                      ),
                    ),
                  ],
                ),
                if (_hasActiveFilters) ...[
                  const SizedBox(height: 8),
                  Align(
                    alignment: AlignmentDirectional.centerStart,
                    child: TextButton(
                      onPressed: _clearFilters,
                      child: Text(t.clearFilters),
                    ),
                  ),
                ],
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: GFLoader(type: GFLoaderType.android, size: GFSize.LARGE))
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              _error ?? '',
                              style: TextStyle(color: theme.colorScheme.error),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 16),
                            GFButton(
                              onPressed: () => _loadFirst(search: _lastSearchQuery),
                              text: t.retry,
                              color: theme.colorScheme.primary,
                            ),
                          ],
                        ),
                      )
                    : _books.isEmpty
                        ? Center(
                            child: Text(
                              _lastSearchQuery != null ? t.noSearchResults : t.noBooks,
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: () => _loadFirst(search: _lastSearchQuery),
                            child: GridView.builder(
                              controller: _scrollController,
                              padding: const EdgeInsets.all(12),
                              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisCount: 2,
                                mainAxisSpacing: 8,
                                crossAxisSpacing: 8,
                                childAspectRatio: 0.64,
                              ),
                              itemCount: _books.length + (_hasMore ? 1 : 0),
                              itemBuilder: (context, i) {
                                if (i >= _books.length) {
                                  return const Padding(
                                    padding: EdgeInsets.all(16),
                                    child: Center(
                                      child: GFLoader(
                                        type: GFLoaderType.android,
                                        size: GFSize.SMALL,
                                      ),
                                    ),
                                  );
                                }
                                final book = _books[i];
                                return BookCard(
                                  book: book,
                                  onTap: () => Navigator.pushNamed(
                                    context,
                                    '/book/${book.id}',
                                    arguments: book,
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
