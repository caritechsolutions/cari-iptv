import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/category.dart';
import '../../../models/media_card.dart';
import '../../../models/watch.dart';
import '../../content/state/content_providers.dart';
import '../../layout/state/layout_providers.dart';
import '../../repositories.dart';
import '../../shell/ui/app_shell.dart';

/// Categories tab grouped by type.
class CategoriesScreen extends ConsumerWidget {
  const CategoriesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cats = ref.watch(categoriesProvider('all'));
    return Scaffold(
      appBar: const TabAppBar(title: 'Categories'),
      body: AsyncView<List<Category>>(
        value: cats,
        onRetry: () => ref.invalidate(categoriesProvider('all')),
        isEmpty: (l) => l.isEmpty,
        emptyMessage: 'No categories yet.',
        emptyIcon: Icons.grid_view_rounded,
        builder: (list) {
          final groups = {
            'Live TV': list.where((c) => c.type == 'live').toList(),
            'Movies': list.where((c) => c.type == 'vod').toList(),
            'TV Shows': list.where((c) => c.type == 'series').toList(),
          };
          return ListView(
            padding: const EdgeInsets.only(bottom: 24),
            children: [
              for (final g in groups.entries)
                if (g.value.isNotEmpty) ...[
                  SectionHeader(title: g.key),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final c in g.value)
                          ActionChip(
                            avatar: Icon(switch (c.type) { 'live' => Icons.live_tv_outlined, 'series' => Icons.tv_outlined, _ => Icons.movie_outlined }, size: 16),
                            label: Text(c.name),
                            onPressed: () => context.push('/category/${c.id}', extra: c.name),
                          ),
                      ],
                    ),
                  ),
                ],
            ],
          );
        },
      ),
    );
  }
}

/// My List: hydrates the bare watchlist rows with details (docs/API_GAPS.md #9),
/// plus a Continue Watching section.
class MyListScreen extends ConsumerWidget {
  const MyListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final entries = ref.watch(watchlistEntriesProvider);
    final cw = ref.watch(continueWatchingProvider);
    return Scaffold(
      appBar: const TabAppBar(title: 'My List'),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(watchlistEntriesProvider);
          ref.invalidate(watchlistProvider);
          ref.invalidate(continueWatchingProvider);
        },
        child: AsyncView<List<WatchlistEntry>>(
          value: entries,
          onRetry: () => ref.invalidate(watchlistEntriesProvider),
          builder: (list) {
            final continueCards = (cw.value ?? const <ContinueWatchingItem>[]).map((i) => i.toCard()).toList();
            if (list.isEmpty && continueCards.isEmpty) {
              return const EmptyView(message: 'Titles you add to My List and shows you are watching appear here.', icon: Icons.bookmark_border_rounded);
            }
            return ListView(
              padding: const EdgeInsets.only(bottom: 24),
              children: [
                if (continueCards.isNotEmpty) ContentRail(title: 'Continue Watching', cards: continueCards, style: 'backdrop'),
                if (list.isNotEmpty) const SectionHeader(title: 'Saved'),
                if (list.isNotEmpty) _HydratedGrid(entries: list),
              ],
            );
          },
        ),
      ),
    );
  }
}

final _hydratedProvider = FutureProvider.family<MediaCard?, WatchlistEntry>((ref, e) async {
  final repo = ref.watch(contentRepositoryProvider);
  try {
    switch (e.contentType) {
      case 'movie':
        return (await repo.movie(e.contentId)).toCard();
      case 'series':
        return (await repo.series(e.contentId)).toCard();
      case 'channel':
        return (await repo.channel(e.contentId)).toCard();
    }
  } catch (_) {}
  return null;
});

class _HydratedGrid extends ConsumerWidget {
  const _HydratedGrid({required this.entries});
  final List<WatchlistEntry> entries;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final width = MediaQuery.sizeOf(context).width;
    final columns = (width / 130).floor().clamp(2, 6);
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(12),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: columns, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 0.56),
      itemCount: entries.length,
      itemBuilder: (context, i) {
        final card = ref.watch(_hydratedProvider(entries[i]));
        return card.when(
          loading: () => Container(decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(10))),
          error: (_, _) => const SizedBox.shrink(),
          data: (c) => c == null
              ? Container(
                  decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(10)),
                  alignment: Alignment.center,
                  child: const Text('Unavailable', style: TextStyle(color: Colors.white38, fontSize: 12)),
                )
              : PosterCard(card: c, width: double.infinity),
        );
      },
    );
  }
}
