import 'package:cari_tv/core/util/time.dart';
import 'package:cari_tv/core/util/url_resolver.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const r = UrlResolver('https://player.example.com');

  test('root-relative upload paths are prefixed with the site origin', () {
    expect(r.resolve('/uploads/vod/12/poster_poster.webp'), 'https://player.example.com/uploads/vod/12/poster_poster.webp');
  });

  test('absolute TMDB URLs are untouched', () {
    expect(r.resolve('https://image.tmdb.org/t/p/w500/x.jpg'), 'https://image.tmdb.org/t/p/w500/x.jpg');
  });

  test('empty and null give null', () {
    expect(r.resolve(null), isNull);
    expect(r.resolve('  '), isNull);
  });

  test('protocol-relative gets https', () {
    expect(r.resolve('//cdn.example.com/a.png'), 'https://cdn.example.com/a.png');
  });

  group('parseApiDateTime', () {
    test('parses EPG Z-stamped values as UTC', () {
      final t = parseApiDateTime('2025-01-15T14:00:00Z')!;
      expect(t.isUtc, isTrue);
      expect(t.hour, 14);
    });

    test('parses raw MySQL datetimes as UTC', () {
      final t = parseApiDateTime('2025-01-15 14:30:00')!;
      expect(t.isUtc, isTrue);
      expect(t.minute, 30);
    });

    test('returns null for garbage', () {
      expect(parseApiDateTime('nope'), isNull);
      expect(parseApiDateTime(null), isNull);
    });
  });
}
