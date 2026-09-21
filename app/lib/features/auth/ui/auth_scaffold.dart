import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/widgets/legal_links.dart';

/// Shared frame for the login / register / forgot-password screens.
class AuthScaffold extends ConsumerWidget {
  const AuthScaffold({super.key, required this.title, this.subtitle, required this.child, this.showLegal = true, this.showBack = false});
  final String title;
  final String? subtitle;
  final Widget child;
  final bool showLegal;
  final bool showBack;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(appConfigProvider);
    final theme = Theme.of(context);
    // A screen reached with `go` (e.g. verify-pending) has nothing under it:
    // both the arrow and system back then lead to Sign In instead of exiting.
    final canPop = context.canPop();
    return PopScope(
      canPop: !showBack || canPop,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go('/login');
      },
      child: Scaffold(
        appBar: showBack
            ? AppBar(
                backgroundColor: Colors.transparent,
                leading: BackButton(onPressed: () => canPop ? context.pop() : context.go('/login')),
              )
            : null,
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Image.asset(config.logoAsset, width: 40, height: 40),
                        const SizedBox(width: 10),
                        Text(config.appName, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
                      ],
                    ),
                    const SizedBox(height: 28),
                    Text(title, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700)),
                    if (subtitle != null) ...[const SizedBox(height: 6), Text(subtitle!, style: theme.textTheme.bodyMedium?.copyWith(color: Colors.white70))],
                    const SizedBox(height: 24),
                    child,
                    if (showLegal) ...[const SizedBox(height: 24), const LegalLinks()],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Inline error box used by the auth forms.
class FormError extends StatelessWidget {
  const FormError(this.message, {super.key, this.action});
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: scheme.error.withValues(alpha: 0.12),
        border: Border.all(color: scheme.error.withValues(alpha: 0.5)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(message!, style: TextStyle(color: scheme.error)),
          if (action != null) ...[const SizedBox(height: 8), action!],
        ],
      ),
    );
  }
}
