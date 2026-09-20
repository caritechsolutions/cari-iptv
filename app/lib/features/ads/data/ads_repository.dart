import '../../../core/network/api_client.dart';
import '../../../models/ad.dart';

/// Context sent with every ad request (mirrors the web player's `_context`).
class AdContext {
  const AdContext({this.contentType, this.contentId, this.channelId, this.categoryId, this.packageId, this.userId});
  final String? contentType;
  final int? contentId;
  final int? channelId;
  final int? categoryId;
  final int? packageId;
  final int? userId;

  Map<String, dynamic> toQuery(String platform) => {
        'platform': platform,
        'content_type': ?contentType,
        'content_id': ?contentId,
        'channel_id': ?channelId,
        'category_id': ?categoryId,
        'package_id': ?packageId,
        'user_id': ?userId,
      };
}

/// Public ad endpoints (no JWT). Impressions/events are form-encoded.
class AdsRepository {
  AdsRepository(this._api);
  final ApiClient _api;

  String get _platform => _api.config.platformTag;

  /// `zoneType` ∈ pre_roll | mid_roll | banner | text_scroller
  Future<AdServeResult> serve(String zoneType, AdContext ctx, {int limit = 3}) async {
    try {
      final res = await _api.get('/ads/serve', auth: false, query: {
        'zone_type': zoneType,
        'limit': limit,
        ...ctx.toQuery(_platform),
      });
      return AdServeResult.fromJson(res.envelope.dataAsJson);
    } catch (_) {
      // Ads must never block playback.
      return AdServeResult.empty;
    }
  }

  Future<List<AdBreak>> breaks({required String contentType, required int contentId, required int durationSeconds}) async {
    try {
      final res = await _api.get('/ads/breaks', auth: false, query: {
        'content_type': contentType,
        'content_id': contentId,
        'duration': durationSeconds,
      });
      final list = res.envelope.dataAsJson['breaks'];
      return (list is List) ? list.whereType<Map>().map((b) => AdBreak.fromJson(Map<String, dynamic>.from(b))).toList(growable: false) : const [];
    } catch (_) {
      return const [];
    }
  }

  Future<OverlaySettings> overlaySettings() async {
    try {
      final res = await _api.get('/ads/overlay-settings', auth: false, cacheScope: 'ads');
      return OverlaySettings.fromJson(res.envelope.dataAsJson);
    } catch (_) {
      return OverlaySettings.defaults;
    }
  }

  /// Returns the impression id (needed for events) or null.
  Future<int?> impression(Ad ad, AdContext ctx) async {
    try {
      final env = await _api.post('/ads/impression', auth: false, formEncoded: true, body: {
        'campaign_id': ad.campaignId,
        'creative_id': ad.id,
        if (ad.placementId != null) 'placement_id': ad.placementId,
        'platform': _platform,
        if (ctx.contentType != null) 'content_type': ctx.contentType,
        if (ctx.contentId != null) 'content_id': ctx.contentId,
        if (ctx.channelId != null) 'channel_id': ctx.channelId,
        if (ctx.userId != null) 'user_id': ctx.userId,
      });
      final id = env.dataAsJson['impression_id'];
      return id is int ? id : int.tryParse('$id');
    } catch (_) {
      return null;
    }
  }

  /// `eventType` ∈ click | complete | skip | error | quartile_25 | quartile_50 | quartile_75 | …
  Future<void> event(Ad ad, String eventType, {int? impressionId, int? userId}) async {
    try {
      await _api.post('/ads/event', auth: false, formEncoded: true, body: {
        'campaign_id': ad.campaignId,
        'creative_id': ad.id,
        'event_type': eventType,
        'impression_id': ?impressionId,
        'user_id': ?userId,
      });
    } catch (_) {}
  }
}
