import 'package:cari_tv/config/app_config.dart';
import 'package:cari_tv/core/providers.dart';
import 'package:cari_tv/features/live/epg/guide_model.dart';
import 'package:cari_tv/features/live/ui/epg_grid.dart';
import 'package:cari_tv/models/channel.dart';
import 'package:cari_tv/models/epg.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

Channel channel(int id, String name) => Channel.fromJson({'id': id, 'name': name, 'slug': name.toLowerCase(), 'stream_url': 'https://h/$id.m3u8'});

EpgProgramme prog(int channelId, String title, DateTime start, Duration length) => EpgProgramme(id: title.hashCode, channelId: channelId, title: title, description: null, start: start.toUtc(), end: start.add(length).toUtc(), category: null);

void main() {
  final now = DateTime(2026, 9, 21, 14, 17);
  final window = GuideWindow.around(now); // 12:00 – 20:00
  final channels = [for (var i = 1; i <= 30; i++) channel(i, 'Channel $i')];

  Map<int, List<EpgProgramme>> programmes() {
    final map = buildGuideMap(channels, const [], now);
    // Channel 1 has real data: a programme airing now and one after it.
    map[1] = [
      prog(1, 'Morning Show', DateTime(2026, 9, 21, 13, 30), const Duration(minutes: 60)),
      prog(1, 'Afternoon News', DateTime(2026, 9, 21, 14, 30), const Duration(minutes: 30)),
    ];
    return map;
  }

  // Channel cells use AppImage (a Riverpod consumer); the test channels have
  // no logo so only the config provider is needed.
  Widget harness(EpgGridController controller, {void Function(Channel, EpgProgramme)? onProgramme, void Function(Channel)? onChannel}) => ProviderScope(
        overrides: [appConfigProvider.overrideWithValue(AppConfig.forFlavor(AppFlavor.dev))],
        child: MaterialApp(
          theme: ThemeData.dark(),
          home: Scaffold(
            body: EpgGrid(
              controller: controller,
              window: window,
              channels: channels,
              programmes: programmes(),
              now: now,
              onProgrammeTap: onProgramme,
              onChannelTap: onChannel,
            ),
          ),
        ),
      );

  testWidgets('opens with the current time in view, header slots and the now marker present', (tester) async {
    tester.view.physicalSize = const Size(400, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final c = EpgGridController();
    await tester.pumpWidget(harness(c));
    await tester.pump();

    final viewport = 400 - 112;
    final nowX = window.xFor(now);
    expect(c.horizontalOffset, closeTo(nowX - viewport / 3, 0.5), reason: 'now sits a third of the way into the timeline');
    expect(find.byKey(const Key('epg-now-line')), findsOneWidget);
    expect(find.text('14:00'), findsWidgets, reason: '30-minute time columns');
    expect(find.text('Channel 1'), findsOneWidget);
    expect(find.text('Morning Show'), findsOneWidget, reason: 'airing programme block');
    expect(find.text('Afternoon News'), findsOneWidget);
    expect(find.text('Channel 2 Content'), findsWidgets, reason: 'placeholder blocks for channels without data');
  });

  testWidgets('channel column stays put while the timeline scrolls horizontally; prev / Now / next paging', (tester) async {
    tester.view.physicalSize = const Size(400, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final c = EpgGridController();
    await tester.pumpWidget(harness(c));
    await tester.pump();
    final nameBefore = tester.getTopLeft(find.text('Channel 1'));

    c.scrollHours(1);
    await tester.pumpAndSettle();
    final afterNext = c.horizontalOffset!;
    expect(tester.getTopLeft(find.text('Channel 1')), nameBefore, reason: 'sticky channel column');
    expect(afterNext, closeTo(window.xFor(now) - (400 - 112) / 3 + 60 * pxPerMinute, 0.5));

    c.scrollHours(-1);
    await tester.pumpAndSettle();
    expect(c.horizontalOffset!, closeTo(afterNext - 60 * pxPerMinute, 0.5));

    c.scrollHours(-10); // clamps at the window start
    await tester.pumpAndSettle();
    expect(c.horizontalOffset, 0);
    expect(find.text('12:00'), findsWidgets, reason: 'the window start slot is on screen');

    c.jumpToNow();
    await tester.pumpAndSettle();
    expect(c.horizontalOffset, closeTo(window.xFor(now) - (400 - 112) / 3, 0.5));
  });

  testWidgets('vertical scroll keeps the channel column and the rows aligned', (tester) async {
    tester.view.physicalSize = const Size(400, 400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final c = EpgGridController();
    await tester.pumpWidget(harness(c));
    await tester.pump();
    expect(find.text('Channel 20'), findsNothing, reason: 'below the fold');

    // Drag on the timeline side (rows, right of the channel column); the
    // channel column must follow.
    await tester.dragFrom(const Offset(300, 200), const Offset(0, -56.0 * 14));
    await tester.pumpAndSettle();
    expect(find.text('Channel 1'), findsNothing);
    expect(find.text('Channel 20'), findsOneWidget);
    // Channel 20's placeholder row is beside its name.
    expect(tester.getTopLeft(find.text('Channel 20')).dy, closeTo(tester.getTopLeft(find.text('Channel 20 Content').first).dy, 20));
  });

  testWidgets('a block that starts off the left edge keeps its title pinned inside the visible part', (tester) async {
    tester.view.physicalSize = const Size(400, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final c = EpgGridController();
    await tester.pumpWidget(harness(c));
    await tester.pump();

    final show = programmes()[1]!.firstWhere((p) => p.title == 'Morning Show');
    final block = find.byKey(Key('epg-block-${show.key}'));
    final title = find.byKey(Key('epg-title-${show.key}'));
    // On open the block (13:30–14:30) starts left of the viewport edge (112).
    expect(tester.getRect(block).left, lessThan(112));
    expect(tester.getRect(block).right, greaterThan(112));
    expect(tester.getTopLeft(title).dx, closeTo(112 + 6, 0.5), reason: 'title moved to the visible edge');
    expect(tester.getRect(title).right, lessThanOrEqualTo(tester.getRect(block).right));

    // Scroll back one hour: the block start is on screen, the title sits at its normal place.
    c.scrollHours(-1);
    await tester.pumpAndSettle();
    expect(tester.getTopLeft(title).dx, closeTo(tester.getRect(block).left + 6, 0.5));

    // Scroll far enough that only the tail of the block is visible: the title
    // stops at the block's end instead of leaving it.
    c.scrollHours(1.5);
    await tester.pumpAndSettle();
    final r = tester.getRect(block);
    expect(r.right - 112, lessThan(60));
    expect(tester.getTopLeft(title).dx, lessThanOrEqualTo(r.right - 6 - 48 + 0.5));
    expect(tester.getTopLeft(title).dx, greaterThan(r.left));
  });

  testWidgets('every block is tappable and reports the exact programme, placeholders included', (tester) async {
    tester.view.physicalSize = const Size(400, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    final taps = <(Channel, EpgProgramme)>[];
    final c = EpgGridController();
    await tester.pumpWidget(harness(c, onProgramme: (ch, p) => taps.add((ch, p))));
    await tester.pump();

    // Blocks that start before the visible range have their title clipped on
    // the left (as on the web), so tap inside the visible part of the block.
    Future<void> tapBlock(EpgProgramme p) async {
      final rect = tester.getRect(find.byKey(Key('epg-block-${p.key}')));
      final visible = Rect.fromLTRB(rect.left.clamp(112.0, 400.0), rect.top, rect.right.clamp(112.0, 400.0), rect.bottom);
      await tester.tapAt(visible.center);
      await tester.pump();
    }

    final map = programmes();
    await tapBlock(map[1]!.first);
    expect(taps.single.$1.id, 1);
    expect(taps.single.$2.title, 'Morning Show');
    expect(taps.single.$2.start!.toLocal(), DateTime(2026, 9, 21, 13, 30));
    expect(taps.single.$2.end!.toLocal(), DateTime(2026, 9, 21, 14, 30));

    await tapBlock(nowAndNext(map[2]!, now).now!);
    expect(taps.length, 2);
    expect(taps.last.$1.id, 2);
    expect(taps.last.$2.isPlaceholder, isTrue);
    expect(taps.last.$2.hasTimes, isTrue, reason: 'placeholder blocks carry exact times for time-shift');
    expect(taps.last.$2.start!.toLocal().minute, 0);
    expect(taps.last.$2.duration, const Duration(hours: 1));
  });

  testWidgets('tapping a channel cell reports the channel', (tester) async {
    tester.view.physicalSize = const Size(400, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    Channel? tapped;
    await tester.pumpWidget(harness(EpgGridController(), onChannel: (ch) => tapped = ch));
    await tester.pump();
    await tester.tap(find.text('Channel 3'));
    expect(tapped?.id, 3);
  });
}
