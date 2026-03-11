import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_platform_widgets/flutter_platform_widgets.dart';
import 'package:provider/provider.dart';

import 'l10n/app_localizations.dart';
import 'providers/auth_provider.dart';
import 'providers/locale_provider.dart';
import 'providers/profile_provider.dart';
import 'screens/book_detail_screen.dart';
import 'screens/book_list_screen.dart';
import 'screens/cart_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/author_books_screen.dart';
import 'screens/author_list_screen.dart';
import 'screens/category_books_screen.dart';
import 'screens/category_list_screen.dart';
import 'screens/guest_landing_screen.dart';
import 'screens/main_shell.dart';
import 'screens/login_screen.dart';
import 'screens/orders_screen.dart';
import 'screens/register_screen.dart';

void main() {
  runApp(const BookStoreApp());
}

ThemeData _buildBookStoreTheme() {
  const surface = Color(0xFFF5F0E8);
  const surfaceVariant = Color(0xFFEBE4DA);
  const primary = Color(0xFF8B6914);
  const onPrimary = Color(0xFFFFFFFF);
  const onSurface = Color(0xFF2C2416);
  const outline = Color(0xFFC4B8A4);

  return ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.light(
      primary: primary,
      onPrimary: onPrimary,
      secondary: const Color(0xFF6B5B45),
      onSecondary: Colors.white,
      surface: surface,
      onSurface: onSurface,
      surfaceContainerHighest: surfaceVariant,
      outline: outline,
      error: const Color(0xFFB3261E),
      onError: Colors.white,
    ),
    scaffoldBackgroundColor: surface,
    cardColor: surfaceVariant,
    cardTheme: CardThemeData(
      color: surfaceVariant,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: surface,
      foregroundColor: onSurface,
      elevation: 0,
      centerTitle: true,
      scrolledUnderElevation: 1,
      surfaceTintColor: Colors.transparent,
      titleTextStyle: const TextStyle(
        color: Color(0xFF2C2416),
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
    ),
    textTheme: const TextTheme(
      headlineMedium: TextStyle(
        color: Color(0xFF2C2416),
        fontSize: 22,
        fontWeight: FontWeight.bold,
      ),
      titleLarge: TextStyle(
        color: Color(0xFF2C2416),
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
      titleMedium: TextStyle(
        color: Color(0xFF2C2416),
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: TextStyle(
        color: Color(0xFF2C2416),
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: TextStyle(color: Color(0xFF2C2416), fontSize: 16),
      bodyMedium: TextStyle(color: Color(0xFF4A4238), fontSize: 14),
      bodySmall: TextStyle(color: Color(0xFF6B5B45), fontSize: 12),
      labelLarge: TextStyle(
        color: Color(0xFF2C2416),
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: onPrimary,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        elevation: 0,
        shadowColor: Colors.transparent,
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: surface,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: outline),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: primary, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    bottomNavigationBarTheme: BottomNavigationBarThemeData(
      backgroundColor: surfaceVariant,
      selectedItemColor: primary,
      unselectedItemColor: outline,
      type: BottomNavigationBarType.fixed,
      elevation: 0,
    ),
  );
}

class BookStoreApp extends StatelessWidget {
  const BookStoreApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => LocaleProvider()),
        ChangeNotifierProvider(create: (_) => ProfileProvider()),
      ],
      child: Consumer<LocaleProvider>(
        builder: (context, localeProvider, _) {
          return MaterialApp(
            title: 'Book Store',
            debugShowCheckedModeBanner: false,
            theme: _buildBookStoreTheme(),
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            supportedLocales: const [
              Locale('ar', ''),
              Locale('en', ''),
            ],
            locale: Locale(localeProvider.languageCode, ''),
            initialRoute: '/',
            routes: {
              '/': (context) => const AuthWrapper(),
              '/guest': (context) => const GuestLandingScreen(),
              '/login': (context) => const LoginScreen(),
              '/register': (context) => const RegisterScreen(),
              '/home': (context) => const MainShell(),
              '/books': (context) => const BookListScreen(),
              '/cart': (context) => const CartScreen(),
              '/checkout': (context) => const CheckoutScreen(),
              '/orders': (context) => const OrdersScreen(),
              '/authors': (context) => const AuthorListScreen(),
              '/categories': (context) => const CategoryListScreen(),
            },
            onGenerateRoute: (settings) {
              if (settings.name?.startsWith('/book/') == true) {
                return MaterialPageRoute(
                  builder: (_) => const BookDetailScreen(),
                  settings: settings,
                );
              }
              if (settings.name?.startsWith('/author/') == true) {
                final id = settings.name!.replaceFirst('/author/', '');
                final args = settings.arguments as Map<String, dynamic>?;
                return MaterialPageRoute(
                  builder: (_) => AuthorBooksScreen(
                    authorId: id,
                    authorName: args?['name'],
                  ),
                  settings: settings,
                );
              }
              if (settings.name?.startsWith('/category/') == true) {
                final id = settings.name!.replaceFirst('/category/', '');
                final args = settings.arguments as Map<String, dynamic>?;
                return MaterialPageRoute(
                  builder: (_) => CategoryBooksScreen(
                    categoryId: id,
                    categoryTitle: args?['title'],
                  ),
                  settings: settings,
                );
              }
              return null;
            },
          );
        },
      ),
    );
  }
}

class AuthWrapper extends StatelessWidget {
  const AuthWrapper({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        if (auth.loading) {
          return PlatformScaffold(
            body: Center(child: PlatformCircularProgressIndicator()),
          );
        }
        // Always show MainShell so navigation (Home / Books / Cart / Profile) is visible
        return const MainShell();
      },
    );
  }
}
