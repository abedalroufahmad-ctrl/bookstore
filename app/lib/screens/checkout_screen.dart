import 'package:flutter/material.dart';
import 'package:getwidget/getwidget.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';
import '../providers/profile_provider.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();
  final _addressController = TextEditingController();
  final _cityController = TextEditingController();
  final _countryController = TextEditingController();
  final _postalController = TextEditingController();
  bool _loading = false;
  String? _error;
  bool _profilePrefilled = false;
  bool _profileFetchedFromApi = false;
  List<Map<String, dynamic>> _paymentMethods = [];
  String _selectedPaymentMethod = 'cod';

  @override
  void initState() {
    super.initState();
    _loadPaymentMethods();
  }

  Future<void> _loadProfileFromApi(BuildContext context) async {
    if (_profileFetchedFromApi || !context.mounted) return;
    _profileFetchedFromApi = true;
    final res = await ApiService.instance.customerMe();
    if (!context.mounted) return;
    if (res.success && res.data != null) {
      await context.read<ProfileProvider>().loadFromCustomer(res.data);
      if (!mounted) return;
      setState(() => _profilePrefilled = false);
    }
  }

  void _prefillFromProfile(BuildContext context) {
    if (_profilePrefilled || !context.mounted) return;
    _profilePrefilled = true;
    final p = context.read<ProfileProvider>();
    if (p.address != null && p.address!.isNotEmpty) _addressController.text = p.address!;
    if (p.city != null && p.city!.isNotEmpty) _cityController.text = p.city!;
    if (p.country != null && p.country!.isNotEmpty) _countryController.text = p.country!;
    if (p.postalCode != null && p.postalCode!.isNotEmpty) _postalController.text = p.postalCode!;
  }

  Future<void> _loadPaymentMethods() async {
    final res = await ApiService.instance.getSettings();
    if (!mounted) return;
    if (res.success && res.data != null) {
      final list = res.data!['payment_methods'];
      if (list is List) {
        final methods = <Map<String, dynamic>>[];
        for (final e in list) {
          if (e is Map && e['enabled'] == true && e['id'] != null) {
            methods.add(Map<String, dynamic>.from(e));
          }
        }
        setState(() {
          _paymentMethods = methods;
          if (methods.isNotEmpty && !methods.any((m) => m['id'] == _selectedPaymentMethod)) {
            _selectedPaymentMethod = methods.first['id'] as String;
          }
        });
      }
    }
  }

  @override
  void dispose() {
    _addressController.dispose();
    _cityController.dispose();
    _countryController.dispose();
    _postalController.dispose();
    super.dispose();
  }

  Future<void> _checkout() async {
    if (_formKey.currentState?.validate() != true) return;
    if (!mounted) return;
    setState(() {
      _error = null;
      _loading = true;
    });
    final shipping = {
      'address': _addressController.text.trim(),
      'city': _cityController.text.trim(),
      'country': _countryController.text.trim(),
      'postal_code': _postalController.text.trim(),
    };
    final res = await ApiService.instance.checkout(
      shipping,
      paymentMethod: _selectedPaymentMethod,
    );
    if (!mounted) return;
    setState(() => _loading = false);
    final ordersRaw = res.data?['orders'];
    if (res.success &&
        ordersRaw is List &&
        ordersRaw.isNotEmpty) {
      Navigator.pushNamedAndRemoveUntil(
        context,
        '/orders',
        (r) => r.settings.name == '/',
      );
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppLocalizations.of(context).orderPlacedSuccess)),
      );
    } else {
      if (mounted) setState(() => _error = res.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_profileFetchedFromApi) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _loadProfileFromApi(context));
    }
    if (!_profilePrefilled) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _prefillFromProfile(context));
    }
    final auth = context.watch<AuthProvider>();
    if (auth.userType != UserType.customer) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        Navigator.pushReplacementNamed(context, '/login');
      });
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(t.checkoutTitle)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(t.shippingAddress, style: const TextStyle(fontSize: 18)),
              const SizedBox(height: 16),
              TextFormField(
                controller: _addressController,
                decoration: InputDecoration(labelText: t.addressLabel),
                validator: (v) =>
                    v == null || v.isEmpty ? t.addressRequired : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _cityController,
                decoration: InputDecoration(labelText: t.cityLabel),
                validator: (v) =>
                    v == null || v.isEmpty ? t.cityRequired : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _countryController,
                decoration: InputDecoration(labelText: t.countryLabel),
                validator: (v) =>
                    v == null || v.isEmpty ? t.countryRequired : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _postalController,
                decoration: InputDecoration(labelText: t.postalCodeLabel),
                keyboardType: TextInputType.streetAddress,
              ),
              if (_paymentMethods.isNotEmpty) ...[
                const SizedBox(height: 20),
                Text(t.paymentMethodLabel, style: const TextStyle(fontSize: 18)),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  key: ValueKey(
                    '${_paymentMethods.map((m) => m['id']).join('|')}|$_selectedPaymentMethod',
                  ),
                  initialValue: _paymentMethods.any((m) => m['id'] == _selectedPaymentMethod)
                      ? _selectedPaymentMethod
                      : _paymentMethods.first['id'] as String,
                  decoration: const InputDecoration(
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                  items: _paymentMethods
                      .map((m) {
                        final id = m['id'] as String;
                        final name = m['name'] as String? ?? id;
                        return DropdownMenuItem<String>(
                          value: id,
                          child: Text(t.paymentMethodDisplayName(id, name)),
                        );
                      })
                      .toList(),
                  onChanged: (v) => setState(() => _selectedPaymentMethod = v ?? _selectedPaymentMethod),
                ),
              ] else if (_paymentMethods.isEmpty && !_loading) ...[
                const SizedBox(height: 12),
                Text(
                  t.noPaymentMethodsAvailable,
                  style: TextStyle(color: Theme.of(context).colorScheme.error, fontSize: 13),
                ),
              ],
              if ((_error ?? '').isNotEmpty) ...[
                const SizedBox(height: 16),
                Text(
                  _error ?? '',
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 24),
              GFButton(
                onPressed: (_loading || _paymentMethods.isEmpty)
                    ? null
                    : () {
                        if (_formKey.currentState?.validate() == true) _checkout();
                      },
                fullWidthButton: true,
                size: GFSize.LARGE,
                color: Theme.of(context).colorScheme.primary,
                child: _loading
                    ? const SizedBox(
                        height: 24,
                        width: 24,
                        child: GFLoader(type: GFLoaderType.android, size: GFSize.SMALL),
                      )
                    : Text(AppLocalizations.of(context).placeOrder),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
