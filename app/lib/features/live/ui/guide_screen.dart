import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/app_image.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/channel.dart';
import '../../../models/epg.dart';
import '../../layout/state/layout_providers.dart';
import '../../repositories.dart';

final _guideByDateProvider = FutureProvider.family<List<EpgChannelSchedule>, String>((ref, date) async {
  return ref.watch(epgRepositoryProvider).guide(date: DateTime.parse(date));
});

final _channelGuideProvider = FutureProvider.family<List<EpgProgramme>, ({int channelId, String date})>((ref, key) async {
  return ref.watch(epgRepositoryProvider).channel(key.channelId, date: DateTime.parse(key.date));
});

/// Full TV guide: day picker + per-channel horizontal programme strips.
class GuideScreen extends ConsumerStatefulWidget {
  const GuideScreen({super.key});

  @override
  ConsumerState<GuideScreen> createState() => _GuideScreenState();
}

class _GuideScreenState extends ConsumerState<GuideScreen> {
  DateTime _day = DateTime.now();

  String get _dateKey => DateFormat('yyyy-MM-dd').format(_day);

  @override
  Widget build(BuildContext context) {
    final channels = ref.watch(channelsProvider).value ?? const <Channel>[];
    final byId = {for (final c in channels) c.id: c};
    final guide = ref.watch(_guideByDateProvider(_dateKey));
    final now = DateTime.now().toUtc();
    final days = List.generate(7, (i) => DateTime.now().add(Duration(days: i - 1)));
    final timeFmt = DateFormat.Hm();

    return Scaffold(
      appBar: AppBar(title: const Text('TV Guide')),
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
            child: AsyncView<List<EpgChannelSchedule>>(
              value: guide,
              onRetry: () => ref.invalidate(_guideByDateProvider(_dateKey)),
              isEmpty: (g) => g.isEmpty,
              emptyMessage: 'No guide data for this day.',
              emptyIcon: Icons.calendar_today_outlined,
              builder: (g) => ListView.builder(
                itemCount: g.length,
                itemBuilder: (context, i) {
                  final s = g[i];
                  final ch = byId[s.channelId];
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ListTile(
                        dense: true,
                        leading: SizedBox(width: 48, height: 30, child: AppImage(ch?.logoUrl, fit: BoxFit.contain, icon: Icons.live_tv_outlined)),
                        title: Text(ch?.name ?? s.channelName, style: const TextStyle(fontWeight: FontWeight.w600)),
                        trailing: ch == null ? null : const Icon(Icons.play_arrow_rounded),
                        onTap: ch == null ? null : () => openCard(context, ref, ch.toCard()),
                      ),
                      SizedBox(
                        height: 72,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          itemCount: s.programmes.length,
                          separatorBuilder: (_, _) => const SizedBox(width: 6),
                          itemBuilder: (context, j) {
                            final p = s.programmes[j];
                            final airing = p.isAiringAt(now);
                            return InkWell(
                              onTap: () => _showProgramme(context, p, ch),
                              child: Container(
                                width: 170,
                                padding: const EdgeInsets.all(8),
                                decoration: BoxDecoration(
                                  color: airing ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.25) : Theme.of(context).colorScheme.surface,
                                  borderRadius: BorderRadius.circular(8),
                                  border: airing ? Border.all(color: Theme.of(context).colorScheme.primary) : null,
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(p.start != null && p.end != null ? '${timeFmt.format(p.start!.toLocal())} – ${timeFmt.format(p.end!.toLocal())}' : '', style: const TextStyle(fontSize: 11, color: Colors.white54)),
                                    const SizedBox(height: 2),
                                    Expanded(child: Text(p.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600))),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                      const SizedBox(height: 8),
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showProgramme(BuildContext context, EpgProgramme p, Channel? ch) {
    final fmt = DateFormat('EEE d MMM, HH:mm');
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(p.title, style: Theme.of(ctx).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text([if (ch != null) ch.name, if (p.start != null) fmt.format(p.start!.toLocal()), if (p.category != null) p.category!].join(' · '), style: const TextStyle(color: Colors.white54, fontSize: 12)),
            if (p.description != null && p.description!.isNotEmpty) ...[const SizedBox(height: 12), Text(p.description!, style: const TextStyle(color: Colors.white70, height: 1.4))],
            if (ch != null && p.isAiringAt(DateTime.now().toUtc())) ...[
              const SizedBox(height: 16),
              Consumer(builder: (c2, ref, _) => FilledButton.icon(onPressed: () { Navigator.pop(ctx); openCard(context, ref, ch.toCard()); }, icon: const Icon(Icons.play_arrow_rounded), label: const Text('Watch now'))),
            ],
          ],
        ),
      ),
    );
  }
}

/// Single-channel guide (long-press a channel).
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
                  ChoiceChip(label: Text(DateUtils.isSameDay(d, DateTime.now()) ? 'Today' : DateFormat('EEE d').format(d)), selected: DateUtils.isSameDay(d, _day), onSelected: (_) => setState(() => _day = d)),
                  const SizedBox(width: 6),
                ],
              ],
            ),
          ),
          Expanded(
            child: AsyncView<List<EpgProgramme>>(
              value: progs,
              onRetry: () => ref.invalidate(_channelGuideProvider(key)),
              isEmpty: (l) => l.isEmpty,
              emptyMessage: 'No programmes listed for this day.',
              builder: (list) => ListView.builder(
                itemCount: list.length,
                itemBuilder: (context, i) {
                  final p = list[i];
                  final airing = p.isAiringAt(now);
                  return ListTile(
                    selected: airing,
                    leading: Text(p.start != null ? fmt.format(p.start!.toLocal()) : '', style: const TextStyle(fontSize: 13)),
                    title: Text(p.title, style: TextStyle(fontWeight: airing ? FontWeight.w700 : FontWeight.w500)),
                    subtitle: p.description != null && p.description!.isNotEmpty ? Text(p.description!, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)) : null,
                    trailing: airing ? const Text('ON NOW', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Colors.redAccent)) : null,
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}
