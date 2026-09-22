// Shared fakes for widget tests that mount real screens.
//
// - FakeVideoPlayerPlatform records every call video_player makes to the
//   platform (create / play / pause / dispose ...), so a test can prove the
//   native player was paused and released.
// - FakeWakelock replaces the wakelock_plus method channel.
// - Repositories are mocktail mocks; unstubbed calls throw, which the
//   providers turn into error states (screens still render their chrome).
// - Auth, entitlements, navigation, offline banner and the manifest poller
//   are overridden so no network, timers or plugins are touched.
import 'dart:async';

import 'package:cari_tv/config/app_config.dart';
import 'package:cari_tv/core/providers.dart';
import 'package:cari_tv/features/ads/data/ads_repository.dart';
import 'package:cari_tv/features/analytics/data/analytics_repository.dart';
import 'package:cari_tv/features/auth/data/auth_repository.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/billing/billing_provider.dart';
import 'package:cari_tv/features/content/data/content_repository.dart';
import 'package:cari_tv/features/layout/data/layout_repository.dart';
import 'package:cari_tv/features/layout/state/layout_providers.dart';
import 'package:cari_tv/features/library/data/user_content_repository.dart';
import 'package:cari_tv/features/live/data/epg_repository.dart';
import 'package:cari_tv/features/navigation/state/navigation_provider.dart';
import 'package:cari_tv/features/player/hls/hls_master.dart';
import 'package:cari_tv/features/player/hls/hls_master_source.dart';
import 'package:cari_tv/features/player/state/player_support.dart';
import 'package:cari_tv/features/recommendations/data/recommendation_repository.dart';
import 'package:cari_tv/features/repositories.dart';
import 'package:cari_tv/models/app_features.dart';
import 'package:cari_tv/models/entitlements.dart';
import 'package:cari_tv/models/navigation.dart';
import 'package:cari_tv/models/user.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/misc.dart' show Override;
import 'package:mocktail/mocktail.dart';
import 'package:plugin_platform_interface/plugin_platform_interface.dart';
import 'package:video_player_platform_interface/video_player_platform_interface.dart';
import 'package:wakelock_plus_platform_interface/wakelock_plus_platform_interface.dart';

// --- Repository mocks ---------------------------------------------------------

class MockContentRepository extends Mock implements ContentRepository {}

class MockLayoutRepository extends Mock implements LayoutRepository {}

class MockUserContentRepository extends Mock implements UserContentRepository {}

class MockEpgRepository extends Mock implements EpgRepository {}

class MockAdsRepository extends Mock implements AdsRepository {}

class MockRecommendationRepository extends Mock implements RecommendationRepository {}

class MockAuthRepository extends Mock implements AuthRepository {}

/// Records analytics calls instead of batching them on a timer.
class FakeAnalyticsRepository implements AnalyticsRepository {
  final List<String> events = [];
  final List<String> qoeEvents = [];
  final List<QoeCall> qoeCalls = [];
  int flushes = 0;

  List<QoeCall> qoeOf(String type) => qoeCalls.where((q) => q.type == type).toList();

  @override
  String get sessionId => 'test-session';

  @override
  void track(String eventType, {String? contentType, int? contentId, String? page, Map<String, dynamic>? metadata}) => events.add(eventType);

  @override
  void qoe(String eventType, {String? contentType, int? contentId, Map<String, dynamic>? metadata}) {
    qoeEvents.add(eventType);
    qoeCalls.add(QoeCall(eventType, contentType: contentType, contentId: contentId, metadata: metadata ?? const {}));
  }

  @override
  Future<void> flush() async => flushes++;

  @override
  void dispose() {}
}

class QoeCall {
  const QoeCall(this.type, {this.contentType, this.contentId, required this.metadata});
  final String type;
  final String? contentType;
  final int? contentId;
  final Map<String, dynamic> metadata;
}

/// A failure the fake player reports for URLs containing [urlPart]:
/// `after == null` fails before `initialized` (decoder init failure);
/// otherwise the player initialises and fails [after] that long into playback
/// (adaptive selection climbing to a bad rendition).
class FakeFailure {
  const FakeFailure(this.message, {this.after});
  final String message;
  final Duration? after;
}

/// Serves canned master playlists by URL.
class FakeMasterSource implements HlsMasterSource {
  FakeMasterSource([Map<String, String> masters = const {}]) : _masters = Map.of(masters);
  final Map<String, String> _masters;
  final List<Uri> loads = [];

