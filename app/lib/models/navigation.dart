import '../core/util/json.dart';

/// Row of `/app/pages/{platform}`.
class AppPage {
  const AppPage({required this.id, required this.name, required this.slug, required this.pageType, required this.icon, required this.layoutId, required this.isSystem, required this.sortOrder});
  final int id;
  final String name;
  final String slug;
  final String pageType;
  final String? icon;
  final int? layoutId;
  final bool isSystem;
  final int sortOrder;

  factory AppPage.fromJson(Json j) => AppPage(
        id: asInt(j['id']),
        name: asString(j['name']),
        slug: asString(j['slug']),
        pageType: asString(j['page_type'], 'custom'),
        icon: asStringOrNull(j['icon']),
        layoutId: asIntOrNull(j['layout_id']),
        isSystem: asBool(j['is_system']),
        sortOrder: asInt(j['sort_order']),
      );
}

/// Item of `/app/navigation/{platform}`.
class NavItem {
  const NavItem({required this.id, required this.label, required this.icon, required this.target, required this.url, required this.sortOrder, required this.pageSlug, required this.pageType, required this.layoutId});
  final int id;
  final String label;
  final String? icon;

  /// page | url | deeplink
  final String target;
  final String? url;
  final int sortOrder;
  final String? pageSlug;
  final String? pageType;
  final int? layoutId;

  factory NavItem.fromJson(Json j) => NavItem(
        id: asInt(j['id']),
        label: asString(j['label']),
        icon: asStringOrNull(j['icon']),
        target: asString(j['target'], 'page'),
        url: asStringOrNull(j['url']),
        sortOrder: asInt(j['sort_order']),
        pageSlug: asStringOrNull(j['page_slug']),
        pageType: asStringOrNull(j['page_type']),
        layoutId: asIntOrNull(j['layout_id']),
      );
}

class AppNavigation {
  const AppNavigation({required this.id, required this.platform, required this.position, required this.style, required this.showIcons, required this.showLabels, required this.maxItems, required this.items});
  final int id;
  final String platform;
  final String position;
  final String style;
  final bool showIcons;
  final bool showLabels;
  final int maxItems;
  final List<NavItem> items;

  factory AppNavigation.fromJson(Json j) {
    final settings = asJson(j['settings']);
    return AppNavigation(
      id: asInt(j['id']),
      platform: asString(j['platform']),
      position: asString(j['position'], 'main'),
      style: asString(settings['style'], 'bottom_tab'),
      showIcons: asBool(settings['show_icons'], true),
      showLabels: asBool(settings['show_labels'], true),
      maxItems: asInt(settings['max_items'], 5),
      items: asJsonList(j['items']).map(NavItem.fromJson).toList(growable: false),
    );
  }
}
