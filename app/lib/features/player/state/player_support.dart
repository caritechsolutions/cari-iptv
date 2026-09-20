import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import '../../../core/providers.dart';
import '../../../models/ad.dart';
import '../../../models/content_extras.dart';
import '../../ads/data/ads_repository.dart';
import '../../library/data/user_content_repository.dart';
import '../../auth/state/auth_notifier.dart';
import '../../repositories.dart';
import '../ui/playback_request.dart';

/// Loads a WebVTT subtitle file (static, no auth) into a video_player caption file.
final subtitleFileProvider = FutureProvider.family<ClosedCaptionFile?, Subtitle>((ref, sub) async {
  final url = ref.watch(urlResolverProvider).resolve(sub.filePath);
  if (url == null) return null;
  try {
    final res = await Dio().get<String>(url, options: Options(responseType: ResponseType.plain, receiveTimeout: const Duration(seconds: 15)));
    final text = res.data ?? '';
    if (text.trim().isEmpty) return null;
    if (sub.format.toLowerCase() == 'srt' || !text.trimLeft().startsWith('WEBVTT')) return SubRipCaptionFile(text);
    return WebVTTCaptionFile(text);
  } catch (_) {
    return null;
  }
});

/// Ads to show around a playback: pre-roll list, mid-roll break positions and
/// overlay settings. Everything is best-effort and never blocks playback.
class PlaybackAds {
  const PlaybackAds({required this.preRoll, required this.breaks, required this.overlay, required this.context});
  final List<Ad> preRoll;
  final List<AdBreak> breaks;
  final OverlaySettings overlay;
  final AdContext context;

  static const none = PlaybackAds(preRoll: [], breaks: [], overlay: OverlaySettings.defaults, context: AdContext());
}

final playbackAdsProvider = FutureProvider.autoDispose.family<PlaybackAds, PlaybackRequest>((ref, req) async {
  final ads = ref.watch(adsRepositoryProvider);
  final user = ref.watch(currentUserProvider);
  final ent = ref.watch(entitlementsProvider).value;
  final packageId = ent?.packages.where((p) => p.isSubscribed).map((p) => p.id).firstOrNull;
  final ctx = AdContext(
    contentType: req.isLive ? 'live' : (req.contentType == 'episode' ? 'series' : 'vod'),
    contentId: req.isLive ? null : req.contentId,
    channelId: req.channelId,
    categoryId: req.categoryId,
    packageId: packageId,
    userId: user?.id,
  );
  final results = await Future.wait([
    ads.serve('pre_roll', ctx),
    req.isLive ? Future.value(const <AdBreak>[]) : ads.breaks(contentType: req.contentType == 'episode' ? 'episode' : 'movie', contentId: req.contentId, durationSeconds: req.durationHint ?? 0),
    ads.overlaySettings(),
  ]);
  final serve = results[0] as AdServeResult;
  return PlaybackAds(
    preRoll: serve.ads.where((a) => a.hasPlayableVideo).toList(),
    breaks: (results[1] as List<AdBreak>).where((b) => b.type == 'mid_roll' && b.position > 0).toList(),
    overlay: results[2] as OverlaySettings,
    context: ctx,
  );
});

/// Posts watch progress every 10 s while playing (web player cadence) and on
/// pause/stop. Live channels are not tracked (no meaningful duration).
class ProgressReporter {
  ProgressReporter(this.repo, this.request);
  final UserContentRepository repo;
  final PlaybackRequest request;
  Timer? _timer;
  int _lastSent = -1;

  void start(Duration Function() position, Duration Function() duration) {
    if (request.isLive) return;
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 10), (_) => report(position(), duration()));
  }

  Future<void> report(Duration position, Duration duration) async {
    if (request.isLive) return;
    final p = position.inSeconds;
    final d = duration.inSeconds;
    if (d <= 0 || p <= 0 || p == _lastSent) return;
    _lastSent = p;
    try {
      await repo.saveProgress(contentType: request.contentType, contentId: request.contentId, progress: p, duration: d);
    } catch (_) {}
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
  }
}
