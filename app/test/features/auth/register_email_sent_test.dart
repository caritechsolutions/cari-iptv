// POST /auth/register may answer email_sent: false (account created, mail
// failed). The app then asks for a resend instead of pointing at the inbox.
// Older backends omit the field, which counts as sent.
import 'package:cari_tv/config/app_config.dart';
import 'package:cari_tv/core/providers.dart';
import 'package:cari_tv/features/auth/data/auth_repository.dart';
import 'package:cari_tv/features/auth/ui/verify_pending_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

void main() {
  group('RegisterResult', () {
    test('email_sent false is carried; absent means sent (older backend)', () {
      expect(RegisterResult.fromData(const {'requires_verification': true, 'email_sent': false, 'message': 'created, mail failed'}).emailSent, isFalse);
      expect(RegisterResult.fromData(const {'requires_verification': true, 'email_sent': true, 'message': 'ok'}).emailSent, isTrue);
      expect(RegisterResult.fromData(const {'requires_verification': true, 'message': 'ok'}).emailSent, isTrue);
      expect(RegisterResult.fromData(const {}).message, contains('check your email'));
    });
  });

  Widget harness(Widget screen) => ProviderScope(
        overrides: [appConfigProvider.overrideWithValue(AppConfig.forFlavor(AppFlavor.dev))],
        child: MaterialApp.router(routerConfig: GoRouter(routes: [GoRoute(path: '/', builder: (_, _) => screen), GoRoute(path: '/login', builder: (_, _) => const Scaffold(body: Text('login')))])),
      );

  testWidgets('verify screen: resend wording when the mail was not sent, inbox wording otherwise', (tester) async {
    await tester.pumpWidget(harness(const VerifyPendingScreen(email: 'new@example.com', message: 'created, mail failed', emailSent: false)));
    await tester.pump();
    expect(find.byKey(const Key('verify-not-sent')), findsOneWidget);
    expect(find.textContaining('We sent a link'), findsNothing);
    expect(find.textContaining('new@example.com'), findsOneWidget);
    expect(find.text('Resend verification email'), findsOneWidget);

    await tester.pumpWidget(harness(const VerifyPendingScreen(email: 'new@example.com', message: 'Account created!')));
    await tester.pump();
    expect(find.byKey(const Key('verify-not-sent')), findsNothing);
    expect(find.textContaining('We sent a link to new@example.com'), findsOneWidget);
  });
}
