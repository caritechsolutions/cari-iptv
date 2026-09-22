import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/app_image.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../core/widgets/legal_links.dart';
import '../../../models/content_extras.dart';
import '../../../models/media_card.dart';
import '../../auth/state/auth_notifier.dart';
import '../../repositories.dart';
import '../state/content_providers.dart';

/// Backdrop header with gradient, poster and title. Used by movie and series detail.
class DetailHeader extends StatelessWidget {
  const DetailHeader({super.key, required this.title, this.backdropUrl, this.posterUrl, this.metaLine, this.genres = const []});
  final String title;
  final String? backdropUrl;
  final String? posterUrl;
  final String? metaLine;
  final List<String> genres;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        AspectRatio(aspectRatio: 16 / 9, child: AppImage(backdropUrl ?? posterUrl)),
        Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.transparent, Theme.of(context).scaffoldBackgroundColor],
                stops: const [0.4, 1],
              ),
            ),
          ),
        ),
        Positioned(
          left: 16,
          right: 16,
          bottom: 0,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              SizedBox(width: 90, child: AspectRatio(aspectRatio: 2 / 3, child: AppImage(posterUrl, borderRadius: BorderRadius.circular(8)))),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800), maxLines: 3, overflow: TextOverflow.ellipsis),
                    if (metaLine != null) Text(metaLine!, style: const TextStyle(color: Colors.white70, fontSize: 13)),
                    if (genres.isNotEmpty) Text(genres.take(4).join(' · '), style: const TextStyle(color: Colors.white54, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Watchlist toggle button bound to [watchlistProvider].
class WatchlistButton extends ConsumerWidget {
  const WatchlistButton({super.key, required this.type, required this.id, this.compact = false});
  final String type;
  final int id;
  final bool compact;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inList = ref.watch(watchlistProvider).value?.contains('$type:$id') ?? false;
    Future<void> toggle() async {
      try {
        final now = await ref.read(watchlistProvider.notifier).toggle(type, id);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(now ? 'Added to My List' : 'Removed from My List'), duration: const Duration(seconds: 1)));
        }
      } catch (e) {
        if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(describeError(e))));
      }
    }

    if (compact) {
      return IconButton(onPressed: toggle, icon: Icon(inList ? Icons.bookmark_rounded : Icons.bookmark_border_rounded), tooltip: inList ? 'Remove from My List' : 'Add to My List');
    }
    return OutlinedButton.icon(
      onPressed: toggle,
      icon: Icon(inList ? Icons.check_rounded : Icons.add_rounded),
      label: Text(inList ? 'In My List' : 'My List'),
    );
  }
}

/// Community rating + the subscriber's own star rating.
class RatingRow extends ConsumerWidget {
  const RatingRow({super.key, required this.type, required this.id, this.voteAverage});
  final String type;
  final int id;
  final double? voteAverage;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final key = (type: type, id: id);
    final stats = ref.watch(ratingProvider(key)).value;
    final user = stats?.userRating;

    Future<void> rate(int stars) async {
      try {
        await ref.read(userContentRepositoryProvider).rate(contentType: type, contentId: id, rating: stars);
        ref.invalidate(ratingProvider(key));
      } catch (e) {
        if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(describeError(e))));
      }
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          if (voteAverage != null && voteAverage! > 0) ...[
            const Icon(Icons.star_rounded, color: Colors.amber, size: 18),
            const SizedBox(width: 4),
            Text(voteAverage!.toStringAsFixed(1), style: const TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(width: 14),
          ],
          if (stats?.communityRating != null) ...[
            Text('Community ${stats!.communityRating!.toStringAsFixed(1)} (${stats.ratingCount})', style: const TextStyle(color: Colors.white54, fontSize: 12)),
            const SizedBox(width: 14),
          ],
          const Spacer(),
          for (var i = 1; i <= 5; i++)
            InkWell(
              onTap: () => rate(i),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: Icon(user != null && i <= user ? Icons.star_rounded : Icons.star_outline_rounded, size: 22, color: user != null && i <= user ? Colors.amber : Colors.white38),
              ),
            ),
        ],
      ),
    );
  }
}

class CastRail extends StatelessWidget {
  const CastRail({super.key, required this.cast});
  final List<CastMember> cast;

  @override
  Widget build(BuildContext context) {
    if (cast.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeader(title: 'Cast & crew'),
        SizedBox(
          height: 118,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: cast.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (context, i) {
              final c = cast[i];
              return SizedBox(
                width: 76,
                child: InkWell(
                  onTap: c.tmdbPersonId == null ? null : () => context.push('/person/${c.tmdbPersonId}'),
                  child: Column(
                    children: [
                      ClipOval(child: SizedBox(width: 64, height: 64, child: AppImage(c.profileUrl, icon: Icons.person_outline))),
                      const SizedBox(height: 4),
                      Text(c.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                      Text(c.character ?? c.role ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 10, color: Colors.white54)),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class TrailerButtons extends StatelessWidget {
  const TrailerButtons({super.key, required this.trailers});
  final List<Trailer> trailers;

  @override
  Widget build(BuildContext context) {
    final primary = trailers.where((t) => t.watchUrl != null).toList()..sort((a, b) => (b.isPrimary ? 1 : 0) - (a.isPrimary ? 1 : 0));
    if (primary.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Wrap(
        spacing: 8,
        children: [
          for (final t in primary.take(3))
            ActionChip(avatar: const Icon(Icons.play_circle_outline, size: 18), label: Text(t.title), onPressed: () => openExternal(context, t.watchUrl!)),
        ],
      ),
    );
  }
}

/// Shows the entitlement / adult notice for a detail page (play button stays disabled).
class AccessNotice extends ConsumerWidget {
  const AccessNotice({super.key, required this.card});
  final MediaCard card;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final ent = ref.watch(entitlementsProvider).value;
    String? text;
    if (card.isAdult && !(user?.adultEnabled ?? false)) {
      text = 'Adult content is hidden. Enable it in Profile to watch.';
    } else if (ent != null && ent.locks(type: card.type, id: card.id, categoryId: card.categoryId, isRestricted: card.isRestricted)) {
      text = ent.hasSubscription ? 'Not included in your current package.' : 'An active subscription is needed to watch this.';
    }
    if (text == null) return const SizedBox.shrink();
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: Colors.amber.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
      child: Row(children: [const Icon(Icons.lock_outline, key: Key('access-notice-lock'), size: 18, color: Colors.amber), const SizedBox(width: 8), Expanded(child: Text(text, style: const TextStyle(fontSize: 13)))]),
    );
  }
}

/// Neutral message for 402 responses — no purchase flows in the app. With
/// billing off it says only that the option is not available: no plans,
/// prices, buying or websites.
String friendlyPaymentMessage(Object e, {bool billing = true}) {
  if (e is ApiException && e.isPaymentRequired) return billing ? 'This package is not available in the app.' : 'Not available.';
  return describeError(e);
}