  void put(String url, String text) => _masters[url] = text;

  @override
  Future<HlsMaster?> load(Uri url) async {
    loads.add(url);
    final text = _masters[url.toString()];
    return text == null ? null : parseHlsMaster(text, url);
  }
}

// --- Platform fakes -------------------------------------------------------------

/// video_player platform that never touches a real player. Emits the
/// `initialized` event for every created player and records the call order.
class FakeVideoPlayerPlatform extends VideoPlayerPlatform with MockPlatformInterfaceMixin {
  final List<String> calls = [];
  final Map<int, StreamController<VideoEvent>> _events = {};
  int _nextId = 1;

  /// Position reported to the controller's periodic position poll.
  Duration position = Duration.zero;
  Duration duration = const Duration(minutes: 90);

  /// Delay before a created player reports `initialized` (zero = at once).
  Duration initDelay = Duration.zero;
  final Map<int, Timer> _initTimers = {};

  /// URL each player was created with, and failures by URL substring.
  final Map<int, String> urls = {};
  final Map<String, FakeFailure> failures = {};
  List<String> get createdUrls => [for (var i = 1; i < _nextId; i++) urls[i] ?? ''];

  List<String> callsFor(int id) => calls.where((c) => c.endsWith(':$id')).toList();
  bool disposed(int id) => calls.contains('dispose:$id');

  @override
  Future<void> init() async => calls.add('init');

  @override
  Future<int?> create(DataSource dataSource) => createWithOptions(VideoCreationOptions(dataSource: dataSource, viewType: VideoViewType.textureView));

  @override
  Future<int?> createWithOptions(VideoCreationOptions options) async {
    final id = _nextId++;
    // Single-subscription so the initialized event waits for the listener.
    // onCancel must be set: without it StreamSubscription.cancel() returns a
    // root-zone future that never completes under FakeAsync, and
    // VideoPlayerController.dispose() would hang before reaching the platform.
    final controller = StreamController<VideoEvent>(onCancel: () async {});
    _events[id] = controller;
    final url = options.dataSource.uri ?? '';
    urls[id] = url;
    final failure = failures.entries.where((e) => url.contains(e.key)).map((e) => e.value).firstOrNull;
    void fail() {
      if (!controller.isClosed) controller.addError(PlatformException(code: 'VideoError', message: failure!.message));
    }

    void ready() {
      if (!controller.isClosed) controller.add(VideoEvent(eventType: VideoEventType.initialized, duration: duration, size: const Size(1920, 1080)));
    }

    if (failure != null && failure.after == null) {
      fail();
    } else if (initDelay == Duration.zero) {
      ready();
      if (failure != null) _initTimers[id] = Timer(failure.after!, fail);
    } else {
      _initTimers[id] = Timer(initDelay, () {
        ready();
        if (failure != null) _initTimers[id] = Timer(failure.after!, fail);
      });
    }
    calls.add('create:$id');
    return id;
  }

  @override
  Stream<VideoEvent> videoEventsFor(int playerId) => _events[playerId]!.stream;

  @override
  Future<void> play(int playerId) async => calls.add('play:$playerId');

  @override
  Future<void> pause(int playerId) async => calls.add('pause:$playerId');

  @override
  Future<void> dispose(int playerId) async {
    calls.add('dispose:$playerId');
    _initTimers.remove(playerId)?.cancel();
    await _events.remove(playerId)?.close();
  }

  @override
  Future<void> setLooping(int playerId, bool looping) async {}

  @override
  Future<void> setVolume(int playerId, double volume) async {}

  @override
  Future<void> setPlaybackSpeed(int playerId, double speed) async {}

  @override
  Future<void> seekTo(int playerId, Duration position) async {
    calls.add('seek:$playerId');
    this.position = position;
  }

  @override
  Future<Duration> getPosition(int playerId) async => position;

  @override
  Future<void> setMixWithOthers(bool mixWithOthers) async {}

  @override
  Future<void> setPreventsDisplaySleepDuringVideoPlayback(int playerId, bool preventsDisplaySleep) async {}

  @override
  Widget buildView(int playerId) => const SizedBox.expand();

  @override
  Widget buildViewWithOptions(VideoViewOptions options) => const SizedBox.expand();
}

class FakeWakelock extends WakelockPlusPlatformInterface with MockPlatformInterfaceMixin {
  bool enabledNow = false;

  @override
  Future<void> toggle({required bool enable}) async => enabledNow = enable;

