import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/state/auth_notifier.dart';
import '../../models/entitlements.dart';
import '../../models/media_card.dart';

/// Whether [card] is locked for the signed-in subscriber (web rule, see
/// [Entitlements.locks]). Null while entitlements are still loading.
bool? isCardLocked(WidgetRef ref, MediaCard card) {
  final ent = ref.watch(entitlementsProvider).value;
  if (ent == null) return null;
  return ent.locks(type: card.type, id: card.id, categoryId: card.categoryId, isRestricted: card.isRestricted);
}

/// The padlock / 18+ marker drawn on cards, channel rows and the guide.
/// Adult content always shows 18+; the padlock appears only when the play
/// gate would refuse, and follows entitlement changes.
class AccessBadge extends ConsumerWidget {
  const AccessBadge(this.card, {super.key, this.overlay = false});

  /// Boxed overlay for image cards; plain small icon for list rows.
  const AccessBadge.overlay(this.card, {super.key}) : overlay = true;

  final MediaCard card;
  final bool overlay;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final IconData icon;
    if (card.isAdult) {
      icon = Icons.eighteen_up_rating_outlined;
    } else if (isCardLocked(ref, card) ?? false) {
      icon = Icons.lock_outline;
    } else {
      return const SizedBox.shrink();
    }
    final key = Key(icon == Icons.lock_outline ? 'locked-${card.type}-${card.id}' : 'adult-${card.type}-${card.id}');
    if (!overlay) return Padding(padding: const EdgeInsets.only(left: 6), child: Icon(icon, key: key, size: 14, color: Colors.white54));
    return Positioned(
      top: 6,
      right: 6,
      child: Container(
        padding: const EdgeInsets.all(4),
        decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(6)),
        child: Icon(icon, key: key, size: 14, color: Colors.white),
      ),
    );
  }
}
