import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/legal_links.dart';
import '../../navigation/state/navigation_provider.dart';

/// Bottom-tab shell. Tabs come from the backend navigation (max 5); every
/// other page stays reachable through the app bar actions.
///
/// System back on a tab root goes to Home first; only Home exits the app.
/// Pages pushed inside the shell (search, settings, a non-tab page) keep the
/// tab bar and get the normal back arrow; back pops them.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.child, required this.location});
  final Widget child;
  final String location;

  static bool isHome(String location) => location == '/home' || location.startsWith('/home/');

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final destinations = ref.watch(destinationsProvider);
    final selected = destinations.indexWhere((d) => d.path != null && location.startsWith(d.path!));

    return PopScope(
      // The shell's own route is the bottom of the root navigator. When
      // nothing inside the shell can pop, back means "go to Home" unless we
      // are already there, in which case the app may exit.
      canPop: isHome(location),
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/home');
      },
      child: Scaffold(
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
          destinations: [for (final d in destinations) NavigationDestination(icon: Icon(d.icon), label: d.label)],
        ),
      ),
    );
  }
}

/// Opens a top-level page: switches tabs when [path] is a bottom tab,
/// otherwise pushes it inside the shell so it gets a back arrow and the tab
/// bar stays visible. Never use `context.go` for these from content.
void openTopLevel(BuildContext context, WidgetRef ref, String path) {
  final isTab = ref.read(destinationsProvider).any((d) => d.path == path);
  if (isTab) {
    context.go(path);
  } else {
    context.push(path);
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
