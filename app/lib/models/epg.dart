import '../core/util/json.dart';
import '../core/util/time.dart';

class EpgProgramme {
  const EpgProgramme({required this.id, required this.channelId, required this.title, required this.description, required this.start, required this.end, required this.category});
  final int id;
  final int channelId;
  final String title;
  final String? description;
  final DateTime? start;
  final DateTime? end;
  final String? category;

  bool isAiringAt(DateTime t) => start != null && end != null && !t.isBefore(start!) && t.isBefore(end!);

  double progressAt(DateTime t) {
    if (start == null || end == null) return 0;
    final total = end!.difference(start!).inSeconds;
    if (total <= 0) return 0;
    return (t.difference(start!).inSeconds / total).clamp(0, 1).toDouble();
  }

  factory EpgProgramme.fromJson(Json j, {int? channelId}) => EpgProgramme(
        id: asInt(j['id']),
        channelId: asIntOrNull(j['channel_id']) ?? channelId ?? 0,
        title: asString(j['title']),
        description: asStringOrNull(j['description']),
        start: parseApiDateTime(j['start_time']),
        end: parseApiDateTime(j['end_time']),
        category: asStringOrNull(j['category']),
      );
}

/// Grouped form of `/epg` (no channel_id): one entry per channel.
class EpgChannelSchedule {
  const EpgChannelSchedule({required this.channelId, required this.channelName, required this.programmes});
  final int channelId;
  final String channelName;
  final List<EpgProgramme> programmes;

  EpgProgramme? nowAt(DateTime t) {
    for (final p in programmes) {
      if (p.isAiringAt(t)) return p;
    }
    return null;
  }

  EpgProgramme? nextAfter(DateTime t) {
    for (final p in programmes) {
      if (p.start != null && p.start!.isAfter(t)) return p;
    }
    return null;
  }

  factory EpgChannelSchedule.fromJson(Json j) {
    final id = asInt(j['channel_id']);
    return EpgChannelSchedule(
      channelId: id,
      channelName: asString(j['channel_name']),
      programmes: asJsonList(j['programmes']).map((p) => EpgProgramme.fromJson(p, channelId: id)).toList(growable: false),
    );
  }
}
