import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/state/auth_notifier.dart';
import '../../features/player/ui/playback_request.dart';
import '../../models/media_card.dart';
import 'legal_links.dart';
import 'access_badge.dart';
import 'app_image.dart';

/// Opens the right screen for any [MediaCard]. Restricted / adult gating is
/// applied here so every rail, grid and search result behaves the same.
Future<void> openCard(BuildContext context, WidgetRef ref, MediaCard card) async {
  if (!await gateAllows(context, ref, card)) return;
  if (!context.mounted) return;
  switch (card.type) {
    case 'movie':
      context.push('/movie/${card.id}');
    case 'series':
      context.push('/series/${card.id}');
    case 'episode':
      context.push('/episode/${card.id}');
    case 'channel':
      context.push('/player', extra: PlaybackRequest.channel(id: card.id, title: card.title, streamUrl: card.streamUrl ?? '', logoUrl: card.logoUrl));
    case 'category':
      context.push('/category/${card.id}', extra: card.title);
    case 'custom':
      final link = card.linkUrl;
      if (link == null || link.isEmpty) return;
      if (link.startsWith('http')) {
        openExternal(context, link);
      } else {
        final m = RegExp(r'^/?(movies?|series|channels?|watch/(movie|series|channel))/(\d+)').firstMatch(link);
        if (m != null) {
          final kind = (m.group(2) ?? m.group(1))!;
          final id = m.group(3);
          if (kind.startsWith('movie')) context.push('/movie/$id');
          if (kind == 'series') context.push('/series/$id');
          if (kind.startsWith('channel')) context.push('/player', extra: PlaybackRequest.channel(id: int.parse(id!), title: card.title, streamUrl: ''));
        } else {
          context.push(link.startsWith('/') ? link : '/$link');
        }
      }
  }
}

/// Returns true when the item may be opened. Shows a dialog otherwise.
Future<bool> gateAllows(BuildContext context, WidgetRef ref, MediaCard card) async {
  final user = ref.read(currentUserProvider);
  if (card.isAdult && !(user?.adultEnabled ?? false)) {
    await _info(context, 'Adult content', 'This title is marked as adult content. Enable adult content in Profile to watch it.');
    return false;
  }
  if (card.isAdult && (user?.parentalPin?.isNotEmpty ?? false)) {
    final ok = await askParentalPin(context, user!.parentalPin!);
    if (!ok) return false;
  }
  // Same rule as the padlock badges (web: isContentLocked).
  final ent = await ref.read(entitlementsProvider.future);
  if (ent.locks(type: card.type, id: card.id, categoryId: card.categoryId, isRestricted: card.isRestricted)) {
    if (!context.mounted) return false;
    if (!ent.hasSubscription) {
      await _info(context, 'Subscription required', 'An active subscription is needed to watch this.');
    } else {
      await _info(context, 'Not included in your plan', 'This title is not part of your current package.');
    }
    return false;
  }
  return true;
}

Future<void> _info(BuildContext context, String title, String message) => showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('OK'))],
      ),
    );

/// Asks for the 4-digit parental PIN. Returns true when it matches.
Future<bool> askParentalPin(BuildContext context, String pin) async {
  final controller = TextEditingController();
  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Enter parental PIN'),
      content: TextField(
        controller: controller,
        keyboardType: TextInputType.number,
        obscureText: true,
        maxLength: 4,
        autofocus: true,
        decoration: const InputDecoration(counterText: ''),
        onSubmitted: (v) => Navigator.pop(ctx, v == pin),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(ctx, controller.text == pin), child: const Text('Unlock')),
      ],
    ),
  );
  if (ok == false && context.mounted && controller.text.isNotEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Incorrect PIN')));
  }
  return ok ?? false;
}

