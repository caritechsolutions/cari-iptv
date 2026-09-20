import 'dart:convert';

import 'package:hive_ce_flutter/hive_flutter.dart';

/// Offline cache of API responses, invalidated per content type using the
/// `/manifest` version hashes (same idea as the web player's cache busting).
///
/// Entries are stored as JSON strings under the request URL, tagged with a
/// content "scope" (channels, movies, series, categories, epg, layouts,
/// navigation, user) so that a changed manifest hash clears only that scope.
class CacheStore {
  CacheStore._(this._data, this._scopes, this._manifest);

  final Box<String> _data; // url -> json
  final Box<String> _scopes; // url -> scope
  final Box<String> _manifest; // scope -> version hash

  static Future<CacheStore> open() async {
    await Hive.initFlutter('cari_tv');
    final data = await Hive.openBox<String>('api_cache');
    final scopes = await Hive.openBox<String>('api_cache_scopes');
    final manifest = await Hive.openBox<String>('manifest_versions');
    return CacheStore._(data, scopes, manifest);
  }

  /// Test/in-memory variant (Hive must be initialised by the caller).
  static CacheStore fromBoxes(Box<String> data, Box<String> scopes, Box<String> manifest) =>
      CacheStore._(data, scopes, manifest);

  Object? get(String key) {
    final raw = _data.get(key);
    if (raw == null) return null;
    try {
      return jsonDecode(raw);
    } catch (_) {
      return null;
    }
  }

  Future<void> put(String key, Object? body, {required String scope}) async {
    try {
      await _data.put(key, jsonEncode(body));
      await _scopes.put(key, scope);
    } catch (_) {
      // Cache failures are never fatal.
    }
  }

  Future<void> invalidateScope(String scope) async {
    final keys = _scopes.keys.where((k) => _scopes.get(k) == scope).toList();
    await _data.deleteAll(keys);
    await _scopes.deleteAll(keys);
  }

  Future<void> clearAll() async {
    await _data.clear();
    await _scopes.clear();
    await _manifest.clear();
  }

  String? manifestVersion(String scope) => _manifest.get(scope);

  Future<void> setManifestVersion(String scope, String version) => _manifest.put(scope, version);

  int get entryCount => _data.length;
}
