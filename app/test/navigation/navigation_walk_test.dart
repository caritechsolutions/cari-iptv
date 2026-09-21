// Walks every route of the real router and asserts the navigation rules:
//  - every screen that is not a bottom-tab root shows a back arrow, and the
//    system back button leads to the same place;
//  - the tab bar stays visible on all top-level pages, search included;
//  - system back on a tab root goes to Home first; only Home exits the app.
import 'package:cari_tv/app.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/player/ui/playback_request.dart';
import 'package:cari_tv/models/navigation.dart';
import 'package:cari_tv/features/player/ui/player_screen.dart';
import 'package:cari_tv/router/app_router.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../support/test_harness.dart';

/// Pages pushed on top of the current screen (never tab roots).
const pushedRoutes = <String, Object?>{
  '/movie/1': null,
  '/series/1': null,
  '/episode/1': null,
  '/category/1': 'Drama',
  '/person/1': null,
  '/guide': null,
  '/guide/1': null,
  '/search': null,
  '/settings': null,
  '/subscribe': null,
  '/profile': null,
  '/categories': null,
  '/page/about': null,
  '/player': PlaybackRequest(contentType: 'movie', contentId: 1, title: 'Movie', streamUrl: 'https://example.com/a.m3u8'),
};

/// Routes that keep the tab bar (inside the shell).
const shellRoutes = {'/home', '/movies', '/series', '/live', '/categories', '/my-list', '/subscribe', '/profile', '/page/about', '/search', '/settings'};

bool hasBackArrow() => find.byType(BackButton).evaluate().isNotEmpty || find.byIcon(Icons.arrow_back_rounded).evaluate().isNotEmpty;

