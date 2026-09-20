import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/layout.dart';
import '../../../models/media_card.dart';
import '../../repositories.dart';
import '../../shell/ui/app_shell.dart';
import '../state/layout_providers.dart';
import 'section_widgets.dart';

/// Home tab: server-driven layout, or the built-in default home when the
/// backend has no published mobile layout (docs/API_GAPS.md #4).
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.watch(manifestPollerProvider);
    final config = ref.watch(appConfigProvider);
    final layout = ref.watch(homeLayoutProvider);

    Future<void> refresh() async {
      ref.invalidate(homeLayoutProvider);
      ref.invalidate(continueWatchingProvider);
      ref.invalidate(recommendationSetsProvider);
      await ref.read(homeLayoutProvider.future);
    }

    return Scaffold(
      appBar: TabAppBar(title: config.appName),
      body: AsyncView<AppLayout?>(
        value: layout,
        onRetry: refresh,
        builder: (data) => data == null
            ? DefaultHome(onRefresh: refresh)
            : LayoutView(layout: data, onRefresh: refresh),
      ),
    );
  }
}

/// Fallback home built from the plain content endpoints.
class DefaultHome extends ConsumerWidget {
  const DefaultHome({super.key, required this.onRefresh});
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final featured = ref.watch(_featuredProvider);
    final latestMovies = ref.watch(_latestMoviesProvider);
    final latestSeries = ref.watch(_latestSeriesProvider);
    final channels = ref.watch(channelsProvider);
    final cw = ref.watch(continueWatchingProvider);

    final allLoading = featured.isLoading && latestMovies.isLoading && latestSeries.isLoading;
    final allFailed = featured.hasError && latestMovies.hasError && latestSeries.hasError;
    if (allLoading) return const LoadingView();
    if (allFailed) {
      return ErrorView(
        error: featured.error!,
        onRetry: () {
          ref.invalidate(_featuredProvider);
          ref.invalidate(_latestMoviesProvider);
          ref.invalidate(_latestSeriesProvider);
          ref.invalidate(channelsProvider);
        },
      );
    }

    final hero = (featured.value ?? const []).take(6).map((m) => m.toCard()).toList();

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(_featuredProvider);
        ref.invalidate(_latestMoviesProvider);
        ref.invalidate(_latestSeriesProvider);
        ref.invalidate(channelsProvider);
        await onRefresh();
      },
      child: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          if (hero.isNotEmpty) _StaticHero(cards: hero),
          if ((cw.value ?? const []).isNotEmpty)
            ContentRail(title: 'Continue Watching', cards: cw.value!.map((i) => i.toCard()).toList(), style: 'backdrop'),
          if ((latestMovies.value ?? const []).isNotEmpty)
            ContentRail(title: 'Latest Movies', cards: latestMovies.value!.map((m) => m.toCard()).toList(), onSeeAll: () => context.go('/movies')),
          if ((latestSeries.value ?? const []).isNotEmpty)
            ContentRail(title: 'Latest TV Shows', cards: latestSeries.value!.map((s) => s.toCard()).toList(), onSeeAll: () => context.go('/series')),
          if ((channels.value ?? const []).isNotEmpty)
            ContentRail(title: 'Live TV', cards: channels.value!.take(20).map((c) => c.toCard()).toList(), style: 'backdrop', onSeeAll: () => context.go('/live')),
          if (hero.isEmpty && (latestMovies.value ?? const []).isEmpty && (latestSeries.value ?? const []).isEmpty && (channels.value ?? const []).isEmpty)
            const EmptyView(message: 'No content has been published yet.'),
        ],
      ),
    );
  }
}

final _featuredProvider = FutureProvider((ref) => ref.watch(contentRepositoryProvider).featuredMovies());
final _latestMoviesProvider = FutureProvider((ref) async => (await ref.watch(contentRepositoryProvider).movies(limit: 20)).items);
final _latestSeriesProvider = FutureProvider((ref) async => (await ref.watch(contentRepositoryProvider).seriesList(limit: 20)).items);

/// Builds a hero slideshow from ad-hoc cards by wrapping them as layout items.
class _StaticHero extends StatelessWidget {
  const _StaticHero({required this.cards});
  final List<MediaCard> cards;

  @override
  Widget build(BuildContext context) {
    final items = [
      for (final c in cards)
        LayoutItem(
          id: c.id,
          contentType: c.type,
          contentId: c.id,
          settings: const {},
          sortOrder: 0,
          content: {
            'id': c.id,
            'title': c.title,
            'poster_url': c.posterUrl,
            'backdrop_url': c.backdropUrl,
            'year': c.year,
            'vote_average': c.rating,
            'is_restricted': c.isRestricted,
            'is_adult': c.isAdult,
            'stream_url': c.streamUrl,
          },
        ),
    ];
    return HeroSlideshow(section: LayoutSection(id: 0, type: 'hero_slideshow', title: null, settings: const {'height': 'medium'}, sortOrder: 0, items: items));
  }
}
