import 'package:flutter/widgets.dart';

/// Pixel width to decode a network image at, so grid thumbnails don't keep full-size
/// (often 2000px) covers in the image cache. Returns null when the width is unbounded.
int? decodeWidthFor(BuildContext context, BoxConstraints constraints) {
  if (!constraints.hasBoundedWidth || constraints.maxWidth <= 0) return null;
  return (constraints.maxWidth * MediaQuery.devicePixelRatioOf(context)).round();
}
