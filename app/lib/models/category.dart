import '../core/util/json.dart';
import 'media_card.dart';

/// `type` ∈ live | vod | series
class Category {
  const Category({required this.id, required this.name, required this.slug, required this.type, required this.icon, required this.sortOrder});
  final int id;
  final String name;
  final String slug;
  final String type;
  final String? icon;
  final int sortOrder;

  factory Category.fromJson(Json j) => Category(
        id: asInt(j['id']),
        name: asString(j['name']),
        slug: asString(j['slug']),
        type: asString(j['type'], 'vod'),
        icon: asStringOrNull(j['icon']),
        sortOrder: asInt(j['sort_order']),
      );

  MediaCard toCard() => MediaCard(id: id, type: 'category', title: name, subtitle: type);
}
