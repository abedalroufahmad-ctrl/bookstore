import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_platform_widgets/flutter_platform_widgets.dart';
import 'package:getwidget/getwidget.dart';
import 'package:provider/provider.dart';
import '../providers/locale_provider.dart';
import '../l10n/app_localizations.dart';

import '../api/api_client.dart';
import '../api/api_service.dart';
import '../models/book.dart';
import '../providers/auth_provider.dart';
import '../widgets/book_card.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  List<Book> _books = [];
  List<Category> _categories = [];
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
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        ApiService.instance.getBooks(),
        ApiService.instance.getCategories(),
        ApiService.instance.getAuthors(),
        ApiService.instance.getSettings(),
      ]);

      final booksRes = results[0];
      final categoriesRes = results[1] as ApiResponse<List<Category>>;
      final authorsRes = results[2] as ApiResponse<List<Author>>;
      final settingsRes = results[3] as ApiResponse<Map<String, dynamic>>;

      if (mounted) {
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

          if (categoriesRes.success) _categories = categoriesRes.data ?? [];
          if (authorsRes.success) _authors = authorsRes.data ?? [];

          if (settingsRes.success && settingsRes.data != null) {
            _globalDiscount = (settingsRes.data!['global_discount'] ?? 0).toDouble();
          }

          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
        _error = 'حدث خطأ أثناء تحميل البيانات: $e';
      });
    }
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
    final localeProvider = context.watch<LocaleProvider>();
    final featuredBooks = _books.take(5).toList();
    final newestBooks = _books.reversed.take(10).toList();

    return PlatformScaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: PlatformAppBar(
        title: Text(t.appName),
        trailingActions: [
          PlatformIconButton(
            icon: Icon(context.platformIcon(material: Icons.language, cupertino: CupertinoIcons.globe)),
            onPressed: () => localeProvider.toggleLanguage(),
          ),
          PlatformIconButton(
            icon: Icon(context.platformIcons.shoppingCart),
            onPressed: () {
              final auth = context.read<AuthProvider>();
              if (!auth.isLoggedIn) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(t.cartLoginMsg),
                    action: SnackBarAction(
                      label: t.navLogin,
                      onPressed: () => Navigator.pushNamed(context, '/login'),
                    ),
                  ),
                );
              } else {
                Navigator.pushNamed(context, '/cart');
              }
            },
          ),
          Consumer<AuthProvider>(
            builder: (context, auth, _) {
              if (!auth.isLoggedIn) {
                return PlatformPopupMenu(
                  icon: Icon(context.platformIcons.accountCircle),
                  options: [
                    PopupMenuOption(
                      label: t.navLogin,
                      onTap: (_) => Navigator.pushNamed(context, '/login'),
                    ),
                    PopupMenuOption(
                      label: t.navRegister,
                      onTap: (_) => Navigator.pushNamed(context, '/register'),
                    ),
                  ],
                );
              }
              return PlatformPopupMenu(
                icon: Icon(context.platformIcons.accountCircle),
                options: [
                  PopupMenuOption(
                    label: t.navOrders,
                    onTap: (_) => Navigator.pushNamed(context, '/orders'),
                  ),
                  PopupMenuOption(
                    label: t.navLogout,
                    onTap: (_) {
                      context.read<AuthProvider>().logout();
                      Navigator.pushNamedAndRemoveUntil(context, '/', (r) => false);
                    },
                  ),
                ],
              );
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: _isLoading
            ? const Center(child: GFLoader(type: GFLoaderType.android, size: GFSize.LARGE))
            : _error != null
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(_error!, style: TextStyle(color: theme.colorScheme.error)),
                        const SizedBox(height: 16),
                        GFButton(
                          onPressed: _loadData,
                          text: 'إعادة المحاولة',
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      ],
                    ),
                  )
                : SingleChildScrollView(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Search Bar
                        Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: SearchBar(
                            hintText: t.heroTitle,
                            leading: Icon(Icons.search, color: theme.colorScheme.outline),
                            padding: const MaterialStatePropertyAll(EdgeInsets.symmetric(horizontal: 16, vertical: 12)),
                            elevation: const MaterialStatePropertyAll(0),
                            backgroundColor: WidgetStatePropertyAll(theme.cardColor),
                            shape: WidgetStatePropertyAll(RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                          ),
                        ),

                        // Hero Banner / Carousel
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

                        // Categories
                        _buildSectionHeader(t.navCategories, '/categories'),
                        SizedBox(
                          height: 110,
                          child: ListView.builder(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: _categories.length,
                            itemBuilder: (context, i) {
                              final cat = _categories[i];
                              return Padding(
                                padding: const EdgeInsets.only(left: 16),
                                child: SizedBox(
                                  width: 80,
                                  child: InkWell(
                                    onTap: () => Navigator.pushNamed(
                                      context,
                                      '/category/${cat.id}',
                                      arguments: {'title': cat.subjectTitle},
                                    ),
                                    borderRadius: BorderRadius.circular(16),
                                    child: Column(
                                      mainAxisSize: MainAxisSize.max,
                                      children: [
                                        Container(
                                          width: 56,
                                          height: 56,
                                          decoration: BoxDecoration(
                                            color: _getCategoryColor(cat.deweyCode),
                                            borderRadius: BorderRadius.circular(14),
                                          ),
                                          child: Center(
                                            child: Text(
                                              _getCategoryIcon(cat.deweyCode),
                                              style: const TextStyle(fontSize: 26),
                                            ),
                                          ),
                                        ),
                                        const SizedBox(height: 6),
                                        Flexible(
                                          child: Text(
                                            cat.subjectTitle ?? '',
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

                        // Authors
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

                        // Newest Books
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
                                    onTap: () => Navigator.pushNamed(context, '/book/${newestBooks[i].id}', arguments: newestBooks[i]),
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
