import '../core/util/json.dart';
import '../core/util/time.dart';
import 'media_card.dart';

/// `GET /auth/watch-progress` and entries of the batch map.
class WatchProgress {
  const WatchProgress({required this.contentId, required this.progressSeconds, required this.durationSeconds, required this.completed, required this.lastWatchedAt});
  final int contentId;
  final int progressSeconds;
  final int durationSeconds;
  final bool completed;
  final DateTime? lastWatchedAt;

  double get fraction => durationSeconds > 0 ? (progressSeconds / durationSeconds).clamp(0, 1).toDouble() : 0;

  /// Worth offering "resume" (more than 5 % in, not completed, at least 30 s).
  bool get isResumable => !completed && progressSeconds >= 30 && fraction < 0.95;

  factory WatchProgress.fromJson(Json j, {int? contentId}) => WatchProgress(
        contentId: asIntOrNull(j['content_id']) ?? contentId ?? 0,
        progressSeconds: asInt(j['progress_seconds']),
        durationSeconds: asInt(j['duration_seconds']),
        completed: asBool(j['completed']),
        lastWatchedAt: parseApiDateTime(j['last_watched_at']),
      );

  /// Parses the `/auth/watch-progress/batch` map keyed by content id.
  static Map<int, WatchProgress> mapFromBatch(Object? data) {
    final out = <int, WatchProgress>{};
    if (data is Map) {
      data.forEach((k, v) {
        if (v is Map) {
          final id = asIntOrNull(k) ?? asIntOrNull(asJson(v)['content_id']);
          if (id != null) out[id] = WatchProgress.fromJson(asJson(v), contentId: id);
        }
      });
    } else if (data is List) {
      for (final v in data.whereType<Map>()) {
        final p = WatchProgress.fromJson(asJson(v));
        out[p.contentId] = p;
      }
    }
    return out;
  }
}

/// Item of `/auth/continue-watching`. Episodes are folded into series items
/// (`content_type: "series"`, `id` = series id, `resume_*` fields).
class ContinueWatchingItem {
  const ContinueWatchingItem({
    required this.contentType,
    required this.contentId,
    required this.id,
    required this.title,
    required this.posterUrl,
    required this.backdropUrl,
    required this.year,
    required this.runtime,
    required this.streamUrl,
    required this.progress,
    required this.resumeEpisodeId,
    required this.resumeSeasonNumber,
    required this.resumeEpisodeNumber,
    required this.resumeEpisodeTitle,
  });

  final String contentType;
  final int contentId;
  final int id;
  final String title;
  final String? posterUrl;
  final String? backdropUrl;
  final int? year;
  final int? runtime;
  final String? streamUrl;
  final WatchProgress progress;
  final int? resumeEpisodeId;
  final int? resumeSeasonNumber;
  final int? resumeEpisodeNumber;
  final String? resumeEpisodeTitle;

  factory ContinueWatchingItem.fromJson(Json j) {
    final type = asString(j['content_type'], 'movie');
    return ContinueWatchingItem(
      contentType: type,
      contentId: asInt(j['content_id']),
      id: asInt(j['id'], asInt(j['content_id'])),
      title: asString(j['title'], 'Unknown'),
      posterUrl: asStringOrNull(j['poster_url']),
      backdropUrl: asStringOrNull(j['backdrop_url']),
      year: asIntOrNull(j['year']),
      runtime: asIntOrNull(j['runtime']),
      streamUrl: asStringOrNull(j['stream_url']),
      progress: WatchProgress.fromJson(j, contentId: asInt(j['content_id'])),
      resumeEpisodeId: asIntOrNull(j['resume_episode_id']),
      resumeSeasonNumber: asIntOrNull(j['resume_season_number']),
      resumeEpisodeNumber: asIntOrNull(j['resume_episode_number']),
      resumeEpisodeTitle: asStringOrNull(j['resume_episode_title']),
    );
  }

  MediaCard toCard() => MediaCard(
        id: id,
        type: contentType,
        title: title,
        posterUrl: posterUrl,
        backdropUrl: backdropUrl,
        year: year,
        streamUrl: streamUrl,
        subtitle: resumeSeasonNumber != null && resumeEpisodeNumber != null
            ? 'S$resumeSeasonNumber E$resumeEpisodeNumber'
            : year?.toString(),
        progress: progress.fraction,
      );
}

/// Raw row of `/auth/watchlist` (no title/image; hydrate via detail endpoints).
class WatchlistEntry {
  const WatchlistEntry({required this.contentType, required this.contentId, required this.createdAt});
  final String contentType;
  final int contentId;
  final DateTime? createdAt;

  factory WatchlistEntry.fromJson(Json j) => WatchlistEntry(
        contentType: asString(j['content_type']),
        contentId: asInt(j['content_id']),
        createdAt: parseApiDateTime(j['created_at']),
      );
}

/// `/auth/rate` and `/auth/rating` payload.
class RatingStats {
  const RatingStats({required this.communityRating, required this.ratingCount, required this.userRating});
  final double? communityRating;
  final int ratingCount;
  final int? userRating;

  factory RatingStats.fromJson(Json j) => RatingStats(
        communityRating: asDoubleOrNull(j['community_rating']),
        ratingCount: asInt(j['rating_count']),
        userRating: asIntOrNull(j['user_rating']),
      );
}
