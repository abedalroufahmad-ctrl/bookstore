import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../providers/auth_provider.dart';

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
  List<Map<String, dynamic>> _paymentMethods = [];
  String _selectedPaymentMethod = 'cod';

  @override
  void initState() {
    super.initState();
    _loadPaymentMethods();
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
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _error = null;
      _loading = true;
    });
    final res = await ApiService.instance.checkout(
      {
        'address': _addressController.text.trim(),
        'city': _cityController.text.trim(),
        'country': _countryController.text.trim(),
        'postal_code': _postalController.text.trim(),
      },
      paymentMethod: _selectedPaymentMethod,
    );
    setState(() => _loading = false);
    if (!mounted) return;
    if (res.success && res.data != null) {
      Navigator.pushNamedAndRemoveUntil(
        context,
        '/orders',
        (r) => r.settings.name == '/',
      );
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order placed successfully')),
      );
    } else {
      setState(() => _error = res.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (auth.userType != UserType.customer) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        Navigator.pushReplacementNamed(context, '/login');
      });
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Shipping address', style: TextStyle(fontSize: 18)),
              const SizedBox(height: 16),
              TextFormField(
                controller: _addressController,
                decoration: const InputDecoration(labelText: 'Address'),
                validator: (v) =>
                    v == null || v.isEmpty ? 'Address required' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _cityController,
                decoration: const InputDecoration(labelText: 'City'),
                validator: (v) =>
                    v == null || v.isEmpty ? 'City required' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _countryController,
                decoration: const InputDecoration(labelText: 'Country'),
                validator: (v) =>
                    v == null || v.isEmpty ? 'Country required' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _postalController,
                decoration: const InputDecoration(labelText: 'Postal code'),
                keyboardType: TextInputType.streetAddress,
              ),
              if (_paymentMethods.isNotEmpty) ...[
                const SizedBox(height: 20),
                const Text('Payment method', style: TextStyle(fontSize: 18)),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  value: _paymentMethods.any((m) => m['id'] == _selectedPaymentMethod)
                      ? _selectedPaymentMethod
                      : _paymentMethods.first['id'] as String,
                  decoration: const InputDecoration(
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  ),
                  items: _paymentMethods
                      .map((m) => DropdownMenuItem<String>(
                            value: m['id'] as String,
                            child: Text(m['name'] as String? ?? m['id'] as String),
                          ))
                      .toList(),
                  onChanged: (v) => setState(() => _selectedPaymentMethod = v!),
                ),
              ] else if (_paymentMethods.isEmpty && !_loading) ...[
                const SizedBox(height: 12),
                Text(
                  'No payment method available. Please try again later.',
                  style: TextStyle(color: Theme.of(context).colorScheme.error, fontSize: 13),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 16),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 24),
              FilledButton(
                onPressed: (_loading || _paymentMethods.isEmpty)
                    ? null
                    : () {
                        if (_formKey.currentState!.validate()) _checkout();
                      },
                child: _loading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Place order'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
