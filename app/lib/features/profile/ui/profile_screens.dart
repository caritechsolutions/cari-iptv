import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/legal_links.dart';
import '../../../models/entitlements.dart';
import '../../auth/state/auth_notifier.dart';
import '../../content/ui/detail_widgets.dart';
import '../../repositories.dart';
import '../../shell/ui/app_shell.dart';

/// Profile tab: account details, adult content toggle, parental PIN, packages.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final ent = ref.watch(entitlementsProvider);
    if (user == null) return const Scaffold(body: LoadingView());
    return Scaffold(
      appBar: const TabAppBar(title: 'Profile'),
      body: ListView(
        children: [
          ListTile(
            leading: CircleAvatar(radius: 26, child: Text(user.displayName.isNotEmpty ? user.displayName[0].toUpperCase() : '?')),
            title: Text(user.displayName, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18)),
            subtitle: Text([user.email, if (user.username.isNotEmpty) '@${user.username}'].where((e) => e.isNotEmpty).join(' · ')),
          ),
          const Divider(),
          SwitchListTile(
            secondary: const Icon(Icons.eighteen_up_rating_outlined),
            title: const Text('Show adult content'),
            subtitle: const Text('Titles marked as adult are hidden unless enabled'),
            value: user.adultEnabled,
            onChanged: (v) async {
              try {
                await ref.read(authProvider.notifier).updateProfile(adultEnabled: v);
              } catch (e) {
                if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(describeError(e))));
              }
            },
          ),
          ListTile(
            leading: const Icon(Icons.pin_outlined),
            title: const Text('Parental PIN'),
            subtitle: Text(user.parentalPin == null || user.parentalPin!.isEmpty ? 'Not set' : 'Set · asked before adult content plays'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => _editPin(context, ref, user.parentalPin),
          ),
          ListTile(
            leading: const Icon(Icons.devices_outlined),
            title: const Text('Simultaneous devices'),
            subtitle: Text('${user.maxConnections} allowed. Signing in on another device signs out the oldest session.'),
          ),
          const Divider(),
          const SectionHeaderText('Your packages'),
          ent.when(
            loading: () => const Padding(padding: EdgeInsets.all(16), child: LinearProgressIndicator()),
            error: (e, _) => ErrorView(error: e, compact: true, onRetry: () => ref.invalidate(entitlementsProvider)),
            data: (e) => Column(
              children: [
                if (e.packages.where((p) => p.isSubscribed).isEmpty)
                  const ListTile(title: Text('No active package'), subtitle: Text('Some titles may not be available.')),
                for (final p in e.packages.where((p) => p.isSubscribed))
                  ListTile(leading: const Icon(Icons.check_circle, color: Colors.greenAccent), title: Text(p.name), subtitle: Text(p.priceDisplay)),
                TextButton(onPressed: () => context.go('/subscribe'), child: const Text('View all packages')),
              ],
            ),
          ),
          const Divider(),
          ListTile(leading: const Icon(Icons.settings_outlined), title: const Text('Settings'), trailing: const Icon(Icons.chevron_right), onTap: () => context.push('/settings')),
        ],
      ),
    );
  }

  Future<void> _editPin(BuildContext context, WidgetRef ref, String? current) async {
    final controller = TextEditingController();
    final result = await showDialog<String?>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Parental PIN'),
        content: TextField(controller: controller, keyboardType: TextInputType.number, maxLength: 4, obscureText: true, autofocus: true, decoration: const InputDecoration(labelText: '4-digit PIN')),
        actions: [
          if (current != null && current.isNotEmpty) TextButton(onPressed: () => Navigator.pop(ctx, ''), child: const Text('Remove PIN')),
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, controller.text), child: const Text('Save')),
        ],
      ),
    );
    if (result == null) return;
    try {
      if (result.isEmpty) {
        await ref.read(authProvider.notifier).updateProfile(clearPin: true);
      } else {
        if (!RegExp(r'^\d{4}$').hasMatch(result)) throw const ApiException(code: 'VALIDATION_ERROR', message: 'PIN must be exactly 4 digits');
        await ref.read(authProvider.notifier).updateProfile(parentalPin: result);
      }
    } catch (e) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(describeError(e))));
    }
  }
}

class SectionHeaderText extends StatelessWidget {
  const SectionHeaderText(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
        child: Text(text.toUpperCase(), style: TextStyle(fontSize: 11, letterSpacing: 1, color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w700)),
      );
}

/// Packages tab: view-only. Free/trial packages can be activated through the
/// API; paid packages show a neutral "not available in the app" message.
class PackagesScreen extends ConsumerWidget {
  const PackagesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ent = ref.watch(entitlementsProvider);
    return Scaffold(
      appBar: const TabAppBar(title: 'Packages'),
      body: AsyncView<Entitlements>(
        value: ent,
        onRetry: () => ref.invalidate(entitlementsProvider),
        isEmpty: (e) => e.packages.isEmpty,
        emptyMessage: 'No packages are available.',
        emptyIcon: Icons.inventory_2_outlined,
        builder: (e) => ListView.builder(
          padding: const EdgeInsets.all(12),
          itemCount: e.packages.length,
          itemBuilder: (context, i) => _PackageCard(pkg: e.packages[i]),
        ),
      ),
    );
  }
}

