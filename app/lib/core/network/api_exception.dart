/// Typed error raised by the API layer. `code` mirrors the backend's
/// `error.code` (UNAUTHORIZED, TOKEN_EXPIRED, AUTH_FAILED, EMAIL_NOT_VERIFIED,
/// VALIDATION_ERROR, NOT_FOUND, PAYMENT_REQUIRED, …) or one of the client
/// codes below.
class ApiException implements Exception {
  const ApiException({
    required this.code,
    required this.message,
    this.statusCode,
    this.details = const {},
  });

  static const codeNetwork = 'NETWORK_ERROR';
  static const codeTimeout = 'TIMEOUT';
  static const codeParse = 'PARSE_ERROR';
  static const codeUnknown = 'UNKNOWN';

  final String code;
  final String message;
  final int? statusCode;

  /// Extra keys the backend sometimes adds to the error object
  /// (e.g. `needs_verification`, `email`).
  final Map<String, dynamic> details;

  bool get isUnauthorized => statusCode == 401 || code == 'UNAUTHORIZED' || code == 'TOKEN_EXPIRED';
  bool get isNetwork => code == codeNetwork || code == codeTimeout;
  bool get isNotFound => statusCode == 404 || code == 'NOT_FOUND';
  bool get isPaymentRequired => statusCode == 402 || code == 'PAYMENT_REQUIRED';

  @override
  String toString() => 'ApiException($code${statusCode != null ? ' $statusCode' : ''}): $message';
}
