// Google / Apple sign-in: the buttons appear only when the backend enables a
// provider; tapping Google asks the plugin wrapper for a credential, sends it
// to /auth/social and signs the user in. A cancelled sheet shows no error.
import 'package:cari_tv/features/auth/data/auth_repository.dart';
import 'package:cari_tv/features/auth/data/social_sign_in.dart';
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/auth/ui/login_screen.dart';
import 'package:cari_tv/models/auth_tokens.dart';
import 'package:cari_tv/models/sign_in_providers.dart';
import 'package:cari_tv/models/user.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';

import '../../support/test_harness.dart';

class FakeSocial implements SocialSignIn {
  FakeSocial({this.cancel = false});
  final bool cancel;
  String? askedClientId;

  @override
  bool get appleAvailable => false;

  @override
  Future<SocialCredential> google(String serverClientId) async {
    askedClientId = serverClientId;
    if (cancel) throw const SocialSignInCancelled();
    return const SocialCredential(provider: 'google', idToken: 'tok-123', firstName: 'Ada');
  }

  @override
  Future<SocialCredential> apple() async => throw UnimplementedError();
}

class FakeCred extends Fake implements SocialCredential {}

/// Explicit pumps: the auth screens keep indefinite animations alive, so
/// pumpAndSettle never returns (same convention as the other widget tests).
Future<void> settle(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  setUpAll(() => registerFallbackValue(FakeCred()));

  final googleOn = SignInProviders.fromJson({'google': {'enabled': true, 'client_id': 'web-id.apps'}, 'apple': {'enabled': false}});

  Widget harness(TestRepos repos, FakeSocial social) => ProviderScope(
        overrides: [
          ...testOverrides(auth: const AuthSignedOut(), repos: repos),
          socialSignInProvider.overrideWithValue(social),
        ],
        // AuthScaffold asks the router whether it can pop, so a GoRouter is required.
        child: MaterialApp.router(routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => const LoginScreen())])),
      );

  testWidgets('no providers enabled → no social buttons', (tester) async {
    final repos = TestRepos();
    when(() => repos.auth.providers()).thenAnswer((_) async => SignInProviders.none);
    await tester.pumpWidget(harness(repos, FakeSocial()));
    await settle(tester);
    expect(find.byKey(const Key('google-sign-in')), findsNothing);
    expect(find.text('or continue with'), findsNothing);
  });

  testWidgets('Google button signs in through /auth/social with the web client id', (tester) async {
    final repos = TestRepos();
    final social = FakeSocial();
    when(() => repos.auth.providers()).thenAnswer((_) async => googleOn);
    when(() => repos.auth.social(any(), deviceName: any(named: 'deviceName'))).thenAnswer((_) async => LoginResult(
          AuthTokens(accessToken: 'a', refreshToken: 'r', expiresAt: DateTime.now().add(const Duration(hours: 1))),
          User.fromJson({'id': 9, 'username': 'ada', 'email': 'ada@example.com', 'has_password': false}),
        ));

    await tester.pumpWidget(harness(repos, social));
    await settle(tester);
    expect(find.byKey(const Key('google-sign-in')), findsOneWidget);

    await tester.tap(find.byKey(const Key('google-sign-in')));
    await settle(tester);

    expect(social.askedClientId, 'web-id.apps');
    final sent = verify(() => repos.auth.social(captureAny(), deviceName: any(named: 'deviceName'))).captured.single as SocialCredential;
    expect(sent.idToken, 'tok-123');
    expect(sent.provider, 'google');
  });

  testWidgets('closing the Google sheet shows no error', (tester) async {
    final repos = TestRepos();
    when(() => repos.auth.providers()).thenAnswer((_) async => googleOn);
    await tester.pumpWidget(harness(repos, FakeSocial(cancel: true)));
    await settle(tester);
    await tester.tap(find.byKey(const Key('google-sign-in')));
    await settle(tester);
    verifyNever(() => repos.auth.social(any(), deviceName: any(named: 'deviceName')));
    expect(find.byKey(const Key('google-sign-in')), findsOneWidget, reason: 'still on the login screen, button re-enabled');
    expect(tester.widget<OutlinedButton>(find.byKey(const Key('google-sign-in'))).enabled, isTrue);
  });

  test('User.has_password defaults to true for older backends', () {
    expect(User.fromJson({'id': 1}).hasPassword, isTrue);
    expect(User.fromJson({'id': 1, 'has_password': false}).hasPassword, isFalse);
  });
}
