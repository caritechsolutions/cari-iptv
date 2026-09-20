import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../models/movie.dart';
import '../../../models/series.dart';
import '../../../models/watch.dart';
import '../../auth/state/auth_notifier.dart';
import '../../repositories.dart';
import '../data/content_repository.dart';

/// Query for the movie / series list pages.
class ListQuery {
  const ListQuery({this.kind = 'movie', this.categoryId, this.sort = 'latest', this.genre, this.featured});
  final String kind; // movie | series
  final int? categoryId;
  final String sort;
  final String? genre;
  final bool? featured;

  ListQuery copyWith({int? categoryId, String? sort, String? genre, bool clearCategory = false}) => ListQuery(
        kind: kind,
        categoryId: clearCategory ? null : (categoryId ?? this.categoryId),
        sort: sort ?? this.sort,
        genre: genre ?? this.genre,
        featured: featured,
      );

  @override
  bool operator ==(Object other) =>
      other is ListQuery && other.kind == kind && other.categoryId == categoryId && other.sort == sort && other.genre == genre && other.featured == featured;

  @override
  int get hashCode => Object.hash(kind, categoryId, sort, genre, featured);
}

/// Paged list state with "load more".
class PagedList<T> {
  const PagedList({required this.items, required this.total, this.loadingMore = false, this.fromCache = false});
  final List<T> items;
  final int total;
  final bool loadingMore;
  final bool fromCache;
  bool get hasMore => items.length < total;
}

class MovieListNotifier extends AsyncNotifier<PagedList<Movie>> {
  MovieListNotifier(this.arg);
  final ListQuery arg;
  static const pageSize = 30;

  @override
  Future<PagedList<Movie>> build() async {
    final page = await ref.watch(contentRepositoryProvider).movies(
          categoryId: arg.categoryId,
          sort: arg.sort,
          genre: arg.genre,
          featured: arg.featured,
          limit: pageSize,
        );
    return PagedList(items: page.items, total: page.total, fromCache: page.fromCache);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || current.loadingMore || !current.hasMore) return;
    state = AsyncData(PagedList(items: current.items, total: current.total, loadingMore: true));
    try {
      final page = await ref.read(contentRepositoryProvider).movies(
            categoryId: arg.categoryId,
            sort: arg.sort,
            genre: arg.genre,
            featured: arg.featured,
            limit: pageSize,
            offset: current.items.length,
          );
      state = AsyncData(PagedList(items: [...current.items, ...page.items], total: page.total));
    } catch (_) {
      state = AsyncData(PagedList(items: current.items, total: current.total));
    }
  }
}

class SeriesListNotifier extends AsyncNotifier<PagedList<Series>> {
  SeriesListNotifier(this.arg);
  final ListQuery arg;
  static const pageSize = 30;

  @override
  Future<PagedList<Series>> build() async {
    final page = await ref.watch(contentRepositoryProvider).seriesList(
          categoryId: arg.categoryId,
          sort: arg.sort,
          genre: arg.genre,
          featured: arg.featured,
          limit: pageSize,
        );
    return PagedList(items: page.items, total: page.total, fromCache: page.fromCache);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || current.loadingMore || !current.hasMore) return;
    state = AsyncData(PagedList(items: current.items, total: current.total, loadingMore: true));
    try {
      final page = await ref.read(contentRepositoryProvider).seriesList(
            categoryId: arg.categoryId,
            sort: arg.sort,
            genre: arg.genre,
            featured: arg.featured,
            limit: pageSize,
            offset: current.items.length,
          );
      state = AsyncData(PagedList(items: [...current.items, ...page.items], total: page.total));
    } catch (_) {
      state = AsyncData(PagedList(items: current.items, total: current.total));
    }
  }
}

final movieListProvider = AsyncNotifierProvider.family<MovieListNotifier, PagedList<Movie>, ListQuery>(MovieListNotifier.new);
final seriesListProvider = AsyncNotifierProvider.family<SeriesListNotifier, PagedList<Series>, ListQuery>(SeriesListNotifier.new);

final movieDetailProvider = FutureProvider.family<Movie, int>((ref, id) => ref.watch(contentRepositoryProvider).movie(id));
final seriesDetailProvider = FutureProvider.family<Series, int>((ref, id) => ref.watch(contentRepositoryProvider).series(id));
final episodeDetailProvider = FutureProvider.family<Episode, int>((ref, id) => ref.watch(contentRepositoryProvider).episode(id));
final personProvider = FutureProvider.family<Person, int>((ref, id) => ref.watch(contentRepositoryProvider).person(id));

/// Saved position for a movie/episode (null when never watched or signed out).
final watchProgressProvider = FutureProvider.family<WatchProgress?, ({String type, int id})>((ref, key) async {
  if (ref.watch(currentUserProvider) == null) return null;
  try {
    return await ref.watch(userContentRepositoryProvider).progress(contentType: key.type, contentId: key.id);
  } catch (_) {
    return null;
  }
});

/// Progress for all episodes of a series, keyed by episode id.
final seriesProgressProvider = FutureProvider.family<Map<int, WatchProgress>, int>((ref, seriesId) async {
  final series = await ref.watch(seriesDetailProvider(seriesId).future);
  final ids = [for (final s in series.seasons) for (final e in s.episodes) e.id];
  if (ids.isEmpty || ref.watch(currentUserProvider) == null) return const {};
  try {
    return await ref.watch(userContentRepositoryProvider).batchProgress(contentType: 'episode', ids: ids);
  } catch (_) {
    return const {};
  }
});

final ratingProvider = FutureProvider.family<RatingStats?, ({String type, int id})>((ref, key) async {
  if (ref.watch(currentUserProvider) == null) return null;
  try {
    return await ref.watch(userContentRepositoryProvider).rating(contentType: key.type, contentId: key.id);
  } catch (_) {
    return null;
  }
});

/// Watchlist membership as a set of "type:id" keys, kept in sync after toggles.
class WatchlistNotifier extends AsyncNotifier<Set<String>> {
  @override
  Future<Set<String>> build() async {
    if (ref.watch(currentUserProvider) == null) return {};
    try {
      final entries = await ref.watch(userContentRepositoryProvider).watchlist();
      return {for (final e in entries) '${e.contentType}:${e.contentId}'};
    } catch (_) {
      return {};
    }
  }

  bool contains(String type, int id) => state.value?.contains('$type:$id') ?? false;

  Future<bool> toggle(String type, int id) async {
    final now = await ref.read(userContentRepositoryProvider).toggleWatchlist(contentType: type, contentId: id);
    final set = {...?state.value};
    if (now) {
      set.add('$type:$id');
    } else {
      set.remove('$type:$id');
    }
    state = AsyncData(set);
    ref.invalidate(watchlistEntriesProvider);
    return now;
  }
}

final watchlistProvider = AsyncNotifierProvider<WatchlistNotifier, Set<String>>(WatchlistNotifier.new);

final watchlistEntriesProvider = FutureProvider<List<WatchlistEntry>>((ref) async {
  if (ref.watch(currentUserProvider) == null) return const [];
  return ref.watch(userContentRepositoryProvider).watchlist();
});
