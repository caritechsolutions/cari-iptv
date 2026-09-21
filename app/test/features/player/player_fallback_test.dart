// Graceful quality fallback: on a decoder failure the player switches to the
// best remaining rendition instead of showing an error, remembers the
// exclusion for the session, reports every failure with its outcome, and
// offers a manual quality menu that hides undecodable renditions.
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/player/hls/quality_memory.dart';
import 'package:cari_tv/features/player/ui/playback_request.dart';
import 'package:cari_tv/features/player/ui/player_error_panel.dart';
import 'package:cari_tv/features/player/ui/player_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';

import '../../support/test_harness.dart';
import 'hls_master_test.dart' show masterNoCodecs, masterWithCodecs;

const _masterUrl = 'https://vod1.example.com:8090/content/series-9-s1e3/master.m3u8';
const _episode = PlaybackRequest(contentType: 'episode', contentId: 33, title: 'Black Sails', subtitle: 'S1E3 · III.', streamUrl: _masterUrl, durationHint: 3300);

String exoError(int w, int h, String codec) =>
    'ExoPlaybackException: MediaCodecVideoRenderer error, index=0, reason=..., format=Format(1, null, null, video/mp2t, video/avc, $codec, 5500000, null, [$w, $h, 25.0, ColorInfo(BT709, Limited range, SDR SMPTE 170M, false, 8, 8)], [-1, -1]), format_supported=NO_UNSUPPORTED_TYPE';