class _PackageCard extends ConsumerWidget {
  const _PackageCard({required this.pkg});
  final Package pkg;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    Future<void> act() async {
      try {
        final msg = pkg.isSubscribed
            ? await ref.read(authRepositoryProvider).unsubscribe(pkg.id)
            : await ref.read(authRepositoryProvider).subscribe(pkg.id);
        ref.invalidate(entitlementsProvider);
        if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
      } catch (e) {
        if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(friendlyPaymentMessage(e))));
      }
    }

    final canSelfActivate = pkg.isFree || pkg.trialDays > 0;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(pkg.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16))),
                Text(pkg.priceDisplay, style: TextStyle(color: Theme.of(context).colorScheme.primary, fontWeight: FontWeight.w700)),
              ],
            ),
            if (pkg.billingPeriod.isNotEmpty || pkg.trialDays > 0)
              Text([if (pkg.billingPeriod.isNotEmpty) pkg.billingPeriod, if (pkg.trialDays > 0) '${pkg.trialDays}-day trial'].join(' · '), style: const TextStyle(color: Colors.white54, fontSize: 12)),
            if (pkg.description.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 6), child: Text(pkg.description, style: const TextStyle(color: Colors.white70, fontSize: 13))),
            if (pkg.features.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Wrap(spacing: 6, runSpacing: 4, children: [for (final f in pkg.features) Chip(label: Text(f, style: const TextStyle(fontSize: 11)), visualDensity: VisualDensity.compact)]),
              ),
            const SizedBox(height: 10),
            Row(
              children: [
                if (pkg.isSubscribed) const Padding(padding: EdgeInsets.only(right: 8), child: Icon(Icons.check_circle, color: Colors.greenAccent, size: 18)),
                Text(pkg.isSubscribed ? 'Active on your account' : (canSelfActivate ? 'Available' : 'Not available in the app'), style: const TextStyle(fontSize: 12, color: Colors.white70)),
                const Spacer(),
                if (pkg.isSubscribed)
                  OutlinedButton(onPressed: act, child: const Text('Cancel'))
                else if (canSelfActivate)
                  FilledButton(onPressed: act, child: Text(pkg.isFree ? 'Activate' : 'Start trial')),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Settings: app info, cache, legal links, sign out, delete account.
class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(appConfigProvider);
    final user = ref.watch(currentUserProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          if (user != null)
            ListTile(
              leading: const Icon(Icons.person_outline),
              title: Text(user.displayName),
              subtitle: Text(user.email),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.go('/profile'),
            ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.cleaning_services_outlined),
            title: const Text('Clear cached content'),
            subtitle: const Text('Removes saved pages and lists; they reload on next use'),
            onTap: () async {
              await ref.read(cacheStoreProvider).clearAll();
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Cache cleared')));
            },
          ),
          ListTile(leading: const Icon(Icons.privacy_tip_outlined), title: const Text('Privacy Policy'), onTap: () => openExternal(context, config.privacyUrl)),
          ListTile(leading: const Icon(Icons.description_outlined), title: const Text('Terms of Service'), onTap: () => openExternal(context, config.termsUrl)),
          FutureBuilder<PackageInfo>(
            future: PackageInfo.fromPlatform(),
            builder: (context, snap) => ListTile(
              leading: const Icon(Icons.info_outline),
              title: const Text('App version'),
              subtitle: Text(snap.hasData ? '${snap.data!.version} (${snap.data!.buildNumber}) · ${config.flavor.name}' : '…'),
            ),
          ),
          ListTile(leading: const Icon(Icons.dns_outlined), title: const Text('Server'), subtitle: Text(config.apiBaseUrl)),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.logout),
            title: const Text('Sign out'),
            onTap: () async {
              await ref.read(authProvider.notifier).logout();
            },
          ),
          ListTile(
            leading: Icon(Icons.delete_forever_outlined, color: Theme.of(context).colorScheme.error),
            title: Text('Delete account', style: TextStyle(color: Theme.of(context).colorScheme.error)),
            subtitle: const Text('Permanently removes your account and personal data'),
            onTap: () => _deleteAccount(context, ref),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Future<void> _deleteAccount(BuildContext context, WidgetRef ref) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete your account?'),
        content: const Text(
          'This cannot be undone. Your profile details, watch history, My List, ratings and signed-in devices will be permanently removed. '
          'Subscription records are kept in anonymised form for accounting.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: Theme.of(ctx).colorScheme.error), onPressed: () => Navigator.pop(ctx, true), child: const Text('Continue')),
        ],
      ),
    );
    if (confirm != true || !context.mounted) return;

    final controller = TextEditingController();
    final password = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm with your password'),
        content: TextField(controller: controller, obscureText: true, autofocus: true, decoration: const InputDecoration(labelText: 'Password')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: Theme.of(ctx).colorScheme.error), onPressed: () => Navigator.pop(ctx, controller.text), child: const Text('Delete account')),
        ],
      ),
    );
    if (password == null || password.isEmpty || !context.mounted) return;
    try {
      await ref.read(authProvider.notifier).deleteAccount(password);
    } catch (e) {
      if (context.mounted) {
        final msg = e is ApiException && e.code == 'AUTH_FAILED' ? 'Incorrect password.' : describeError(e);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
      }
    }
  }
}
