import '../core/util/json.dart';
import 'media_card.dart';

/// One set of `/recommendations`. `setType` ∈ for_you | because_watched |
/// trending | top_picks | hidden_gems | new_releases | evening_plan.
class RecommendationSet {
  const RecommendationSet({required this.id, required this.setType, required this.title, required this.reason, required this.priority, required this.items});
  final int id;
  final String setType;
  final String title;
  final String? reason;
  final int priority;
  final List<MediaCard> items;

  factory RecommendationSet.fromJson(Json j) => RecommendationSet(
        id: asInt(j['id']),
        setType: asString(j['set_type']),
        title: asString(j['title']),
        reason: asStringOrNull(j['reason']),
        priority: asInt(j['priority']),
        items: asJsonList(j['items']).map(_cardFromItem).whereType<MediaCard>().toList(growable: false),
      );

  static MediaCard? _cardFromItem(Json c) {
    final id = asIntOrNull(c['id']);
    if (id == null) return null;
    final type = asString(c['content_type'], 'movie');
    return MediaCard(
      id: id,
      type: type,
      title: asString(c['title']),
      posterUrl: asStringOrNull(c['poster_url']),
      backdropUrl: asStringOrNull(c['backdrop_url']),
      year: asIntOrNull(c['year']),
      rating: asDoubleOrNull(c['vote_average']),
      subtitle: asStringOrNull(c['recommendation_reason']) ?? asIntOrNull(c['year'])?.toString(),
      isAdult: asBool(c['is_adult']),
      isRestricted: asBool(c['is_restricted']),
    );
  }
}
