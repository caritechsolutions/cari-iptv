import 'package:flutter/material.dart';

import '../../config/app_config.dart';

/// Dark theme derived from [AppConfig] colours (matches the admin palette).
ThemeData buildAppTheme(AppConfig config) {
  final scheme = ColorScheme.dark(
    primary: config.primaryColor,
    secondary: config.accentColor,
    surface: config.surfaceColor,
    error: const Color(0xFFEF4444),
    onPrimary: Colors.white,
    onSurface: const Color(0xFFEEF0F6),
  );

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: config.backgroundColor,
    canvasColor: config.backgroundColor,
    appBarTheme: AppBarTheme(
      backgroundColor: config.backgroundColor,
      foregroundColor: scheme.onSurface,
      elevation: 0,
      centerTitle: false,
    ),
    cardTheme: CardThemeData(
      color: config.surfaceColor,
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: config.surfaceColor,
      indicatorColor: config.primaryColor.withValues(alpha: 0.25),
      labelTextStyle: WidgetStateProperty.all(
        const TextStyle(fontSize: 11, fontWeight: FontWeight.w500),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: config.surfaceColor,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide.none,
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: config.surfaceColor,
      contentTextStyle: TextStyle(color: scheme.onSurface),
    ),
    dividerColor: Colors.white12,
  );
}
