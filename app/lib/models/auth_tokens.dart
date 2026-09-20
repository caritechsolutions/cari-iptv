import '../core/util/json.dart';

/// Session tokens as issued by `POST /auth/login` (+ refreshed access token).
class AuthTokens {
  const AuthTokens({
    required this.accessToken,
    required this.refreshToken,
    required this.expiresAt,
  });

  final String accessToken;
  final String refreshToken;

  /// Absolute expiry of the access token (UTC).
  final DateTime expiresAt;

  /// Builds from the login payload. `expires_in` is seconds (3600).
  factory AuthTokens.fromLogin(Json data, {DateTime? now}) {
    final issued = now ?? DateTime.now().toUtc();
    return AuthTokens(
      accessToken: asString(data['access_token']),
      refreshToken: asString(data['refresh_token']),
      expiresAt: issued.add(Duration(seconds: asInt(data['expires_in'], 3600))),
    );
  }

  factory AuthTokens.fromJson(Json json) => AuthTokens(
        accessToken: asString(json['access_token']),
        refreshToken: asString(json['refresh_token']),
        expiresAt: DateTime.tryParse(asString(json['expires_at']))?.toUtc() ?? DateTime.fromMillisecondsSinceEpoch(0, isUtc: true),
      );

  Json toJson() => {
        'access_token': accessToken,
        'refresh_token': refreshToken,
        'expires_at': expiresAt.toIso8601String(),
      };

  AuthTokens withAccess(String accessToken, int expiresIn, {DateTime? now}) => AuthTokens(
        accessToken: accessToken,
        refreshToken: refreshToken,
        expiresAt: (now ?? DateTime.now().toUtc()).add(Duration(seconds: expiresIn)),
      );

  /// True when the access token expires within [margin] (default 30 s, like the web player).
  bool isExpiring({Duration margin = const Duration(seconds: 30), DateTime? now}) =>
      (now ?? DateTime.now().toUtc()).add(margin).isAfter(expiresAt);

  bool get isValid => accessToken.isNotEmpty && refreshToken.isNotEmpty;
}
