import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Navy palette (matches the web `--color-navy-*` tokens in `web/src/index.css`).
class AppColors {
  static const navy50 = Color(0xFFF2F5FB);
  static const navy100 = Color(0xFFE3E9F5);
  static const navy200 = Color(0xFFC5D1EA);
  static const navy300 = Color(0xFF9BB0D8);
  static const navy400 = Color(0xFF6B87C0);
  static const navy500 = Color(0xFF4865A6);
  static const navy600 = Color(0xFF344E8A);
  static const navy700 = Color(0xFF283D70);
  static const navy800 = Color(0xFF1F2F58);
  static const navy900 = Color(0xFF172344);
  static const navy950 = Color(0xFF0E162D);

  static const primary = navy900;
  static const primaryHover = navy800;
  static const primaryLight = navy100;
  static const accent = navy500;
  static const accentHover = navy600;
  static const accentSoft = navy100;
  static const text = navy950;
  static const textMuted = Color(0xFF5B6B8C);
  static const border = Color(0xFFDBE2EF);
  static const bg = Color(0xFFF5F7FB);
  static const bgCard = Color(0xFFFFFFFF);
  static const success = Color(0xFF15803D);
  static const discount = Color(0xFFDC2626);
  static const sold = Color(0xFF7F1D1D);

  /// Dark-mode companions.
  static const darkBg = Color(0xFF0B1224);
  static const darkBgCard = Color(0xFF141E38);
  static const darkSurfaceHigh = Color(0xFF1C2A4D);
  static const darkText = navy100;
  static const darkTextMuted = navy300;
  static const darkBorder = Color(0xFF26365E);
  static const darkAccent = navy300;
}

ThemeData buildLightTheme() {
  const bg = AppColors.bg;
  const card = AppColors.bgCard;
  const primary = AppColors.primary;
  const secondary = Color.fromARGB(255, 81, 107, 164);
  const onSurface = AppColors.text;
  const muted = AppColors.textMuted;
  const outline = AppColors.border;
  const error = AppColors.discount;

  final baseText = _cairoTextTheme(ThemeData.light().textTheme);

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: const ColorScheme.light(
      primary: primary,
      onPrimary: Colors.white,
      primaryContainer: AppColors.navy100,
      onPrimaryContainer: AppColors.navy900,
      secondary: secondary,
      onSecondary: Colors.white,
      secondaryContainer: AppColors.navy200,
      onSecondaryContainer: AppColors.navy900,
      tertiary: AppColors.navy400,
      onTertiary: Colors.white,
      surface: card,
      onSurface: onSurface,
      onSurfaceVariant: muted,
      surfaceContainerHighest: AppColors.accentSoft,
      outline: outline,
      error: error,
      onError: Colors.white,
    ),
    scaffoldBackgroundColor: bg,
    cardColor: card,
    dividerColor: outline,
    cardTheme: CardThemeData(
      color: card,
      elevation: 0,
      shadowColor: AppColors.navy950.withValues(alpha: 0.08),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(24),
        side: const BorderSide(color: outline),
      ),
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: bg,
      foregroundColor: onSurface,
      elevation: 0,
      centerTitle: true,
      scrolledUnderElevation: 0,
      surfaceTintColor: Colors.transparent,
      titleTextStyle: GoogleFonts.cairo(
        color: onSurface,
        fontSize: 18,
        fontWeight: FontWeight.w700,
      ),
    ),
    textTheme: baseText.copyWith(
      headlineMedium: baseText.headlineMedium?.copyWith(
        color: onSurface,
        fontSize: 22,
        fontWeight: FontWeight.bold,
      ),
      titleLarge: baseText.titleLarge?.copyWith(
        color: onSurface,
        fontSize: 18,
        fontWeight: FontWeight.w700,
      ),
      titleMedium: baseText.titleMedium?.copyWith(
        color: onSurface,
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: baseText.titleSmall?.copyWith(
        color: onSurface,
        fontSize: 14,
        fontWeight: FontWeight.w700,
      ),
      bodyLarge: baseText.bodyLarge?.copyWith(color: onSurface, fontSize: 16),
      bodyMedium: baseText.bodyMedium?.copyWith(color: muted, fontSize: 14),
      bodySmall: baseText.bodySmall?.copyWith(color: muted, fontSize: 12),
      labelLarge: baseText.labelLarge?.copyWith(
        color: onSurface,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: Colors.white,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        elevation: 0,
        shadowColor: Colors.transparent,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: Colors.white,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: primary,
        side: const BorderSide(color: outline),
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: AppColors.navy600),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: outline),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: secondary, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: AppColors.accentSoft,
      selectedColor: AppColors.navy200,
      labelStyle: GoogleFonts.cairo(color: onSurface, fontSize: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      side: BorderSide.none,
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: secondary),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: card,
      selectedItemColor: primary,
      unselectedItemColor: muted,
      type: BottomNavigationBarType.fixed,
      elevation: 0,
    ),
    floatingActionButtonTheme: const FloatingActionButtonThemeData(
      backgroundColor: primary,
      foregroundColor: Colors.white,
      elevation: 2,
      shape: StadiumBorder(),
    ),
  );
}

