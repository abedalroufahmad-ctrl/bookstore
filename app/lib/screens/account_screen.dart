import 'package:flutter/material.dart';
import 'package:flutter_platform_widgets/flutter_platform_widgets.dart';
import 'package:provider/provider.dart';

import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';

/// Account tab: login/register for guests, orders & logout for logged-in users.
class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return PlatformScaffold(
      appBar: PlatformAppBar(title: Text(t.navAccount)),
      body: Consumer<AuthProvider>(
        builder: (context, auth, _) {
          if (!auth.isLoggedIn) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    PlatformElevatedButton(
                      onPressed: () => Navigator.pushNamed(context, '/login'),
                      child: Text(t.navLogin),
                    ),
                    const SizedBox(height: 16),
                    PlatformTextButton(
                      onPressed: () => Navigator.pushNamed(context, '/register'),
                      child: Text(t.navRegister),
                    ),
                  ],
                ),
              ),
            );
          }
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              ListTile(
                title: Text(t.navOrders),
                onTap: () => Navigator.pushNamed(context, '/orders'),
              ),
              ListTile(
                title: Text(t.navLogout),
                onTap: () {
                  auth.logout();
                  Navigator.pushNamedAndRemoveUntil(context, '/', (_) => false);
                },
              ),
            ],
          );
        },
      ),
    );
  }
}
