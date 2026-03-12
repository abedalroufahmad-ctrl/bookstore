import 'dart:async';

import 'package:flutter/material.dart';
import 'package:getwidget/getwidget.dart';

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
  final List<Book> _books = [];
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;
  String? _lastSearchQuery;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  static const _searchDebounceDuration = Duration(milliseconds: 400);

  @override
  void initState() {
    super.initState();
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

  Future<void> _loadFirst({String? search}) async {
    setState(() {
      _loading = true;
      _error = null;
      _page = 1;
      _books.clear();
      _hasMore = true;
      _lastSearchQuery = search;
    });
    final res = await ApiService.instance.getBooksPaginated(1, search: search);
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
    final res = await ApiService.instance.getBooksPaginated(nextPage, search: _lastSearchQuery);
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

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(t.booksTitle)),
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
              shape: WidgetStatePropertyAll(RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
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
                                    child: Center(child: GFLoader(type: GFLoaderType.android, size: GFSize.SMALL)),
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
