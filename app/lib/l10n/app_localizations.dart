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
  String get navWarehouses => _s('المستودعات', 'Warehouses');
  String get navPublishers => _s('الناشرون', 'Publishers');
  String get navCart => _s('السلة', 'Cart');
  String get navAccount => _s('الحساب', 'Account');
  String get navProfile => _s('الملف الشخصي', 'Profile');
  String get navOrders => _s('طلباتي', 'My Orders');
  String get navLogin => _s('تسجيل الدخول', 'Login');
  String get navRegister => _s('إنشاء حساب', 'Register');
  String get navLogout => _s('تسجيل الخروج', 'Logout');

  String get adminPosTerminal => _s('نقطة البيع', 'Point of Sale');
  String get adminPosTerminalHint => _s('إنشاء فواتير للعملاء مباشرة (الاسم اختياري)', 'Create walk-in invoices (name optional)');
  String get adminPosReports => _s('فواتير المبيعات', 'Sales invoices');
  String get adminPosReportsHint => _s('مراجعة الفواتير وإجمالي اليوم والشهر والسنة', 'Review invoices and daily, monthly, and yearly totals');
  String get adminPosWalkIn => _s('عميل مباشر بدون اسم', 'Walk-in (no name)');
  String get adminPosCustomerOptional => _s('اسم العميل (اختياري)', 'Customer name (optional)');
  String get adminPosCompleteSale => _s('إنشاء الفاتورة', 'Create invoice');
  String get adminPosNewSale => _s('بيع جديد', 'New sale');
  String get adminPosInvoiceCreated => _s('تم إنشاء الفاتورة', 'Invoice created');
  String get adminPosCartEmpty => _s('اختر الكتب لإضافتها إلى الفاتورة', 'Select books to add them to the invoice');
  String get adminPosOwnWarehouseDefault => _s('يُعرض مستودعك افتراضياً. يمكنك التبديل إلى مستودع أو دار نشر أخرى.', 'Your warehouse is selected by default. You can switch to another warehouse or publisher.');
  String get adminAllPublishers => _s('كل الناشرين', 'All publishers');
  String get adminAllWarehouses => _s('كل المستودعات', 'All warehouses');
  String get adminTotalToday => _s('اليوم', 'Today');
  String get adminTotalThisMonth => _s('هذا الشهر', 'This month');
  String get adminTotalThisYear => _s('هذه السنة', 'This year');
  String get adminTotalAllTime => _s('الإجمالي الكلي', 'All time');
  String get adminNoInvoices => _s('لا توجد فواتير بعد', 'No invoices yet');
  String get adminNoReports => _s('لا توجد مبيعات في هذه الفترة', 'No sales in this period');
  String get adminPrint => _s('طباعة', 'Print');
  String get adminOpenInvoice => _s('فتح', 'Open');
  String get adminBackToInvoices => _s('العودة إلى الفواتير', 'Back to invoices');
  String get adminInvoiceNotFound => _s('الفاتورة غير موجودة', 'Invoice not found');
  String get adminCurrentSale => _s('البيع الحالي', 'Current sale');
  String get adminInvoiceId => _s('الفاتورة', 'Invoice');
  String get adminDate => _s('التاريخ', 'Date');
  String get adminWarehouse => _s('المستودع', 'Warehouse');
  String get adminReportDaily => _s('حسب اليوم', 'By day');
  String get adminReportMonthly => _s('حسب الشهر', 'By month');
  String get adminReportYearly => _s('حسب السنة', 'By year');
  String get adminRecentInvoices => _s('الفواتير', 'Invoices');
  String get adminItemTitle => _s('العنوان', 'Title');
  String get adminItemPrice => _s('السعر', 'Price');
  String invoicesCount(int count) => _s('$count فاتورة', '$count invoices');

  String adminRoleLabel(String role) {
    switch (role) {
      case 'manager':
        return _s('مدير', 'Manager');
      case 'shipping':
        return _s('شحن', 'Shipping');
      case 'review':
        return _s('مراجعة', 'Review');
      case 'accounting':
        return _s('محاسبة', 'Accounting');
      case 'warehouse_manager':
        return _s('مدير مستودع', 'Warehouse Manager');
      case 'publisher_manager':
        return _s('مدير دار نشر', 'Publisher Manager');
      case 'direct_sales':
        return _s('مبيعات مباشرة', 'Direct Sales');
      default:
        return role.replaceAll('_', ' ');
    }
  }

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
  String get passwordMinLength =>
      _s('يجب أن تكون كلمة المرور 8 أحرف على الأقل', 'Password must be at least 8 characters');
  String get rememberMe => _s('تذكرني', 'Remember me');
  String get signingInAs => _s('نوع الدخول', 'Signing in as');
  String get alreadyAccount => _s('لديك حساب؟', 'Already have an account?');
  String get noAccount => _s('ليس لديك حساب؟', "Don't have an account?");

  // ── Home ────────────────────────────────────────────────────────────────────
  String get heroTitle => _s('مرحباً بك في متجر الكتب', 'Welcome to Book Store');
  String get featuredBooks => _s('عروض مميزة', 'Featured Books');
  String get newestBooks => _s('أحدث الكتب', 'Newest Books');
  String get viewAll => _s('عرض الكل', 'View All');

  // ── Search ───────────────────────────────────────────────────────────────────
  String get searchHint => _s('بحث...', 'Search...');
  String get searchBooksHint => _s('بحث عن كتاب أو مؤلف...', 'Search books or authors...');
  String get searchAuthorsHint => _s('بحث عن مؤلف...', 'Search authors...');
  String get searchCategoriesHint => _s('بحث عن تصنيف...', 'Search categories...');
  String get searchWarehousesHint => _s('بحث عن مستودع...', 'Search warehouses...');
  String get noSearchResults => _s('لا توجد نتائج', 'No results found');

  // ── Books ───────────────────────────────────────────────────────────────────
  String get booksTitle => _s('تصفح الكتب', 'Browse Books');
  String get filterAllBooks => _s('الكل', 'All');
  String get conditionNew => _s('جديد', 'New');
  String get conditionUsed => _s('مستعمل', 'Used');
  String get clearFilters => _s('مسح الفلاتر', 'Clear filters');
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
  String get orderPlacedSuccess => _s('تم تأكيد الطلب بنجاح', 'Order placed successfully');
  String get noPaymentMethodsAvailable =>
      _s('لا توجد طريقة دفع متاحة. يرجى المحاولة لاحقاً.', 'No payment method available. Please try again later.');
  String get connectionError => _s('خطأ في الاتصال', 'Connection error');
  String get invalidResponse => _s('استجابة غير صالحة', 'Invalid response');

  // ── Orders ──────────────────────────────────────────────────────────────────
  String get ordersTitle => _s('طلباتي', 'My Orders');
  String get noOrders => _s('لا توجد طلبات بعد', 'No orders yet');
  String get staffOrdersTitle => _s('طلبات المستودع', 'Warehouse orders');
  String get staffOrdersSubtitle =>
      _s('مراجعة الطلبات وعروض الأسعار', 'Review orders and submit quotes');
  String get warehouseQuote => _s('عرض المستودع', 'Warehouse quote');
  String get warehouseQuoteHint => _s(
        'أكد إجمالي البنود وأضف رسوم الشحن واحفظ لإرسال العرض للعميل.',
        'Confirm line totals, add shipping fee, and save to send the quote to the customer.',
      );
  String get shippingFeeLabel => _s('رسوم الشحن', 'Shipping fee');
  String get shippingMethodLabel => _s('طريقة الشحن', 'Shipping method');
  String get shippingMethodHint =>
      _s('مثال: توصيل عادي', 'e.g. Standard courier');
  String get paymentMethodLabel => _s('طريقة الدفع', 'Payment method');
  String get paymentMethodHint => _s('مثال: cod أو paypal', 'e.g. cod, paypal');
  String get saveQuote => _s('حفظ العرض', 'Save quote');
  String get booksSubtotalLabel => _s('الكتب', 'Books');
  String get paymentStatusLabel => _s('حالة الدفع', 'Payment status');
  String get invalidShippingFee => _s('أدخل رسوم شحن صالحة', 'Enter a valid shipping fee');
  String get ordersItemTitleCol => _s('العنوان', 'Title');
  String get ordersItemPriceCol => _s('السعر', 'Price');
  String get ordersBackToList => _s('عودة إلى الطلبات', 'Back to orders');
  String get orderDetailHeading => _s('طلب', 'Order');
  String get orderNotFound => _s('لم يُعثر على الطلب', 'Order not found');
  String get ordersBooksSubtotal => _s('مجموع الكتب', 'Books subtotal');
  String get ordersTotalLabel => _s('الإجمالي', 'Total');
  String get ordersStatusLabel => _s('الحالة', 'Status');
  String get ordersWarehouseLabel => _s('المستودع', 'Warehouse');
  String get ordersPublisherLabel => _s('الناشر', 'Publisher');
  String get ordersPayWithPayPal => _s('الدفع عبر PayPal', 'Pay with PayPal');
  String get ordersConfirmWithWarehouse =>
      _s('تأكيد وإرسال إلى المستودع', 'Confirm & resubmit to warehouse');
  String get ordersAwaitingQuoteCustomerHint => _s(
        'أكّد هذا العرض لمتابعة التنفيذ، أو ادفع عبر PayPal إذا كان ذلك هو خيارك.',
        'The warehouse finalized this quote. Confirm to continue fulfillment, or pay with PayPal if that is your method.',
      );
  String get ordersLineItemsHeading => _s('البنود', 'Items');
  String get orderBookFallback => _s('كتاب', 'Book');

  // ── Authors ─────────────────────────────────────────────────────────────────
  String get authorsTitle => _s('المؤلفون', 'Authors');
  String get noAuthors => _s('لا يوجد مؤلفون', 'No authors found');

  // ── Categories ───────────────────────────────────────────────────────────────
  String get categoriesTitle => _s('التصنيفات', 'Categories');
  String get noCategories => _s('لا توجد تصنيفات', 'No categories found');

  String get warehousesTitle => _s('المستودعات', 'Warehouses');
  String get noWarehouses => _s('لا توجد مستودعات', 'No warehouses found');
  String get noPublishers => _s('لا يوجد ناشرون', 'No publishers found');
  String get warehouseBooksTitle => _s('كتب المستودع', 'Warehouse books');
  String get noBooksInWarehouse => _s('لا توجد كتب في هذا المستودع', 'No books in this warehouse');

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
  String get cancel => _s('إلغاء', 'Cancel');
  String get add => _s('إضافة', 'Add');
  String get delete => _s('حذف', 'Delete');
  String get assign => _s('إسناد', 'Assign');
  String get unassigned => _s('غير مسند', 'Unassigned');
  String get saveChanges => _s('حفظ التغييرات', 'Save Changes');

  // ── Admin ───────────────────────────────────────────────────────────────────
  String get adminTitle => _s('الإدارة', 'Admin');
  String get adminDashboard => _s('لوحة الإدارة', 'Admin dashboard');
  String get adminBooks => _s('الكتب', 'Books');
  String get adminManageCatalog => _s('إدارة الكتالوج', 'Manage catalog');
  String get adminAuthors => _s('المؤلفون', 'Authors');
  String get adminManageAuthors => _s('إضافة وإدارة المؤلفين', 'Add and manage authors');
  String get adminCategories => _s('التصنيفات', 'Categories');
  String get adminManageCategories => _s('إضافة وإدارة التصنيفات', 'Add and manage categories');
  String get adminPublishers => _s('الناشرون', 'Publishers');
  String get adminManagePublishers => _s('إضافة وإدارة الناشرين', 'Add and manage publishers');
  String get adminManageWarehouses => _s('إضافة وإدارة المستودعات', 'Add and manage warehouses');
  String get adminBooksByWarehouse => _s('كتب حسب المستودع', 'Books by warehouse');
  String get adminBooksByWarehouseHint =>
      _s('تصفح الكتالوج حسب المستودع', 'Browse catalog per warehouse');
  String get adminOrders => _s('الطلبات', 'Orders');
  String get adminManageOrders => _s('عرض وإدارة الطلبات', 'View and manage orders');
  String get adminViewManageOrders => _s('عرض وإدارة الطلبات', 'View and manage orders');
  String get adminEmployees => _s('الموظفون', 'Employees');
  String get adminManageStaff => _s('إدارة الموظفين', 'Manage staff');
  String get adminCustomers => _s('العملاء', 'Customers');
  String get adminManageCustomers => _s('عرض العملاء', 'View customers');
  String get adminSettings => _s('الإعدادات', 'Settings');
  String get adminGlobalSettings =>
      _s('إعدادات الموقع العامة', 'Global site configurations');
  String get adminPublisherSettings => _s('إعدادات الناشر', 'Publisher settings');
  String get adminConfigurePublisher => _s(
        'تهيئة حسابات التحصيل وسياسة الإرجاع والخصومات',
        'Configure payout accounts, return policy, and discounts',
      );
  String get adminNoPublisherSelected => _s(
        'لم يُحدد ناشر أو ليس لديك صلاحية.',
        'No publisher selected or you do not have permission.',
      );
  String get adminSupportEmail => _s('بريد الدعم', 'Support email');
  String get adminSupportPhone => _s('هاتف الدعم', 'Support phone');
  String get adminDefaultDiscount => _s('الخصم الافتراضي (%)', 'Default discount (%)');
  String get adminDefaultDiscountHint => _s(
        'سيُطبَّق هذا الخصم على كل كتبك ما لم يُتجاوز بخصم خاص.',
        'Applied to all your books unless overridden.',
      );
  String get adminReturnPolicy => _s('سياسة الإرجاع', 'Return policy');
  String get adminPayoutAccounts => _s('حسابات التحصيل', 'Payout accounts');
  String get adminPayoutAccountsHint => _s(
        'يدفع العملاء لهذا الناشر مباشرة عند إدخال حساب. تحتفظ إدارة المشروع بالنسبة المتفق عليها من إيراد الكتب.',
        'Customers pay this publishing house directly when an account is set. Project management keeps the agreed percentage of book revenue.',
      );
  String get adminPaypalEmail => _s('بريد PayPal', 'PayPal email');
  String get adminPaypalMerchantId => _s('معرّف تاجر PayPal', 'PayPal merchant ID');
  String get adminPaypalMerchantIdHint => _s(
        'اختياري. استخدمه إذا زوّدك PayPal بمعرّف تاجر لهذا الناشر.',
        'Optional. Use if PayPal provided a merchant ID for this house.',
      );
  String get adminBankName => _s('اسم البنك', 'Bank name');
  String get adminBankAccountNumber => _s('رقم الحساب البنكي', 'Bank account number');
  String get adminPlatformCommissionPercent =>
      _s('عمولة إدارة المشروع (%)', 'Project management commission (%)');
  String get adminPlatformCommissionPercentHint => _s(
        'حصة إدارة المشروع من إيراد الكتب (بدون الشحن) حسب الاتفاق مع هذا الناشر.',
        'Share of book revenue (not shipping) kept by project management, as agreed with this publishing house.',
      );
  String get adminPlatformCommissionReadOnly => _s(
        'تُحدَّد هذه النسبة مع إدارة المشروع ولا يمكن تغييرها من هنا.',
        'This rate is set with project management and cannot be changed here.',
      );
  String get adminPaymentMethods => _s('طرق الدفع', 'Payment methods');
  String get adminNoGlobalPaymentMethods =>
      _s('لا توجد طرق دفع مفعلة عالمياً.', 'No payment methods are globally enabled.');
  String get adminPlatformCommissionAmount =>
      _s('حصة إدارة المشروع', 'Project management share');
  String get adminPublisherPayoutAmount => _s('حصة دار النشر', 'Publishing house share');
  String get adminPayoutPaypalEmail => _s('تحصيل PayPal', 'PayPal payout');
  String get adminAddBook => _s('إضافة كتاب', 'Add book');
  String get adminEditBook => _s('تعديل كتاب', 'Edit book');
  String get adminCoverImage => _s('صورة الغلاف', 'Cover image');
  String get adminCoverImageHint => _s(
        'التقط صورة واضحة للغلاف (ويفضّل ظهر الغلاف مع الرقم الدولي ISBN). نحفظ الصورة ونحاول ملء البيانات تلقائياً.',
        'Take a clear cover photo (back cover with ISBN barcode works best). We save it and try to fill book details automatically.',
      );
  String get adminTakeCoverPhoto => _s('التقاط صورة', 'Take photo');
  String get adminUploadCoverFile => _s('اختيار صورة', 'Choose image');
  String get adminRemoveCover => _s('إزالة الغلاف', 'Remove cover');
  String get adminReadingCover => _s('جاري قراءة الغلاف...', 'Reading cover...');
  String get adminFailedAnalyzeCover => _s('فشل قراءة صورة الغلاف', 'Failed to read cover image');
  String get adminCoverFilled => _s(
        'تم حفظ الغلاف وملء الحقول المتاحة — راجعها قبل الحفظ.',
        'Cover saved and available fields filled — review before saving.',
      );
  String get adminCoverSavedOnly => _s(
        'تم حفظ الغلاف. لم تُقرأ تفاصيل كافية — عبّئ الحقول يدوياً.',
        'Cover saved. Not enough details read — fill fields manually.',
      );
  String get adminSave => _s('حفظ', 'Save');
  String get adminDeleteConfirm => _s('هل أنت متأكد من الحذف؟', 'Are you sure you want to delete?');
  String get adminCondition => _s('الحالة', 'Condition');
  String get adminVisible => _s('ظاهر', 'Visible');
  String get adminSold => _s('مباع', 'Sold');
  String get adminNewCondition => _s('جديد', 'New');
  String get adminUsedCondition => _s('مستعمل', 'Used');
  String get adminSelectWarehouse => _s('اختر المستودع', 'Select warehouse');
  String get adminSelectPublisher => _s('اختر الناشر', 'Select publisher');
  String get adminSelectCategory => _s('اختر التصنيف', 'Select category');
  String get adminRole => _s('الدور', 'Role');
  String get adminPublisher => _s('الناشر', 'Publisher');
  String get adminFailedSave => _s('فشل الحفظ', 'Failed to save');
  String get adminNoItems => _s('لا توجد عناصر', 'No items');
  String get adminAddAuthor => _s('إضافة مؤلف', 'Add author');
  String get adminDeleteAuthor => _s('حذف المؤلف', 'Delete author');
  String get adminAddCategory => _s('إضافة تصنيف', 'Add category');
  String get adminDeweyCode => _s('رمز ديوي', 'Dewey code');
  String get adminSubjectTitle => _s('عنوان الموضوع', 'Subject title');
  String get adminDeleteBook => _s('حذف الكتاب', 'Delete book');
  String get adminSettingsTitle => _s('إعدادات الإدارة', 'Admin Settings');
  String get adminGlobalSettingsHeading => _s('الإعدادات العامة', 'Global Settings');
  String get adminGlobalDiscount => _s('الخصم العام (%)', 'Global Discount (%)');
  String get adminGlobalDiscountHint => _s(
        'يُطبَّق على كل الكتب التي ليس لها خصم خاص.',
        'Applied to all books that do not have a special discount.',
      );
  String get adminCatalogItemsPerPage =>
      _s('عناصر الكتالوج لكل صفحة', 'Catalog items per page');
  String get adminSettingsSaved => _s('تم حفظ الإعدادات', 'Settings saved');
  String get adminPublisherSettingsBody => _s(
        'قم بتهيئة سياسة الإرجاع والخصومات من لوحة الإدارة على الويب حالياً',
        'Configure return policy and discounts from the web admin for now',
      );
  String get fieldRequired => _s('مطلوب', 'Required');
  String get invalidNumber => _s('رقم غير صالح', 'Invalid number');
  String get mustBeBetween0And100 =>
      _s('يجب أن يكون بين 0 و 100', 'Must be between 0 and 100');
  String deleteNamed(String name) =>
      isAr ? 'حذف «$name»؟' : 'Delete "$name"?';
  String get assignTo => _s('إسناد إلى', 'Assign to');
  String get employeeLabel => _s('الموظف', 'Employee');
  String assignedTo(String name) =>
      isAr ? 'المسند: $name' : 'Assigned: $name';
  String get itemsLabel => _s('العناصر', 'Items');
  String orderNumber(String id) => isAr ? 'طلب #$id' : 'Order #$id';
  String paymentMethodDisplayName(String id, String fallback) {
    switch (id) {
      case 'cod':
        return _s('الدفع عند الاستلام', 'Cash on Delivery (COD)');
      case 'card':
      case 'stripe':
        return _s('بطاقة ائتمان/خصم', 'Credit/Debit Card');
      case 'paypal':
        return 'PayPal';
      default:
        return fallback;
    }
  }

  String get booksByAuthorFallback => _s('كتب المؤلف', 'Author books');
  String get noBooksForAuthor => _s('لا توجد كتب لهذا المؤلف', 'No books for this author');
  String get booksByCategoryFallback => _s('كتب التصنيف', 'Category books');
  String get noBooksInCategory =>
      _s('لا توجد كتب في هذا التصنيف', 'No books in this category');
  String get statusLabel => _s('الحالة', 'Status');
  String get totalLabel => _s('الإجمالي', 'Total');
  String get customerLabel => _s('العميل', 'Customer');
  String get updateStatus => _s('تحديث الحالة', 'Update status');

  String orderStatus(String status) {
    switch (status) {
      case 'pending_warehouse_review':
        return _s('أُرسل للمستودع (بانتظار التسعير)', 'Sent to warehouse (pending pricing)');
      case 'awaiting_customer_confirmation':
        return _s('بانتظار تأكيد العميل', 'Awaiting customer confirmation');
      case 'resubmitted_to_warehouse':
        return _s('أُعيد للمستودع (وافق العميل)', 'Returned to warehouse (customer accepted)');
      case 'processing_fulfillment':
        return _s('قيد التجهيز / الشحن', 'Processing / preparing shipment');
      case 'shipped_collecting_payment':
        return _s('تم الشحن / تحصيل الدفع', 'Shipped / collecting payment');
      case 'completed':
        return _s('مكتمل', 'Completed');
      case 'cancelled':
        return _s('ملغى', 'Cancelled');
      case 'pending_review':
        return _s('بانتظار المراجعة (قديم)', 'Pending review (legacy)');
      case 'confirmed':
        return _s('مؤكد (قديم)', 'Confirmed (legacy)');
      case 'preparing':
        return _s('قيد التجهيز (قديم)', 'Preparing (legacy)');
      case 'shipped':
        return _s('تم الشحن (قديم)', 'Shipped (legacy)');
      case 'delivered':
        return _s('تم التسليم (قديم)', 'Delivered (legacy)');
      default:
        return status.replaceAll('_', ' ');
    }
  }

  // ── Profile ───────────────────────────────────────────────────────────────
  String get myProfile => _s('الملف الشخصي', 'My Profile');
  String get editProfile => _s('تعديل الملف', 'Edit profile');
  String get personalInformation => _s('المعلومات الشخصية', 'Personal Information');
  String get phoneLabel => _s('الهاتف', 'Phone');
  String get shippingAddressSection => _s('عنوان الشحن', 'Shipping Address');
  String get preferences => _s('التفضيلات', 'Preferences');
  String get language => _s('اللغة', 'Language');
  String get myOrders => _s('طلباتي', 'My Orders');
  String get viewAndManageOrders => _s('عرض وإدارة طلباتك', 'View and manage your orders');
  String get appVersion => _s('الإصدار', 'Version');
  String get notSet => _s('—', '—');
  String get save => _s('حفظ', 'Save');

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
