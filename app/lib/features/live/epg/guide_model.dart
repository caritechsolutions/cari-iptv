/// Pure model for the TV guide, mirroring the web player's live page
/// (`public/assets/js/player/app.js`: generatePlaceholderEpg, buildEpgMap,
/// renderEpgGrid):
///
/// - channels without guide data get one-hour placeholder blocks
///   `<Channel> Content` / `Regular programming on <Channel>`, 27 blocks from
///   three hours ago (local, on the hour);
/// - the grid window starts two hours ago, floored to :00/:30 local, and
///   spans [windowHours]; time columns are 30 minutes at [pxPerMinute];
/// - now/next come from the same programme list, placeholders included.
///
/// Every programme, real or placeholder, carries exact start and end times so
/// time-shifting can later start from any block without reworking the guide.
library;

import '../../../models/channel.dart';
import '../../../models/epg.dart';

const int windowHours = 8;
const int slotMinutes = 30;
const double pxPerMinute = 7;

/// 27 one-hour blocks from three hours ago (local time, on the hour).
List<EpgProgramme> placeholderSchedule(Channel channel, DateTime now) {
  final local = now.toLocal();
  final first = DateTime(local.year, local.month, local.day, local.hour - 3);
  return List.generate(27, (h) {
    final start = first.add(Duration(hours: h));
    final end = start.add(const Duration(hours: 1));
    return EpgProgramme(
      id: 0,
      channelId: channel.id,
      title: '${channel.name} Content',
      description: 'Regular programming on ${channel.name}',
      start: start.toUtc(),
      end: end.toUtc(),
      category: channel.categoryName ?? 'General',
      isPlaceholder: true,
    );
  });
}

/// Programmes per channel id: real guide rows (with times) when a channel
/// has any, placeholders otherwise. Sorted by start.
Map<int, List<EpgProgramme>> buildGuideMap(List<Channel> channels, List<EpgChannelSchedule> guide, DateTime now) {
  final byChannel = {for (final s in guide) s.channelId: s.programmes.where((p) => p.hasTimes).toList()..sort((a, b) => a.start!.compareTo(b.start!))};
  return {
    for (final ch in channels) ch.id: (byChannel[ch.id] ?? const []).isNotEmpty ? byChannel[ch.id]! : placeholderSchedule(ch, now),
  };
}

/// Programme airing at [now] and the first one starting after it.
({EpgProgramme? now, EpgProgramme? next}) nowAndNext(List<EpgProgramme> programmes, DateTime now) {
  EpgProgramme? current;
  EpgProgramme? next;
  for (final p in programmes) {
    if (current == null && p.isAiringAt(now)) current = p;
    if (next == null && p.isFutureAt(now)) next = p;
  }
  return (now: current, next: next);
}

/// The visible time range of the grid and its pixel mapping.
class GuideWindow {
  GuideWindow._(this.start, this.end);

  /// Two hours before [now], floored to :00 / :30 local, spanning [windowHours].
  factory GuideWindow.around(DateTime now, {int hours = windowHours}) {
    final local = now.toLocal();
    final base = DateTime(local.year, local.month, local.day, local.hour, local.minute < 30 ? 0 : 30).subtract(const Duration(hours: 2));
    return GuideWindow._(base, base.add(Duration(hours: hours)));
  }

  /// Local times.
  final DateTime start;
  final DateTime end;

  Duration get length => end.difference(start);
  double get width => length.inMinutes * pxPerMinute;

  /// Column boundaries every [slotMinutes].
  List<DateTime> get slots => [for (var t = start; t.isBefore(end); t = t.add(const Duration(minutes: slotMinutes))) t];

  /// X position of a moment inside the window (clamped).
  double xFor(DateTime t) {
    final minutes = t.toLocal().difference(start).inSeconds / 60;
    return (minutes * pxPerMinute).clamp(0, width);
  }

  bool contains(EpgProgramme p) => p.hasTimes && p.end!.toLocal().isAfter(start) && p.start!.toLocal().isBefore(end);

  /// Left edge and width of a programme block, clipped to the window.
  ({double left, double width}) blockFor(EpgProgramme p) {
    final left = xFor(p.start!);
    final right = xFor(p.end!);
    return (left: left, width: right - left);
  }
}
