import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';

class AdminSettingsScreen extends StatefulWidget {
  const AdminSettingsScreen({super.key});

  @override
  State<AdminSettingsScreen> createState() => _AdminSettingsScreenState();
}

class _AdminSettingsScreenState extends State<AdminSettingsScreen> {
  final _discountCtrl = TextEditingController();
  final _perPageCtrl = TextEditingController();
  bool _loading = true;
  bool _saving = false;
  String? _error;
  Map<String, dynamic> _settings = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _discountCtrl.dispose();
    _perPageCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await ApiService.instance.adminGetSettings();
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (res.success && res.data != null) {
        _settings = Map<String, dynamic>.from(res.data!);
        _discountCtrl.text = '${_settings['global_discount'] ?? ''}';
        _perPageCtrl.text = '${_settings['catalog_items_per_page'] ?? ''}';
      } else {
        _error = res.message;
      }
    });
  }

  Future<void> _save() async {
    final t = AppLocalizations.of(context);
    final discountRaw = _discountCtrl.text.trim().replaceAll(',', '.');
    final discount = double.tryParse(discountRaw);
    if (discount == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.invalidNumber)),
      );
      return;
    }
    if (discount < 0 || discount > 100) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.mustBeBetween0And100)),
      );
      return;
    }

    final body = <String, dynamic>{
      ..._settings,
      'global_discount': discount,
    };

    final perPageRaw = _perPageCtrl.text.trim();
    if (perPageRaw.isNotEmpty) {
      final perPage = int.tryParse(perPageRaw);
      if (perPage == null || perPage < 1) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(t.invalidNumber)),
        );
        return;
      }
      body['catalog_items_per_page'] = perPage;
    }

    setState(() => _saving = true);
    final res = await ApiService.instance.adminUpdateSettings(body);
    if (!mounted) return;
    setState(() => _saving = false);

    if (res.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.adminSettingsSaved)),
      );
      _load();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res.message.isNotEmpty ? res.message : t.adminFailedSave),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminSettingsTitle),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
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
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      t.adminGlobalSettingsHeading,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _discountCtrl,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: InputDecoration(
                        labelText: t.adminGlobalDiscount,
                        helperText: t.adminGlobalDiscountHint,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _perPageCtrl,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(
                        labelText: t.adminCatalogItemsPerPage,
                      ),
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _saving ? null : _save,
                      child: _saving
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(t.adminSave),
                    ),
                  ],
                ),
    );
  }
}
