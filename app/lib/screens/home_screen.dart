import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:flutter_platform_widgets/flutter_platform_widgets.dart';
import 'package:getwidget/getwidget.dart';
import 'package:provider/provider.dart';

import '../providers/locale_provider.dart';
import '../l10n/app_localizations.dart';

import '../api/api_client.dart';
import '../api/api_service.dart';
import '../models/book.dart';
import '../widgets/book_card.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Book> _books = [];
  List<Warehouse> _warehouses = [];
  List<Author> _authors = [];
  double _globalDiscount = 0;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        ApiService.instance.getBooks(),
        ApiService.instance.getWarehousesPaginated(1, perPage: 40),
        ApiService.instance.getAuthors(),
        ApiService.instance.getSettings(),
      ]);

      if (!mounted) return;
      final booksRes = results[0];
      final warehousesRes = results[1] as ApiResponse<PaginatedResult<Warehouse>>;
      final authorsRes = results[2] as ApiResponse<List<Author>>;
      final settingsRes = results[3] as ApiResponse<Map<String, dynamic>>;

      setState(() {
        if (booksRes.success && booksRes.data != null) {
          final d = booksRes.data;
          List<Book> list = [];
          if (d is Map && d['data'] != null) {
            list = (d['data'] as List)
                .map((e) => Book.fromJson(e as Map<String, dynamic>))
                .toList();
          } else if (d is List) {
            list = d.map((e) => Book.fromJson(e as Map<String, dynamic>)).toList();
          }
          _books = list;
        }

        if (warehousesRes.success && warehousesRes.data != null) {
          _warehouses = warehousesRes.data!.items;
        }
        if (authorsRes.success) _authors = authorsRes.data ?? [];

        if (settingsRes.success && settingsRes.data != null) {
          _globalDiscount = (settingsRes.data!['global_discount'] ?? 0).toDouble();
        }

        _isLoading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _error = '${AppLocalizations.of(context).error}: $e';
        });
      }
    }
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
    context.watch<LocaleProvider>();
    final featuredBooks = _books.take(5).toList();
    final newestBooks = _books.reversed.take(10).toList();

    final localeCode = Localizations.localeOf(context).languageCode;

    final mainTopics = [
      {'code': '000', 'name': localeCode == 'ar' ? 'المعلومات، الحواسيب، الأعمال العامة' : 'Information, Computers, Public Business', 'icon': '💻'},
      {'code': '100', 'name': localeCode == 'ar' ? 'الفلسفة، علم النفس، الأفكار' : 'Philosophy, Psychology, Ideas', 'icon': '🧠'},
      {'code': '200', 'name': localeCode == 'ar' ? 'الدين' : 'Religion', 'icon': '🕌'},
      {'code': '300', 'name': localeCode == 'ar' ? 'العلوم الاجتماعية، المجتمع' : 'Social Sciences, Society', 'icon': '🌍'},
      {'code': '400', 'name': localeCode == 'ar' ? 'اللغة' : 'Language', 'icon': '🗣️'},
      {'code': '500', 'name': localeCode == 'ar' ? 'العلوم الطبيعية، الرياضيات' : 'Natural Sciences, Mathematics', 'icon': '🔬'},
      {'code': '600', 'name': localeCode == 'ar' ? 'التكنولوجيا، العلوم التطبيقية' : 'Technology, Applied Sciences', 'icon': '⚙️'},
      {'code': '700', 'name': localeCode == 'ar' ? 'الفنون، الترفيه، الرياضة' : 'Arts, Entertainment, Sports', 'icon': '🎨'},
      {'code': '800', 'name': localeCode == 'ar' ? 'الأدب' : 'Literature', 'icon': '📖'},
      {'code': '900', 'name': localeCode == 'ar' ? 'التاريخ، الجغرافيا' : 'History, Geography', 'icon': '🗺️'},
    ];

    return ColoredBox(
      color: theme.scaffoldBackgroundColor,
      child: RefreshIndicator(
        onRefresh: _loadData,
        child: _isLoading
            ? SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: SizedBox(
                  height: MediaQuery.of(context).size.height * 0.7,
                  child: const Center(
                    child: GFLoader(type: GFLoaderType.android, size: GFSize.LARGE),
                  ),
                ),
              )
            : _error != null
                ? SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: SizedBox(
                      height: MediaQuery.of(context).size.height * 0.7,
                      child: Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
                            const SizedBox(height: 16),
                            GFButton(
                              onPressed: _loadData,
                              text: t.retry,
                              color: theme.colorScheme.primary,
                            ),
                          ],
                        ),
                      ),
                    ),
                  )
                : SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: SearchBar(
                            hintText: t.heroTitle,
                            leading: Icon(Icons.search, color: theme.colorScheme.outline),
                            padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(horizontal: 16, vertical: 12)),
                            elevation: const WidgetStatePropertyAll(0),
                            backgroundColor: WidgetStatePropertyAll(theme.cardColor),
                            shape: WidgetStatePropertyAll(RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                          ),
                        ),
                        if (featuredBooks.isNotEmpty) ...[
                          CarouselSlider(
                            options: CarouselOptions(
                              height: 340,
                              autoPlay: true,
                              enlargeCenterPage: true,
                              viewportFraction: 0.55,
                            ),
                            items: featuredBooks.map((book) {
                              return BookCard(
                                book: book,
                                globalDiscount: _globalDiscount,
                                onTap: () => Navigator.pushNamed(context, '/book/${book.id}', arguments: book),
                              );
                            }).toList(),
                          ),
                        ],
                        _buildSectionHeader(t.navCategories, '/categories'),
                        SizedBox(
                          height: 110,
                          child: ListView.builder(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: mainTopics.length,
                            itemBuilder: (context, i) {
                              final topic = mainTopics[i];
                              return Padding(
                                padding: const EdgeInsets.only(left: 16),
                                child: SizedBox(
                                  width: 80,
                                  child: InkWell(
                                    onTap: () => Navigator.pushNamed(
                                      context,
                                      '/categories',
                                      arguments: {'topicCode': topic['code']},
                                    ),
                                    borderRadius: BorderRadius.circular(16),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.max,
                                      children: [
                                        Container(
                                          width: 56,
                                          height: 56,
                                          decoration: BoxDecoration(
                                            color: _getCategoryColor(topic['code']),
                                            borderRadius: BorderRadius.circular(14),
                                          ),
                                          child: Center(
                                            child: Text(
                                              topic['icon']!,
                                              style: const TextStyle(fontSize: 26),
                                            ),
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        Flexible(
                                          child: Text(
                                            topic['name']!,
                                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        _buildSectionHeader(t.navWarehouses, '/warehouses'),
                        SizedBox(
                          height: 110,
                          child: ListView.builder(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: _warehouses.length,
                            itemBuilder: (context, i) {
                              final w = _warehouses[i];
                              return Padding(
                                padding: const EdgeInsets.only(left: 16),
                                child: SizedBox(
                                  width: 88,
                                  child: InkWell(
                                    onTap: () => Navigator.pushNamed(
                                      context,
                                      '/warehouse/${w.id}',
                                      arguments: {'name': w.name},
                                    ),
                                    borderRadius: BorderRadius.circular(16),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.max,
                                      children: [
                                        Container(
                                          width: 56,
                                          height: 56,
                                          decoration: BoxDecoration(
                                            color: theme.colorScheme.secondaryContainer,
                                            borderRadius: BorderRadius.circular(14),
                                          ),
                                          child: const Center(
                                            child: Text('🏭', style: TextStyle(fontSize: 26)),
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        Flexible(
                                          child: Text(
                                            w.name ?? '',
                                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        _buildSectionHeader(t.navAuthors, '/authors'),
                        SizedBox(
                          height: 110,
                          child: ListView.builder(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: _authors.length,
                            itemBuilder: (context, i) {
                              final author = _authors[i];
                              return Padding(
                                padding: const EdgeInsets.only(left: 16),
                                child: SizedBox(
                                  width: 80,
                                  child: InkWell(
                                    onTap: () => Navigator.pushNamed(
                                      context,
                                      '/author/${author.id}',
                                      arguments: {'name': author.name},
                                    ),
                                    borderRadius: BorderRadius.circular(30),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.max,
                                      children: [
                                        CircleAvatar(
                                          radius: 28,
                                          backgroundColor: theme.colorScheme.primaryContainer,
                                          child: Text(
                                            _getInitials(author.name),
                                            style: TextStyle(
                                              color: theme.colorScheme.onPrimaryContainer,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 12,
                                            ),
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        Flexible(
                                          child: Text(
                                            author.name ?? '',
                                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            textAlign: TextAlign.center,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        _buildSectionHeader(t.newestBooks, '/books'),
                        SizedBox(
                          height: 340,
                          child: ListView.builder(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: newestBooks.length,
                            itemBuilder: (context, i) {
                              return Padding(
                                padding: const EdgeInsets.only(right: 8),
                                child: SizedBox(
                                  width: 160,
                                  child: BookCard(
                                    book: newestBooks[i],
                                    globalDiscount: _globalDiscount,
                                    onTap: () => Navigator.pushNamed(
                                      context,
                                      '/book/${newestBooks[i].id}',
                                      arguments: newestBooks[i],
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                        const SizedBox(height: 32),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildSectionHeader(String title, String route) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Text(
              title,
              style: theme.textTheme.titleLarge,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          PlatformTextButton(
            onPressed: () => Navigator.pushNamed(context, route),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(AppLocalizations.of(context).viewAll),
                Icon(Icons.chevron_right, size: 20, color: theme.colorScheme.primary),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
