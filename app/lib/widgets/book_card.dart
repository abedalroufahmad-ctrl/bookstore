import 'package:flutter/material.dart';

import '../config.dart';
import '../models/book.dart';
import 'neumorphic.dart';

String _resolveCoverUrl(String path) {
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  final origin = Uri.parse(apiBaseUrl).origin;
  return path.startsWith('/') ? '$origin$path' : '$origin/$path';
}

class BookCard extends StatelessWidget {
  final Book book;
  final double? globalDiscount;
  final VoidCallback? onTap;

  const BookCard({
    super.key,
    required this.book,
    this.globalDiscount,
    this.onTap,
  });

  static Widget _buildCoverImage(BuildContext context, String? imageUrl, ThemeData theme) {
    final url = imageUrl?.trim();
    if (url == null || url.isEmpty) {
      return Container(
        color: theme.colorScheme.surfaceContainerHighest.withOpacity(0.6),
        child: Icon(Icons.menu_book_outlined, size: 40, color: theme.colorScheme.outline),
      );
    }
    return Image.network(
      _resolveCoverUrl(url),
      fit: BoxFit.cover,
      loadingBuilder: (context, child, loadingProgress) {
        if (loadingProgress == null) return child;
        return Container(
          color: theme.colorScheme.surfaceContainerHighest.withOpacity(0.6),
          child: child,
        );
      },
      errorBuilder: (_, __, ___) => Container(
        color: theme.colorScheme.surfaceContainerHighest.withOpacity(0.6),
        child: Icon(Icons.menu_book_outlined, size: 40, color: theme.colorScheme.outline),
      ),
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
                                color: theme.colorScheme.primary.withOpacity(0.35),
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
              if (book.authors != null && book.authors!.isNotEmpty)
                GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: () {
                    final author = book.authors!.first;
                    Navigator.of(context).pushNamed(
                      '/author/${author.id}',
                      arguments: {'name': author.name},
                    );
                  },
                  child: Text(
                    book.authors!.first.name ?? '',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.primary,
                      fontSize: 11,
                      decoration: TextDecoration.underline,
                      decorationColor: theme.colorScheme.primary,
                    ),
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
                          color: theme.colorScheme.onSurface.withOpacity(0.5),
                          decoration: TextDecoration.lineThrough,
                          fontSize: 11,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ],
              ),
            ],
            );
          },
        ),
      ),
    );
  }
}
