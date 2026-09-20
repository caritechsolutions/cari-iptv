import 'package:cari_tv/core/network/api_envelope.dart';
import 'package:cari_tv/core/network/api_exception.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('data/meta envelope', () {
    final env = ApiEnvelope.parse({
      'data': [{'id': 1}],
      'meta': {'version': 'abc', 'total': '42', 'server_time': '2026-09-20T00:00:00Z', 'api_version': '1.0'},
    });
    expect(env.dataAsList.length, 1);
    expect(env.version, 'abc');
    expect(env.total, 42);
  });

  test('bare data envelope (auth endpoints)', () {
    final env = ApiEnvelope.parse({'data': {'saved': true}});
    expect(env.dataAsJson['saved'], true);
    expect(env.meta, isEmpty);
  });

  test('success envelope (ads / analytics) exposes the whole body', () {
    final env = ApiEnvelope.parse({'success': true, 'ads': [], 'count': 0});
    expect(env.dataAsJson['count'], 0);
  });

  test('success:false throws', () {
    expect(() => ApiEnvelope.parse({'success': false, 'message': 'Missing required fields'}), throwsA(isA<ApiException>()));
  });

  test('error envelope throws with code, status and extras', () {
    try {
      ApiEnvelope.parse({
        'error': {'code': 'EMAIL_NOT_VERIFIED', 'message': 'Please verify', 'needs_verification': true, 'email': 'a@b.com'},
      }, statusCode: 401);
      fail('should throw');
    } on ApiException catch (e) {
      expect(e.code, 'EMAIL_NOT_VERIFIED');
      expect(e.statusCode, 401);
      expect(e.details['needs_verification'], true);
      expect(e.details['email'], 'a@b.com');
      expect(e.isUnauthorized, isTrue);
    }
  });

  test('non-map body with error status throws HTTP code', () {
    expect(
      () => ApiEnvelope.parse('<html>', statusCode: 502),
      throwsA(isA<ApiException>().having((e) => e.code, 'code', 'HTTP_502')),
    );
  });
}
