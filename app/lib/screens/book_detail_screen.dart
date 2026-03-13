import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../config.dart';
import '../models/book.dart';
import '../providers/auth_provider.dart';

import '../l10n/app_localizations.dart';

String _resolveCoverUrl(String path) {
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  final origin = Uri.parse(apiBaseUrl).origin;
  return path.startsWith('/') ? '$origin$path' : '$origin/$path';
}

Widget _buildLogoPlaceholder({double? width, double? height}) {
  return Image.asset(
    'assets/app_icon.png',
    width: width,
    height: height,
    fit: BoxFit.cover,
    errorBuilder: (_, __, ___) => Icon(Icons.menu_book_outlined, size: width != null ? 64 : 40),
  );
}

class BookDetailScreen extends StatefulWidget {
  const BookDetailScreen({super.key});

  @override
  State<BookDetailScreen> createState() => _BookDetailScreenState();
}

class _BookDetailScreenState extends State<BookDetailScreen> {
  Book? _book;
  double _globalDiscount = 0;
  bool _loading = true;
  int _qty = 1;
  bool _authorsLoadRequested = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final args = ModalRoute.of(context)?.settings.arguments;
    if (args is Book) {
      // Only use args for initial display; do not overwrite when navigating back (would replace refetched book with list book that has no authors)
      if (_book == null) {
        setState(() {
          _book = args;
          _loading = false;
        });
      }
      // If book was passed from list/carousel without authors, fetch full book to get author names
      final hasAuthors = args.authors != null && args.authors!.isNotEmpty;
      if (!hasAuthors && !_authorsLoadRequested) {
        _authorsLoadRequested = true;
        _load(args.id);
      }
    } else {
      final id = ModalRoute.of(context)?.settings.arguments as String?;
      if (id != null) _load(id);
    }
  }

  Future<void> _load(String id) async {
    if (!mounted) return;
    setState(() => _loading = true);
    final res = await ApiService.instance.getBook(id);
    final settingsRes = await ApiService.instance.getSettings();
    if (!mounted) return;
    setState(() {
      _loading = false;
      _book = res.data;
      if (settingsRes.success && settingsRes.data != null) {
        _globalDiscount = (settingsRes.data!['global_discount'] ?? 0).toDouble();
      }
    });
  }

  Future<void> _addToCart() async {
    if (_book == null) return;
    final auth = context.read<AuthProvider>();
    if (auth.userType != UserType.customer) {
      Navigator.pushNamed(context, '/login');
      return;
    }
    final t = AppLocalizations.of(context);
    final res = await ApiService.instance.addToCart(_book!.id, quantity: _qty);
    if (!mounted) return;
    if (res.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t.addToCart)),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading || _book == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    final t = AppLocalizations.of(context);
    final b = _book!;
    final price = b.price;
    final bookDiscount = (b.discountPercent ?? 0).toDouble();
    final finalDiscount = bookDiscount > 0 ? bookDiscount : _globalDiscount;
    final discountedPrice = finalDiscount > 0 ? price * (1 - finalDiscount / 100) : price;

    return Scaffold(
      appBar: AppBar(title: Text(b.title)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: _buildDetailCover(context, b),
            ),
            Text(
              b.title,
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Text(
                  '\$${discountedPrice.toStringAsFixed(2)}',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.bold,
                      ),
                ),
                if (finalDiscount > 0) ...[
                  const SizedBox(width: 8),
                  Text(
                    '\$${price.toStringAsFixed(2)}',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: Colors.grey,
                          decoration: TextDecoration.lineThrough,
                        ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.red,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      '${finalDiscount.toInt()}% -',
                      style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ],
            ),
            _buildAuthorsRow(t.bookAuthors, b.authors, t.notSet),
            if (b.category != null)
              _buildCategoryRow(t.bookCategory, b.category!),
            if (b.isbn != null && b.isbn!.isNotEmpty)
              _buildInfoRow(t.bookIsbn, b.isbn!),
            if (b.publisher != null && b.publisher!.isNotEmpty)
              _buildInfoRow(t.bookPublisher, b.publisher!),
            if (b.publishYear != null)
              _buildInfoRow(t.bookYear, b.publishYear.toString()),
            if (b.pages != null)
              _buildInfoRow(t.bookPages, b.pages.toString()),
            if (b.editionNumber != null)
              _buildInfoRow(t.bookEdition, b.editionNumber.toString()),
            if (b.size != null && b.size!.isNotEmpty)
              _buildInfoRow(t.bookSize, b.size!),
            if (b.weight != null)
              _buildInfoRow(t.bookWeight, '${b.weight} kg'),
            if (b.stockQuantity >= 0)
              _buildInfoRow(
                _s(context, 'المخزون', 'Stock'),
                b.stockQuantity > 0 ? _s(context, 'متوفر (${b.stockQuantity})', '${b.stockQuantity} in stock') : t.outOfStock,
              ),
            if (b.description != null && b.description!.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text(t.bookDescription, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(b.description!, style: Theme.of(context).textTheme.bodyMedium),
            ],
            const SizedBox(height: 24),
            Row(
              children: [
                Text('${_s(context, 'الكمية: ', 'Quantity: ')}'),
                IconButton(
                  icon: const Icon(Icons.remove),
                  onPressed: _qty > 1 ? () => setState(() => _qty--) : null,
                ),
                Text('$_qty'),
                IconButton(
                  icon: const Icon(Icons.add),
                  onPressed: () => setState(() => _qty++),
                ),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: b.stockQuantity > 0 ? _addToCart : null,
                icon: const Icon(Icons.add_shopping_cart),
                label: Text(t.addToCart),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailCover(BuildContext context, Book b) {
    const w = 340.0;
    const h = 480.0;
    final url = (b.coverImageThumb ?? b.coverImage)?.trim();
    final valid = url != null &&
        url.isNotEmpty &&
        url.toLowerCase() != 'null' &&
        url.toLowerCase() != 'undefined';
    if (!valid) {
      return SizedBox(
        width: w,
        height: h,
        child: _buildLogoPlaceholder(width: w, height: h),
      );
    }
    return GestureDetector(
      onTap: () => _showFullImage(context, b.coverImage ?? b.coverImageThumb ?? ''),
      child: Image.network(
        _resolveCoverUrl(url),
        width: w,
        height: h,
        fit: BoxFit.cover,
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) return child;
          return SizedBox(
            width: w,
            height: h,
            child: const Center(child: CircularProgressIndicator()),
          );
        },
        errorBuilder: (_, __, ___) => SizedBox(
          width: w,
          height: h,
          child: _buildLogoPlaceholder(width: w, height: h),
        ),
      ),
    );
  }

  void _showFullImage(BuildContext context, String url) {
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(16),
        child: Stack(
          children: [
            InteractiveViewer(
              minScale: 0.5,
              maxScale: 4,
              child: Image.network(
                _resolveCoverUrl(url),
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => const Icon(Icons.broken_image, size: 64),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: IconButton(
                icon: const Icon(Icons.close, color: Colors.white),
                onPressed: () => Navigator.pop(ctx),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 90,
            child: Text(
              '$label:',
              style: TextStyle(
                fontWeight: FontWeight.w500,
                color: Colors.grey.shade700,
              ),
            ),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }

  Widget _buildAuthorsRow(String label, List<Author>? authors, String emptyValue) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 90,
            child: Text(
              '$label:',
              style: TextStyle(
                fontWeight: FontWeight.w500,
                color: Colors.grey.shade700,
              ),
            ),
          ),
          Expanded(
            child: (authors != null && authors.isNotEmpty)
                ? Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      for (int i = 0; i < authors.length; i++) ...[
                        if (i > 0) Text(', ', style: TextStyle(color: Colors.grey.shade700)),
                        InkWell(
                          onTap: () => Navigator.pushNamed(
                            context,
                            '/author/${authors[i].id}',
                            arguments: {'name': authors[i].name},
                          ),
                          borderRadius: BorderRadius.circular(4),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 2),
                            child: Text(
                              authors[i].name ?? '',
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.primary,
                                decoration: TextDecoration.underline,
                                decorationColor: Theme.of(context).colorScheme.primary,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ],
                  )
                : Text(emptyValue),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryRow(String label, Category category) {
    final value = category.subjectTitle ?? category.deweyCode ?? '';
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 90,
            child: Text(
              '$label:',
              style: TextStyle(
                fontWeight: FontWeight.w500,
                color: Colors.grey.shade700,
              ),
            ),
          ),
          Expanded(
            child: InkWell(
              onTap: () => Navigator.pushNamed(
                context,
                '/category/${category.id}',
                arguments: {'title': category.subjectTitle},
              ),
              borderRadius: BorderRadius.circular(4),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 2),
                child: Text(
                  value,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.primary,
                    decoration: TextDecoration.underline,
                    decorationColor: Theme.of(context).colorScheme.primary,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _s(BuildContext context, String ar, String en) {
    return AppLocalizations.of(context).isAr ? ar : en;
  }
}
