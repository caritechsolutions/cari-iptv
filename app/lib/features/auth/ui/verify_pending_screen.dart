import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_view.dart';
import '../../repositories.dart';
import 'auth_scaffold.dart';

/// Shown after registration: the account exists but the email must be verified.
class VerifyPendingScreen extends ConsumerStatefulWidget {
  const VerifyPendingScreen({super.key, required this.email, this.message});
  final String email;
  final String? message;

  @override
  ConsumerState<VerifyPendingScreen> createState() => _VerifyPendingScreenState();
}

class _VerifyPendingScreenState extends ConsumerState<VerifyPendingScreen> {
  String? _status;
  bool _busy = false;

  Future<void> _resend() async {
    setState(() => _busy = true);
    try {
      _status = await ref.read(authRepositoryProvider).resendVerification(widget.email);
    } catch (e) {
      _status = describeError(e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AuthScaffold(
      title: 'Verify your email',
      showLegal: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.mark_email_unread_outlined, size: 56, color: Colors.greenAccent),
          const SizedBox(height: 16),
          Text(widget.message ?? 'Account created! Please check your email to verify your account.', textAlign: TextAlign.center),
          const SizedBox(height: 6),
          Text('We sent a link to ${widget.email}. Open it, then sign in.', textAlign: TextAlign.center, style: const TextStyle(color: Colors.white70, fontSize: 13)),
          if (_status != null) ...[const SizedBox(height: 12), Text(_status!, textAlign: TextAlign.center, style: const TextStyle(fontSize: 13))],
          const SizedBox(height: 24),
          FilledButton(onPressed: () => context.go('/login'), child: const Text('Go to Sign In')),
          TextButton(onPressed: _busy ? null : _resend, child: const Text('Resend verification email')),
        ],
      ),
    );
  }
}
