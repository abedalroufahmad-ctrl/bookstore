import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';

class AdminCategoriesScreen extends StatefulWidget {
  const AdminCategoriesScreen({super.key});

  @override
  State<AdminCategoriesScreen> createState() => _AdminCategoriesScreenState();
}

class _AdminCategoriesScreenState extends State<AdminCategoriesScreen> {
  List<Category> _categories = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = true);
    final res = await ApiService.instance.adminCategoriesList();
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) _categories = res.data!;
    });
  }

  Future<void> _addCategory() async {
    if (!context.mounted) return;
    final t = AppLocalizations.of(context);
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (ctx) {
        final codeC = TextEditingController();
        final titleC = TextEditingController();
        return AlertDialog(
          title: Text(t.adminAddCategory),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: codeC,
                decoration: InputDecoration(labelText: t.adminDeweyCode),
              ),
              TextField(
                controller: titleC,
                decoration: InputDecoration(labelText: t.adminSubjectTitle),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text(t.cancel),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, {
                'code': codeC.text.trim(),
                'title': titleC.text.trim(),
              }),
              child: Text(t.add),
            ),
          ],
        );
      },
    );
    final code = result?['code']?.toString().trim();
    final title = result?['title']?.toString().trim();
    if (code != null && code.isNotEmpty && title != null && title.isNotEmpty) {
      await ApiService.instance.adminCategoriesCreate(code, title);
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminCategories),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _categories.length,
              itemBuilder: (context, i) {
                final c = _categories[i];
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(c.subjectTitleEn ?? ''),
                    subtitle: Text(c.deweyCode ?? ''),
                  ),
                );
              },
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _addCategory,
        child: const Icon(Icons.add),
      ),
    );
  }
}
