import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../config/app_config.dart';
import '../providers.dart';

/// The brand logo, legible on the dark theme whatever the artwork:
/// `logo_dark.png` when the brand ships one (`BRAND_LOGO_DARK` define, set by
/// tool/build_brand.sh from the file's presence), otherwise `logo.png` on a
/// light rounded plate so dark lettering never vanishes into the background.
class BrandLogo extends ConsumerWidget {
  const BrandLogo({super.key, required this.height, this.maxWidth = 280});
  final double height;
  final double maxWidth;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(appConfigProvider);
    final box = BoxConstraints(maxHeight: height, maxWidth: maxWidth);
    if (config.hasDarkLogo) {
      return ConstrainedBox(
        constraints: box,
        child: Image.asset(config.logoDarkAsset, key: const Key('brand-logo-dark'), fit: BoxFit.contain, errorBuilder: (_, _, _) => _plate(config, height)),
      );
    }
    return _plate(config, height);
  }

  Widget _plate(AppConfig config, double height) {
    final box = BoxConstraints(maxHeight: height, maxWidth: maxWidth);
    return Container(
      key: const Key('brand-logo-plate'),
      constraints: box,
      padding: EdgeInsets.all(height * 0.12),
      decoration: BoxDecoration(color: const Color(0xFFF4F4F6), borderRadius: BorderRadius.circular(height * 0.18)),
      child: Image.asset(config.logoAsset, fit: BoxFit.contain),
    );
  }
}

/// Logo plus the app name, unless the logo already carries the name
/// (`LOGO_HAS_NAME`), in which case only the logo is shown, larger.
class BrandHeader extends ConsumerWidget {
  const BrandHeader({super.key, this.logoHeight = 40, this.wordmarkHeight = 56, this.textStyle});
  final double logoHeight;
  final double wordmarkHeight;
  final TextStyle? textStyle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(appConfigProvider);
    if (config.logoHasName) return BrandLogo(height: wordmarkHeight);
    return Row(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        BrandLogo(height: logoHeight, maxWidth: logoHeight * 2),
        const SizedBox(width: 10),
        Flexible(child: Text(config.appName, key: const Key('brand-name'), maxLines: 1, overflow: TextOverflow.ellipsis, style: textStyle ?? Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700))),
      ],
    );
  }
}

/// Keeps a stable reference for tests and callers that need the asset path.
extension BrandAssets on AppConfig {
  String get logoDarkAsset => 'assets/branding/logo_dark.png';
}
