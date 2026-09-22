import 'dart:async';

import 'package:clock/clock.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/access_badge.dart';
import '../../../core/widgets/app_image.dart';
import '../../../models/channel.dart';
import '../../../models/epg.dart';
import '../../layout/state/layout_providers.dart';
import '../../live/epg/guide_model.dart';
import '../../live/ui/programme_sheet.dart';

/// Portrait panel under the live player, matching the web live page
/// (`public/assets/js/player/app.js`: playLiveChannel): channel name with the
/// Live badge, "Now Playing" (title, `HH:mm - HH:mm`, progress bar,
/// description), "Up Next" (start time, title), then the channel's schedule
/// for the guide window as a vertical list of blocks (past dimmed, airing
/// highlighted, future normal) auto-scrolled to the airing block.
///
/// Now/next come from the same programme lists as the guide (real rows or
/// placeholder blocks) and refresh by themselves when a block ends. Every
/// block opens the guide's details sheet, so time-shift plugs in here too.
/// A "Channels" button switches channels without leaving the player.
class LiveInfoPanel extends ConsumerStatefulWidget {
  const LiveInfoPanel({super.key, required this.channelId, required this.channelName, this.onSwitchChannel});

  final int channelId;
  final String channelName;

  /// Replaces the stream in the same player; the panel follows [channelId].
  final void Function(Channel channel)? onSwitchChannel;

  @override
  ConsumerState<LiveInfoPanel> createState() => _LiveInfoPanelState();
}

class _LiveInfoPanelState extends ConsumerState<LiveInfoPanel> {
  static const double rowExtent = 60;

  DateTime _now = clock.now().toUtc();
  Timer? _progressTimer;
  Timer? _boundaryTimer;
  final _schedule = ScrollController();
  int? _scrolledForChannel;

  @override
  void initState() {
    super.initState();
    // Progress bars advance every 30 s (web: 30 s), independent of the boundary.
    _progressTimer = Timer.periodic(const Duration(seconds: 30), (_) => _refresh());
  }

