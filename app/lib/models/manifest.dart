import '../core/util/json.dart';

/// `/manifest?platform=mobile` → version hashes per content scope.
/// Scope names double as cache scopes in [CacheStore].
class Manifest {
  const Manifest(this.versions);

  /// scope → version hash (channels, movies, series, categories, epg, layouts, navigation)
  final Map<String, String> versions;

  static const scopes = ['channels', 'movies', 'series', 'categories', 'epg', 'layouts', 'navigation'];

  factory Manifest.fromJson(Json j, {String platform = 'mobile'}) {
    final out = <String, String>{};
    for (final scope in const ['channels', 'movies', 'series', 'categories', 'epg']) {
      final v = asStringOrNull(asJson(j[scope])['version']);
      if (v != null) out[scope] = v;
    }
    for (final scope in const ['layouts', 'navigation']) {
      final perPlatform = asJson(j[scope]);
      final v = asStringOrNull(asJson(perPlatform[platform])['version']);
      if (v != null) out[scope] = v;
    }
    return Manifest(out);
  }

  /// Scopes whose hash differs from [previous] (missing on either side counts as changed).
  List<String> changedScopes(Map<String, String?> previous) => versions.entries
      .where((e) => previous[e.key] != e.value)
      .map((e) => e.key)
      .toList(growable: false);
}
