import '../core/util/json.dart';

/// `features` block of `GET /app/config/{platform}`: server-controlled
/// switches. Every flag defaults to false when the block or the key is absent
/// (older backends), so a missing value never turns a surface on.
class AppFeatures {
  const AppFeatures({this.billing = false});

  /// Admin → Settings → Mobile App → "Show billing in the mobile app"
  /// (`mobile_billing_enabled`). Off hides every billing surface.
  final bool billing;

  static const none = AppFeatures();

  factory AppFeatures.fromJson(Object? features) {
    if (features is! Map) return none;
    final j = asJson(features);
    return AppFeatures(billing: asBool(j['billing']));
  }

  /// Reads the block out of a full `/app/config` payload.
  factory AppFeatures.fromConfig(Json config) => AppFeatures.fromJson(config['features']);
}
