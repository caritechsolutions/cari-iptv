import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/app_image.dart';
import '../../../models/channel.dart';
import '../../../models/epg.dart';
import '../epg/guide_model.dart';

/// Drives the grid from the outside (prev / Now / next paging).
class EpgGridController {
  _EpgGridState? _state;

  /// Scroll the timeline by [hours] (negative = back), like the web player's
  /// prev/next buttons (one hour per press).
  void scrollHours(double hours) => _state?._scrollBy(hours * 60 * pxPerMinute);

  /// Put the now marker a third of the way into the viewport.
  void jumpToNow() => _state?._jumpToNow(animate: true);

  double? get horizontalOffset => _state?._h.hasClients == true ? _state!._h.offset : null;
}

/// Programme grid: sticky channel column on the left, sticky 30-minute time
/// header on top, horizontal scroll for time and vertical scroll for
/// channels, a now-marker line, and tappable programme blocks (placeholders
/// included). Opens with the current time in view.
class EpgGrid extends StatefulWidget {
  const EpgGrid({
    super.key,
    required this.window,
    required this.channels,
    required this.programmes,
    required this.now,
    this.activeChannelId,
    this.onChannelTap,
    this.onProgrammeTap,
    this.controller,
    this.channelWidth = 112,
    this.rowHeight = 56,
    this.headerHeight = 28,
  });

  final GuideWindow window;
  final List<Channel> channels;

  /// Programmes per channel id, real or placeholder, sorted by start.
  final Map<int, List<EpgProgramme>> programmes;
  final DateTime now;
  final int? activeChannelId;
  final void Function(Channel channel)? onChannelTap;
  final void Function(Channel channel, EpgProgramme programme)? onProgrammeTap;
  final EpgGridController? controller;
  final double channelWidth;
  final double rowHeight;
  final double headerHeight;

  @override
  State<EpgGrid> createState() => _EpgGridState();
}

class _EpgGridState extends State<EpgGrid> {
  final _h = ScrollController();
  final _hHeader = ScrollController();
  final _vLeft = ScrollController();
  final _vRight = ScrollController();
  bool _syncingV = false;
  double _viewportWidth = 0;
  bool _positioned = false;

  @override
  void initState() {
    super.initState();
    widget.controller?._state = this;
    _h.addListener(_syncHeader);
    _vLeft.addListener(() => _syncVertical(_vLeft, _vRight));
    _vRight.addListener(() => _syncVertical(_vRight, _vLeft));
  }

