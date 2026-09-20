import '../core/util/json.dart';
import 'media_card.dart';

/// One row of `/search` (flat, normalised to `image_url`).
class SearchResult {
  const SearchResult({required this.id, required this.title, required this.slug, required this.imageUrl, required this.contentType, required this.year, required this.voteAverage});
  final int id;
  final String title;
  final String slug;
  final String? imageUrl;
  final String contentType;
  final int? year;
  final double? voteAverage;

  factory SearchResult.fromJson(Json j) => SearchResult(
        id: asInt(j['id']),
        title: asString(j['title'], asString(j['name'])),
        slug: asString(j['slug']),
        imageUrl: asStringOrNull(j['image_url']),
        contentType: asString(j['content_type']),
        year: asIntOrNull(j['year']),
        voteAverage: asDoubleOrNull(j['vote_average']),
      );

  MediaCard toCard() => MediaCard(
        id: id,
        type: contentType,
        title: title,
        posterUrl: contentType == 'channel' ? null : imageUrl,
        logoUrl: contentType == 'channel' ? imageUrl : null,
        year: year,
        rating: voteAverage,
        subtitle: year?.toString(),
      );
}
