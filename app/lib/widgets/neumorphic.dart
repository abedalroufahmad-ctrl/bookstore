import 'package:flutter/material.dart';

import '../theme.dart';

/// Soft floating card used across the Flamingo shop UI.
class NeumorphicContainer extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;
  final double borderRadius;
  final Color? color;
  final double depth;

  const NeumorphicContainer({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(12),
    this.margin = EdgeInsets.zero,
    this.borderRadius = 24,
    this.color,
    this.depth = 4,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final baseColor = color ?? theme.cardColor;
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: baseColor,
        borderRadius: BorderRadius.circular(borderRadius),
        border: Border.all(
          color: isDark ? FlamingoColors.darkBorder : FlamingoColors.border,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.35 : 0.06),
            offset: const Offset(0, 8),
            blurRadius: 24,
            spreadRadius: -4,
          ),
        ],
      ),
      child: child,
    );
  }
}
