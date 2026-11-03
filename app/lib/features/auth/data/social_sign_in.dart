import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

/// What a provider hands back after the user picks an account. The backend
/// verifies [idToken]; the rest is only sent along on first sign-in.
class SocialCredential {
  const SocialCredential({required this.provider, required this.idToken, this.authorizationCode, this.firstName, this.lastName});
  final String provider;
  final String idToken;
  final String? authorizationCode;
  final String? firstName;
  final String? lastName;
}

/// Thrown when the user dismissed the provider's sheet; callers show nothing.
class SocialSignInCancelled implements Exception {
  const SocialSignInCancelled();
}

/// Thin wrapper over the platform plugins so screens and tests never touch
/// them directly (tests override [socialSignInProvider]).
abstract class SocialSignIn {
  /// Whether Sign in with Apple can be offered on this device.
  bool get appleAvailable;

  Future<SocialCredential> google(String serverClientId);
  Future<SocialCredential> apple();
}

class PluginSocialSignIn implements SocialSignIn {
  String? _initialisedFor;

  @override
  bool get appleAvailable => !kIsWeb && (Platform.isIOS || Platform.isMacOS);

  @override
  Future<SocialCredential> google(String serverClientId) async {
    final signIn = GoogleSignIn.instance;
    if (_initialisedFor != serverClientId) {
      await signIn.initialize(serverClientId: serverClientId);
      _initialisedFor = serverClientId;
    }
    final GoogleSignInAccount account;
    try {
      account = await signIn.authenticate();
    } on GoogleSignInException catch (e) {
      if (e.code == GoogleSignInExceptionCode.canceled) throw const SocialSignInCancelled();
      rethrow;
    }
    final idToken = account.authentication.idToken;
    if (idToken == null || idToken.isEmpty) {
      throw StateError('Google did not return an ID token');
    }
    final name = account.displayName?.trim() ?? '';
    final parts = name.isEmpty ? const <String>[] : name.split(RegExp(r'\s+'));
    return SocialCredential(
      provider: 'google',
      idToken: idToken,
      firstName: parts.isNotEmpty ? parts.first : null,
      lastName: parts.length > 1 ? parts.sublist(1).join(' ') : null,
    );
  }

  @override
  Future<SocialCredential> apple() async {
    final AuthorizationCredentialAppleID c;
    try {
      c = await SignInWithApple.getAppleIDCredential(scopes: [AppleIDAuthorizationScopes.email, AppleIDAuthorizationScopes.fullName]);
    } on SignInWithAppleAuthorizationException catch (e) {
      if (e.code == AuthorizationErrorCode.canceled) throw const SocialSignInCancelled();
      rethrow;
    }
    final idToken = c.identityToken;
    if (idToken == null || idToken.isEmpty) {
      throw StateError('Apple did not return an identity token');
    }
    return SocialCredential(
      provider: 'apple',
      idToken: idToken,
      authorizationCode: c.authorizationCode,
      firstName: c.givenName,
      lastName: c.familyName,
    );
  }
}

final socialSignInProvider = Provider<SocialSignIn>((ref) => PluginSocialSignIn());
