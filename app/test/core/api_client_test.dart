import 'package:cari_tv/config/app_config.dart';
import 'package:cari_tv/core/network/api_client.dart';
import 'package:cari_tv/core/network/api_exception.dart';
import 'package:cari_tv/core/storage/token_store.dart';
import 'package:cari_tv/models/auth_tokens.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http_mock_adapter/http_mock_adapter.dart';

void main() {
  late MemoryTokenStore store;
  late Dio dio;
  late Dio plain;
  late DioAdapter dioMock;
  late DioAdapter plainMock;
  late ApiClient client;

  final base = BaseOptions(baseUrl: AppConfig.dev.apiV1, responseType: ResponseType.json);

  setUp(() {
    store = MemoryTokenStore();
    dio = Dio(base);
    plain = Dio(base);
    dioMock = DioAdapter(dio: dio);
    plainMock = DioAdapter(dio: plain);
    client = ApiClient(config: AppConfig.dev, tokenStore: store, dio: dio, plainDio: plain);
  });

  AuthTokens tokens({bool expired = false}) => AuthTokens(
        accessToken: 'old',
        refreshToken: 'r1',
        expiresAt: DateTime.now().toUtc().add(Duration(minutes: expired ? -5 : 30)),
      );

  test('adds bearer header from the token store', () async {
    store.tokens = tokens();
    dioMock.onGet('/auth/me', (s) => s.reply(200, {'data': {'id': 1}}), headers: {'Authorization': 'Bearer old'});
    final res = await client.get('/auth/me');
    expect(res.envelope.dataAsJson['id'], 1);
  });

  test('refreshes pre-emptively when the access token is expiring', () async {
    store.tokens = tokens(expired: true);
    plainMock.onPost('/auth/refresh', (s) => s.reply(200, {'data': {'access_token': 'new', 'expires_in': 3600, 'token_type': 'Bearer'}}), data: Matchers.any);
    dioMock.onGet('/movies', (s) => s.reply(200, {'data': [], 'meta': {'total': 0}}), headers: {'Authorization': 'Bearer new'});
    await client.get('/movies');
    expect(store.tokens!.accessToken, 'new');
    expect(store.tokens!.refreshToken, 'r1', reason: 'refresh token is not rotated by the backend');
  });

  test('retries once after a 401 with a refreshed token', () async {
    store.tokens = tokens();
    dioMock.onGet('/channels', (s) => s.reply(401, {'error': {'code': 'UNAUTHORIZED', 'message': 'Invalid or expired token'}}), data: Matchers.any);
    plainMock.onPost('/auth/refresh', (s) => s.reply(200, {'data': {'access_token': 'new', 'expires_in': 3600}}), data: Matchers.any);
    plainMock.onGet('/channels', (s) => s.reply(200, {'data': [{'id': 5}], 'meta': {}}), headers: {'Authorization': 'Bearer new'});
    final res = await client.get('/channels');
    expect(res.envelope.dataAsList.single['id'], 5);
  });

  test('refresh failure clears the session and reports the reason', () async {
    store.tokens = tokens(expired: true);
    plainMock.onPost('/auth/refresh', (s) => s.reply(401, {'error': {'code': 'TOKEN_EXPIRED', 'message': 'Invalid or expired refresh token'}}), data: Matchers.any);
    final reasons = <String>[];
    final sub = client.sessionExpired.listen(reasons.add);
    await expectLater(client.get('/movies'), throwsA(isA<ApiException>().having((e) => e.isUnauthorized, 'unauthorized', true)));
    await Future<void>.delayed(Duration.zero);
    expect(store.tokens, isNull);
    expect(reasons, ['TOKEN_EXPIRED']);
    await sub.cancel();
  });

  test('public endpoints send no bearer even with a session', () async {
    store.tokens = tokens();
    dioMock.onGet('/ads/overlay-settings', (s) => s.reply(200, {'success': true, 'settings': {'banner_enabled': 1}}), data: Matchers.any);
    final res = await client.get('/ads/overlay-settings', auth: false);
    expect(res.envelope.dataAsJson['settings'], isA<Map>());
  });

  test('backend error envelope maps to ApiException with code', () async {
    dioMock.onPost('/auth/login', (s) => s.reply(401, {'error': {'code': 'AUTH_FAILED', 'message': 'Invalid credentials'}}), data: Matchers.any);
    await expectLater(
      client.post('/auth/login', body: {'identity': 'x', 'password': 'y'}, auth: false),
      throwsA(isA<ApiException>().having((e) => e.code, 'code', 'AUTH_FAILED').having((e) => e.statusCode, 'status', 401)),
    );
  });

  test('402 is exposed as payment required', () async {
    store.tokens = tokens();
    dioMock.onPost('/auth/subscribe', (s) => s.reply(402, {'error': {'code': 'PAYMENT_REQUIRED', 'message': 'Payment needed'}}), data: Matchers.any);
    await expectLater(
      client.post('/auth/subscribe', body: {'package_id': 2}),
      throwsA(isA<ApiException>().having((e) => e.isPaymentRequired, 'payment', true)),
    );
  });
}
