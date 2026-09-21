import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/util/json.dart';
import '../../../core/widgets/app_image.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../shell/ui/app_shell.dart';
import '../../../core/widgets/legal_links.dart';
import '../../../models/layout.dart';
import '../../../models/media_card.dart';
import '../../auth/state/auth_notifier.dart';
import '../../player/ui/playback_request.dart';
import '../state/layout_providers.dart';

/// Maps a layout section to its widget. Unknown types render nothing.
Widget buildSection(LayoutSection section) {
  switch (section.type) {
    case 'hero_slideshow':
      return HeroSlideshow(section: section);
    case 'content_row':
      return ContentRowSection(section: section);
    case 'channel_grid':
      return ChannelGridSection(section: section);
    case 'continue_watching':
      return ContinueWatchingSection(section: section);
    case 'category_grid':
      return CategoryGridSection(section: section);
    case 'banner':
      return BannerSection(section: section);
    case 'spotlight':
      return SpotlightSection(section: section);
    case 'text_divider':
      return TextDividerSection(section: section);
    case 'recommended_for_you':
    case 'because_you_watched':
    case 'trending_now':
    case 'top_picks':
    case 'hidden_gems':
      return RecommendationSection(section: section);
    case 'live_now':
      return LiveNowSection(section: section);
    case 'epg_schedule':
      return EpgScheduleSection(section: section);
    case 'packages_list':
      return PackagesListSection(section: section);
    default:
      return const SizedBox.shrink();
  }
}

// -----------------------------------------------------------------------------

class HeroSlideshow extends ConsumerStatefulWidget {
  const HeroSlideshow({super.key, required this.section});
  final LayoutSection section;

  @override
  ConsumerState<HeroSlideshow> createState() => _HeroSlideshowState();
}

class _HeroSlideshowState extends ConsumerState<HeroSlideshow> {
  final _controller = PageController();
  Timer? _timer;
  int _index = 0;

  @override
  void initState() {
    super.initState();
    final s = widget.section.settings;
    if (asBool(s['auto_rotate'], true) && widget.section.cards.length > 1) {
      final interval = asInt(s['interval'], 6).clamp(3, 60);
      _timer = Timer.periodic(Duration(seconds: interval), (_) {
        if (!mounted || !_controller.hasClients) return;
        _index = (_index + 1) % widget.section.cards.length;
        _controller.animateToPage(_index, duration: const Duration(milliseconds: 400), curve: Curves.easeOut);
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cards = widget.section.cards;
    if (cards.isEmpty) return const SizedBox.shrink();
    final height = switch (asString(widget.section.settings['height'], 'medium')) {
      'small' => 200.0,
      'large' => 320.0,
      _ => 260.0,
    };
    final showPlay = asBool(widget.section.settings['show_play_button'], true);
    return SizedBox(
      height: height,
      child: PageView.builder(
        controller: _controller,
        itemCount: cards.length,
        onPageChanged: (i) => _index = i,
        itemBuilder: (context, i) {
          final card = cards[i];
          return GestureDetector(
            onTap: () => openCard(context, ref, card),
            child: Stack(
              fit: StackFit.expand,
              children: [
                AppImage(card.landscapeImage),
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.transparent, Colors.black87]),
                  ),
                ),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 18,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(card.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                      if (card.subtitle != null) Text(card.subtitle!, style: const TextStyle(color: Colors.white70)),
                      if (showPlay) ...[
                        const SizedBox(height: 10),
                        FilledButton.icon(
                          style: FilledButton.styleFrom(minimumSize: const Size(0, 40)),
                          onPressed: () => openCard(context, ref, card),
                          icon: Icon(card.type == 'channel' ? Icons.play_arrow_rounded : Icons.info_outline_rounded),
                          label: Text(card.type == 'channel' ? 'Watch' : 'Details'),
                        ),
                      ],
                    ],
                  ),
                ),
                if (cards.length > 1)
                  Positioned(
                    right: 16,
                    top: 12,
                    child: Text('${i + 1}/${cards.length}', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class ContentRowSection extends StatelessWidget {
  const ContentRowSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context) {
    final style = asString(section.settings['card_style'], 'poster');
    final cards = section.cards;
    if (cards.isEmpty) return const SizedBox.shrink();
    return ContentRail(title: section.title, cards: cards, style: style);
  }
}

class ChannelGridSection extends ConsumerWidget {
  const ChannelGridSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cards = section.cards;
    if (cards.isEmpty) return const SizedBox.shrink();
    final columns = asInt(section.settings['columns'], 4).clamp(2, 6);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(title: section.title ?? 'Channels', onSeeAll: () => openTopLevel(context, ref, '/live')),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: columns, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.4),
            itemCount: cards.length,
            itemBuilder: (context, i) => _ChannelTile(card: cards[i]),
          ),
        ),
      ],
    );
  }
}

