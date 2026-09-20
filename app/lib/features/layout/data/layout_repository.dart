import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../models/layout.dart';
import '../../../models/manifest.dart';
import '../../../models/navigation.dart';

/// `/app/*` and `/manifest`. Platform is always `mobile`; the API has no
/// fallback to another platform (docs/API_DISCOVERY.md §5).
class LayoutRepository {
  LayoutRepository(this._api);
  final ApiClient _api;

  static const platform = 'mobile';

  Future<Manifest> manifest() async {
    final res = await _api.get('/manifest', query: {'platform': platform});
    return Manifest.fromJson(res.envelope.dataAsJson, platform: platform);
  }

  /// Default published mobile layout, or `null` when none exists (404).
  Future<AppLayout?> defaultLayout({bool preferCache = false}) async {
    try {
      final res = await _api.get('/app/layout/$platform', cacheScope: 'layouts', preferCache: preferCache);
      return AppLayout.fromJson(res.envelope.dataAsJson);
    } on ApiException catch (e) {
      if (e.isNotFound) return null;
      rethrow;
    }
  }

  Future<AppLayout?> layoutById(int id, {bool preferCache = false}) async {
    try {
      final res = await _api.get('/app/layout/$platform', query: {'id': id}, cacheScope: 'layouts', preferCache: preferCache);
      return AppLayout.fromJson(res.envelope.dataAsJson);
    } on ApiException catch (e) {
      if (e.isNotFound) return null;
      rethrow;
    }
  }

  Future<AppNavigation?> navigation({String position = 'main', bool preferCache = false}) async {
    try {
      final res = await _api.get('/app/navigation/$platform', query: {'position': position}, cacheScope: 'navigation', preferCache: preferCache);
      return AppNavigation.fromJson(res.envelope.dataAsJson);
    } on ApiException catch (e) {
      if (e.isNotFound) return null;
      rethrow;
    }
  }

  Future<List<AppPage>> pages({bool preferCache = false}) async {
    final res = await _api.get('/app/pages/$platform', cacheScope: 'navigation', preferCache: preferCache);
    return res.envelope.dataAsList.map(AppPage.fromJson).toList(growable: false);
  }
}