/// Portrait poster card (2:3) with title underneath and optional progress bar.
class PosterCard extends ConsumerWidget {
  const PosterCard({super.key, required this.card, this.width = 120, this.onTap});
  final MediaCard card;
  final double width;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return SizedBox(
      width: width,
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap ?? () => openCard(context, ref, card),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AspectRatio(aspectRatio: 2 / 3, child: AppImage(card.primaryImage, borderRadius: BorderRadius.circular(10))),
                AccessBadge.overlay(card),
                if (card.progress != null && card.progress! > 0)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: ClipRRect(
                      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(10)),
                      child: LinearProgressIndicator(value: card.progress, minHeight: 4, backgroundColor: Colors.black45),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 6),
            Text(card.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
            if (card.subtitle != null)
              Text(card.subtitle!, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.white54, fontSize: 11)),
          ],
        ),
      ),
    );
  }
}

/// Landscape card (16:9) — used for backdrop rails, continue watching, channels.
class LandscapeCard extends ConsumerWidget {
  const LandscapeCard({super.key, required this.card, this.width = 200, this.onTap, this.showTitleOverlay = false});
  final MediaCard card;
  final double width;
  final VoidCallback? onTap;
  final bool showTitleOverlay;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isChannel = card.type == 'channel';
    return SizedBox(
      width: width,
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap ?? () => openCard(context, ref, card),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                AspectRatio(
                  aspectRatio: 16 / 9,
                  child: isChannel
                      ? Container(
                          decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(10)),
                          padding: const EdgeInsets.all(14),
                          child: AppImage(card.logoUrl, fit: BoxFit.contain, icon: Icons.live_tv_outlined),
                        )
                      : AppImage(card.landscapeImage, borderRadius: BorderRadius.circular(10)),
                ),
                if (card.progress != null && card.progress! > 0)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    child: ClipRRect(
                      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(10)),
                      child: LinearProgressIndicator(value: card.progress, minHeight: 4, backgroundColor: Colors.black45),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 6),
            Text(card.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600)),
            if (card.subtitle != null)
              Text(card.subtitle!, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.white54, fontSize: 11)),
          ],
        ),
      ),
    );
  }
}

/// Section title with optional "See all".
class SectionHeader extends StatelessWidget {
  const SectionHeader({super.key, required this.title, this.onSeeAll, this.subtitle});
  final String title;
  final String? subtitle;
  final VoidCallback? onSeeAll;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 18, 8, 8),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                if (subtitle != null) Text(subtitle!, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.white54)),
              ],
            ),
          ),
          if (onSeeAll != null) TextButton(onPressed: onSeeAll, child: const Text('See all')),
        ],
      ),
    );
  }
}

/// Horizontal rail of cards. `style` ∈ poster | backdrop | square.
class ContentRail extends StatelessWidget {
  const ContentRail({super.key, required this.cards, this.style = 'poster', this.title, this.subtitle, this.onSeeAll});
  final List<MediaCard> cards;
  final String style;
  final String? title;
  final String? subtitle;
  final VoidCallback? onSeeAll;

  @override
  Widget build(BuildContext context) {
    if (cards.isEmpty) return const SizedBox.shrink();
    final landscape = style == 'backdrop' || cards.first.type == 'channel';
    final height = landscape ? 160.0 : 232.0;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (title != null) SectionHeader(title: title!, subtitle: subtitle, onSeeAll: onSeeAll),
        SizedBox(
          height: height,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: cards.length,
            separatorBuilder: (_, _) => const SizedBox(width: 10),
            itemBuilder: (_, i) => landscape ? LandscapeCard(card: cards[i]) : PosterCard(card: cards[i]),
          ),
        ),
      ],
    );
  }
}

/// Responsive grid of poster cards (used by list pages).
class PosterGrid extends StatelessWidget {
  const PosterGrid({super.key, required this.cards, this.controller, this.footer});
  final List<MediaCard> cards;
  final ScrollController? controller;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final columns = (width / 130).floor().clamp(2, 6);
    return CustomScrollView(
      controller: controller,
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.all(12),
          sliver: SliverGrid(
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: columns, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 0.56),
            delegate: SliverChildBuilderDelegate((_, i) => PosterCard(card: cards[i], width: double.infinity), childCount: cards.length),
          ),
        ),
        if (footer != null) SliverToBoxAdapter(child: footer),
      ],
    );
  }
}
