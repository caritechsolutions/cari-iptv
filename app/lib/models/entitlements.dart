import '../core/util/json.dart';

/// A package as listed by `/auth/entitlements` (`packages[]`). View-only in
/// the app: there are no purchase flows.
class Package {
  const Package({
    required this.id,
    required this.name,
    required this.slug,
    required this.description,
    required this.priceDisplay,
    required this.billingPeriod,
    required this.trialDays,
    required this.maxConnections,
    required this.features,
    required this.isSubscribed,
    required this.isAdult,
    required this.isFree,
    required this.isFeatured,
    required this.color,
  });

  final int id;
  final String name;
  final String slug;
  final String description;
  final String priceDisplay;
  final String billingPeriod;
  final int trialDays;
  final int maxConnections;
  final List<String> features;
  final bool isSubscribed;
  final bool isAdult;
  final bool isFree;
  final bool isFeatured;
  final String? color;

  factory Package.fromJson(Json j) => Package(
        id: asInt(j['id']),
        name: asString(j['name']),
        slug: asString(j['slug']),
        description: asString(j['description']),
        priceDisplay: asString(j['price_display']),
        billingPeriod: asString(j['billing_period']),
        trialDays: asInt(j['trial_days']),
        maxConnections: asInt(j['max_connections'], 1),
        features: asStringList(j['features']),
        isSubscribed: asBool(j['is_subscribed']),
        isAdult: asBool(j['is_adult']),
        isFree: asBool(j['is_free']),
        isFeatured: asBool(j['is_featured']),
        color: asStringOrNull(j['color']),
      );
}

/// `/auth/entitlements` payload. Content endpoints only flag `is_restricted`;
/// the app decides access by checking these id sets (like the web player).
class Entitlements {
  const Entitlements({
    required this.movieIds,
    required this.seriesIds,
    required this.channelIds,
    required this.categoryIds,
    required this.packages,
    required this.hasSubscription,
    required this.adultEnabled,
  });

  final Set<int> movieIds;
  final Set<int> seriesIds;
  final Set<int> channelIds;
  final Set<int> categoryIds;
  final List<Package> packages;
  final bool hasSubscription;
  final bool adultEnabled;

  static const empty = Entitlements(
    movieIds: {},
    seriesIds: {},
    channelIds: {},
    categoryIds: {},
    packages: [],
    hasSubscription: false,
    adultEnabled: false,
  );

  factory Entitlements.fromJson(Json j) => Entitlements(
        movieIds: asIntList(j['movies']).toSet(),
        seriesIds: asIntList(j['series']).toSet(),
        channelIds: asIntList(j['channels']).toSet(),
        categoryIds: asIntList(j['categories']).toSet(),
        packages: asJsonList(j['packages']).map(Package.fromJson).toList(growable: false),
        hasSubscription: asBool(j['has_subscription']),
        adultEnabled: asBool(j['adult_enabled']),
      );

  /// The web player's lock rule (`isContentLocked` in app.js): with no
  /// active subscription everything is locked; otherwise only restricted
  /// content whose id (or category) is absent from the entitled sets. The
  /// same rule drives the padlock badges and the play gate.
  bool locks({required String type, required int id, int? categoryId, required bool isRestricted}) {
    if (!hasSubscription) return true;
    if (!isRestricted) return false;
    return !allows(type, id, categoryId: categoryId);
  }

  /// True when a restricted item of [type] with [id] is included in an active package.
  bool allows(String type, int id, {int? categoryId}) {
    if (categoryId != null && categoryIds.contains(categoryId)) return true;
    switch (type) {
      case 'movie':
        return movieIds.contains(id);
      case 'series':
      case 'episode':
        return seriesIds.contains(id);
      case 'channel':
        return channelIds.contains(id);
      default:
        return false;
    }
  }
}
