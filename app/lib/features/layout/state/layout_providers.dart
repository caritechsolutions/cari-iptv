import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../models/category.dart';
import '../../../models/channel.dart';
import '../../../models/epg.dart';
import '../../../models/layout.dart';
import '../../../models/recommendation.dart';
import '../../../models/watch.dart';
import '../../auth/state/auth_notifier.dart';
import '../../navigation/state/navigation_provider.dart';
import '../../repositories.dart';

/// Default published mobile layout (null → the app renders its built-in home).
final homeLayoutProvider = FutureProvider<AppLayout?>((ref) async {
  if (ref.watch(currentUserProvider) == null) return null;
  return ref.watch(layoutRepositoryProvider).defaultLayout();
});

final layoutByIdProvider = FutureProvider.family<AppLayout?, int>((ref, id) async {
  if (ref.watch(currentUserProvider) == null) return null;
  return ref.watch(layoutRepositoryProvider).layoutById(id);
});

// --- Data used by client-filled sections -------------------------------------

final continueWatchingProvider = FutureProvider<List<ContinueWatchingItem>>((ref) async {
  if (ref.watch(currentUserProvider) == null) return const [];
  return ref.watch(userContentRepositoryProvider).continueWatching();
});

/// `type` ∈ live | vod | series | all
final categoriesProvider = FutureProvider.family<List<Category>, String>((ref, type) async {
  if (ref.watch(currentUserProvider) == null) return const [];
  return ref.watch(contentRepositoryProvider).categories(type: type == 'all' ? null : type);
});

final recommendationSetsProvider = FutureProvider<List<RecommendationSet>>((ref) async {
  if (ref.watch(currentUserProvider) == null) return const [];
  return ref.watch(recommendationRepositoryProvider).sets();
});

final channelsProvider = FutureProvider<List<Channel>>((ref) async {
  if (ref.watch(currentUserProvider) == null) return const [];
  return (await ref.watch(contentRepositoryProvider).channels()).items;
});

/// Guide for the current window (−3 h … +24 h), grouped per channel.
final epgGuideProvider = FutureProvider<List<EpgChannelSchedule>>((ref) async {
  if (ref.watch(currentUserProvider) == null) return const [];
  return ref.watch(epgRepositoryProvider).guide();
});

// --- Manifest polling ----------------------------------------------------------

/// Polls `/manifest?platform=mobile` every 30 s while the app is in the
/// foreground (and on resume). When a version hash changes, the matching
/// cache scope is cleared and dependent providers are invalidated — the same
/// strategy the web player uses (docs/API_DISCOVERY.md §5).
class ManifestPoller extends Notifier<DateTime?> with WidgetsBindingObserver {
  Timer? _timer;
  bool _checking = false;

  @override
  DateTime? build() {
    final signedIn = ref.watch(currentUserProvider) != null;
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    if (signedIn) {
      WidgetsBinding.instance.addObserver(this);
      _timer = Timer.periodic(const Duration(seconds: 30), (_) => check());
      Future.microtask(check);
    }
    ref.onDispose(() {
      _timer?.cancel();
      WidgetsBinding.instance.removeObserver(this);
    });
    return null;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) check();
  }

  Future<void> check() async {
    if (_checking) return;
    _checking = true;
    try {
      final manifest = await ref.read(layoutRepositoryProvider).manifest();
      final cache = ref.read(cacheStoreProvider);
      final previous = {for (final s in manifest.versions.keys) s: cache.manifestVersion(s)};
      final changed = manifest.changedScopes(previous);
      final firstRun = previous.values.every((v) => v == null);
      for (final scope in changed) {
        await cache.setManifestVersion(scope, manifest.versions[scope]!);
        if (!firstRun) await cache.invalidateScope(scope);
      }
      if (!firstRun && changed.isNotEmpty) _invalidate(changed);
      state = DateTime.now();
    } catch (_) {
      // Offline or server error: keep whatever is cached.
    } finally {
      _checking = false;
    }
  }

  void _invalidate(List<String> scopes) {
    for (final scope in scopes) {
      switch (scope) {
        case 'layouts':
          ref.invalidate(homeLayoutProvider);
          ref.invalidate(layoutByIdProvider);
        case 'navigation':
          ref.invalidate(navigationProvider);
          ref.invalidate(pagesProvider);
        case 'channels':
          ref.invalidate(channelsProvider);
        case 'categories':
          ref.invalidate(categoriesProvider);
        case 'epg':
          ref.invalidate(epgGuideProvider);
        case 'movies':
        case 'series':
          ref.invalidate(homeLayoutProvider);
          ref.invalidate(recommendationSetsProvider);
      }
    }
  }
}

final manifestPollerProvider = NotifierProvider<ManifestPoller, DateTime?>(ManifestPoller.new);
