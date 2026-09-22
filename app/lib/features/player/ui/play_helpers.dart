import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/time.dart';
import '../../../core/util/url_resolver.dart';
import '../../../core/widgets/legal_links.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/media_card.dart';
import '../../../models/series.dart';
import '../../../models/watch.dart';
import '../../auth/state/auth_notifier.dart';
import '../../repositories.dart';
import 'playback_request.dart';

/// Applies entitlement/adult gating, offers "Resume or start over", then opens
/// the player. Returns true when the player screen was opened. With [replace]
/// the current screen is replaced by the player (used by launcher screens so
/// back from the player never lands on them).
Future<bool> playWithGate(BuildContext context, WidgetRef ref, {required MediaCard card, required PlaybackRequest request, WatchProgress? progress, bool replace = false}) async {
  final user = ref.read(currentUserProvider);
  if (card.isAdult && !(user?.adultEnabled ?? false)) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enable adult content in Profile to watch this title.')));
    return false;
  }
  if (card.isAdult && (user?.parentalPin?.isNotEmpty ?? false)) {
    if (!await askParentalPin(context, user!.parentalPin!)) return false;
  }
  final ent = await ref.read(entitlementsProvider.future);
  if (ent.locks(type: card.type, id: card.id, categoryId: card.categoryId, isRestricted: card.isRestricted)) {
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(ent.hasSubscription ? 'This title is not included in your current package.' : 'An active subscription is needed to watch this.')));
    }
    return false;
  }
  if (!context.mounted) return false;

  // Web links (e.g. a YouTube URL stored as stream_url) open in the browser.
  if (isExternalWatchUrl(request.streamUrl)) {
    await openExternal(context, request.streamUrl);
    return false;
  }

  var req = request;
  if (progress != null && progress.isResumable) {
    final resume = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Resume watching?'),
        content: Text('Continue from ${formatDuration(Duration(seconds: progress.progressSeconds))}?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Start over')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Resume')),
        ],
      ),
    );
    if (resume == null) return false;
    if (resume) req = req.copyWith(resumeFromSeconds: progress.progressSeconds);
  }
  if (!context.mounted) return false;
  if (replace) {
    context.pushReplacement('/player', extra: req);
  } else {
    context.push('/player', extra: req);
  }
  return true;
}

/// Opens the player for an episode. Fetches `/episodes/{id}` first so that
/// markers, subtitles and `next_episode` are available (the series payload
/// omits them — docs/API_GAPS.md #15).
Future<bool> playEpisode(BuildContext context, WidgetRef ref, {required Series series, required Episode episode, WatchProgress? progress, bool replace = false}) async {
  Episode full = episode;
  try {
    full = await ref.read(contentRepositoryProvider).episode(episode.id);
  } catch (e) {
    if (!context.mounted) return false;
    if (!episode.isPlayable) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(describeError(e))));
      return false;
    }
  }
  if (!context.mounted) return false;
  final stream = full.streamUrl ?? episode.streamUrl;
  if (stream == null || stream.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('This episode is not available yet.')));
    return false;
  }
  final code = full.seasonNumber != null ? 'S${full.seasonNumber}E${full.episodeNumber}' : 'E${full.episodeNumber}';
  return playWithGate(
    context,
    ref,
    replace: replace,
    card: series.toCard(),
    request: PlaybackRequest(
      contentType: 'episode',
      contentId: full.id,
      title: series.title,
      subtitle: '$code · ${full.title}',
      streamUrl: stream,
      posterUrl: full.stillUrl ?? series.backdropUrl ?? series.posterUrl,
      durationHint: full.runtime != null ? full.runtime! * 60 : null,
      markers: full.markers,
      subtitles: full.subtitles,
      nextEpisode: full.nextEpisode,
      seriesId: series.id,
      categoryId: series.categoryId,
    ),
    progress: progress,
  );
}
