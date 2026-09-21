import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'hls_master.dart';

/// Result of applying a decoder failure to a master playlist.
class FallbackDecision {
  const FallbackDecision({required this.failed, required this.excluded, required this.next, required this.rule});

  /// The rendition believed to have failed (null when it could not be told).
  final HlsVariant? failed;

  /// Everything excluded by this failure (the failed one included).
  final List<HlsVariant> excluded;

  /// Best rendition still allowed, null when none remains.
  final HlsVariant? next;

  /// `codecs` (exact codec match) or `resolution` (master without CODECS).
  final String rule;
}

/// What the app remembers about one title for the rest of the session so a
/// bad rendition is never tried twice (no flapping).
class TitleQuality {
  final Set<Uri> excludedUris = {};
  final Set<String> excludedCodecs = {};

  /// Rendition the title should start on instead of the master (after a
  /// fallback or a manual choice). Null = Auto (master).
  Uri? pinned;

  bool get hasExclusions => excludedUris.isNotEmpty || excludedCodecs.isNotEmpty;

  bool allows(HlsVariant v) {
    if (excludedUris.contains(v.uri)) return false;
    final c = v.videoCodec;
    if (c != null && excludedCodecs.contains(c)) return false;
    return true;
  }

  List<HlsVariant> allowed(HlsMaster master) => master.variants.where(allows).toList();
}

/// Session-scoped memory of undecodable renditions, keyed by title.
class QualityMemory {
  final Map<String, TitleQuality> _titles = {};

  static String keyFor(String contentType, int contentId) => '$contentType:$contentId';

  TitleQuality forTitle(String key) => _titles.putIfAbsent(key, TitleQuality.new);

  TitleQuality? peek(String key) => _titles[key];

  /// Applies a decoder failure and returns what to do next.
  ///
  /// - Master with CODECS on every variant: exclude every variant whose
  ///   video codec string equals the failed one (exact match). The native
  ///   player's own capability check excludes the rest in Auto mode.
  /// - Master without CODECS: the other renditions' profiles are unknown, so
  ///   exclude the failed rendition and every rendition of equal or higher
  ///   rank (the bad renditions are the upper part of one encode ladder).
  FallbackDecision fail(String key, HlsMaster master, {HlsVariant? failed, String? failedCodec}) {
    final t = forTitle(key);
    final allowedBefore = t.allowed(master);
    // If the failed rendition is unknown (Auto mode with no resolution in the
    // error), assume adaptive selection climbed to the best still allowed.
    final f = failed ?? (allowedBefore.isNotEmpty ? allowedBefore.first : null);
    final excluded = <HlsVariant>[];
    String rule;
    if (master.hasCodecs) {
      rule = 'codecs';
      final codec = f?.videoCodec ?? failedCodec;
      if (codec != null) t.excludedCodecs.add(codec);
      if (f != null) t.excludedUris.add(f.uri);
      for (final v in master.variants) {
        if (!allowedBefore.contains(v)) continue;
        if (v.uri == f?.uri || (codec != null && v.videoCodec == codec)) excluded.add(v);
      }
    } else {
      rule = 'resolution';
      if (f != null) {
        for (final v in master.variants) {
          if (!allowedBefore.contains(v)) continue;
          if (v.rank >= f.rank) {
            t.excludedUris.add(v.uri);
            excluded.add(v);
          }
        }
      }
    }
    final remaining = t.allowed(master);
    final next = remaining.isNotEmpty ? remaining.first : null;
    t.pinned = next?.uri;
    return FallbackDecision(failed: f, excluded: excluded, next: next, rule: rule);
  }
}

/// Kept alive for the whole session on purpose.
final qualityMemoryProvider = Provider<QualityMemory>((ref) => QualityMemory());
