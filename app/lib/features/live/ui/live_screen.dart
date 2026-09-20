import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/app_image.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/channel.dart';
import '../../../models/epg.dart';
import '../../layout/state/layout_providers.dart';
import '../../shell/ui/app_shell.dart';

/// Live TV tab: channel list with category filter and now/next from the EPG.
class LiveScreen extends ConsumerStatefulWidget {
  const LiveScreen({super.key, this.categoryId, this.title, this.embedded = false});
  final int? categoryId;
  final String? title;
  final bool embedded;

  @override
  ConsumerState<LiveScreen> createState() => _LiveScreenState();
}

class _LiveScreenState extends ConsumerState<LiveScreen> {
  int? _categoryId;
  String _filter = '';

  @override
  void initState() {
    super.initState();
    _categoryId = widget.categoryId;
  }

  @override
  Widget build(BuildContext context) {
    final channels = ref.watch(channelsProvider);
    final guide = ref.watch(epgGuideProvider).value ?? const <EpgChannelSchedule>[];
    final categories = ref.watch(categoriesProvider('live')).value ?? const [];
    final byChannel = {for (final s in guide) s.channelId: s};
    final now = DateTime.now().toUtc();

    final title = widget.title ?? 'Live TV';
    return Scaffold(
      appBar: widget.embedded
          ? AppBar(title: Text(title))
          : TabAppBar(title: title, actions: [IconButton(tooltip: 'TV Guide', icon: const Icon(Icons.calendar_view_day_rounded), onPressed: () => context.push('/guide'))]),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
            child: TextField(
              decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Filter channels', isDense: true),
              onChanged: (v) => setState(() => _filter = v.trim().toLowerCase()),
            ),
          ),
          if (widget.categoryId == null && categories.isNotEmpty)
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
              value: channels,
              onRetry: () => ref.invalidate(channelsProvider),
              builder: (all) {
                final list = all.where((c) => (_categoryId == null || c.categoryId == _categoryId) && (_filter.isEmpty || c.name.toLowerCase().contains(_filter))).toList();
                if (list.isEmpty) return const EmptyView(message: 'No channels found.', icon: Icons.live_tv_outlined);
                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(channelsProvider);
                    ref.invalidate(epgGuideProvider);
                  },
                  child: ListView.builder(
                    itemCount: list.length,
                    itemBuilder: (context, i) {
                      final ch = list[i];
                      final sched = byChannel[ch.id];
                      final nowProg = sched?.nowAt(now);
                      final next = sched?.nextAfter(now);
                      return ChannelTile(channel: ch, now: nowProg, next: next, at: now);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class ChannelTile extends ConsumerWidget {
  const ChannelTile({super.key, required this.channel, this.now, this.next, required this.at});
  final Channel channel;
  final EpgProgramme? now;
  final EpgProgramme? next;
  final DateTime at;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final fmt = DateFormat.Hm();
    return ListTile(
      onTap: () => openCard(context, ref, channel.toCard()),
      onLongPress: () => context.push('/guide/${channel.id}', extra: channel),
      leading: SizedBox(
        width: 64,
        height: 40,
        child: Container(
          decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(6)),
          padding: const EdgeInsets.all(4),
          child: AppImage(channel.logoUrl, fit: BoxFit.contain, icon: Icons.live_tv_outlined),
        ),
      ),
      title: Row(
        children: [
          if (channel.channelNumber != null) Text('${channel.channelNumber}  ', style: const TextStyle(color: Colors.white54, fontSize: 12)),
          Expanded(child: Text(channel.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600))),
          if (channel.isHd) const Padding(padding: EdgeInsets.only(left: 6), child: Text('HD', style: TextStyle(fontSize: 10, color: Colors.white54))),
          if (channel.isRestricted || channel.isAdult) Padding(padding: const EdgeInsets.only(left: 6), child: Icon(channel.isAdult ? Icons.eighteen_up_rating_outlined : Icons.lock_outline, size: 14, color: Colors.white54)),
        ],
      ),
      subtitle: now == null
          ? Text(channel.categoryName ?? 'No programme information', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Now: ${now!.title}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12)),
                if (next != null && next!.start != null)
                  Text('Next ${fmt.format(next!.start!.toLocal())}: ${next!.title}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, color: Colors.white54)),
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: LinearProgressIndicator(value: now!.progressAt(at), minHeight: 2, backgroundColor: Colors.white12),
                ),
              ],
            ),
      trailing: const Icon(Icons.play_arrow_rounded),
    );
  }
}
