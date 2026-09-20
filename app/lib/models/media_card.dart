/// Lightweight, type-agnostic view of a content item used by rails, grids,
/// search results and layout items. Image paths are raw (may be relative);
/// resolve with [UrlResolver] at render time.
class MediaCard {
  const MediaCard({
    required this.id,
    required this.type,
    required this.title,
    this.posterUrl,
    this.backdropUrl,
    this.logoUrl,
    this.subtitle,
    this.year,
    this.rating,
    this.isRestricted = false,
    this.isAdult = false,
    this.categoryId,
    this.streamUrl,
    this.linkUrl,
    this.progress,
  });

  final int id;

  /// movie | series | channel | category | episode | custom
  final String type;
  final String title;
  final String? posterUrl;
  final String? backdropUrl;
  final String? logoUrl;
  final String? subtitle;
  final int? year;
  final double? rating;
  final bool isRestricted;
  final bool isAdult;
  final int? categoryId;
  final String? streamUrl;

  /// For custom layout items.
  final String? linkUrl;

  /// 0..1 when known (continue watching).
  final double? progress;

  /// Best available portrait image, then landscape, then logo.
  String? get primaryImage => posterUrl ?? backdropUrl ?? logoUrl;

  /// Best available landscape image.
  String? get landscapeImage => backdropUrl ?? posterUrl ?? logoUrl;

  MediaCard copyWith({double? progress, String? subtitle}) => MediaCard(
        id: id,
        type: type,
        title: title,
        posterUrl: posterUrl,
        backdropUrl: backdropUrl,
        logoUrl: logoUrl,
        subtitle: subtitle ?? this.subtitle,
        year: year,
        rating: rating,
        isRestricted: isRestricted,
        isAdult: isAdult,
        categoryId: categoryId,
        streamUrl: streamUrl,
        linkUrl: linkUrl,
        progress: progress ?? this.progress,
      );
}