  @override
  Future<bool> get enabled async => enabledNow;
}

/// Installs both platform fakes and returns the video one for assertions.
FakeVideoPlayerPlatform installPlatformFakes() {
  final video = FakeVideoPlayerPlatform();
  VideoPlayerPlatform.instance = video;
  WakelockPlusPlatformInterface.instance = FakeWakelock();
  return video;
}

// --- Riverpod overrides ----------------------------------------------------------

class FixedAuth extends AuthNotifier {
  FixedAuth(this.initial);
  final AuthState initial;
  @override
  AuthState build() => initial;
}

/// No 30 s timer, no lifecycle observer.
class NoPoller extends ManifestPoller {
  @override
  DateTime? build() => null;
}

const testUser = User(id: 1, username: 'tester', email: 'tester@example.com', firstName: 'Test', lastName: 'User', avatar: null, maxConnections: 1, parentalPin: null, adultEnabled: false);

const testEntitlements = Entitlements(movieIds: {}, seriesIds: {}, channelIds: {}, categoryIds: {}, packages: [], hasSubscription: true, adultEnabled: false);

/// A backend navigation that makes Search and Settings bottom tabs.
const navWithSearchAndSettings = AppNavigation(
  id: 1,
  platform: 'mobile',
  position: 'main',
  style: 'bottom_tab',
  showIcons: true,
  showLabels: true,
  maxItems: 5,
  items: [
    NavItem(id: 1, label: 'Home', icon: 'lucide-home', target: 'page', url: null, sortOrder: 1, pageSlug: 'home', pageType: 'home', layoutId: null),
    NavItem(id: 2, label: 'Search', icon: 'lucide-search', target: 'page', url: null, sortOrder: 2, pageSlug: 'search', pageType: 'search', layoutId: null),
    NavItem(id: 3, label: 'Live TV', icon: 'lucide-radio', target: 'page', url: null, sortOrder: 3, pageSlug: 'live', pageType: 'live_tv', layoutId: null),
    NavItem(id: 4, label: 'Settings', icon: 'lucide-settings', target: 'page', url: null, sortOrder: 4, pageSlug: 'settings', pageType: 'settings', layoutId: null),
  ],
);

class TestRepos {
  final content = MockContentRepository();
  final layout = MockLayoutRepository();
  final userContent = MockUserContentRepository();
  final epg = MockEpgRepository();
  final ads = MockAdsRepository();
  final recommendations = MockRecommendationRepository();
  final auth = MockAuthRepository();
  final analytics = FakeAnalyticsRepository();
}

/// Overrides for a fully mounted app or a single screen. Screens whose data
/// calls are not stubbed render their error state, which is what the
/// navigation audit wants: a way back must exist even when loading fails.
List<Override> testOverrides({required AuthState auth, AppNavigation? nav, TestRepos? repos, HlsMasterSource? masterSource, Override? entitlements, AppFeatures features = AppFeatures.none, AppConfig? config}) {
  final r = repos ?? TestRepos();
  return [
    hlsMasterSourceProvider.overrideWithValue(masterSource ?? FakeMasterSource()),
    appConfigProvider.overrideWithValue(config ?? AppConfig.forFlavor(AppFlavor.dev)),
    // Server feature switches (billing etc.); off unless a test turns them on.
    remoteFeaturesProvider.overrideWith((ref) async => features),
    authProvider.overrideWith(() => FixedAuth(auth)),
    // A duplicate override of the same provider is not allowed, so tests that
    // drive entitlements pass their own override here.
    entitlements ?? entitlementsProvider.overrideWith((ref) async => testEntitlements),
    isOfflineProvider.overrideWith((ref) => Stream.value(false)),
    manifestPollerProvider.overrideWith(NoPoller.new),
    navigationProvider.overrideWith((ref) async => nav),
    playbackAdsProvider.overrideWith((ref, req) async => PlaybackAds.none),
    contentRepositoryProvider.overrideWithValue(r.content),
    layoutRepositoryProvider.overrideWithValue(r.layout),
    userContentRepositoryProvider.overrideWithValue(r.userContent),
    epgRepositoryProvider.overrideWithValue(r.epg),
    adsRepositoryProvider.overrideWithValue(r.ads),
    recommendationRepositoryProvider.overrideWithValue(r.recommendations),
    authRepositoryProvider.overrideWithValue(r.auth),
    analyticsRepositoryProvider.overrideWithValue(r.analytics),
  ];
}
