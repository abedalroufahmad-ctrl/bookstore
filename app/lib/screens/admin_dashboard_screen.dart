import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';

/// Role-gated admin hub mirroring the web admin dashboard.
class AdminDashboardScreen extends StatelessWidget {
  const AdminDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final auth = context.watch<AuthProvider>();
    final role = auth.employee?.role ?? '';
    final isManager = role == 'manager';
    final isWarehouseManager = role == 'warehouse_manager';
    final isPublisherManager = role == 'publisher_manager';
    final isScoped = isWarehouseManager || (!isManager && auth.userType == UserType.employee);

    final tiles = <_AdminTile>[
      if (!isScoped || isWarehouseManager || isPublisherManager)
        _AdminTile(
          title: t.adminBooks,
          subtitle: t.adminManageCatalog,
          icon: Icons.menu_book_outlined,
          route: '/admin/books',
        ),
      if (!isScoped || isPublisherManager)
        _AdminTile(
          title: t.authorsTitle,
          subtitle: t.adminManageAuthors,
          icon: Icons.person_outline,
          route: '/admin/authors',
        ),
      if (!isScoped)
        _AdminTile(
          title: t.categoriesTitle,
          subtitle: t.adminManageCategories,
          icon: Icons.category_outlined,
          route: '/admin/categories',
        ),
      if (!isScoped)
        _AdminTile(
          title: t.adminPublishers,
          subtitle: t.adminManagePublishers,
          icon: Icons.business_outlined,
          route: '/admin/publishers',
        ),
      if (!isScoped || isWarehouseManager || isPublisherManager)
        _AdminTile(
          title: t.warehousesTitle,
          subtitle: t.adminManageWarehouses,
          icon: Icons.warehouse_outlined,
          route: '/admin/warehouses',
        ),
      if (!isScoped || isWarehouseManager || isPublisherManager)
        _AdminTile(
          title: t.adminBooksByWarehouse,
          subtitle: t.adminBooksByWarehouseHint,
          icon: Icons.storefront_outlined,
          route: '/admin/warehouses/browse',
        ),
      _AdminTile(
        title: t.adminOrders,
        subtitle: t.adminViewManageOrders,
        icon: Icons.receipt_long_outlined,
        route: '/admin/orders',
      ),
      if (!isScoped || isWarehouseManager || isPublisherManager)
        _AdminTile(
          title: t.adminEmployees,
          subtitle: t.adminManageStaff,
          icon: Icons.badge_outlined,
          route: '/admin/employees',
        ),
      if (!isScoped)
        _AdminTile(
          title: t.adminCustomers,
          subtitle: t.adminManageCustomers,
          icon: Icons.people_outline,
          route: '/admin/customers',
        ),
      if (!isScoped)
        _AdminTile(
          title: t.adminSettings,
          subtitle: t.adminGlobalSettings,
          icon: Icons.settings_outlined,
          route: '/admin/settings',
        ),
      if (isPublisherManager)
        _AdminTile(
          title: t.adminPublisherSettings,
          subtitle: t.adminConfigurePublisher,
          icon: Icons.tune,
          route: '/admin/publisher-settings',
        ),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminDashboard),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: GridView.builder(
        padding: const EdgeInsets.all(16),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.15,
        ),
        itemCount: tiles.length,
        itemBuilder: (context, i) {
          final tile = tiles[i];
          return Card(
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => Navigator.pushNamed(context, tile.route),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(tile.icon, color: Theme.of(context).colorScheme.primary),
                    const Spacer(),
                    Text(tile.title, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 4),
                    Text(
                      tile.subtitle,
                      style: Theme.of(context).textTheme.bodySmall,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _AdminTile {
  const _AdminTile({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.route,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final String route;
}
