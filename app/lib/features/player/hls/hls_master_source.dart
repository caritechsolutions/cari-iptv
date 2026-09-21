import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'hls_master.dart';

/// Fetches and parses a master playlist. Tests override the provider.
abstract class HlsMasterSource {
  /// Null when the URL is not an HLS master (media playlist, MP4, network error).
  Future<HlsMaster?> load(Uri url);
}

class DioHlsMasterSource implements HlsMasterSource {
  DioHlsMasterSource({Dio? dio}) : _dio = dio ?? Dio(BaseOptions(connectTimeout: const Duration(seconds: 5), receiveTimeout: const Duration(seconds: 5)));
  final Dio _dio;

  @override
  Future<HlsMaster?> load(Uri url) async {
    if (!url.path.toLowerCase().endsWith('.m3u8') && !url.path.toLowerCase().contains('.m3u8')) return null;
    try {
      final res = await _dio.getUri<String>(url, options: Options(responseType: ResponseType.plain));
      final text = res.data;
      if (text == null || text.isEmpty) return null;
      // Relative variant URIs resolve against the final URL after redirects.
      final base = res.realUri;
      return parseHlsMaster(text, base);
    } catch (_) {
      return null;
    }
  }
}

final hlsMasterSourceProvider = Provider<HlsMasterSource>((ref) => DioHlsMasterSource());
