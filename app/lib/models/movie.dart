import '../core/util/json.dart';
import 'content_extras.dart';
import 'media_card.dart';

class Movie {
  const Movie({
    required this.id,
    required this.title,
    required this.slug,
    required this.year,
    required this.genres,
    required this.runtime,
    required this.voteAverage,
    required this.posterUrl,
    required this.backdropUrl,
    required this.streamUrl,
    required this.synopsis,
    required this.isFeatured,
    required this.isAdult,
    required this.isRestricted,
    required this.categoryId,
    required this.categoryName,
    required this.vodStatus,
    required this.communityRating,
    required this.communityRatingCount,
    required this.drm,
    required this.trailers,
    required this.cast,
    required this.markers,
    required this.subtitles,
  });

  final int id;
  final String title;
  final String slug;
  final int? year;
  final List<String> genres;
  final int? runtime;
  final double? voteAverage;
  final String? posterUrl;
  final String? backdropUrl;
  final String? streamUrl;
  final String? synopsis;
  final bool isFeatured;
  final bool isAdult;
  final bool isRestricted;
  final int? categoryId;
  final String? categoryName;
  final String? vodStatus;
  final double? communityRating;
  final int communityRatingCount;
  final DrmInfo? drm;
  final List<Trailer> trailers;
  final List<CastMember> cast;
  final List<ContentMarker> markers;
  final List<Subtitle> subtitles;

  bool get isPlayable => streamUrl != null && streamUrl!.isNotEmpty;

  factory Movie.fromJson(Json j) => Movie(
        id: asInt(j['id']),
        title: asString(j['title']),
        slug: asString(j['slug']),
        year: asIntOrNull(j['year']),
        genres: asStringList(j['genres']),
        runtime: asIntOrNull(j['runtime']),
        voteAverage: asDoubleOrNull(j['vote_average']),
        posterUrl: asStringOrNull(j['poster_url']),
        backdropUrl: asStringOrNull(j['backdrop_url']),
        streamUrl: asStringOrNull(j['stream_url']),
        synopsis: asStringOrNull(j['synopsis']),
        isFeatured: asBool(j['is_featured']),
        isAdult: asBool(j['is_adult']),
        isRestricted: asBool(j['is_restricted']),
        categoryId: asIntOrNull(j['category_id']),
        categoryName: asStringOrNull(j['category_name']),
        vodStatus: asStringOrNull(j['vod_status']),
        communityRating: asDoubleOrNull(j['community_rating']),
        communityRatingCount: asInt(j['community_rating_count']),
        drm: DrmInfo.fromJsonOrNull(j['drm']),
        trailers: asJsonList(j['trailers']).map(Trailer.fromJson).toList(growable: false),
        cast: asJsonList(j['cast']).map(CastMember.fromJson).toList(growable: false),
        markers: asJsonList(j['markers']).map(ContentMarker.fromJson).toList(growable: false),
        subtitles: asJsonList(j['subtitles']).map(Subtitle.fromJson).toList(growable: false),
      );

  MediaCard toCard() => MediaCard(
        id: id,
        type: 'movie',
        title: title,
        posterUrl: posterUrl,
        backdropUrl: backdropUrl,
        year: year,
        rating: voteAverage,
        subtitle: year?.toString(),
        isRestricted: isRestricted,
        isAdult: isAdult,
        categoryId: categoryId,
        streamUrl: streamUrl,
      );
}
