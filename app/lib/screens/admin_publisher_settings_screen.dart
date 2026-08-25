import 'package:flutter/material.dart';

import '../l10n/app_localizations.dart';

/// Minimal placeholder for publisher_manager settings on mobile.
class AdminPublisherSettingsScreen extends StatelessWidget {
  const AdminPublisherSettingsScreen({super.key});

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
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          t.adminPublisherSettingsBody,
          style: Theme.of(context).textTheme.bodyLarge,
        ),
      ),
    );
  }
}
