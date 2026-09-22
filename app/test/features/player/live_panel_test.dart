// Live player panel: Now Playing / Up Next / schedule under the video,
// self-refreshing at block boundaries, auto-scrolled schedule, and channel
// switching inside the same player.
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/content/data/content_repository.dart' as content;
import 'package:cari_tv/features/live/epg/guide_model.dart';
import 'package:cari_tv/features/player/ui/playback_request.dart';
import 'package:cari_tv/features/player/ui/player_screen.dart';
import 'package:cari_tv/models/channel.dart';
import 'package:cari_tv/models/epg.dart';
import 'package:clock/clock.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';

import '../../support/test_harness.dart';

Channel channel(int id, String name) => Channel.fromJson({'id': id, 'name': name, 'slug': name.toLowerCase(), 'stream_url': 'https://live.example.com/$id/master.m3u8'});

EpgProgramme prog(int channelId, String title, DateTime start, Duration length, {String? description}) =>
    EpgProgramme(id: title.hashCode, channelId: channelId, title: title, description: description, start: start.toUtc(), end: start.add(length).toUtc(), category: null);

void main() {
  late FakeVideoPlayerPlatform video;
  late TestRepos repos;
  late List<EpgProgramme> served; // the ABC schedule the guide mock handed out
  final channels = [channel(1, 'ABC'), channel(2, 'CVM TV'), channel(3, 'TVJ')];

  /// Schedule for channel 1 around the (fake) test clock: 12 half-hour blocks
  /// from 3 h ago; the airing one is index 6 and ends 2 s from now.
  List<EpgProgramme> abcSchedule(DateTime now) {
    final airingEnd = now.add(const Duration(seconds: 2));
    final airingStart = airingEnd.subtract(const Duration(minutes: 30));
    return [
      for (var i = 6; i >= 1; i--) prog(1, 'Earlier $i', airingStart.subtract(Duration(minutes: 30 * i)), const Duration(minutes: 30)),
      prog(1, 'Evening News', airingStart, const Duration(minutes: 30), description: 'Headlines and weather.'),
      prog(1, 'Sports Desk', airingEnd, const Duration(minutes: 30), description: 'Results from the day.'),
      for (var i = 1; i <= 12; i++) prog(1, 'Later $i', airingEnd.add(Duration(minutes: 30 * i)), const Duration(minutes: 30)),
    ];
  }

  setUp(() {
    video = installPlatformFakes();
    repos = TestRepos();
    when(() => repos.content.channels(categoryId: any(named: 'categoryId'), limit: any(named: 'limit'), offset: any(named: 'offset'), preferCache: any(named: 'preferCache')))
        .thenAnswer((_) async => content.Page(items: channels, total: channels.length, offset: 0));
    when(() => repos.epg.guide(date: any(named: 'date'), limit: any(named: 'limit'), preferCache: any(named: 'preferCache'))).thenAnswer((_) async {
      served = abcSchedule(clock.now());
      // Channel 2 has no guide data: placeholder blocks.
      return [EpgChannelSchedule(channelId: 1, channelName: 'ABC', programmes: served)];
    });
    when(() => repos.userContent.saveProgress(contentType: any(named: 'contentType'), contentId: any(named: 'contentId'), progress: any(named: 'progress'), duration: any(named: 'duration'))).thenAnswer((_) async {});
  });

  final abc = PlaybackRequest.channel(id: 1, title: 'ABC', streamUrl: 'https://live.example.com/1/master.m3u8');

  GoRouter router() => GoRouter(routes: [
        GoRoute(path: '/', builder: (context, _) => Scaffold(body: Center(child: TextButton(key: const Key('play'), onPressed: () => context.push('/player', extra: abc), child: const Text('Play'))))),
        GoRoute(path: '/player', builder: (_, s) => PlayerScreen(request: s.extra as PlaybackRequest)),
      ]);

  Widget harness() => ProviderScope(
        overrides: testOverrides(auth: const AuthSignedIn(testUser), repos: repos),
        child: MaterialApp.router(routerConfig: router()),
      );

  Future<void> open(WidgetTester tester) async {
    tester.view.physicalSize = const Size(400, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(harness());
    await tester.tap(find.byKey(const Key('play')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.byType(PlayerScreen), findsOneWidget);
  }

  String textOf(Key key) => (find.byKey(key).evaluate().single.widget as Text).data!;

  group('nowAndNext at a block boundary', () {
    test('at the exact end of a block the next one is now (end exclusive, start inclusive)', () {
      final t = DateTime.utc(2026, 9, 21, 14, 0);
      final a = prog(1, 'A', t.subtract(const Duration(minutes: 30)), const Duration(minutes: 30));
      final b = prog(1, 'B', t, const Duration(minutes: 30));
      final c = prog(1, 'C', t.add(const Duration(minutes: 30)), const Duration(minutes: 30));
      final before = nowAndNext([a, b, c], t.subtract(const Duration(seconds: 1)));
      expect(before.now!.title, 'A');
      expect(before.next!.title, 'B');
      final at = nowAndNext([a, b, c], t);
      expect(at.now!.title, 'B');
      expect(at.next!.title, 'C');
    });
  });

  testWidgets('Now Playing, Up Next and the schedule match the web live page and move on when the block ends', (tester) async {
    await open(tester);
    await tester.pump();

    expect(textOf(const Key('live-channel-name')), 'ABC');
    expect(find.text('LIVE'), findsOneWidget);
    expect(find.text('NOW PLAYING'), findsOneWidget);
    expect(textOf(const Key('live-now-title')), 'Evening News');
    expect(textOf(const Key('live-now-time')), matches(RegExp(r'^\d\d:\d\d - \d\d:\d\d$')), reason: 'HH:mm - HH:mm like the web');
    expect(find.byKey(const Key('live-now-progress')), findsOneWidget);
    expect(textOf(const Key('live-now-desc')), 'Headlines and weather.');
    expect(find.text('UP NEXT'), findsOneWidget);
    expect(textOf(const Key('live-next-title')), 'Sports Desk');
    expect(textOf(const Key('live-next-time')), matches(RegExp(r'^\d\d:\d\d$')));
    final progress = tester.widget<LinearProgressIndicator>(find.byKey(const Key('live-now-progress')));
    expect(progress.value, greaterThan(0.99), reason: 'the block is about to end');

    // The block ends 2 s from now: no interaction, the panel refreshes itself.
    await tester.pump(const Duration(seconds: 3));
    expect(textOf(const Key('live-now-title')), 'Sports Desk');
    expect(textOf(const Key('live-now-desc')), 'Results from the day.');
    expect(textOf(const Key('live-next-title')), 'Later 1');
    expect(tester.widget<LinearProgressIndicator>(find.byKey(const Key('live-now-progress'))).value, lessThan(0.01));
  });

  testWidgets('schedule list opens scrolled to the airing block; past dimmed, airing highlighted', (tester) async {
    await open(tester);
    await tester.pump();

    final list = tester.widget<ListView>(find.byKey(const Key('live-schedule')));
    // The list holds the blocks inside the guide window; the airing block's
    // index is the number of earlier blocks that fall inside it.
    final window = GuideWindow.around(clock.now());
    final blocks = served.where(window.contains).toList();
    final airingIndex = blocks.indexWhere((p) => p.title == 'Evening News');
    expect(airingIndex, greaterThanOrEqualTo(3));
    expect(list.controller!.offset, airingIndex * 60.0, reason: 'auto-scrolled so the airing block is the first visible row');
    final listTop = tester.getTopLeft(find.byKey(const Key('live-schedule'))).dy;
    final airingKey = Key('live-block-${blocks[airingIndex].key}');
    expect(tester.getTopLeft(find.byKey(airingKey)).dy, closeTo(listTop, 0.5));
    expect(find.text('Earlier $airingIndex'), findsNothing, reason: 'the oldest in-window past block is scrolled out of view');
    expect(find.text('Sports Desk'), findsWidgets, reason: 'the next block is right below');

    // Dimming: an earlier block that is still built sits under an Opacity of 0.5.
    list.controller!.jumpTo(0);
    await tester.pump();
    Opacity opacityOf(String title) => tester.widget<Opacity>(find.ancestor(of: find.text(title).last, matching: find.byType(Opacity)).first);
    expect(opacityOf(blocks.first.title).opacity, 0.5, reason: 'past block dimmed');
    expect(opacityOf('Evening News').opacity, 1, reason: 'airing block');
    expect(opacityOf('Sports Desk').opacity, 1, reason: 'future block');
  });

  testWidgets('every block opens the details sheet with exact times (time-shift hook)', (tester) async {
    await open(tester);
    await tester.pump();
    await tester.tap(find.byKey(const Key('live-up-next')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('programme-when')), findsOneWidget);
    expect(find.textContaining('30 min'), findsOneWidget);
    expect(find.text('Watch now'), findsNothing, reason: 'already playing this channel');
  });

  testWidgets('switching channel replaces the stream in the same player and updates the panel', (tester) async {
    await open(tester);
    await tester.pump();
    expect(video.createdUrls, ['https://live.example.com/1/master.m3u8']);

    await tester.tap(find.byKey(const Key('live-channels-button')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('live-channel-picker')), findsOneWidget);
    expect(find.textContaining('Now: CVM TV Content'), findsOneWidget, reason: 'placeholder now programme for a channel without data');
    await tester.tap(find.byKey(const Key('live-pick-2')));
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.byType(PlayerScreen), findsOneWidget, reason: 'same player, no new route');
    expect(video.createdUrls, ['https://live.example.com/1/master.m3u8', 'https://live.example.com/2/master.m3u8']);
    expect(textOf(const Key('live-channel-name')), 'CVM TV');
    expect(textOf(const Key('live-now-title')), 'CVM TV Content');
    expect(textOf(const Key('live-now-desc')), 'Regular programming on CVM TV');
    expect(textOf(const Key('live-next-title')), 'CVM TV Content');
    expect(find.text('Evening News'), findsNothing, reason: 'schedule now shows the new channel');
    expect(repos.analytics.events, contains('channel_switch'));
  });
}
