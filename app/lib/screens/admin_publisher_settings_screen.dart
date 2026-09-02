import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';

class AdminPublisherSettingsScreen extends StatefulWidget {
  const AdminPublisherSettingsScreen({super.key});

  @override
  State<AdminPublisherSettingsScreen> createState() =>
      _AdminPublisherSettingsScreenState();
}

class _AdminPublisherSettingsScreenState
    extends State<AdminPublisherSettingsScreen> {
  final _supportEmailCtrl = TextEditingController();
  final _supportPhoneCtrl = TextEditingController();
  final _returnPolicyCtrl = TextEditingController();
  final _discountCtrl = TextEditingController();
  final _paypalEmailCtrl = TextEditingController();
  final _paypalMerchantCtrl = TextEditingController();
  final _bankNameCtrl = TextEditingController();
  final _bankAccountCtrl = TextEditingController();
  final _commissionCtrl = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  bool _didInit = false;
  bool _isManager = false;
  String? _error;
  String? _publisherId;
  List<Map<String, dynamic>> _globalPaymentMethods = [];
  List<String> _selectedPaymentMethods = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didInit) return;
    _didInit = true;
    final args = ModalRoute.of(context)?.settings.arguments;
    final employee = context.read<AuthProvider>().employee;
    _isManager = employee?.role == 'manager';
    final fromArgs = args is String && args.isNotEmpty ? args : null;
    _publisherId = fromArgs ?? employee?.publisherId;
    _load();
  }

  @override
  void dispose() {
    _supportEmailCtrl.dispose();
    _supportPhoneCtrl.dispose();
    _returnPolicyCtrl.dispose();
    _discountCtrl.dispose();
    _paypalEmailCtrl.dispose();
    _paypalMerchantCtrl.dispose();
    _bankNameCtrl.dispose();
    _bankAccountCtrl.dispose();
    _commissionCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final id = _publisherId;
    if (id == null || id.isEmpty) {
      setState(() {
        _loading = false;
        _error = AppLocalizations.of(context).adminNoPublisherSelected;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    final settingsRes = await ApiService.instance.adminPublisherSettingsGet(id);
    final globalRes = await ApiService.instance.adminGetSettings();
    if (!mounted) return;
    if (!settingsRes.success || settingsRes.data == null) {
      setState(() {
        _loading = false;
        _error = settingsRes.message;
      });
      return;
    }
    final data = settingsRes.data!;
    _supportEmailCtrl.text = '${data['support_email'] ?? ''}';
    _supportPhoneCtrl.text = '${data['support_phone'] ?? ''}';
    _returnPolicyCtrl.text = '${data['return_policy'] ?? ''}';
    _discountCtrl.text = '${data['default_discount'] ?? 0}';
    _paypalEmailCtrl.text = '${data['paypal_email'] ?? ''}';
    _paypalMerchantCtrl.text = '${data['paypal_merchant_id'] ?? ''}';
    _bankNameCtrl.text = '${data['bank_name'] ?? ''}';
    _bankAccountCtrl.text = '${data['bank_account_number'] ?? ''}';
    _commissionCtrl.text = '${data['platform_commission_percent'] ?? 0}';
    final methods = data['payment_methods'];
    _selectedPaymentMethods = methods is List
        ? methods.map((e) => e.toString()).toList()
        : <String>[];
    _globalPaymentMethods = _parsePaymentMethods(globalRes.data);
    setState(() => _loading = false);
  }

  List<Map<String, dynamic>> _parsePaymentMethods(Map<String, dynamic>? settings) {
    if (settings == null) return [];
    final raw = settings['payment_methods'];
    if (raw is List) {
      return raw.whereType<Map>().map((item) {
        return <String, dynamic>{
          'id': '${item['id'] ?? ''}',
          'name': '${item['name'] ?? item['id'] ?? ''}',
          'enabled': item['enabled'] == true,
        };
      }).toList();
    }
    return [];
  }

  Future<void> _save() async {
    final t = AppLocalizations.of(context);
    final id = _publisherId;
    if (id == null) return;

    final discount = double.tryParse(_discountCtrl.text.trim().replaceAll(',', '.'));
    if (discount == null || discount < 0 || discount > 100) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.mustBeBetween0And100)),
      );
      return;
    }
    final commissionRaw = _commissionCtrl.text.trim().replaceAll(',', '.');
    final commission = double.tryParse(commissionRaw);
    if (commission == null || commission < 0 || commission > 100) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.mustBeBetween0And100)),
      );
      return;
    }

    final body = <String, dynamic>{
      'support_email': _supportEmailCtrl.text.trim(),
      'support_phone': _supportPhoneCtrl.text.trim(),
      'return_policy': _returnPolicyCtrl.text.trim(),
      'default_discount': discount,
      'payment_methods': _selectedPaymentMethods,
      'paypal_email': _paypalEmailCtrl.text.trim(),
      'paypal_merchant_id': _paypalMerchantCtrl.text.trim(),
      'bank_name': _bankNameCtrl.text.trim(),
      'bank_account_number': _bankAccountCtrl.text.trim(),
    };
    if (_isManager) {
      body['platform_commission_percent'] = commission;
    }

    setState(() => _saving = true);
    final res = await ApiService.instance.adminPublisherSettingsUpdate(id, body);
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
        title: Text(t.adminPublisherSettings),
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
                    TextField(
                      controller: _supportEmailCtrl,
                      keyboardType: TextInputType.emailAddress,
                      decoration: InputDecoration(labelText: t.adminSupportEmail),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _supportPhoneCtrl,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(labelText: t.adminSupportPhone),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _discountCtrl,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(
                        labelText: t.adminDefaultDiscount,
                        helperText: t.adminDefaultDiscountHint,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _returnPolicyCtrl,
                      maxLines: 4,
                      decoration: InputDecoration(labelText: t.adminReturnPolicy),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      t.adminPayoutAccounts,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t.adminPayoutAccountsHint,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _paypalEmailCtrl,
                      keyboardType: TextInputType.emailAddress,
                      decoration: InputDecoration(labelText: t.adminPaypalEmail),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _paypalMerchantCtrl,
                      decoration: InputDecoration(
                        labelText: t.adminPaypalMerchantId,
                        helperText: t.adminPaypalMerchantIdHint,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _bankNameCtrl,
                      decoration: InputDecoration(labelText: t.adminBankName),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _bankAccountCtrl,
                      decoration: InputDecoration(labelText: t.adminBankAccountNumber),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _commissionCtrl,
                      enabled: _isManager,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(
                        labelText: t.adminPlatformCommissionPercent,
                        helperText: _isManager
                            ? t.adminPlatformCommissionPercentHint
                            : t.adminPlatformCommissionReadOnly,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      t.adminPaymentMethods,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    if (_globalPaymentMethods.where((m) => m['enabled'] == true).isEmpty)
                      Text(t.adminNoGlobalPaymentMethods)
                    else
                      ..._globalPaymentMethods.where((m) => m['enabled'] == true).map((m) {
                        final methodId = m['id'] as String;
                        return CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text('${m['name']}'),
                          value: _selectedPaymentMethods.contains(methodId),
                          onChanged: (checked) {
                            setState(() {
                              if (checked == true) {
                                _selectedPaymentMethods = [
                                  ..._selectedPaymentMethods,
                                  methodId,
                                ];
                              } else {
                                _selectedPaymentMethods = _selectedPaymentMethods
                                    .where((id) => id != methodId)
                                    .toList();
                              }
                            });
                          },
                        );
                      }),
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