  @override
  void didUpdateWidget(EpgGrid oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller?._state = null;
      widget.controller?._state = this;
    }
  }

  @override
  void dispose() {
    widget.controller?._state = null;
    _h.dispose();
    _hHeader.dispose();
    _vLeft.dispose();
    _vRight.dispose();
    super.dispose();
  }

  void _syncHeader() {
    if (_hHeader.hasClients && _hHeader.offset != _h.offset) _hHeader.jumpTo(_h.offset);
  }

  void _syncVertical(ScrollController from, ScrollController to) {
    if (_syncingV || !to.hasClients || !from.hasClients) return;
    _syncingV = true;
    if (to.offset != from.offset) to.jumpTo(from.offset);
    _syncingV = false;
  }

  double get _nowX => widget.window.xFor(widget.now);

  double _clampX(double x) {
    if (!_h.hasClients) return x;
    return x.clamp(_h.position.minScrollExtent, _h.position.maxScrollExtent);
  }

  void _scrollBy(double px) {
    if (!_h.hasClients) return;
    _h.animateTo(_clampX(_h.offset + px), duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
  }

  void _jumpToNow({bool animate = false}) {
    if (!_h.hasClients) return;
    final target = _clampX(_nowX - _viewportWidth / 3);
    if (animate) {
      _h.animateTo(target, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
    } else {
      _h.jumpTo(target);
    }
  }

  @override
  Widget build(BuildContext context) {
    final window = widget.window;
    final timeFmt = DateFormat.Hm();
    final theme = Theme.of(context);
    final border = BorderSide(color: Colors.white.withValues(alpha: 0.08));

    return LayoutBuilder(
      builder: (context, constraints) {
        _viewportWidth = constraints.maxWidth - widget.channelWidth;
        if (!_positioned) {
          _positioned = true;
          // Current time in view on open.
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) _jumpToNow();
          });
        }
        return Column(
          children: [
            // Sticky time header
            SizedBox(
              height: widget.headerHeight,
              child: Row(
                children: [
                  Container(width: widget.channelWidth, decoration: BoxDecoration(border: Border(right: border, bottom: border))),
                  Expanded(
                    child: SingleChildScrollView(
                      controller: _hHeader,
                      scrollDirection: Axis.horizontal,
                      physics: const NeverScrollableScrollPhysics(),
                      child: SizedBox(
                        width: window.width,
                        height: widget.headerHeight,
                        child: Stack(
                          children: [
                            Positioned.fill(child: Container(decoration: BoxDecoration(border: Border(bottom: border)))),
                            for (final slot in window.slots)
                              Positioned(
                                left: window.xFor(slot),
                                top: 0,
                                bottom: 0,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6),
                                  decoration: BoxDecoration(border: Border(left: border)),
                                  alignment: Alignment.centerLeft,
                                  child: Text(timeFmt.format(slot), style: const TextStyle(fontSize: 11, color: Colors.white70)),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            // Body: sticky channel column + scrolling timeline
            Expanded(
              child: Row(
                children: [
                  SizedBox(
                    width: widget.channelWidth,
                    child: ListView.builder(
                      controller: _vLeft,
                      physics: const ClampingScrollPhysics(),
                      itemExtent: widget.rowHeight,
                      itemCount: widget.channels.length,
                      itemBuilder: (context, i) => _channelCell(widget.channels[i], theme, border),
                    ),
                  ),
                  Expanded(
                    child: SingleChildScrollView(
                      controller: _h,
                      scrollDirection: Axis.horizontal,
                      child: SizedBox(
                        width: window.width,
                        child: Stack(
                          children: [
                            ListView.builder(
                              controller: _vRight,
                              physics: const ClampingScrollPhysics(),
                              itemExtent: widget.rowHeight,
                              itemCount: widget.channels.length,
                              itemBuilder: (context, i) => _timelineRow(widget.channels[i], theme, border, timeFmt),
                            ),
                            // Now marker
                            Positioned(
                              left: _nowX - 1,
                              top: 0,
                              bottom: 0,
                              child: IgnorePointer(
                                child: Container(key: const Key('epg-now-line'), width: 2, color: theme.colorScheme.error),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _channelCell(Channel ch, ThemeData theme, BorderSide border) {
    final active = ch.id == widget.activeChannelId;
    return InkWell(
      onTap: widget.onChannelTap == null ? null : () => widget.onChannelTap!(ch),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8),
        decoration: BoxDecoration(
          color: active ? theme.colorScheme.primary.withValues(alpha: 0.18) : theme.colorScheme.surface,
          border: Border(right: border, bottom: border),
        ),
        child: Row(
          children: [
            SizedBox(width: 34, height: 22, child: AppImage(ch.logoUrl, fit: BoxFit.contain, icon: Icons.live_tv_outlined)),
            const SizedBox(width: 6),
            Expanded(child: Text(ch.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11, fontWeight: active ? FontWeight.w700 : FontWeight.w600))),
          ],
        ),
      ),
    );
  }

  Widget _timelineRow(Channel ch, ThemeData theme, BorderSide border, DateFormat timeFmt) {
    final window = widget.window;
    final progs = widget.programmes[ch.id] ?? const <EpgProgramme>[];
    final active = ch.id == widget.activeChannelId;
    return Container(
      decoration: BoxDecoration(color: active ? theme.colorScheme.primary.withValues(alpha: 0.06) : null, border: Border(bottom: border)),
      child: Stack(
        children: [
          for (final p in progs)
            if (window.contains(p))
              if (window.blockFor(p) case final b when b.width >= 3)
                Positioned(
                  left: b.left,
                  width: b.width,
                  top: 2,
                  bottom: 2,
                  child: _ProgrammeBlock(
                    programme: p,
                    now: widget.now,
                    timeFmt: timeFmt,
                    hScroll: _h,
                    left: b.left,
                    width: b.width,
                    onTap: widget.onProgrammeTap == null ? null : () => widget.onProgrammeTap!(ch, p),
                  ),
                ),
        ],
      ),
    );
  }
}

/// A programme block. Its title and time stay pinned inside the visible part
/// of the block while the timeline scrolls: when the block's start is off the
/// left edge, the text shifts right by the hidden amount (keeping at least
/// [_minTextWidth] of the block for it), so the airing programme's name is
/// readable on a phone-width screen.
class _ProgrammeBlock extends StatelessWidget {
  const _ProgrammeBlock({required this.programme, required this.now, required this.timeFmt, required this.hScroll, required this.left, required this.width, this.onTap});
  final EpgProgramme programme;
  final DateTime now;
  final DateFormat timeFmt;

  /// Horizontal timeline scroll; the block listens to it to pin its text.
  final ScrollController hScroll;

  /// Block position and width in timeline pixels.
  final double left;
  final double width;
  final VoidCallback? onTap;

  static const double _padding = 6;
  static const double _minTextWidth = 48;

  /// How far the text moves right so it starts at the visible edge.
  double _textShift() {
    final offset = hScroll.hasClients ? hScroll.offset : 0.0;
    final maxShift = (width - _padding * 2 - _minTextWidth).clamp(0.0, double.infinity);
    return (offset - left).clamp(0.0, maxShift);
  }

  @override
  Widget build(BuildContext context) {
    final p = programme;
    final theme = Theme.of(context);
    final airing = p.isAiringAt(now);
    final past = p.isPastAt(now);
    return Opacity(
      opacity: past ? 0.5 : 1,
      child: Material(
        color: airing ? theme.colorScheme.primary.withValues(alpha: 0.12) : theme.colorScheme.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(4),
          side: BorderSide(color: airing ? theme.colorScheme.primary : Colors.white.withValues(alpha: 0.1)),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          key: Key('epg-block-${p.key}'),
          onTap: onTap,
          child: ListenableBuilder(
            listenable: hScroll,
            builder: (context, child) => Padding(
              padding: EdgeInsets.fromLTRB(_padding + _textShift(), 3, _padding, 3),
              child: child,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(p.title, key: Key('epg-title-${p.key}'), maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: p.isPlaceholder ? Colors.white60 : Colors.white)),
                Text(timeFmt.format(p.start!.toLocal()), maxLines: 1, style: const TextStyle(fontSize: 10, color: Colors.white54)),
                if (airing) ...[
                  const Spacer(),
                  ClipRRect(borderRadius: BorderRadius.circular(2), child: LinearProgressIndicator(value: p.progressAt(now), minHeight: 2, backgroundColor: Colors.white12)),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
