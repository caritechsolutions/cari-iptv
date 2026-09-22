// Billing feature switch: server `features.billing` (default false), brand
// `BILLING_UI` override, and every billing surface hidden when off.
import 'package:cari_tv/app.dart';
import 'package:cari_tv/config/app_config.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/billing/billing_provider.dart';
import 'package:cari_tv/features/content/ui/detail_widgets.dart';
import 'package:cari_tv/features/layout/ui/section_widgets.dart';
import 'package:cari_tv/features/navigation/state/navigation_provider.dart';
import 'package:cari_tv/core/network/api_exception.dart';
import 'package:cari_tv/models/app_features.dart';
import 'package:cari_tv/models/entitlements.dart';
import 'package:cari_tv/models/layout.dart';
import 'package:cari_tv/models/navigation.dart';
import 'package:cari_tv/router/app_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../support/test_harness.dart';

/// Backend navigation with a Packages tab (page type `subscription`).
const navWithPackages = AppNavigation(
  id: 1,
  platform: 'mobile',
  position: 'main',
  style: 'bottom_tab',
  showIcons: true,
  showLabels: true,
  maxItems: 5,
  items: [
    NavItem(id: 1, label: 'Home', icon: 'lucide-home', target: 'page', url: null, sortOrder: 1, pageSlug: 'home', pageType: 'home', layoutId: null),
    NavItem(id: 2, label: 'Live TV', icon: 'lucide-radio', target: 'page', url: null, sortOrder: 2, pageSlug: 'live', pageType: 'live_tv', layoutId: null),
    NavItem(id: 3, label: 'Packages', icon: 'lucide-credit-card', target: 'page', url: null, sortOrder: 3, pageSlug: 'subscribe', pageType: 'subscription', layoutId: null),
    NavItem(id: 4, label: 'Profile', icon: 'lucide-user', target: 'page', url: null, sortOrder: 4, pageSlug: 'profile', pageType: 'profile', layoutId: null),
  ],
);

final basicPackage = Package.fromJson(const {'id': 1, 'name': 'Basic', 'slug': 'basic', 'description': 'Live channels', 'price_display': 'Free', 'billing_period': 'monthly', 'trial_days': 0, 'max_connections': 1, 'features': [], 'is_subscribed': true, 'is_free': true});
final withPackage = Entitlements(movieIds: const {}, seriesIds: const {}, channelIds: const {}, categoryIds: const {}, packages: [basicPackage], hasSubscription: true, adultEnabled: false);

