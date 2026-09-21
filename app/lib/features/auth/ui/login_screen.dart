import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/util/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../repositories.dart';
import '../state/auth_notifier.dart';
import 'auth_scaffold.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _identity = TextEditingController();
  final _password = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _busy = false;
  bool _obscure = true;
  String? _error;
  String? _unverifiedEmail;
  String? _info;

  @override
  void initState() {
    super.initState();
    // Startup marker read by tool/smoke_test.sh from logcat (debug and release).
    debugPrint('CARI_SMOKE screen=login');
  }

  @override
  void dispose() {
    _identity.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
      _unverifiedEmail = null;
      _info = null;
    });
    try {
      await ref.read(authProvider.notifier).login(_identity.text.trim(), _password.text);
    } on ApiException catch (e) {
      setState(() {
        if (e.code == 'EMAIL_NOT_VERIFIED') {
          _unverifiedEmail = asStringOrNull(e.details['email']) ?? _identity.text.trim();
          _error = e.message;
        } else if (e.code == 'AUTH_FAILED') {
          _error = 'Incorrect username/email or password.';
        } else {
          _error = describeError(e);
        }
      });
    } catch (e) {
      setState(() => _error = describeError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    final email = _unverifiedEmail;
    if (email == null) return;
    try {
      final msg = await ref.read(authRepositoryProvider).resendVerification(email);
      setState(() {
        _info = msg;
        _error = null;
        _unverifiedEmail = null;
      });
    } catch (e) {
      setState(() => _error = describeError(e));
    }
  }

  @override
  Widget build(BuildContext context) {
    final signedOutMessage = switch (ref.watch(authProvider)) {
      AuthSignedOut(:final message) => message,
      _ => null,
    };

    return AuthScaffold(
      title: 'Welcome back',
      subtitle: 'Sign in to continue watching',
      child: Form(
        key: _form,
        child: AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (signedOutMessage != null && _error == null && _info == null)
                FormError(signedOutMessage),
              if (_info != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)),
                  child: Text(_info!),
                ),
              FormError(
                _error,
                action: _unverifiedEmail != null
                    ? TextButton(onPressed: _resend, child: const Text('Resend verification email'))
                    : null,
              ),
              TextFormField(
                controller: _identity,
                decoration: const InputDecoration(labelText: 'Username or email', prefixIcon: Icon(Icons.person_outline)),
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.username],
                textInputAction: TextInputAction.next,
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter your username or email' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _password,
                obscureText: _obscure,
                decoration: InputDecoration(
                  labelText: 'Password',
                  prefixIcon: const Icon(Icons.lock_outline),
                  suffixIcon: IconButton(
                    icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),
                autofillHints: const [AutofillHints.password],
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _busy ? null : _submit(),
                validator: (v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
              ),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(onPressed: () => context.push('/forgot-password'), child: const Text('Forgot password?')),
              ),
              const SizedBox(height: 4),
              FilledButton(
                onPressed: _busy ? null : _submit,
                child: _busy
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Text('Sign In'),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text("Don't have an account?"),
                  TextButton(onPressed: () => context.push('/register'), child: const Text('Register')),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
