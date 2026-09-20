import '../../../models/content_extras.dart';
import '../../../models/series.dart';

/// Everything the player screen needs, built by the detail pages or channel lists.
class PlaybackRequest {
  const PlaybackRequest({
    required this.contentType,
    required this.contentId,
    required this.title,
    required this.streamUrl,
    this.subtitle,
    this.posterUrl,
    this.logoUrl,
    this.isLive = false,
    this.resumeFromSeconds,
    this.durationHint,
    this.markers = const [],
    this.subtitles = const [],
    this.nextEpisode,
    this.seriesId,
    this.channelId,
    this.categoryId,
  });

  /// movie | episode | channel (matches the watch-progress content_type)
  final String contentType;
  final int contentId;
  final String title;
  final String? subtitle;
  final String streamUrl;
  final String? posterUrl;
  final String? logoUrl;
  final bool isLive;
  final int? resumeFromSeconds;
  final int? durationHint;
  final List<ContentMarker> markers;
  final List<Subtitle> subtitles;
  final EpisodeRef? nextEpisode;
  final int? seriesId;
  final int? channelId;
  final int? categoryId;

  factory PlaybackRequest.channel({required int id, required String title, required String streamUrl, String? logoUrl, int? categoryId}) =>
      PlaybackRequest(contentType: 'channel', contentId: id, title: title, streamUrl: streamUrl, logoUrl: logoUrl, isLive: true, channelId: id, categoryId: categoryId);

  PlaybackRequest copyWith({int? resumeFromSeconds, String? streamUrl}) => PlaybackRequest(
        contentType: contentType,
        contentId: contentId,
        title: title,
        subtitle: subtitle,
        streamUrl: streamUrl ?? this.streamUrl,
        posterUrl: posterUrl,
        logoUrl: logoUrl,
        isLive: isLive,
        resumeFromSeconds: resumeFromSeconds ?? this.resumeFromSeconds,
        durationHint: durationHint,
        markers: markers,
        subtitles: subtitles,
        nextEpisode: nextEpisode,
        seriesId: seriesId,
        channelId: channelId,
        categoryId: categoryId,
      );
}
