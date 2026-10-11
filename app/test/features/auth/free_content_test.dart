// Free content (admin: channel "Available Without Purchase", movie "Free
// Content" → API `is_free`) plays for any signed-in account, subscription or
// not, and never shows a padlock. Rule, shared with the web player:
// free → playable; else no subscription → locked; else restricted and not
// entitled → locked. Checked on the rule, the Live TV list, a channel rail,
// a search row and the guide's channel column.
import 'package:cari_tv/core/widgets/cards.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/content/data/content_repository.dart' as content;
import 'package:cari_tv/features/live/epg/guide_model.dart';
import 'package:cari_tv/features/live/ui/epg_grid.dart';
import 'package:cari_tv/features/live/ui/live_screen.dart';
import 'package:cari_tv/models/channel.dart';
import 'package:cari_tv/models/entitlements.dart';
import 'package:cari_tv/models/search_result.dart';
import 'package:cari_tv/models/watch.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';

import '../../support/test_harness.dart';

Entitlements ent({bool subscription = true, Set<int> channels = const {}}) =>
    Entitlements(movieIds: const {}, seriesIds: const {}, channelIds: channels, categoryIds: const {}, packages: const [], hasSubscription: subscription, adultEnabled: false);

Channel channel(int id, String name, {bool restricted = false, bool free = false}) => Channel.fromJson(
    {'id': id, 'name': name, 'slug': name.toLowerCase(), 'stream_url': 'https://live.example.com/$id.m3u8', 'is_restricted': restricted, 'is_free': free});

/// Restricted (in a content group) AND flagged free: the flag wins.
final cvm = channel(3, 'CVM', restricted: true, free: true);
final abc = channel(1, 'ABC', restricted: true);
final tvj = channel(2, 'TVJ');

void main() {
  group('Entitlements.locks with is_free', () {
    test('free content is open with no subscription at all', () {
      expect(ent(subscription: false).locks(type: 'channel', id: 3, isRestricted: false, isFree: true), isFalse);
      expect(ent(subscription: false).locks(type: 'movie', id: 8, isRestricted: false, isFree: true), isFalse);
    });
    test('free wins over a content group the account is not entitled to', () {
      expect(ent(channels: {}).locks(type: 'channel', id: 3, isRestricted: true, isFree: true), isFalse);
      expect(ent(subscription: false).locks(type: 'channel', id: 3, isRestricted: true, isFree: true), isFalse);
    });
    test('the rest of the rule is unchanged', () {
      expect(ent(subscription: false).locks(type: 'channel', id: 2, isRestricted: false), isTrue);
      expect(ent(channels: {}).locks(type: 'channel', id: 1, isRestricted: true), isTrue);
      expect(ent(channels: {1}).locks(type: 'channel', id: 1, isRestricted: true), isFalse);
    });
    test('every row model carries the flag into its card', () {
      expect(cvm.toCard().isFree, isTrue);
      expect(SearchResult.fromJson({'id': 3, 'title': 'CVM', 'content_type': 'channel', 'is_free': true}).toCard().isFree, isTrue);
      expect(ContinueWatchingItem.fromJson({'content_type': 'movie', 'content_id': 8, 'id': 8, 'title': 'Short', 'is_free': 1}).toCard().isFree, isTrue);
      // Older backends without the field: not free, as before.
      expect(SearchResult.fromJson({'id': 3, 'title': 'CVM', 'content_type': 'channel'}).toCard().isFree, isFalse);
    });
  });

  group('no subscription', () {
    late TestRepos repos;
    var opened = 0;

    setUp(() {
      opened = 0;
      repos = TestRepos();
      when(() => repos.content.channels(categoryId: any(named: 'categoryId'), limit: any(named: 'limit'), offset: any(named: 'offset'), preferCache: any(named: 'preferCache')))
          .thenAnswer((_) async => content.Page(items: [abc, tvj, cvm], total: 3, offset: 0));
      when(() => repos.content.categories(type: any(named: 'type'), preferCache: any(named: 'preferCache'))).thenAnswer((_) async => const []);
      when(() => repos.epg.guide(date: any(named: 'date'), limit: any(named: 'limit'), preferCache: any(named: 'preferCache'))).thenAnswer((_) async => const []);
    });

    Widget harness(Widget home) => ProviderScope(
          overrides: testOverrides(auth: const AuthSignedIn(testUser), repos: repos, entitlements: entitlementsProvider.overrideWith((ref) async => ent(subscription: false))),
          child: MaterialApp.router(
            routerConfig: GoRouter(routes: [
              GoRoute(path: '/', builder: (_, _) => home),
              GoRoute(path: '/player', builder: (_, _) {
                opened++;
                return const Scaffold(body: Text('player'));
              }),
            ]),
          ),
        );

    Finder lock(int id) => find.byKey(Key('locked-channel-$id'));

    testWidgets('Live TV list: the free channel has no padlock and plays; the others stay locked', (tester) async {
      await tester.pumpWidget(harness(const LiveScreen(embedded: true)));
      await tester.pumpAndSettle();
      expect(lock(1), findsOneWidget);
      expect(lock(2), findsOneWidget);
      expect(lock(3), findsNothing, reason: 'free channel, no subscription');
      await tester.tap(find.text('CVM'));
      await tester.pumpAndSettle();
      expect(opened, 1);
      expect(find.text('player'), findsOneWidget);
    });

    testWidgets('channel rail: the free channel card has no padlock and opens the player', (tester) async {
      await tester.pumpWidget(harness(Scaffold(body: ListView(children: [ContentRail(title: 'Live TV', cards: [abc.toCard(), cvm.toCard()])]))));
      await tester.pumpAndSettle();
      expect(lock(1), findsOneWidget);
      expect(lock(3), findsNothing);
      await tester.tap(find.byWidgetPredicate((w) => w is LandscapeCard && w.card.id == 3));
      await tester.pumpAndSettle();
      expect(opened, 1);
    });

    testWidgets('search row: a free channel opened from a search result plays', (tester) async {
      final row = SearchResult.fromJson({'id': 3, 'title': 'CVM', 'slug': 'cvm', 'content_type': 'channel', 'is_restricted': true, 'is_free': true});
      await tester.pumpWidget(harness(Scaffold(body: Center(child: PosterCard(card: row.toCard().copyWith(), width: 120)))));
      await tester.pumpAndSettle();
      expect(lock(3), findsNothing);
      await tester.tap(find.byType(PosterCard));
      await tester.pumpAndSettle();
      expect(opened, 1);
    });

    testWidgets('guide channel column: padlock on the locked channels only', (tester) async {
      final now = DateTime(2026, 9, 21, 14, 17);
      final controller = EpgGridController();
      await tester.pumpWidget(harness(Scaffold(
        body: EpgGrid(controller: controller, window: GuideWindow.around(now), channels: [abc, tvj, cvm], programmes: buildGuideMap([abc, tvj, cvm], const [], now), now: now),
      )));
      await tester.pumpAndSettle();
      expect(lock(1), findsOneWidget);
      expect(lock(2), findsOneWidget);
      expect(lock(3), findsNothing);
    });
  });
}
