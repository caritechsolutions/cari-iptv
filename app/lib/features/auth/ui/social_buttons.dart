import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/widgets/async_view.dart';
import '../data/social_sign_in.dart';
import '../state/auth_notifier.dart';

/// "or continue with" + Google / Apple buttons, shown on the sign-in and
/// register screens when the operator enabled a provider. Renders nothing
/// otherwise, so operators without social sign-in see no change.
class SocialSignInButtons extends ConsumerStatefulWidget {
  const SocialSignInButtons({super.key, this.onBusy, this.onError});

  /// Lets the host screen disable its own form while a provider sheet is up.
  final ValueChanged<bool>? onBusy;
  final ValueChanged<String>? onError;

  @override
  ConsumerState<SocialSignInButtons> createState() => _SocialSignInButtonsState();
}

class _SocialSignInButtonsState extends ConsumerState<SocialSignInButtons> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);
    widget.onBusy?.call(true);
    try {
      await action();
    } on SocialSignInCancelled {
      // user closed the sheet: nothing to say
    } on ApiException catch (e) {
      widget.onError?.call(e.message);
    } catch (e) {
      widget.onError?.call(describeError(e));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        widget.onBusy?.call(false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final providers = ref.watch(signInProvidersProvider).value;
    if (providers == null || !providers.any) return const SizedBox.shrink();
    final social = ref.read(socialSignInProvider);
    final showApple = providers.appleEnabled && social.appleAvailable;
    if (!providers.googleEnabled && !showApple) return const SizedBox.shrink();

    final notifier = ref.read(authProvider.notifier);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 16),
        Row(children: [
          const Expanded(child: Divider()),
          Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Text('or continue with', style: Theme.of(context).textTheme.bodySmall)),
          const Expanded(child: Divider()),
        ]),
        const SizedBox(height: 12),
        if (providers.googleEnabled)
          OutlinedButton.icon(
            key: const Key('google-sign-in'),
            onPressed: _busy ? null : () => _run(() => notifier.loginWithGoogle(providers.googleClientId!)),
            icon: const _GoogleMark(),
            label: const Text('Continue with Google'),
          ),
        if (providers.googleEnabled && showApple) const SizedBox(height: 10),
        if (showApple)
          FilledButton.icon(
            key: const Key('apple-sign-in'),
            style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: Colors.black),
            onPressed: _busy ? null : () => _run(notifier.loginWithApple),
            icon: const Icon(Icons.apple, size: 22),
            label: const Text('Sign in with Apple'),
          ),
      ],
    );
  }
}

/// Google's four-colour "G", drawn so no asset is needed.
class _GoogleMark extends StatelessWidget {
  const _GoogleMark();

  @override
  Widget build(BuildContext context) => SizedBox(width: 18, height: 18, child: CustomPaint(painter: _GPainter()));
}

class _GPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromLTWH(0, 0, size.width, size.height);
    final stroke = size.width * 0.22;
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke;
    final r = rect.deflate(stroke / 2);
    // Arcs: red (top-left), yellow (bottom-left), green (bottom-right), blue (right + bar)
    canvas.drawArc(r, 3.6, 1.6, false, paint..color = const Color(0xFFEA4335));
    canvas.drawArc(r, 2.0, 1.6, false, paint..color = const Color(0xFFFBBC05));
    canvas.drawArc(r, 0.45, 1.6, false, paint..color = const Color(0xFF34A853));
    canvas.drawArc(r, -0.1, 0.55, false, paint..color = const Color(0xFF4285F4));
    canvas.drawLine(Offset(size.width / 2, size.height / 2), Offset(size.width - stroke / 2, size.height / 2), paint..color = const Color(0xFF4285F4));
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
