import '../core/util/json.dart';
import 'content_extras.dart';
import 'media_card.dart';

/// `next_episode` / `prev_episode` on `/episodes/{id}`.
class EpisodeRef {
  const EpisodeRef({required this.id, required this.episodeNumber, required this.title, required this.streamUrl, required this.stillUrl, required this.runtime, required this.seasonNumber});
  final int id;
  final int episodeNumber;
  final String title;
  final String? streamUrl;
  final String? stillUrl;
  final int? runtime;
  final int? seasonNumber;

  static EpisodeRef? fromJsonOrNull(Object? v) {
    if (v is! Map) return null;
    final j = asJson(v);
    return EpisodeRef(
      id: asInt(j['id']),
      episodeNumber: asInt(j['episode_number']),
      title: asString(j['title']),
      streamUrl: asStringOrNull(j['stream_url']),
      stillUrl: asStringOrNull(j['still_url']),
      runtime: asIntOrNull(j['runtime']),
      seasonNumber: asIntOrNull(j['season_number']),
    );
  }
}

class Episode {
  const Episode({
    required this.id,
    required this.seriesId,
    required this.seasonId,
    required this.episodeNumber,
    required this.seasonNumber,
    required this.title,
    required this.synopsis,
    required this.runtime,
    required this.streamUrl,
    required this.stillUrl,
    required this.airDate,
    required this.voteAverage,
    required this.vodStatus,
    required this.seriesTitle,
    required this.seriesPosterUrl,
    required this.seriesBackdropUrl,
    required this.drm,
    required this.markers,
    required this.subtitles,
    required this.nextEpisode,
    required this.prevEpisode,
  });

  final int id;
  final int? seriesId;
  final int? seasonId;
  final int episodeNumber;
  final int? seasonNumber;
  final String title;
  final String? synopsis;
  final int? runtime;
  final String? streamUrl;
  final String? stillUrl;
  final String? airDate;
  final double? voteAverage;
  final String? vodStatus;
  final String? seriesTitle;
  final String? seriesPosterUrl;
  final String? seriesBackdropUrl;
  final DrmInfo? drm;
  final List<ContentMarker> markers;
  final List<Subtitle> subtitles;
  final EpisodeRef? nextEpisode;
  final EpisodeRef? prevEpisode;

  bool get isPlayable => streamUrl != null && streamUrl!.isNotEmpty;

  String get code => seasonNumber != null ? 'S${seasonNumber!}E$episodeNumber' : 'E$episodeNumber';

  factory Episode.fromJson(Json j, {int? seriesId, int? seasonId, int? seasonNumber}) => Episode(
        id: asInt(j['id']),
        seriesId: asIntOrNull(j['series_id']) ?? seriesId,
        seasonId: asIntOrNull(j['season_id']) ?? seasonId,
        episodeNumber: asInt(j['episode_number']),
        seasonNumber: asIntOrNull(j['season_number']) ?? seasonNumber,
        title: asString(j['title'], asString(j['name'])),
        synopsis: asStringOrNull(j['synopsis']) ?? asStringOrNull(j['overview']),
        runtime: asIntOrNull(j['runtime']),
        streamUrl: asStringOrNull(j['stream_url']),
        stillUrl: asStringOrNull(j['still_url']),
        airDate: asStringOrNull(j['air_date']),
        voteAverage: asDoubleOrNull(j['vote_average']),
        vodStatus: asStringOrNull(j['vod_status']),
        seriesTitle: asStringOrNull(j['series_title']),
        seriesPosterUrl: asStringOrNull(j['series_poster_url']),
        seriesBackdropUrl: asStringOrNull(j['series_backdrop_url']),
        drm: DrmInfo.fromJsonOrNull(j['drm']),
        markers: asJsonList(j['markers']).map(ContentMarker.fromJson).toList(growable: false),
        subtitles: asJsonList(j['subtitles']).map(Subtitle.fromJson).toList(growable: false),
        nextEpisode: EpisodeRef.fromJsonOrNull(j['next_episode']),
        prevEpisode: EpisodeRef.fromJsonOrNull(j['prev_episode']),
      );
}

