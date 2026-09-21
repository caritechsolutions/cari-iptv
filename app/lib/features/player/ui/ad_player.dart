import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import '../../../core/widgets/app_image.dart';
import '../../../core/widgets/legal_links.dart';
import '../../../models/ad.dart';
import '../../ads/data/ads_repository.dart';
import '../../repositories.dart';

/// Plays a list of video ads (pre-roll / mid-roll) sequentially with a skip
/// button after `skip_after` seconds, tracking impression, complete, skip and
/// click. Calls [onFinished] when all ads are done or an ad fails.
class AdPlayer extends ConsumerStatefulWidget {
  const AdPlayer({super.key, required this.ads, required this.context, required this.onFinished});
  final List<Ad> ads;
  final AdContext context;
  final VoidCallback onFinished;

  @override
  ConsumerState<AdPlayer> createState() => _AdPlayerState();
}

class _AdPlayerState extends ConsumerState<AdPlayer> with WidgetsBindingObserver {
  int _index = 0;
  VideoPlayerController? _controller;
  int? _impressionId;
  int _elapsed = 0;
  Timer? _tick;
  bool _finished = false;

  Ad get _ad => widget.ads[_index];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  /// Same background rule as content: pause when the app leaves the
  /// foreground; ads resume by themselves when it comes back.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final c = _controller;
    if (c == null || !c.value.isInitialized) return;
    switch (state) {
      case AppLifecycleState.inactive:
      case AppLifecycleState.hidden:
      case AppLifecycleState.paused:
        if (c.value.isPlaying) unawaited(c.pause());
      case AppLifecycleState.resumed:
        if (!_finished && !c.value.isPlaying) unawaited(c.play());
      case AppLifecycleState.detached:
        break;
    }
  }

  Future<void> _load() async {
    _tick?.cancel();
    await _controller?.dispose();
    _controller = null;
    _elapsed = 0;
    _impressionId = null;
    if (_index >= widget.ads.length) return _done();
    final ad = _ad;
    final c = VideoPlayerController.networkUrl(Uri.parse(ad.videoUrl!));
    _controller = c;
    try {
      await c.initialize().timeout(const Duration(seconds: 12));
      if (!mounted || !identical(_controller, c)) return;
      setState(() {});
      await c.play();
      _impressionId = await ref.read(adsRepositoryProvider).impression(ad, widget.context);
      _tick = Timer.periodic(const Duration(seconds: 1), (_) {
        if (!mounted) return;
        setState(() => _elapsed++);
        final v = c.value;
        if (v.isInitialized && v.duration > Duration.zero && v.position >= v.duration - const Duration(milliseconds: 300)) {
          _complete();
        }
      });
      c.addListener(_onValue);
    } catch (_) {
      _next();
    }
  }

  void _onValue() {
    final v = _controller?.value;
    if (v == null) return;
    if (v.hasError) {
      ref.read(adsRepositoryProvider).event(_ad, 'error', impressionId: _impressionId, userId: widget.context.userId);
      _next();
    }
  }

  void _complete() {
    _tick?.cancel();
    ref.read(adsRepositoryProvider).event(_ad, 'complete', impressionId: _impressionId, userId: widget.context.userId);
    _next();
  }

  void _skip() {
    _tick?.cancel();
    ref.read(adsRepositoryProvider).event(_ad, 'skip', impressionId: _impressionId, userId: widget.context.userId);
    _next();
  }

  void _click() {
    final url = _ad.clickUrl;
    if (url == null || url.isEmpty) return;
    ref.read(adsRepositoryProvider).event(_ad, 'click', impressionId: _impressionId, userId: widget.context.userId);
    openExternal(context, url);
  }

  void _next() {
    if (!mounted) return;
    _controller?.removeListener(_onValue);
    _index++;
    if (_index >= widget.ads.length) {
      _done();
    } else {
      _load();
    }
  }

  void _done() {
    if (_finished) return;
    _finished = true;
    _tick?.cancel();
    widget.onFinished();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _tick?.cancel();
    final c = _controller;
    _controller = null;
    if (c != null) {
      c.removeListener(_onValue);
      // Stop the ad's audio before the native player is freed.
      if (c.value.isInitialized) unawaited(c.pause());
      unawaited(c.dispose());
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = _controller;
    final ad = _index < widget.ads.length ? _ad : null;
    final skipAfter = ad?.skipAfter ?? 5;
    final canSkip = _elapsed >= skipAfter;
    final remaining = c != null && c.value.isInitialized ? (c.value.duration - c.value.position).inSeconds : null;

    return Container(
      color: Colors.black,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (c != null && c.value.isInitialized)
            Center(child: AspectRatio(aspectRatio: c.value.aspectRatio == 0 ? 16 / 9 : c.value.aspectRatio, child: GestureDetector(onTap: _click, child: VideoPlayer(c))))
          else
            const Center(child: CircularProgressIndicator()),
          Positioned(
            left: 16,
            top: 16,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(6)),
              child: Text('Ad ${_index + 1} of ${widget.ads.length}${remaining != null ? ' · ${remaining}s' : ''}', style: const TextStyle(fontSize: 12)),
            ),
          ),
          Positioned(
            right: 16,
            bottom: 24,
            child: FilledButton.tonal(
              onPressed: canSkip ? _skip : null,
              child: Text(canSkip ? 'Skip ad' : 'Skip in ${skipAfter - _elapsed}s'),
            ),
          ),
          if (ad?.clickUrl != null)
            Positioned(
              left: 16,
              bottom: 24,
              child: TextButton.icon(onPressed: _click, icon: const Icon(Icons.open_in_new, size: 16), label: const Text('Learn more')),
            ),
        ],
      ),
    );
  }
}

