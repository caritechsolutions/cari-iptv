/// Classification of native player errors into something a viewer can read.
///
/// The raw text (ExoPlayer / AVPlayer) is never shown directly; it is kept in
/// [technical] for the "Details" toggle and for the QoE event so bad titles
/// can be found from the admin side.
enum PlaybackErrorKind {
  /// The device has no decoder for this stream (e.g. H.264 High 10 / 10-bit
  /// on phone hardware). Retrying cannot help; the title must be re-encoded.
  unsupportedFormat,

  /// Plain http:// blocked by the per-brand network policy / ATS.
  cleartextBlocked,
  certificate,
  notFound,
  forbidden,
  network,
  unknown,
}

class PlaybackError {
  const PlaybackError({required this.kind, required this.message, required this.technical, this.codec, this.mime, this.formatSupported});

  final PlaybackErrorKind kind;

  /// Plain-language explanation shown to the viewer.
  final String message;

  /// The raw error text, behind the "Details" toggle.
  final String technical;

  /// Codec string from the failing format, e.g. `avc1.6E0028`.
  final String? codec;

  /// Container/sample MIME types found in the error, e.g. `video/mp2t, video/avc`.
  final String? mime;

  /// ExoPlayer's `format_supported=...` verdict when present.
  final String? formatSupported;

  /// Whether a Retry button makes sense.
  bool get retryable => switch (kind) {
        PlaybackErrorKind.unsupportedFormat || PlaybackErrorKind.cleartextBlocked || PlaybackErrorKind.certificate || PlaybackErrorKind.forbidden => false,
        _ => true,
      };

  String headline({required bool live}) => switch (kind) {
        PlaybackErrorKind.unsupportedFormat => 'This title is in a format this device cannot play',
        PlaybackErrorKind.cleartextBlocked => 'This stream is not allowed on this device',
        _ => live ? 'This channel cannot be played right now' : 'This video cannot be played right now',
      };

  /// Fields for the `playback_error` QoE event.
  Map<String, dynamic> toQoeMetadata({required String streamUrl}) {
    final uri = Uri.tryParse(streamUrl);
    return {
      'kind': kind.name,
      'message': technical.length > 600 ? technical.substring(0, 600) : technical,
      'codec': ?codec,
      'mime': ?mime,
      'format_supported': ?formatSupported,
      'url_scheme': ?uri?.scheme,
      'stream_host': ?uri?.host,
    };
  }
}

final _codecRe = RegExp(r'\b(avc1|avc3|hvc1|hev1|vp08|vp09|av01|dvhe|dvh1|dvav|dva1|mp4a|ac-3|ec-3|opus|flac)(\.[0-9A-Za-z.]+)?', caseSensitive: false);
final _mimeRe = RegExp(r'\b(video|audio|application)/[A-Za-z0-9.+_-]+');
final _formatSupportedRe = RegExp(r'format_supported=([A-Z_]+)');

/// Decoder / unsupported-format signatures (ExoPlayer, MediaCodec, AVFoundation).
const _decoderSignals = [
  'mediacodecvideorenderer',
  'mediacodecaudiorenderer',
  'decoderinitializationexception',
  'decoder init failed',
  'no decoder',
  'no suitable decoder',
  'unsupported format',
  'unsupportedformat',
  'format_supported=no',
  'error_code_decoding',
  'error_code_decoder',
  'error_code_parsing_container_unsupported',
  'error_code_parsing_manifest_unsupported',
  'cannot decode',
  'avfoundationerrordomain code=-11828', // AVErrorFileFormatNotRecognized
  'avfoundationerrordomain code=-11800', // AVErrorUnknown (media format)
  'media format is not supported',
];

PlaybackError classifyPlaybackError(String raw, String url) {
  final lower = raw.toLowerCase();
  final host = Uri.tryParse(url)?.host ?? '';
  final isHttp = url.startsWith('http://');
  final codec = _codecRe.firstMatch(raw)?.group(0);
  final mimes = _mimeRe.allMatches(raw).map((m) => m.group(0)!).toSet();
  final mime = mimes.isEmpty ? null : mimes.join(', ');
  final formatSupported = _formatSupportedRe.firstMatch(raw)?.group(1);

  PlaybackError make(PlaybackErrorKind kind, String message) => PlaybackError(kind: kind, message: message, technical: raw, codec: codec, mime: mime, formatSupported: formatSupported);

  if (lower.contains('cleartext') || lower.contains('clear text') || lower.contains('app transport security') || (isHttp && (lower.contains('not permitted') || lower.contains('-1022')))) {
    return make(PlaybackErrorKind.cleartextBlocked, 'This stream uses an insecure http:// address ($host) that this app is not allowed to play. Ask your provider for an https:// stream.');
  }
  if (_decoderSignals.any(lower.contains) || (formatSupported != null && formatSupported.startsWith('NO'))) {
    final what = codec != null ? ' ($codec)' : '';
    return make(PlaybackErrorKind.unsupportedFormat, 'The video is encoded in a way this phone\'s hardware cannot decode$what. Retrying will not help; the title needs to be re-encoded by the provider.');
  }
  if (lower.contains('certificate') || lower.contains('ssl') || lower.contains('tls') || lower.contains('trust anchor')) {
    return make(PlaybackErrorKind.certificate, 'The stream server ($host) has an invalid security certificate.');
  }
  if (lower.contains('404') || lower.contains('not found')) return make(PlaybackErrorKind.notFound, 'The stream was not found on the server ($host).');
  if (lower.contains('403') || lower.contains('401') || lower.contains('forbidden')) return make(PlaybackErrorKind.forbidden, 'The stream server ($host) refused access.');
  if (lower.contains('unable to connect') || lower.contains('failed to connect') || lower.contains('unknownhost') || lower.contains('timeout') || lower.contains('network') || lower.contains('sockettimeout') || lower.contains('connection reset')) {
    return make(PlaybackErrorKind.network, 'Could not connect to the stream server ($host). Check your connection and try again.');
  }
  return make(PlaybackErrorKind.unknown, 'Something went wrong while playing this stream. Check your connection and try again.');
}
