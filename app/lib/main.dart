import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'providers/auth_provider.dart';
import 'screens/admin_dashboard_screen.dart';
import 'screens/admin_authors_screen.dart';
import 'screens/admin_books_screen.dart';
import 'screens/admin_categories_screen.dart';
import 'screens/admin_orders_screen.dart';
import 'screens/book_detail_screen.dart';
import 'screens/cart_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/author_books_screen.dart';
import 'screens/author_list_screen.dart';
import 'screens/category_books_screen.dart';
import 'screens/category_list_screen.dart';
import 'screens/home_screen.dart';
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
    return ChangeNotifierProvider(
      create: (_) => AuthProvider(),
      child: MaterialApp(
        title: 'Book Store',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(seedColor: Colors.brown),
          useMaterial3: true,
          fontFamily: 'Roboto', // Default, we might want a better Arabic font later
        ),
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: const [
          Locale('ar', ''),
          Locale('en', ''),
        ],
        locale: const Locale('ar', ''),
        initialRoute: '/',
        routes: {
          '/': (context) => const AuthWrapper(),
          '/login': (context) => const LoginScreen(),
          '/register': (context) => const RegisterScreen(),
          '/home': (context) => const HomeScreen(),
          '/admin': (context) => const AdminDashboardScreen(),
          '/admin/books': (context) => const AdminBooksScreen(),
          '/admin/authors': (context) => const AdminAuthorsScreen(),
          '/admin/categories': (context) => const AdminCategoriesScreen(),
          '/admin/orders': (context) => const AdminOrdersScreen(),
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
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }
        if (auth.isLoggedIn) {
          return const HomeScreen();
        }
        return const LoginScreen();
      },
    );
  }
}
