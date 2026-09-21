import 'package:cari_tv/features/live/epg/guide_model.dart';
import 'package:cari_tv/models/channel.dart';
import 'package:cari_tv/models/epg.dart';
import 'package:flutter_test/flutter_test.dart';

Channel channel(int id, String name, {String? category}) => Channel.fromJson({'id': id, 'name': name, 'slug': name.toLowerCase(), 'stream_url': 'https://h/$id.m3u8', 'category_name': category});

EpgProgramme prog(int channelId, String title, DateTime start, Duration length) => EpgProgramme(id: 1, channelId: channelId, title: title, description: null, start: start.toUtc(), end: start.add(length).toUtc(), category: null);

void main() {
  // 14:17 local on a fixed day.
  final now = DateTime(2026, 9, 21, 14, 17);

  group('placeholderSchedule', () {
    test('27 one-hour blocks from three hours ago, on the hour, named after the channel', () {
      final ch = channel(5, 'CVM TV', category: 'News');
      final blocks = placeholderSchedule(ch, now);
      expect(blocks.length, 27);
      expect(blocks.first.start!.toLocal(), DateTime(2026, 9, 21, 11, 0));
      expect(blocks.first.end!.toLocal(), DateTime(2026, 9, 21, 12, 0));
      expect(blocks.last.end!.toLocal(), DateTime(2026, 9, 22, 14, 0));
      for (var i = 1; i < blocks.length; i++) {
        expect(blocks[i].start, blocks[i - 1].end, reason: 'blocks are contiguous with exact times');
        expect(blocks[i].duration, const Duration(hours: 1));
      }
      expect(blocks.every((b) => b.isPlaceholder), isTrue);
      expect(blocks.every((b) => b.title == 'CVM TV Content'), isTrue);
      expect(blocks.every((b) => b.description == 'Regular programming on CVM TV'), isTrue);
      expect(blocks.every((b) => b.category == 'News'), isTrue);
      expect(blocks.every((b) => b.hasTimes), isTrue);
      expect(blocks.map((b) => b.key).toSet().length, 27, reason: 'every block has a distinct key for later time-shift');
    });

    test('category defaults to General', () {
      expect(placeholderSchedule(channel(1, 'X'), now).first.category, 'General');
    });
  });

  group('buildGuideMap', () {
    test('channels with data keep their programmes; channels without get placeholders', () {
      final a = channel(1, 'A');
      final b = channel(2, 'B');
      final real = prog(1, 'News', now.subtract(const Duration(minutes: 10)), const Duration(minutes: 30));
      final map = buildGuideMap([a, b], [EpgChannelSchedule(channelId: 1, channelName: 'A', programmes: [real]), const EpgChannelSchedule(channelId: 2, channelName: 'B', programmes: [])], now);
      expect(map[1]!.single.title, 'News');
      expect(map[1]!.single.isPlaceholder, isFalse);
      expect(map[2]!.length, 27);
      expect(map[2]!.first.isPlaceholder, isTrue);
    });

    test('rows without times are dropped and the rest sorted by start', () {
      final a = channel(1, 'A');
      final later = prog(1, 'Later', now.add(const Duration(hours: 1)), const Duration(hours: 1));
      final earlier = prog(1, 'Earlier', now, const Duration(hours: 1));
      const broken = EpgProgramme(id: 9, channelId: 1, title: 'Broken', description: null, start: null, end: null, category: null);
      final map = buildGuideMap([a], [EpgChannelSchedule(channelId: 1, channelName: 'A', programmes: [later, broken, earlier])], now);
      expect(map[1]!.map((p) => p.title), ['Earlier', 'Later']);
    });
  });

  group('nowAndNext', () {
    test('finds the airing block and the next one, placeholders included', () {
      final ch = channel(5, 'CVM TV');
      final nn = nowAndNext(placeholderSchedule(ch, now), now);
      expect(nn.now!.start!.toLocal(), DateTime(2026, 9, 21, 14, 0));
      expect(nn.next!.start!.toLocal(), DateTime(2026, 9, 21, 15, 0));
      expect(nn.now!.title, 'CVM TV Content');
    });

    test('nothing airing gives null now but still a next', () {
      final future = prog(1, 'Later', now.add(const Duration(hours: 2)), const Duration(hours: 1));
      final nn = nowAndNext([future], now);
      expect(nn.now, isNull);
      expect(nn.next!.title, 'Later');
    });
  });

  group('GuideWindow', () {
    test('starts two hours before now floored to :00/:30, spans 8 hours, 30-minute slots', () {
      final w = GuideWindow.around(now);
      expect(w.start, DateTime(2026, 9, 21, 12, 0));
      expect(w.end, DateTime(2026, 9, 21, 20, 0));
      expect(w.slots.length, 16);
      expect(w.slots[1], DateTime(2026, 9, 21, 12, 30));
      expect(w.width, 8 * 60 * pxPerMinute);
    });

    test('floors to :30 when past the half hour', () {
      final w = GuideWindow.around(DateTime(2026, 9, 21, 14, 45));
      expect(w.start, DateTime(2026, 9, 21, 12, 30));
    });

    test('maps time to pixels and clips blocks to the window', () {
      final w = GuideWindow.around(now);
      expect(w.xFor(DateTime(2026, 9, 21, 12, 0)), 0);
      expect(w.xFor(DateTime(2026, 9, 21, 13, 0)), 60 * pxPerMinute);
      expect(w.xFor(now), closeTo((2 * 60 + 17) * pxPerMinute, 0.01));
      final spanning = prog(1, 'Long', DateTime(2026, 9, 21, 11, 0), const Duration(hours: 2));
      expect(w.contains(spanning), isTrue);
      final b = w.blockFor(spanning);
      expect(b.left, 0);
      expect(b.width, 60 * pxPerMinute, reason: 'the part before the window is clipped');
      final outside = prog(1, 'Old', DateTime(2026, 9, 21, 9, 0), const Duration(hours: 1));
      expect(w.contains(outside), isFalse);
    });
  });
}
