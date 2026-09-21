// Proves the fix for "video keeps playing after back": leaving the player by
// any route pauses and disposes the native player, saves progress, and going
// to the background pauses playback.
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/player/ui/playback_request.dart';
import 'package:cari_tv/features/player/ui/player_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';

import '../../support/test_harness.dart';

const _vod = PlaybackRequest(contentType: 'movie', contentId: 7, title: 'Test movie', streamUrl: 'https://example.com/movie.m3u8', durationHint: 5400);

void main() {
  late FakeVideoPlayerPlatform video;
  late TestRepos repos;

  setUp(() {
    video = installPlatformFakes();
    repos = TestRepos();
    when(() => repos.userContent.saveProgress(
          contentType: any(named: 'contentType'),
          contentId: any(named: 'contentId'),
          progress: any(named: 'progress'),
          duration: any(named: 'duration'),
        )).thenAnswer((_) async {});
  });

  /// Real go_router stack like the app: a home page that pushes /player.
  GoRouter router() => GoRouter(
        routes: [
          GoRoute(
            path: '/',
            builder: (context, _) => Scaffold(
              body: Center(
                child: TextButton(key: const Key('play'), onPressed: () => context.push('/player', extra: _vod), child: const Text('Play')),
              ),
            ),
          ),
          GoRoute(path: '/player', builder: (_, s) => PlayerScreen(request: s.extra as PlaybackRequest)),
        ],
      );

  Widget harness() => ProviderScope(
        overrides: testOverrides(auth: const AuthSignedIn(testUser), repos: repos),
        child: MaterialApp.router(routerConfig: router()),
      );

  /// Opens the player and lets it reach the playing state at 42 s.
  Future<void> openAndPlay(WidgetTester tester) async {
    await tester.pumpWidget(harness());
    await tester.tap(find.byKey(const Key('play')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500)); // route transition + ad lookup
    await tester.pump(const Duration(milliseconds: 500)); // controller initialised → play
    expect(find.byType(PlayerScreen), findsOneWidget);
    expect(video.calls, contains('play:1'), reason: video.calls.toString());
    video.position = const Duration(seconds: 42);
    await tester.pump(const Duration(seconds: 1)); // position poll picks up 42 s
  }

  group('PlayerScreen release', () {
    testWidgets('system back closes the player, pauses then disposes the native player, and saves progress', (tester) async {
      await openAndPlay(tester);
      expect(video.disposed(1), isFalse);

      await tester.binding.handlePopRoute();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.byType(PlayerScreen), findsNothing, reason: 'back must close the player route');

      final calls = video.callsFor(1);
      expect(calls, contains('pause:1'), reason: calls.toString());
      expect(calls, contains('dispose:1'), reason: calls.toString());
      expect(calls.lastIndexOf('pause:1'), lessThan(calls.indexOf('dispose:1')), reason: 'pause before release: $calls');
      expect(calls.last, 'dispose:1', reason: 'nothing may talk to the player after release: $calls');

      final saved = verify(() => repos.userContent.saveProgress(contentType: 'movie', contentId: 7, progress: captureAny(named: 'progress'), duration: any(named: 'duration'))).captured;
      expect(saved, isNotEmpty, reason: 'watch progress must be saved on the way out');
      expect(saved.last, 42);
      expect(repos.analytics.events, contains('watch_abandon'));
      expect(repos.analytics.flushes, 1);
    });

    testWidgets('in-app back arrow does the same as system back', (tester) async {
      await openAndPlay(tester);
      await tester.tap(find.byIcon(Icons.arrow_back_rounded).first);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.byType(PlayerScreen), findsNothing);
      expect(video.callsFor(1), containsAllInOrder(['pause:1', 'dispose:1']));
    });

    testWidgets('going to the background pauses playback and saves progress without releasing', (tester) async {
      await openAndPlay(tester);
      final before = video.callsFor(1).length;

      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pump();
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
      await tester.pump();

      final after = video.callsFor(1).sublist(before);
      expect(after, contains('pause:1'), reason: after.toString());
      expect(video.disposed(1), isFalse, reason: 'backgrounding pauses, it does not release');
      verify(() => repos.userContent.saveProgress(contentType: 'movie', contentId: 7, progress: 42, duration: any(named: 'duration'))).called(1);

      // Coming back does not auto-resume; the user presses play.
      final plays = video.callsFor(1).where((c) => c == 'play:1').length;
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pump();
      expect(video.callsFor(1).where((c) => c == 'play:1').length, plays);

      // Leaving afterwards still releases.
      await tester.binding.handlePopRoute();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pump(const Duration(milliseconds: 500));
      expect(video.disposed(1), isTrue);
    });

    testWidgets('leaving before the stream is ready still releases the player', (tester) async {
      video.initDelay = const Duration(seconds: 5); // stream never becomes ready before we leave
      await tester.pumpWidget(harness());
      await tester.tap(find.byKey(const Key('play')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      // Controller created; leave immediately.
      await tester.binding.handlePopRoute();
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.byType(PlayerScreen), findsNothing);
      expect(video.calls.where((c) => c.startsWith('create:')).length, 1);
      expect(video.disposed(1), isTrue, reason: video.calls.toString());
      expect(video.calls, isNot(contains('play:1')), reason: 'never played, so nothing to pause');
    });
  });
}
