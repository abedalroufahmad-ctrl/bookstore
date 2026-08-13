import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';
import 'admin_warehouses_browse_screen.dart';

class AdminDashboardScreen extends StatelessWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminTitle),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () {
              context.read<AuthProvider>().logout();
              Navigator.pushNamedAndRemoveUntil(
                context,
                '/login',
                (r) => false,
              );
            },
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _AdminTile(
            title: t.adminBooks,
            subtitle: t.adminManageCatalog,
            icon: Icons.menu_book,
            onTap: () => Navigator.pushNamed(context, '/admin/books'),
          ),
          _AdminTile(
            title: t.adminAuthors,
            subtitle: t.adminManageAuthors,
            icon: Icons.person,
            onTap: () => Navigator.pushNamed(context, '/admin/authors'),
          ),
          _AdminTile(
            title: t.adminCategories,
            subtitle: t.adminManageCategories,
            icon: Icons.category,
            onTap: () => Navigator.pushNamed(context, '/admin/categories'),
          ),
          _AdminTile(
            title: t.adminBooksByWarehouse,
            subtitle: t.adminBooksByWarehouseHint,
            icon: Icons.warehouse,
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute<void>(builder: (_) => const AdminWarehousesBrowseScreen()),
            ),
          ),
          _AdminTile(
            title: t.adminOrders,
            subtitle: t.adminManageOrders,
            icon: Icons.receipt_long,
            onTap: () => Navigator.pushNamed(context, '/admin/orders'),
          ),
          _AdminTile(
            title: t.adminSettings,
            subtitle: t.adminGlobalSettings,
            icon: Icons.settings,
            onTap: () => Navigator.pushNamed(context, '/admin/settings'),
          ),
        ],
      ),
    );
  }
}

class _AdminTile extends StatelessWidget {
  const _AdminTile({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
