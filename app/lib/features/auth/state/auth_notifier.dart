import 'dart:async';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../models/entitlements.dart';
import '../../../models/user.dart';
import '../../repositories.dart';

/// Session state. `restoring` while the stored session is checked at start.
sealed class AuthState {
  const AuthState();
}

class AuthRestoring extends AuthState {
  const AuthRestoring();
}

class AuthSignedOut extends AuthState {
  const AuthSignedOut({this.message});

  /// Shown on the login screen (e.g. "signed out on another device").
  final String? message;
}

class AuthSignedIn extends AuthState {
  const AuthSignedIn(this.user);
  final User user;
}

class AuthNotifier extends Notifier<AuthState> {
  StreamSubscription<String>? _expirySub;

  @override
  AuthState build() {
    final api = ref.watch(apiClientProvider);
    _expirySub?.cancel();
    _expirySub = api.sessionExpired.listen(_onSessionExpired);
    ref.onDispose(() => _expirySub?.cancel());
    unawaited(_restore());
    return const AuthRestoring();
  }

  Future<void> _restore() async {
    final tokens = await ref.read(tokenStoreProvider).read();
    if (tokens == null || !tokens.isValid) {
      state = const AuthSignedOut();
      return;
    }
    try {
      final user = await ref.read(authRepositoryProvider).me();
      state = AuthSignedIn(user);
    } on ApiException catch (e) {
      if (e.isUnauthorized || e.code == 'ACCOUNT_INACTIVE') {
        await ref.read(tokenStoreProvider).clear();
        state = AuthSignedOut(message: _expiryMessage(e.code));
      } else {
        // Offline: keep the session; user data will load later.
        state = AuthSignedIn(_placeholderUser());
      }
    } catch (_) {
      state = AuthSignedIn(_placeholderUser());
    }
  }

  User _placeholderUser() => const User(
        id: 0,
        username: '',
        email: '',
        firstName: '',
        lastName: '',
        avatar: null,
        maxConnections: 1,
        parentalPin: null,
        adultEnabled: false,
      );

  void _onSessionExpired(String code) {
    if (state is AuthSignedIn) {
      state = AuthSignedOut(message: _expiryMessage(code));
    }
  }

  String _expiryMessage(String code) => switch (code) {
        'TOKEN_EXPIRED' => 'Your session ended. This can happen when the account was signed in on another device. Please sign in again.',
        'ACCOUNT_INACTIVE' => 'This account is no longer active.',
        _ => 'Your session has expired. Please sign in again.',
      };

  Future<void> login(String identity, String password) async {
    final result = await ref.read(authRepositoryProvider).login(
          identity: identity,
          password: password,
          deviceName: await _deviceName(),
        );
    state = AuthSignedIn(result.user);
    ref.invalidate(entitlementsProvider);
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    await ref.read(cacheStoreProvider).invalidateScope('user');
    state = const AuthSignedOut();
    ref.invalidate(entitlementsProvider);
  }

  Future<void> refreshUser() async {
    if (state is! AuthSignedIn) return;
    try {
      state = AuthSignedIn(await ref.read(authRepositoryProvider).me());
    } catch (_) {}
  }

  Future<void> updateProfile({bool? adultEnabled, String? parentalPin, bool clearPin = false}) async {
    await ref.read(authRepositoryProvider).updateProfile(adultEnabled: adultEnabled, parentalPin: parentalPin, clearPin: clearPin);
    final current = state;
    if (current is AuthSignedIn) {
      state = AuthSignedIn(current.user.copyWith(adultEnabled: adultEnabled, parentalPin: clearPin ? null : parentalPin));
    }
    ref.invalidate(entitlementsProvider);
  }

  Future<void> deleteAccount(String password) async {
    await ref.read(authRepositoryProvider).deleteAccount(password);
    await ref.read(cacheStoreProvider).clearAll();
    state = const AuthSignedOut(message: 'Your account has been deleted.');
  }

  Future<String?> _deviceName() async {
    try {
      final info = DeviceInfoPlugin();
      if (defaultTargetPlatform == TargetPlatform.android) {
        final a = await info.androidInfo;
        return '${a.manufacturer} ${a.model}'.trim();
      }
      if (defaultTargetPlatform == TargetPlatform.iOS) {
        final i = await info.iosInfo;
        return i.name;
      }
    } catch (_) {}
    return 'Mobile app';
  }
}

final authProvider = NotifierProvider<AuthNotifier, AuthState>(AuthNotifier.new);

/// Convenience: the signed-in user or null.
final currentUserProvider = Provider<User?>((ref) {
  final s = ref.watch(authProvider);
  return s is AuthSignedIn ? s.user : null;
});

/// Entitlements for the signed-in subscriber (empty when signed out).
final entitlementsProvider = FutureProvider<Entitlements>((ref) async {
  if (ref.watch(currentUserProvider) == null) return Entitlements.empty;
  try {
    return await ref.watch(authRepositoryProvider).entitlements();
  } on ApiException catch (e) {
    if (e.isNetwork) return Entitlements.empty;
    rethrow;
  }
});
