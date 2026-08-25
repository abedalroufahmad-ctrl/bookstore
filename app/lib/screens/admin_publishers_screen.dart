import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';

class AdminPublishersScreen extends StatefulWidget {
  const AdminPublishersScreen({super.key});

  @override
  State<AdminPublishersScreen> createState() => _AdminPublishersScreenState();
}

class _AdminPublishersScreenState extends State<AdminPublishersScreen> {
  List<Map<String, dynamic>> _rows = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  String _idOf(Map<String, dynamic> m) =>
      (m['_id'] ?? m['id'] ?? '').toString();

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await ApiService.instance.adminPublishersList();
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _rows = res.data!;
      } else {
        _error = res.message;
      }
    });
  }

  Future<void> _showForm({Map<String, dynamic>? row}) async {
    final t = AppLocalizations.of(context);
    final nameCtrl = TextEditingController(text: row?['name']?.toString() ?? '');
    final emailCtrl =
        TextEditingController(text: row?['email']?.toString() ?? '');
    final phoneCtrl =
        TextEditingController(text: row?['phone']?.toString() ?? '');

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(
          row == null
              ? (t.isAr ? 'إضافة ناشر' : 'Add publisher')
              : (t.isAr ? 'تعديل ناشر' : 'Edit publisher'),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameCtrl,
              decoration: InputDecoration(labelText: t.nameLabel),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: emailCtrl,
              decoration: InputDecoration(labelText: t.emailLabel),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: phoneCtrl,
              decoration: InputDecoration(labelText: t.phoneLabel),
            ),
          ],
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

    final body = <String, dynamic>{
      'name': nameCtrl.text.trim(),
      if (emailCtrl.text.trim().isNotEmpty) 'email': emailCtrl.text.trim(),
      if (phoneCtrl.text.trim().isNotEmpty) 'phone': phoneCtrl.text.trim(),
    };
    nameCtrl.dispose();
    emailCtrl.dispose();
    phoneCtrl.dispose();

    if (ok != true || !mounted) return;
    if ((body['name'] as String).isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.fieldRequired)),
      );
      return;
    }

    final res = row == null
        ? await ApiService.instance.adminPublishersCreate(body)
        : await ApiService.instance.adminPublishersUpdate(_idOf(row), body);
    if (!mounted) return;
    if (res.success) {
      _load();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res.message.isNotEmpty ? res.message : t.adminFailedSave),
        ),
      );
    }
  }

  Future<void> _confirmDelete(Map<String, dynamic> row) async {
    final t = AppLocalizations.of(context);
    final name = row['name']?.toString() ?? _idOf(row);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(t.delete),
        content: Text(t.deleteNamed(name)),
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
    final res = await ApiService.instance.adminPublishersDelete(_idOf(row));
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
        title: Text(t.adminPublishers),
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
                  child: _rows.isEmpty
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
                          itemCount: _rows.length,
                          itemBuilder: (context, i) {
                            final p = _rows[i];
                            final subtitle = [
                              p['email']?.toString(),
                              p['phone']?.toString(),
                            ]
                                .whereType<String>()
                                .where((s) => s.isNotEmpty)
                                .join(' · ');
                            return Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                title: Text(p['name']?.toString() ?? _idOf(p)),
                                subtitle:
                                    subtitle.isEmpty ? null : Text(subtitle),
                                onTap: () => _showForm(row: p),
                                trailing: IconButton(
                                  icon: const Icon(Icons.delete_outline),
                                  onPressed: () => _confirmDelete(p),
                                ),
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
