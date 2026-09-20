import 'package:flutter/material.dart';

/// Build flavour. Selected by the entry point (`main_dev.dart` / `main_prod.dart`).
enum AppFlavor { dev, prod }

/// Single source of truth for branding and environment.
///
/// To whitelabel the app: change the values here and replace the images in
/// `assets/branding/` (see README "Branding"). Nothing else needs to change.
class AppConfig {
  const AppConfig({
    required this.flavor,
    required this.appName,
    required this.apiBaseUrl,
    required this.primaryColor,
    required this.accentColor,
    required this.backgroundColor,
    required this.surfaceColor,
    required this.logoAsset,
    required this.privacyUrl,
    required this.termsUrl,
    required this.deleteAccountUrl,
    required this.platformTag,
  });

  final AppFlavor flavor;

  /// Display name shown in-app (the launcher label comes from Android/iOS resources).
  final String appName;

  /// Site origin, no trailing slash, no path. The API lives at `$apiBaseUrl/api/v1`.
  final String apiBaseUrl;

  final Color primaryColor;
  final Color accentColor;
  final Color backgroundColor;
  final Color surfaceColor;

  /// Asset path of the logo shown on the login screen and app bar.
  final String logoAsset;

  /// Public legal pages (served by the backend).
  final String privacyUrl;
  final String termsUrl;
  final String deleteAccountUrl;

  /// Value sent as `platform` on analytics and ad calls so test traffic can be
  /// filtered out. `mobile` for prod, `mobile-dev` for dev.
  final String platformTag;

  String get apiV1 => '$apiBaseUrl/api/v1';
  bool get isDev => flavor == AppFlavor.dev;

  // ---------------------------------------------------------------------------
  // Flavours. Only ONE host was provided, so dev and prod point at the same
  // site for now; change `apiBaseUrl` in `prod` when a production host exists.
  // ---------------------------------------------------------------------------

  static const _site = 'https://player.caritech.net';

  static const dev = AppConfig(
    flavor: AppFlavor.dev,
    appName: 'CARI TV (dev)',
    apiBaseUrl: _site,
    primaryColor: Color(0xFF6366F1),
    accentColor: Color(0xFF22C55E),
    backgroundColor: Color(0xFF0F172A),
    surfaceColor: Color(0xFF1E293B),
    logoAsset: 'assets/branding/logo.png',
    privacyUrl: '$_site/privacy',
    termsUrl: '$_site/privacy#terms',
    deleteAccountUrl: '$_site/delete-account',
    platformTag: 'mobile-dev',
  );

  static const prod = AppConfig(
    flavor: AppFlavor.prod,
    appName: 'CARI TV',
    apiBaseUrl: _site,
    primaryColor: Color(0xFF6366F1),
    accentColor: Color(0xFF22C55E),
    backgroundColor: Color(0xFF0F172A),
    surfaceColor: Color(0xFF1E293B),
    logoAsset: 'assets/branding/logo.png',
    privacyUrl: '$_site/privacy',
    termsUrl: '$_site/privacy#terms',
    deleteAccountUrl: '$_site/delete-account',
    platformTag: 'mobile',
  );
}
