import 'package:cari_tv/core/util/url_resolver.dart';
import 'package:cari_tv/features/player/ui/playback_error.dart';
import 'package:cari_tv/features/player/ui/player_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('isExternalWatchUrl', () {
    test('YouTube and Vimeo links are external', () {
      expect(isExternalWatchUrl('https://www.youtube.com/watch?v=abc'), isTrue);
      expect(isExternalWatchUrl('https://youtu.be/abc'), isTrue);
      expect(isExternalWatchUrl('https://vimeo.com/123'), isTrue);
    });
    test('HLS and MP4 are streams', () {
      expect(isExternalWatchUrl('https://vod1.example.com:8090/content/movie-1/master.m3u8'), isFalse);
      expect(isExternalWatchUrl('https://headend.example.com/wplg-abc/video.m3u8'), isFalse);
      expect(isExternalWatchUrl('http://cdn.example.com/a.mp4'), isFalse);
      expect(isExternalWatchUrl(null), isFalse);
    });
  });

  group('friendlyPlaybackError', () {
    test('cleartext block gives a specific message', () {
      final m = _PlayerErrors.friendly('Cleartext HTTP traffic to cdn.example.com not permitted', 'http://cdn.example.com/live.m3u8');
      expect(m, contains('insecure http://'));
      expect(m, contains('cdn.example.com'));
    });
    test('iOS ATS block gives the same message', () {
      final m = _PlayerErrors.friendly('The resource could not be loaded because the App Transport Security policy requires the use of a secure connection.', 'http://cdn.example.com/live.m3u8');
      expect(m, contains('insecure http://'));
    });
    test('certificate problems are explained', () {
      expect(_PlayerErrors.friendly('javax.net.ssl.SSLHandshakeException: Trust anchor for certification path not found', 'https://vod1.example.com:8090/m.m3u8'), contains('security certificate'));
    });
    test('unknown errors never reach the viewer raw', () {
      final m = _PlayerErrors.friendly('Something odd', 'https://x/y.m3u8');
      expect(m, isNot(contains('Something odd')));
      expect(classifyPlaybackError('Something odd', 'https://x/y.m3u8').technical, 'Something odd');
    });
  });

  group('classifyPlaybackError', () {
    const exo = 'ExoPlaybackException: MediaCodecVideoRenderer error, index=0, reason=..., format=Format(1, null, null, video/mp2t, video/avc, avc1.6E0028, 5500000, null, [1920, 1080, 25.0, ColorInfo(BT709, Limited range, SDR SMPTE 170M, false, 8, 8)], [-1, -1]), format_supported=NO_UNSUPPORTED_TYPE';
    const url = 'https://vod1.example.com:8090/content/series-9-s1e3/master.m3u8';

    test('decoder failure is an unsupported format, not retryable, with the codec string', () {
      final e = classifyPlaybackError(exo, url);
      expect(e.kind, PlaybackErrorKind.unsupportedFormat);
      expect(e.retryable, isFalse);
      expect(e.codec, 'avc1.6E0028');
      expect(e.mime, contains('video/avc'));
      expect(e.formatSupported, 'NO_UNSUPPORTED_TYPE');
      expect(e.headline(live: false), 'This title is in a format this device cannot play');
      expect(e.message, contains('avc1.6E0028'));
      expect(e.message, isNot(contains('ExoPlaybackException')));
      expect(e.technical, exo);
    });

    test('QoE metadata carries codec, kind and host', () {
      final m = classifyPlaybackError(exo, url).toQoeMetadata(streamUrl: url);
      expect(m['codec'], 'avc1.6E0028');
      expect(m['kind'], 'unsupportedFormat');
      expect(m['format_supported'], 'NO_UNSUPPORTED_TYPE');
      expect(m['stream_host'], 'vod1.example.com');
      expect(m['url_scheme'], 'https');
      expect(m['message'], startsWith('ExoPlaybackException'));
    });

    test('network problems stay retryable and are not decoder errors', () {
      final e = classifyPlaybackError('Source error: java.net.UnknownHostException: Unable to resolve host "vod1.example.com"', url);
      expect(e.kind, PlaybackErrorKind.network);
      expect(e.retryable, isTrue);
      expect(e.codec, isNull);
    });

    test('a 10-bit HEVC decoder failure is classified from format_supported alone', () {
      final e = classifyPlaybackError('Playback error, format=Format(video/mp4, video/hevc, hvc1.2.4.L120.B0), format_supported=NO_EXCEEDS_CAPABILITIES', url);
      expect(e.kind, PlaybackErrorKind.unsupportedFormat);
      expect(e.codec, 'hvc1.2.4.L120.B0');
    });

    test('cleartext keeps its own kind and is not retryable', () {
      final e = classifyPlaybackError('Cleartext HTTP traffic to cdn.example.com not permitted', 'http://cdn.example.com/live.m3u8');
      expect(e.kind, PlaybackErrorKind.cleartextBlocked);
      expect(e.retryable, isFalse);
    });
  });
}

class _PlayerErrors {
  static String friendly(String raw, String url) => PlayerScreenErrors.friendlyPlaybackError(raw, url);
}
