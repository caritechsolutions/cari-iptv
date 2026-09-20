import '../../../core/network/api_client.dart';
import '../../../core/util/json.dart';
import '../../../models/auth_tokens.dart';
import '../../../models/entitlements.dart';
import '../../../models/user.dart';

class LoginResult {
  const LoginResult(this.tokens, this.user);
  final AuthTokens tokens;
  final User user;
}

/// `/auth/*` endpoints. See docs/API_DISCOVERY.md §1.
class AuthRepository {
  AuthRepository(this._api);
  final ApiClient _api;

  Future<LoginResult> login({required String identity, required String password, String? deviceName}) async {
    final env = await _api.post('/auth/login', auth: false, body: {
      'identity': identity,
      'password': password,
      'device_type': 'mobile',
      'device_name': ?deviceName,
    });
    final data = env.dataAsJson;
    final tokens = AuthTokens.fromLogin(data);
    await _api.tokenStore.write(tokens);
    return LoginResult(tokens, User.fromJson(asJson(data['user'])));
  }

  Future<String> register({
    required String firstName,
    required String lastName,
    required String email,
    required String password,
    required String passwordConfirm,
    String? phone,
    String? country,
    String? birthday,
  }) async {
    final env = await _api.post('/auth/register', auth: false, body: {
      'first_name': firstName,
      'last_name': lastName,
      'email': email,
      'password': password,
      'password_confirm': passwordConfirm,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (country != null && country.isNotEmpty) 'country': country,
      if (birthday != null && birthday.isNotEmpty) 'birthday': birthday,
    });
    return asString(env.dataAsJson['message'], 'Account created. Please check your email to verify your account.');
  }

  Future<String> resendVerification(String email) async {
    final env = await _api.post('/auth/resend-verification', auth: false, body: {'email': email});
    return asString(env.dataAsJson['message']);
  }

  Future<String> forgotPassword(String email) async {
    final env = await _api.post('/auth/forgot-password', auth: false, body: {'email': email});
    return asString(env.dataAsJson['message'], 'If an account exists with that email, reset instructions have been sent.');
  }

  Future<User> me() async {
    final res = await _api.get('/auth/me');
    return User.fromJson(res.envelope.dataAsJson);
  }

  /// Revokes the refresh token server-side (best effort) and clears local storage.
  Future<void> logout() async {
    final tokens = await _api.tokenStore.read();
    await _api.tokenStore.clear();
    if (tokens != null && tokens.refreshToken.isNotEmpty) {
      try {
        await _api.post('/auth/logout', auth: false, body: {'refresh_token': tokens.refreshToken});
      } catch (_) {
        // Offline logout is still a logout.
      }
    }
  }

  Future<void> updateProfile({bool? adultEnabled, String? parentalPin, bool clearPin = false}) async {
    await _api.post('/auth/update-profile', body: {
      'adult_enabled': ?adultEnabled,
      'parental_pin': ?parentalPin,
      if (clearPin) 'parental_pin': null,
    });
  }

  Future<void> deleteAccount(String password) async {
    await _api.post('/auth/delete-account', body: {'password': password});
    await _api.tokenStore.clear();
  }

  Future<Entitlements> entitlements() async {
    final res = await _api.get('/auth/entitlements', cacheScope: 'user');
    return Entitlements.fromJson(res.envelope.dataAsJson);
  }

  /// Free/trial packages only; paid packages raise `PAYMENT_REQUIRED` (402).
  Future<String> subscribe(int packageId) async {
    final env = await _api.post('/auth/subscribe', body: {'package_id': packageId});
    return asString(env.dataAsJson['message'], 'Subscribed');
  }

  Future<String> unsubscribe(int packageId) async {
    final env = await _api.post('/auth/unsubscribe', body: {'package_id': packageId});
    return asString(env.dataAsJson['message'], 'Subscription cancelled');
  }
}
