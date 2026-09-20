import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/legal_links.dart';
import '../../navigation/state/navigation_provider.dart';

/// Bottom-tab shell. Tabs come from the backend navigation (max 5); every
/// other page stays reachable through the app bar actions.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.child, required this.location});
  final Widget child;
  final String location;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final destinations = ref.watch(destinationsProvider);
    final selected = destinations.indexWhere((d) => d.path != null && location.startsWith(d.path!));

    return Scaffold(
      body: Column(
        children: [
          const OfflineBanner(),
          Expanded(child: child),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: selected < 0 ? 0 : selected,
        indicatorColor: selected < 0 ? Colors.transparent : null,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        height: 64,
        onDestinationSelected: (i) {
          final d = destinations[i];
          if (d.path != null) {
            context.go(d.path!);
          } else if (d.externalUrl != null) {
            openExternal(context, d.externalUrl!);
          }
        },
        destinations: [
          for (final d in destinations) NavigationDestination(icon: Icon(d.icon), label: d.label),
        ],
      ),
    );
  }
}

/// Standard app bar for tab pages: branding on the left, search and account on the right.
class TabAppBar extends ConsumerWidget implements PreferredSizeWidget {
  const TabAppBar({super.key, this.title, this.actions = const []});
  final String? title;
  final List<Widget> actions;

  @override
  Size get preferredSize => const Size.fromHeight(kToolbarHeight);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return AppBar(
      title: Text(title ?? ''),
      actions: [
        ...actions,
        IconButton(tooltip: 'Search', icon: const Icon(Icons.search_rounded), onPressed: () => context.push('/search')),
        IconButton(tooltip: 'Account', icon: const Icon(Icons.account_circle_outlined), onPressed: () => context.push('/settings')),
      ],
    );
  }
}
