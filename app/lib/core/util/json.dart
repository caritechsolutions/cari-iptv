/// Tolerant JSON accessors.
///
/// The PHP backend returns numeric columns inconsistently (PDO may emit `"12"`
/// or `12`, booleans as `0/1`, `"0"/"1"` or `true/false`), so every model
/// parses through these helpers instead of casting directly.
library;

typedef Json = Map<String, dynamic>;

int? asIntOrNull(Object? v) {
  if (v == null) return null;
  if (v is int) return v;
  if (v is double) return v.toInt();
  if (v is bool) return v ? 1 : 0;
  if (v is String) {
    final s = v.trim();
    if (s.isEmpty) return null;
    return int.tryParse(s) ?? double.tryParse(s)?.toInt();
  }
  return null;
}

int asInt(Object? v, [int fallback = 0]) => asIntOrNull(v) ?? fallback;

double? asDoubleOrNull(Object? v) {
  if (v == null) return null;
  if (v is double) return v;
  if (v is int) return v.toDouble();
  if (v is String) return double.tryParse(v.trim());
  return null;
}

double asDouble(Object? v, [double fallback = 0]) => asDoubleOrNull(v) ?? fallback;

bool asBool(Object? v, [bool fallback = false]) {
  if (v == null) return fallback;
  if (v is bool) return v;
  if (v is num) return v != 0;
  if (v is String) {
    final s = v.trim().toLowerCase();
    if (s == '1' || s == 'true' || s == 'yes') return true;
    if (s == '0' || s == 'false' || s == 'no' || s.isEmpty) return false;
  }
  return fallback;
}

String? asStringOrNull(Object? v) {
  if (v == null) return null;
  if (v is String) return v.isEmpty ? null : v;
  return v.toString();
}

String asString(Object? v, [String fallback = '']) => asStringOrNull(v) ?? fallback;

Json asJson(Object? v) {
  if (v is Map<String, dynamic>) return v;
  if (v is Map) return v.map((k, val) => MapEntry(k.toString(), val));
  return <String, dynamic>{};
}

List<Json> asJsonList(Object? v) {
  if (v is List) {
    return v.whereType<Map>().map(asJson).toList(growable: false);
  }
  if (v is Map) {
    // Some endpoints return maps keyed by id (e.g. watch-progress/batch)
    return v.values.whereType<Map>().map(asJson).toList(growable: false);
  }
  return const [];
}

List<int> asIntList(Object? v) {
  if (v is List) {
    return v.map(asIntOrNull).whereType<int>().toList(growable: false);
  }
  return const [];
}

/// `genres` may be a JSON array, a JSON-encoded string, or a comma list.
List<String> asStringList(Object? v) {
  if (v == null) return const [];
  if (v is List) return v.map((e) => e.toString()).where((e) => e.isNotEmpty).toList(growable: false);
  if (v is String) {
    final s = v.trim();
    if (s.isEmpty) return const [];
    if (s.startsWith('[')) {
      // Cheap JSON array parse without importing dart:convert in every model
      final inner = s.substring(1, s.length - 1);
      return inner
          .split(',')
          .map((e) => e.trim().replaceAll(RegExp(r'^"|"$'), ''))
          .where((e) => e.isNotEmpty)
          .toList(growable: false);
    }
    return s.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList(growable: false);
  }
  return const [];
}
