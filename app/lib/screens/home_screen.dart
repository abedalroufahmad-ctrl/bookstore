import 'package:carousel_slider/carousel_slider.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

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
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final results = await Future.wait([
        ApiService.instance.getBooks(),
        ApiService.instance.getCategories(),
        ApiService.instance.getAuthors(),
      ]);

      final booksRes = results[0] as ApiResponse<dynamic>;
      final categoriesRes = results[1] as ApiResponse<List<Category>>;
      final authorsRes = results[2] as ApiResponse<List<Author>>;

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

      setState(() => _loading = false);
    } catch (e) {
      setState(() {
        _loading = false;
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
    final featuredBooks = _books.take(5).toList();
    final newestBooks = _books.reversed.take(10).toList();

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: const Text('متجر الكتب'),
        actions: [
          IconButton(
            icon: const Icon(Icons.shopping_cart_outlined),
            onPressed: () => Navigator.pushNamed(context, '/cart'),
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.account_circle_outlined),
            onSelected: (value) {
              if (value == 'logout') {
                context.read<AuthProvider>().logout();
                Navigator.pushNamedAndRemoveUntil(context, '/login', (r) => false);
              } else if (value == 'admin') {
                Navigator.pushNamed(context, '/admin');
              } else if (value == 'orders') {
                Navigator.pushNamed(context, '/orders');
              }
            },
            itemBuilder: (context) {
              final auth = context.watch<AuthProvider>();
              return [
                if (auth.userType == UserType.employee)
                  const PopupMenuItem(value: 'admin', child: Text('لوحة الإدارة')),
                const PopupMenuItem(value: 'orders', child: Text('طلباتي')),
                const PopupMenuItem(value: 'logout', child: Text('تسجيل الخروج')),
              ];
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadData,
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(_error!, style: const TextStyle(color: Colors.red)),
                        const SizedBox(height: 16),
                        ElevatedButton(onPressed: _loadData, child: const Text('إعادة المحاولة')),
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
                            hintText: 'ابحث عن الكتب، المؤلفين...',
                            leading: const Icon(Icons.search),
                            padding: const MaterialStatePropertyAll(EdgeInsets.symmetric(horizontal: 16)),
                            elevation: const MaterialStatePropertyAll(1),
                            backgroundColor: MaterialStatePropertyAll(Colors.white),
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
                                discountPercent: 20,
                                onTap: () => Navigator.pushNamed(context, '/book/${book.id}', arguments: book),
                              );
                            }).toList(),
                          ),
                        ],

                        // Categories
                        _buildSectionHeader('التصنيفات', '/categories'),
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
                                child: InkWell(
                                  onTap: () => Navigator.pushNamed(
                                    context,
                                    '/category/${cat.id}',
                                    arguments: {'title': cat.subjectTitle},
                                  ),
                                  borderRadius: BorderRadius.circular(16),
                                  child: Column(
                                    children: [
                                      Container(
                                        width: 60,
                                        height: 60,
                                        decoration: BoxDecoration(
                                          color: _getCategoryColor(cat.deweyCode),
                                          borderRadius: BorderRadius.circular(16),
                                        ),
                                        child: Center(
                                          child: Text(
                                            _getCategoryIcon(cat.deweyCode),
                                            style: const TextStyle(fontSize: 28),
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        cat.subjectTitle ?? '',
                                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                        ),

                        // Authors
                        _buildSectionHeader('المؤلفون', '/authors'),
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
                                child: InkWell(
                                  onTap: () => Navigator.pushNamed(
                                    context,
                                    '/author/${author.id}',
                                    arguments: {'name': author.name},
                                  ),
                                  borderRadius: BorderRadius.circular(30),
                                  child: Column(
                                    children: [
                                      CircleAvatar(
                                        radius: 30,
                                        backgroundColor: theme.colorScheme.primaryContainer,
                                        child: Text(
                                          _getInitials(author.name),
                                          style: TextStyle(
                                            color: theme.colorScheme.onPrimaryContainer,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      Text(
                                        author.name ?? '',
                                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                                      ),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                        ),

                        // Newest Books
                        _buildSectionHeader('أحدث الكتب', '/books'),
                        SizedBox(
                          height: 340,
                          child: ListView.builder(
                            scrollDirection: Axis.horizontal,
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: newestBooks.length,
                            itemBuilder: (context, i) {
                              return BookCard(
                                book: newestBooks[i],
                                onTap: () => Navigator.pushNamed(context, '/book/${newestBooks[i].id}', arguments: newestBooks[i]),
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
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          TextButton(
            onPressed: () => Navigator.pushNamed(context, route),
            child: const Row(
              children: [
                Text('عرض الكل'),
                Icon(Icons.chevron_right, size: 20),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
