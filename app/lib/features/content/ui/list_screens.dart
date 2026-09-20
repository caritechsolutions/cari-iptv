import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/media_card.dart';
import '../../layout/state/layout_providers.dart';
import '../../shell/ui/app_shell.dart';
import '../state/content_providers.dart';

const _sorts = {'latest': 'Latest', 'popular': 'Popular', 'rating': 'Top rated', 'title': 'A–Z', 'year': 'Year'};

/// Movies / TV Shows tab with sort and category filters and infinite scroll.
class MediaListScreen extends ConsumerStatefulWidget {
  const MediaListScreen({super.key, required this.kind, this.categoryId, this.title, this.embedded = false});

  /// movie | series
  final String kind;
  final int? categoryId;
  final String? title;

  /// True when pushed (category page) rather than shown as a tab.
  final bool embedded;

  @override
  ConsumerState<MediaListScreen> createState() => _MediaListScreenState();
}

class _MediaListScreenState extends ConsumerState<MediaListScreen> {
  final _scroll = ScrollController();
  late ListQuery _query = ListQuery(kind: widget.kind, categoryId: widget.categoryId);

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) _loadMore();
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _loadMore() {
    if (widget.kind == 'movie') {
      ref.read(movieListProvider(_query).notifier).loadMore();
    } else {
      ref.read(seriesListProvider(_query).notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isMovie = widget.kind == 'movie';
    final categories = ref.watch(categoriesProvider(isMovie ? 'vod' : 'series')).value ?? const [];

    final AsyncValue<PagedList<dynamic>> list = isMovie ? ref.watch(movieListProvider(_query)) : ref.watch(seriesListProvider(_query));

    final title = widget.title ?? (isMovie ? 'Movies' : 'TV Shows');
    final filters = Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
      child: Row(
        children: [
          Expanded(
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  if (widget.categoryId == null) ...[
                    ChoiceChip(label: const Text('All'), selected: _query.categoryId == null, onSelected: (_) => setState(() => _query = _query.copyWith(clearCategory: true))),
                    for (final c in categories) ...[
                      const SizedBox(width: 6),
                      ChoiceChip(label: Text(c.name), selected: _query.categoryId == c.id, onSelected: (_) => setState(() => _query = _query.copyWith(categoryId: c.id))),
                    ],
                  ],
                ],
              ),
            ),
          ),
          PopupMenuButton<String>(
            tooltip: 'Sort',
            icon: const Icon(Icons.sort_rounded),
            initialValue: _query.sort,
            onSelected: (s) => setState(() => _query = _query.copyWith(sort: s)),
            itemBuilder: (_) => [for (final e in _sorts.entries) PopupMenuItem(value: e.key, child: Text(e.value))],
          ),
        ],
      ),
    );

    return Scaffold(
      appBar: widget.embedded ? AppBar(title: Text(title)) : TabAppBar(title: title),
      body: Column(
        children: [
          filters,
          Expanded(
            child: AsyncView<PagedList<dynamic>>(
              value: list,
              onRetry: () => isMovie ? ref.invalidate(movieListProvider(_query)) : ref.invalidate(seriesListProvider(_query)),
              isEmpty: (p) => p.items.isEmpty,
              emptyMessage: 'No ${isMovie ? 'movies' : 'shows'} found.',
              emptyIcon: isMovie ? Icons.movie_outlined : Icons.tv_outlined,
              builder: (p) {
                final cards = p.items.map<MediaCard>((e) => e.toCard() as MediaCard).toList();
                return RefreshIndicator(
                  onRefresh: () async => isMovie ? ref.invalidate(movieListProvider(_query)) : ref.invalidate(seriesListProvider(_query)),
                  child: PosterGrid(
                    cards: cards,
                    controller: _scroll,
                    footer: p.loadingMore
                        ? const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()))
                        : (p.fromCache ? const Padding(padding: EdgeInsets.all(12), child: Center(child: Text('Showing saved results (offline)', style: TextStyle(color: Colors.white54, fontSize: 12)))) : const SizedBox(height: 16)),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
