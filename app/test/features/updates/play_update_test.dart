// Play in-app update prompt: a no-op unless the brand is distributed through
// Play (PLAY_UPDATES) and the build is prod; otherwise the flexible flow runs
// on start and "Restart" installs the downloaded update.
import 'dart:async';

import 'package:cari_tv/config/app_config.dart';
import 'package:cari_tv/core/providers.dart';
import 'package:cari_tv/features/updates/play_update.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:in_app_update/in_app_update.dart';

class FakeUpdater implements PlayUpdater {
  FakeUpdater({this.availability = UpdateAvailability.updateNotAvailable, this.flexible = true, this.installed = InstallStatus.unknown, this.throwOnCheck = false});
  final UpdateAvailability availability;
  final bool flexible;
  final InstallStatus installed;
  final bool throwOnCheck;
  final calls = <String>[];
  final statuses = StreamControllerSync();

  @override
  Future<AppUpdateInfo> checkForUpdate() async {
    calls.add('check');
    if (throwOnCheck) throw Exception('Install not from Play');
    return AppUpdateInfo(
      updateAvailability: availability,
      immediateUpdateAllowed: false,
      immediateAllowedPreconditions: null,
      flexibleUpdateAllowed: flexible,
      flexibleAllowedPreconditions: null,
      availableVersionCode: 5,
      installStatus: installed,
      packageName: 'com.freetv.ng',
      clientVersionStalenessDays: null,
      updatePriority: 0,
    );
  }

  @override
  Future<AppUpdateResult> startFlexibleUpdate() async {
    calls.add('start');
    return AppUpdateResult.success;
  }

  @override
  Future<void> completeFlexibleUpdate() async => calls.add('complete');

  @override
  Stream<InstallStatus> get installStatus => statuses.stream;
}

void main() {
  final play = AppConfig.forFlavor(AppFlavor.prod).copyWith(playUpdates: true);
  final sideload = AppConfig.forFlavor(AppFlavor.prod); // PLAY_UPDATES default false
  final dev = AppConfig.forFlavor(AppFlavor.dev).copyWith(playUpdates: true);
  final key = GlobalKey<ScaffoldMessengerState>();

  Widget harness(AppConfig config, FakeUpdater updater) => ProviderScope(
        overrides: [appConfigProvider.overrideWithValue(config), playUpdaterProvider.overrideWithValue(updater)],
        child: MaterialApp(
          scaffoldMessengerKey: key,
          builder: (context, child) => PlayUpdatePrompt(messengerKey: key, child: child ?? const SizedBox.shrink()),
          home: const Scaffold(body: Text('home')),
        ),
      );

  // The prompt only runs on Android; every case below is an Android run.
  final android = TargetPlatformVariant.only(TargetPlatform.android);

  testWidgets('brand not from Play: Play is never asked', (tester) async {
    final u = FakeUpdater(availability: UpdateAvailability.updateAvailable);
    await tester.pumpWidget(harness(sideload, u));
    await tester.pumpAndSettle();
    expect(u.calls, isEmpty);
  }, variant: android);

  testWidgets('dev build: Play is never asked even for a Play brand', (tester) async {
    final u = FakeUpdater(availability: UpdateAvailability.updateAvailable);
    await tester.pumpWidget(harness(dev, u));
    await tester.pumpAndSettle();
    expect(u.calls, isEmpty);
  }, variant: android);

  testWidgets('no update published: checked, nothing started', (tester) async {
    final u = FakeUpdater();
    await tester.pumpWidget(harness(play, u));
    await tester.pumpAndSettle();
    expect(u.calls, ['check']);
    expect(find.byKey(const Key('play-update-ready')), findsNothing);
  }, variant: android);

  testWidgets('update available: flexible download starts, Restart installs it once downloaded', (tester) async {
    final u = FakeUpdater(availability: UpdateAvailability.updateAvailable);
    await tester.pumpWidget(harness(play, u));
    await tester.pumpAndSettle();
    expect(u.calls, ['check', 'start']);
    expect(find.byKey(const Key('play-update-ready')), findsNothing, reason: 'nothing to restart into yet');
    u.statuses.add(InstallStatus.downloading);
    await tester.pump();
    expect(find.byKey(const Key('play-update-ready')), findsNothing);
    u.statuses.add(InstallStatus.downloaded);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1)); // snackbar slides in
    expect(find.byKey(const Key('play-update-ready')), findsOneWidget);
    await tester.tap(find.text('Restart'));
    await tester.pump();
    expect(u.calls.last, 'complete');
  }, variant: android);

  testWidgets('already downloaded in an earlier session: only the restart offer', (tester) async {
    final u = FakeUpdater(availability: UpdateAvailability.updateAvailable, installed: InstallStatus.downloaded);
    await tester.pumpWidget(harness(play, u));
    await tester.pumpAndSettle();
    expect(u.calls, ['check']);
    expect(find.byKey(const Key('play-update-ready')), findsOneWidget);
  }, variant: android);

  testWidgets('sideloaded build of a Play brand: the check fails silently', (tester) async {
    final u = FakeUpdater(throwOnCheck: true);
    await tester.pumpWidget(harness(play, u));
    await tester.pumpAndSettle();
    expect(u.calls, ['check']);
    expect(find.text('home'), findsOneWidget);
    expect(tester.takeException(), isNull);
  }, variant: android);
}

/// Minimal sync broadcast stream for the fake.
class StreamControllerSync {
  final _listeners = <void Function(InstallStatus)>[];
  Stream<InstallStatus> get stream {
    late StreamController<InstallStatus> c;
    c = StreamController<InstallStatus>(onListen: () => _listeners.add(c.add), onCancel: () => _listeners.remove(c.add), sync: true);
    return c.stream;
  }

  void add(InstallStatus s) {
    for (final l in List.of(_listeners)) {
      l(s);
    }
  }
}