Future<void> settle(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  late List<String> systemPops;
  late FakeVideoPlayerPlatform video;

  setUp(() {
    video = installPlatformFakes();
    systemPops = [];
    PackageInfo.setMockInitialValues(appName: 'CARI TV', packageName: 'net.caritech.caritv.dev', version: '1.0.0', buildNumber: '1', buildSignature: '', installerStore: null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
      if (call.method == 'SystemNavigator.pop') systemPops.add(call.method);
      return null;
    });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null);
  });

  Future<GoRouter> pumpApp(WidgetTester tester, {required AuthState auth, AppNavigation? nav}) async {
    await tester.pumpWidget(ProviderScope(overrides: testOverrides(auth: auth, nav: nav), child: const CariApp()));
    await settle(tester);
    final container = ProviderScope.containerOf(tester.element(find.byType(CariApp)));
    return container.read(appRouterProvider);
  }

  String location(GoRouter router) => router.state.uri.path;

  group('signed in', () {
    testWidgets('starts on Home with the tab bar and no back arrow', (tester) async {
      final router = await pumpApp(tester, auth: const AuthSignedIn(testUser));
      expect(location(router), '/home');
      expect(find.byType(NavigationBar), findsOneWidget);
      expect(hasBackArrow(), isFalse);
    });

    for (final entry in pushedRoutes.entries) {
      testWidgets('${entry.key}: pushed page has a back arrow and system back returns to Home', (tester) async {
        final router = await pumpApp(tester, auth: const AuthSignedIn(testUser));
        router.push(entry.key, extra: entry.value);
        await settle(tester);
        expect(location(router), entry.key);
        expect(hasBackArrow(), isTrue, reason: '${entry.key} has no way back');
        final inShell = shellRoutes.contains(entry.key);
        expect(find.byType(NavigationBar), inShell ? findsOneWidget : findsNothing, reason: '${entry.key}: tab bar ${inShell ? 'must stay visible' : 'must be hidden'}');

        await tester.binding.handlePopRoute();
        await settle(tester);
        expect(location(router), '/home', reason: 'system back from ${entry.key} must return to Home');
        expect(systemPops, isEmpty, reason: 'system back on a pushed page must not exit the app');
        if (entry.key == '/player') {
          expect(video.disposed(1), isTrue, reason: 'leaving the player must release it: ${video.calls}');
          expect(find.byType(PlayerScreen), findsNothing);
        }
      });
    }

    testWidgets('default tabs: system back on a tab root goes to Home, and Home exits the app', (tester) async {
      final router = await pumpApp(tester, auth: const AuthSignedIn(testUser));
      for (final tab in ['/movies', '/series', '/live', '/my-list']) {
        router.go(tab);
        await settle(tester);
        expect(location(router), tab);
        expect(find.byType(NavigationBar), findsOneWidget, reason: '$tab is a tab root');
        expect(hasBackArrow(), isFalse, reason: '$tab is a tab root and must not show a back arrow');
        await tester.binding.handlePopRoute();
        await settle(tester);
        expect(location(router), '/home', reason: 'back on $tab must go to Home');
        expect(systemPops, isEmpty, reason: 'back on $tab must not exit the app');
      }
      await tester.binding.handlePopRoute();
      await settle(tester);
      expect(systemPops, ['SystemNavigator.pop'], reason: 'back on Home exits the app');
    });

    testWidgets('tab bar from the backend: Search and Settings as tab roots keep the tab bar and have no back arrow', (tester) async {
      final router = await pumpApp(tester, auth: const AuthSignedIn(testUser), nav: navWithSearchAndSettings);
      expect(find.byType(NavigationBar), findsOneWidget);
      expect(find.text('Search'), findsWidgets);
      for (final tab in ['/search', '/settings', '/live']) {
        router.go(tab);
        await settle(tester);
        expect(location(router), tab);
        expect(find.byType(NavigationBar), findsOneWidget, reason: '$tab as a tab must keep the tab bar');
        expect(hasBackArrow(), isFalse, reason: '$tab as a tab root must not show a back arrow');
        await tester.binding.handlePopRoute();
        await settle(tester);
        expect(location(router), '/home');
      }
      // Tapping the Search tab from Home.
      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text('Search')));
      await settle(tester);
      expect(location(router), '/search');
      expect(find.byType(NavigationBar), findsOneWidget);
    });

    testWidgets('search opened from the app bar keeps the tab bar and gets a back arrow', (tester) async {
      final router = await pumpApp(tester, auth: const AuthSignedIn(testUser));
      await tester.tap(find.byTooltip('Search'));
      await settle(tester);
      expect(location(router), '/search');
      expect(find.byType(NavigationBar), findsOneWidget, reason: 'tab bar stays on search');
      expect(hasBackArrow(), isTrue, reason: 'search pushed from the app bar needs a back arrow');
      await tester.tap(find.byType(BackButton));
      await settle(tester);
      expect(location(router), '/home');
    });

    testWidgets('a page pushed on top of a non-Home tab returns to that tab', (tester) async {
      final router = await pumpApp(tester, auth: const AuthSignedIn(testUser));
      router.go('/movies');
      await settle(tester);
      router.push('/movie/3');
      await settle(tester);
      expect(location(router), '/movie/3');
      await tester.binding.handlePopRoute();
      await settle(tester);
      expect(location(router), '/movies', reason: 'back returns to where the user came from');
    });
  });

  group('signed out', () {
    testWidgets('login is a root; register and forgot-password have a back arrow and return to login', (tester) async {
      final router = await pumpApp(tester, auth: const AuthSignedOut());
      expect(location(router), '/login');
      expect(hasBackArrow(), isFalse);
      for (final path in ['/register', '/forgot-password']) {
        router.push(path);
        await settle(tester);
        expect(location(router), path);
        expect(hasBackArrow(), isTrue, reason: '$path needs a back arrow');
        await tester.binding.handlePopRoute();
        await settle(tester);
        expect(location(router), '/login');
      }
    });

    testWidgets('verify-pending (reached with go) still has a back arrow and back leads to login, not out of the app', (tester) async {
      final router = await pumpApp(tester, auth: const AuthSignedOut());
      router.go('/verify-pending', extra: {'email': 'a@b.c'});
      await settle(tester);
      expect(location(router), '/verify-pending');
      expect(hasBackArrow(), isTrue);
      await tester.binding.handlePopRoute();
      await settle(tester);
      expect(location(router), '/login');
      expect(systemPops, isEmpty);
    });

    testWidgets('system back on login exits the app', (tester) async {
      await pumpApp(tester, auth: const AuthSignedOut());
      await tester.binding.handlePopRoute();
      await settle(tester);
      expect(systemPops, ['SystemNavigator.pop']);
    });
  });
}
