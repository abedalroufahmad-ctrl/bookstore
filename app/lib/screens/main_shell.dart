import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'package:flutter_platform_widgets/flutter_platform_widgets.dart' show isMaterial;

import '../l10n/app_localizations.dart';
import 'account_screen.dart';
import 'book_list_screen.dart';
import 'cart_screen.dart';
import 'home_screen.dart';

/// Main shell with bottom navigation: Home, Books, Cart, Profile.
/// Uses a plain Scaffold + BottomNavigationBar so the nav bar is always visible.
class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final useMaterial = isMaterial(context);

    final navItems = [
      BottomNavigationBarItem(
        icon: Icon(useMaterial ? Icons.home_outlined : CupertinoIcons.house),
        activeIcon: Icon(useMaterial ? Icons.home : CupertinoIcons.house_fill),
        label: t.navHome,
      ),
      BottomNavigationBarItem(
        icon: Icon(useMaterial ? Icons.menu_book_outlined : CupertinoIcons.book),
        activeIcon: Icon(useMaterial ? Icons.menu_book : CupertinoIcons.book_fill),
        label: t.navBooks,
      ),
      BottomNavigationBarItem(
        icon: Icon(useMaterial ? Icons.shopping_cart_outlined : CupertinoIcons.cart),
        activeIcon: Icon(useMaterial ? Icons.shopping_cart : CupertinoIcons.cart_fill),
        label: t.navCart,
      ),
      BottomNavigationBarItem(
        icon: Icon(useMaterial ? Icons.person_outline : CupertinoIcons.person),
        activeIcon: Icon(useMaterial ? Icons.person : CupertinoIcons.person_fill),
        label: t.navProfile,
      ),
    ];

    final body = IndexedStack(
      index: _currentIndex,
      children: const [
        HomeScreen(),
        BookListScreen(),
        CartScreen(),
        AccountScreen(),
      ],
    );

    return Scaffold(
      body: body,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (int index) => setState(() => _currentIndex = index),
        type: BottomNavigationBarType.fixed,
        items: navItems,
        iconSize: 26,
        selectedFontSize: 12,
        unselectedFontSize: 11,
      ),
    );
  }
}
