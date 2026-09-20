import 'dart:async';

import 'package:dio/dio.dart';

import '../../models/auth_tokens.dart';
import '../storage/token_store.dart';
import '../util/json.dart';
import 'api_envelope.dart';
import 'api_exception.dart';

/// Attaches `Authorization: Bearer` and keeps the access token fresh.
///
/// - Refreshes pre-emptively when the token expires within 30 s (mirrors the
///   web player) and retries once on a 401.
/// - Extends [QueuedInterceptor], so concurrent requests wait while a refresh
///   is in flight (single-flight refresh without extra locking).
/// - Refresh and retry go through a separate plain [Dio] to avoid re-entering
///   this queued interceptor.
/// - When refresh fails with 401 (`TOKEN_EXPIRED`, e.g. evicted by the device
///   limit) the session is cleared and [onSessionExpired] fires.
class AuthInterceptor extends QueuedInterceptor {
  AuthInterceptor({
    required this.tokenStore,
    required this.plainDio,
    required this.refreshPath,
    this.onSessionExpired,
    DateTime Function()? clock,
  }) : _clock = clock ?? (() => DateTime.now().toUtc());

  final TokenStore tokenStore;
  final Dio plainDio;
  final String refreshPath;
  final void Function(String reason)? onSessionExpired;
  final DateTime Function() _clock;

  static const extraAuth = 'auth';
  static const extraRetried = 'auth_retried';

  bool _requiresAuth(RequestOptions o) => o.extra[extraAuth] != false;

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    if (!_requiresAuth(options)) {
      handler.next(options);
      return;
    }

    final stored = await tokenStore.read();
    if (stored == null || !stored.isValid) {
      // No session: let the request go; the server answers 401.
      handler.next(options);
      return;
    }

    var tokens = stored;
    if (tokens.isExpiring(now: _clock())) {
      try {
        tokens = await _refresh(tokens);
      } on ApiException catch (e) {
        if (e.isUnauthorized) {
          handler.reject(DioException(requestOptions: options, error: e, type: DioExceptionType.cancel));
          return;
        }
        // Network trouble during refresh: try the request with the old token.
      }
    }

    options.headers['Authorization'] = 'Bearer ${tokens.accessToken}';
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    final options = err.requestOptions;
    final status = err.response?.statusCode;

    if (status != 401 || !_requiresAuth(options) || options.extra[extraRetried] == true) {
      handler.next(err);
      return;
    }

    final tokens = await tokenStore.read();
    if (tokens == null || !tokens.isValid) {
      handler.next(err);
      return;
    }

    try {
      final fresh = await _refresh(tokens);
      final retryOptions = options.copyWith(
        headers: {...options.headers, 'Authorization': 'Bearer ${fresh.accessToken}'},
        extra: {...options.extra, extraRetried: true},
      );
      final response = await plainDio.fetch<dynamic>(retryOptions);
      handler.resolve(response);
    } on ApiException catch (e) {
      handler.reject(DioException(requestOptions: options, error: e, response: err.response, type: DioExceptionType.badResponse));
    } on DioException catch (e) {
      handler.next(e);
    }
  }

  /// Exchanges the refresh token for a new access token. Throws [ApiException].
  Future<AuthTokens> _refresh(AuthTokens current) async {
    try {
      final res = await plainDio.post<dynamic>(refreshPath, data: {'refresh_token': current.refreshToken});
      final env = ApiEnvelope.parse(res.data, statusCode: res.statusCode);
      final data = env.dataAsJson;
      final updated = current.withAccess(
        asString(data['access_token']),
        asInt(data['expires_in'], 3600),
        now: _clock(),
      );
      if (updated.accessToken.isEmpty) {
        throw const ApiException(code: ApiException.codeParse, message: 'Refresh returned no access token');
      }
      await tokenStore.write(updated);
      return updated;
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 401 || status == 400 || status == 403) {
        ApiException parsed;
        try {
          ApiEnvelope.parse(e.response?.data, statusCode: status);
          parsed = ApiException(code: 'TOKEN_EXPIRED', message: 'Session expired', statusCode: status);
        } on ApiException catch (inner) {
          parsed = inner;
        }
        await tokenStore.clear();
        onSessionExpired?.call(parsed.code);
        throw parsed;
      }
      throw ApiException(
        code: e.type == DioExceptionType.connectionTimeout || e.type == DioExceptionType.receiveTimeout
            ? ApiException.codeTimeout
            : ApiException.codeNetwork,
        message: 'Could not reach the server',
      );
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await tokenStore.clear();
        onSessionExpired?.call(e.code);
      }
      rethrow;
    }
  }
}
