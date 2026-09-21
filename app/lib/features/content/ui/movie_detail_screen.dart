import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/util/time.dart';
import '../../../core/util/url_resolver.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/async_view.dart';
import '../../../models/movie.dart';
import '../../player/ui/playback_request.dart';
import '../../player/ui/play_helpers.dart';
import '../state/content_providers.dart';
import 'detail_widgets.dart';

class MovieDetailScreen extends ConsumerWidget {
  const MovieDetailScreen({super.key, required this.id});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final movie = ref.watch(movieDetailProvider(id));
    return Scaffold(
      // Loading and error states need the back arrow too.
      appBar: movie.hasValue ? null : AppBar(backgroundColor: Colors.transparent, leading: const AppBackButton()),
      body: AsyncView<Movie>(
        value: movie,
        onRetry: () => ref.invalidate(movieDetailProvider(id)),
        builder: (m) => _MovieBody(movie: m),
      ),
    );
  }
}

class _MovieBody extends ConsumerWidget {
  const _MovieBody({required this.movie});
  final Movie movie;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final progress = ref.watch(watchProgressProvider((type: 'movie', id: movie.id))).value;
    final meta = [
      if (movie.year != null) '${movie.year}',
      if (movie.runtime != null) formatRuntime(movie.runtime),
      if (movie.categoryName != null) movie.categoryName!,
    ].join(' · ');

    return CustomScrollView(
      slivers: [
        SliverAppBar(
          pinned: true,
          backgroundColor: Colors.transparent,
          leading: const AppBackButton(),
          actions: [WatchlistButton(type: 'movie', id: movie.id, compact: true)],
        ),
        SliverToBoxAdapter(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              DetailHeader(title: movie.title, backdropUrl: movie.backdropUrl, posterUrl: movie.posterUrl, metaLine: meta, genres: movie.genres),
              AccessNotice(card: movie.toCard()),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: movie.isPlayable
                            ? () => playWithGate(
                                  context,
                                  ref,
                                  card: movie.toCard(),
                                  request: PlaybackRequest(
                                    contentType: 'movie',
                                    contentId: movie.id,
                                    title: movie.title,
                                    subtitle: meta,
                                    streamUrl: movie.streamUrl!,
                                    posterUrl: movie.posterUrl ?? movie.backdropUrl,
                                    durationHint: movie.runtime != null ? movie.runtime! * 60 : null,
                                    markers: movie.markers,
                                    subtitles: movie.subtitles,
                                    categoryId: movie.categoryId,
                                  ),
                                  progress: progress,
                                )
                            : null,
                        icon: Icon(isExternalWatchUrl(movie.streamUrl) ? Icons.open_in_new_rounded : Icons.play_arrow_rounded),
                        label: Text(isExternalWatchUrl(movie.streamUrl)
                            ? 'Watch on ${Uri.tryParse(movie.streamUrl!)?.host.contains('youtu') ?? false ? 'YouTube' : 'the web'}'
                            : progress != null && progress.isResumable ? 'Resume' : (movie.isPlayable ? 'Play' : 'Not available')),
                      ),
                    ),
                    const SizedBox(width: 10),
                    WatchlistButton(type: 'movie', id: movie.id),
                  ],
                ),
              ),
              if (progress != null && progress.isResumable)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: LinearProgressIndicator(value: progress.fraction, minHeight: 3, borderRadius: BorderRadius.circular(2)),
                ),
              RatingRow(type: 'movie', id: movie.id, voteAverage: movie.voteAverage),
              if (movie.synopsis != null && movie.synopsis!.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Text(movie.synopsis!, style: const TextStyle(color: Colors.white70, height: 1.4)),
                ),
              const SizedBox(height: 12),
              TrailerButtons(trailers: movie.trailers),
              CastRail(cast: movie.cast),
              if (movie.subtitles.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                  child: Text('Subtitles: ${movie.subtitles.map((s) => s.languageName).join(', ')}', style: const TextStyle(color: Colors.white54, fontSize: 12)),
                ),
              const SizedBox(height: 32),
            ],
          ),
        ),
      ],
    );
  }
}
