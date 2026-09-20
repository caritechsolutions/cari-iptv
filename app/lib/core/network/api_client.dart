import 'dart:async';

import 'package:dio/dio.dart';

import '../../config/app_config.dart';
import '../storage/cache_store.dart';
import '../storage/token_store.dart';
import 'api_envelope.dart';
import 'api_exception.dart';
import 'auth_interceptor.dart';

/// Result of a GET that may have been served from the offline cache.
class ApiResult {
  const ApiResult(this.envelope, {this.fromCache = false});
  final ApiEnvelope envelope;
  final bool fromCache;
}

/// Thin wrapper over dio: base URL, auth, envelope parsing, error mapping and
/// an optional offline cache keyed by URL and content scope.
class ApiClient {
  ApiClient({
    required this.config,
    required this.tokenStore,
    this.cache,
    Dio? dio,
    Dio? plainDio,
  }) {
    final base = BaseOptions(
      baseUrl: config.apiV1,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json'},
      responseType: ResponseType.json,
    );
    this.plainDio = plainDio ?? Dio(base);
    this.dio = dio ?? Dio(base);
    this.dio.interceptors.add(
      AuthInterceptor(
        tokenStore: tokenStore,
        plainDio: this.plainDio,
        refreshPath: '/auth/refresh',
        onSessionExpired: (reason) => _sessionExpired.add(reason),
      ),
    );
  }

  final AppConfig config;
  final TokenStore tokenStore;
  final CacheStore? cache;
  late final Dio dio;
  late final Dio plainDio;

  final _sessionExpired = StreamController<String>.broadcast();

  /// Emits the backend error code when a refresh fails and the session is cleared.
  Stream<String> get sessionExpired => _sessionExpired.stream;

  // ---------------------------------------------------------------------------

  /// GET with optional caching. When [cacheScope] is set the response is stored
  /// and returned on network failure (`fromCache: true`). When [preferCache]
  /// is true a cached copy is returned immediately without hitting the network.
  Future<ApiResult> get(
    String path, {
    Map<String, dynamic>? query,
    bool auth = true,
    String? cacheScope,
    bool preferCache = false,
    CancelToken? cancelToken,
  }) async {
    final key = _cacheKey(path, query);
    if (cacheScope != null && preferCache) {
      final cached = cache?.get(key);
      if (cached != null) return ApiResult(ApiEnvelope.parse(cached), fromCache: true);
    }
    try {
      final res = await dio.get<dynamic>(
        path,
        queryParameters: query,
        options: Options(extra: {AuthInterceptor.extraAuth: auth}),
        cancelToken: cancelToken,
      );
      final env = ApiEnvelope.parse(res.data, statusCode: res.statusCode);
      if (cacheScope != null) {
        unawaited(cache?.put(key, res.data, scope: cacheScope));
      }
      return ApiResult(env);
    } on DioException catch (e) {
      final mapped = _map(e);
      if (cacheScope != null && mapped.isNetwork) {
        final cached = cache?.get(key);
        if (cached != null) return ApiResult(ApiEnvelope.parse(cached), fromCache: true);
      }
      throw mapped;
    }
  }

  Future<ApiEnvelope> post(
    String path, {
    Object? body,
    bool auth = true,
    bool formEncoded = false,
    CancelToken? cancelToken,
  }) async {
    try {
      final res = await dio.post<dynamic>(
        path,
        data: body,
        options: Options(
          extra: {AuthInterceptor.extraAuth: auth},
          contentType: formEncoded ? Headers.formUrlEncodedContentType : Headers.jsonContentType,
        ),
        cancelToken: cancelToken,
      );
      return ApiEnvelope.parse(res.data, statusCode: res.statusCode);
    } on DioException catch (e) {
      throw _map(e);
    }
  }

  // ---------------------------------------------------------------------------

  String _cacheKey(String path, Map<String, dynamic>? query) {
    if (query == null || query.isEmpty) return path;
    final entries = query.entries.where((e) => e.value != null).map((e) => '${e.key}=${e.value}').toList()..sort();
    return '$path?${entries.join('&')}';
  }

  ApiException _map(DioException e) {
    if (e.error is ApiException) return e.error as ApiException;
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return const ApiException(code: ApiException.codeTimeout, message: 'The server took too long to respond');
      case DioExceptionType.connectionError:
      case DioExceptionType.unknown:
      case DioExceptionType.transformTimeout:
        return const ApiException(code: ApiException.codeNetwork, message: 'No internet connection');
      case DioExceptionType.cancel:
        return const ApiException(code: 'CANCELLED', message: 'Request cancelled');
      case DioExceptionType.badCertificate:
        return const ApiException(code: ApiException.codeNetwork, message: 'Secure connection failed');
      case DioExceptionType.badResponse:
        final status = e.response?.statusCode;
        try {
          ApiEnvelope.parse(e.response?.data, statusCode: status);
          return ApiException(code: 'HTTP_$status', message: 'Request failed ($status)', statusCode: status);
        } on ApiException catch (parsed) {
          return parsed;
        }
    }
  }

  void dispose() {
    _sessionExpired.close();
    dio.close(force: true);
    plainDio.close(force: true);
  }
}
