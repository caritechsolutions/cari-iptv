// Every renderer of a channel, movie or series card draws the one AccessBadge,
// so a locked item shows its padlock wherever it appears: plain cards and
// rails, the built-in home, and every server-driven section type.
import 'package:cari_tv/core/util/json.dart';
import 'package:cari_tv/core/widgets/cards.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/content/data/content_repository.dart' as content;
import 'package:cari_tv/features/layout/ui/home_screen.dart';
import 'package:cari_tv/features/layout/ui/section_widgets.dart';
import 'package:cari_tv/models/channel.dart';
import 'package:cari_tv/models/entitlements.dart';
import 'package:cari_tv/models/epg.dart';
import 'package:cari_tv/models/layout.dart';
import 'package:cari_tv/models/media_card.dart';
import 'package:cari_tv/models/movie.dart';
import 'package:cari_tv/models/recommendation.dart';
import 'package:cari_tv/models/search_result.dart';
import 'package:cari_tv/models/series.dart';
import 'package:cari_tv/models/watch.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../support/test_harness.dart';

const lockedChannel = MediaCard(id: 1, type: 'channel', title: 'ABN', isRestricted: true);
const lockedMovie = MediaCard(id: 5, type: 'movie', title: 'Locked Film', isRestricted: true);
const lockedSeries = MediaCard(id: 7, type: 'series', title: 'Locked Show', isRestricted: true);
const openChannel = MediaCard(id: 2, type: 'channel', title: 'Open TV');

/// Subscription active, nothing entitled: every restricted card is locked.
const nothingEntitled = Entitlements(movieIds: {}, seriesIds: {}, channelIds: {}, categoryIds: {}, packages: [], hasSubscription: true, adultEnabled: false);

Json contentJson(MediaCard c) => {'id': c.id, 'title': c.title, 'name': c.title, 'is_restricted': c.isRestricted, 'stream_url': 'https://h/${c.id}.m3u8'};

LayoutSection section(String type, List<MediaCard> cards, {Map<String, Object?> settings = const {}}) => LayoutSection(
      id: 1,
      type: type,
      title: 'Section',
      settings: settings,
      sortOrder: 0,
      items: [for (final c in cards) LayoutItem(id: c.id, contentType: c.type, contentId: c.id, settings: const {}, sortOrder: 0, content: contentJson(c))],
    );

