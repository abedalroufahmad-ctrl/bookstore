import 'package:flutter/material.dart';
import 'package:getwidget/getwidget.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../providers/profile_provider.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!mounted) return;
    setState(() {
      _error = null;
      _loading = true;
    });
    final auth = context.read<AuthProvider>();
    final err = await auth.loginAsCustomer(_emailController.text, _passwordController.text);
    if (!mounted) return;
    setState(() => _loading = false);
    if (err != null) {
      if (mounted) setState(() => _error = err);
    } else {
      final customer = context.read<AuthProvider>().customer;
      if (customer != null) {
        await context.read<ProfileProvider>().loadFromCustomer(customer);
      }
      if (!mounted) return;
      // Only navigate when at /login route (e.g. from cart redirect).
      // When at / (AuthWrapper), it rebuilds and shows HomeScreen automatically.
      if (ModalRoute.of(context)?.settings.name == '/login') {
        Navigator.pushReplacementNamed(context, '/');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: theme.cardColor,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: theme.colorScheme.outline.withOpacity(0.3)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.06),
                    offset: const Offset(0, 4),
                    blurRadius: 12,
                  ),
                ],
              ),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Book Store',
                      style: theme.textTheme.headlineMedium,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Login',
                      style: theme.textTheme.titleLarge,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),
                    TextFormField(
                      controller: _emailController,
                      decoration: const InputDecoration(labelText: 'Email'),
                      keyboardType: TextInputType.emailAddress,
                      validator: (v) =>
                          v == null || v.isEmpty ? 'Email required' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _passwordController,
                      decoration: const InputDecoration(labelText: 'Password'),
                      obscureText: true,
                      validator: (v) =>
                          v == null || v.isEmpty ? 'Password required' : null,
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 16),
                      Text(
                        _error!,
                        style: TextStyle(color: theme.colorScheme.error),
                      ),
                    ],
                    const SizedBox(height: 24),
                    GFButton(
                      onPressed: _loading
                          ? null
                          : () {
                              if (_formKey.currentState!.validate()) _login();
                            },
                      fullWidthButton: true,
                      size: GFSize.LARGE,
                      color: theme.colorScheme.primary,
                      child: _loading
                          ? const SizedBox(
                              height: 24,
                              width: 24,
                              child: GFLoader(type: GFLoaderType.android, size: GFSize.SMALL),
                            )
                          : const Text('Login'),
                    ),
                    const SizedBox(height: 16),
                    GFButton(
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const RegisterScreen(),
                        ),
                      ).then((_) => _formKey.currentState?.reset()),
                      text: 'Register',
                      type: GFButtonType.outline,
                      fullWidthButton: true,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
