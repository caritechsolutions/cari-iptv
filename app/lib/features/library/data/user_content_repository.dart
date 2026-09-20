import '../../../core/network/api_client.dart';
import '../../../core/util/json.dart';
import '../../../models/watch.dart';

/// Per-subscriber content state: watch progress, continue watching, watchlist,
/// ratings. See docs/API_DISCOVERY.md §2.
class UserContentRepository {
  UserContentRepository(this._api);
  final ApiClient _api;

  /// `contentType` ∈ movie | episode | channel
  Future<void> saveProgress({required String contentType, required int contentId, required int progress, required int duration}) async {
    await _api.post('/auth/watch-progress', body: {
      'content_type': contentType,
      'content_id': contentId,
      'progress': progress,
      'duration': duration,
    });
  }

  Future<WatchProgress?> progress({required String contentType, required int contentId}) async {
    final res = await _api.get('/auth/watch-progress', query: {'content_type': contentType, 'content_id': contentId});
    final data = res.envelope.data;
    if (data is! Map || data.isEmpty) return null;
    return WatchProgress.fromJson(asJson(data), contentId: contentId);
  }

  /// Up to 100 ids per call; larger lists are chunked.
  Future<Map<int, WatchProgress>> batchProgress({required String contentType, required List<int> ids}) async {
    final out = <int, WatchProgress>{};
    for (var i = 0; i < ids.length; i += 100) {
      final chunk = ids.sublist(i, i + 100 > ids.length ? ids.length : i + 100);
      if (chunk.isEmpty) continue;
      final res = await _api.get('/auth/watch-progress/batch', query: {'content_type': contentType, 'ids': chunk.join(',')});
      out.addAll(WatchProgress.mapFromBatch(res.envelope.data));
    }
    return out;
  }

  /// `type` ∈ movie | series (null = both)
  Future<List<ContinueWatchingItem>> continueWatching({String? type}) async {
    final res = await _api.get('/auth/continue-watching', query: {'type': ?type});
    return res.envelope.dataAsList.map(ContinueWatchingItem.fromJson).toList(growable: false);
  }

  Future<List<WatchlistEntry>> watchlist() async {
    final res = await _api.get('/auth/watchlist');
    return res.envelope.dataAsList.map(WatchlistEntry.fromJson).toList(growable: false);
  }

  /// Returns the new state (true = now in the list). `contentType` ∈ movie | series | channel.
  Future<bool> toggleWatchlist({required String contentType, required int contentId}) async {
    final env = await _api.post('/auth/watchlist/toggle', body: {'content_type': contentType, 'content_id': contentId});
    return asBool(env.dataAsJson['in_watchlist']);
  }

  Future<RatingStats> rate({required String contentType, required int contentId, required int rating}) async {
    final env = await _api.post('/auth/rate', body: {'content_type': contentType, 'content_id': contentId, 'rating': rating});
    return RatingStats.fromJson(env.dataAsJson);
  }

  Future<RatingStats> rating({required String contentType, required int contentId}) async {
    final res = await _api.get('/auth/rating', query: {'content_type': contentType, 'content_id': contentId});
    return RatingStats.fromJson(res.envelope.dataAsJson);
  }
}
