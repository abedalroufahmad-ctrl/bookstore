import 'dart:async';

import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';

class CategoryListScreen extends StatefulWidget {
  const CategoryListScreen({super.key});

  @override
  State<CategoryListScreen> createState() => _CategoryListScreenState();
}

class _CategoryListScreenState extends State<CategoryListScreen> {
  final List<Category> _categories = [];
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

  String? _topicCodeFilter;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final args = ModalRoute.of(context)?.settings.arguments as Map?;
    if (args != null && args['topicCode'] != null) {
      _topicCodeFilter = args['topicCode'] as String;
    }
  }

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
      _categories.clear();
      _hasMore = true;
      _lastSearchQuery = search;
    });
    final res = await ApiService.instance.getCategoriesPaginated(1, search: search);
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _categories.addAll(res.data!.items);
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
    final res = await ApiService.instance.getCategoriesPaginated(nextPage, search: _lastSearchQuery);
    if (!mounted) return;
    setState(() {
      _loadingMore = false;
      if (res.success && res.data != null) {
        _categories.addAll(res.data!.items);
        _hasMore = res.data!.hasMore;
        _page = nextPage;
      } else {
        _hasMore = false;
      }
    });
  }

  String _getCategoryIcon(String? deweyCode) {
    if (deweyCode == null) return '📖';
    final code = int.tryParse(deweyCode) ?? 0;
    if (code < 100) return '💻';
    if (code < 200) return '🧠';
    if (code < 300) return '🕌';
    if (code < 400) return '🌍';
    if (code < 500) return '🗣️';
    if (code < 600) return '🔬';
    if (code < 700) return '⚙️';
    if (code < 800) return '🎨';
    if (code < 900) return '📖';
    return '🗺️';
  }

  Color _getCategoryColor(String? deweyCode) {
    final colors = [
      const Color(0xFFFEF3C7),
      const Color(0xFFDBEAFE),
      const Color(0xFFD1FAE5),
      const Color(0xFFFCE7F3),
      const Color(0xFFE0E7FF),
    ];
    if (deweyCode == null) return colors[0];
    return colors[deweyCode.hashCode % colors.length];
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final t = AppLocalizations.of(context);
    
    final localeCode = Localizations.localeOf(context).languageCode;
    
    final topics = [
      {'range': [0, 99], 'name': localeCode == 'ar' ? 'المعلومات، الحواسيب، الأعمال العامة' : 'Information, Computers, Public Business', 'code': '000'},
      {'range': [100, 199], 'name': localeCode == 'ar' ? 'الفلسفة، علم النفس، الأفكار' : 'Philosophy, Psychology, Ideas', 'code': '100'},
      {'range': [200, 299], 'name': localeCode == 'ar' ? 'الدين' : 'Religion', 'code': '200'},
      {'range': [300, 399], 'name': localeCode == 'ar' ? 'العلوم الاجتماعية، المجتمع' : 'Social Sciences, Society', 'code': '300'},
      {'range': [400, 499], 'name': localeCode == 'ar' ? 'اللغة' : 'Language', 'code': '400'},
      {'range': [500, 599], 'name': localeCode == 'ar' ? 'العلوم الطبيعية، الرياضيات' : 'Natural Sciences, Mathematics', 'code': '500'},
      {'range': [600, 699], 'name': localeCode == 'ar' ? 'التكنولوجيا، العلوم التطبيقية' : 'Technology, Applied Sciences', 'code': '600'},
      {'range': [700, 799], 'name': localeCode == 'ar' ? 'الفنون، الترفيه، الرياضة' : 'Arts, Entertainment, Sports', 'code': '700'},
      {'range': [800, 899], 'name': localeCode == 'ar' ? 'الأدب' : 'Literature', 'code': '800'},
      {'range': [900, 999], 'name': localeCode == 'ar' ? 'التاريخ، الجغرافيا' : 'History, Geography', 'code': '900'},
      {'range': [-1, -1], 'name': localeCode == 'ar' ? 'أخرى' : 'Other', 'code': 'Other'},
    ];

    final groupedTopics = <Map<String, dynamic>>[];
    for (final topic in topics) {
      if (_topicCodeFilter != null && _topicCodeFilter != topic['code']) continue;
      final range = topic['range'] as List<int>;
      final cats = _categories.where((cat) {
        final c = int.tryParse(cat.deweyCode ?? '') ?? -1;
        if (topic['code'] == 'Other') return c < 0 || c > 999;
        return c >= range[0] && c <= range[1];
      }).toList();
      if (cats.isNotEmpty) {
        groupedTopics.add({'topic': topic, 'cats': cats});
      }
    }

    return Scaffold(
      appBar: AppBar(title: Text(t.navCategories)),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: SearchBar(
              controller: _searchController,
              hintText: t.searchCategoriesHint,
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
                    : _categories.isEmpty
                        ? Center(
                            child: Text(
                              _lastSearchQuery != null ? t.noSearchResults : t.noCategories,
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: () => _loadFirst(search: _lastSearchQuery),
                            child: ListView.builder(
                              controller: _scrollController,
                              padding: const EdgeInsets.all(16),
                              itemCount: groupedTopics.length + (_hasMore ? 1 : 0),
                              itemBuilder: (context, i) {
                                if (i >= groupedTopics.length) {
                                      return const Padding(
                                        padding: EdgeInsets.all(16),
                                        child: Center(child: CircularProgressIndicator()),
                                      );
                                    }
                                    
                                    final group = groupedTopics[i];
                                    final topic = group['topic'] as Map<String, dynamic>;
                                    final cats = group['cats'] as List<Category>;

                                    return Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Padding(
                                          padding: EdgeInsets.only(bottom: 12, top: i > 0 ? 24 : 0),
                                          child: Text(
                                            topic['code'] == 'Other' ? topic['name'] : '${topic['code']} - ${topic['name']}',
                                            style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                                          ),
                                        ),
                                        GridView.builder(
                                          shrinkWrap: true,
                                          physics: const NeverScrollableScrollPhysics(),
                                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                            crossAxisCount: 2,
                                            mainAxisSpacing: 16,
                                            crossAxisSpacing: 16,
                                            childAspectRatio: 1.2,
                                          ),
                                          itemCount: cats.length,
                                          itemBuilder: (context, j) {
                                            final cat = cats[j];
                                            return InkWell(
                                              onTap: () {
                                                final title = localeCode == 'ar' && cat.subjectTitleAr != null && cat.subjectTitleAr!.isNotEmpty ? cat.subjectTitleAr : cat.subjectTitleEn;
                                                Navigator.pushNamed(
                                                  context,
                                                  '/category/${cat.id}',
                                                  arguments: {'title': title},
                                                );
                                              },
                                              borderRadius: BorderRadius.circular(12),
                                              child: Container(
                                                decoration: BoxDecoration(
                                                  color: theme.cardColor,
                                                  borderRadius: BorderRadius.circular(12),
                                                  border: Border.all(
                                                    color: theme.colorScheme.outline.withOpacity(0.3),
                                                  ),
                                                ),
                                                child: Column(
                                                  mainAxisAlignment: MainAxisAlignment.center,
                                                  children: [
                                                    Container(
                                                      width: 50,
                                                      height: 50,
                                                      decoration: BoxDecoration(
                                                        color: _getCategoryColor(cat.deweyCode),
                                                        borderRadius: BorderRadius.circular(12),
                                                      ),
                                                      child: Center(
                                                        child: Text(
                                                          _getCategoryIcon(cat.deweyCode),
                                                          style: const TextStyle(fontSize: 24),
                                                        ),
                                                      ),
                                                    ),
                                                    const SizedBox(height: 12),
                                                    Padding(
                                                      padding: const EdgeInsets.symmetric(horizontal: 8),
                                                      child: Text(
                                                        (localeCode == 'ar' && cat.subjectTitleAr != null && cat.subjectTitleAr!.isNotEmpty ? cat.subjectTitleAr : cat.subjectTitleEn) ?? '',
                                                        textAlign: TextAlign.center,
                                                        maxLines: 2,
                                                        overflow: TextOverflow.ellipsis,
                                                        style: const TextStyle(
                                                          fontWeight: FontWeight.bold,
                                                          fontSize: 13,
                                                        ),
                                                      ),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                            );
                                          },
                                        ),
                                      ],
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
