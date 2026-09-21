import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/app_image.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../layout/state/layout_providers.dart';
import '../../layout/ui/section_widgets.dart';
import '../../live/ui/live_screen.dart';
import '../../navigation/state/navigation_provider.dart';
import '../../repositories.dart';
import '../../player/ui/play_helpers.dart';
import '../data/content_repository.dart';
import '../state/content_providers.dart';
import 'list_screens.dart';

/// Resolves an episode and opens the player directly (deep-link style route).
class EpisodeLaunchScreen extends ConsumerStatefulWidget {
  const EpisodeLaunchScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<EpisodeLaunchScreen> createState() => _EpisodeLaunchScreenState();
}

class _EpisodeLaunchScreenState extends ConsumerState<EpisodeLaunchScreen> {
  Object? _error;

  @override
  void initState() {
    super.initState();
    _launch();
  }

  Future<void> _launch() async {
    try {
      final ep = await ref.read(contentRepositoryProvider).episode(widget.id);
      final series = ep.seriesId != null ? await ref.read(contentRepositoryProvider).series(ep.seriesId!) : null;
      if (!mounted) return;
      if (series == null) {
        leaveScreen(context);
        return;
      }
      final progress = await ref.read(watchProgressProvider((type: 'episode', id: ep.id)).future);
      if (!mounted) return;
      // The player replaces this launcher, so back from the player returns to
      // where the user came from. If nothing opened (gated, external link,
      // resume dialog dismissed) leave instead of sitting on a spinner.
      final opened = await playEpisode(context, ref, series: series, episode: ep, progress: progress, replace: true);
      if (!opened && mounted) leaveScreen(context);
    } catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: _error == null ? const LoadingView() : ErrorView(error: _error!, onRetry: _launch),
      );
}

/// Category page: channels for `live`, movies for `vod`, series for `series`.
class CategoryScreen extends ConsumerWidget {
  const CategoryScreen({super.key, required this.id, this.title});
  final int id;
  final String? title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cats = ref.watch(categoriesProvider('all'));
    return cats.when(
      loading: () => Scaffold(appBar: AppBar(title: Text(title ?? 'Category')), body: const LoadingView()),
      error: (e, _) => Scaffold(appBar: AppBar(title: Text(title ?? 'Category')), body: ErrorView(error: e, onRetry: () => ref.invalidate(categoriesProvider('all')))),
      data: (list) {
        final cat = list.where((c) => c.id == id).firstOrNull;
        final type = cat?.type ?? 'vod';
        final name = title ?? cat?.name ?? 'Category';
        if (type == 'live') return LiveScreen(categoryId: id, title: name, embedded: true);
        return MediaListScreen(kind: type == 'series' ? 'series' : 'movie', categoryId: id, title: name, embedded: true);
      },
    );
  }
}

/// Person filmography from `/person/{tmdbPersonId}`.
class PersonScreen extends ConsumerWidget {
  const PersonScreen({super.key, required this.tmdbId});
  final int tmdbId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final person = ref.watch(personProvider(tmdbId));
    return Scaffold(
      appBar: AppBar(title: Text(person.value?.name ?? '')),
      body: AsyncView<Person>(
        value: person,
        onRetry: () => ref.invalidate(personProvider(tmdbId)),
        builder: (p) => ListView(
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  ClipOval(child: SizedBox(width: 80, height: 80, child: AppImage(p.profileImage, icon: Icons.person_outline))),
                  const SizedBox(width: 16),
                  Expanded(child: Text(p.name, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700))),
                ],
              ),
            ),
            if (p.movies.isNotEmpty) ContentRail(title: 'Movies', cards: p.movies.map((m) => m.toCard()).toList()),
            if (p.series.isNotEmpty) ContentRail(title: 'TV Shows', cards: p.series.map((s) => s.toCard()).toList()),
            if (p.movies.isEmpty && p.series.isEmpty) const EmptyView(message: 'No titles in the library for this person.'),
          ],
        ),
      ),
    );
  }
}

/// Custom page (layout linked from a nav item or `/app/pages`).
class CustomPageScreen extends ConsumerWidget {
  const CustomPageScreen({super.key, required this.slug});
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pages = ref.watch(pagesProvider).value ?? const [];
    final page = pages.where((p) => p.slug == slug).firstOrNull;
    if (page == null || page.layoutId == null) {
      return Scaffold(appBar: AppBar(title: Text(page?.name ?? slug)), body: const EmptyView(message: 'This page has no layout yet.'));
    }
    return _LayoutPage(title: page.name, layoutId: page.layoutId!);
  }
}

class _LayoutPage extends ConsumerWidget {
  const _LayoutPage({required this.title, required this.layoutId});
  final String title;
  final int layoutId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final layout = ref.watch(layoutByIdProvider(layoutId));
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: AsyncView(
        value: layout,
        onRetry: () => ref.invalidate(layoutByIdProvider(layoutId)),
        builder: (l) => l == null ? const EmptyView(message: 'This page has no layout yet.') : LayoutView(layout: l),
      ),
    );
  }
}
