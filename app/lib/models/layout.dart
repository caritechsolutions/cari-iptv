import '../core/util/json.dart';
import 'media_card.dart';

/// One `items[]` entry of a layout section with its resolved `content`.
class LayoutItem {
  const LayoutItem({required this.id, required this.contentType, required this.contentId, required this.settings, required this.sortOrder, required this.content});
  final int id;
  final String contentType;
  final int? contentId;
  final Json settings;
  final int sortOrder;
  final Json content;

  factory LayoutItem.fromJson(Json j) => LayoutItem(
        id: asInt(j['id']),
        contentType: asString(j['content_type'], 'custom'),
        contentId: asIntOrNull(j['content_id']),
        settings: asJson(j['settings']),
        sortOrder: asInt(j['sort_order']),
        content: asJson(j['content']),
      );

  /// Flattens `content` (movie/series/channel/category/custom) into a card.
  MediaCard? toCard() {
    if (contentType == 'custom') {
      final title = asString(settings['title'], asString(content['title']));
      final image = asStringOrNull(settings['image_url']) ?? asStringOrNull(content['image_url']);
      if (title.isEmpty && image == null) return null;
      return MediaCard(
        id: id,
        type: 'custom',
        title: title,
        backdropUrl: image,
        linkUrl: asStringOrNull(settings['link_url']) ?? asStringOrNull(content['link_url']),
      );
    }
    if (content.isEmpty) return null;
    final c = content;
    final cardId = asIntOrNull(c['id']) ?? contentId;
    if (cardId == null) return null;
    switch (contentType) {
      case 'movie':
      case 'series':
        return MediaCard(
          id: cardId,
          type: contentType,
          title: asString(c['title']),
          posterUrl: asStringOrNull(c['poster_url']),
          backdropUrl: asStringOrNull(c['backdrop_url']),
          year: asIntOrNull(c['year']),
          rating: asDoubleOrNull(c['vote_average']),
          subtitle: asIntOrNull(c['year'])?.toString(),
          isRestricted: asBool(c['is_restricted']),
          isAdult: asBool(c['is_adult']),
          streamUrl: asStringOrNull(c['stream_url']),
        );
      case 'channel':
        return MediaCard(
          id: cardId,
          type: 'channel',
          title: asString(c['name'], asString(c['title'])),
          logoUrl: asStringOrNull(c['logo_url']),
          streamUrl: asStringOrNull(c['stream_url']),
          isRestricted: asBool(c['is_restricted']),
        );
      case 'category':
        return MediaCard(id: cardId, type: 'category', title: asString(c['name']), subtitle: asStringOrNull(c['type']));
      default:
        return null;
    }
  }
}

class LayoutSection {
  const LayoutSection({required this.id, required this.type, required this.title, required this.settings, required this.sortOrder, required this.items});
  final int id;
  final String type;
  final String? title;
  final Json settings;
  final int sortOrder;
  final List<LayoutItem> items;

  String get source => asString(settings['source'], 'curated');
  int get maxItems => asInt(settings['max_items'], 20);

  factory LayoutSection.fromJson(Json j) => LayoutSection(
        id: asInt(j['id']),
        type: asString(j['section_type']),
        title: asStringOrNull(j['title']),
        settings: asJson(j['settings']),
        sortOrder: asInt(j['sort_order']),
        items: asJsonList(j['items']).map(LayoutItem.fromJson).toList(growable: false),
      );

  List<MediaCard> get cards => items.map((i) => i.toCard()).whereType<MediaCard>().toList(growable: false);
}

class AppLayout {
  const AppLayout({required this.id, required this.name, required this.platform, required this.status, required this.updatedAt, required this.sections});
  final int id;
  final String name;
  final String platform;
  final String status;
  final String? updatedAt;
  final List<LayoutSection> sections;

  factory AppLayout.fromJson(Json j) => AppLayout(
        id: asInt(j['id']),
        name: asString(j['name']),
        platform: asString(j['platform']),
        status: asString(j['status']),
        updatedAt: asStringOrNull(j['updated_at']),
        sections: asJsonList(j['sections']).map(LayoutSection.fromJson).toList(growable: false),
      );
}
