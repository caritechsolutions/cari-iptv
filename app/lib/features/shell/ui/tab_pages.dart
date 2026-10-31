// Tab pages: thin wrappers so the router never has to change.
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../models/layout.dart';
import '../../content/ui/list_screens.dart';
import '../../content/ui/misc_screens.dart';
import '../../layout/state/layout_providers.dart';
import '../../layout/ui/home_screen.dart';
import '../../layout/ui/section_widgets.dart';
import 'app_shell.dart';
import '../../library/ui/library_screens.dart';
import '../../live/ui/live_screen.dart';
import '../../profile/ui/profile_screens.dart';
import '../../search/ui/search_screen.dart';

class HomeTab extends StatelessWidget {
  const HomeTab({super.key});
  @override
  Widget build(BuildContext context) => const HomeScreen();
}

class MoviesTab extends StatelessWidget {
  const MoviesTab({super.key});
  @override
  Widget build(BuildContext context) => const _PageWithLayout(pageType: 'movies', title: 'Movies', fallback: MediaListScreen(kind: 'movie'));
}

class SeriesTab extends StatelessWidget {
  const SeriesTab({super.key});
  @override
  Widget build(BuildContext context) => const _PageWithLayout(pageType: 'series', title: 'TV Shows', fallback: MediaListScreen(kind: 'series'));
}

/// A system page that renders the layout linked to it in Pages & Nav when
/// there is one (the web player's rule), and its built-in screen otherwise.
class _PageWithLayout extends ConsumerWidget {
  const _PageWithLayout({required this.pageType, required this.title, required this.fallback});
  final String pageType;
  final String title;
  final Widget fallback;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final layout = ref.watch(pageLayoutProvider(pageType));
    // While pages load, or when there is no linked layout, show the built-in screen.
    final AppLayout? linked = layout.value;
    if (linked == null) return fallback;
    return Scaffold(
      appBar: TabAppBar(title: title),
      body: LayoutView(layout: linked, onRefresh: () async {
        ref.invalidate(pageLayoutProvider(pageType));
        await ref.read(pageLayoutProvider(pageType).future);
      }),
    );
  }
}

class LiveTab extends StatelessWidget {
  const LiveTab({super.key});
  @override
  Widget build(BuildContext context) => const LiveScreen();
}

class CategoriesTab extends StatelessWidget {
  const CategoriesTab({super.key});
  @override
  Widget build(BuildContext context) => const CategoriesScreen();
}

class MyListTab extends StatelessWidget {
  const MyListTab({super.key});
  @override
  Widget build(BuildContext context) => const MyListScreen();
}

class SubscribeTab extends StatelessWidget {
  const SubscribeTab({super.key});
  @override
  Widget build(BuildContext context) => const PackagesScreen();
}

class ProfileTab extends StatelessWidget {
  const ProfileTab({super.key});
  @override
  Widget build(BuildContext context) => const ProfileScreen();
}

class CustomPageTab extends StatelessWidget {
  const CustomPageTab({super.key, required this.slug});
  final String slug;
  @override
  Widget build(BuildContext context) => CustomPageScreen(slug: slug);
}

class SearchPage extends StatelessWidget {
  const SearchPage({super.key});
  @override
  Widget build(BuildContext context) => const SearchScreen();
}

class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key});
  @override
  Widget build(BuildContext context) => const SettingsScreen();
}
