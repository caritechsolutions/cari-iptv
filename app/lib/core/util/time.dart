/// Parses the two datetime formats the API emits and treats both as UTC:
///  - `2025-01-15T14:00:00Z`  (EPG endpoints, string-stamped)
///  - `2025-01-15 14:00:00`   (raw MySQL, e.g. channel now_playing, last_watched_at)
DateTime? parseApiDateTime(Object? v) {
  if (v == null) return null;
  final s = v.toString().trim();
  if (s.isEmpty) return null;
  var normalized = s.replaceFirst(' ', 'T');
  if (!normalized.endsWith('Z') && !RegExp(r'[+-]\d\d:?\d\d$').hasMatch(normalized)) {
    normalized = '${normalized}Z';
  }
  return DateTime.tryParse(normalized)?.toUtc();
}

String formatDuration(Duration d) {
  final h = d.inHours;
  final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
  final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
  return h > 0 ? '$h:$m:$s' : '$m:$s';
}

String formatRuntime(int? minutes) {
  if (minutes == null || minutes <= 0) return '';
  final h = minutes ~/ 60;
  final m = minutes % 60;
  if (h == 0) return '${m}m';
  return m == 0 ? '${h}h' : '${h}h ${m}m';
}
