import 'package:flutter/material.dart';

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
    this.borderRadius = 16,
    this.color,
    this.depth = 4,
  });

  @override
  Widget build(BuildContext context) {
    final baseColor = color ?? const Color(0xFFE0E5EC);
    final d = depth.clamp(1, 12).toDouble();
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: baseColor,
        borderRadius: BorderRadius.circular(borderRadius),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.12),
            offset: Offset(d, d),
            blurRadius: d * 3,
          ),
          BoxShadow(
            color: Colors.white.withOpacity(0.9),
            offset: Offset(-d, -d),
            blurRadius: d * 3,
          ),
        ],
      ),
      child: child,
    );
  }
}

