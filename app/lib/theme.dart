import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Flamingo palette (matches web shop tokens).
class FlamingoColors {
  static const primary = Color(0xFF1A1A1A);
  static const primaryHover = Color(0xFF2D2D2D);
  static const primaryLight = Color(0xFFF5F0EE);
  static const accent = Color(0xFFE8A09A);
  static const accentHover = Color(0xFFD98982);
  static const accentSoft = Color(0xFFFDE8E4);
  static const accentSky = Color(0xFF7BCBD8);
  static const accentGold = Color(0xFFF5A623);
  static const earth = Color(0xFF70503B);
  static const sage = Color(0xFFBFC4B2);
  static const text = Color(0xFF1A1A1A);
  static const textMuted = Color(0xFF757575);
  static const border = Color(0xFFE5E5E5);
  static const bg = Color(0xFFFFF8F6);
  static const bgCard = Color(0xFFFFFFFF);
  static const success = Color(0xFF6B8F71);
  static const discount = Color(0xFFC45C4A);

  /// Dark-mode companions.
  static const darkBg = Color(0xFF1C1918);
  static const darkBgCard = Color(0xFF2A2524);
  static const darkText = Color(0xFFF5F0EE);
  static const darkTextMuted = Color(0xFFB0A8A4);
  static const darkBorder = Color(0xFF3D3634);
  static const darkAccent = Color(0xFFE8A09A);
}

ThemeData buildFlamingoLightTheme() {
  const bg = FlamingoColors.bg;
  const card = FlamingoColors.bgCard;
  const primary = FlamingoColors.primary;
  const secondary = FlamingoColors.accent;
  const onSurface = FlamingoColors.text;
  const muted = FlamingoColors.textMuted;
  const outline = FlamingoColors.border;
  const error = FlamingoColors.discount;

  final baseText = GoogleFonts.cairoTextTheme();

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: const ColorScheme.light(
      primary: primary,
      onPrimary: Colors.white,
      secondary: secondary,
      onSecondary: primary,
      tertiary: FlamingoColors.accentSky,
      onTertiary: primary,
      surface: card,
      onSurface: onSurface,
      surfaceContainerHighest: FlamingoColors.accentSoft,
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
      shadowColor: Colors.black.withValues(alpha: 0.08),
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
      backgroundColor: FlamingoColors.accentSoft,
      selectedColor: FlamingoColors.accent,
      labelStyle: GoogleFonts.cairo(color: onSurface, fontSize: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      side: BorderSide.none,
    ),
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

ThemeData buildFlamingoDarkTheme() {
  const bg = FlamingoColors.darkBg;
  const card = FlamingoColors.darkBgCard;
  const primary = FlamingoColors.darkAccent;
  const secondary = FlamingoColors.accentSky;
  const onSurface = FlamingoColors.darkText;
  const muted = FlamingoColors.darkTextMuted;
  const outline = FlamingoColors.darkBorder;
  const error = FlamingoColors.discount;

  final baseText = GoogleFonts.cairoTextTheme(ThemeData.dark().textTheme);

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    colorScheme: const ColorScheme.dark(
      primary: primary,
      onPrimary: FlamingoColors.primary,
      secondary: secondary,
      onSecondary: FlamingoColors.primary,
      tertiary: FlamingoColors.accentGold,
      onTertiary: FlamingoColors.primary,
      surface: card,
      onSurface: onSurface,
      surfaceContainerHighest: Color(0xFF35302E),
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
        foregroundColor: FlamingoColors.primary,
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        elevation: 0,
        shadowColor: Colors.transparent,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: FlamingoColors.primary,
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
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: card,
      selectedItemColor: primary,
      unselectedItemColor: muted,
      type: BottomNavigationBarType.fixed,
      elevation: 0,
    ),
    floatingActionButtonTheme: const FloatingActionButtonThemeData(
      backgroundColor: primary,
      foregroundColor: FlamingoColors.primary,
      elevation: 2,
      shape: StadiumBorder(),
    ),
  );
}

/// Back-compat aliases used by older call sites.
ThemeData buildTokyoNightLightTheme() => buildFlamingoLightTheme();
ThemeData buildTokyoNightDarkTheme() => buildFlamingoDarkTheme();
