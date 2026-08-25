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
    final res = await ApiService.instance.adminCategoriesList();
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _categories = res.data!;
      } else {
        _error = res.message;
      }
    });
  }

  Future<void> _showForm({Category? category}) async {
    final t = AppLocalizations.of(context);
    final deweyCtrl = TextEditingController(text: category?.deweyCode ?? '');
    final enCtrl = TextEditingController(text: category?.subjectTitleEn ?? '');
    final arCtrl = TextEditingController(text: category?.subjectTitleAr ?? '');

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(category == null ? t.adminAddCategory : (t.isAr ? 'تعديل تصنيف' : 'Edit category')),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: deweyCtrl,
                decoration: InputDecoration(labelText: t.adminDeweyCode),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: enCtrl,
                decoration: InputDecoration(
                  labelText: '${t.adminSubjectTitle} (EN)',
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: arCtrl,
                decoration: InputDecoration(
                  labelText: '${t.adminSubjectTitle} (AR)',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(t.cancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(t.adminSave),
          ),
        ],
      ),
    );

    final dewey = deweyCtrl.text.trim();
    final en = enCtrl.text.trim();
    final ar = arCtrl.text.trim();
    deweyCtrl.dispose();
    enCtrl.dispose();
    arCtrl.dispose();

    if (ok != true || !mounted) return;
    if (dewey.isEmpty || en.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.fieldRequired)),
      );
      return;
    }

    if (category == null) {
      final createRes =
          await ApiService.instance.adminCategoriesCreate(
            deweyCode: dewey,
            subjectTitleEn: en,
            subjectTitleAr: ar.isEmpty ? null : ar,
          );
      if (!mounted) return;
      if (!createRes.success || createRes.data == null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              createRes.message.isNotEmpty
                  ? createRes.message
                  : t.adminFailedSave,
            ),
          ),
        );
        return;
      }
      if (ar.isNotEmpty) {
        await ApiService.instance.adminCategoriesUpdate(
          createRes.data!.id,
          {
            'dewey_code': dewey,
            'subject_title_en': en,
            'subject_title_ar': ar,
          },
        );
      }
    } else {
      final res = await ApiService.instance.adminCategoriesUpdate(
        category.id,
        {
          'dewey_code': dewey,
          'subject_title_en': en,
          'subject_title_ar': ar,
        },
      );
      if (!mounted) return;
      if (!res.success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              res.message.isNotEmpty ? res.message : t.adminFailedSave,
            ),
          ),
        );
        return;
      }
    }
    if (mounted) _load();
  }

  Future<void> _confirmDelete(Category category) async {
    final t = AppLocalizations.of(context);
    final label = category.subjectTitleEn ??
        category.subjectTitleAr ??
        category.deweyCode ??
        category.id;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(t.delete),
        content: Text(t.deleteNamed(label)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(t.cancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(t.delete),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final res = await ApiService.instance.adminCategoriesDelete(category.id);
    if (!mounted) return;
    if (res.success) {
      _load();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message.isNotEmpty ? res.message : t.error)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.categoriesTitle),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () => _showForm(),
          ),
        ],
      ),
      body: _loading
          ? Center(child: Text(t.loading))
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!.isNotEmpty ? _error! : t.error),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _load, child: Text(t.retry)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _categories.isEmpty
                      ? ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            SizedBox(
                              height: MediaQuery.of(context).size.height * 0.4,
                              child: Center(child: Text(t.adminNoItems)),
                            ),
                          ],
                        )
                      : ListView.builder(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.all(16),
                          itemCount: _categories.length,
                          itemBuilder: (context, i) {
                            final c = _categories[i];
                            final title = c.subjectTitleEn ??
                                c.subjectTitleAr ??
                                c.deweyCode ??
                                c.id;
                            final subtitle = [
                              c.deweyCode,
                              if (c.subjectTitleAr != null &&
                                  c.subjectTitleAr != title)
                                c.subjectTitleAr,
                            ].whereType<String>().where((s) => s.isNotEmpty).join(' · ');
                            return Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                title: Text(title),
                                subtitle:
                                    subtitle.isEmpty ? null : Text(subtitle),
                                onTap: () => _showForm(category: c),
                                trailing: IconButton(
                                  icon: const Icon(Icons.delete_outline),
                                  onPressed: () => _confirmDelete(c),
                                ),
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