class Season {
  const Season({required this.id, required this.seasonNumber, required this.name, required this.overview, required this.posterUrl, required this.episodes});
  final int id;
  final int seasonNumber;
  final String name;
  final String? overview;
  final String? posterUrl;
  final List<Episode> episodes;

  factory Season.fromJson(Json j, {int? seriesId}) {
    final id = asInt(j['id']);
    final number = asInt(j['season_number']);
    return Season(
      id: id,
      seasonNumber: number,
      name: asString(j['name'], 'Season $number'),
      overview: asStringOrNull(j['overview']) ?? asStringOrNull(j['synopsis']),
      posterUrl: asStringOrNull(j['poster_url']),
      episodes: asJsonList(j['episodes'])
          .map((e) => Episode.fromJson(e, seriesId: seriesId, seasonId: id, seasonNumber: number))
          .toList(growable: false),
    );
  }
}

class Series {
  const Series({
    required this.id,
    required this.title,
    required this.slug,
    required this.year,
    required this.genres,
    required this.synopsis,
    required this.voteAverage,
    required this.posterUrl,
    required this.backdropUrl,
    required this.isFeatured,
    required this.isAdult,
    required this.isRestricted,
    required this.categoryId,
    required this.categoryName,
    required this.seasonCount,
    required this.episodeCount,
    required this.communityRating,
    required this.communityRatingCount,
    required this.seasons,
    required this.trailers,
    required this.cast,
  });

  final int id;
  final String title;
  final String slug;
  final int? year;
  final List<String> genres;
  final String? synopsis;
  final double? voteAverage;
  final String? posterUrl;
  final String? backdropUrl;
  final bool isFeatured;
  final bool isAdult;
  final bool isRestricted;
  final int? categoryId;
  final String? categoryName;
  final int? seasonCount;
  final int? episodeCount;
  final double? communityRating;
  final int communityRatingCount;
  final List<Season> seasons;
  final List<Trailer> trailers;
  final List<CastMember> cast;

  factory Series.fromJson(Json j) {
    final id = asInt(j['id']);
    return Series(
      id: id,
      title: asString(j['title']),
      slug: asString(j['slug']),
      year: asIntOrNull(j['year']),
      genres: asStringList(j['genres']),
      synopsis: asStringOrNull(j['synopsis']),
      voteAverage: asDoubleOrNull(j['vote_average']),
      posterUrl: asStringOrNull(j['poster_url']),
      backdropUrl: asStringOrNull(j['backdrop_url']),
      isFeatured: asBool(j['is_featured']),
      isAdult: asBool(j['is_adult']),
      isRestricted: asBool(j['is_restricted']),
      categoryId: asIntOrNull(j['category_id']),
      categoryName: asStringOrNull(j['category_name']),
      seasonCount: asIntOrNull(j['season_count']) ?? asIntOrNull(j['number_of_seasons']),
      episodeCount: asIntOrNull(j['episode_count']) ?? asIntOrNull(j['number_of_episodes']),
      communityRating: asDoubleOrNull(j['community_rating']),
      communityRatingCount: asInt(j['community_rating_count']),
      seasons: asJsonList(j['seasons']).map((s) => Season.fromJson(s, seriesId: id)).toList(growable: false),
      trailers: asJsonList(j['trailers']).map(Trailer.fromJson).toList(growable: false),
      cast: asJsonList(j['cast']).map(CastMember.fromJson).toList(growable: false),
    );
  }

  MediaCard toCard() => MediaCard(
        id: id,
        type: 'series',
        title: title,
        posterUrl: posterUrl,
        backdropUrl: backdropUrl,
        year: year,
        rating: voteAverage,
        subtitle: seasonCount != null ? '$seasonCount season${seasonCount == 1 ? '' : 's'}' : year?.toString(),
        isRestricted: isRestricted,
        isAdult: isAdult,
        categoryId: categoryId,
      );
}
