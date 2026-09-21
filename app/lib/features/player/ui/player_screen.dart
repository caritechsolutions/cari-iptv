import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:video_player/video_player.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

import '../../../core/util/time.dart';
import '../../../core/widgets/app_image.dart';
import '../../../models/ad.dart';
import '../../../models/content_extras.dart';
import '../../repositories.dart';
import '../state/player_support.dart';
import 'ad_player.dart';
import 'playback_request.dart';
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

class _PlayerScreenState extends ConsumerState<PlayerScreen> {
  VideoPlayerController? _controller;
  late PlaybackRequest _req = widget.request;
  _Phase _phase = _Phase.loadingAds;
  PlaybackAds _ads = PlaybackAds.none;
  final Set<double> _playedBreaks = {};
  List<Ad> _midRollAds = const [];

  bool _controlsVisible = true;
  Timer? _hideTimer;
  Timer? _positionTimer;
  String? _error;
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
  late final ProgressReporter _progress = ProgressReporter(ref.read(userContentRepositoryProvider), _req);
  final PlayerFullscreen _fs = PlayerFullscreen();

  @override
  void initState() {
    super.initState();
    _fs.addListener(_onFullscreenChanged);
    WakelockPlus.enable();
    _start();
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
        setState(() => _error = 'Channel not available');
        return;
      }
    }
    try {
      _ads = await ref.read(playbackAdsProvider(_req).future).timeout(const Duration(seconds: 6));
    } catch (_) {
      _ads = PlaybackAds.none;
    }
    if (!mounted) return;
    if (_ads.preRoll.isNotEmpty) {
      setState(() => _phase = _Phase.preRoll);
    } else {
      _startContent();
    }
  }

  Future<void> _startContent() async {
    setState(() => _phase = _Phase.content);
    await _initController(_req.streamUrl, resumeFrom: _req.resumeFromSeconds);
  }

  Future<void> _initController(String url, {int? resumeFrom}) async {
    _controller?.removeListener(_onValue);
    await _controller?.dispose();
    _initialized = false;
    _error = null;
    _startedAt = DateTime.now();
    _startupReported = false;
    _completedReported = false;
    final uri = Uri.tryParse(url);
    if (uri == null || url.isEmpty) {
      setState(() => _error = 'No stream URL');
      return;
    }
    final isHls = url.contains('.m3u8');
    final c = VideoPlayerController.networkUrl(
      uri,
      formatHint: isHls ? VideoFormat.hls : null,
      videoPlayerOptions: VideoPlayerOptions(allowBackgroundPlayback: false, mixWithOthers: false),
    );
    _controller = c;
    c.addListener(_onValue);
    try {
      await c.initialize();
      if (!mounted) return;
      _initialized = true;
      _duration = c.value.duration;
      if (resumeFrom != null && resumeFrom > 0 && !_req.isLive) {
        await c.seekTo(Duration(seconds: resumeFrom));
      }
      final defaultSub = _req.subtitles.where((s) => s.isDefault).firstOrNull;
      if (defaultSub != null) _setSubtitle(defaultSub);
      await c.play();
      _progress.start(() => c.value.position, () => c.value.duration);
      _positionTimer = Timer.periodic(const Duration(milliseconds: 500), (_) => _tick());
      ref.read(analyticsRepositoryProvider).track('watch_start', contentType: _req.contentType, contentId: _req.contentId, metadata: {'live': _req.isLive});
      _scheduleHide();
      setState(() {});
    } catch (e) {
      if (!mounted) return;
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
      ref.read(analyticsRepositoryProvider).qoe(v.isBuffering ? 'buffer_start' : 'buffer_end', contentType: _req.contentType, contentId: _req.contentId);
    }
    if (v.isPlaying && !_startupReported && _startedAt != null) {
      _startupReported = true;
      ref.read(analyticsRepositoryProvider).qoe('startup', contentType: _req.contentType, contentId: _req.contentId, metadata: {'ms': DateTime.now().difference(_startedAt!).inMilliseconds});
    }
  }

  void _reportError(String message) {
    final friendly = PlayerScreenErrors.friendlyPlaybackError(message, _req.streamUrl);
    setState(() => _error = friendly);
    // A stream error is an exit from full screen: back to portrait + system bars.
    unawaited(_fs.exit());
    ref.read(analyticsRepositoryProvider).qoe('playback_error', contentType: _req.contentType, contentId: _req.contentId, metadata: {'message': message, 'url_scheme': Uri.tryParse(_req.streamUrl)?.scheme});
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
      ref.read(analyticsRepositoryProvider).track('watch_complete', contentType: _req.contentType, contentId: _req.contentId);
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
    if (c != null) _progress.start(() => c.value.position, () => c.value.duration);
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
    _progress.stop();
    _positionTimer?.cancel();
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
      ref.read(analyticsRepositoryProvider).track('episode_nav', contentType: 'episode', contentId: ep.id);
      setState(() {});
      await _initController(_req.streamUrl);
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
      ref.read(analyticsRepositoryProvider).track('cc_toggle', contentType: _req.contentType, contentId: _req.contentId, metadata: {'language': sub.languageCode});
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
      ref.read(analyticsRepositoryProvider).track('watch_pause', contentType: _req.contentType, contentId: _req.contentId);
    } else {
      await c.play();
      ref.read(analyticsRepositoryProvider).track('watch_resume', contentType: _req.contentType, contentId: _req.contentId);
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
    ref.read(analyticsRepositoryProvider).track(seconds < 0 ? 'watch_rewind' : 'watch_seek', contentType: _req.contentType, contentId: _req.contentId);
    _scheduleHide();
  }

  Future<void> _retry() async {
    setState(() => _error = null);
    await _initController(_req.streamUrl, resumeFrom: _position.inSeconds);
  }

  @override
  void dispose() {
    _hideTimer?.cancel();
    _positionTimer?.cancel();
    _nextTimer?.cancel();
    final c = _controller;
    if (c != null && c.value.isInitialized && !_req.isLive) {
      final pos = c.value.position;
      final dur = c.value.duration;
      if (!_completedReported && pos.inSeconds > 0) {
        unawaited(_progress.report(pos, dur));
        ref.read(analyticsRepositoryProvider).track('watch_abandon', contentType: _req.contentType, contentId: _req.contentId, metadata: {'position': pos.inSeconds});
      }
    }
    _progress.stop();
    c?.removeListener(_onValue);
    c?.dispose();
    unawaited(ref.read(analyticsRepositoryProvider).flush());
    WakelockPlus.disable();
    // Unconditional: whatever state we were in, the app leaves the player in portrait.
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

    return PlayerShell(
      controller: _fs,
      stage: stage,
      below: _below(),
    );
  }

  bool get _full => _fs.isFullscreen;

  void _close() => context.canPop() ? context.pop() : context.go('/home');

  /// Ads play in the same stage; the full-screen toggle stays available.
  Widget _adStage(Widget ad) => Stack(
        fit: StackFit.expand,
        children: [
          ad,
          Positioned(right: 4, top: 4, child: SafeArea(child: FullscreenToggleButton(controller: _fs))),
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
        child: SafeArea(child: IconButton(icon: const Icon(Icons.arrow_back_rounded, size: 28), onPressed: _close)),
      );

  /// Portrait-only panel under the 16:9 stage: title, then the error message
  /// with Back/Retry, or the skip-intro / next-episode extras.
  Widget _below() {
    final extras = _extras();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(_req.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18))),
              if (_req.isLive) _liveBadge(),
            ],
          ),
          if (_req.subtitle != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(_req.subtitle!, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, fontSize: 13))),
          if (_error != null) ...[
            const SizedBox(height: 16),
            Text(_req.isLive ? 'This channel cannot be played right now.' : 'This video cannot be played right now.', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            Text(_error!, style: const TextStyle(color: Colors.white54, fontSize: 12), maxLines: 4, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 14),
            Row(
              children: [
                OutlinedButton(onPressed: _close, child: const Text('Back')),
                const SizedBox(width: 12),
                FilledButton.icon(onPressed: _retry, icon: const Icon(Icons.refresh), label: const Text('Retry')),
              ],
            ),
          ] else if (extras != null) ...[
            const SizedBox(height: 16),
            Align(alignment: Alignment.centerRight, child: extras),
          ],
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
          ref.read(analyticsRepositoryProvider).track('skip_intro', contentType: _req.contentType, contentId: _req.contentId);
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
          const Center(child: Icon(Icons.error_outline_rounded, size: 48, color: Colors.redAccent)),
          _backButton(),
        ],
      );
    }

    final extras = _full ? _extras() : null;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: _toggleControls,
      onDoubleTapDown: (d) {
        final w = MediaQuery.sizeOf(context).width;
        _seekBy(d.localPosition.dx < w / 2 ? -10 : 10);
      },
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (c != null && _initialized)
            Center(child: AspectRatio(aspectRatio: c.value.aspectRatio == 0 ? 16 / 9 : c.value.aspectRatio, child: VideoPlayer(c)))
          else
            _loading(),
          if (c != null && _initialized && _activeSubtitle != null)
            Positioned(
              left: 24,
              right: 24,
              bottom: _controlsVisible ? (_full ? 96 : 52) : (_full ? 32 : 12),
              child: ClosedCaption(text: c.value.caption.text, textStyle: TextStyle(fontSize: _full ? 18 : 14, color: Colors.white, backgroundColor: Colors.black54)),
            ),
          if (_buffering || (c != null && !_initialized)) const Center(child: CircularProgressIndicator()),
          if (_phase == _Phase.content && _initialized)
            AdOverlays(context: _ads.context, settings: _ads.overlay, playing: playing && !_controlsVisible),
          if (extras != null) Positioned(right: 24, bottom: 96, child: extras),
          if (_controlsVisible) _controls(c, playing),
        ],
      ),
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
                            Text(_req.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                            if (_req.subtitle != null) Text(_req.subtitle!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, fontSize: 12)),
                          ],
                        )
                      : const SizedBox.shrink(),
                ),
                if (live && full) Padding(padding: const EdgeInsets.only(right: 8), child: _liveBadge()),
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
                if (_req.nextEpisode != null)
                  IconButton(tooltip: 'Next episode', icon: const Icon(Icons.skip_next_rounded, size: 28), onPressed: _playNext),
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

