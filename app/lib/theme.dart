import 'package:flutter/material.dart';

ThemeData buildGruvboxLightTheme() {
  const bg0 = Color(0xFFfbf1c7);
  const bg1 = Color(0xFFebdbb2);
  const fg0 = Color(0xFF282828);
  const fg1 = Color(0xFF3c3836);
  const primary = Color(0xFF45707a); // Blue
  const secondary = Color(0xFFc35e0a); // Orange
  const red = Color(0xFFc14a4a);
  const outline = Color(0xFFa89984);

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
      titleTextStyle: TextStyle(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
    ),
    textTheme: const TextTheme(
      headlineMedium: TextStyle(
        color: fg0,
        fontSize: 22,
        fontWeight: FontWeight.bold,
      ),
      titleLarge: TextStyle(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
      titleMedium: TextStyle(
        color: fg0,
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: TextStyle(
        color: fg0,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: TextStyle(color: fg0, fontSize: 16),
      bodyMedium: TextStyle(color: fg1, fontSize: 14),
      bodySmall: TextStyle(color: outline, fontSize: 12),
      labelLarge: TextStyle(
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

ThemeData buildGruvboxDarkTheme() {
  const bg0 = Color(0xFF282828);
  const bg1 = Color(0xFF3c3836);
  const fg0 = Color(0xFFfbf1c7);
  const fg1 = Color(0xFFebdbb2);
  const primary = Color(0xFF83a598); // Blue
  const secondary = Color(0xFFfe8019); // Orange
  const red = Color(0xFFfb4934);
  const outline = Color(0xFFa89984);

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
      titleTextStyle: TextStyle(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
    ),
    textTheme: const TextTheme(
      headlineMedium: TextStyle(
        color: fg0,
        fontSize: 22,
        fontWeight: FontWeight.bold,
      ),
      titleLarge: TextStyle(
        color: fg0,
        fontSize: 18,
        fontWeight: FontWeight.w600,
      ),
      titleMedium: TextStyle(
        color: fg0,
        fontSize: 16,
        fontWeight: FontWeight.w600,
      ),
      titleSmall: TextStyle(
        color: fg0,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: TextStyle(color: fg0, fontSize: 16),
      bodyMedium: TextStyle(color: fg1, fontSize: 14),
      bodySmall: TextStyle(color: outline, fontSize: 12),
      labelLarge: TextStyle(
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