void main() {
  late FakeVideoPlayerPlatform video;
  late TestRepos repos;
  late FakeMasterSource masters;
  late QualityMemory memory;

  setUp(() {
    video = installPlatformFakes();
    repos = TestRepos();
    masters = FakeMasterSource({_masterUrl: masterNoCodecs});
    memory = QualityMemory();
    when(() => repos.userContent.saveProgress(contentType: any(named: 'contentType'), contentId: any(named: 'contentId'), progress: any(named: 'progress'), duration: any(named: 'duration'))).thenAnswer((_) async {});
  });

  GoRouter router() => GoRouter(routes: [
        GoRoute(path: '/', builder: (context, _) => Scaffold(body: Center(child: TextButton(key: const Key('play'), onPressed: () => context.push('/player', extra: _episode), child: const Text('Play'))))),
        GoRoute(path: '/player', builder: (_, s) => PlayerScreen(request: s.extra as PlaybackRequest)),
      ]);

  Widget harness() => ProviderScope(
        overrides: [...testOverrides(auth: const AuthSignedIn(testUser), repos: repos, masterSource: masters), qualityMemoryProvider.overrideWithValue(memory)],
        child: MaterialApp.router(routerConfig: router()),
      );

  Future<void> open(WidgetTester tester) async {
    await tester.pumpWidget(harness());
    await tester.tap(find.byKey(const Key('play')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.byType(PlayerScreen), findsOneWidget);
  }

  Future<void> pumpSeconds(WidgetTester tester, double s) async {
    for (var i = 0; i < (s * 2).round(); i++) {
      await tester.pump(const Duration(milliseconds: 500));
    }
  }

  Finder errorScreen() => find.byType(PlayerErrorPanel);

  group('quality fallback', () {
    testWidgets('decoder failure switches to the best remaining rendition, resumes, notes it, and never shows the error screen', (tester) async {
      // Auto (master) climbs to 1080p High 10 after 2 s; 720p is High 10 too; 360p is fine.
      video.failures['master.m3u8'] = FakeFailure(exoError(1920, 1080, 'avc1.6E0028'), after: const Duration(seconds: 2));
      video.failures['stream_720p.m3u8'] = FakeFailure(exoError(1280, 720, 'avc1.6E001F'));
      await open(tester);
      video.position = const Duration(seconds: 42);
      await pumpSeconds(tester, 3); // position poll picks up 42 s, then the master fails

      expect(video.createdUrls, [_masterUrl, 'https://vod1.example.com:8090/content/series-9-s1e3/stream_720p.m3u8', 'https://vod1.example.com:8090/content/series-9-s1e3/stream_360p.m3u8']);
      expect(errorScreen(), findsNothing, reason: 'a playable rendition remained');
      expect(find.byKey(const Key('player-note')), findsOneWidget);
      expect(find.textContaining('360p'), findsWidgets);
      expect(video.calls, contains('seek:3'), reason: 'the fallback resumes at the previous position');
      expect(video.position, const Duration(seconds: 42));
      expect(video.disposed(1), isTrue);
      expect(video.disposed(2), isTrue);
      expect(video.calls, contains('play:3'));

      final t = memory.forTitle('episode:33');
      final master = (await masters.load(Uri.parse(_masterUrl)))!;
      expect(t.allowed(master).map((v) => v.label), ['360p']);
      expect(t.pinned.toString(), endsWith('stream_360p.m3u8'));
    });

    testWidgets('every failure is reported once with codec, content id and outcome; the recovered one is flagged', (tester) async {
      video.failures['master.m3u8'] = FakeFailure(exoError(1920, 1080, 'avc1.6E0028'), after: const Duration(seconds: 2));
      video.failures['stream_720p.m3u8'] = FakeFailure(exoError(1280, 720, 'avc1.6E001F'));
      await open(tester);
      video.position = const Duration(seconds: 42);
      await pumpSeconds(tester, 3);
      // 360p is playing from 42 s; advancing past it proves recovery.
      video.position = const Duration(seconds: 44);
      await pumpSeconds(tester, 1);

      final errors = repos.analytics.qoeOf('playback_error');
      expect(errors.length, 2, reason: 'one event per failed rendition: ${errors.map((e) => e.metadata)}');
      final first = errors[0];
      expect(first.contentId, 33);
      expect(first.contentType, 'episode');
      expect(first.metadata['codec'], 'avc1.6E0028');
      expect(first.metadata['rendition'], '1080p');
      expect(first.metadata['recovered'], isFalse, reason: 'its fallback (720p) failed too');
      expect(first.metadata['outcome'], 'failed');
      expect(first.metadata['fallback_to'], '720p');
      expect(first.metadata['rule'], 'resolution');
      final second = errors[1];
      expect(second.metadata['codec'], 'avc1.6E001F');
      expect(second.metadata['rendition'], '720p');
      expect(second.metadata['recovered'], isTrue);
      expect(second.metadata['outcome'], 'recovered');
      expect(second.metadata['fallback_to'], '360p');
      expect(second.metadata['message'], startsWith('ExoPlaybackException'));
    });

    testWidgets('no playable rendition left: the format screen is shown, without Retry, and all failures are reported as failed', (tester) async {
      video.failures['master.m3u8'] = FakeFailure(exoError(1920, 1080, 'avc1.6E0028'), after: const Duration(seconds: 1));
      video.failures['stream_720p.m3u8'] = FakeFailure(exoError(1280, 720, 'avc1.6E001F'));
      video.failures['stream_360p.m3u8'] = FakeFailure(exoError(640, 360, 'avc1.6E001E'));
      await open(tester);
      await pumpSeconds(tester, 3);

      expect(errorScreen(), findsOneWidget);
      expect(find.text('This title is in a format this device cannot play'), findsOneWidget);
      expect(find.text('Retry'), findsNothing);
      expect(video.createdUrls.length, 3);
      final errors = repos.analytics.qoeOf('playback_error');
      expect(errors.length, 3);
      expect(errors.map((e) => e.metadata['recovered']), everyElement(isFalse));
      expect(errors.last.metadata['fallback_to'], isNull);
    });

    testWidgets('no flapping: reopening the title starts on the remembered rendition and Auto is gone from the menu', (tester) async {
      video.failures['master.m3u8'] = FakeFailure(exoError(1920, 1080, 'avc1.6E0028'), after: const Duration(seconds: 1));
      video.failures['stream_720p.m3u8'] = FakeFailure(exoError(1280, 720, 'avc1.6E001F'));
      await open(tester);
      await pumpSeconds(tester, 3);
      expect(video.createdUrls.last, endsWith('stream_360p.m3u8'));

      // Leave and come back.
      await tester.binding.handlePopRoute();
      await pumpSeconds(tester, 1);
      expect(find.byType(PlayerScreen), findsNothing);
      await tester.tap(find.byKey(const Key('play')));
      await pumpSeconds(tester, 1.5);
      expect(find.byType(PlayerScreen), findsOneWidget);
      expect(video.createdUrls.length, 4, reason: 'exactly one player for the second session: ${video.createdUrls}');
      expect(video.createdUrls.last, endsWith('stream_360p.m3u8'), reason: 'the master (Auto) must not be tried again');
      expect(errorScreen(), findsNothing);
      expect(repos.analytics.qoeOf('playback_error').length, 2, reason: 'no new failures on replay');

      await tester.tap(find.byTooltip('Quality'));
      await tester.pumpAndSettle();
      expect(find.text('Auto'), findsNothing, reason: 'adaptive selection could pick the bad rendition again');
      expect(find.text('1080p'), findsNothing);
      expect(find.text('720p'), findsNothing);
      expect(find.widgetWithText(CheckedPopupMenuItem<String>, '360p'), findsOneWidget);
    });

    testWidgets('master with CODECS: only the exact codec string is excluded and the failed variant is identified by it', (tester) async {
      masters.put(_masterUrl, masterWithCodecs);
      // Error text without a resolution: the codec string identifies the rendition.
      video.failures['master.m3u8'] = const FakeFailure('MediaCodecVideoRenderer error, format=Format(video/avc, avc1.6E0028), format_supported=NO_EXCEEDS_CAPABILITIES', after: Duration(seconds: 1));
      await open(tester);
      await pumpSeconds(tester, 2.5);
      expect(video.createdUrls.last, endsWith('stream_720p.m3u8'), reason: '720p has a different codec string and stays allowed');
      final t = memory.forTitle('episode:33');
      expect(t.excludedCodecs, {'avc1.6E0028'});
      final err = repos.analytics.qoeOf('playback_error');
      expect(err, isEmpty, reason: 'outcome not known yet');
      video.position = const Duration(seconds: 1);
      await pumpSeconds(tester, 1);
      expect(repos.analytics.qoeOf('playback_error').single.metadata['rule'], 'codecs');
    });
  });

  group('quality menu', () {
    testWidgets('lists Auto and every rendition, switching opens the variant directly at the same position, Auto returns to the master', (tester) async {
      await open(tester);
      video.position = const Duration(seconds: 30);
      await pumpSeconds(tester, 1);

      await tester.tap(find.byTooltip('Quality'));
      await tester.pumpAndSettle();
      expect(find.text('Auto'), findsOneWidget);
      expect(find.text('1080p'), findsOneWidget);
      expect(find.text('720p'), findsOneWidget);
      expect(find.text('360p'), findsOneWidget);

      await tester.tap(find.text('720p'));
      await tester.pump();
      await pumpSeconds(tester, 1);
      expect(video.createdUrls.last, endsWith('stream_720p.m3u8'));
      expect(video.calls, contains('seek:2'));
      expect(video.position, const Duration(seconds: 30));
      expect(video.disposed(1), isTrue);
      expect(find.textContaining('Quality: 720p'), findsOneWidget);
      expect(memory.forTitle('episode:33').pinned.toString(), endsWith('stream_720p.m3u8'));

      // Controls may have auto-hidden; tap the stage to show them again.
      if (find.byTooltip('Quality').evaluate().isEmpty) {
        await tester.tap(find.byType(PlayerScreen));
        await tester.pump();
      }
      await tester.tap(find.byTooltip('Quality'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Auto'));
      await tester.pump();
      await pumpSeconds(tester, 1);
      expect(video.createdUrls.last, _masterUrl);
      expect(memory.forTitle('episode:33').pinned, isNull);
    });

    testWidgets('no quality menu when the stream is not a master playlist', (tester) async {
      masters = FakeMasterSource();
      await open(tester);
      expect(find.byTooltip('Quality'), findsNothing);
    });
  });
}
