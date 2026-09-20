import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../models/auth_tokens.dart';

/// Persists the session in the platform keystore (Android Keystore / iOS Keychain).
abstract class TokenStore {
  Future<AuthTokens?> read();
  Future<void> write(AuthTokens tokens);
  Future<void> clear();
}

class SecureTokenStore implements TokenStore {
  SecureTokenStore([FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'auth_tokens_v1';
  final FlutterSecureStorage _storage;
  AuthTokens? _memo;

  @override
  Future<AuthTokens?> read() async {
    if (_memo != null) return _memo;
    try {
      final raw = await _storage.read(key: _key);
      if (raw == null || raw.isEmpty) return null;
      _memo = AuthTokens.fromJson(jsonDecode(raw) as Map<String, dynamic>);
      return _memo;
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> write(AuthTokens tokens) async {
    _memo = tokens;
    await _storage.write(key: _key, value: jsonEncode(tokens.toJson()));
  }

  @override
  Future<void> clear() async {
    _memo = null;
    await _storage.delete(key: _key);
  }
}

/// In-memory implementation for tests.
class MemoryTokenStore implements TokenStore {
  AuthTokens? tokens;

  @override
  Future<AuthTokens?> read() async => tokens;

  @override
  Future<void> write(AuthTokens t) async => tokens = t;

  @override
  Future<void> clear() async => tokens = null;
}
