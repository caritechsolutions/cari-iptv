/// Minimal HLS master (multivariant) playlist model and parser.
///
/// Only what the player needs for quality fallback and the quality menu:
/// the `#EXT-X-STREAM-INF` variants with their URI, bandwidth, resolution,
/// name and CODECS. Media playlists (with `#EXTINF`) are not masters and
/// parse to null.
class HlsVariant {
  const HlsVariant({required this.uri, this.bandwidth, this.width, this.height, this.name, this.codecs});

  final Uri uri;
  final int? bandwidth;
  final int? width;
  final int? height;
  final String? name;

  /// The CODECS attribute as written in the master (e.g. `avc1.640028,mp4a.40.2`), null when absent.
  final String? codecs;

  /// Video codec part of [codecs] (first entry that is not audio), null when absent.
  String? get videoCodec {
    final c = codecs;
    if (c == null) return null;
    for (final part in c.split(',')) {
      final p = part.trim();
      if (p.isEmpty) continue;
      if (p.startsWith('mp4a') || p.startsWith('ac-3') || p.startsWith('ec-3') || p.startsWith('opus') || p.startsWith('flac')) continue;
      return p;
    }
    return null;
  }

  /// "1080p", "720p" … from the resolution, else the NAME, else the bandwidth.
  String get label {
    if (height != null) return '${height}p';
    if (name != null && name!.isNotEmpty) return name!;
    if (bandwidth != null) return '${(bandwidth! / 1000).round()} kbps';
    return uri.pathSegments.isNotEmpty ? uri.pathSegments.last : uri.toString();
  }

  /// Ordering key: height first, then bandwidth.
  int get rank => (height ?? 0) * 1000000 + ((bandwidth ?? 0) ~/ 1000);

  @override
  String toString() => 'HlsVariant($label, ${bandwidth}bps, codecs=$codecs, $uri)';
}

class HlsMaster {
  HlsMaster({required this.url, required List<HlsVariant> variants}) : variants = List.unmodifiable(variants..sort((a, b) => b.rank.compareTo(a.rank)));

  final Uri url;

  /// Best first.
  final List<HlsVariant> variants;

  /// True when every variant carries a CODECS attribute. Then the native
  /// player can exclude undecodable variants itself and the app matches by
  /// exact codec string; without it the app falls back to the resolution rule.
  bool get hasCodecs => variants.isNotEmpty && variants.every((v) => v.codecs != null && v.codecs!.isNotEmpty);

  HlsVariant? byUri(Uri uri) => variants.where((v) => v.uri == uri).firstOrNull;
}

final _attrRe = RegExp(r'([A-Z0-9-]+)=("(?:[^"]*)"|[^,]*)');

Map<String, String> parseAttributes(String s) {
  final out = <String, String>{};
  for (final m in _attrRe.allMatches(s)) {
    var v = m.group(2)!;
    if (v.length >= 2 && v.startsWith('"') && v.endsWith('"')) v = v.substring(1, v.length - 1);
    out[m.group(1)!] = v;
  }
  return out;
}

/// Parses [text] fetched from [url]. Returns null when it is not a master
/// playlist (a media playlist or something else).
HlsMaster? parseHlsMaster(String text, Uri url) {
  final lines = text.split(RegExp(r'\r?\n')).map((l) => l.trim()).toList();
  if (lines.isEmpty || !lines.first.startsWith('#EXTM3U')) return null;
  if (lines.any((l) => l.startsWith('#EXTINF'))) return null;

  final variants = <HlsVariant>[];
  for (var i = 0; i < lines.length; i++) {
    final line = lines[i];
    if (!line.startsWith('#EXT-X-STREAM-INF:')) continue;
    final attrs = parseAttributes(line.substring('#EXT-X-STREAM-INF:'.length));
    // The URI is the next non-empty, non-comment line.
    String? uriLine;
    for (var j = i + 1; j < lines.length; j++) {
      if (lines[j].isEmpty || lines[j].startsWith('#')) continue;
      uriLine = lines[j];
      break;
    }
    if (uriLine == null) continue;
    int? w, h;
    final res = attrs['RESOLUTION'];
    if (res != null) {
      final parts = res.toLowerCase().split('x');
      if (parts.length == 2) {
        w = int.tryParse(parts[0]);
        h = int.tryParse(parts[1]);
      }
    }
    variants.add(HlsVariant(
      uri: url.resolve(uriLine),
      bandwidth: int.tryParse(attrs['BANDWIDTH'] ?? ''),
      width: w,
      height: h,
      name: attrs['NAME'],
      codecs: attrs['CODECS'],
    ));
  }
  if (variants.isEmpty) return null;
  return HlsMaster(url: url, variants: variants);
}
