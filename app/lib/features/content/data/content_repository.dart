import '../../../core/network/api_client.dart';
import '../../../core/util/json.dart';
import '../../../models/category.dart';
import '../../../models/channel.dart';
import '../../../models/movie.dart';
import '../../../models/search_result.dart';
import '../../../models/series.dart';

/// A page of list results with the backend's `meta.total`.
class Page<T> {
  const Page({required this.items, required this.total, required this.offset, this.fromCache = false});
  final List<T> items;
  final int total;
  final int offset;
  final bool fromCache;
  bool get hasMore => offset + items.length < total;
}

/// Person (cast member) filmography from `/person/{tmdbPersonId}`.
class Person {
  const Person({required this.name, required this.profileImage, required this.movies, required this.series});
  final String name;
  final String? profileImage;
  final List<Movie> movies;
  final List<Series> series;
}

/// Content endpoints. See docs/API_DISCOVERY.md §3.
class ContentRepository {
  ContentRepository(this._api);
  final ApiClient _api;

  Future<Page<Channel>> channels({int? categoryId, int limit = 500, int offset = 0, bool preferCache = false}) async {
    final res = await _api.get('/channels', cacheScope: 'channels', preferCache: preferCache, query: {
      'category_id': ?categoryId,
      'limit': limit,
      'offset': offset,
    });
    final items = res.envelope.dataAsList.map(Channel.fromJson).toList(growable: false);
    return Page(items: items, total: res.envelope.total ?? items.length, offset: offset, fromCache: res.fromCache);
  }

  Future<Channel> channel(int id) async {
    final res = await _api.get('/channels/$id', cacheScope: 'channels');
    return Channel.fromJson(res.envelope.dataAsJson);
  }

  Future<Page<Movie>> movies({
    int? categoryId,
    bool? featured,
    String? genre,
    int? year,
    String sort = 'latest',
    int limit = 30,
    int offset = 0,
    bool preferCache = false,
  }) async {
    final res = await _api.get('/movies', cacheScope: 'movies', preferCache: preferCache, query: {
      'category_id': ?categoryId,
      if (featured == true) 'featured': 1,
      'genre': ?genre,
      'year': ?year,
      'sort': sort,
      'limit': limit,
      'offset': offset,
    });
    final items = res.envelope.dataAsList.map(Movie.fromJson).toList(growable: false);
    return Page(items: items, total: res.envelope.total ?? items.length, offset: offset, fromCache: res.fromCache);
  }

  Future<List<Movie>> featuredMovies() async {
    final res = await _api.get('/movies/featured', cacheScope: 'movies');
    return res.envelope.dataAsList.map(Movie.fromJson).toList(growable: false);
  }

  Future<Movie> movie(int id) async {
    final res = await _api.get('/movies/$id', cacheScope: 'movies');
    return Movie.fromJson(res.envelope.dataAsJson);
  }

  Future<Page<Series>> seriesList({
    int? categoryId,
    bool? featured,
    String? genre,
    String sort = 'latest',
    int limit = 30,
    int offset = 0,
    bool preferCache = false,
  }) async {
    final res = await _api.get('/series', cacheScope: 'series', preferCache: preferCache, query: {
      'category_id': ?categoryId,
      if (featured == true) 'featured': 1,
      'genre': ?genre,
      'sort': sort,
      'limit': limit,
      'offset': offset,
    });
    final items = res.envelope.dataAsList.map(Series.fromJson).toList(growable: false);
    return Page(items: items, total: res.envelope.total ?? items.length, offset: offset, fromCache: res.fromCache);
  }

  Future<Series> series(int id) async {
    final res = await _api.get('/series/$id', cacheScope: 'series');
    return Series.fromJson(res.envelope.dataAsJson);
  }

  Future<Episode> episode(int id) async {
    final res = await _api.get('/episodes/$id', cacheScope: 'series');
    return Episode.fromJson(res.envelope.dataAsJson);
  }

  /// `type` ∈ live | vod | series (null = all)
  Future<List<Category>> categories({String? type, bool preferCache = false}) async {
    final res = await _api.get('/categories', cacheScope: 'categories', preferCache: preferCache, query: {
      'type': ?type,
    });
    return res.envelope.dataAsList.map(Category.fromJson).toList(growable: false);
  }

  /// `type` ∈ all | movie | series | channel. Requires q ≥ 2 chars.
  Future<List<SearchResult>> search(String q, {String type = 'all', int limit = 20}) async {
    final res = await _api.get('/search', query: {'q': q, 'type': type, 'limit': limit});
    return res.envelope.dataAsList.map(SearchResult.fromJson).toList(growable: false);
  }

  Future<Person> person(int tmdbPersonId) async {
    final res = await _api.get('/person/$tmdbPersonId');
    final j = res.envelope.dataAsJson;
    return Person(
      name: asString(j['name']),
      profileImage: asStringOrNull(j['profile_image']) ?? asStringOrNull(j['profile_url']),
      movies: asJsonList(j['movies']).map(Movie.fromJson).toList(growable: false),
      series: asJsonList(j['series']).map(Series.fromJson).toList(growable: false),
    );
  }
}
