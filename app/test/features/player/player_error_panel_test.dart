import 'package:cari_tv/features/player/ui/playback_error.dart';
import 'package:cari_tv/features/player/ui/player_error_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

const _exo = 'ExoPlaybackException: MediaCodecVideoRenderer error, index=0, format=Format(1, null, null, video/mp2t, video/avc, avc1.6E0028, 5500000, null, [1920, 1080, 25.0]), format_supported=NO_UNSUPPORTED_TYPE';

Widget _wrap(Widget child) => MaterialApp(
      theme: ThemeData.dark(),
      home: Scaffold(body: Column(children: [const SizedBox(height: 200), Expanded(child: child)])),
    );

void main() {
  testWidgets('unsupported format: plain headline, no raw text, no Retry, details behind a toggle', (tester) async {
    final error = classifyPlaybackError(_exo, 'https://vod1.example.com:8090/x/master.m3u8');
    var backs = 0;
    await tester.pumpWidget(_wrap(PlayerErrorPanel(error: error, live: false, title: 'Black Sails', subtitle: 'S1E3 · III.', onBack: () => backs++, onRetry: error.retryable ? () {} : null)));

    expect(find.text('Black Sails'), findsOneWidget);
    expect(find.text('S1E3 · III.'), findsOneWidget);
    expect(find.text('This title is in a format this device cannot play'), findsOneWidget);
    expect(find.textContaining('ExoPlaybackException'), findsNothing, reason: 'raw exception must stay hidden until Details');
    expect(find.text('Retry'), findsNothing, reason: 'retry is pointless for a decoder failure');
    expect(find.text('Back'), findsOneWidget);
    expect(tester.takeException(), isNull, reason: 'no layout overflow');

    await tester.tap(find.text('Details'));
    await tester.pump();
    expect(find.textContaining('ExoPlaybackException'), findsOneWidget);
    expect(find.textContaining('avc1.6E0028'), findsWidgets);
    expect(find.text('Hide details'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.tap(find.text('Back'));
    expect(backs, 1);
  });

  testWidgets('network error keeps Retry and a generic headline', (tester) async {
    final error = classifyPlaybackError('java.net.SocketTimeoutException: timeout', 'https://vod1.example.com/x.m3u8');
    var retries = 0;
    await tester.pumpWidget(_wrap(PlayerErrorPanel(error: error, live: true, title: 'Channel 5', onBack: () {}, onRetry: () => retries++)));
    expect(find.text('This channel cannot be played right now'), findsOneWidget);
    await tester.tap(find.text('Retry'));
    expect(retries, 1);
  });

  testWidgets('a very long technical message never overflows (scrolls instead)', (tester) async {
    final error = classifyPlaybackError('$_exo${' more text' * 200}', 'https://vod1.example.com/x.m3u8');
    await tester.pumpWidget(_wrap(PlayerErrorPanel(error: error, live: false, title: 'T', onBack: () {})));
    await tester.tap(find.text('Details'));
    await tester.pump();
    expect(tester.takeException(), isNull);
    expect(find.byType(SingleChildScrollView), findsOneWidget);
  });
}
