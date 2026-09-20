import 'package:cari_tv/models/ad.dart';
import 'package:cari_tv/models/auth_tokens.dart';
import 'package:cari_tv/models/channel.dart';
import 'package:cari_tv/models/entitlements.dart';
import 'package:cari_tv/models/epg.dart';
import 'package:cari_tv/models/layout.dart';
import 'package:cari_tv/models/manifest.dart';
import 'package:cari_tv/models/movie.dart';
import 'package:cari_tv/models/navigation.dart';
import 'package:cari_tv/models/series.dart';
import 'package:cari_tv/models/user.dart';
import 'package:cari_tv/models/watch.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('AuthTokens from login payload and round trip', () {
    final now = DateTime.utc(2026, 1, 1, 12);
    final t = AuthTokens.fromLogin({'access_token': 'a', 'refresh_token': 'r', 'expires_in': '3600'}, now: now);
    expect(t.expiresAt, DateTime.utc(2026, 1, 1, 13));
    expect(t.isExpiring(now: now), isFalse);
    expect(t.isExpiring(now: now.add(const Duration(minutes: 59, seconds: 45))), isTrue);
    final back = AuthTokens.fromJson(t.toJson());
    expect(back.accessToken, 'a');
    expect(back.expiresAt, t.expiresAt);
  });

  test('User parses PDO-style strings', () {
    final u = User.fromJson({'id': '7', 'username': 'bob', 'email': 'b@x.com', 'first_name': 'Bob', 'last_name': '', 'max_connections': '2', 'parental_pin': '0000', 'adult_enabled': '0'});
    expect(u.id, 7);
    expect(u.maxConnections, 2);
    expect(u.adultEnabled, isFalse);
    expect(u.displayName, 'Bob');
  });

  test('Entitlements gate restricted content', () {
    final e = Entitlements.fromJson({'movies': ['1', 2], 'series': [], 'channels': [9], 'categories': [3], 'packages': [{'id': 1, 'name': 'Free', 'is_free': 1, 'price_display': 'Free', 'features': '[]'}], 'has_subscription': 1, 'adult_enabled': false});
    expect(e.allows('movie', 1), isTrue);
    expect(e.allows('movie', 3), isFalse);
    expect(e.allows('movie', 3, categoryId: 3), isTrue);
    expect(e.allows('channel', 9), isTrue);
    expect(e.packages.single.isFree, isTrue);
  });

  test('Channel detail with now_playing raw datetime', () {
    final c = Channel.fromJson({'id': 5, 'name': 'CNC3', 'slug': 'cnc3', 'logo_url': '/uploads/channel/5/logo_medium.webp', 'stream_url': 'http://x/live.m3u8', 'is_hd': '1', 'is_restricted': false, 'channel_number': '3', 'now_playing': {'title': 'News', 'description': '', 'start_time': '2026-09-20 18:00:00', 'end_time': '2026-09-20 19:00:00'}, 'next_up': null});
    expect(c.isHd, isTrue);
    expect(c.nowPlaying!.start, DateTime.utc(2026, 9, 20, 18));
    expect(c.nextUp, isNull);
    expect(c.toCard().subtitle, 'Ch 3');
  });

  test('Movie detail with extras', () {
    final m = Movie.fromJson({
      'id': 12, 'title': 'Film', 'slug': 'film', 'year': '2020', 'genres': '["Action","Drama"]', 'runtime': '95', 'vote_average': '7.4',
      'poster_url': '/uploads/vod/12/poster_poster.webp', 'stream_url': 'https://vod/content/movie-12/master.m3u8', 'is_restricted': '1',
      'drm': {'scheme': 'cenc', 'key_id': 'abc', 'license_url': 'https://site/api/v1/drm/license?content_id=movie-12&server_id=1'},
      'trailers': [{'id': 1, 'title': 'T', 'video_key': 'abc', 'url': null, 'is_primary': 1}],
      'markers': [{'id': 1, 'marker_type': 'intro_end', 'position_seconds': '65.500', 'label': null}],
      'subtitles': [{'id': 1, 'language_code': 'en', 'language_name': 'English', 'file_path': '/uploads/vod/12/subtitles/en.vtt', 'format': 'vtt', 'is_default': 1, 'is_forced': 0}],
      'cast': [{'name': 'A', 'character_name': 'B', 'role': 'actor'}],
    });
    expect(m.genres, ['Action', 'Drama']);
    expect(m.runtime, 95);
    expect(m.voteAverage, 7.4);
    expect(m.isRestricted, isTrue);
    expect(m.drm!.keyId, 'abc');
    expect(m.trailers.single.watchUrl, 'https://www.youtube.com/watch?v=abc');
    expect(m.markers.single.positionSeconds, 65.5);
    expect(m.subtitles.single.isDefault, isTrue);
    expect(m.cast.single.character, 'B');
    expect(m.isPlayable, isTrue);
  });

  test('Series detail nests seasons and episodes with inherited ids', () {
    final s = Series.fromJson({
      'id': 3, 'title': 'Show', 'slug': 'show', 'season_count': '2',
      'seasons': [
        {'id': 30, 'season_number': 1, 'name': 'Season 1', 'episodes': [{'id': 300, 'title': 'Pilot', 'episode_number': '1', 'runtime': 42, 'stream_url': 'https://v/master.m3u8', 'vod_status': 'complete'}]},
      ],
    });
    final ep = s.seasons.single.episodes.single;
    expect(ep.seriesId, 3);
    expect(ep.seasonId, 30);
    expect(ep.seasonNumber, 1);
    expect(ep.code, 'S1E1');
    expect(s.toCard().subtitle, '2 seasons');
  });

  test('Episode next/prev refs', () {
    final e = Episode.fromJson({'id': 301, 'series_id': 3, 'episode_number': 2, 'season_number': 1, 'title': 'Two', 'next_episode': {'id': 302, 'episode_number': 3, 'title': 'Three', 'stream_url': 'u'}, 'prev_episode': {'id': 300, 'episode_number': 1, 'title': 'Pilot'}});
    expect(e.nextEpisode!.id, 302);
    expect(e.prevEpisode!.episodeNumber, 1);
  });

  test('Watch progress batch map keyed by id', () {
    final map = WatchProgress.mapFromBatch({'300': {'content_id': 300, 'progress_seconds': '120', 'duration_seconds': '2400', 'completed': 0}});
    expect(map[300]!.fraction, closeTo(0.05, 0.001));
    expect(map[300]!.isResumable, isTrue);
  });

  test('Continue watching folds episodes into series items', () {
    final i = ContinueWatchingItem.fromJson({'content_type': 'series', 'content_id': 301, 'id': 3, 'title': 'Show', 'progress_seconds': 600, 'duration_seconds': 2400, 'completed': 0, 'last_watched_at': '2026-09-19 20:00:00', 'resume_episode_id': 301, 'resume_season_number': 1, 'resume_episode_number': 2, 'resume_episode_title': 'Two'});
    expect(i.resumeEpisodeId, 301);
    expect(i.toCard().subtitle, 'S1 E2');
    expect(i.toCard().progress, 0.25);
  });

  test('Layout with curated and custom items', () {
    final l = AppLayout.fromJson({
      'id': 1, 'name': 'Mobile Home', 'platform': 'mobile', 'status': 'published', 'updated_at': '2026-09-01 10:00:00',
      'sections': [
        {'id': 10, 'section_type': 'content_row', 'title': 'Latest', 'settings': {'source': 'latest', 'max_items': 10}, 'sort_order': 0, 'is_active': 1,
          'items': [{'id': 0, 'content_type': 'movie', 'content_id': 12, 'settings': [], 'sort_order': 0, 'content': {'id': 12, 'title': 'Film', 'poster_url': '/p.webp', 'year': 2020}}]},
        {'id': 11, 'section_type': 'banner', 'title': null, 'settings': {'image_url': '/b.png', 'link_url': '/movies/12'}, 'sort_order': 1, 'is_active': 1,
          'items': [{'id': 5, 'content_type': 'custom', 'content_id': null, 'settings': {'title': 'Promo', 'image_url': '/promo.png', 'link_url': 'https://x'}, 'sort_order': 0, 'content': {}}]},
        {'id': 12, 'section_type': 'continue_watching', 'title': 'Continue', 'settings': {}, 'sort_order': 2, 'is_active': 1, 'items': []},
      ],
    });
    expect(l.sections.length, 3);
    expect(l.sections[0].source, 'latest');
    expect(l.sections[0].cards.single.type, 'movie');
    expect(l.sections[1].cards.single.linkUrl, 'https://x');
    expect(l.sections[2].cards, isEmpty);
  });

  test('Navigation and pages', () {
    final n = AppNavigation.fromJson({'id': 2, 'platform': 'mobile', 'position': 'main', 'settings': {'style': 'bottom_tab', 'max_items': '5'}, 'items': [{'id': 1, 'label': 'Home', 'icon': 'lucide-home', 'target': 'page', 'url': null, 'sort_order': 0, 'page_slug': 'home', 'page_type': 'home', 'layout_id': null}]});
    expect(n.maxItems, 5);
    expect(n.items.single.pageType, 'home');
    final p = AppPage.fromJson({'id': 1, 'name': 'Home', 'slug': 'home', 'page_type': 'home', 'icon': 'lucide-home', 'layout_id': null, 'is_system': '1', 'sort_order': 0});
    expect(p.isSystem, isTrue);
  });

  test('Manifest picks mobile platform hashes and reports changes', () {
    final m = Manifest.fromJson({
      'channels': {'version': 'c1', 'count': 10}, 'movies': {'version': 'm1'}, 'series': {'version': 's1'}, 'categories': {'version': 'k1'}, 'epg': {'version': 'e1'},
      'layouts': {'mobile': {'version': 'l1'}, 'web': {'version': 'lw'}}, 'navigation': {'web': {'version': 'nw'}},
    });
    expect(m.versions['layouts'], 'l1');
    expect(m.versions.containsKey('navigation'), isFalse);
    expect(m.changedScopes({'channels': 'c1', 'movies': 'old'}), containsAll(['movies', 'series', 'layouts']));
    expect(m.changedScopes({'channels': 'c1', 'movies': 'old'}), isNot(contains('channels')));
  });

  test('EPG grouped schedule now/next', () {
    final s = EpgChannelSchedule.fromJson({'channel_id': 5, 'channel_name': 'X', 'programmes': [
      {'id': 1, 'title': 'A', 'start_time': '2026-09-20T10:00:00Z', 'end_time': '2026-09-20T11:00:00Z'},
      {'id': 2, 'title': 'B', 'start_time': '2026-09-20T11:00:00Z', 'end_time': '2026-09-20T12:00:00Z'},
    ]});
    final t = DateTime.utc(2026, 9, 20, 10, 30);
    expect(s.nowAt(t)!.title, 'A');
    expect(s.nextAfter(t)!.title, 'B');
    expect(s.nowAt(t)!.progressAt(t), 0.5);
  });

  test('Ads: serve result, breaks and overlay settings', () {
    final r = AdServeResult.fromJson({'success': true, 'source': 'direct', 'ads': [{'id': 1, 'campaign_id': 2, 'type': 'pre_roll', 'video_url': 'https://v.mp4', 'skip_after': '5', 'font_size': null}], 'count': 1});
    expect(r.ads.single.isVideo, isTrue);
    expect(r.ads.single.skipAfter, 5);
    expect(r.ads.single.fontSize, 'medium');
    expect(AdBreak.fromJson({'position': '0', 'type': 'pre_roll', 'label': 'Pre-Roll', 'source': 'auto'}).type, 'pre_roll');
    final o = OverlaySettings.fromJson({'success': true, 'settings': {'banner_enabled': true, 'banner_initial_delay': 30, 'scroller_enabled': false}});
    expect(o.scrollerEnabled, isFalse);
    expect(o.bannerRepeatInterval, 300);
  });
}
