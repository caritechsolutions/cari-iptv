import '../../../core/network/api_client.dart';
import '../../../models/recommendation.dart';

/// `/recommendations`. The first call for a subscriber may be slow (the
/// backend generates the taste profile synchronously).
class RecommendationRepository {
  RecommendationRepository(this._api);
  final ApiClient _api;

  /// `type` ∈ movie | series (null = mixed)
  Future<List<RecommendationSet>> sets({String? type}) async {
    final res = await _api.get('/recommendations', query: {'type': ?type});
    return res.envelope.dataAsList.map(RecommendationSet.fromJson).toList(growable: false);
  }
}
