/// Book weights are stored in grams. Convert for display only.
String formatWeight(double? weightG, [String unit = 'kg']) {
  if (weightG == null) return '';
  const factors = {
    'g': 1.0,
    'kg': 0.001,
    'lb': 1 / 453.592,
    'oz': 1 / 28.3495,
  };
  final value = weightG * (factors[unit] ?? 0.001);
  final decimals = value >= 100
      ? 0
      : value >= 10
          ? 1
          : value >= 1
              ? 2
              : 3;
  return '${value.toStringAsFixed(decimals)} $unit';
}

double lineWeightGrams(num? unitWeightG, num? quantity) {
  if (unitWeightG == null) return 0;
  return unitWeightG.toDouble() * (quantity ?? 1).toDouble();
}
