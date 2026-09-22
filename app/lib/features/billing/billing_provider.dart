import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../config/app_config.dart';
import '../../core/providers.dart';
import '../../models/app_features.dart';
import '../auth/state/auth_notifier.dart';
import '../repositories.dart';

/// Server feature switches from `/app/config/mobile`. Cached with the
/// `navigation` scope (served from cache when offline) and re-fetched by the
/// manifest poller whenever the manifest changes. Signed out → all off.
final remoteFeaturesProvider = FutureProvider<AppFeatures>((ref) async {
  if (ref.watch(currentUserProvider) == null) return AppFeatures.none;
  try {
    return await ref.watch(layoutRepositoryProvider).features();
  } catch (_) {
    return AppFeatures.none;
  }
});

/// The brand's `BILLING_UI` combined with the server switch:
/// `on` / `off` force it, `auto` follows `features.billing`; unknown or not
/// loaded yet counts as off, so nothing billing-related flashes on screen
/// while the config loads.
bool resolveBilling(BillingUi brand, bool? server) => switch (brand) {
      BillingUi.on => true,
      BillingUi.off => false,
      BillingUi.auto => server ?? false,
    };

/// Whether billing surfaces (packages page and nav item, package rows and
/// sections, activate/cancel actions) are shown. Entitlement locks and the
/// "not included in your plan" messages do not depend on this.
final billingEnabledProvider = Provider<bool>((ref) {
  final brand = ref.watch(appConfigProvider).billingUi;
  final server = ref.watch(remoteFeaturesProvider).value?.billing;
  return resolveBilling(brand, server);
});
