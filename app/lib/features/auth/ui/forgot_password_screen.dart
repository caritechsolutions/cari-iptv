import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_view.dart';
import '../../repositories.dart';
import 'auth_scaffold.dart';

/// Requests a reset link. The link in the email opens the web page
/// `/reset-password/{token}` where the user sets a new password.
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _email = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _busy = false;
  String? _error;
  String? _sentMessage;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final msg = await ref.read(authRepositoryProvider).forgotPassword(_email.text.trim());
      setState(() => _sentMessage = msg);
    } catch (e) {
      setState(() => _error = describeError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_sentMessage != null) {
      return AuthScaffold(
        title: 'Check your email',
        showBack: true,
        showLegal: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Icon(Icons.mark_email_read_outlined, size: 56, color: Colors.greenAccent),
            const SizedBox(height: 16),
            Text(_sentMessage!, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            const Text('Open the link in the email to choose a new password, then come back and sign in. The link expires after 1 hour.',
                textAlign: TextAlign.center, style: TextStyle(color: Colors.white70, fontSize: 13)),
            const SizedBox(height: 24),
            FilledButton(onPressed: () => context.go('/login'), child: const Text('Back to Sign In')),
          ],
        ),
      );
    }

    return AuthScaffold(
      title: 'Forgot your password?',
      subtitle: 'Enter your email and we will send you a reset link',
      showBack: true,
      showLegal: false,
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FormError(_error),
            TextFormField(
              controller: _email,
              decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined)),
              keyboardType: TextInputType.emailAddress,
              autofocus: true,
              onFieldSubmitted: (_) => _busy ? null : _submit(),
              validator: (v) => (v == null || !v.contains('@')) ? 'Enter a valid email address' : null,
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: _busy ? null : _submit,
              child: _busy
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Send Reset Link'),
            ),
          ],
        ),
      ),
    );
  }
}