class _ChannelTile extends ConsumerWidget {
  const _ChannelTile({required this.card});
  final MediaCard card;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: () => openCard(context, ref, card),
      child: Container(
        decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(10)),
        padding: const EdgeInsets.all(10),
        child: Column(
          children: [
            Expanded(child: AppImage(card.logoUrl, fit: BoxFit.contain, icon: Icons.live_tv_outlined)),
            const SizedBox(height: 4),
            Text(card.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11)),
          ],
        ),
      ),
    );
  }
}

class ContinueWatchingSection extends ConsumerWidget {
  const ContinueWatchingSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final items = ref.watch(continueWatchingProvider);
    return items.maybeWhen(
      data: (list) => list.isEmpty
          ? const SizedBox.shrink()
          : ContentRail(title: section.title ?? 'Continue Watching', cards: list.map((i) => i.toCard()).toList(), style: 'backdrop'),
      orElse: () => const SizedBox.shrink(),
    );
  }
}

class CategoryGridSection extends ConsumerWidget {
  const CategoryGridSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final type = asString(section.settings['category_type'], asString(section.settings['type'], 'all'));
    final cats = ref.watch(categoriesProvider(type));
    return cats.maybeWhen(
      data: (list) {
        if (list.isEmpty) return const SizedBox.shrink();
        final max = asInt(section.settings['max_items'], 12);
        final shown = list.take(max).toList();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SectionHeader(title: section.title ?? 'Browse by category', onSeeAll: () => openTopLevel(context, ref, '/categories')),
            SizedBox(
              height: 44,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: shown.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (context, i) => ActionChip(
                  label: Text(shown[i].name),
                  onPressed: () => context.push('/category/${shown[i].id}', extra: shown[i].name),
                ),
              ),
            ),
          ],
        );
      },
      orElse: () => const SizedBox.shrink(),
    );
  }
}

class BannerSection extends ConsumerWidget {
  const BannerSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = section.settings;
    final image = asStringOrNull(s['image_url']) ?? section.cards.firstOrNull?.backdropUrl;
    if (image == null) return const SizedBox.shrink();
    final ratio = switch (asString(s['aspect_ratio'], '21:9')) {
      '16:9' => 16 / 9,
      '3:1' => 3.0,
      _ => 21 / 9,
    };
    final link = asStringOrNull(s['link_url']) ?? section.cards.firstOrNull?.linkUrl;
    final linkType = asString(s['link_type'], 'url');
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: link == null
            ? null
            : () {
                final id = int.tryParse(link);
                if (linkType == 'movie' && id != null) {
                  context.push('/movie/$id');
                } else if (linkType == 'series' && id != null) {
                  context.push('/series/$id');
                } else if (linkType == 'channel' && id != null) {
                  context.push('/player', extra: PlaybackRequest.channel(id: id, title: section.title ?? 'Live', streamUrl: ''));
                } else {
                  openCard(context, ref, MediaCard(id: section.id, type: 'custom', title: section.title ?? '', linkUrl: link));
                }
              },
        child: AspectRatio(aspectRatio: ratio, child: AppImage(image, borderRadius: BorderRadius.circular(12))),
      ),
    );
  }
}

