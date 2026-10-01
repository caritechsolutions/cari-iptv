// Brand logo on dark screens: logo_dark.png when the brand ships one, a light
// plate otherwise; the app name is printed beside the logo only when the
// artwork does not already carry it (LOGO_HAS_NAME).
import 'package:cari_tv/config/app_config.dart';
import 'package:cari_tv/core/providers.dart';
import 'package:cari_tv/core/widgets/brand_logo.dart';
import 'package:cari_tv/features/auth/ui/auth_scaffold.dart';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

/// Serves a 1×1 PNG for every image key (the test bundle has no brand
/// assets); everything else comes from the real bundle.
class _PngBundle extends CachingAssetBundle {
  static final _png = base64Decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
  @override
  Future<ByteData> load(String key) async => key.endsWith('.png') ? ByteData.sublistView(_png) : rootBundle.load(key);
}

void main() {
  Widget harness(AppConfig config, Widget child) => DefaultAssetBundle(
        bundle: _PngBundle(),
        child: ProviderScope(
          key: UniqueKey(),
          overrides: [appConfigProvider.overrideWithValue(config)],
          child: MaterialApp.router(routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => Scaffold(body: Center(child: child)))])),
        ),
      );
  final base = AppConfig.forFlavor(AppFlavor.dev);

  testWidgets('without logo_dark.png the logo sits on a light plate; with it the dark asset is used', (tester) async {
    await tester.pumpWidget(harness(base.copyWith(hasDarkLogo: false), const BrandLogo(height: 56)));
    await tester.pump();
    expect(find.byKey(const Key('brand-logo-plate')), findsOneWidget);
    expect(find.byKey(const Key('brand-logo-dark')), findsNothing);
    final plate = tester.widget<Container>(find.byKey(const Key('brand-logo-plate')));
    expect((plate.decoration as BoxDecoration).color!.computeLuminance(), greaterThan(0.8), reason: 'light plate behind dark lettering');

    await tester.pumpWidget(harness(base.copyWith(hasDarkLogo: true), const BrandLogo(height: 56)));
    await tester.pump();
    expect(find.byKey(const Key('brand-logo-dark')), findsOneWidget);
    expect(find.byKey(const Key('brand-logo-plate')), findsNothing);
    expect((tester.widget<Image>(find.byKey(const Key('brand-logo-dark'))).image as AssetImage).assetName, 'assets/branding/logo_dark.png');
  });

  testWidgets('LOGO_HAS_NAME true hides the app name beside the logo; false shows it', (tester) async {
    await tester.pumpWidget(harness(base.copyWith(logoHasName: true), const BrandHeader()));
    await tester.pump();
    expect(find.byKey(const Key('brand-name')), findsNothing);
    expect(find.byType(BrandLogo), findsOneWidget);

    await tester.pumpWidget(harness(base.copyWith(logoHasName: false), const BrandHeader()));
    await tester.pump();
    expect(find.byKey(const Key('brand-name')), findsOneWidget);
    expect(find.text(base.appName), findsOneWidget);
  });

  testWidgets('the login frame uses the brand header, so a wordmark logo shows the name once', (tester) async {
    await tester.pumpWidget(harness(base.copyWith(logoHasName: true, hasDarkLogo: true), const AuthScaffold(title: 'Sign in', showLegal: false, child: SizedBox())));
    await tester.pump();
    expect(find.byType(BrandHeader), findsOneWidget);
    expect(find.text(base.appName), findsNothing, reason: 'name is inside the artwork');
    expect(find.text('Sign in'), findsOneWidget);
  });

  test('LOGO_HAS_NAME parsing defaults to true', () {
    expect(AppConfig.parseFlag('false', true), isFalse);
    expect(AppConfig.parseFlag('0', true), isFalse);
    expect(AppConfig.parseFlag('', true), isTrue);
    expect(AppConfig.parseFlag('yes', false), isTrue);
  });
}