void main() {
  group('AppFeatures', () {
    test('absent block or key means billing off', () {
      expect(AppFeatures.fromConfig(const {'navigation': null, 'pages': []}).billing, isFalse);
      expect(AppFeatures.fromConfig(const {'features': {}}).billing, isFalse);
      expect(AppFeatures.fromConfig(const {'features': null}).billing, isFalse);
    });
    test('reads features.billing as a bool in any of the API spellings', () {
      expect(AppFeatures.fromConfig(const {'features': {'billing': true}}).billing, isTrue);
      expect(AppFeatures.fromConfig(const {'features': {'billing': 1}}).billing, isTrue);
      expect(AppFeatures.fromConfig(const {'features': {'billing': '0'}}).billing, isFalse);
    });
  });

  group('resolveBilling (brand BILLING_UI × server switch)', () {
    test('auto follows the server; not loaded counts as off', () {
      expect(resolveBilling(BillingUi.auto, true), isTrue);
      expect(resolveBilling(BillingUi.auto, false), isFalse);
      expect(resolveBilling(BillingUi.auto, null), isFalse);
    });
    test('off wins over a server that says on; on wins over a server that says off', () {
      expect(resolveBilling(BillingUi.off, true), isFalse);
      expect(resolveBilling(BillingUi.on, false), isTrue);
      expect(resolveBilling(BillingUi.on, null), isTrue);
    });
    test('BILLING_UI parsing is forgiving and defaults to auto', () {
      expect(BillingUi.parse('off'), BillingUi.off);
      expect(BillingUi.parse(' ON '), BillingUi.on);
      expect(BillingUi.parse('false'), BillingUi.off);
      expect(BillingUi.parse(''), BillingUi.auto);
      expect(BillingUi.parse('whatever'), BillingUi.auto);
    });
    test('destinations drop subscription pages only when billing is off', () {
      expect(destinationsFrom(navWithPackages, billing: true).map((d) => d.label), ['Home', 'Live TV', 'Packages', 'Profile']);
      expect(destinationsFrom(navWithPackages, billing: false).map((d) => d.label), ['Home', 'Live TV', 'Profile']);
    });
    test('402 message: neutral wording with billing off', () {
      final e = ApiException(statusCode: 402, code: 'PAYMENT_REQUIRED', message: 'Payment required: visit https://example.com/plans');
      expect(friendlyPaymentMessage(e, billing: false), 'Not available.');
      expect(friendlyPaymentMessage(e, billing: true), 'This package is not available in the app.');
    });
  });

  group('billing surfaces', () {
    late TestRepos repos;

    setUp(() {
      installPlatformFakes();
      repos = TestRepos();
      PackageInfo.setMockInitialValues(appName: 'CARI TV', packageName: 'net.caritech.caritv.dev', version: '1.0.0', buildNumber: '1', buildSignature: '', installerStore: null);
    });

    Future<void> settle(WidgetTester tester) async {
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pump(const Duration(milliseconds: 400));
    }

    Future<GoRouter> pumpApp(WidgetTester tester, {required AppFeatures features, BillingUi brand = BillingUi.auto}) async {
      await tester.pumpWidget(ProviderScope(
        overrides: testOverrides(
          auth: const AuthSignedIn(testUser),
          nav: navWithPackages,
          repos: repos,
          features: features,
          config: AppConfig.forFlavor(AppFlavor.dev).withBillingUi(brand),
          entitlements: entitlementsProvider.overrideWith((ref) async => withPackage),
        ),
        child: const CariApp(),
      ));
      await settle(tester);
      final container = ProviderScope.containerOf(tester.element(find.byType(CariApp)));
      return container.read(appRouterProvider);
    }

    Finder tab(String label) => find.descendant(of: find.byType(NavigationBar), matching: find.text(label));

    testWidgets('server off (default): no Packages tab, no package rows in Profile, /subscribe is a neutral page', (tester) async {
      final router = await pumpApp(tester, features: AppFeatures.none);
      expect(tab('Home'), findsOneWidget);
      expect(tab('Packages'), findsNothing);

      router.go('/profile');
      await settle(tester);
      expect(find.text('YOUR PACKAGES'), findsNothing);
      expect(find.text('View all packages'), findsNothing);
      expect(find.text('Basic'), findsNothing);

      router.go('/subscribe');
      await settle(tester);
      expect(find.byKey(const Key('billing-unavailable')), findsOneWidget);
      expect(find.text('Activate'), findsNothing);
      expect(find.text('Cancel'), findsNothing);
      expect(find.textContaining('Free'), findsNothing, reason: 'no prices');
    });

    testWidgets('server on: Packages tab, package rows in Profile and the packages page with its actions', (tester) async {
      final router = await pumpApp(tester, features: const AppFeatures(billing: true));
      expect(tab('Packages'), findsOneWidget);

      router.go('/profile');
      await settle(tester);
      expect(find.text('YOUR PACKAGES'), findsOneWidget);
      expect(find.text('View all packages'), findsOneWidget);

      router.go('/subscribe');
      await settle(tester);
      expect(find.byKey(const Key('billing-unavailable')), findsNothing);
      expect(find.text('Basic'), findsOneWidget);
      expect(find.text('Cancel'), findsOneWidget, reason: 'unsubscribe action on the active package');
    });

    testWidgets('brand override: off hides everything although the server says on; on shows it although the server says off', (tester) async {
      var router = await pumpApp(tester, features: const AppFeatures(billing: true), brand: BillingUi.off);
      expect(tab('Packages'), findsNothing);
      router.go('/profile');
      await settle(tester);
      expect(find.text('YOUR PACKAGES'), findsNothing);

      router = await pumpApp(tester, features: AppFeatures.none, brand: BillingUi.on);
      expect(tab('Packages'), findsOneWidget);
      router.go('/profile');
      await settle(tester);
      expect(find.text('YOUR PACKAGES'), findsOneWidget);
    });

    testWidgets('packages_list layout section renders nothing when billing is off and the packages when on', (tester) async {
      const section = LayoutSection(id: 1, type: 'packages_list', title: 'Our packages', settings: {}, sortOrder: 0, items: []);
      Widget harness(AppFeatures f) => ProviderScope(
            key: UniqueKey(),
            overrides: testOverrides(auth: const AuthSignedIn(testUser), repos: repos, features: f, entitlements: entitlementsProvider.overrideWith((ref) async => withPackage)),
            child: const MaterialApp(home: Scaffold(body: PackagesListSection(section: section))),
          );
      await tester.pumpWidget(harness(AppFeatures.none));
      await settle(tester);
      expect(find.text('Our packages'), findsNothing);
      expect(find.text('Basic'), findsNothing);

      await tester.pumpWidget(harness(const AppFeatures(billing: true)));
      await settle(tester);
      expect(find.text('Our packages'), findsOneWidget);
      expect(find.text('Basic'), findsOneWidget);
    });
  });
}