  @override
  void didUpdateWidget(LiveInfoPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.channelId != widget.channelId) _refresh();
  }

  @override
  void dispose() {
    _progressTimer?.cancel();
    _boundaryTimer?.cancel();
    _schedule.dispose();
    super.dispose();
  }

  void _refresh() {
    if (!mounted) return;
    setState(() => _now = clock.now().toUtc());
  }

  /// Fires once, just after the airing block ends, so now/next move on
  /// without the viewer doing anything.
  void _armBoundary(EpgProgramme? airing, EpgProgramme? next) {
    final end = airing?.end ?? next?.start;
    if (end == null) return;
    final wait = end.difference(_now) + const Duration(milliseconds: 500);
    if (_boundaryTimer != null && _boundaryTimer!.isActive && _boundaryAt == end) return;
    _boundaryTimer?.cancel();
    _boundaryAt = end;
    _boundaryTimer = Timer(wait.isNegative ? Duration.zero : wait, _refresh);
  }

  DateTime? _boundaryAt;

  /// Put the airing block at the top of the schedule list on open and after
  /// a channel switch.
  void _autoScroll(List<EpgProgramme> blocks, {required bool ready}) {
    // Wait for the guide data: the first frames show placeholder blocks whose
    // airing index differs from the real schedule's.
    if (!ready || _scrolledForChannel == widget.channelId) return;
    _scrolledForChannel = widget.channelId;
    final index = blocks.indexWhere((p) => p.isAiringAt(_now));
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_schedule.hasClients) return;
      final target = (index < 0 ? 0.0 : index * rowExtent).clamp(0.0, _schedule.position.maxScrollExtent);
      _schedule.jumpTo(target);
    });
  }

  @override
  Widget build(BuildContext context) {
    final channelsAsync = ref.watch(channelsProvider);
    final guideAsync = ref.watch(epgGuideProvider);
    final channels = channelsAsync.value ?? const <Channel>[];
    final guide = guideAsync.value ?? const <EpgChannelSchedule>[];
    final ready = !channelsAsync.isLoading && !guideAsync.isLoading;
    final channel = channels.where((c) => c.id == widget.channelId).firstOrNull;
    final programmes = buildGuideMap(channels, guide, _now);
    // Before the channel list arrives, the current channel still gets blocks.
    final mine = programmes[widget.channelId] ?? (channel == null ? placeholderSchedule(Channel.fromJson({'id': widget.channelId, 'name': widget.channelName}), _now) : const <EpgProgramme>[]);
    final nn = nowAndNext(mine, _now);
    _armBoundary(nn.now, nn.next);
    final window = GuideWindow.around(_now);
    final blocks = mine.where(window.contains).toList();
    _autoScroll(blocks, ready: ready);
    final timeFmt = DateFormat.Hm();
    final theme = Theme.of(context);
    final airing = nn.now;

    void openBlock(EpgProgramme p) => showProgrammeSheet(context, ref, p, channel, showWatch: false);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 8, 0),
          child: Row(
            children: [
              Expanded(child: Text(widget.channelName, key: const Key('live-channel-name'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18))),
              const _LiveBadge(),
              if (widget.onSwitchChannel != null && channels.isNotEmpty)
                TextButton.icon(
                  key: const Key('live-channels-button'),
                  onPressed: () => _showChannels(context, channels, programmes),
                  icon: const Icon(Icons.list_rounded, size: 18),
                  label: const Text('Channels'),
                ),
            ],
          ),
        ),
        // Now Playing
        InkWell(
          key: const Key('live-now-playing'),
          onTap: airing == null ? null : () => openBlock(airing),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const _Label('Now Playing'),
                Text(airing?.title ?? 'Live', key: const Key('live-now-title'), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
                if (airing != null) ...[
                  Text('${timeFmt.format(airing.start!.toLocal())} - ${timeFmt.format(airing.end!.toLocal())}', key: const Key('live-now-time'), style: const TextStyle(color: Colors.white60, fontSize: 13)),
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: ClipRRect(borderRadius: BorderRadius.circular(2), child: LinearProgressIndicator(key: const Key('live-now-progress'), value: airing.progressAt(_now), minHeight: 3, backgroundColor: Colors.white12)),
                  ),
                  if (airing.description != null && airing.description!.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(airing.description!, key: const Key('live-now-desc'), maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, fontSize: 13, height: 1.35)),
                    ),
                ],
              ],
            ),
          ),
        ),
        // Up Next
        if (nn.next != null)
          InkWell(
            key: const Key('live-up-next'),
            onTap: () => openBlock(nn.next!),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const _Label('Up Next'),
                      const Spacer(),
                      Text(timeFmt.format(nn.next!.start!.toLocal()), key: const Key('live-next-time'), style: const TextStyle(color: Colors.white60, fontSize: 12)),
                    ],
                  ),
                  Text(nn.next!.title, key: const Key('live-next-title'), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 14)),
                ],
              ),
            ),
          ),
        const Padding(padding: EdgeInsets.fromLTRB(16, 12, 16, 4), child: _Label('Schedule')),
        // Schedule for the guide window
        Expanded(
          child: blocks.isEmpty
              ? const Center(child: Text('No programme information', style: TextStyle(color: Colors.white54)))
              : ListView.builder(
                  key: const Key('live-schedule'),
                  controller: _schedule,
                  itemExtent: rowExtent,
                  itemCount: blocks.length,
                  itemBuilder: (context, i) => _ScheduleRow(programme: blocks[i], now: _now, timeFmt: timeFmt, theme: theme, onTap: () => openBlock(blocks[i])),
                ),
        ),
      ],
    );
  }

  void _showChannels(BuildContext context, List<Channel> channels, Map<int, List<EpgProgramme>> programmes) {
    final index = channels.indexWhere((c) => c.id == widget.channelId);
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (ctx) => FractionallySizedBox(
        heightFactor: 0.75,
        child: _ChannelPicker(
          channels: channels,
          programmes: programmes,
          currentId: widget.channelId,
          initialIndex: index < 0 ? 0 : index,
          now: _now,
          onPick: (ch) {
            Navigator.pop(ctx);
            if (ch.id != widget.channelId) widget.onSwitchChannel?.call(ch);
          },
        ),
      ),
    );
  }
}

class _Label extends StatelessWidget {
  const _Label(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Text(text.toUpperCase(), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, letterSpacing: 0.6, color: Theme.of(context).colorScheme.primary));
}

