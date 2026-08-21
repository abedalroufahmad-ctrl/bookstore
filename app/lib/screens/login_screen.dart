import 'package:flutter/material.dart';
import 'package:getwidget/getwidget.dart';
import 'package:provider/provider.dart';

import '../l10n/app_localizations.dart';
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
  bool _rememberMe = false;
  bool _loginAsStaff = false;
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
    final String? err;
    if (_loginAsStaff) {
      err = await auth.loginAsEmployee(
        _emailController.text.trim(),
        _passwordController.text,
      );
    } else {
      err = await auth.loginAsCustomer(
        _emailController.text,
        _passwordController.text,
        rememberMe: _rememberMe,
      );
    }
    if (!mounted) return;
    setState(() => _loading = false);
    if (err != null) {
      if (mounted) setState(() => _error = err);
    } else {
      if (!_loginAsStaff) {
        final customer = context.read<AuthProvider>().customer;
        if (customer != null) {
          await context.read<ProfileProvider>().loadFromCustomer(customer);
        }
      }
      if (!mounted) return;
      // Leave the login route whether it was named `/login` or pushed another way.
      if (Navigator.of(context).canPop()) {
        Navigator.of(context).pop();
      } else {
        Navigator.of(context).pushReplacementNamed('/');
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final t = AppLocalizations.of(context);
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
                border: Border.all(color: theme.colorScheme.outline.withValues(alpha: 0.3)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
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
                      t.appName,
                      style: theme.textTheme.headlineMedium,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t.loginTitle,
                      style: theme.textTheme.titleLarge,
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: Text(
                        t.signingInAs,
                        style: theme.textTheme.labelLarge,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: ChoiceChip(
                            label: Text(t.customer),
                            selected: !_loginAsStaff,
                            onSelected: (v) {
                              if (v) setState(() => _loginAsStaff = false);
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ChoiceChip(
                            label: Text(t.employee),
                            selected: _loginAsStaff,
                            onSelected: (v) {
                              if (v) setState(() => _loginAsStaff = true);
                            },
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),
                    TextFormField(
                      controller: _emailController,
                      decoration: InputDecoration(labelText: t.emailLabel),
                      keyboardType: TextInputType.emailAddress,
                      validator: (v) =>
                          v == null || v.isEmpty ? t.emailRequired : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _passwordController,
                      decoration: InputDecoration(labelText: t.passwordLabel),
                      obscureText: true,
                      validator: (v) =>
                          v == null || v.isEmpty ? t.passwordRequired : null,
                    ),
                    const SizedBox(height: 8),
                    if (!_loginAsStaff)
                      Material(
                        color: Colors.transparent,
                        child: CheckboxListTile(
                          value: _rememberMe,
                          onChanged: (v) =>
                              setState(() => _rememberMe = v ?? false),
                          contentPadding: EdgeInsets.zero,
                          dense: true,
                          title: Text(t.rememberMe),
                          controlAffinity: ListTileControlAffinity.leading,
                        ),
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
                          : Text(t.loginBtn),
                    ),
                    if (!_loginAsStaff) ...[
                      const SizedBox(height: 16),
                      GFButton(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const RegisterScreen(),
                          ),
                        ).then((_) => _formKey.currentState?.reset()),
                        text: t.registerBtn,
                        type: GFButtonType.outline,
                        fullWidthButton: true,
                      ),
                    ],
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
