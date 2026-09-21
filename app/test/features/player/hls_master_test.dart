import 'package:cari_tv/features/player/hls/hls_master.dart';
import 'package:cari_tv/features/player/hls/quality_memory.dart';
import 'package:flutter_test/flutter_test.dart';

final _url = Uri.parse('https://vod1.example.com:8090/content/series-9-s1e3/master.m3u8');

const masterNoCodecs = '''
#EXTM3U
#EXT-X-VERSION:3

#EXT-X-STREAM-INF:BANDWIDTH=500000,RESOLUTION=640x360,NAME="360p"
stream_360p.m3u8
#EXT-X-STREAM-INF:BANDWIDTH=3000000,RESOLUTION=1280x720,NAME="720p"
stream_720p.m3u8
#EXT-X-STREAM-INF:BANDWIDTH=5500000,RESOLUTION=1920x1080,NAME="1080p"
stream_1080p.m3u8
''';

const masterWithCodecs = '''
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-STREAM-INF:BANDWIDTH=500000,RESOLUTION=640x360,NAME="360p",CODECS="avc1.64001E,mp4a.40.2",SUBTITLES="subs"
stream_360p.m3u8
#EXT-X-STREAM-INF:BANDWIDTH=3000000,RESOLUTION=1280x720,NAME="720p",CODECS="avc1.6E001F,mp4a.40.2"
stream_720p.m3u8
#EXT-X-STREAM-INF:BANDWIDTH=5500000,RESOLUTION=1920x1080,NAME="1080p",CODECS="avc1.6E0028,mp4a.40.2"
stream_1080p.m3u8
''';

void main() {
  group('parseHlsMaster', () {
    test('parses variants best-first with resolved URIs and no CODECS', () {
      final m = parseHlsMaster(masterNoCodecs, _url)!;
      expect(m.variants.map((v) => v.label), ['1080p', '720p', '360p']);
      expect(m.variants.first.uri.toString(), 'https://vod1.example.com:8090/content/series-9-s1e3/stream_1080p.m3u8');
      expect(m.variants.first.bandwidth, 5500000);
      expect(m.variants.first.width, 1920);
      expect(m.hasCodecs, isFalse);
      expect(m.variants.first.codecs, isNull);
    });

    test('quoted CODECS with commas is kept intact and hasCodecs is true', () {
      final m = parseHlsMaster(masterWithCodecs, _url)!;
      expect(m.hasCodecs, isTrue);
      expect(m.variants.first.codecs, 'avc1.6E0028,mp4a.40.2');
      expect(m.variants.first.videoCodec, 'avc1.6E0028');
      expect(m.variants.last.videoCodec, 'avc1.64001E');
    });

    test('a media playlist is not a master', () {
      expect(parseHlsMaster('#EXTM3U\n#EXT-X-TARGETDURATION:4\n#EXTINF:4.0,\nseg0.ts\n', _url), isNull);
      expect(parseHlsMaster('not a playlist', _url), isNull);
    });
  });

  group('QualityMemory.fail', () {
    test('master without CODECS: resolution rule excludes the failed rendition and everything above it', () {
      final memory = QualityMemory();
      final m = parseHlsMaster(masterNoCodecs, _url)!;
      final d = memory.fail('episode:3', m, failed: m.variants[1] /* 720p */, failedCodec: 'avc1.6E001F');
      expect(d.rule, 'resolution');
      expect(d.excluded.map((v) => v.label), ['1080p', '720p']);
      expect(d.next!.label, '360p');
      final t = memory.forTitle('episode:3');
      expect(t.allowed(m).map((v) => v.label), ['360p']);
      expect(t.pinned, d.next!.uri);
      expect(t.hasExclusions, isTrue);
    });

    test('master without CODECS and unknown failed rendition: assumes the best still allowed', () {
      final memory = QualityMemory();
      final m = parseHlsMaster(masterNoCodecs, _url)!;
      final d1 = memory.fail('episode:3', m);
      expect(d1.failed!.label, '1080p');
      expect(d1.next!.label, '720p');
      final d2 = memory.fail('episode:3', m);
      expect(d2.failed!.label, '720p');
      expect(d2.next!.label, '360p');
      final d3 = memory.fail('episode:3', m);
      expect(d3.next, isNull, reason: 'nothing playable remains');
    });

    test('master with CODECS: exact codec match only, lower rendition with the same codec is excluded too', () {
      final memory = QualityMemory();
      final m = parseHlsMaster(masterWithCodecs, _url)!;
      // 720p and 1080p both High 10 but different levels → different strings → only the failed one goes.
      final d = memory.fail('movie:7', m, failed: m.variants.first, failedCodec: 'avc1.6E0028');
      expect(d.rule, 'codecs');
      expect(d.excluded.map((v) => v.label), ['1080p']);
      expect(d.next!.label, '720p', reason: 'exact matching must not touch a different codec string');
      expect(memory.forTitle('movie:7').excludedCodecs, {'avc1.6E0028'});
    });

    test('master with CODECS: a second variant sharing the codec string is excluded with the first', () {
      final memory = QualityMemory();
      final text = masterWithCodecs.replaceAll('avc1.6E001F', 'avc1.6E0028');
      final m = parseHlsMaster(text, _url)!;
      final d = memory.fail('movie:8', m, failed: m.variants.first, failedCodec: 'avc1.6E0028');
      expect(d.excluded.map((v) => v.label), ['1080p', '720p']);
      expect(d.next!.label, '360p');
    });

    test('memory is per title', () {
      final memory = QualityMemory();
      final m = parseHlsMaster(masterNoCodecs, _url)!;
      memory.fail('episode:3', m, failed: m.variants.first);
      expect(memory.peek('episode:4'), isNull);
      expect(memory.forTitle('episode:4').hasExclusions, isFalse);
    });
  });
}
