import '../core/util/json.dart';

class Trailer {
  const Trailer({required this.id, required this.title, required this.videoKey, required this.url, required this.isPrimary});
  final int id;
  final String title;
  final String? videoKey;
  final String? url;
  final bool isPrimary;

  /// YouTube URL derived from `video_key` when `url` is empty.
  String? get watchUrl => url ?? (videoKey != null ? 'https://www.youtube.com/watch?v=$videoKey' : null);

  factory Trailer.fromJson(Json j) => Trailer(
        id: asInt(j['id']),
        title: asString(j['title'], asString(j['name'], 'Trailer')),
        videoKey: asStringOrNull(j['video_key']),
        url: asStringOrNull(j['url']),
        isPrimary: asBool(j['is_primary']),
      );
}

class CastMember {
  const CastMember({required this.name, required this.character, required this.role, required this.profileUrl, required this.tmdbPersonId});
  final String name;
  final String? character;
  final String? role;
  final String? profileUrl;
  final int? tmdbPersonId;

  factory CastMember.fromJson(Json j) => CastMember(
        name: asString(j['name'], asString(j['person_name'])),
        character: asStringOrNull(j['character_name']) ?? asStringOrNull(j['character']),
        role: asStringOrNull(j['role']) ?? asStringOrNull(j['job']),
        profileUrl: asStringOrNull(j['profile_url']) ?? asStringOrNull(j['profile_image']) ?? asStringOrNull(j['profile_path']),
        tmdbPersonId: asIntOrNull(j['tmdb_person_id']) ?? asIntOrNull(j['tmdb_id']),
      );
}

/// `marker_type` ∈ intro_start | intro_end | credits_start | ad_cue
class ContentMarker {
  const ContentMarker({required this.type, required this.positionSeconds, required this.label});
  final String type;
  final double positionSeconds;
  final String? label;

  factory ContentMarker.fromJson(Json j) => ContentMarker(
        type: asString(j['marker_type']),
        positionSeconds: asDouble(j['position_seconds']),
        label: asStringOrNull(j['label']),
      );
}

class Subtitle {
  const Subtitle({required this.id, required this.languageCode, required this.languageName, required this.filePath, required this.format, required this.isDefault, required this.isForced});
  final int id;
  final String languageCode;
  final String languageName;

  /// Root-relative (`/uploads/.../en.vtt`) — resolve with UrlResolver.
  final String filePath;
  final String format;
  final bool isDefault;
  final bool isForced;

  factory Subtitle.fromJson(Json j) => Subtitle(
        id: asInt(j['id']),
        languageCode: asString(j['language_code']),
        languageName: asString(j['language_name'], asString(j['language_code'])),
        filePath: asString(j['file_path']),
        format: asString(j['format'], 'vtt'),
        isDefault: asBool(j['is_default']),
        isForced: asBool(j['is_forced']),
      );
}

/// ClearKey info on movie/episode detail. Not needed for HLS AES-128 playback
/// (the key URL inside the playlist is public); kept for completeness.
class DrmInfo {
  const DrmInfo({required this.scheme, required this.keyId, required this.licenseUrl});
  final String scheme;
  final String keyId;
  final String licenseUrl;

  static DrmInfo? fromJsonOrNull(Object? v) {
    if (v is! Map) return null;
    final j = asJson(v);
    return DrmInfo(scheme: asString(j['scheme'], 'cenc'), keyId: asString(j['key_id']), licenseUrl: asString(j['license_url']));
  }
}
