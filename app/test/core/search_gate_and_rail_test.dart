// A restricted channel found by search carries the same entitlement fields as
// the channel list, so the play gate refuses it; and channel rails show about
// three cards per screen width.
import 'package:cari_tv/core/widgets/cards.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/models/entitlements.dart';
import 'package:cari_tv/models/media_card.dart';
import 'package:cari_tv/models/search_result.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import '../support/test_harness.dart';

const nothingEntitled = Entitlements(movieIds: {}, seriesIds: {}, channelIds: {}, categoryIds: {}, packages: [], hasSubscription: true, adultEnabled: false);

void main() {
  testWidgets('a restricted channel from a search row is refused by the gate and never reaches the player', (tester) async {
    // Row shape of /search since the backend commit that added the fields.
    final row = SearchResult.fromJson({'id': 1, 'title': 'ABN', 'slug': 'abn', 'image_url': '/uploads/abn.png', 'content_type': 'channel', 'is_restricted': true, 'is_adult': false, 'category_id': 3});
    final card = row.toCard();
    expect(card.isRestricted, isTrue);
    expect(card.categoryId, 3);

    var playerOpened = 0;
    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (_, _) => Scaffold(body: Center(child: PosterCard(card: card, width: 120)))),
      GoRoute(path: '/player', builder: (_, _) {
        playerOpened++;
        return const Scaffold(body: Text('player'));
      }),
    ]);
    await tester.pumpWidget(ProviderScope(
      overrides: testOverrides(auth: const AuthSignedIn(testUser), entitlements: entitlementsProvider.overrideWith((ref) async => nothingEntitled)),
      child: MaterialApp.router(routerConfig: router),
    ));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('locked-channel-1')), findsOneWidget, reason: 'padlock on the search result card');
    await tester.tap(find.byType(PosterCard));
    await tester.pumpAndSettle();
    expect(find.text('Not included in your plan'), findsOneWidget);
    expect(playerOpened, 0);

    // The same row without the fields (older backend) is not gated: documented in API_GAPS.md.
    expect(SearchResult.fromJson({'id': 1, 'title': 'ABN', 'content_type': 'channel'}).toCard().isRestricted, isFalse);
  });

  testWidgets('channel rails fit about three cards across a phone; movie rails keep their width', (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final channels = [for (var i = 1; i <= 6; i++) MediaCard(id: i, type: 'channel', title: 'Channel $i')];
    final movies = [for (var i = 1; i <= 6; i++) MediaCard(id: i, type: 'movie', title: 'Movie $i')];
    await tester.pumpWidget(ProviderScope(
      overrides: testOverrides(auth: const AuthSignedIn(testUser)),
      child: MaterialApp(home: Scaffold(body: ListView(children: [ContentRail(title: 'Live TV', cards: channels), ContentRail(title: 'Movies', cards: movies, style: 'backdrop')]))),
    ));
    await tester.pumpAndSettle();

    expect(channelCardWidth(360), closeTo(102.7, 0.1));
    final channelCards = find.byWidgetPredicate((w) => w is LandscapeCard && w.card.type == 'channel');
    final widths = channelCards.evaluate().map((e) => tester.getSize(find.byWidget(e.widget)).width).toSet();
    expect(widths.single, closeTo(102.7, 0.1));
    final fullyVisible = channelCards.evaluate().where((e) => tester.getRect(find.byWidget(e.widget)).right <= 360).length;
    expect(fullyVisible, 3, reason: 'three channel cards fully on screen');

    final movieCards = find.byWidgetPredicate((w) => w is LandscapeCard && w.card.type == 'movie');
    expect(tester.getSize(movieCards.first).width, 200, reason: 'backdrop rails unchanged');
  });
}
