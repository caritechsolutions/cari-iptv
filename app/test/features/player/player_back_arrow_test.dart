// One back arrow in the player: the one in the controls overlay, which hides
// with the controls. The loading overlay must not draw a second one on top
// of it (two arrows were drawn slightly offset while the stream was opening).
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/player/ui/playback_request.dart';
import 'package:cari_tv/features/player/ui/player_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:video_player/video_player.dart';

import '../../support/test_harness.dart';

const _vod = PlaybackRequest(contentType: 'movie', contentId: 7, title: 'Test movie', streamUrl: 'https://example.com/movie.m3u8', durationHint: 5400);
final _live = PlaybackRequest.channel(id: 1, title: 'ABC', streamUrl: 'https://live.example.com/1/master.m3u8');

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

  Widget harness(PlaybackRequest req) => ProviderScope(
        overrides: testOverrides(auth: const AuthSignedIn(testUser), repos: repos),
        child: MaterialApp.router(
          routerConfig: GoRouter(
            routes: [
              GoRoute(
                path: '/',
                builder: (context, _) => Scaffold(
                  body: Center(child: TextButton(key: const Key('play'), onPressed: () => context.push('/player', extra: req), child: const Text('Play'))),
                ),
              ),
              GoRoute(path: '/player', builder: (_, s) => PlayerScreen(request: s.extra as PlaybackRequest)),
            ],
          ),
        ),
      );

  Finder arrows() => find.byIcon(Icons.arrow_back_rounded);

  for (final (name, req) in [('VOD', _vod), ('live', _live)]) {
    testWidgets('$name player: exactly one back arrow while opening, while playing, and none with the controls hidden', (tester) async {
      // Phone-sized viewport: the live panel under the 16:9 stage needs the height.
      tester.view.physicalSize = const Size(480, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.reset);
      video.initDelay = const Duration(seconds: 3); // hold the stream in the opening state
      await tester.pumpWidget(harness(req));
      await tester.tap(find.byKey(const Key('play')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500)); // route transition + ad lookup → content phase, stream opening
      expect(find.byType(PlayerScreen), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsWidgets, reason: 'still opening');
      expect(arrows(), findsOneWidget, reason: 'while the stream opens, with the controls shown');

      await tester.pump(const Duration(seconds: 3)); // controller initialised → play
      expect(video.calls, contains('play:1'), reason: video.calls.toString());
      expect(arrows(), findsOneWidget, reason: 'while playing with the controls shown');

      // The controls auto-hide 4 s into playback, and the arrow goes with them.
      await tester.pump(const Duration(seconds: 5));
      expect(find.byIcon(Icons.pause_circle_filled_rounded), findsNothing, reason: 'controls hidden');
      expect(arrows(), findsNothing, reason: 'controls hidden');

      // A tap on the video brings them back, with the one arrow.
      await tester.tap(find.byType(VideoPlayer));
      await tester.pump(const Duration(milliseconds: 350)); // past the double-tap window
      expect(find.byIcon(Icons.pause_circle_filled_rounded), findsOneWidget, reason: 'controls shown again');
      expect(arrows(), findsOneWidget, reason: 'controls shown again');

      // The remaining arrow closes the player.
      await tester.tap(arrows());
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 500));
      expect(find.byType(PlayerScreen), findsNothing);
    });
  }
}