/// Banner and text-scroller overlays shown over live/VOD playback according
/// to `/ads/overlay-settings` timing.
class AdOverlays extends ConsumerStatefulWidget {
  const AdOverlays({super.key, required this.context, required this.settings, required this.playing});
  final AdContext context;
  final OverlaySettings settings;

  /// Overlays only advance while content is playing.
  final bool playing;

  @override
  ConsumerState<AdOverlays> createState() => _AdOverlaysState();
}

class _AdOverlaysState extends ConsumerState<AdOverlays> with SingleTickerProviderStateMixin {
  Timer? _timer;
  int _seconds = 0;
  Ad? _banner;
  Ad? _scroller;
  bool _showBanner = false;
  bool _showScroller = false;
  int _bannerShownAt = -1;
  int _nextBannerAt = 0;
  int _nextScrollerAt = 0;
  int? _bannerImpression;
  // Runs only while a scroller is on screen (no permanent animation while playing).
  late final AnimationController _marquee = AnimationController(vsync: this, duration: const Duration(seconds: 18));

  @override
  void initState() {
    super.initState();
    _nextBannerAt = widget.settings.bannerInitialDelay;
    _nextScrollerAt = widget.settings.scrollerInitialDelay;
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  Future<void> _tick() async {
    if (!widget.playing || !mounted) return;
    _seconds++;
    final s = widget.settings;
    if (s.bannerEnabled && !_showBanner && _seconds >= _nextBannerAt) {
      final res = await ref.read(adsRepositoryProvider).serve('banner', widget.context, limit: 1);
      final ad = res.ads.where((a) => a.imageUrl != null).firstOrNull;
      if (ad != null && mounted) {
        setState(() {
          _banner = ad;
          _showBanner = true;
          _bannerShownAt = _seconds;
        });
        _bannerImpression = await ref.read(adsRepositoryProvider).impression(ad, widget.context);
      }
      _nextBannerAt = _seconds + s.bannerRepeatInterval;
    }
    if (_showBanner && _seconds - _bannerShownAt >= s.bannerDisplayDuration) {
      setState(() => _showBanner = false);
    }
    if (s.scrollerEnabled && !_showScroller && _seconds >= _nextScrollerAt) {
      final res = await ref.read(adsRepositoryProvider).serve('text_scroller', widget.context, limit: 1);
      final ad = res.ads.where((a) => a.scrollText != null && a.scrollText!.isNotEmpty).firstOrNull;
      if (ad != null && mounted) {
        setState(() {
          _scroller = ad;
          _showScroller = true;
        });
        _marquee.repeat();
        await ref.read(adsRepositoryProvider).impression(ad, widget.context);
        Future.delayed(const Duration(seconds: 20), () {
          if (!mounted) return;
          _marquee.stop();
          setState(() => _showScroller = false);
        });
      }
      _nextScrollerAt = _seconds + s.scrollerRepeatInterval;
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _marquee.dispose();
    super.dispose();
  }

  Color _hex(String? v, Color fallback, {double? opacity}) {
    if (v == null) return fallback.withValues(alpha: opacity ?? fallback.a);
    var h = v.replaceFirst('#', '');
    if (h.length == 6) h = 'FF$h';
    final c = Color(int.tryParse(h, radix: 16) ?? fallback.toARGB32());
    return opacity == null ? c : c.withValues(alpha: opacity);
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        if (_showScroller && _scroller != null)
          Positioned(
            left: 0,
            right: 0,
            bottom: 72,
            child: Container(
              height: 32,
              color: _hex(_scroller!.bgColor, Colors.black, opacity: _scroller!.bgOpacity ?? 0.7),
              child: ClipRect(
                child: AnimatedBuilder(
                  animation: _marquee,
                  builder: (context, _) {
                    final width = MediaQuery.sizeOf(context).width;
                    return Transform.translate(
                      offset: Offset(width - _marquee.value * (width * 2.2), 0),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          _scroller!.scrollText!,
                          maxLines: 1,
                          softWrap: false,
                          overflow: TextOverflow.visible,
                          style: TextStyle(
                            color: _hex(_scroller!.textColor, Colors.white),
                            fontSize: switch (_scroller!.fontSize) { 'small' => 12.0, 'large' => 18.0, _ => 14.0 },
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),
          ),
        if (_showBanner && _banner != null)
          Positioned(
            left: 0,
            right: 0,
            bottom: (_banner!.bannerPosition ?? 'bottom').contains('top') ? null : 8,
            top: (_banner!.bannerPosition ?? 'bottom').contains('top') ? 8 : null,
            child: Center(
              child: Stack(
                children: [
                  GestureDetector(
                    onTap: () {
                      if (_banner!.clickUrl != null) {
                        ref.read(adsRepositoryProvider).event(_banner!, 'click', impressionId: _bannerImpression, userId: widget.context.userId);
                        openExternal(context, _banner!.clickUrl!);
                      }
                    },
                    child: ConstrainedBox(
                      constraints: BoxConstraints(maxHeight: 90, maxWidth: MediaQuery.sizeOf(context).width - 32),
                      child: AppImage(_banner!.imageUrl, fit: BoxFit.contain, icon: Icons.image_not_supported_outlined),
                    ),
                  ),
                  Positioned(
                    right: 0,
                    top: 0,
                    child: InkWell(
                      onTap: () => setState(() => _showBanner = false),
                      child: Container(color: Colors.black54, padding: const EdgeInsets.all(2), child: const Icon(Icons.close, size: 14)),
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}
