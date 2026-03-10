import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_platform_widgets/flutter_platform_widgets.dart';
import 'package:provider/provider.dart';

import 'l10n/app_localizations.dart';
import 'providers/auth_provider.dart';
import 'providers/locale_provider.dart';
import 'screens/book_detail_screen.dart';
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

class BookStoreApp extends StatelessWidget {
  const BookStoreApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => LocaleProvider()),
      ],
      child: Consumer<LocaleProvider>(
        builder: (context, localeProvider, _) {
          return MaterialApp(
            title: 'Book Store',
            debugShowCheckedModeBanner: false,
            theme: ThemeData(
              colorScheme: ColorScheme.fromSeed(
                seedColor: Colors.brown,
                brightness: Brightness.light,
              ),
              useMaterial3: true,
              scaffoldBackgroundColor: const Color(0xFFE0E5EC),
              cardColor: const Color(0xFFE0E5EC),
              appBarTheme: const AppBarTheme(
                backgroundColor: Color(0xFFE0E5EC),
                elevation: 0,
                centerTitle: true,
              ),
              elevatedButtonTheme: ElevatedButtonThemeData(
                style: ElevatedButton.styleFrom(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(24),
                  ),
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                  elevation: 0,
                  shadowColor: Colors.transparent,
                ),
              ),
            ),
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
        if (auth.isLoggedIn) {
          return const MainShell();
        }
        // Not logged in → show welcome screen so guests can browse
        return const GuestLandingScreen();
      },
    );
  }
}
