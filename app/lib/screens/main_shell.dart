import 'package:flutter/material.dart';
import 'package:flutter_platform_widgets/flutter_platform_widgets.dart';

import '../l10n/app_localizations.dart';
import 'account_screen.dart';
import 'book_list_screen.dart';
import 'cart_screen.dart';
import 'home_screen.dart';

/// Main shell with platform-adaptive bottom navigation (Material NavigationBar / Cupertino tab bar).
class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  late final PlatformTabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = PlatformTabController(initialIndex: 0);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final items = [
      BottomNavigationBarItem(
        icon: Icon(context.platformIcons.home),
        label: t.navHome,
      ),
      BottomNavigationBarItem(
        icon: Icon(context.platformIcons.book),
        label: t.navBooks,
      ),
      BottomNavigationBarItem(
        icon: Icon(context.platformIcons.shoppingCart),
        label: t.navCart,
      ),
      BottomNavigationBarItem(
        icon: Icon(context.platformIcons.accountCircle),
        label: t.navProfile,
      ),
    ];

    return PlatformTabScaffold(
      tabController: _tabController,
      items: items,
      materialTabs: (context, _) => MaterialNavBarData(
        iconSize: 22,
        selectedFontSize: 11,
        unselectedFontSize: 10,
      ),
      bodyBuilder: (context, index) {
        switch (index) {
          case 0:
            return const HomeScreen();
          case 1:
            return const BookListScreen();
          case 2:
            return const CartScreen();
          case 3:
            return const AccountScreen();
          default:
            return const HomeScreen();
        }
      },
    );
  }
}
