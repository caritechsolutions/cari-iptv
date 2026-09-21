import 'package:cari_tv/features/player/ui/player_fullscreen.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

/// Records every SystemChrome orientation / system-UI call the player makes,
/// exactly as it reaches the platform channel.
class _ChromeLog {
  final List<String> calls = [];

  void install() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
      switch (call.method) {
        case 'SystemChrome.setPreferredOrientations':
          final list = (call.arguments as List).cast<String>().map((s) => s.split('.').last).join('+');
          calls.add('orientations:$list');
        case 'SystemChrome.setEnabledSystemUIMode':
          calls.add('uiMode:${(call.arguments as String).split('.').last}');
        default:
          break;
      }
      return null;
    });
  }

  void uninstall() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null);
  }

  void clear() => calls.clear();

  bool get lastIsPortrait => calls.lastWhere((c) => c.startsWith('orientations:'), orElse: () => '') == 'orientations:portraitUp+portraitDown';
  bool get lastIsLandscape => calls.lastWhere((c) => c.startsWith('orientations:'), orElse: () => '') == 'orientations:landscapeLeft+landscapeRight';
  String get lastUiMode => calls.lastWhere((c) => c.startsWith('uiMode:'), orElse: () => '');
}

const _stageKey = Key('stage');
const _belowKey = Key('below');

/// A home page that pushes the player shell as its own route, like the app does.
Widget _harness(PlayerFullscreen controller) {
  return MaterialApp(
    home: Builder(
      builder: (context) => Scaffold(
        body: Center(
          child: TextButton(
            key: const Key('open'),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => PlayerShell(
                  controller: controller,
                  stage: Stack(
                    key: _stageKey,
                    fit: StackFit.expand,
                    children: [
                      const ColoredBox(color: Colors.black),
                      Positioned(right: 0, bottom: 0, child: FullscreenToggleButton(controller: controller)),
                    ],
                  ),
                  below: const Text('Title under the video', key: _belowKey),
                ),
              ),
            ),
            child: const Text('Open player'),
          ),
        ),
      ),
    ),
  );
}

Size _stageSize(WidgetTester tester) => tester.getSize(find.byKey(_stageKey));

void main() {
  late _ChromeLog chrome;
  late PlayerFullscreen controller;

  setUp(() {
    chrome = _ChromeLog()..install();
    controller = PlayerFullscreen();
  });

  tearDown(() {
    chrome.uninstall();
    controller.dispose();
  });

  Future<void> openPlayer(WidgetTester tester) async {
    await tester.pumpWidget(_harness(controller));
    await tester.tap(find.byKey(const Key('open')));
    await tester.pumpAndSettle();
    expect(find.byType(PlayerShell), findsOneWidget);
    chrome.clear();
  }

  group('PlayerShell full screen', () {
    testWidgets('opens in portrait: 16:9 stage at the top, panel below, no orientation change', (tester) async {
      await openPlayer(tester);
      expect(controller.isFullscreen, isFalse);
      expect(find.byKey(_belowKey), findsOneWidget);
      final size = _stageSize(tester);
      expect(size.width / size.height, closeTo(16 / 9, 0.01));
      expect(size.width, tester.view.physicalSize.width / tester.view.devicePixelRatio);
      expect(chrome.calls.where((c) => c.startsWith('orientations:')), isEmpty, reason: 'opening the player must not rotate the phone');
    });

    testWidgets('button enters full screen (landscape + immersive) and exits (portrait + bars)', (tester) async {
      await openPlayer(tester);

      await tester.tap(find.byIcon(Icons.fullscreen_rounded));
      await tester.pumpAndSettle();
      expect(controller.isFullscreen, isTrue);
      expect(chrome.lastIsLandscape, isTrue, reason: chrome.calls.toString());
      expect(chrome.lastUiMode, 'uiMode:immersiveSticky');
      expect(find.byKey(_belowKey), findsNothing);
      expect(_stageSize(tester), tester.view.physicalSize / tester.view.devicePixelRatio, reason: 'stage fills the screen');
      expect(find.byIcon(Icons.fullscreen_exit_rounded), findsOneWidget);

      chrome.clear();
      await tester.tap(find.byIcon(Icons.fullscreen_exit_rounded));
      await tester.pumpAndSettle();
      expect(controller.isFullscreen, isFalse);
      expect(chrome.lastIsPortrait, isTrue, reason: chrome.calls.toString());
      expect(chrome.lastUiMode, 'uiMode:edgeToEdge');
      expect(find.byKey(_belowKey), findsOneWidget);
      expect(find.byType(PlayerShell), findsOneWidget, reason: 'exiting full screen must not close the player');
    });

    testWidgets('back button in full screen only exits full screen; second back closes the player', (tester) async {
      await openPlayer(tester);
      await tester.tap(find.byIcon(Icons.fullscreen_rounded));
      await tester.pumpAndSettle();
      expect(controller.isFullscreen, isTrue);
      chrome.clear();

      // System back (Android back button / predictive back gesture).
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.byType(PlayerShell), findsOneWidget, reason: 'first back stays on the player');
      expect(controller.isFullscreen, isFalse);
      expect(chrome.lastIsPortrait, isTrue, reason: chrome.calls.toString());
      expect(chrome.lastUiMode, 'uiMode:edgeToEdge');

      chrome.clear();
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.byType(PlayerShell), findsNothing, reason: 'second back closes the player');
      expect(find.text('Open player'), findsOneWidget);
      expect(chrome.lastIsPortrait, isTrue, reason: 'dispose restores portrait again: ${chrome.calls}');
    });

    testWidgets('back button in portrait closes the player directly', (tester) async {
      await openPlayer(tester);
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.byType(PlayerShell), findsNothing);
      expect(chrome.lastIsPortrait, isTrue);
      expect(chrome.lastUiMode, 'uiMode:edgeToEdge');
    });

    testWidgets('programmatic exit (playback ended / stream error) restores portrait and keeps the player open', (tester) async {
      await openPlayer(tester);
      await controller.enter();
      await tester.pumpAndSettle();
      expect(controller.isFullscreen, isTrue);
      chrome.clear();

      await controller.exit(); // what _reportError() and the end-of-playback path call
      await tester.pumpAndSettle();
      expect(controller.isFullscreen, isFalse);
      expect(chrome.lastIsPortrait, isTrue);
      expect(chrome.lastUiMode, 'uiMode:edgeToEdge');
      expect(find.byType(PlayerShell), findsOneWidget);
      expect(find.byKey(_belowKey), findsOneWidget);

      // Calling exit again when already out is a harmless no-op.
      chrome.clear();
      await controller.exit();
      expect(chrome.calls, isEmpty);
    });

    testWidgets('leaving the player while in full screen (route popped from code) restores portrait on dispose', (tester) async {
      await openPlayer(tester);
      await controller.enter();
      await tester.pumpAndSettle();
      chrome.clear();

      final navigator = tester.state<NavigatorState>(find.byType(Navigator));
      navigator.pop(); // e.g. session expired redirect or context.pop() from the error view
      await tester.pumpAndSettle();
      expect(find.byType(PlayerShell), findsNothing);
      expect(controller.isFullscreen, isFalse);
      expect(chrome.lastIsPortrait, isTrue, reason: chrome.calls.toString());
      expect(chrome.lastUiMode, 'uiMode:edgeToEdge');
    });
  });
}