class _LiveBadge extends StatelessWidget {
  const _LiveBadge();

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(left: 8),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(4)),
        child: const Text('LIVE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800)),
      );
}

/// One block of the schedule list: time column, title, description line;
/// past dimmed, airing highlighted with a progress bar, future normal.
class _ScheduleRow extends StatelessWidget {
  const _ScheduleRow({required this.programme, required this.now, required this.timeFmt, required this.theme, required this.onTap});
  final EpgProgramme programme;
  final DateTime now;
  final DateFormat timeFmt;
  final ThemeData theme;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = programme;
    final airing = p.isAiringAt(now);
    final past = p.isPastAt(now);
    return Opacity(
      opacity: past ? 0.5 : 1,
      child: Material(
        color: airing ? theme.colorScheme.primary.withValues(alpha: 0.12) : Colors.transparent,
        child: InkWell(
          key: Key('live-block-${p.key}'),
          onTap: onTap,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            decoration: BoxDecoration(border: Border(left: BorderSide(color: airing ? theme.colorScheme.primary : Colors.transparent, width: 3), bottom: BorderSide(color: Colors.white.withValues(alpha: 0.06)))),
            child: Row(
              children: [
                SizedBox(
                  width: 48,
                  child: Text('${timeFmt.format(p.start!.toLocal())}\n${timeFmt.format(p.end!.toLocal())}', style: const TextStyle(fontSize: 11, color: Colors.white60, height: 1.4)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(p.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 13, fontWeight: airing ? FontWeight.w700 : FontWeight.w500, color: p.isPlaceholder ? Colors.white70 : Colors.white)),
                      if (p.description != null && p.description!.isNotEmpty) Text(p.description!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, color: Colors.white54)),
                      if (airing) Padding(padding: const EdgeInsets.only(top: 4), child: ClipRRect(borderRadius: BorderRadius.circular(2), child: LinearProgressIndicator(value: p.progressAt(now), minHeight: 2, backgroundColor: Colors.white12))),
                    ],
                  ),
                ),
                if (airing) const Padding(padding: EdgeInsets.only(left: 8), child: Icon(Icons.sensors_rounded, color: Colors.redAccent, size: 16)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Channel list with each channel's airing programme; the current one is
/// highlighted and scrolled into view.
class _ChannelPicker extends StatefulWidget {
  const _ChannelPicker({required this.channels, required this.programmes, required this.currentId, required this.initialIndex, required this.now, required this.onPick});
  final List<Channel> channels;
  final Map<int, List<EpgProgramme>> programmes;
  final int currentId;
  final int initialIndex;
  final DateTime now;
  final void Function(Channel) onPick;

  @override
  State<_ChannelPicker> createState() => _ChannelPickerState();
}

class _ChannelPickerState extends State<_ChannelPicker> {
  static const double extent = 64;
  late final _controller = ScrollController(initialScrollOffset: (widget.initialIndex * extent - extent).clamp(0, double.infinity).toDouble());

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        const Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 8), child: Align(alignment: Alignment.centerLeft, child: Text('Channels', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)))),
        Expanded(
          child: ListView.builder(
            key: const Key('live-channel-picker'),
            controller: _controller,
            itemExtent: extent,
            itemCount: widget.channels.length,
            itemBuilder: (context, i) {
              final ch = widget.channels[i];
              final current = ch.id == widget.currentId;
              final now = nowAndNext(widget.programmes[ch.id] ?? const [], widget.now).now;
              return ListTile(
                key: Key('live-pick-${ch.id}'),
                selected: current,
                selectedTileColor: theme.colorScheme.primary.withValues(alpha: 0.12),
                onTap: () => widget.onPick(ch),
                leading: SizedBox(width: 56, height: 34, child: AppImage(ch.logoUrl, fit: BoxFit.contain, icon: Icons.live_tv_outlined)),
                title: Row(children: [Expanded(child: Text(ch.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: current ? FontWeight.w700 : FontWeight.w600, fontSize: 14))), AccessBadge(ch.toCard())]),
                subtitle: Text(now == null ? (ch.categoryName ?? '') : 'Now: ${now.title}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
                trailing: current ? const Icon(Icons.volume_up_rounded, size: 18) : null,
              );
            },
          ),
        ),
      ],
    );
  }
}
