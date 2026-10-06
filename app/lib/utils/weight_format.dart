/// Book weights are stored in grams. Convert for display only.
String formatWeight(double? weightG, [String unit = 'kg']) {
  if (weightG == null) return '';
  final value = gramsToDisplayValue(weightG, unit);
  final decimals = value >= 100
      ? 0
      : value >= 10
          ? 1
          : value >= 1
              ? 2
              : 3;
  return '${value.toStringAsFixed(decimals)} $unit';
}

double gramsToDisplayValue(double weightG, [String unit = 'kg']) {
  const factors = {
    'g': 1.0,
    'kg': 0.001,
    'lb': 1 / 453.592,
    'oz': 1 / 28.3495,
  };
  return weightG * (factors[unit] ?? 0.001);
}

/// Parse a display-unit value back to grams for storage.
double? displayToGrams(String? raw, [String unit = 'kg']) {
  if (raw == null) return null;
  final text = raw.trim().replaceAll(',', '.');
  if (text.isEmpty) return null;
  final value = double.tryParse(text);
  if (value == null) return null;
  switch (unit) {
    case 'g':
      return value;
    case 'lb':
      return value * 453.592;
    case 'oz':
      return value * 28.3495;
    case 'kg':
    default:
      return value * 1000;
  }
}

String gramsToDisplayInput(double? weightG, [String unit = 'kg']) {
  if (weightG == null) return '';
  final value = gramsToDisplayValue(weightG, unit);
  if (value == value.roundToDouble()) return value.toStringAsFixed(0);
  return value.toStringAsFixed(3).replaceFirst(RegExp(r'\.?0+$'), '');
}

double lineWeightGrams(num? unitWeightG, num? quantity) {
  if (unitWeightG == null) return 0;
  return unitWeightG.toDouble() * (quantity ?? 1).toDouble();
}
