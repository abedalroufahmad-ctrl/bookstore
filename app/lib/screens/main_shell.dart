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

  String _tabTitle(AppLocalizations t) {
    switch (_currentIndex) {
      case 1:
        return t.booksTitle;
      case 2:
        return t.cartTitle;
      case 3:
        return t.myProfile;
      default:
        return t.navHome;
    }
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

    context.watch<LocaleProvider>();
    final auth = context.watch<AuthProvider>();

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

    final body = IndexedStack(
      index: _currentIndex,
      children: [
        const HomeScreen(),
        const BookListScreen(showAppBar: false),
        const CartScreen(showAppBar: false),
        const AccountScreen(showAppBar: false),
      ],
    );

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: Text(
          _currentIndex == 0 ? t.appName : _tabTitle(t),
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
                  auth.userType == UserType.employee) {
                items.add(PopupMenuItem(value: 'orders', child: Text(t.navOrders)));
              }

              if (auth.userType == UserType.employee) {
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
      body: body,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: theme.bottomNavigationBarTheme.backgroundColor,
          border: Border(
            top: BorderSide(
              color: theme.colorScheme.outline.withValues(alpha: 0.2),
            ),
          ),
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
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
}
