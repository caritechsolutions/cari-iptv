import '../core/util/json.dart';

/// Which third-party sign-in buttons the operator enabled
/// (`GET /auth/providers`, also `auth` in `/app/config`).
class SignInProviders {
  const SignInProviders({this.googleEnabled = false, this.googleClientId, this.appleEnabled = false, this.appleServicesId});

  final bool googleEnabled;

  /// Google *Web* client id; the Android/iOS plugin uses it as the server
  /// client id so the ID token it returns is audience-matched by the backend.
  final String? googleClientId;
  final bool appleEnabled;
  final String? appleServicesId;

  static const none = SignInProviders();

  bool get any => googleEnabled || appleEnabled;

  factory SignInProviders.fromJson(Object? j) {
    if (j is! Map) return none;
    final m = asJson(j);
    final g = asJson(m['google']);
    final a = asJson(m['apple']);
    return SignInProviders(
      googleEnabled: asBool(g['enabled']) && asStringOrNull(g['client_id']) != null,
      googleClientId: asStringOrNull(g['client_id']),
      appleEnabled: asBool(a['enabled']),
      appleServicesId: asStringOrNull(a['services_id']),
    );
  }
}
