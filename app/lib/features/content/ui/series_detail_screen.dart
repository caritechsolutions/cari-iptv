import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/time.dart';
import '../../../core/widgets/app_image.dart';
import '../../../core/widgets/async_view.dart';
import '../../../models/series.dart';
import '../../../models/watch.dart';
import '../../player/ui/play_helpers.dart';
import '../state/content_providers.dart';
import 'detail_widgets.dart';

class SeriesDetailScreen extends ConsumerStatefulWidget {
  const SeriesDetailScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<SeriesDetailScreen> createState() => _SeriesDetailScreenState();
}

class _SeriesDetailScreenState extends ConsumerState<SeriesDetailScreen> {
  int? _seasonId;

  @override
  Widget build(BuildContext context) {
    final series = ref.watch(seriesDetailProvider(widget.id));
    return Scaffold(
      body: AsyncView<Series>(
        value: series,
        onRetry: () => ref.invalidate(seriesDetailProvider(widget.id)),
        builder: (s) {
          final seasons = s.seasons;
          final season = seasons.isEmpty ? null : seasons.firstWhere((x) => x.id == _seasonId, orElse: () => seasons.first);
          final progress = ref.watch(seriesProgressProvider(s.id)).value ?? const <int, WatchProgress>{};
          final meta = [
            if (s.year != null) '${s.year}',
            if (s.seasonCount != null) '${s.seasonCount} season${s.seasonCount == 1 ? '' : 's'}',
            if (s.categoryName != null) s.categoryName!,
          ].join(' · ');

          // Next episode to play: most recently watched unfinished, else first.
          Episode? nextUp;
          for (final se in seasons) {
            for (final e in se.episodes) {
              final p = progress[e.id];
              if (p != null && !p.completed && p.progressSeconds > 0) {
                nextUp = e;
              }
            }
          }
          nextUp ??= seasons.isNotEmpty && seasons.first.episodes.isNotEmpty ? seasons.first.episodes.first : null;

          return CustomScrollView(
            slivers: [
              SliverAppBar(
                pinned: true,
                backgroundColor: Colors.transparent,
                leading: BackButton(onPressed: () => context.canPop() ? context.pop() : context.go('/home')),
                actions: [WatchlistButton(type: 'series', id: s.id, compact: true)],
              ),
              SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    DetailHeader(title: s.title, backdropUrl: s.backdropUrl, posterUrl: s.posterUrl, metaLine: meta, genres: s.genres),
                    AccessNotice(card: s.toCard()),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                      child: Row(
                        children: [
                          Expanded(
                            child: FilledButton.icon(
                              onPressed: nextUp == null || !nextUp.isPlayable ? null : () => playEpisode(context, ref, series: s, episode: nextUp!, progress: progress[nextUp.id]),
                              icon: const Icon(Icons.play_arrow_rounded),
                              label: Text(nextUp == null ? 'No episodes' : (progress[nextUp.id]?.isResumable ?? false) ? 'Resume ${nextUp.code}' : 'Play ${nextUp.code}'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          WatchlistButton(type: 'series', id: s.id),
                        ],
                      ),
                    ),
                    RatingRow(type: 'series', id: s.id, voteAverage: s.voteAverage),
                    if (s.synopsis != null && s.synopsis!.isNotEmpty)
                      Padding(padding: const EdgeInsets.symmetric(horizontal: 16), child: Text(s.synopsis!, style: const TextStyle(color: Colors.white70, height: 1.4))),
                    const SizedBox(height: 8),
                    TrailerButtons(trailers: s.trailers),
                    if (seasons.length > 1)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                        child: SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              for (final se in seasons) ...[
                                ChoiceChip(label: Text(se.name), selected: se.id == season?.id, onSelected: (_) => setState(() => _seasonId = se.id)),
                                const SizedBox(width: 6),
                              ],
                            ],
                          ),
                        ),
                      ),
                    if (season != null && season.episodes.isEmpty) const Padding(padding: EdgeInsets.all(16), child: Text('No episodes in this season yet.', style: TextStyle(color: Colors.white54))),
                  ],
                ),
              ),
              if (season != null)
                SliverList.builder(
                  itemCount: season.episodes.length,
                  itemBuilder: (context, i) {
                    final e = season.episodes[i];
                    final p = progress[e.id];
                    return _EpisodeTile(
                      episode: e,
                      progress: p,
                      onTap: e.isPlayable ? () => playEpisode(context, ref, series: s, episode: e, progress: p) : null,
                    );
                  },
                ),
              const SliverToBoxAdapter(child: SizedBox(height: 32)),
              SliverToBoxAdapter(child: CastRail(cast: s.cast)),
              const SliverToBoxAdapter(child: SizedBox(height: 32)),
            ],
          );
        },
      ),
    );
  }
}

class _EpisodeTile extends StatelessWidget {
  const _EpisodeTile({required this.episode, this.progress, this.onTap});
  final Episode episode;
  final WatchProgress? progress;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final playable = onTap != null;
    return ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      leading: SizedBox(
        width: 110,
        child: Stack(
          children: [
            AspectRatio(aspectRatio: 16 / 9, child: AppImage(episode.stillUrl, borderRadius: BorderRadius.circular(6))),
            if (!playable)
              const Positioned.fill(child: Center(child: Icon(Icons.hourglass_empty_rounded, color: Colors.white70))),
            if (progress != null && progress!.fraction > 0)
              Positioned(left: 0, right: 0, bottom: 0, child: LinearProgressIndicator(value: progress!.fraction, minHeight: 3, backgroundColor: Colors.black45)),
          ],
        ),
      ),
      title: Text('${episode.episodeNumber}. ${episode.title}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w600, color: playable ? null : Colors.white54)),
      subtitle: Text(
        [if (episode.runtime != null) formatRuntime(episode.runtime), if (!playable) 'Not available yet', if (episode.synopsis != null) episode.synopsis!].join(' · '),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontSize: 12),
      ),
      trailing: playable ? Icon(progress?.completed ?? false ? Icons.check_circle_outline : Icons.play_circle_outline) : null,
    );
  }
}
