import 'package:flutter/material.dart';

/// Central strings file — all translatable text is here.
class AppLocalizations {
  final Locale locale;
  AppLocalizations(this.locale);

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const delegate = _AppLocalizationsDelegate();

  bool get isAr => locale.languageCode == 'ar';

  String _s(String ar, String en) => isAr ? ar : en;

  // ── Navigation ──────────────────────────────────────────────────────────────
  String get appName => _s('متجر الكتب', 'Book Store');
  String get navHome => _s('الرئيسية', 'Home');
  String get navBooks => _s('الكتب', 'Books');
  String get navAuthors => _s('المؤلفون', 'Authors');
  String get navCategories => _s('التصنيفات', 'Categories');
  String get navCart => _s('السلة', 'Cart');
  String get navAccount => _s('الحساب', 'Account');
  String get navProfile => _s('الملف الشخصي', 'Profile');
  String get navOrders => _s('طلباتي', 'My Orders');
  String get navLogin => _s('تسجيل الدخول', 'Login');
  String get navRegister => _s('إنشاء حساب', 'Register');
  String get navLogout => _s('تسجيل الخروج', 'Logout');

  // ── Auth ────────────────────────────────────────────────────────────────────
  String get loginTitle => _s('تسجيل الدخول', 'Login');
  String get loginBtn => _s('دخول', 'Login');
  String get loggingIn => _s('جاري الدخول...', 'Logging in...');
  String get customer => _s('عميل', 'Customer');
  String get employee => _s('موظف', 'Employee');
  String get emailLabel => _s('البريد الإلكتروني', 'Email');
  String get passwordLabel => _s('كلمة المرور', 'Password');
  String get emailRequired => _s('البريد الإلكتروني مطلوب', 'Email required');
  String get passwordRequired => _s('كلمة المرور مطلوبة', 'Password required');
  String get registerTitle => _s('إنشاء حساب', 'Register');
  String get registerBtn => _s('تسجيل', 'Register');
  String get registering => _s('جاري التسجيل...', 'Registering...');
  String get nameLabel => _s('الاسم', 'Name');
  String get nameRequired => _s('الاسم مطلوب', 'Name required');
  String get passwordConfirm => _s('تأكيد كلمة المرور', 'Confirm Password');
  String get passwordMismatch => _s('كلمتا المرور غير متطابقتين', 'Passwords do not match');
  String get alreadyAccount => _s('لديك حساب؟', 'Already have an account?');
  String get noAccount => _s('ليس لديك حساب؟', "Don't have an account?");

  // ── Home ────────────────────────────────────────────────────────────────────
  String get heroTitle => _s('مرحباً بك في متجر الكتب', 'Welcome to Book Store');
  String get featuredBooks => _s('عروض مميزة', 'Featured Books');
  String get newestBooks => _s('أحدث الكتب', 'Newest Books');
  String get viewAll => _s('عرض الكل', 'View All');

  // ── Books ───────────────────────────────────────────────────────────────────
  String get booksTitle => _s('تصفح الكتب', 'Browse Books');
  String get noBooks => _s('لا توجد كتب', 'No books found');
  String get inStock => _s('متوفر', 'In Stock');
  String get outOfStock => _s('غير متوفر', 'Out of Stock');
  String get addToCart => _s('أضف إلى السلة', 'Add to Cart');
  String get loginToAddToCart => _s('سجّل دخولك للإضافة إلى السلة', 'Login to Add to Cart');
  String get bookDescription => _s('الوصف', 'Description');
  String get bookAuthors => _s('المؤلفون', 'Authors');
  String get bookCategory => _s('التصنيف', 'Category');
  String get bookIsbn => _s('رقم ISBN', 'ISBN');
  String get bookPublisher => _s('الناشر', 'Publisher');
  String get bookYear => _s('السنة', 'Year');
  String get bookPages => _s('الصفحات', 'Pages');
  String get bookEdition => _s('الطبعة', 'Edition');
  String get bookSize => _s('الحجم', 'Size');
  String get bookWeight => _s('الوزن', 'Weight');

  // ── Cart ────────────────────────────────────────────────────────────────────
  String get cartTitle => _s('سلة التسوق', 'Shopping Cart');
  String get cartEmpty => _s('السلة فارغة', 'Cart is empty');
  String get remove => _s('حذف', 'Remove');
  String get checkout => _s('إتمام الشراء', 'Checkout');
  String totalStr(double amount) => isAr ? 'الإجمالي: \$${ amount.toStringAsFixed(2)}' : 'Total: \$${amount.toStringAsFixed(2)}';

  // ── Checkout ─────────────────────────────────────────────────────────────────
  String get checkoutTitle => _s('إتمام الشراء', 'Checkout');
  String get shippingAddress => _s('عنوان الشحن', 'Shipping Address');
  String get addressLabel => _s('العنوان', 'Address');
  String get cityLabel => _s('المدينة', 'City');
  String get countryLabel => _s('البلد', 'Country');
  String get postalCodeLabel => _s('الرمز البريدي', 'Postal Code');
  String get placeOrder => _s('تأكيد الطلب', 'Place Order');
  String get addressRequired => _s('العنوان مطلوب', 'Address required');
  String get cityRequired => _s('المدينة مطلوبة', 'City required');
  String get countryRequired => _s('البلد مطلوب', 'Country required');

  // ── Orders ──────────────────────────────────────────────────────────────────
  String get ordersTitle => _s('طلباتي', 'My Orders');
  String get noOrders => _s('لا توجد طلبات بعد', 'No orders yet');

  // ── Authors ─────────────────────────────────────────────────────────────────
  String get authorsTitle => _s('المؤلفون', 'Authors');
  String get noAuthors => _s('لا يوجد مؤلفون', 'No authors found');

  // ── Categories ───────────────────────────────────────────────────────────────
  String get categoriesTitle => _s('التصنيفات', 'Categories');
  String get noCategories => _s('لا توجد تصنيفات', 'No categories found');

  // ── Guest Landing ────────────────────────────────────────────────────────────
  String get welcomeTitle => _s('مرحباً بك', 'Welcome');
  String get welcomeSubtitle => _s('متجر الكتب', 'Book Store');
  String get browseAsGuest => _s('تصفح كزائر', 'Browse as Guest');

  // ── Common ──────────────────────────────────────────────────────────────────
  String get loading => _s('جاري التحميل...', 'Loading...');
  String get retry => _s('إعادة المحاولة', 'Retry');
  String get error => _s('حدث خطأ', 'An error occurred');
  String get languageToggle => isAr ? 'EN' : 'عربي';
  String get loginRequired => _s('سجّل دخولك للمتابعة', 'Login required to continue');
  String get cartLoginMsg => _s('سجّل دخولك للوصول إلى السلة', 'Login to access cart');
  String get loginAction => _s('تسجيل الدخول', 'Login');

  // Helper for dynamic access
  String get(String key) {
    switch (key) {
      case 'app_title': return appName;
      case 'app_subtitle': return welcomeSubtitle;
      case 'browse_as_guest': return browseAsGuest;
      case 'login': return navLogin;
      case 'create_account': return navRegister;
      case 'welcome_title': return welcomeTitle;
      default: return key;
    }
  }
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) =>
      ['ar', 'en'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async =>
      AppLocalizations(locale);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}
