import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';
import '../providers/locale_provider.dart';
import '../providers/theme_provider.dart';
import 'account_screen.dart';
import 'book_list_screen.dart';
import 'cart_screen.dart';
import 'home_screen.dart';

/// Bottom tabs + browse menu mirroring SPA routes (authors, categories, warehouses, orders).
class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _currentIndex = 0;

  String _tabTitle(AppLocalizations t, {required bool showCart}) {
    final titles = <String>[
      t.navHome,
      t.booksTitle,
      if (showCart) t.cartTitle,
      t.myProfile,
    ];
    if (_currentIndex < 0 || _currentIndex >= titles.length) return t.navHome;
    return titles[_currentIndex];
  }

  void _goToCart(AuthProvider auth) {
    final t = AppLocalizations.of(context);
    final messenger = ScaffoldMessenger.of(context);
    if (!auth.isLoggedIn) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(t.cartLoginMsg),
          action: SnackBarAction(
            label: t.navLogin,
            onPressed: () => Navigator.pushNamed(context, '/login'),
          ),
        ),
      );
      return;
    }
    setState(() => _currentIndex = 2);
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final isWide = MediaQuery.sizeOf(context).width >= 900;

    context.watch<LocaleProvider>();
    final auth = context.watch<AuthProvider>();
    final showCart = !auth.isEmployee;

    final screens = <Widget>[
      const HomeScreen(),
      const BookListScreen(showAppBar: false),
      if (showCart) const CartScreen(showAppBar: false),
      const AccountScreen(showAppBar: false),
    ];
    final navItems = [
      BottomNavigationBarItem(
        icon: const Icon(Icons.home_outlined),
        activeIcon: const Icon(Icons.home),
        label: t.navHome,
      ),
      BottomNavigationBarItem(
        icon: const Icon(Icons.menu_book_outlined),
        activeIcon: const Icon(Icons.menu_book),
        label: t.navBooks,
      ),
      if (showCart)
        BottomNavigationBarItem(
          icon: const Icon(Icons.shopping_cart_outlined),
          activeIcon: const Icon(Icons.shopping_cart),
          label: t.navCart,
        ),
      BottomNavigationBarItem(
        icon: const Icon(Icons.person_outline),
        activeIcon: const Icon(Icons.person),
        label: t.navProfile,
      ),
    ];
    if (_currentIndex >= screens.length) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) setState(() => _currentIndex = 0);
      });
    }
    final safeIndex = _currentIndex.clamp(0, screens.length - 1);

    final body = IndexedStack(
      index: safeIndex,
      children: screens,
    );

    return Scaffold(
      appBar: isWide ? null : AppBar(
        automaticallyImplyLeading: false,
        title: Text(
          _currentIndex == 0 ? t.appName : _tabTitle(t, showCart: showCart),
        ),
        actions: [
          IconButton(
            tooltip: 'Toggle Theme',
            onPressed: () => context.read<ThemeProvider>().toggleTheme(),
            icon: Icon(
              context.watch<ThemeProvider>().isDarkMode
                  ? Icons.light_mode
                  : Icons.dark_mode,
            ),
          ),
          IconButton(
            tooltip: t.language,
            onPressed: () => context.read<LocaleProvider>().toggleLanguage(),
            icon: const Icon(Icons.language),
          ),
          if (showCart)
            IconButton(
              tooltip: t.navCart,
              onPressed: () => _goToCart(auth),
              icon: const Icon(Icons.shopping_cart_outlined),
            ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_horiz),
            onSelected: (value) async {
              switch (value) {
                case 'books':
                  setState(() => _currentIndex = 1);
                  break;
                case 'categories':
                  await Navigator.pushNamed(context, '/categories');
                  break;
                case 'authors':
                  await Navigator.pushNamed(context, '/authors');
                  break;
                case 'publishers':
                  await Navigator.pushNamed(context, '/publishers');
                  break;
                case 'warehouses':
                  await Navigator.pushNamed(context, '/warehouses');
                  break;
                case 'orders':
                  if (!auth.isLoggedIn) {
                    await Navigator.pushNamed(context, '/login');
                  } else {
                    await Navigator.pushNamed(context, '/orders');
                  }
                  break;
                case 'browse_warehouses':
                  await Navigator.pushNamed(context, '/admin/warehouses/browse');
                  break;
                case 'staff_orders':
                  await Navigator.pushNamed(context, '/staff/orders');
                  break;
                case 'pos':
                  await Navigator.pushNamed(context, '/admin/pos');
                  break;
                case 'pos_reports':
                  await Navigator.pushNamed(context, '/admin/pos/reports');
                  break;
                case 'admin':
                  await Navigator.pushNamed(context, '/admin');
                  break;
                case 'login':
                  await Navigator.pushNamed(context, '/login');
                  break;
                case 'register':
                  await Navigator.pushNamed(context, '/register');
                  break;
                case 'logout':
                  await context.read<AuthProvider>().logout();
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(t.navLogout)),
                    );
                  }
                  setState(() => _currentIndex = 0);
                  break;
              }
            },
            itemBuilder: (ctx) {
              final items = <PopupMenuEntry<String>>[
                PopupMenuItem(value: 'books', child: Text(t.navBooks)),
                PopupMenuItem(value: 'categories', child: Text(t.navCategories)),
                PopupMenuItem(value: 'authors', child: Text(t.navAuthors)),
                PopupMenuItem(value: 'publishers', child: Text(t.navPublishers)),
                PopupMenuItem(value: 'warehouses', child: Text(t.navWarehouses)),
              ];

              if (auth.userType == UserType.customer ||
                  (auth.userType == UserType.employee && !auth.isDirectSales)) {
                items.add(PopupMenuItem(value: 'orders', child: Text(t.navOrders)));
              }

              if (auth.isDirectSales) {
                items.addAll([
                  PopupMenuItem(value: 'pos', child: Text(t.adminPosTerminal)),
                  PopupMenuItem(value: 'pos_reports', child: Text(t.adminPosReports)),
                ]);
              } else if (auth.userType == UserType.employee) {
                items.addAll([
                  PopupMenuItem(
                    value: 'admin',
                    child: Text(t.adminDashboard),
                  ),
                  PopupMenuItem(
                    value: 'browse_warehouses',
                    child: Text(t.warehouseBooksTitle),
                  ),
                  PopupMenuItem(
                    value: 'staff_orders',
                    child: Text(t.staffOrdersTitle),
                  ),
                ]);
              }

              items.add(const PopupMenuDivider());

              if (!auth.isLoggedIn) {
                items.addAll([
                  PopupMenuItem(value: 'login', child: Text(t.navLogin)),
                  PopupMenuItem(value: 'register', child: Text(t.navRegister)),
                ]);
              } else {
                items.add(PopupMenuItem(value: 'logout', child: Text(t.navLogout)));
              }

              return items;
            },
          ),
        ],
      ),
      body: Column(
        children: [
          if (isWide) _buildDesktopHeader(context, auth, t, showCart),
          Expanded(
            child: Align(
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: BoxConstraints(maxWidth: isWide ? 1400 : double.infinity),
                child: body,
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: isWide ? null : Container(
        decoration: BoxDecoration(
          color: theme.bottomNavigationBarTheme.backgroundColor,
          border: Border(
            top: BorderSide(
              color: theme.colorScheme.outline.withValues(alpha: 0.2),
            ),
          ),
        ),
        child: BottomNavigationBar(
          currentIndex: safeIndex,
          onTap: (int index) => setState(() => _currentIndex = index),
          type: BottomNavigationBarType.fixed,
          items: navItems,
          iconSize: 26,
          selectedFontSize: 12,
          unselectedFontSize: 11,
        ),
      ),
    );
  }

  Widget _buildDesktopHeader(
    BuildContext context,
    AuthProvider auth,
    AppLocalizations t,
    bool showCart,
  ) {
    final theme = Theme.of(context);
    final name = auth.customer?.name ?? auth.employee?.name;

    Widget routeLink(String label, String route, IconData icon) {
      return TextButton.icon(
        onPressed: () => Navigator.pushNamed(context, route),
        icon: Icon(icon, size: 18),
        label: Text(label),
      );
    }

    return Material(
      color: theme.colorScheme.surface,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Text(
                    'B',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                      fontSize: 22,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Text(
                  t.appName,
                  style: theme.textTheme.titleLarge?.copyWith(
                    color: theme.colorScheme.primary,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Spacer(),
                if (name != null) ...[
                  CircleAvatar(
                    radius: 17,
                    backgroundColor: theme.colorScheme.primary,
                    child: Text(
                      name.isEmpty ? '?' : name.characters.first.toUpperCase(),
                      style: const TextStyle(color: Colors.white),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(name, style: theme.textTheme.labelLarge),
                  const SizedBox(width: 12),
                  TextButton(
                    onPressed: () async {
                      await context.read<AuthProvider>().logout();
                      if (context.mounted) setState(() => _currentIndex = 0);
                    },
                    child: Text(t.navLogout),
                  ),
                ] else ...[
                  TextButton(
                    onPressed: () => Navigator.pushNamed(context, '/login'),
                    child: Text(t.navLogin),
                  ),
                  FilledButton.tonal(
                    onPressed: () => Navigator.pushNamed(context, '/register'),
                    child: Text(t.navRegister),
                  ),
                ],
                if (showCart)
                  IconButton(
                    tooltip: t.navCart,
                    onPressed: () => _goToCart(auth),
                    icon: const Icon(Icons.shopping_cart_outlined),
                  ),
                IconButton(
                  tooltip: t.language,
                  onPressed: () => context.read<LocaleProvider>().toggleLanguage(),
                  icon: const Icon(Icons.language),
                ),
                IconButton(
                  tooltip: 'Toggle Theme',
                  onPressed: () => context.read<ThemeProvider>().toggleTheme(),
                  icon: Icon(
                    context.watch<ThemeProvider>().isDarkMode
                        ? Icons.light_mode
                        : Icons.dark_mode,
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
            child: Row(
              children: [
                routeLink(t.navBooks, '/books', Icons.menu_book_outlined),
                routeLink(t.navAuthors, '/authors', Icons.edit_outlined),
                routeLink(t.navCategories, '/categories', Icons.category_outlined),
                routeLink(t.navPublishers, '/publishers', Icons.account_balance_outlined),
                routeLink(t.navWarehouses, '/warehouses', Icons.warehouse_outlined),
                if (auth.userType == UserType.customer)
                  routeLink(t.navOrders, '/orders', Icons.receipt_long_outlined),
                if (auth.isEmployee && !auth.isDirectSales)
                  routeLink(t.adminDashboard, '/admin', Icons.dashboard_outlined),
                if (auth.isDirectSales) ...[
                  routeLink(t.adminPosTerminal, '/admin/pos', Icons.point_of_sale),
                  routeLink(t.adminPosReports, '/admin/pos/reports', Icons.analytics_outlined),
                ],
              ],
            ),
          ),
          const Divider(height: 1),
        ],
      ),
    );
  }
}
