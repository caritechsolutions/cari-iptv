import 'package:cari_tv/core/util/url_resolver.dart';
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
    test('unknown errors pass through', () {
      expect(_PlayerErrors.friendly('Something odd', 'https://x/y.m3u8'), 'Something odd');
    });
  });
}

class _PlayerErrors {
  static String friendly(String raw, String url) => PlayerScreenErrors.friendlyPlaybackError(raw, url);
}
