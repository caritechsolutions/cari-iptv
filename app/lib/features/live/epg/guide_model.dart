/// Pure model for the TV guide, mirroring the web player's live page
/// (`public/assets/js/player/app.js`: generatePlaceholderEpg, buildEpgMap,
/// renderEpgGrid):
///
/// - missing guide data is filled with one-hour placeholder blocks
///   `<Channel> Content` / `Regular programming on <Channel>` from three hours
///   ago (local, on the hour) to 24 hours ahead: a full schedule for channels
///   without data, and clipped blocks in every gap between real programmes,
///   so every channel has continuous coverage (the web only fills channels
///   without data);
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

/// Coverage range of placeholder blocks: three hours ago (local, on the
/// hour) to 24 hours ahead, as the web's 27 hourly blocks.
({DateTime from, DateTime to}) placeholderRange(DateTime now) {
  final local = now.toLocal();
  final from = DateTime(local.year, local.month, local.day, local.hour - 3);
  return (from: from, to: from.add(const Duration(hours: 27)));
}

EpgProgramme _placeholder(Channel channel, DateTime start, DateTime end) => EpgProgramme(
      id: 0,
      channelId: channel.id,
      title: '${channel.name} Content',
      description: 'Regular programming on ${channel.name}',
      start: start.toUtc(),
      end: end.toUtc(),
      category: channel.categoryName ?? 'General',
      isPlaceholder: true,
    );

/// Hour-aligned placeholder blocks covering [from, to) (local times). The
/// first and last blocks are clipped to the range so a gap between two real
/// programmes is filled exactly, edge to edge.
List<EpgProgramme> placeholderBlocks(Channel channel, DateTime from, DateTime to) {
  final out = <EpgProgramme>[];
  var cursor = DateTime(from.year, from.month, from.day, from.hour);
  while (cursor.isBefore(to)) {
    final next = cursor.add(const Duration(hours: 1));
    final start = cursor.isBefore(from) ? from : cursor;
    final end = next.isAfter(to) ? to : next;
    if (end.isAfter(start)) out.add(_placeholder(channel, start, end));
    cursor = next;
  }
  return out;
}

/// 27 one-hour blocks from three hours ago (local time, on the hour), for a
/// channel with no guide data at all.
List<EpgProgramme> placeholderSchedule(Channel channel, DateTime now) {
  final range = placeholderRange(now);
  return placeholderBlocks(channel, range.from, range.to);
}

/// Real programmes (sorted, with times) plus placeholder blocks in every gap
/// across [placeholderRange], so the channel has continuous coverage: before
/// the first programme, between programmes, and after the last one.
/// Overlapping real programmes are kept as they are.
List<EpgProgramme> fillGaps(Channel channel, List<EpgProgramme> real, DateTime now) {
  final range = placeholderRange(now);
  final out = <EpgProgramme>[];
  var cursor = range.from;
  for (final p in real) {
    final start = p.start!.toLocal();
    final end = p.end!.toLocal();
    if (start.isAfter(cursor)) out.addAll(placeholderBlocks(channel, cursor, start.isBefore(range.to) ? start : range.to));
    out.add(p);
    if (end.isAfter(cursor)) cursor = end;
  }
  if (cursor.isBefore(range.to)) out.addAll(placeholderBlocks(channel, cursor, range.to));
  return out;
}

/// Programmes per channel id, sorted by start: real guide rows (with times)
/// with every gap filled by placeholder blocks, or a full placeholder
/// schedule for channels without any data.
Map<int, List<EpgProgramme>> buildGuideMap(List<Channel> channels, List<EpgChannelSchedule> guide, DateTime now) {
  final byChannel = {for (final s in guide) s.channelId: s.programmes.where((p) => p.hasTimes).toList()..sort((a, b) => a.start!.compareTo(b.start!))};
  return {
    for (final ch in channels) ch.id: fillGaps(ch, byChannel[ch.id] ?? const [], now),
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