/// Classifies native player errors into user-facing text. Cleartext (http://)
/// blocks come from the per-brand network security policy (Android) or ATS (iOS).
class PlayerScreenErrors {
  static String friendlyPlaybackError(String raw, String url) {
    final lower = raw.toLowerCase();
    final host = Uri.tryParse(url)?.host ?? '';
    final isHttp = url.startsWith('http://');
    if (lower.contains('cleartext') || lower.contains('clear text') || lower.contains('app transport security') || (isHttp && (lower.contains('not permitted') || lower.contains('-1022')))) {
      return 'This stream uses an insecure http:// address ($host) that this app is not allowed to play. Ask your provider for an https:// stream.';
    }
    if (lower.contains('certificate') || lower.contains('ssl') || lower.contains('tls') || lower.contains('trust anchor')) {
      return 'The stream server ($host) has an invalid security certificate.';
    }
    if (lower.contains('404') || lower.contains('not found')) return 'The stream was not found on the server ($host).';
    if (lower.contains('403') || lower.contains('401') || lower.contains('forbidden')) return 'The stream server ($host) refused access.';
    if (lower.contains('unable to connect') || lower.contains('failed to connect') || lower.contains('unknownhost') || lower.contains('timeout')) {
      return 'Could not connect to the stream server ($host). Check your connection and try again.';
    }
    return raw;
  }
}

