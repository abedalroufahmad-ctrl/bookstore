import 'package:flutter/material.dart';

import '../config.dart';
import '../models/book.dart';
import '../utils/weight_format.dart';
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
    return Image.network(
      _resolveCoverUrl(url),
      fit: BoxFit.cover,
      loadingBuilder: (context, child, loadingProgress) {
        if (loadingProgress == null) return child;
        return _buildLogoPlaceholder(context);
      },
      errorBuilder: (_, _, _) => _buildLogoPlaceholder(context),
    );
  }

  static Widget _buildLogoPlaceholder(BuildContext context) {
    return Container(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      alignment: Alignment.center,
      child: Icon(Icons.menu_book_rounded, size: 48, color: Theme.of(context).colorScheme.outline),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final price = book.price;
    final bookDiscount = (book.discountPercent ?? 0).toDouble();
    final finalDiscount = bookDiscount > 0 ? bookDiscount : (globalDiscount ?? 0);
    final discountedPrice = finalDiscount > 0 ? price * (1 - finalDiscount / 100) : price;

    return GestureDetector(
      onTap: onTap,
      child: NeumorphicContainer(
        margin: const EdgeInsets.only(left: 2, right: 2, bottom: 4, top: 2),
        padding: const EdgeInsets.all(8),
        borderRadius: 18,
        child: LayoutBuilder(
          builder: (context, constraints) {
            final hasBoundedHeight = constraints.maxHeight < double.infinity;
            final cover = AspectRatio(
              aspectRatio: 3 / 4,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
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
                            color: book.isSold ? const Color(0xFF7F1D1D) : const Color(0xFF92400E),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            book.isSold
                                ? (Localizations.localeOf(context).languageCode == 'ar' ? 'مباع' : 'Sold')
                                : (Localizations.localeOf(context).languageCode == 'ar' ? 'مستعمل' : 'Used'),
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
                            color: theme.colorScheme.primary,
                            borderRadius: BorderRadius.circular(8),
                            boxShadow: [
                              BoxShadow(
                                color: theme.colorScheme.primary.withValues(alpha: 0.35),
                                blurRadius: 4,
                                offset: const Offset(0, 1),
                              ),
                            ],
                          ),
                          child: Text(
                            bookDiscount > 0
                                ? '${finalDiscount.toInt()}%'
                                : '−${finalDiscount.toInt()}%',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: theme.colorScheme.onPrimary,
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
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (hasBoundedHeight) Flexible(child: cover) else cover,
                const SizedBox(height: 6),
                Text(
                  book.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                  ),
                ),
                if (book.displayPublishers.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        for (var i = 0; i < book.displayPublishers.length && i < 3; i++) ...[
                          if (i > 0)
                            Text(
                              '، ',
                              style: theme.textTheme.bodySmall?.copyWith(fontSize: 10),
                            ),
                          Builder(
                            builder: (context) {
                              final p = book.displayPublishers[i];
                              final label = i == 0 ? '🏢 ${p.name}' : (p.name ?? '');
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
                                      color: theme.colorScheme.primary,
                                    ),
                                  ),
                                );
                              }
                              return Text(
                                label,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  fontSize: 10,
                                  color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                                ),
                              );
                            },
                          ),
                        ],
                        if (book.displayPublishers.length > 3)
                          Text(
                            ' +${book.displayPublishers.length - 3}',
                            style: theme.textTheme.bodySmall?.copyWith(fontSize: 10),
                          ),
                      ],
                    ),
                  ),
                if (book.warehouse != null && book.warehouse!.name != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: book.warehouse!.id.isNotEmpty
                        ? GestureDetector(
                            behavior: HitTestBehavior.opaque,
                            onTap: () {
                              Navigator.of(context).pushNamed(
                                '/warehouse/${book.warehouse!.id}',
                                arguments: {'name': book.warehouse!.name},
                              );
                            },
                            child: Text(
                              '🏭 ${book.warehouse!.name}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall?.copyWith(
                                fontSize: 10,
                                color: theme.colorScheme.primary,
                              ),
                            ),
                          )
                        : Text(
                            '🏭 ${book.warehouse!.name}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              fontSize: 10,
                              color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                            ),
                          ),
                  ),
                if (book.displayAuthors.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Wrap(
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        for (var i = 0; i < book.displayAuthors.length && i < 3; i++) ...[
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
                                color: theme.colorScheme.primary,
                                fontSize: 11,
                                decoration: TextDecoration.none,
                              ),
                            ),
                          ),
                        ],
                        if (book.displayAuthors.length > 3)
                          Text(
                            ' +${book.displayAuthors.length - 3}',
                            style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
                          ),
                      ],
                    ),
                  ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        '\$${discountedPrice.toStringAsFixed(2)}',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (finalDiscount > 0) ...[
                      const SizedBox(width: 4),
                      Flexible(
                        child: Text(
                          '\$${price.toStringAsFixed(2)}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurface.withValues(alpha: 0.5),
                            decoration: TextDecoration.lineThrough,
                            fontSize: 11,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ],
                ),
                if (book.weight != null && book.weight! > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      formatWeight(book.weight, weightUnit),
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontSize: 10,
                        color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                      ),
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
