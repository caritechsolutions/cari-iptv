import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../models/navigation.dart';
import '../../auth/state/auth_notifier.dart';
import '../../billing/billing_provider.dart';
import '../../repositories.dart';

/// A resolved bottom-tab destination.
class NavDestination {
  const NavDestination({required this.label, required this.icon, required this.path, this.externalUrl, this.layoutId});
  final String label;
  final IconData icon;

  /// Route path for page targets; null for url/deeplink targets.
  final String? path;
  final String? externalUrl;
  final int? layoutId;
}

/// Route path for each backend `page_type` (docs/API_DISCOVERY.md §5).
String? pathForPageType(String? pageType, {String? slug}) => switch (pageType) {
      'home' => '/home',
      'movies' => '/movies',
      'series' => '/series',
      'live_tv' => '/live',
      'categories' => '/categories',
      'search' => '/search',
      'watchlist' => '/my-list',
      'settings' => '/settings',
      'profile' => '/profile',
      'subscription' => '/subscribe',
      'custom' => slug != null ? '/page/$slug' : null,
      _ => null,
    };

IconData iconFor(String? lucide, String? pageType) {
  switch (lucide) {
    case 'lucide-home':
      return Icons.home_rounded;
    case 'lucide-film':
      return Icons.movie_rounded;
    case 'lucide-clapperboard':
    case 'lucide-tv':
      return Icons.tv_rounded;
    case 'lucide-radio':
      return Icons.sensors_rounded;
    case 'lucide-grid-3x3':
    case 'lucide-layout-grid':
      return Icons.grid_view_rounded;
    case 'lucide-search':
      return Icons.search_rounded;
    case 'lucide-bookmark':
    case 'lucide-heart':
      return Icons.bookmark_rounded;
    case 'lucide-settings':
      return Icons.settings_rounded;
    case 'lucide-user':
      return Icons.person_rounded;
    case 'lucide-credit-card':
      return Icons.credit_card_rounded;
  }
  return switch (pageType) {
    'home' => Icons.home_rounded,
    'movies' => Icons.movie_rounded,
    'series' => Icons.tv_rounded,
    'live_tv' => Icons.sensors_rounded,
    'categories' => Icons.grid_view_rounded,
    'search' => Icons.search_rounded,
    'watchlist' => Icons.bookmark_rounded,
    'settings' => Icons.settings_rounded,
    'profile' => Icons.person_rounded,
    'subscription' => Icons.credit_card_rounded,
    _ => Icons.circle_outlined,
  };
}

/// Default tabs used when the backend has no mobile navigation.
const defaultDestinations = [
  NavDestination(label: 'Home', icon: Icons.home_rounded, path: '/home'),
  NavDestination(label: 'Movies', icon: Icons.movie_rounded, path: '/movies'),
  NavDestination(label: 'TV Shows', icon: Icons.tv_rounded, path: '/series'),
  NavDestination(label: 'Live TV', icon: Icons.sensors_rounded, path: '/live'),
  NavDestination(label: 'My List', icon: Icons.bookmark_rounded, path: '/my-list'),
];

/// Page types that only exist to sell or manage packages.
bool isBillingPageType(String? pageType) => pageType == 'subscription' || pageType == 'packages';

List<NavDestination> destinationsFrom(AppNavigation? nav, {bool billing = true}) {
  if (nav == null || nav.items.isEmpty) return defaultDestinations;
  final items = [...nav.items]..sort((a, b) => a.sortOrder.compareTo(b.sortOrder));
  final out = <NavDestination>[];
  for (final item in items) {
    if (item.target == 'page') {
      if (!billing && isBillingPageType(item.pageType)) continue;
      final path = pathForPageType(item.pageType, slug: item.pageSlug);
      if (path == null) continue;
      out.add(NavDestination(label: item.label, icon: iconFor(item.icon, item.pageType), path: path, layoutId: item.layoutId));
    } else if (item.url != null) {
      out.add(NavDestination(label: item.label, icon: iconFor(item.icon, null), path: null, externalUrl: item.url));
    }
    if (out.length >= nav.maxItems.clamp(2, 5)) break;
  }
  return out.length >= 2 ? out : defaultDestinations;
}

final navigationProvider = FutureProvider<AppNavigation?>((ref) async {
  if (ref.watch(currentUserProvider) == null) return null;
  try {
    return await ref.watch(layoutRepositoryProvider).navigation();
  } catch (_) {
    return null;
  }
});

final pagesProvider = FutureProvider<List<AppPage>>((ref) async {
  if (ref.watch(currentUserProvider) == null) return const [];
  final billing = ref.watch(billingEnabledProvider);
  try {
    final pages = await ref.watch(layoutRepositoryProvider).pages();
    return billing ? pages : pages.where((p) => !isBillingPageType(p.pageType)).toList(growable: false);
  } catch (_) {
    return const [];
  }
});

final destinationsProvider = Provider<List<NavDestination>>((ref) {
  final nav = ref.watch(navigationProvider).value;
  return destinationsFrom(nav, billing: ref.watch(billingEnabledProvider));
});
