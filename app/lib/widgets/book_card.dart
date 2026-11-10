import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../config.dart';
import '../l10n/app_localizations.dart';
import '../models/book.dart';
import '../providers/auth_provider.dart';
import '../theme.dart';
import '../utils/price_format.dart';
import '../utils/weight_format.dart';
import 'decode_size.dart';
import 'neumorphic.dart';

String _resolveCoverUrl(String path) {
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  final origin = Uri.parse(apiBaseUrl).origin;
  return path.startsWith('/') ? '$origin$path' : '$origin/$path';
}

class BookCard extends StatelessWidget {
  final Book book;
  final double? globalDiscount;
  final String weightUnit;
  final VoidCallback? onTap;

  const BookCard({
    super.key,
    required this.book,
    this.globalDiscount,
    this.weightUnit = 'kg',
    this.onTap,
  });

  static Widget _buildCoverImage(BuildContext context, String? imageUrl, ThemeData theme) {
    final url = imageUrl?.trim();
    final isNullLike = url != null &&
        url.isNotEmpty &&
        (url.toLowerCase() == 'null' || url.toLowerCase() == 'undefined');
    if (url == null || url.isEmpty || isNullLike) {
      return _buildLogoPlaceholder(context);
    }
    return LayoutBuilder(
      builder: (context, constraints) => Image.network(
        _resolveCoverUrl(url),
        fit: BoxFit.cover,
        cacheWidth: decodeWidthFor(context, constraints),
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) return child;
          return _buildLogoPlaceholder(context);
        },
        errorBuilder: (_, _, _) => _buildLogoPlaceholder(context),
      ),
    );
  }

  static Widget _buildLogoPlaceholder(BuildContext context) {
    return Container(
      color: Theme.of(context).brightness == Brightness.dark
          ? AppColors.darkBgCard
          : AppColors.accentSoft,
      alignment: Alignment.center,
      child: Icon(
        Icons.menu_book_rounded,
        size: 48,
        color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.35),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final price = book.price;
    final bookDiscount = (book.discountPercent ?? 0).toDouble();
    final finalDiscount = bookDiscount > 0 ? bookDiscount : (globalDiscount ?? 0);
    final discountedPrice = finalDiscount > 0 ? price * (1 - finalDiscount / 100) : price;
    final categoryLabel = book.category == null
        ? null
        : (locale == 'ar' && (book.category!.subjectTitleAr?.isNotEmpty ?? false)
            ? book.category!.subjectTitleAr
            : (book.category!.subjectTitleEn ?? book.category!.deweyCode));

    return GestureDetector(
      onTap: onTap,
      child: NeumorphicContainer(
        margin: const EdgeInsets.only(left: 2, right: 2, bottom: 4, top: 2),
        padding: const EdgeInsets.all(12),
        borderRadius: 24,
        child: LayoutBuilder(
          builder: (context, constraints) {
            final hasBoundedHeight = constraints.maxHeight < double.infinity;
            final cover = AspectRatio(
              aspectRatio: 3 / 4,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    _buildCoverImage(
                      context,
                      book.coverImageThumb ?? book.coverImage,
                      theme,
                    ),
                    if (book.isUsed || book.isSold)
                      Positioned(
                        top: 6,
                        left: 6,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: book.isSold ? AppColors.sold : AppColors.navy600,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            book.isSold
                                ? (locale == 'ar' ? 'مباع' : 'Sold')
                                : (locale == 'ar' ? 'مستعمل' : 'Used'),
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    if (finalDiscount > 0)
                      Positioned(
                        top: 6,
                        right: 6,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppColors.discount,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            bookDiscount > 0
                                ? '${finalDiscount.toInt()}%'
                                : '−${finalDiscount.toInt()}%',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            );
            return Column(
              mainAxisSize: hasBoundedHeight ? MainAxisSize.max : MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                if (hasBoundedHeight) Flexible(child: cover) else cover,
                const SizedBox(height: 10),
                Text(
                  book.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: theme.colorScheme.onSurface,
                  ),
                ),
                if (categoryLabel != null && categoryLabel.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      categoryLabel.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.6,
                        color: AppColors.accentHover,
                      ),
                    ),
                  ),
                if (book.weight != null && book.weight! > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      formatWeight(book.weight, weightUnit),
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 10,
                        color: theme.colorScheme.onSurface.withValues(alpha: 0.55),
                      ),
                    ),
                  ),
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Flexible(
                      child: Text(
                        formatBookPrice(discountedPrice, book.currencySymbol),
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurface,
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (finalDiscount > 0) ...[
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          formatBookPrice(price, book.currencySymbol),
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurface.withValues(alpha: 0.45),
                            decoration: TextDecoration.lineThrough,
                            fontSize: 11,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ],
                ),
                if (!book.isSold &&
                    context.watch<AuthProvider>().userType != UserType.employee)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: _AddToCartButton(bookId: book.id),
                  ),
                if (book.displayPublishers.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Wrap(
                      alignment: WrapAlignment.center,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        for (var i = 0; i < book.displayPublishers.length && i < 2; i++) ...[
                          if (i > 0)
                            Text(
                              '، ',
                              style: theme.textTheme.bodySmall?.copyWith(fontSize: 10),
                            ),
                          Builder(
                            builder: (context) {
                              final p = book.displayPublishers[i];
                              final label = p.name ?? '';
                              if (p.id.isNotEmpty) {
                                return GestureDetector(
                                  behavior: HitTestBehavior.opaque,
                                  onTap: () {
                                    Navigator.of(context).pushNamed(
                                      '/publisher/${p.id}',
                                      arguments: {'name': p.name},
                                    );
                                  },
                                  child: Text(
                                    label,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      fontSize: 10,
                                      color: theme.colorScheme.onSurface,
                                    ),
                                  ),
                                );
                              }
                              return Text(
                                label,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(fontSize: 10),
                              );
                            },
                          ),
                        ],
                      ],
                    ),
                  ),
                if (book.displayAuthors.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Wrap(
                      alignment: WrapAlignment.center,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        for (var i = 0; i < book.displayAuthors.length && i < 2; i++) ...[
                          if (i > 0)
                            Text(
                              '، ',
                              style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
                            ),
                          GestureDetector(
                            behavior: HitTestBehavior.opaque,
                            onTap: () {
                              final author = book.displayAuthors[i];
                              Navigator.of(context).pushNamed(
                                '/author/${author.id}',
                                arguments: {'name': author.name},
                              );
                            },
                            child: Text(
                              book.displayAuthors[i].name ?? '',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                                fontSize: 11,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _AddToCartButton extends StatefulWidget {
  final String bookId;

  const _AddToCartButton({required this.bookId});

  @override
  State<_AddToCartButton> createState() => _AddToCartButtonState();
}

class _AddToCartButtonState extends State<_AddToCartButton> {
  bool _adding = false;

  Future<void> _add() async {
    if (context.read<AuthProvider>().userType != UserType.customer) {
      Navigator.pushNamed(context, '/login');
      return;
    }
    final t = AppLocalizations.of(context);
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _adding = true);
    final res = await ApiService.instance.addToCart(widget.bookId);
    if (!mounted) return;
    setState(() => _adding = false);
    messenger.showSnackBar(
      SnackBar(content: Text(res.success ? t.addToCart : res.message)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    return SizedBox(
      width: double.infinity,
      height: 34,
      child: FilledButton.icon(
        onPressed: _adding ? null : _add,
        style: FilledButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: 8),
          textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
        icon: _adding
            ? const SizedBox(
                width: 14,
                height: 14,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.add_shopping_cart, size: 16),
        label: Text(t.addToCart, maxLines: 1, overflow: TextOverflow.ellipsis),
      ),
    );
  }
}
