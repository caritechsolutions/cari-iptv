import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

import '../../../core/util/time.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/app_image.dart';
import '../../../models/ad.dart';
import '../../../models/content_extras.dart';
import '../../analytics/data/analytics_repository.dart';
import '../../library/data/user_content_repository.dart';
import '../../repositories.dart';
import '../hls/hls_master.dart';
import '../hls/hls_master_source.dart';
import '../hls/quality_memory.dart';
import '../state/player_support.dart';
import 'ad_player.dart';
import 'playback_error.dart';
import 'playback_request.dart';
import 'player_error_panel.dart';
import 'player_fullscreen.dart';

enum _Phase { loadingAds, preRoll, content, midRoll }

/// Player for live channels and VOD.
///
/// - Portrait by default (the app is portrait-locked): the video sits at the
///   top of the screen in a 16:9 stage, title and extras underneath. The
///   full-screen button rotates to landscape + immersive; back, playback
///   ending, a stream error or leaving the player always restore portrait.
///   Pre-roll / mid-roll ads play in the same stage under the same rules.
/// - HLS via video_player (ExoPlayer / AVPlayer); AES-128 keys are fetched by
///   the native player from the public key URL inside the playlist.
/// - Resume position, progress posting every 10 s, skip-intro / next-episode
///   markers, VTT subtitles, pre-roll / mid-roll ads and overlays, QoE events.
class PlayerScreen extends ConsumerStatefulWidget {
  const PlayerScreen({super.key, required this.request});
  final PlaybackRequest request;

  @override
  ConsumerState<PlayerScreen> createState() => _PlayerScreenState();
}

class _PlayerScreenState extends ConsumerState<PlayerScreen> with WidgetsBindingObserver {
  VideoPlayerController? _controller;
  late PlaybackRequest _req = widget.request;
  _Phase _phase = _Phase.loadingAds;
  PlaybackAds _ads = PlaybackAds.none;
  final Set<double> _playedBreaks = {};
  List<Ad> _midRollAds = const [];

  bool _controlsVisible = true;
  Timer? _hideTimer;
  Timer? _positionTimer;
  PlaybackError? _error;
  bool _buffering = false;
  bool _initialized = false;
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;
  Subtitle? _activeSubtitle;
  bool _showSkipIntro = false;
  bool _showNextCountdown = false;
  int _nextCountdown = 10;
  Timer? _nextTimer;
  DateTime? _startedAt;
  bool _startupReported = false;
  bool _completedReported = false;
  // Captured in initState: `ref` must never be used from dispose() — Riverpod
  // throws a StateError once the element is unmounted, and anything after the
  // throw (such as releasing the video controller) would never run.
  late final AnalyticsRepository _analytics;
  late final UserContentRepository _userContent;
  late ProgressReporter _progress;
  final PlayerFullscreen _fs = PlayerFullscreen();

  // Quality fallback: the parsed master playlist of the current title, the
  // variant opened directly (null = Auto / master), and a decoder failure
  // whose outcome (recovered or not) is not known yet.
  late final HlsMasterSource _masterSource;
  late final QualityMemory _quality;
  HlsMaster? _master;
  HlsVariant? _variant;
  _PendingFailure? _pendingFailure;
  String? _note;
  Timer? _noteTimer;

  @override
  void initState() {
    super.initState();
    _analytics = ref.read(analyticsRepositoryProvider);
    _userContent = ref.read(userContentRepositoryProvider);
    _masterSource = ref.read(hlsMasterSourceProvider);
    _quality = ref.read(qualityMemoryProvider);
    _progress = ProgressReporter(_userContent, _req);
    WidgetsBinding.instance.addObserver(this);
    _fs.addListener(_onFullscreenChanged);
    WakelockPlus.enable();
    _start();
  }

