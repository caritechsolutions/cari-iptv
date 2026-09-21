import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Owns the player's full-screen state and the platform side effects that go
/// with it (orientation + system bars).
///
/// The app is portrait-locked (see `bootstrap.dart`). Full screen is the only
/// place landscape is ever requested, and every way out of it — the toggle
/// button, the Android back button/gesture, playback ending, a stream error,
/// leaving the player, `dispose` — goes through [exit] or [restorePortrait],
/// so the app can never be left in landscape.
class PlayerFullscreen extends ChangeNotifier {
  static const portrait = [DeviceOrientation.portraitUp, DeviceOrientation.portraitDown];
  static const landscape = [DeviceOrientation.landscapeLeft, DeviceOrientation.landscapeRight];

  bool _on = false;
  bool _disposed = false;

  bool get isFullscreen => _on;

  /// Rotate to landscape and hide the system bars.
  Future<void> enter() async {
    if (_on || _disposed) return;
    _on = true;
    notifyListeners();
    await SystemChrome.setPreferredOrientations(landscape);
    await SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  }

  /// Back to portrait with the system bars visible. Safe to call when not in
  /// full screen (no-op) and safe to call more than once.
  Future<void> exit() async {
    if (!_on) return;
    _on = false;
    if (!_disposed) notifyListeners();
    await restorePortrait();
  }

  Future<void> toggle() => _on ? exit() : enter();

  /// Unconditional restore, used on dispose and error paths regardless of the
  /// recorded state so a lost notification can never leave the phone rotated.
  static Future<void> restorePortrait() async {
    await SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    await SystemChrome.setPreferredOrientations(portrait);
  }

  @override
  void dispose() {
    _disposed = true;
    _on = false;
    super.dispose();
  }
}

/// Layout shell for the player.
///
/// Portrait: the 16:9 [stage] sits at the top of the screen with [below]
/// underneath it. Full screen: the stage fills the whole screen.
///
/// The Android back button / gesture while in full screen only leaves full
/// screen (the route is not popped); the next back pops the player. On
/// dispose the orientation and system bars are restored no matter what.
class PlayerShell extends StatefulWidget {
  const PlayerShell({super.key, required this.controller, required this.stage, this.below, this.stageAspectRatio = 16 / 9});

  final PlayerFullscreen controller;
  final Widget stage;
  final Widget? below;
  final double stageAspectRatio;

  @override
  State<PlayerShell> createState() => _PlayerShellState();
}

class _PlayerShellState extends State<PlayerShell> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_changed);
  }

  @override
  void didUpdateWidget(PlayerShell oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_changed);
      widget.controller.addListener(_changed);
    }
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    widget.controller.removeListener(_changed);
    // Leaving the player by any route: always back to portrait + system bars.
    unawaited(widget.controller.exit());
    unawaited(PlayerFullscreen.restorePortrait());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final full = widget.controller.isFullscreen;
    final Widget body;
    if (full) {
      body = widget.stage;
    } else {
      body = SafeArea(
        child: Column(
          children: [
            AspectRatio(aspectRatio: widget.stageAspectRatio, child: widget.stage),
            Expanded(child: widget.below ?? const SizedBox.shrink()),
          ],
        ),
      );
    }
    return PopScope(
      // In full screen the back button/gesture only leaves full screen.
      canPop: !full,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && widget.controller.isFullscreen) unawaited(widget.controller.exit());
      },
      child: Scaffold(backgroundColor: Colors.black, body: body),
    );
  }
}

/// Enter / exit full-screen button for the player controls (also shown over ads).
class FullscreenToggleButton extends StatelessWidget {
  const FullscreenToggleButton({super.key, required this.controller, this.iconSize = 28});
  final PlayerFullscreen controller;
  final double iconSize;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) => IconButton(
        tooltip: controller.isFullscreen ? 'Exit full screen' : 'Full screen',
        iconSize: iconSize,
        icon: Icon(controller.isFullscreen ? Icons.fullscreen_exit_rounded : Icons.fullscreen_rounded),
        onPressed: () => unawaited(controller.toggle()),
      ),
    );
  }
}
