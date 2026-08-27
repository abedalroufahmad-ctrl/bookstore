import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_platform_widgets/flutter_platform_widgets.dart';
import 'package:provider/provider.dart';

import 'l10n/app_localizations.dart';
import 'providers/auth_provider.dart';
import 'providers/locale_provider.dart';
import 'providers/profile_provider.dart';
import 'theme.dart';
import 'screens/book_detail_screen.dart';
import 'screens/book_list_screen.dart';
import 'screens/cart_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/author_books_screen.dart';
import 'screens/author_list_screen.dart';
import 'screens/category_books_screen.dart';
import 'screens/category_list_screen.dart';
import 'screens/warehouse_books_screen.dart';
import 'screens/warehouse_list_screen.dart';
import 'screens/publisher_books_screen.dart';
import 'screens/publisher_list_screen.dart';
import 'screens/admin_authors_screen.dart';
import 'screens/admin_book_form_screen.dart';
import 'screens/admin_books_screen.dart';
import 'screens/admin_categories_screen.dart';
import 'screens/admin_customers_screen.dart';
import 'screens/admin_dashboard_screen.dart';
import 'screens/admin_employees_screen.dart';
import 'screens/admin_orders_screen.dart';
import 'screens/admin_publisher_settings_screen.dart';
import 'screens/admin_publishers_screen.dart';
import 'screens/admin_settings_screen.dart';
import 'screens/admin_warehouses_browse_screen.dart';
import 'screens/admin_warehouses_screen.dart';
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
        ChangeNotifierProvider(create: (_) => ProfileProvider()),
      ],
      child: Consumer<LocaleProvider>(
        builder: (context, localeProvider, _) {
          return MaterialApp(
            title: 'Book Store',
            debugShowCheckedModeBanner: false,
            theme: buildGruvboxLightTheme(),
            darkTheme: buildGruvboxDarkTheme(),
            themeMode: ThemeMode.system,
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
              '/warehouses': (context) => const WarehouseListScreen(),
              '/publishers': (context) => const PublisherListScreen(),
              '/admin': (context) => const AdminDashboardScreen(),
              '/admin/books': (context) => const AdminBooksScreen(),
              '/admin/books/form': (context) => const AdminBookFormScreen(),
              '/admin/authors': (context) => const AdminAuthorsScreen(),
              '/admin/categories': (context) => const AdminCategoriesScreen(),
              '/admin/publishers': (context) => const AdminPublishersScreen(),
              '/admin/warehouses': (context) => const AdminWarehousesScreen(),
              '/admin/warehouses/browse': (context) => const AdminWarehousesBrowseScreen(),
              '/admin/orders': (context) => const AdminOrdersScreen(),
              '/admin/employees': (context) => const AdminEmployeesScreen(),
              '/admin/customers': (context) => const AdminCustomersScreen(),
              '/admin/settings': (context) => const AdminSettingsScreen(),
              '/admin/publisher-settings': (context) =>
                  const AdminPublisherSettingsScreen(),
              '/staff/orders': (context) =>
                  const AdminOrdersScreen(useEmployeeApi: true),
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
              if (settings.name?.startsWith('/warehouse/') == true) {
                final id = settings.name!.replaceFirst('/warehouse/', '');
                final args = settings.arguments as Map<String, dynamic>?;
                return MaterialPageRoute(
                  builder: (_) => WarehouseBooksScreen(
                    warehouseId: id,
                    warehouseName: args?['name'] as String?,
                  ),
                  settings: settings,
                );
              }
              if (settings.name?.startsWith('/publisher/') == true) {
                final id = settings.name!.replaceFirst('/publisher/', '');
                final args = settings.arguments as Map<String, dynamic>?;
                return MaterialPageRoute(
                  builder: (_) => PublisherBooksScreen(
                    publisherId: id,
                    publisherName: args?['name'] as String?,
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