  /// App goes to the background (home button, screen off, incoming call):
  /// pause and save progress. `inactive` arrives before `paused`, so pausing
  /// here also stops video_player's own observer from auto-resuming later —
  /// the user resumes with the play button.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.inactive:
      case AppLifecycleState.hidden:
      case AppLifecycleState.paused:
        _pauseForBackground();
      case AppLifecycleState.resumed:
        if (mounted) setState(() => _controlsVisible = true);
      case AppLifecycleState.detached:
        break;
    }
  }

  void _pauseForBackground() {
    final c = _controller;
    if (c == null || !c.value.isInitialized || !c.value.isPlaying) return;
    unawaited(c.pause());
    unawaited(_progress.report(c.value.position, c.value.duration));
    _analytics.track('watch_pause', contentType: _req.contentType, contentId: _req.contentId, metadata: {'reason': 'background'});
    _hideTimer?.cancel();
    if (mounted) setState(() => _controlsVisible = true);
  }

  /// Stops audio/video and frees the native player. Idempotent, never throws,
  /// and every exit path (back, next episode, retry, dispose) goes through it.
  /// Progress is saved on the way out.
  void _releaseController({bool abandon = false}) {
    final c = _controller;
    if (c == null) return;
    _controller = null;
    _initialized = false;
    _positionTimer?.cancel();
    _positionTimer = null;
    _progress.stop();
    c.removeListener(_onValue);
    try {
      if (c.value.isInitialized && !_req.isLive) {
        final pos = c.value.position;
        final dur = c.value.duration;
        if (!_completedReported && pos.inSeconds > 0) {
          unawaited(_progress.report(pos, dur));
          if (abandon) _analytics.track('watch_abandon', contentType: _req.contentType, contentId: _req.contentId, metadata: {'position': pos.inSeconds});
        }
      }
    } catch (_) {
      // Reporting must never keep the native player alive.
    } finally {
      if (c.value.isInitialized) unawaited(c.pause());
      unawaited(c.dispose());
    }
  }

  void _onFullscreenChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _start() async {
    // Channels opened from a link may have no stream URL yet.
    if (_req.isLive && _req.streamUrl.isEmpty) {
      try {
        final ch = await ref.read(contentRepositoryProvider).channel(_req.contentId);
        _req = _req.copyWith(streamUrl: ch.streamUrl);
      } catch (e) {
        _reportError('Channel not available (could not load the channel record)');
        return;
      }
    }
    final masterFuture = _loadMaster();
    try {
      _ads = await ref.read(playbackAdsProvider(_req).future).timeout(const Duration(seconds: 6));
    } catch (_) {
      _ads = PlaybackAds.none;
    }
    _master = await masterFuture;
    if (!mounted) return;
    if (_ads.preRoll.isNotEmpty) {
      setState(() => _phase = _Phase.preRoll);
    } else {
      _startContent();
    }
  }

  Future<void> _startContent() async {
    setState(() => _phase = _Phase.content);
    await _initController(_startUrl(), resumeFrom: _req.resumeFromSeconds);
  }

  // --- Quality fallback -----------------------------------------------------

  String get _titleKey => QualityMemory.keyFor(_req.contentType, _req.contentId);

  /// Parses the master playlist (null when the URL is not a master).
  Future<HlsMaster?> _loadMaster() async {
    final uri = Uri.tryParse(_req.streamUrl);
    if (uri == null || _req.streamUrl.isEmpty) return null;
    try {
      return await _masterSource.load(uri).timeout(const Duration(seconds: 6));
    } catch (_) {
      return null;
    }
  }

  /// Where playback starts: a rendition remembered for this title (after a
  /// fallback or a manual choice) is opened directly so a bad rendition is
  /// never tried again; otherwise the master (Auto).
  String _startUrl() {
    final master = _master;
    final pinned = _quality.peek(_titleKey)?.pinned;
    if (master != null && pinned != null) {
      final v = master.byUri(pinned);
      if (v != null && _quality.forTitle(_titleKey).allows(v)) {
        _variant = v;
        return v.uri.toString();
      }
    }
    _variant = null;
    return _req.streamUrl;
  }

  String get _currentUrl => _variant?.uri.toString() ?? _req.streamUrl;

  /// Renditions the quality menu may offer (excluded ones are hidden).
  List<HlsVariant> get _qualityChoices {
    final master = _master;
    if (master == null) return const [];
    return _quality.forTitle(_titleKey).allowed(master);
  }

  /// Auto (master) is offered only while nothing has been excluded: once a
  /// rendition failed, adaptive selection could pick it again.
  bool get _autoAllowed => !_quality.forTitle(_titleKey).hasExclusions;

  /// Which rendition failed in Auto mode: from the resolution ExoPlayer prints
  /// in the format, else the exact codec string (masters with CODECS).
  HlsVariant? _guessFailedVariant(HlsMaster master, PlaybackError error) {
    final m = RegExp(r'\[(\d+), (\d+), ').firstMatch(error.technical);
    if (m != null) {
      final h = int.tryParse(m.group(2)!);
      final w = int.tryParse(m.group(1)!);
      final byRes = master.variants.where((v) => v.height == h && (v.width == null || w == null || v.width == w)).firstOrNull ?? master.variants.where((v) => v.height == h).firstOrNull;
      if (byRes != null) return byRes;
    }
    final codec = error.codec;
    if (codec != null && master.hasCodecs) {
      return master.variants.where((v) => v.videoCodec == codec).firstOrNull;
    }
    return null;
  }

  /// Opens [v] directly (null = back to the master / Auto) and resumes.
  Future<void> _switchToVariant(HlsVariant? v, {Duration? resumeAt, String? note, bool manual = false}) async {
    _variant = v;
    if (manual) _quality.forTitle(_titleKey).pinned = v?.uri;
    if (note != null) _showNote(note);
    final resume = resumeAt ?? _position;
    await _initController(v?.uri.toString() ?? _req.streamUrl, resumeFrom: _req.isLive ? null : resume.inSeconds);
  }

  void _showNote(String text) {
    _noteTimer?.cancel();
    setState(() => _note = text);
    _noteTimer = Timer(const Duration(seconds: 5), () {
      if (mounted) setState(() => _note = null);
    });
  }

  /// Every decoder failure is reported exactly once, when its outcome is
  /// known: `recovered` when the fallback rendition plays, `failed` when no
  /// rendition remained or the fallback failed too, `abandoned` when the
  /// viewer left before that.
  void _emitPlaybackError(PlaybackError error, {required String outcome, HlsVariant? failed, FallbackDecision? decision}) {
    final meta = error.toQoeMetadata(streamUrl: _req.streamUrl);
    meta['recovered'] = outcome == 'recovered';
    meta['outcome'] = outcome;
    if (failed != null) meta['rendition'] = failed.label;
    if (decision != null) {
      meta['rule'] = decision.rule;
      meta['excluded'] = decision.excluded.map((v) => v.label).toList();
      if (decision.next != null) meta['fallback_to'] = decision.next!.label;
    }
    _analytics.qoe('playback_error', contentType: _req.contentType, contentId: _req.contentId, metadata: meta);
  }

  void _flushPendingFailure(String outcome) {
    final p = _pendingFailure;
    if (p == null) return;
    _pendingFailure = null;
    _emitPlaybackError(p.error, outcome: outcome, failed: p.decision.failed, decision: p.decision);
  }

  /// Called from the position tick: the fallback rendition counts as
  /// recovered once it plays past the resume point (or 8 s without an error).
  void _checkRecovered(VideoPlayerController c) {
    final p = _pendingFailure;
    if (p == null) return;
    final v = c.value;
    final advanced = v.isPlaying && !v.isBuffering && v.position > p.position + const Duration(milliseconds: 500);
    final timedOut = DateTime.now().difference(p.at) > const Duration(seconds: 8) && v.isPlaying;
    if (advanced || timedOut) _flushPendingFailure('recovered');
  }

  Future<void> _initController(String url, {int? resumeFrom}) async {
    _releaseController();
    _error = null;
    _startedAt = DateTime.now();
    _startupReported = false;
    _completedReported = false;
    final uri = Uri.tryParse(url);
    if (uri == null || url.isEmpty) {
      _reportError('No stream URL for this title');
      return;
    }
    final isHls = url.contains('.m3u8');
    final c = VideoPlayerController.networkUrl(uri, formatHint: isHls ? VideoFormat.hls : null, videoPlayerOptions: VideoPlayerOptions(allowBackgroundPlayback: false, mixWithOthers: false));
    _controller = c;
    c.addListener(_onValue);
    // The screen may be left (or the controller replaced) during any await.
    bool stale() => !mounted || !identical(_controller, c);
    try {
      await c.initialize();
      if (stale()) return;
      _initialized = true;
      _duration = c.value.duration;
      if (resumeFrom != null && resumeFrom > 0 && !_req.isLive) {
        await c.seekTo(Duration(seconds: resumeFrom));
        if (stale()) return;
      }
      final defaultSub = _req.subtitles.where((s) => s.isDefault).firstOrNull;
      if (defaultSub != null) _setSubtitle(defaultSub);
      await c.play();
      if (stale()) return;
      _progress.start(() => c.value.position, () => c.value.duration);
      _positionTimer = Timer.periodic(const Duration(milliseconds: 500), (_) => _tick());
      _analytics.track('watch_start', contentType: _req.contentType, contentId: _req.contentId, metadata: {'live': _req.isLive});
      _scheduleHide();
      setState(() {});
    } catch (e) {
      // The value listener has usually reported this error already.
      if (stale() || _error != null) return;
      _reportError('$e');
    }
  }

  void _onValue() {
    final v = _controller?.value;
    if (v == null || !mounted) return;
    if (v.hasError && _error == null) {
      _reportError(v.errorDescription ?? 'Playback error');
      return;
    }
    if (v.isBuffering != _buffering) {
      setState(() => _buffering = v.isBuffering);
      _analytics.qoe(v.isBuffering ? 'buffer_start' : 'buffer_end', contentType: _req.contentType, contentId: _req.contentId);
    }
    if (v.isPlaying && !_startupReported && _startedAt != null) {
      _startupReported = true;
      _analytics.qoe('startup', contentType: _req.contentType, contentId: _req.contentId, metadata: {'ms': DateTime.now().difference(_startedAt!).inMilliseconds});
    }
  }

  void _reportError(String message) {
    final error = classifyPlaybackError(message, _req.streamUrl);
    // A fallback that was in flight has just failed too.
    _flushPendingFailure('failed');

    final master = _master;
    if (error.kind == PlaybackErrorKind.unsupportedFormat && master != null) {
      final failed = _variant ?? _guessFailedVariant(master, error);
      final decision = _quality.fail(_titleKey, master, failed: failed, failedCodec: error.codec);
      final next = decision.next;
      if (next != null) {
        // Not an error screen: switch down, resume, tell the viewer briefly.
        _pendingFailure = _PendingFailure(error: error, decision: decision, position: _position, at: DateTime.now());
        unawaited(_switchToVariant(next, resumeAt: _position, note: 'Playing in ${next.label} — this device cannot decode the higher quality'));
        return;
      }
      _emitPlaybackError(error, outcome: 'failed', failed: decision.failed, decision: decision);
    } else {
      _emitPlaybackError(error, outcome: 'failed', failed: _variant);
    }
    setState(() => _error = error);
    // A stream error is an exit from full screen: back to portrait + system bars.
    unawaited(_fs.exit());
  }

  void _tick() {
    final c = _controller;
    if (c == null || !c.value.isInitialized || !mounted) return;
    final pos = c.value.position;
    final dur = c.value.duration;
    if (pos != _position || dur != _duration) {
      setState(() {
        _position = pos;
        _duration = dur;
      });
    }
    _checkRecovered(c);

    if (_req.isLive) return;
    final sec = pos.inMilliseconds / 1000;

    // Mid-roll breaks
    if (_phase == _Phase.content) {
      for (final b in _ads.breaks) {
        if (!_playedBreaks.contains(b.position) && sec >= b.position && sec < b.position + 2) {
          _playedBreaks.add(b.position);
          _startMidRoll();
          return;
        }
      }
    }

    // Markers
    final introStart = _marker('intro_start')?.positionSeconds ?? 0;
    final introEnd = _marker('intro_end')?.positionSeconds;
    final credits = _marker('credits_start')?.positionSeconds;
    final showSkip = introEnd != null && sec >= introStart && sec < introEnd - 1;
    if (showSkip != _showSkipIntro) setState(() => _showSkipIntro = showSkip);
    if (credits != null && _req.nextEpisode != null && sec >= credits && !_showNextCountdown && _nextTimer == null) _beginNextCountdown();

    // Completion
    if (dur > Duration.zero && pos >= dur - const Duration(seconds: 1) && !_completedReported) {
      _completedReported = true;
      _progress.report(dur, dur);
      _analytics.track('watch_complete', contentType: _req.contentType, contentId: _req.contentId);
      if (_req.nextEpisode != null && !_showNextCountdown) {
        _beginNextCountdown();
      } else {
        // Playback ended with nothing queued: leave full screen.
        unawaited(_fs.exit());
      }
    }
  }

  ContentMarker? _marker(String type) => _req.markers.where((m) => m.type == type).firstOrNull;

  Future<void> _startMidRoll() async {
    final c = _controller;
    await c?.pause();
    _progress.stop();
    if (!mounted) return;
    final res = await ref.read(adsRepositoryProvider).serve('mid_roll', _ads.context, limit: 2);
    final ads = res.ads.where((a) => a.hasPlayableVideo).toList();
    if (!mounted) return;
    if (ads.isEmpty) {
      if (c != null) {
        await c.play();
        _progress.start(() => c.value.position, () => c.value.duration);
      }
      return;
    }
    setState(() {
      _midRollAds = ads;
      _phase = _Phase.midRoll;
    });
  }

  Future<void> _endMidRoll() async {
    if (!mounted) return;
    setState(() => _phase = _Phase.content);
    final c = _controller;
    await c?.play();
    if (!mounted || c == null || !identical(_controller, c)) return;
    _progress.start(() => c.value.position, () => c.value.duration);
  }

  void _beginNextCountdown() {
    _nextCountdown = 10;
    setState(() => _showNextCountdown = true);
    _nextTimer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return;
      if (_nextCountdown <= 1) {
        t.cancel();
        _playNext();
      } else {
        setState(() => _nextCountdown--);
      }
    });
  }

  void _cancelNextCountdown() {
    _nextTimer?.cancel();
    _nextTimer = null;
    setState(() => _showNextCountdown = false);
    // Cancelling after the end of the episode means playback is over.
    if (_completedReported) unawaited(_fs.exit());
  }

  Future<void> _playNext() async {
    final next = _req.nextEpisode;
    if (next == null) return;
    _nextTimer?.cancel();
    _nextTimer = null;
    _showNextCountdown = false;
    await _progress.report(_position, _duration);
    _releaseController();
    if (!mounted) return;
    try {
      final ep = await ref.read(contentRepositoryProvider).episode(next.id);
      if (!mounted) return;
      final code = ep.seasonNumber != null ? 'S${ep.seasonNumber}E${ep.episodeNumber}' : 'E${ep.episodeNumber}';
      _req = PlaybackRequest(
        contentType: 'episode',
        contentId: ep.id,
        title: _req.title,
        subtitle: '$code · ${ep.title}',
        streamUrl: ep.streamUrl ?? next.streamUrl ?? '',
        posterUrl: ep.stillUrl ?? _req.posterUrl,
        durationHint: ep.runtime != null ? ep.runtime! * 60 : null,
        markers: ep.markers,
        subtitles: ep.subtitles,
        nextEpisode: ep.nextEpisode,
        seriesId: _req.seriesId,
        categoryId: _req.categoryId,
      );
      _playedBreaks.clear();
      _activeSubtitle = null;
      // Progress must be reported against the new episode, not the first one.
      _progress = ProgressReporter(_userContent, _req);
      _analytics.track('episode_nav', contentType: 'episode', contentId: ep.id);
      _variant = null;
      _master = await _loadMaster();
      if (!mounted) return;
      setState(() {});
      await _initController(_startUrl());
    } catch (e) {
      if (mounted) _reportError('Could not load the next episode');
    }
  }

  Future<void> _setSubtitle(Subtitle? sub) async {
    _activeSubtitle = sub;
    final c = _controller;
    if (c == null) return;
    if (sub == null) {
      await c.setClosedCaptionFile(null);
    } else {
      final file = ref.read(subtitleFileProvider(sub).future);
      await c.setClosedCaptionFile(file.then((f) => f ?? WebVTTCaptionFile('WEBVTT\n')));
      _analytics.track('cc_toggle', contentType: _req.contentType, contentId: _req.contentId, metadata: {'language': sub.languageCode});
    }
    if (mounted) setState(() {});
  }

  void _toggleControls() {
    setState(() => _controlsVisible = !_controlsVisible);
    if (_controlsVisible) _scheduleHide();
  }

  void _scheduleHide() {
    _hideTimer?.cancel();
    _hideTimer = Timer(const Duration(seconds: 4), () {
      if (mounted && (_controller?.value.isPlaying ?? false)) setState(() => _controlsVisible = false);
    });
  }

  Future<void> _togglePlay() async {
    final c = _controller;
    if (c == null) return;
    if (c.value.isPlaying) {
      await c.pause();
      await _progress.report(c.value.position, c.value.duration);
      _analytics.track('watch_pause', contentType: _req.contentType, contentId: _req.contentId);
    } else {
      await c.play();
      _analytics.track('watch_resume', contentType: _req.contentType, contentId: _req.contentId);
    }
    _scheduleHide();
    setState(() {});
  }

  Future<void> _seekBy(int seconds) async {
    final c = _controller;
    if (c == null || _req.isLive) return;
    var target = c.value.position + Duration(seconds: seconds);
    if (target < Duration.zero) target = Duration.zero;
    if (_duration > Duration.zero && target > _duration) target = _duration;
    await c.seekTo(target);
    _analytics.track(seconds < 0 ? 'watch_rewind' : 'watch_seek', contentType: _req.contentType, contentId: _req.contentId);
    _scheduleHide();
  }

  Future<void> _retry() async {
    setState(() => _error = null);
    await _initController(_currentUrl, resumeFrom: _position.inSeconds);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _hideTimer?.cancel();
    _positionTimer?.cancel();
    _nextTimer?.cancel();
    _noteTimer?.cancel();
    _flushPendingFailure('abandoned');
    // First and unconditionally: no audio may outlive the screen.
    _releaseController(abandon: true);
    _progress.stop();
    unawaited(_analytics.flush());
    WakelockPlus.disable();
    // Whatever state we were in, the app leaves the player in portrait.
    _fs.removeListener(_onFullscreenChanged);
    _fs.dispose();
    unawaited(PlayerFullscreen.restorePortrait());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = _controller;
    final playing = c?.value.isPlaying ?? false;

    Widget stage;
    switch (_phase) {
      case _Phase.loadingAds:
        stage = _loading();
      case _Phase.preRoll:
        stage = _adStage(AdPlayer(ads: _ads.preRoll, context: _ads.context, onFinished: _startContent));
      case _Phase.midRoll:
        stage = _adStage(AdPlayer(ads: _midRollAds, context: _ads.context, onFinished: _endMidRoll));
      case _Phase.content:
        stage = _content(c, playing);
    }

    return PlayerShell(controller: _fs, stage: stage, below: _below());
  }

  bool get _full => _fs.isFullscreen;

  void _close() => leaveScreen(context);

  /// Ads play in the same stage; the full-screen toggle stays available.
  Widget _adStage(Widget ad) => Stack(
    fit: StackFit.expand,
    children: [
      ad,
      Positioned(
        right: 4,
        top: 4,
        child: SafeArea(child: FullscreenToggleButton(controller: _fs)),
      ),
    ],
  );

  Widget _loading() => Stack(
    fit: StackFit.expand,
    children: [
      if (_req.posterUrl != null) Opacity(opacity: 0.35, child: AppImage(_req.posterUrl)),
      const Center(child: CircularProgressIndicator()),
      _backButton(),
    ],
  );

  Widget _backButton() => Positioned(
    left: 8,
    top: 8,
    child: SafeArea(
      child: IconButton(icon: const Icon(Icons.arrow_back_rounded, size: 28), onPressed: _close),
    ),
  );

  /// Portrait-only panel under the 16:9 stage: title, then the error message
  /// with Back/Retry, or the skip-intro / next-episode extras.
  Widget _below() {
    final error = _error;
    if (error != null) {
      return PlayerErrorPanel(error: error, live: _req.isLive, title: _req.title, subtitle: _req.subtitle, onBack: _close, onRetry: error.retryable ? _retry : null);
    }
    final extras = _extras();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _req.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18),
                ),
              ),
              if (_req.isLive) _liveBadge(),
            ],
          ),
          if (_req.subtitle != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                _req.subtitle!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white70, fontSize: 13),
              ),
            ),
          if (extras != null) ...[const SizedBox(height: 16), Align(alignment: Alignment.centerRight, child: extras)],
        ],
      ),
    );
  }

  Widget _liveBadge() => Container(
    margin: const EdgeInsets.only(left: 8),
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
    decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(4)),
    child: const Text('LIVE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800)),
  );

  /// Skip-intro button or next-episode countdown (over the video in full
  /// screen, under it in portrait). Null when neither applies.
  Widget? _extras() {
    if (_showNextCountdown) {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: Colors.black87, borderRadius: BorderRadius.circular(10)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Next episode in $_nextCountdown s', style: const TextStyle(fontSize: 12, color: Colors.white70)),
            Text(_req.nextEpisode?.title ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextButton(onPressed: _cancelNextCountdown, child: const Text('Cancel')),
                FilledButton(onPressed: _playNext, child: const Text('Play now')),
              ],
            ),
          ],
        ),
      );
    }
    if (_showSkipIntro) {
      return FilledButton.tonal(
        onPressed: () {
          final end = _marker('intro_end')?.positionSeconds;
          if (end != null) _controller?.seekTo(Duration(milliseconds: (end * 1000).round()));
          _analytics.track('skip_intro', contentType: _req.contentType, contentId: _req.contentId);
        },
        child: const Text('Skip intro'),
      );
    }
    return null;
  }

  Widget _content(VideoPlayerController? c, bool playing) {
    if (_error != null) {
      // Details and the Back/Retry buttons are in the panel under the stage
      // (portrait; a stream error always leaves full screen).
      return Stack(
        fit: StackFit.expand,
        children: [
          if (_req.posterUrl != null) Opacity(opacity: 0.25, child: AppImage(_req.posterUrl)),
          Center(child: Icon(_error!.kind == PlaybackErrorKind.unsupportedFormat ? Icons.videocam_off_outlined : Icons.error_outline_rounded, size: 48, color: Colors.redAccent)),
          _backButton(),
        ],
      );
    }

    final extras = _full ? _extras() : null;
    // The tap / double-tap detector covers the video only. Controls are a
    // sibling layer, never a descendant: a button inside a double-tap
    // detector competes with it and reacts 300 ms late (the double-tap
    // timeout). Taps on empty control areas fall through to the detector.
    return Stack(
      fit: StackFit.expand,
      children: [
        GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: _toggleControls,
          onDoubleTapDown: (d) {
            final w = MediaQuery.sizeOf(context).width;
            _seekBy(d.localPosition.dx < w / 2 ? -10 : 10);
          },
          child: c != null && _initialized
              ? Center(
                  child: AspectRatio(aspectRatio: c.value.aspectRatio == 0 ? 16 / 9 : c.value.aspectRatio, child: VideoPlayer(c)),
                )
              : const SizedBox.expand(),
        ),
        if (c == null || !_initialized) _loading(),
        if (c != null && _initialized && _activeSubtitle != null)
          Positioned(
            left: 24,
            right: 24,
            bottom: _controlsVisible ? (_full ? 96 : 52) : (_full ? 32 : 12),
            child: ClosedCaption(
              text: c.value.caption.text,
              textStyle: TextStyle(fontSize: _full ? 18 : 14, color: Colors.white, backgroundColor: Colors.black54),
            ),
          ),
        if (_buffering || (c != null && !_initialized)) const Center(child: CircularProgressIndicator()),
        if (_note != null)
          Positioned(
            left: 12,
            right: 12,
            top: _full ? 64 : 52,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(color: Colors.black87, borderRadius: BorderRadius.circular(16)),
                child: Text(_note!, key: const Key('player-note'), style: const TextStyle(fontSize: 12), textAlign: TextAlign.center),
              ),
            ),
          ),
        if (_phase == _Phase.content && _initialized) AdOverlays(context: _ads.context, settings: _ads.overlay, playing: playing && !_controlsVisible),
        if (extras != null) Positioned(right: 24, bottom: 96, child: extras),
        if (_controlsVisible) _controls(c, playing),
      ],
    );
  }

  Widget _controls(VideoPlayerController? c, bool playing) {
    final live = _req.isLive;
    final full = _full;
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.black87, Colors.transparent, Colors.transparent, Colors.black87], stops: [0, 0.25, 0.7, 1]),
      ),
      child: SafeArea(
        // In portrait the stage sits inside the shell's SafeArea already.
        top: full,
        bottom: full,
        left: full,
        right: full,
        child: Column(
          children: [
            Row(
              children: [
                IconButton(icon: const Icon(Icons.arrow_back_rounded, size: 28), onPressed: _close),
                Expanded(
                  child: full
                      ? Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _req.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
                            ),
                            if (_req.subtitle != null)
                              Text(
                                _req.subtitle!,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: Colors.white70, fontSize: 12),
                              ),
                          ],
                        )
                      : const SizedBox.shrink(),
                ),
                if (live && full) Padding(padding: const EdgeInsets.only(right: 8), child: _liveBadge()),
                if (_qualityChoices.isNotEmpty) _qualityMenu(),
                if (_req.subtitles.isNotEmpty)
                  PopupMenuButton<Subtitle?>(
                    tooltip: 'Subtitles',
                    icon: Icon(_activeSubtitle == null ? Icons.closed_caption_off_outlined : Icons.closed_caption_rounded),
                    onSelected: _setSubtitle,
                    itemBuilder: (_) => [
                      const PopupMenuItem<Subtitle?>(value: null, child: Text('Off')),
                      for (final s in _req.subtitles) PopupMenuItem<Subtitle?>(value: s, child: Text(s.languageName)),
                    ],
                  ),
                if (_req.nextEpisode != null) IconButton(tooltip: 'Next episode', icon: const Icon(Icons.skip_next_rounded, size: 28), onPressed: _playNext),
              ],
            ),
            const Spacer(),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (!live) IconButton(iconSize: full ? 36 : 30, icon: const Icon(Icons.replay_10_rounded), onPressed: () => _seekBy(-10)),
                SizedBox(width: full ? 24 : 16),
                IconButton(iconSize: full ? 64 : 48, icon: Icon(playing ? Icons.pause_circle_filled_rounded : Icons.play_circle_fill_rounded), onPressed: _togglePlay),
                SizedBox(width: full ? 24 : 16),
                if (!live) IconButton(iconSize: full ? 36 : 30, icon: const Icon(Icons.forward_10_rounded), onPressed: () => _seekBy(10)),
              ],
            ),
            const Spacer(),
            Padding(
              padding: const EdgeInsets.only(left: 16, right: 4),
              child: Row(
                children: [
                  if (!live && c != null && _initialized) ...[
                    Text(formatDuration(_position), style: const TextStyle(fontSize: 12)),
                    Expanded(
                      child: SliderTheme(
                        data: SliderTheme.of(context).copyWith(trackHeight: 3, thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 6)),
                        child: Slider(
                          value: _duration.inMilliseconds == 0 ? 0 : (_position.inMilliseconds / _duration.inMilliseconds).clamp(0, 1).toDouble(),
                          onChanged: (v) {
                            final target = Duration(milliseconds: (v * _duration.inMilliseconds).round());
                            setState(() => _position = target);
                          },
                          onChangeEnd: (v) {
                            c.seekTo(Duration(milliseconds: (v * _duration.inMilliseconds).round()));
                            _scheduleHide();
                          },
                        ),
                      ),
                    ),
                    Text(formatDuration(_duration), style: const TextStyle(fontSize: 12)),
                  ] else
                    const Spacer(),
                  FullscreenToggleButton(controller: _fs),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A decoder failure waiting for the outcome of its fallback.
class _PendingFailure {
  const _PendingFailure({required this.error, required this.decision, required this.position, required this.at});
  final PlaybackError error;
  final FallbackDecision decision;
  final Duration position;
  final DateTime at;
}

extension _QualityMenu on _PlayerScreenState {
  static const _autoValue = 'auto';

  Widget _qualityMenu() {
    final choices = _qualityChoices;
    final current = _variant == null ? _autoValue : _variant!.uri.toString();
    return PopupMenuButton<String>(
      tooltip: 'Quality',
      icon: const Icon(Icons.tune_rounded),
      initialValue: current,
      onSelected: (value) {
        if (value == _autoValue) {
          unawaited(_switchToVariant(null, manual: true, note: 'Quality: Auto'));
        } else {
          final v = choices.where((c) => c.uri.toString() == value).firstOrNull;
          if (v != null) unawaited(_switchToVariant(v, manual: true, note: 'Quality: ${v.label}'));
        }
        _scheduleHide();
      },
      itemBuilder: (_) => [
        if (_autoAllowed) CheckedPopupMenuItem<String>(value: _autoValue, checked: _variant == null, child: const Text('Auto')),
        for (final v in choices) CheckedPopupMenuItem<String>(value: v.uri.toString(), checked: _variant?.uri == v.uri, child: Text(v.label)),
      ],
    );
  }
}

/// Kept for callers/tests that only need the viewer-facing sentence; the full
/// classification lives in `playback_error.dart`.
class PlayerScreenErrors {
  static String friendlyPlaybackError(String raw, String url) => classifyPlaybackError(raw, url).message;
}
