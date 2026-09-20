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

/// Applies entitlement/adult gating, offers "Resume or start over", then opens the player.
Future<void> playWithGate(BuildContext context, WidgetRef ref, {required MediaCard card, required PlaybackRequest request, WatchProgress? progress}) async {
  final user = ref.read(currentUserProvider);
  if (card.isAdult && !(user?.adultEnabled ?? false)) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enable adult content in Profile to watch this title.')));
    return;
  }
  if (card.isAdult && (user?.parentalPin?.isNotEmpty ?? false)) {
    if (!await askParentalPin(context, user!.parentalPin!)) return;
  }
  if (card.isRestricted) {
    final ent = await ref.read(entitlementsProvider.future);
    if (!ent.allows(card.type, card.id, categoryId: card.categoryId)) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('This title is not included in your current package.')));
      return;
    }
  }
  if (!context.mounted) return;

  // Web links (e.g. a YouTube URL stored as stream_url) open in the browser.
  if (isExternalWatchUrl(request.streamUrl)) {
    await openExternal(context, request.streamUrl);
    return;
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
    if (resume == null) return;
    if (resume) req = req.copyWith(resumeFromSeconds: progress.progressSeconds);
  }
  if (!context.mounted) return;
  context.push('/player', extra: req);
}

/// Opens the player for an episode. Fetches `/episodes/{id}` first so that
/// markers, subtitles and `next_episode` are available (the series payload
/// omits them — docs/API_GAPS.md #15).
Future<void> playEpisode(BuildContext context, WidgetRef ref, {required Series series, required Episode episode, WatchProgress? progress}) async {
  Episode full = episode;
  try {
    full = await ref.read(contentRepositoryProvider).episode(episode.id);
  } catch (e) {
    if (!context.mounted) return;
    if (!episode.isPlayable) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(describeError(e))));
      return;
    }
  }
  if (!context.mounted) return;
  final stream = full.streamUrl ?? episode.streamUrl;
  if (stream == null || stream.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('This episode is not available yet.')));
    return;
  }
  final code = full.seasonNumber != null ? 'S${full.seasonNumber}E${full.episodeNumber}' : 'E${full.episodeNumber}';
  await playWithGate(
    context,
    ref,
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
