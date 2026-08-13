import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';

class AdminAuthorsScreen extends StatefulWidget {
  const AdminAuthorsScreen({super.key});

  @override
  State<AdminAuthorsScreen> createState() => _AdminAuthorsScreenState();
}

class _AdminAuthorsScreenState extends State<AdminAuthorsScreen> {
  List<Author> _authors = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _loading = true);
    final res = await ApiService.instance.adminAuthorsList();
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) _authors = res.data!;
    });
  }

  Future<void> _addAuthor() async {
    final t = AppLocalizations.of(context);
    final name = await showDialog<String>(
      context: context,
      builder: (ctx) {
        final c = TextEditingController();
        return AlertDialog(
          title: Text(t.adminAddAuthor),
          content: TextField(
            controller: c,
            decoration: InputDecoration(labelText: t.nameLabel),
            autofocus: true,
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text(t.cancel),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, c.text.trim()),
              child: Text(t.add),
            ),
          ],
        );
      },
    );
    if (name != null && name.isNotEmpty) {
      await ApiService.instance.adminAuthorsCreate(name);
      _load();
    }
  }

  Future<void> _delete(Author a) async {
    if (!context.mounted) return;
    final t = AppLocalizations.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(t.adminDeleteAuthor),
        content: Text(t.deleteNamed(a.name ?? '')),
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
    if (ok == true) {
      await ApiService.instance.adminAuthorsDelete(a.id);
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminAuthors),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _authors.length,
              itemBuilder: (context, i) {
                final a = _authors[i];
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(a.name ?? ''),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete, color: Colors.red),
                      onPressed: () => _delete(a),
                    ),
                  ),
                );
              },
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _addAuthor,
        child: const Icon(Icons.add),
      ),
    );
  }
}
