import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';

class AdminWarehousesScreen extends StatefulWidget {
  const AdminWarehousesScreen({super.key});

  @override
  State<AdminWarehousesScreen> createState() => _AdminWarehousesScreenState();
}

class _AdminWarehousesScreenState extends State<AdminWarehousesScreen> {
  List<Map<String, dynamic>> _rows = [];
  List<Map<String, dynamic>> _publishers = [];
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
    final whF = ApiService.instance.adminWarehousesList();
    final pubsF = ApiService.instance.adminPublishersList();
    final wh = await whF;
    final pubs = await pubsF;
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (wh.success && wh.data != null) {
        _rows = wh.data!;
      } else {
        _error = wh.message;
      }
      if (pubs.success && pubs.data != null) {
        _publishers = pubs.data!;
      }
    });
  }

  Future<void> _showForm({Map<String, dynamic>? row}) async {
    final t = AppLocalizations.of(context);
    final nameCtrl = TextEditingController(text: row?['name']?.toString() ?? '');
    final addressCtrl =
        TextEditingController(text: row?['address']?.toString() ?? '');
    final cityCtrl = TextEditingController(text: row?['city']?.toString() ?? '');
    final countryCtrl =
        TextEditingController(text: row?['country']?.toString() ?? '');
    final emailCtrl =
        TextEditingController(text: row?['email']?.toString() ?? '');
    String? publisherId = row?['publisher_id']?.toString();
    if (publisherId == null || publisherId.isEmpty) {
      final pub = row?['publisher'];
      if (pub is Map) {
        publisherId = (pub['_id'] ?? pub['id'])?.toString();
      }
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setDialog) {
            return AlertDialog(
              title: Text(
                row == null
                    ? (t.isAr ? 'إضافة مستودع' : 'Add warehouse')
                    : (t.isAr ? 'تعديل مستودع' : 'Edit warehouse'),
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      controller: nameCtrl,
                      decoration: InputDecoration(labelText: t.nameLabel),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: addressCtrl,
                      decoration: InputDecoration(labelText: t.addressLabel),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: cityCtrl,
                      decoration: InputDecoration(labelText: t.cityLabel),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: countryCtrl,
                      decoration: InputDecoration(labelText: t.countryLabel),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: emailCtrl,
                      decoration: InputDecoration(labelText: t.emailLabel),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String?>(
                      value: publisherId,
                      decoration:
                          InputDecoration(labelText: t.adminSelectPublisher),
                      items: [
                        DropdownMenuItem<String?>(
                          value: null,
                          child: Text(t.adminSelectPublisher),
                        ),
                        ..._publishers.map((p) {
                          final id = _idOf(p);
                          return DropdownMenuItem(
                            value: id,
                            child: Text(p['name']?.toString() ?? id),
                          );
                        }),
                      ],
                      onChanged: (v) => setDialog(() => publisherId = v),
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
            );
          },
        );
      },
    );

    final body = <String, dynamic>{
      'name': nameCtrl.text.trim(),
      'address': addressCtrl.text.trim(),
      'city': cityCtrl.text.trim(),
      'country': countryCtrl.text.trim(),
      'email': emailCtrl.text.trim(),
      if (publisherId != null && publisherId!.isNotEmpty)
        'publisher_id': publisherId,
    };
    nameCtrl.dispose();
    addressCtrl.dispose();
    cityCtrl.dispose();
    countryCtrl.dispose();
    emailCtrl.dispose();

    if (ok != true || !mounted) return;
    if ((body['name'] as String).isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.fieldRequired)),
      );
      return;
    }

    final res = row == null
        ? await ApiService.instance.adminWarehousesCreate(body)
        : await ApiService.instance.adminWarehousesUpdate(_idOf(row), body);
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
    final res = await ApiService.instance.adminWarehousesDelete(_idOf(row));
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
        title: Text(t.warehousesTitle),
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
                            final w = _rows[i];
                            final subtitle = [
                              w['city']?.toString(),
                              w['country']?.toString(),
                              w['email']?.toString(),
                            ]
                                .whereType<String>()
                                .where((s) => s.isNotEmpty)
                                .join(' · ');
                            return Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                title: Text(w['name']?.toString() ?? _idOf(w)),
                                subtitle:
                                    subtitle.isEmpty ? null : Text(subtitle),
                                onTap: () => _showForm(row: w),
                                trailing: IconButton(
                                  icon: const Icon(Icons.delete_outline),
                                  onPressed: () => _confirmDelete(w),
                                ),
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
