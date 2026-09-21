import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/channel.dart';
import '../../../models/epg.dart';
import '../../layout/state/layout_providers.dart';
import '../../repositories.dart';
import '../epg/guide_model.dart';
import 'epg_grid.dart';
import 'programme_sheet.dart';

final _channelGuideProvider = FutureProvider.family<List<EpgProgramme>, ({int channelId, String date})>((ref, key) async {
  return ref.watch(epgRepositoryProvider).channel(key.channelId, date: DateTime.parse(key.date));
});

/// TV guide grid, matching the web player's live page: category chips,
/// prev / Now / next paging, sticky channel column and time header, 30-minute
/// columns, a now marker that moves every 30 s, and placeholder blocks for
/// channels without guide data. Every block is tappable.
class GuideScreen extends ConsumerStatefulWidget {
  const GuideScreen({super.key, this.highlightChannelId});

  /// Row to highlight (e.g. the channel being watched).
  final int? highlightChannelId;

  @override
  ConsumerState<GuideScreen> createState() => _GuideScreenState();
}

class _GuideScreenState extends ConsumerState<GuideScreen> {
  final _grid = EpgGridController();
  int? _categoryId;
  DateTime _now = DateTime.now().toUtc();
  late GuideWindow _window = GuideWindow.around(_now);
  Timer? _clock;

  @override
  void initState() {
    super.initState();
    // Now marker and now/past/future styling follow the clock (web: 30 s).
    _clock = Timer.periodic(const Duration(seconds: 30), (_) {
      if (!mounted) return;
      setState(() {
        _now = DateTime.now().toUtc();
        // The window is anchored to the time the guide was opened; only
        // rebuild it if now has drifted past the visible range.
        if (_now.toLocal().isAfter(_window.end)) _window = GuideWindow.around(_now);
      });
    });
  }

  @override
  void dispose() {
    _clock?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final channelsAsync = ref.watch(channelsProvider);
    final guide = ref.watch(epgGuideProvider);
    final categories = ref.watch(categoriesProvider('live')).value ?? const [];

    return Scaffold(
      appBar: AppBar(
        title: const Text('TV Guide'),
        actions: [
          IconButton(tooltip: 'Earlier', icon: const Icon(Icons.chevron_left_rounded), onPressed: () => _grid.scrollHours(-1)),
          TextButton(onPressed: _grid.jumpToNow, child: const Text('Now')),
          IconButton(tooltip: 'Later', icon: const Icon(Icons.chevron_right_rounded), onPressed: () => _grid.scrollHours(1)),
        ],
      ),
      body: Column(
        children: [
          if (categories.isNotEmpty)
            SizedBox(
              height: 44,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                children: [
                  ChoiceChip(label: const Text('All'), selected: _categoryId == null, onSelected: (_) => setState(() => _categoryId = null)),
                  for (final c in categories) ...[
                    const SizedBox(width: 6),
                    ChoiceChip(label: Text(c.name), selected: _categoryId == c.id, onSelected: (_) => setState(() => _categoryId = c.id)),
                  ],
                ],
              ),
            ),
          Expanded(
            child: AsyncView<List<Channel>>(
              value: channelsAsync,
              onRetry: () {
                ref.invalidate(channelsProvider);
                ref.invalidate(epgGuideProvider);
              },
              builder: (all) {
                final channels = _categoryId == null ? all : all.where((c) => c.categoryId == _categoryId).toList();
                if (channels.isEmpty) return const EmptyView(message: 'No channels in this category.', icon: Icons.live_tv_outlined);
                // Guide rows are optional: without them every channel gets placeholders.
                final programmes = buildGuideMap(channels, guide.value ?? const [], _now);
                return EpgGrid(
                  controller: _grid,
                  window: _window,
                  channels: channels,
                  programmes: programmes,
                  now: _now,
                  activeChannelId: widget.highlightChannelId,
                  onChannelTap: (ch) => openCard(context, ref, ch.toCard()),
                  onProgrammeTap: (ch, p) => showProgrammeSheet(context, ref, p, ch),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// Single-channel schedule for a day (long-press a channel in Live TV).
/// Today falls back to placeholder blocks when the channel has no data.
class ChannelGuideScreen extends ConsumerStatefulWidget {
  const ChannelGuideScreen({super.key, required this.channelId, this.channel});
  final int channelId;
  final Channel? channel;

  @override
  ConsumerState<ChannelGuideScreen> createState() => _ChannelGuideScreenState();
}

class _ChannelGuideScreenState extends ConsumerState<ChannelGuideScreen> {
  DateTime _day = DateTime.now();

  @override
  Widget build(BuildContext context) {
    final key = (channelId: widget.channelId, date: DateFormat('yyyy-MM-dd').format(_day));
    final progs = ref.watch(_channelGuideProvider(key));
    final now = DateTime.now().toUtc();
    final fmt = DateFormat.Hm();
    final days = List.generate(7, (i) => DateTime.now().add(Duration(days: i - 1)));
    final ch = widget.channel;
    final isToday = DateUtils.isSameDay(_day, DateTime.now());
    return Scaffold(
      appBar: AppBar(
        title: Text(ch?.name ?? 'Channel guide'),
        actions: [if (ch != null) IconButton(icon: const Icon(Icons.play_arrow_rounded), onPressed: () => openCard(context, ref, ch.toCard()))],
      ),
      body: Column(
        children: [
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                for (final d in days) ...[
                  ChoiceChip(
                    label: Text(DateUtils.isSameDay(d, DateTime.now()) ? 'Today' : DateFormat('EEE d').format(d)),
                    selected: DateUtils.isSameDay(d, _day),
                    onSelected: (_) => setState(() => _day = d),
                  ),
                  const SizedBox(width: 6),
                ],
              ],
            ),
          ),
          Expanded(
            child: AsyncView<List<EpgProgramme>>(
              value: progs,
              onRetry: () => ref.invalidate(_channelGuideProvider(key)),
              builder: (raw) {
                var list = raw.where((p) => p.hasTimes).toList()..sort((a, b) => a.start!.compareTo(b.start!));
                if (list.isEmpty && isToday && ch != null) list = placeholderSchedule(ch, now);
                if (list.isEmpty) return const EmptyView(message: 'No guide data for this day.', icon: Icons.calendar_today_outlined);
                return ListView.builder(
                  itemCount: list.length,
                  itemBuilder: (context, i) {
                    final p = list[i];
                    final airing = p.isAiringAt(now);
                    return ListTile(
                      selected: airing,
                      leading: Text('${fmt.format(p.start!.toLocal())}\n${fmt.format(p.end!.toLocal())}', style: const TextStyle(fontSize: 12, color: Colors.white70)),
                      title: Text(p.title, style: TextStyle(fontWeight: airing ? FontWeight.w700 : FontWeight.w500, color: p.isPlaceholder ? Colors.white60 : null)),
                      subtitle: p.description != null && p.description!.isNotEmpty ? Text(p.description!, maxLines: 1, overflow: TextOverflow.ellipsis) : null,
                      trailing: airing ? const Icon(Icons.sensors_rounded, color: Colors.redAccent, size: 18) : null,
                      onTap: () => showProgrammeSheet(context, ref, p, ch),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
