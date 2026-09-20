import '../core/util/json.dart';

/// An ad from `/ads/serve`. `type` ∈ pre_roll | mid_roll | banner | text_scroller.
class Ad {
  const Ad({
    required this.id,
    required this.campaignId,
    required this.placementId,
    required this.type,
    required this.zone,
    required this.scrollText,
    required this.scrollSpeed,
    required this.textColor,
    required this.bgColor,
    required this.bgOpacity,
    required this.fontSize,
    required this.imageUrl,
    required this.imageWidth,
    required this.imageHeight,
    required this.bannerPosition,
    required this.clickUrl,
    required this.videoUrl,
    required this.vastTagUrl,
    required this.videoDuration,
    required this.skipAfter,
    required this.altText,
  });

  final int id;
  final int campaignId;
  final int? placementId;
  final String type;
  final String? zone;
  final String? scrollText;
  final int? scrollSpeed;
  final String? textColor;
  final String? bgColor;
  final double? bgOpacity;
  final String fontSize;
  final String? imageUrl;
  final int? imageWidth;
  final int? imageHeight;
  final String? bannerPosition;
  final String? clickUrl;
  final String? videoUrl;
  final String? vastTagUrl;
  final int? videoDuration;
  final int? skipAfter;
  final String? altText;

  bool get isVideo => type == 'pre_roll' || type == 'mid_roll';
  bool get hasPlayableVideo => videoUrl != null && videoUrl!.isNotEmpty;

  factory Ad.fromJson(Json j) => Ad(
        id: asInt(j['id']),
        campaignId: asInt(j['campaign_id']),
        placementId: asIntOrNull(j['placement_id']),
        type: asString(j['type']),
        zone: asStringOrNull(j['zone']),
        scrollText: asStringOrNull(j['scroll_text']),
        scrollSpeed: asIntOrNull(j['scroll_speed']),
        textColor: asStringOrNull(j['text_color']),
        bgColor: asStringOrNull(j['bg_color']),
        bgOpacity: asDoubleOrNull(j['bg_opacity']),
        fontSize: asString(j['font_size'], 'medium'),
        imageUrl: asStringOrNull(j['image_url']),
        imageWidth: asIntOrNull(j['image_width']),
        imageHeight: asIntOrNull(j['image_height']),
        bannerPosition: asStringOrNull(j['banner_position']),
        clickUrl: asStringOrNull(j['click_url']),
        videoUrl: asStringOrNull(j['video_url']),
        vastTagUrl: asStringOrNull(j['vast_tag_url']),
        videoDuration: asIntOrNull(j['video_duration']),
        skipAfter: asIntOrNull(j['skip_after']),
        altText: asStringOrNull(j['alt_text']),
      );
}

/// Whole `/ads/serve` body.
class AdServeResult {
  const AdServeResult({required this.ads, required this.source, required this.vastUrl});
  final List<Ad> ads;
  final String source;
  final String? vastUrl;

  factory AdServeResult.fromJson(Json j) => AdServeResult(
        ads: asJsonList(j['ads']).map(Ad.fromJson).toList(growable: false),
        source: asString(j['source'], 'direct'),
        vastUrl: asStringOrNull(j['vast_url']),
      );

  static const empty = AdServeResult(ads: [], source: 'none', vastUrl: null);
}

/// Entry of `/ads/breaks`.
class AdBreak {
  const AdBreak({required this.position, required this.type, required this.label, required this.source});
  final double position;
  final String type;
  final String? label;
  final String? source;

  factory AdBreak.fromJson(Json j) => AdBreak(
        position: asDouble(j['position']),
        type: asString(j['type']),
        label: asStringOrNull(j['label']),
        source: asStringOrNull(j['source']),
      );
}

/// `/ads/overlay-settings`.
class OverlaySettings {
  const OverlaySettings({
    required this.bannerEnabled,
    required this.bannerInitialDelay,
    required this.bannerDisplayDuration,
    required this.bannerRepeatInterval,
    required this.scrollerEnabled,
    required this.scrollerInitialDelay,
    required this.scrollerRepeatInterval,
  });

  final bool bannerEnabled;
  final int bannerInitialDelay;
  final int bannerDisplayDuration;
  final int bannerRepeatInterval;
  final bool scrollerEnabled;
  final int scrollerInitialDelay;
  final int scrollerRepeatInterval;

  static const defaults = OverlaySettings(
    bannerEnabled: true,
    bannerInitialDelay: 30,
    bannerDisplayDuration: 15,
    bannerRepeatInterval: 300,
    scrollerEnabled: true,
    scrollerInitialDelay: 15,
    scrollerRepeatInterval: 300,
  );

  factory OverlaySettings.fromJson(Json j) {
    final s = j.containsKey('settings') ? asJson(j['settings']) : j;
    return OverlaySettings(
      bannerEnabled: asBool(s['banner_enabled'], true),
      bannerInitialDelay: asInt(s['banner_initial_delay'], 30),
      bannerDisplayDuration: asInt(s['banner_display_duration'], 15),
      bannerRepeatInterval: asInt(s['banner_repeat_interval'], 300),
      scrollerEnabled: asBool(s['scroller_enabled'], true),
      scrollerInitialDelay: asInt(s['scroller_initial_delay'], 15),
      scrollerRepeatInterval: asInt(s['scroller_repeat_interval'], 300),
    );
  }
}
