// Padlock rule (web isContentLocked): no active subscription locks
// everything; otherwise only restricted content absent from the entitlements
// is locked. The badge and the play gate share the rule and follow
// entitlement changes.
import 'package:cari_tv/core/widgets/cards.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/content/data/content_repository.dart' as content;
import 'package:cari_tv/features/content/ui/detail_widgets.dart';
import 'package:cari_tv/features/live/ui/live_screen.dart';
import 'package:cari_tv/models/channel.dart';
import 'package:cari_tv/models/entitlements.dart';
import 'package:cari_tv/models/media_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart' show StateProvider;
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';

import '../../support/test_harness.dart';

Entitlements ent({bool subscription = true, Set<int> channels = const {}, Set<int> movies = const {}, Set<int> categories = const {}}) =>
    Entitlements(movieIds: movies, seriesIds: const {}, channelIds: channels, categoryIds: categories, packages: const [], hasSubscription: subscription, adultEnabled: false);

Channel channel(int id, String name, {bool restricted = false, int? categoryId}) =>
    Channel.fromJson({'id': id, 'name': name, 'slug': name.toLowerCase(), 'stream_url': 'https://live.example.com/$id.m3u8', 'is_restricted': restricted, 'category_id': categoryId});

void main() {
  group('Entitlements.locks', () {
    test('entitled restricted content is not locked', () {
      expect(ent(channels: {1}).locks(type: 'channel', id: 1, isRestricted: true), isFalse);
    });
    test('restricted content absent from the entitlements is locked', () {
      expect(ent(channels: {2}).locks(type: 'channel', id: 1, isRestricted: true), isTrue);
    });
    test('an entitled category unlocks its restricted content', () {
      expect(ent(categories: {7}).locks(type: 'movie', id: 5, categoryId: 7, isRestricted: true), isFalse);
    });
    test('unrestricted content is open to any subscriber', () {
      expect(ent().locks(type: 'channel', id: 9, isRestricted: false), isFalse);
    });
    test('without an active subscription everything is locked, restricted or not', () {
      expect(ent(subscription: false, channels: {1}).locks(type: 'channel', id: 1, isRestricted: true), isTrue);
      expect(ent(subscription: false).locks(type: 'channel', id: 9, isRestricted: false), isTrue);
    });
  });

  group('padlock in the channel list', () {
    late TestRepos repos;
    final abc = channel(1, 'ABC', restricted: true);
    final tvj = channel(2, 'TVJ');
    final entState = StateProvider<Entitlements>((_) => ent());

    setUp(() {
      repos = TestRepos();
      when(() => repos.content.channels(categoryId: any(named: 'categoryId'), limit: any(named: 'limit'), offset: any(named: 'offset'), preferCache: any(named: 'preferCache')))
          .thenAnswer((_) async => content.Page(items: [abc, tvj], total: 2, offset: 0));
      when(() => repos.content.categories(type: any(named: 'type'), preferCache: any(named: 'preferCache'))).thenAnswer((_) async => const []);
      when(() => repos.epg.guide(date: any(named: 'date'), limit: any(named: 'limit'), preferCache: any(named: 'preferCache'))).thenAnswer((_) async => const []);
    });

    Widget harness(Entitlements initial, {Widget? home}) => ProviderScope(
          overrides: [
            ...testOverrides(auth: const AuthSignedIn(testUser), repos: repos, entitlements: entitlementsProvider.overrideWith((ref) async => ref.watch(entState))),
            entState.overrideWith((_) => initial),
          ],
          child: MaterialApp(home: home ?? const LiveScreen(embedded: true)),
        );

    Finder lock(int id) => find.byKey(Key('locked-channel-$id'));

    testWidgets('entitled: no padlock on the restricted channel', (tester) async {
      await tester.pumpWidget(harness(ent(channels: {1})));
      await tester.pumpAndSettle();
      expect(find.text('ABC'), findsOneWidget);
      expect(lock(1), findsNothing);
      expect(lock(2), findsNothing);
    });

    testWidgets('not entitled: padlock on the restricted channel only', (tester) async {
      await tester.pumpWidget(harness(ent(channels: {})));
      await tester.pumpAndSettle();
      expect(lock(1), findsOneWidget);
      expect(lock(2), findsNothing, reason: 'unrestricted channels are open to any subscriber');
    });

    testWidgets('no subscription: padlock on every channel', (tester) async {
      await tester.pumpWidget(harness(ent(subscription: false, channels: {1})));
      await tester.pumpAndSettle();
      expect(lock(1), findsOneWidget);
      expect(lock(2), findsOneWidget);
    });

    testWidgets('the padlock follows entitlement changes', (tester) async {
      await tester.pumpWidget(harness(ent(channels: {})));
      await tester.pumpAndSettle();
      expect(lock(1), findsOneWidget);
      final container = ProviderScope.containerOf(tester.element(find.byType(LiveScreen)));
      container.read(entState.notifier).state = ent(channels: {1});
      await tester.pumpAndSettle();
      expect(lock(1), findsNothing);
      container.read(entState.notifier).state = ent(subscription: false);
      await tester.pumpAndSettle();
      expect(lock(1), findsOneWidget);
      expect(lock(2), findsOneWidget);
    });

    testWidgets('the play gate refuses exactly what is locked, with the right message', (tester) async {
      var opened = 0;
      final router = GoRouter(routes: [
        GoRoute(path: '/', builder: (_, _) => const LiveScreen(embedded: true)),
        GoRoute(path: '/player', builder: (_, _) {
          opened++;
          return const Scaffold(body: Text('player'));
        }),
      ]);
      await tester.pumpWidget(ProviderScope(
        overrides: [
          ...testOverrides(auth: const AuthSignedIn(testUser), repos: repos, entitlements: entitlementsProvider.overrideWith((ref) async => ref.watch(entState))),
          entState.overrideWith((_) => ent(subscription: false)),
        ],
        child: MaterialApp.router(routerConfig: router),
      ));
      await tester.pumpAndSettle();
      await tester.tap(find.text('TVJ'));
      await tester.pumpAndSettle();
      expect(find.text('Subscription required'), findsOneWidget);
      expect(opened, 0);
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();

      final container = ProviderScope.containerOf(tester.element(find.byType(LiveScreen)));
      container.read(entState.notifier).state = ent(channels: {});
      await tester.pumpAndSettle();
      await tester.tap(find.text('ABC'));
      await tester.pumpAndSettle();
      expect(find.text('Not included in your plan'), findsOneWidget);
      expect(opened, 0);
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('TVJ'));
      await tester.pumpAndSettle();
      expect(opened, 1, reason: 'an unrestricted channel opens for any subscriber');
    });
  });

  group('cards and detail pages', () {
    const movie = MediaCard(id: 5, type: 'movie', title: 'Locked Film', isRestricted: true);

    // A fresh scope per pump: a ProviderScope keeps its first overrides.
    Widget harness(Entitlements e, Widget child) => ProviderScope(
          key: UniqueKey(),
          overrides: testOverrides(auth: const AuthSignedIn(testUser), entitlements: entitlementsProvider.overrideWith((ref) async => e)),
          child: MaterialApp(home: Scaffold(body: child)),
        );

    testWidgets('poster card: padlock only when locked', (tester) async {
      await tester.pumpWidget(harness(ent(movies: {5}), const PosterCard(card: movie)));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('locked-movie-5')), findsNothing);
      await tester.pumpWidget(harness(ent(), const PosterCard(card: movie)));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('locked-movie-5')), findsOneWidget);
    });

    testWidgets('detail page notice: package message when not entitled, subscription message without one', (tester) async {
      await tester.pumpWidget(harness(ent(movies: {5}), const AccessNotice(card: movie)));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('access-notice-lock')), findsNothing);
      await tester.pumpWidget(harness(ent(), const AccessNotice(card: movie)));
      await tester.pumpAndSettle();
      expect(find.text('Not included in your current package.'), findsOneWidget);
      await tester.pumpWidget(harness(ent(subscription: false, movies: {5}), const AccessNotice(card: movie)));
      await tester.pumpAndSettle();
      expect(find.text('An active subscription is needed to watch this.'), findsOneWidget);
    });
  });
}
