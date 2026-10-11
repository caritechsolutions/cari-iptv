import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/providers.dart';
import 'core/theme/app_theme.dart';
import 'features/updates/play_update.dart';
import 'router/app_router.dart';

/// App-wide ScaffoldMessenger (used by the Play update prompt).
final appMessengerKey = GlobalKey<ScaffoldMessengerState>();

class CariApp extends ConsumerWidget {
  const CariApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(appConfigProvider);
    final router = ref.watch(appRouterProvider);
    return MaterialApp.router(
      title: config.appName,
      debugShowCheckedModeBanner: config.isDev,
      theme: buildAppTheme(config),
      routerConfig: router,
      scaffoldMessengerKey: appMessengerKey,
      builder: (context, child) => PlayUpdatePrompt(messengerKey: appMessengerKey, child: child ?? const SizedBox.shrink()),
    );
  }
}
