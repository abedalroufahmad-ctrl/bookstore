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
    final baseColor = color ?? Theme.of(context).cardColor;
    final d = depth.clamp(1, 10).toDouble();
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: baseColor,
        borderRadius: BorderRadius.circular(borderRadius),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.08),
            offset: Offset(d, d),
            blurRadius: d * 2.5,
          ),
          BoxShadow(
            color: Colors.white.withOpacity(0.85),
            offset: Offset(-d * 0.8, -d * 0.8),
            blurRadius: d * 2.5,
          ),
        ],
      ),
      child: child,
    );
  }
}

