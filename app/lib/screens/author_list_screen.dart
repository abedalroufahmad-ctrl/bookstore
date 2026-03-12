import 'dart:async';

import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';

class AuthorListScreen extends StatefulWidget {
  const AuthorListScreen({super.key});

  @override
  State<AuthorListScreen> createState() => _AuthorListScreenState();
}

class _AuthorListScreenState extends State<AuthorListScreen> {
  final List<Author> _authors = [];
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
      _authors.clear();
      _hasMore = true;
      _lastSearchQuery = search;
    });
    final res = await ApiService.instance.getAuthorsPaginated(1, search: search);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _authors.addAll(res.data!.items);
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
    final res = await ApiService.instance.getAuthorsPaginated(nextPage, search: _lastSearchQuery);
    if (!mounted) return;
    setState(() {
      _loadingMore = false;
      if (res.success && res.data != null) {
        _authors.addAll(res.data!.items);
        _hasMore = res.data!.hasMore;
        _page = nextPage;
      } else {
        _hasMore = false;
      }
    });
  }

  String _getInitials(String? name) {
    if (name == null || name.isEmpty) return '??';
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.length >= 2) return parts[0][0] + parts[parts.length - 1][0];
    return name.substring(0, name.length >= 2 ? 2 : 1).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(t.navAuthors)),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: SearchBar(
              controller: _searchController,
              hintText: t.searchAuthorsHint,
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
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(_error ?? ''),
                            const SizedBox(height: 16),
                            TextButton(
                              onPressed: () => _loadFirst(search: _lastSearchQuery),
                              child: Text(t.retry),
                            ),
                          ],
                        ),
                      )
                    : _authors.isEmpty
                        ? Center(
                            child: Text(
                              _lastSearchQuery != null ? t.noSearchResults : t.noAuthors,
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: () => _loadFirst(search: _lastSearchQuery),
                            child: ListView.separated(
                              controller: _scrollController,
                              padding: const EdgeInsets.all(16),
                              itemCount: _authors.length + (_hasMore ? 1 : 0),
                              separatorBuilder: (context, i) {
                                if (i == _authors.length) return const SizedBox.shrink();
                                return const Divider();
                              },
                              itemBuilder: (context, i) {
                                if (i >= _authors.length) {
                                  return const Padding(
                                    padding: EdgeInsets.symmetric(vertical: 16),
                                    child: Center(child: CircularProgressIndicator()),
                                  );
                                }
                                final author = _authors[i];
                                    return ListTile(
                                      onTap: () {
                                        Navigator.pushNamed(
                                          context,
                                          '/author/${author.id}',
                                          arguments: {'name': author.name},
                                        );
                                      },
                                      leading: CircleAvatar(
                                        radius: 25,
                                        backgroundColor: theme.colorScheme.primaryContainer,
                                        child: Text(
                                          _getInitials(author.name),
                                          style: TextStyle(
                                            color: theme.colorScheme.onPrimaryContainer,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                      title: Text(
                                        author.name ?? '',
                                        style: const TextStyle(fontWeight: FontWeight.bold),
                                      ),
                                      subtitle: Text(t.viewAll),
                                      trailing: const Icon(Icons.chevron_right),
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
