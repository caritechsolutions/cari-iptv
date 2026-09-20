import '../util/json.dart';
import 'api_exception.dart';

/// Normalises the backend's three response envelopes into one object:
///
///  1. `{"data": …, "meta": {…}}`   (content / app / epg / recommendations)
///  2. `{"data": …}`                 (auth endpoints, no meta)
///  3. `{"success": true, …}`        (ads, analytics — payload is the whole body)
///
/// Errors are `{"error": {"code", "message", …extras}}`.
class ApiEnvelope {
  const ApiEnvelope({required this.data, required this.meta, required this.raw});

  /// The payload: `data` for shapes 1–2, the whole body for shape 3.
  final Object? data;
  final Json meta;
  final Json raw;

  Json get dataAsJson => asJson(data);
  List<Json> get dataAsList => asJsonList(data);

  String? get version => asStringOrNull(meta['version']);
  int? get total => asIntOrNull(meta['total']);

  /// Parses a decoded JSON body. Throws [ApiException] when the body carries
  /// an `error` object or (for shape 3) `success: false`.
  static ApiEnvelope parse(Object? body, {int? statusCode}) {
    if (body is! Map) {
      if (statusCode != null && statusCode >= 400) {
        throw ApiException(code: 'HTTP_$statusCode', message: 'Request failed ($statusCode)', statusCode: statusCode);
      }
      throw ApiException(code: ApiException.codeParse, message: 'Unexpected response format', statusCode: statusCode);
    }
    final json = asJson(body);

    if (json['error'] is Map) {
      final err = asJson(json['error']);
      final extras = Map<String, dynamic>.from(err)
        ..remove('code')
        ..remove('message');
      throw ApiException(
        code: asString(err['code'], 'ERROR'),
        message: asString(err['message'], 'Request failed'),
        statusCode: statusCode,
        details: extras,
      );
    }

    if (json.containsKey('data')) {
      return ApiEnvelope(data: json['data'], meta: asJson(json['meta']), raw: json);
    }

    if (json.containsKey('success')) {
      if (!asBool(json['success'])) {
        throw ApiException(
          code: asString(json['code'], 'REQUEST_FAILED'),
          message: asString(json['message'], 'Request failed'),
          statusCode: statusCode,
        );
      }
      return ApiEnvelope(data: json, meta: const {}, raw: json);
    }

    // Unknown but non-error shape: expose as-is.
    return ApiEnvelope(data: json, meta: const {}, raw: json);
  }
}
