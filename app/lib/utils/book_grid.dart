import 'dart:math' as math;

import 'package:flutter/material.dart';

class CatalogLayout {
  const CatalogLayout({required this.perPage, required this.perRow});

  final int perPage;
  final int perRow;
}

int _clamp(int value, int min, int max) => math.max(min, math.min(max, value));

int _readInt(Map<String, dynamic>? settings, String key, int fallback, int min, int max) {
  final raw = settings?[key];
  if (raw is num) return _clamp(raw.round(), min, max);
  if (raw is String) {
    final parsed = int.tryParse(raw);
    if (parsed != null) return _clamp(parsed, min, max);
  }
  return fallback;
}

CatalogLayout catalogLayoutFromSettings(Map<String, dynamic>? settings, String kind) {
  final defaults = switch (kind) {
    'authors' => (28, 4),
    'categories' => (28, 3),
    'warehouses' => (28, 3),
    _ => (27, 5),
  };
  final pageKey = '${kind}_per_page';
  final rowKey = '${kind}_per_row';
  final legacyPage = kind == 'books' ? _readInt(settings, 'catalog_items_per_page', defaults.$1, 1, 100) : defaults.$1;
  return CatalogLayout(
    perPage: _readInt(settings, pageKey, legacyPage, 1, 100),
    perRow: _readInt(settings, rowKey, defaults.$2, 1, 8),
  );
}

/// Cap admin row count so cards stay readable on a narrow screen.
int effectiveCatalogColumns(double width, int perRow) {
  final maxByWidth = width >= 1100
      ? 5
      : width >= 850
          ? 4
          : width >= 600
              ? 3
              : 2;
  return math.min(_clamp(perRow, 1, 8), maxByWidth);
}

SliverGridDelegate catalogGridDelegate({
  required double width,
  required int perRow,
  double childAspectRatio = 0.52,
  double spacing = 8,
}) {
  return SliverGridDelegateWithFixedCrossAxisCount(
    crossAxisCount: effectiveCatalogColumns(width, perRow),
    mainAxisSpacing: spacing,
    crossAxisSpacing: spacing,
    childAspectRatio: childAspectRatio,
  );
}

/// @deprecated Prefer [catalogGridDelegate] with admin settings.
SliverGridDelegate bookGridDelegateForWidth(double width) {
  return catalogGridDelegate(width: width, perRow: 5);
}
