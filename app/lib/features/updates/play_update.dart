// Google Play in-app update prompt (flexible flow).
//
// On start, a prod build of a brand with PLAY_UPDATES=true asks Play whether
// a newer version code is published. If so, the Play "update available" sheet
// is shown and the download runs in the background while the user keeps
// using the app; when it is ready a snackbar offers "Restart" to install.
// Brands not distributed through Play (PLAY_UPDATES false), dev builds and
// any failure (sideloaded install, no Play services, offline) are silent
// no-ops: the app never blocks on this.
import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:in_app_update/in_app_update.dart';

import '../../core/providers.dart';

/// What the prompt needs from Play; `in_app_update` behind an interface so
/// the flow is testable without Play services.
abstract class PlayUpdater {
  Future<AppUpdateInfo> checkForUpdate();
  Future<AppUpdateResult> startFlexibleUpdate();
  Future<void> completeFlexibleUpdate();
  Stream<InstallStatus> get installStatus;
}

class InAppUpdater implements PlayUpdater {
  const InAppUpdater();
  @override
  Future<AppUpdateInfo> checkForUpdate() => InAppUpdate.checkForUpdate();
  @override
  Future<AppUpdateResult> startFlexibleUpdate() => InAppUpdate.startFlexibleUpdate();
  @override
  Future<void> completeFlexibleUpdate() => InAppUpdate.completeFlexibleUpdate();
  @override
  Stream<InstallStatus> get installStatus => InAppUpdate.installUpdateListener;
}

final playUpdaterProvider = Provider<PlayUpdater>((_) => const InAppUpdater());

/// Wraps the app (MaterialApp `builder`) and runs the check once after the
/// first frame. [messengerKey] is the app's ScaffoldMessenger, for the
/// "Restart" snackbar.
class PlayUpdatePrompt extends ConsumerStatefulWidget {
  const PlayUpdatePrompt({super.key, required this.messengerKey, required this.child});
  final GlobalKey<ScaffoldMessengerState> messengerKey;
  final Widget child;

  @override
  ConsumerState<PlayUpdatePrompt> createState() => _PlayUpdatePromptState();
}

class _PlayUpdatePromptState extends ConsumerState<PlayUpdatePrompt> {
  StreamSubscription<InstallStatus>? _sub;
  bool _offeredRestart = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _check());
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  /// True when this build should talk to Play at all.
  bool get _enabled {
    final config = ref.read(appConfigProvider);
    return config.playUpdates && !config.isDev && defaultTargetPlatform == TargetPlatform.android;
  }

  Future<void> _check() async {
    if (!mounted || !_enabled) return;
    final updater = ref.read(playUpdaterProvider);
    try {
      final info = await updater.checkForUpdate();
      if (!mounted) return;
      if (info.installStatus == InstallStatus.downloaded) {
        // A previous session downloaded it; just offer the restart.
        _offerRestart(updater);
        return;
      }
      if (info.updateAvailability != UpdateAvailability.updateAvailable || !info.flexibleUpdateAllowed) return;
      _sub = updater.installStatus.listen((status) {
        if (status == InstallStatus.downloaded) _offerRestart(updater);
      }, onError: (_) {});
      await updater.startFlexibleUpdate();
    } catch (e) {
      // Not from Play (sideloaded / debug install), no Play services, offline:
      // nothing to do, and never a visible error.
      debugPrint('Play update check skipped: $e');
    }
  }

  void _offerRestart(PlayUpdater updater) {
    if (_offeredRestart || !mounted) return;
    _offeredRestart = true;
    widget.messengerKey.currentState?.showSnackBar(SnackBar(
      key: const Key('play-update-ready'),
      content: const Text('Update downloaded.'),
      duration: const Duration(days: 1),
      action: SnackBarAction(label: 'Restart', onPressed: () => updater.completeFlexibleUpdate()),
    ));
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
