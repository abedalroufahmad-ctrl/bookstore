import 'package:flutter/material.dart';
import '../api/api_service.dart';
import '../models/book.dart';

class CategoryListScreen extends StatefulWidget {
  const CategoryListScreen({super.key});

  @override
  State<CategoryListScreen> createState() => _CategoryListScreenState();
}

class _CategoryListScreenState extends State<CategoryListScreen> {
  List<Category> _categories = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await ApiService.instance.getCategories();
    setState(() {
      _loading = false;
      if (res.success) {
        _categories = res.data ?? [];
      } else {
        _error = res.message;
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
    return Scaffold(
      appBar: AppBar(title: const Text('التصنيفات')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : GridView.builder(
                  padding: const EdgeInsets.all(16),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 16,
                    crossAxisSpacing: 16,
                    childAspectRatio: 1.2,
                  ),
                  itemCount: _categories.length,
                  itemBuilder: (context, i) {
                    final cat = _categories[i];
                    return InkWell(
                      onTap: () {
                        Navigator.pushNamed(
                          context,
                          '/category/${cat.id}',
                          arguments: {'title': cat.subjectTitle},
                        );
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.grey[200]!),
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
                            Text(
                              cat.subjectTitle ?? '',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}
