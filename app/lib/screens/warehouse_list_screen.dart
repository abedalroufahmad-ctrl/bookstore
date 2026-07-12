import 'dart:async';

import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';

class WarehouseListScreen extends StatefulWidget {
  const WarehouseListScreen({super.key});

  @override
  State<WarehouseListScreen> createState() => _WarehouseListScreenState();
}

class _WarehouseListScreenState extends State<WarehouseListScreen> {
  final List<Warehouse> _items = [];
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();
  Timer? _searchDebounce;
  String? _lastSearchQuery;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  static const _debounce = Duration(milliseconds: 400);

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
    _searchDebounce = Timer(_debounce, () {
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
      _items.clear();
      _hasMore = true;
      _lastSearchQuery = search;
    });
    final res = await ApiService.instance.getWarehousesPaginated(1, search: search);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _items.addAll(res.data!.items);
        _hasMore = res.data!.hasMore;
      } else {
        _error = res.message;
      }
    });
  }

  Future<void> _loadMore() async {
    if (!_hasMore || _loadingMore) return;
    setState(() => _loadingMore = true);
    final next = _page + 1;
    final res = await ApiService.instance.getWarehousesPaginated(next, search: _lastSearchQuery);
    if (!mounted) return;
    setState(() {
      _loadingMore = false;
      if (res.success && res.data != null) {
        _items.addAll(res.data!.items);
        _hasMore = res.data!.hasMore;
        _page = next;
      }
    });
  }

  Color _tileColor(String name) {
    final colors = [
      const Color(0xFFFEF3C7),
      const Color(0xFFDBEAFE),
      const Color(0xFFD1FAE5),
      const Color(0xFFFCE7F3),
    ];
    return colors[name.hashCode.abs() % colors.length];
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(t.warehousesTitle)),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: SearchBar(
              controller: _searchController,
              hintText: t.searchWarehousesHint,
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
                    : _items.isEmpty
                        ? Center(
                            child: Text(
                              _lastSearchQuery != null ? t.noSearchResults : t.noWarehouses,
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: () => _loadFirst(search: _lastSearchQuery),
                            child: GridView.builder(
                              controller: _scrollController,
                              padding: const EdgeInsets.all(16),
                              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisCount: 2,
                                mainAxisSpacing: 16,
                                crossAxisSpacing: 16,
                                childAspectRatio: 1.2,
                              ),
                              itemCount: _items.length + (_hasMore ? 1 : 0),
                              itemBuilder: (context, i) {
                                if (i >= _items.length) {
                                  return const Padding(
                                    padding: EdgeInsets.all(16),
                                    child: Center(child: CircularProgressIndicator()),
                                  );
                                }
                                final w = _items[i];
                                return InkWell(
                                  onTap: () {
                                    Navigator.pushNamed(
                                      context,
                                      '/warehouse/${w.id}',
                                      arguments: {'name': w.name},
                                    );
                                  },
                                  borderRadius: BorderRadius.circular(12),
                                  child: Container(
                                    decoration: BoxDecoration(
                                      color: theme.cardColor,
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(
                                        color: theme.colorScheme.outline.withValues(alpha: 0.3),
                                      ),
                                    ),
                                    child: Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Container(
                                          width: 50,
                                          height: 50,
                                          decoration: BoxDecoration(
                                            color: _tileColor(w.name ?? ''),
                                            borderRadius: BorderRadius.circular(12),
                                          ),
                                          child: const Center(
                                            child: Text('🏭', style: TextStyle(fontSize: 26)),
                                          ),
                                        ),
                                        const SizedBox(height: 12),
                                        Padding(
                                          padding: const EdgeInsets.symmetric(horizontal: 8),
                                          child: Text(
                                            w.name ?? '',
                                            textAlign: TextAlign.center,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.bold,
                                              fontSize: 13,
                                            ),
                                          ),
                                        ),
                                        if (w.city != null || w.country != null)
                                          Padding(
                                            padding: const EdgeInsets.only(top: 4, left: 8, right: 8),
                                            child: Text(
                                              [w.city, w.country].whereType<String>().join(', '),
                                              textAlign: TextAlign.center,
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                                              ),
                                            ),
                                          ),
                                      ],
                                    ),
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
