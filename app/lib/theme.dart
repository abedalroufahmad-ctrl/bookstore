import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

ThemeData buildTokyoNightLightTheme() {
  const bg0 = Color(0xFFe1e2e7);
  const bg1 = Color(0xFFd5d6db);
  const fg0 = Color(0xFF3760bf);
  const fg1 = Color(0xFF4c505e);
  const primary = Color(0xFF2e7de9); // Blue
  const secondary = Color(0xFF9854f1); // Purple
  const red = Color(0xFFf52a65);
  const outline = Color(0xFF9699a3);

  return ThemeData(
    useMaterial3: true,
    colorScheme: const ColorScheme.light(
      primary: primary,
      onPrimary: bg0,
      secondary: secondary,
      onSecondary: bg0,
      surface: bg0,
      onSurface: fg0,
      surfaceContainerHighest: bg1,
      outline: outline,
      error: red,
      onError: bg0,
    ),
    scaffoldBackgroundColor: bg0,
    cardColor: bg1,
    cardTheme: CardThemeData(
      color: bg1,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: bg0,
      foregroundColor: fg0,
      elevation: 0,
      centerTitle: true,
      scrolledUnderElevation: 1,
      surfaceTintColor: Colors.transparent,
    ).copyWith(
      titleTextStyle: GoogleFonts.cairo(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
    ),
    textTheme: GoogleFonts.cairoTextTheme().copyWith(
      headlineMedium: const TextStyle(
        color: fg0,
        fontSize: 22,
        fontWeight: FontWeight.bold,
      ),
      titleLarge: const TextStyle(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
      titleMedium: const TextStyle(
        color: fg0,
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: const TextStyle(
        color: fg0,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: const TextStyle(color: fg0, fontSize: 16),
      bodyMedium: const TextStyle(color: fg1, fontSize: 14),
      bodySmall: const TextStyle(color: outline, fontSize: 12),
      labelLarge: const TextStyle(
        color: fg0,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: bg0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        elevation: 0,
        shadowColor: Colors.transparent,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: bg0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: bg0,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: outline),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: primary, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: bg1,
      selectedItemColor: primary,
      unselectedItemColor: outline,
      type: BottomNavigationBarType.fixed,
      elevation: 0,
    ),
  );
}

ThemeData buildTokyoNightDarkTheme() {
  const bg0 = Color(0xFF1a1b26);
  const bg1 = Color(0xFF16161e);
  const fg0 = Color(0xFFc0caf5);
  const fg1 = Color(0xFFa9b1d6);
  const primary = Color(0xFF7aa2f7); // Blue
  const secondary = Color(0xFFbb9af7); // Purple
  const red = Color(0xFFf7768e);
  const outline = Color(0xFF565f89);

  return ThemeData(
    useMaterial3: true,
    colorScheme: const ColorScheme.dark(
      primary: primary,
      onPrimary: bg0,
      secondary: secondary,
      onSecondary: bg0,
      surface: bg0,
      onSurface: fg0,
      surfaceContainerHighest: bg1,
      outline: outline,
      error: red,
      onError: bg0,
    ),
    scaffoldBackgroundColor: bg0,
    cardColor: bg1,
    cardTheme: CardThemeData(
      color: bg1,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: bg0,
      foregroundColor: fg0,
      elevation: 0,
      centerTitle: true,
      scrolledUnderElevation: 1,
      surfaceTintColor: Colors.transparent,
    ).copyWith(
      titleTextStyle: GoogleFonts.cairo(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
    ),
    textTheme: GoogleFonts.cairoTextTheme().copyWith(
      headlineMedium: const TextStyle(
        color: fg0,
        fontSize: 22,
        fontWeight: FontWeight.bold,
      ),
      titleLarge: const TextStyle(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
      titleMedium: const TextStyle(
        color: fg0,
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: const TextStyle(
        color: fg0,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: const TextStyle(color: fg0, fontSize: 16),
      bodyMedium: const TextStyle(color: fg1, fontSize: 14),
      bodySmall: const TextStyle(color: outline, fontSize: 12),
      labelLarge: const TextStyle(
        color: fg0,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: bg0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        elevation: 0,
        shadowColor: Colors.transparent,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: bg0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: bg0,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: outline),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: primary, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: bg1,
      selectedItemColor: primary,
      unselectedItemColor: outline,
      type: BottomNavigationBarType.fixed,
      elevation: 0,
    ),
  );
}