ThemeData buildDarkTheme() {
  const bg = AppColors.darkBg;
  const card = AppColors.darkBgCard;
  const primary = AppColors.darkAccent;
  const secondary = AppColors.navy400;
  const onSurface = AppColors.darkText;
  const muted = AppColors.darkTextMuted;
  const outline = AppColors.darkBorder;
  const error = Color(0xFFF87171);

  final baseText = _cairoTextTheme(ThemeData.dark().textTheme);

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    colorScheme: const ColorScheme.dark(
      primary: primary,
      onPrimary: AppColors.navy950,
      primaryContainer: AppColors.navy700,
      onPrimaryContainer: AppColors.navy100,
      secondary: secondary,
      onSecondary: AppColors.navy950,
      secondaryContainer: AppColors.navy800,
      onSecondaryContainer: AppColors.navy100,
      tertiary: AppColors.navy200,
      onTertiary: AppColors.navy950,
      surface: card,
      onSurface: onSurface,
      onSurfaceVariant: muted,
      surfaceContainerHighest: AppColors.darkSurfaceHigh,
      outline: outline,
      error: error,
      onError: AppColors.navy950,
    ),
    scaffoldBackgroundColor: bg,
    cardColor: card,
    dividerColor: outline,
    cardTheme: CardThemeData(
      color: card,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(24),
        side: const BorderSide(color: outline),
      ),
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: bg,
      foregroundColor: onSurface,
      elevation: 0,
      centerTitle: true,
      scrolledUnderElevation: 0,
      surfaceTintColor: Colors.transparent,
      titleTextStyle: GoogleFonts.cairo(
        color: onSurface,
        fontSize: 18,
        fontWeight: FontWeight.w700,
      ),
    ),
    textTheme: baseText.copyWith(
      headlineMedium: baseText.headlineMedium?.copyWith(
        color: onSurface,
        fontSize: 22,
        fontWeight: FontWeight.bold,
      ),
      titleLarge: baseText.titleLarge?.copyWith(
        color: onSurface,
        fontSize: 18,
        fontWeight: FontWeight.w700,
      ),
      titleMedium: baseText.titleMedium?.copyWith(
        color: onSurface,
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: baseText.titleSmall?.copyWith(
        color: onSurface,
        fontSize: 14,
        fontWeight: FontWeight.w700,
      ),
      bodyLarge: baseText.bodyLarge?.copyWith(color: onSurface, fontSize: 16),
      bodyMedium: baseText.bodyMedium?.copyWith(color: muted, fontSize: 14),
      bodySmall: baseText.bodySmall?.copyWith(color: muted, fontSize: 12),
      labelLarge: baseText.labelLarge?.copyWith(
        color: onSurface,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: AppColors.navy950,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        elevation: 0,
        shadowColor: Colors.transparent,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: AppColors.navy950,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: onSurface,
        side: const BorderSide(color: outline),
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(foregroundColor: primary),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: card,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: outline),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: primary, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: AppColors.darkSurfaceHigh,
      selectedColor: AppColors.navy700,
      labelStyle: GoogleFonts.cairo(color: onSurface, fontSize: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      side: BorderSide.none,
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: primary),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: card,
      selectedItemColor: primary,
      unselectedItemColor: muted,
      type: BottomNavigationBarType.fixed,
      elevation: 0,
    ),
    floatingActionButtonTheme: const FloatingActionButtonThemeData(
      backgroundColor: primary,
      foregroundColor: AppColors.navy950,
      elevation: 2,
      shape: StadiumBorder(),
    ),
  );
}

/// google_fonts 9 returns `material_ui` [TextTheme], a different type than
/// `package:flutter/material.dart`. Apply Cairo per style instead.
TextTheme _cairoTextTheme(TextTheme base) {
  return TextTheme(
    displayLarge: GoogleFonts.cairo(textStyle: base.displayLarge),
    displayMedium: GoogleFonts.cairo(textStyle: base.displayMedium),
    displaySmall: GoogleFonts.cairo(textStyle: base.displaySmall),
    headlineLarge: GoogleFonts.cairo(textStyle: base.headlineLarge),
    headlineMedium: GoogleFonts.cairo(textStyle: base.headlineMedium),
    headlineSmall: GoogleFonts.cairo(textStyle: base.headlineSmall),
    titleLarge: GoogleFonts.cairo(textStyle: base.titleLarge),
    titleMedium: GoogleFonts.cairo(textStyle: base.titleMedium),
    titleSmall: GoogleFonts.cairo(textStyle: base.titleSmall),
    bodyLarge: GoogleFonts.cairo(textStyle: base.bodyLarge),
    bodyMedium: GoogleFonts.cairo(textStyle: base.bodyMedium),
    bodySmall: GoogleFonts.cairo(textStyle: base.bodySmall),
    labelLarge: GoogleFonts.cairo(textStyle: base.labelLarge),
    labelMedium: GoogleFonts.cairo(textStyle: base.labelMedium),
    labelSmall: GoogleFonts.cairo(textStyle: base.labelSmall),
  );
}
