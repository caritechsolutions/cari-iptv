// Tab pages: thin wrappers so the router never has to change.
import 'package:flutter/material.dart';

import '../../content/ui/list_screens.dart';
import '../../content/ui/misc_screens.dart';
import '../../layout/ui/home_screen.dart';
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
  Widget build(BuildContext context) => const MediaListScreen(kind: 'movie');
}

class SeriesTab extends StatelessWidget {
  const SeriesTab({super.key});
  @override
  Widget build(BuildContext context) => const MediaListScreen(kind: 'series');
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
