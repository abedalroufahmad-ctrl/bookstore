import 'package:flutter/material.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';

class AdminSettingsScreen extends StatefulWidget {
  const AdminSettingsScreen({super.key});

  @override
  State<AdminSettingsScreen> createState() => _AdminSettingsScreenState();
}

class _AdminSettingsScreenState extends State<AdminSettingsScreen> {
  final _formKey = GlobalKey<FormState>();
  double _globalDiscount = 0;
  bool _isLoading = true;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() => _isLoading = true);
    final res = await ApiService.instance.getSettings();
    if (!mounted) return;
    setState(() {
      _isLoading = false;
      if (res.success && res.data != null) {
        _globalDiscount = (res.data!['global_discount'] ?? 0).toDouble();
      }
    });
  }

  Future<void> _save() async {
    if (_formKey.currentState?.validate() != true) return;
    _formKey.currentState?.save();

    setState(() => _isSaving = true);
    final t = AppLocalizations.of(context);
    final res = await ApiService.instance.adminUpdateSettings({'global_discount': _globalDiscount});

    if (!mounted) return;
    setState(() => _isSaving = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(res.success ? t.adminSettingsSaved : res.message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(t.adminSettingsTitle)),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Padding(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t.adminGlobalSettingsHeading,
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      initialValue: _globalDiscount.toString(),
                      decoration: InputDecoration(
                        labelText: t.adminGlobalDiscount,
                        border: const OutlineInputBorder(),
                        helperText: t.adminGlobalDiscountHint,
                      ),
                      keyboardType: TextInputType.number,
                      validator: (value) {
                        if (value == null || value.isEmpty) return t.fieldRequired;
                        final val = double.tryParse(value);
                        if (val == null) return t.invalidNumber;
                        if (val < 0 || val > 100) return t.mustBeBetween0And100;
                        return null;
                      },
                      onSaved: (value) => _globalDiscount = double.tryParse(value ?? '') ?? 0,
                    ),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _isSaving ? null : _save,
                        child: _isSaving
                            ? const CircularProgressIndicator(color: Colors.white)
                            : Text(t.saveChanges),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