class SpotlightSection extends ConsumerWidget {
  const SpotlightSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final card = section.cards.firstOrNull;
    if (card == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => openCard(context, ref, card),
        child: Container(
          decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(12)),
          child: Row(
            children: [
              SizedBox(width: 110, child: AspectRatio(aspectRatio: 2 / 3, child: AppImage(card.primaryImage, borderRadius: const BorderRadius.horizontal(left: Radius.circular(12))))),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (section.title != null) Text(section.title!.toUpperCase(), style: TextStyle(fontSize: 11, letterSpacing: 1, color: Theme.of(context).colorScheme.primary)),
                      Text(card.title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                      if (card.subtitle != null) Text(card.subtitle!, style: const TextStyle(color: Colors.white70, fontSize: 13)),
                      const SizedBox(height: 10),
                      FilledButton.icon(
                        style: FilledButton.styleFrom(minimumSize: const Size(0, 38)),
                        onPressed: () => openCard(context, ref, card),
                        icon: const Icon(Icons.play_arrow_rounded),
                        label: const Text('Watch now'),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class TextDividerSection extends StatelessWidget {
  const TextDividerSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context) {
    final title = section.title ?? asStringOrNull(section.settings['text']);
    if (title == null || title.isEmpty) return const Divider(indent: 16, endIndent: 16, height: 24);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 4),
      child: Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
    );
  }
}

class RecommendationSection extends ConsumerWidget {
  const RecommendationSection({super.key, required this.section});
  final LayoutSection section;

  static const _setTypes = {
    'recommended_for_you': 'for_you',
    'because_you_watched': 'because_watched',
    'trending_now': 'trending',
    'top_picks': 'top_picks',
    'hidden_gems': 'hidden_gems',
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sets = ref.watch(recommendationSetsProvider);
    final wanted = _setTypes[section.type];
    return sets.maybeWhen(
      data: (list) {
        final matching = list.where((s) => s.setType == wanted && s.items.isNotEmpty).toList();
        if (matching.isEmpty) return const SizedBox.shrink();
        return Column(
          children: [
            for (final set in matching.take(section.type == 'because_you_watched' ? 3 : 1))
              ContentRail(title: section.title ?? set.title, subtitle: set.reason, cards: set.items),
          ],
        );
      },
      orElse: () => const SizedBox.shrink(),
    );
  }
}

/// "On now" across channels, built from the EPG guide + channel list.
class LiveNowSection extends ConsumerWidget {
  const LiveNowSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final guide = ref.watch(epgGuideProvider);
    final channels = ref.watch(channelsProvider);
    if (guide.value == null || channels.value == null) return const SizedBox.shrink();
    final now = DateTime.now().toUtc();
    final byId = {for (final c in channels.value!) c.id: c};
    final cards = <MediaCard>[];
    for (final s in guide.value!) {
      final ch = byId[s.channelId];
      final prog = s.nowAt(now);
      if (ch == null || prog == null) continue;
      cards.add(ch.toCard().copyWith(subtitle: prog.title));
      if (cards.length >= asInt(section.settings['max_items'], 12)) break;
    }
    if (cards.isEmpty) return const SizedBox.shrink();
    return ContentRail(title: section.title ?? 'Live now', cards: cards, style: 'backdrop', onSeeAll: () => openTopLevel(context, ref, '/live'));
  }
}

/// Mini guide: a few channels with now/next.
class EpgScheduleSection extends ConsumerWidget {
  const EpgScheduleSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final guide = ref.watch(epgGuideProvider);
    final channels = ref.watch(channelsProvider);
    if (guide.value == null || channels.value == null) return const SizedBox.shrink();
    final now = DateTime.now().toUtc();
    final byId = {for (final c in channels.value!) c.id: c};
    final rows = guide.value!.where((s) => byId.containsKey(s.channelId)).take(asInt(section.settings['max_items'], 6)).toList();
    if (rows.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(title: section.title ?? 'TV Guide', onSeeAll: () => openTopLevel(context, ref, '/live')),
        for (final s in rows)
          ListTile(
            dense: true,
            leading: SizedBox(width: 44, height: 32, child: AppImage(byId[s.channelId]!.logoUrl, fit: BoxFit.contain, icon: Icons.live_tv_outlined)),
            title: Text(s.nowAt(now)?.title ?? 'No programme information', maxLines: 1, overflow: TextOverflow.ellipsis),
            subtitle: Text('${byId[s.channelId]!.name} · Next: ${s.nextAfter(now)?.title ?? '—'}', maxLines: 1, overflow: TextOverflow.ellipsis),
            onTap: () => openCard(context, ref, byId[s.channelId]!.toCard()),
          ),
      ],
    );
  }
}

/// View-only list of packages (no purchase flow in the app).
class PackagesListSection extends ConsumerWidget {
  const PackagesListSection({super.key, required this.section});
  final LayoutSection section;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ent = ref.watch(entitlementsProvider);
    return ent.maybeWhen(
      data: (e) {
        if (e.packages.isEmpty) return const SizedBox.shrink();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SectionHeader(title: section.title ?? 'Packages', onSeeAll: () => openTopLevel(context, ref, '/subscribe')),
            SizedBox(
              height: 120,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: e.packages.length,
                separatorBuilder: (_, _) => const SizedBox(width: 10),
                itemBuilder: (context, i) {
                  final p = e.packages[i];
                  return Container(
                    width: 200,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(12)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(p.name, style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(p.priceDisplay, style: TextStyle(color: Theme.of(context).colorScheme.primary)),
                        const Spacer(),
                        Text(p.isSubscribed ? 'Subscribed' : 'View in Packages', style: const TextStyle(fontSize: 12, color: Colors.white70)),
                      ],
                    ),
                  );
                },
              ),
            ),
          ],
        );
      },
      orElse: () => const SizedBox.shrink(),
    );
  }
}

/// Renders a whole layout as a scrollable column of sections.
class LayoutView extends StatelessWidget {
  const LayoutView({super.key, required this.layout, this.onRefresh, this.leading});
  final AppLayout layout;
  final Future<void> Function()? onRefresh;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final sections = [...layout.sections]..sort((a, b) => a.sortOrder.compareTo(b.sortOrder));
    final list = ListView(
      padding: const EdgeInsets.only(bottom: 24),
      children: [
        ?leading,
        for (final s in sections) buildSection(s),
        if (sections.isEmpty) const EmptyView(message: 'This page has no content yet.'),
      ],
    );
    return onRefresh == null ? list : RefreshIndicator(onRefresh: onRefresh!, child: list);
  }
}

/// Helper for external links from custom items.
void openLink(BuildContext context, String url) => openExternal(context, url);
