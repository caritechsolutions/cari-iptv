import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/widgets/cards.dart';
import '../../../models/channel.dart';
import '../../../models/epg.dart';

/// Details of one guide block (real programme or placeholder): exact start and
/// end, duration, category, description, and "Watch now" while it airs.
///
/// This is the tap target for every block. Time-shifting (watch from this
/// block's start) plugs in here: the block already carries exact
/// `programme.start` / `programme.end`, so a future "Watch from HH:mm"
/// action only needs a stream URL with a start offset — the guide itself
/// does not change.
void showProgrammeSheet(BuildContext context, WidgetRef ref, EpgProgramme programme, Channel? channel) {
  final p = programme;
  final now = DateTime.now().toUtc();
  final dayFmt = DateFormat('EEE d MMM');
  final timeFmt = DateFormat.Hm();
  final airing = p.isAiringAt(now);
  final when = p.hasTimes ? '${dayFmt.format(p.start!.toLocal())} · ${timeFmt.format(p.start!.toLocal())} – ${timeFmt.format(p.end!.toLocal())} · ${p.duration.inMinutes} min' : null;

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
          Text([if (channel != null) channel.name, if (p.category != null && p.category!.isNotEmpty) p.category!].join(' · '), style: const TextStyle(color: Colors.white54, fontSize: 12)),
          if (when != null) Padding(padding: const EdgeInsets.only(top: 2), child: Text(when, key: const Key('programme-when'), style: const TextStyle(color: Colors.white70, fontSize: 13))),
          if (p.description != null && p.description!.isNotEmpty) ...[const SizedBox(height: 12), Text(p.description!, style: const TextStyle(color: Colors.white70, height: 1.4))],
          if (p.isPlaceholder) ...[
            const SizedBox(height: 8),
            const Text('No programme information for this slot.', style: TextStyle(color: Colors.white38, fontSize: 12)),
          ],
          if (channel != null && airing) ...[
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: () {
                Navigator.pop(ctx);
                openCard(context, ref, channel.toCard());
              },
              icon: const Icon(Icons.play_arrow_rounded),
              label: const Text('Watch now'),
            ),
          ],
        ],
      ),
    ),
  );
}