void main() {
  late TestRepos repos;
  setUp(() {
    repos = TestRepos();
  });

  // A tall viewport so lazily built lists render every row under test.
  Future<void> tall(WidgetTester tester) async {
    tester.view.physicalSize = const Size(600, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
  }

  Widget harness(Widget child) => ProviderScope(
        key: UniqueKey(),
        overrides: testOverrides(auth: const AuthSignedIn(testUser), repos: repos, entitlements: entitlementsProvider.overrideWith((ref) async => nothingEntitled)),
        child: MaterialApp(home: Scaffold(body: child)),
      );

  Finder lock(MediaCard c) => find.byKey(Key('locked-${c.type}-${c.id}'));

  Future<void> settle(WidgetTester tester) async {
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
  }

  group('plain cards and rails', () {
    testWidgets('PosterCard, LandscapeCard (channel and backdrop), ContentRail both styles, PosterGrid', (tester) async {
      await tall(tester);
      await tester.pumpWidget(harness(const Wrap(children: [
        PosterCard(card: lockedMovie),
        LandscapeCard(card: lockedChannel),
        LandscapeCard(card: lockedSeries),
        LandscapeCard(card: openChannel),
      ])));
      await settle(tester);
      expect(lock(lockedMovie), findsOneWidget);
      expect(lock(lockedChannel), findsOneWidget, reason: 'channel landscape card');
      expect(lock(lockedSeries), findsOneWidget, reason: 'backdrop landscape card');
      expect(lock(openChannel), findsNothing, reason: 'unrestricted channel is open to any subscriber');

      await tester.pumpWidget(harness(ListView(children: const [
        ContentRail(title: 'Posters', cards: [lockedMovie, lockedSeries]),
        ContentRail(title: 'Backdrops', cards: [lockedChannel], style: 'backdrop'),
      ])));
      await settle(tester);
      expect(lock(lockedMovie), findsOneWidget);
      expect(lock(lockedSeries), findsOneWidget);
      expect(lock(lockedChannel), findsOneWidget);

      await tester.pumpWidget(harness(const PosterGrid(cards: [lockedMovie, lockedSeries])));
      await settle(tester);
      expect(lock(lockedMovie), findsOneWidget);
      expect(lock(lockedSeries), findsOneWidget);
    });
  });

  group('server-driven section types', () {
    testWidgets('hero_slideshow, content_row, channel_grid, spotlight', (tester) async {
      await tall(tester);
      await tester.pumpWidget(harness(ListView(children: [
        buildSection(section('hero_slideshow', [lockedMovie])),
        buildSection(section('content_row', [lockedSeries, lockedChannel])),
        buildSection(section('channel_grid', [lockedChannel, openChannel])),
        buildSection(section('spotlight', [lockedMovie])),
      ])));
      await settle(tester);
      expect(lock(lockedMovie), findsNWidgets(2), reason: 'hero and spotlight');
      expect(lock(lockedSeries), findsOneWidget, reason: 'content_row');
      expect(lock(lockedChannel), findsNWidgets(2), reason: 'content_row and channel_grid');
      expect(lock(openChannel), findsNothing);
    });

    testWidgets('live_now and epg_schedule (channels + guide) and recommendation rails', (tester) async {
      final abn = Channel.fromJson(contentJson(lockedChannel));
      final open = Channel.fromJson(contentJson(openChannel));
      when(() => repos.content.channels(categoryId: any(named: 'categoryId'), limit: any(named: 'limit'), offset: any(named: 'offset'), preferCache: any(named: 'preferCache')))
          .thenAnswer((_) async => content.Page(items: [abn, open], total: 2, offset: 0));
      final now = DateTime.now().toUtc();
      final prog = EpgProgramme(id: 1, channelId: 1, title: 'News', description: null, start: now.subtract(const Duration(minutes: 10)), end: now.add(const Duration(minutes: 20)), category: null);
      when(() => repos.epg.guide(date: any(named: 'date'), limit: any(named: 'limit'), preferCache: any(named: 'preferCache')))
          .thenAnswer((_) async => [EpgChannelSchedule(channelId: 1, channelName: 'ABN', programmes: [prog]), const EpgChannelSchedule(channelId: 2, channelName: 'Open TV', programmes: [])]);
      when(() => repos.recommendations.sets(type: any(named: 'type'))).thenAnswer((_) async => const [RecommendationSet(id: 1, setType: 'trending', title: 'Trending', reason: null, priority: 1, items: [lockedMovie])]);

      await tester.pumpWidget(harness(ListView(children: [
        buildSection(section('live_now', const [])),
        buildSection(section('epg_schedule', const [])),
        buildSection(section('trending_now', const [])),
      ])));
      await settle(tester);
      expect(lock(lockedChannel), findsNWidgets(2), reason: 'live_now card and epg_schedule row');
      expect(lock(openChannel), findsNothing);
      expect(lock(lockedMovie), findsOneWidget, reason: 'recommendation rail');
    });
  });

  group('built-in home', () {
    testWidgets('Live TV row, Continue Watching, latest movies and series', (tester) async {
      final abn = Channel.fromJson(contentJson(lockedChannel));
      final open = Channel.fromJson(contentJson(openChannel));
      when(() => repos.content.channels(categoryId: any(named: 'categoryId'), limit: any(named: 'limit'), offset: any(named: 'offset'), preferCache: any(named: 'preferCache')))
          .thenAnswer((_) async => content.Page(items: [abn, open], total: 2, offset: 0));
      when(() => repos.content.featuredMovies()).thenAnswer((_) async => const []);
      when(() => repos.content.movies(categoryId: any(named: 'categoryId'), featured: any(named: 'featured'), genre: any(named: 'genre'), year: any(named: 'year'), sort: any(named: 'sort'), limit: any(named: 'limit'), offset: any(named: 'offset'), preferCache: any(named: 'preferCache')))
          .thenAnswer((_) async => content.Page(items: [Movie.fromJson(contentJson(lockedMovie))], total: 1, offset: 0));
      when(() => repos.content.seriesList(categoryId: any(named: 'categoryId'), featured: any(named: 'featured'), genre: any(named: 'genre'), sort: any(named: 'sort'), limit: any(named: 'limit'), offset: any(named: 'offset'), preferCache: any(named: 'preferCache')))
          .thenAnswer((_) async => content.Page(items: [Series.fromJson(contentJson(lockedSeries))], total: 1, offset: 0));
      when(() => repos.userContent.continueWatching(type: any(named: 'type'))).thenAnswer((_) async => [
            ContinueWatchingItem.fromJson({...contentJson(lockedMovie), 'content_type': 'movie', 'content_id': lockedMovie.id, 'progress_seconds': 100, 'duration_seconds': 1000}),
          ]);

      await tall(tester);
      await tester.pumpWidget(harness(DefaultHome(onRefresh: () async {})));
      await settle(tester);
      await settle(tester);
      expect(find.text('Live TV'), findsOneWidget);
      expect(lock(lockedChannel), findsOneWidget, reason: 'Home Live TV row');
      expect(lock(openChannel), findsNothing);
      expect(lock(lockedMovie), findsNWidgets(2), reason: 'Continue Watching and Latest Movies');
      expect(lock(lockedSeries), findsOneWidget, reason: 'Latest TV Shows');
    });
  });

  group('rows that reach cards through other models', () {
    test('search results and continue-watching rows carry the flags when the API sends them', () {
      final r = SearchResult.fromJson({'id': 3, 'title': 'X', 'content_type': 'channel', 'is_restricted': true, 'is_adult': false, 'category_id': 9}).toCard();
      expect(r.isRestricted, isTrue);
      expect(r.categoryId, 9);
      final cw = ContinueWatchingItem.fromJson({'content_type': 'movie', 'content_id': 4, 'title': 'Y', 'is_restricted': 1, 'progress_seconds': 1, 'duration_seconds': 10}).toCard();
      expect(cw.isRestricted, isTrue);
      expect(SearchResult.fromJson({'id': 3, 'title': 'X', 'content_type': 'movie'}).toCard().isRestricted, isFalse);
    });
  });
}
