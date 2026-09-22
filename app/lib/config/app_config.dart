import 'package:flutter/material.dart';

/// Build flavour (environment). Selected by the entry point (`main_dev.dart` / `main_prod.dart`).
enum AppFlavor { dev, prod }

/// Brand-level billing override (`BILLING_UI` in brand.json): `auto` follows
/// the server's `features.billing`, `on` / `off` force it for the build.
enum BillingUi {
  auto,
  on,
  off;

  static BillingUi parse(String v) => switch (v.trim().toLowerCase()) {
        'on' || 'true' || '1' => BillingUi.on,
        'off' || 'false' || '0' => BillingUi.off,
        _ => BillingUi.auto,
      };
}

/// Single source of truth for branding and environment.
///
/// Every value is a **build-time input** supplied with
/// `--dart-define-from-file=brands/<brand>/brand.json` (see docs/WHITE_LABEL.md).
/// The defaults below are the `caritv` development placeholder so that a plain
/// `flutter run` still works. Nothing in this file needs editing to add a brand.
class AppConfig {
  const AppConfig({
    required this.flavor,
    required this.brandKey,
    required this.appName,
    required this.applicationId,
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
    required this.cleartextHosts,
    this.billingUi = BillingUi.auto,
  });

  final AppFlavor flavor;

  /// Folder name under `brands/`.
  final String brandKey;

  /// Display name shown in-app (the launcher label comes from Android/iOS resources).
  final String appName;

  /// Android application id / iOS bundle id (informational in Dart; Gradle/Xcode set the real one).
  final String applicationId;

  /// Site origin, no trailing slash, no path. The API lives at `$apiBaseUrl/api/v1`.
  final String apiBaseUrl;

  final Color primaryColor;
  final Color accentColor;
  final Color backgroundColor;
  final Color surfaceColor;

  /// Asset path of the logo shown on the login screen and splash.
  final String logoAsset;

  /// Public legal pages (served by the backend).
  final String privacyUrl;
  final String termsUrl;
  final String deleteAccountUrl;

  /// Value sent as `platform` on analytics and ad calls so test traffic can be
  /// filtered out. `mobile` for prod, `mobile-dev` for dev.
  final String platformTag;

  /// Hosts allowed to serve plain `http://` streams (from `CLEARTEXT_HOSTS`).
  /// Enforced natively (Android network security config / iOS ATS); kept here
  /// for diagnostics only.
  final List<String> cleartextHosts;

  /// Billing surfaces: follow the server (`auto`), or force on / off for this
  /// brand (a store build can set `off` regardless of the server).
  final BillingUi billingUi;

  String get apiV1 => '$apiBaseUrl/api/v1';
  bool get isDev => flavor == AppFlavor.dev;

  // ---------------------------------------------------------------------------
  // Build-time defines (brand.json keys). Defaults = caritv dev placeholder.
  // ---------------------------------------------------------------------------
  static const _brandKey = String.fromEnvironment('BRAND_KEY', defaultValue: 'caritv');
  static const _appName = String.fromEnvironment('APP_NAME', defaultValue: 'CARI TV');
  static const _applicationId = String.fromEnvironment('APPLICATION_ID', defaultValue: 'net.caritech.caritv');
  static const _apiDev = String.fromEnvironment('API_BASE_URL_DEV', defaultValue: 'https://player.caritech.net');
  static const _apiProd = String.fromEnvironment('API_BASE_URL_PROD', defaultValue: 'https://player.caritech.net');
  static const _primary = String.fromEnvironment('PRIMARY_COLOR', defaultValue: '#6366F1');
  static const _accent = String.fromEnvironment('ACCENT_COLOR', defaultValue: '#22C55E');
  static const _background = String.fromEnvironment('BACKGROUND_COLOR', defaultValue: '#0F172A');
  static const _surface = String.fromEnvironment('SURFACE_COLOR', defaultValue: '#1E293B');
  static const _privacy = String.fromEnvironment('PRIVACY_URL', defaultValue: 'https://player.caritech.net/privacy');
  static const _terms = String.fromEnvironment('TERMS_URL', defaultValue: 'https://player.caritech.net/privacy#terms');
  static const _delete = String.fromEnvironment('DELETE_ACCOUNT_URL', defaultValue: 'https://player.caritech.net/delete-account');
  static const _platformProd = String.fromEnvironment('PLATFORM_TAG_PROD', defaultValue: 'mobile');
  static const _cleartext = String.fromEnvironment('CLEARTEXT_HOSTS', defaultValue: '');
  static const _billingUi = String.fromEnvironment('BILLING_UI', defaultValue: 'auto');

  /// Builds the config for [flavor] from the build-time defines.
  factory AppConfig.forFlavor(AppFlavor flavor) {
    final isDev = flavor == AppFlavor.dev;
    return AppConfig(
      flavor: flavor,
      brandKey: _brandKey,
      appName: isDev ? '$_appName (dev)' : _appName,
      applicationId: isDev ? '$_applicationId.dev' : _applicationId,
      apiBaseUrl: _stripSlash(isDev ? _apiDev : _apiProd),
      primaryColor: parseHexColor(_primary, const Color(0xFF6366F1)),
      accentColor: parseHexColor(_accent, const Color(0xFF22C55E)),
      backgroundColor: parseHexColor(_background, const Color(0xFF0F172A)),
      surfaceColor: parseHexColor(_surface, const Color(0xFF1E293B)),
      logoAsset: 'assets/branding/logo.png',
      privacyUrl: _privacy,
      termsUrl: _terms,
      deleteAccountUrl: _delete,
      platformTag: isDev ? '$_platformProd-dev' : _platformProd,
      cleartextHosts: parseHostList(_cleartext),
      billingUi: BillingUi.parse(_billingUi),
    );
  }

  /// Same config with another billing override (tests, previews).
  AppConfig withBillingUi(BillingUi value) => AppConfig(
        flavor: flavor,
        brandKey: brandKey,
        appName: appName,
        applicationId: applicationId,
        apiBaseUrl: apiBaseUrl,
        primaryColor: primaryColor,
        accentColor: accentColor,
        backgroundColor: backgroundColor,
        surfaceColor: surfaceColor,
        logoAsset: logoAsset,
        privacyUrl: privacyUrl,
        termsUrl: termsUrl,
        deleteAccountUrl: deleteAccountUrl,
        platformTag: platformTag,
        cleartextHosts: cleartextHosts,
        billingUi: value,
      );

  /// Development placeholder brand (what `flutter run` uses).
  static AppConfig get dev => AppConfig.forFlavor(AppFlavor.dev);
  static AppConfig get prod => AppConfig.forFlavor(AppFlavor.prod);

  static String _stripSlash(String s) => s.trim().replaceFirst(RegExp(r'/+$'), '');

  /// Parses `#RRGGBB`, `RRGGBB`, `#AARRGGBB` or `0xAARRGGBB`.
  static Color parseHexColor(String input, Color fallback) {
    var h = input.trim().replaceFirst('#', '').replaceFirst(RegExp(r'^0x', caseSensitive: false), '');
    if (h.length == 6) h = 'FF$h';
    if (h.length != 8) return fallback;
    final v = int.tryParse(h, radix: 16);
    return v == null ? fallback : Color(v);
  }

  /// Comma/space separated host list → trimmed, lower-cased, de-duplicated.
  static List<String> parseHostList(String input) => input
      .split(RegExp(r'[,\s]+'))
      .map((h) => h.trim().toLowerCase())
      .where((h) => h.isNotEmpty)
      .toSet()
      .toList(growable: false);
}
