import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';

class AdminDashboardScreen extends StatelessWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Admin'),
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
            title: 'Books',
            subtitle: 'Manage catalog',
            icon: Icons.menu_book,
            onTap: () => Navigator.pushNamed(context, '/admin/books'),
          ),
          _AdminTile(
            title: 'Authors',
            subtitle: 'Add and manage authors',
            icon: Icons.person,
            onTap: () => Navigator.pushNamed(context, '/admin/authors'),
          ),
          _AdminTile(
            title: 'Categories',
            subtitle: 'Add and manage categories',
            icon: Icons.category,
            onTap: () => Navigator.pushNamed(context, '/admin/categories'),
          ),
          _AdminTile(
            title: 'Orders',
            subtitle: 'View and manage orders',
            icon: Icons.receipt_long,
            onTap: () => Navigator.pushNamed(context, '/admin/orders'),
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
        leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.arrow_forward_ios, size: 16),
        onTap: onTap,
      ),
    );
  }
}
