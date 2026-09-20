import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/state/auth_notifier.dart';
import '../features/auth/ui/forgot_password_screen.dart';
import '../features/auth/ui/login_screen.dart';
import '../features/auth/ui/register_screen.dart';
import '../features/auth/ui/splash_screen.dart';
import '../features/auth/ui/verify_pending_screen.dart';
import '../features/content/ui/misc_screens.dart';
import '../features/content/ui/movie_detail_screen.dart';
import '../features/content/ui/series_detail_screen.dart';
import '../features/live/ui/guide_screen.dart';
import '../features/player/ui/playback_request.dart';
import '../features/player/ui/player_screen.dart';
import '../features/shell/ui/app_shell.dart';
import '../features/shell/ui/tab_pages.dart';
import '../models/channel.dart';

/// Notifies go_router when the auth state changes so redirects re-run.
class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(Ref ref) {
    ref.listen<AuthState>(authProvider, (_, _) => notifyListeners());
  }
}

const _authPaths = {'/login', '/register', '/forgot-password', '/verify-pending'};

final appRouterProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthRefresh(ref);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refresh,
    debugLogDiagnostics: false,
    redirect: (context, state) {
      final auth = ref.read(authProvider);
      final path = state.matchedLocation;
      final onAuthPage = _authPaths.contains(path);

      if (auth is AuthRestoring) return path == '/splash' ? null : '/splash';
      if (auth is AuthSignedOut) return onAuthPage ? null : '/login';
      // Signed in
      if (path == '/splash' || onAuthPage || path == '/') return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, _) => const RegisterScreen()),
      GoRoute(path: '/forgot-password', builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(
        path: '/verify-pending',
        builder: (_, state) {
          final extra = state.extra is Map ? state.extra as Map : const {};
          return VerifyPendingScreen(email: '${extra['email'] ?? ''}', message: extra['message'] as String?);
        },
      ),

      // Tab shell
      ShellRoute(
        builder: (context, state, child) => AppShell(location: state.matchedLocation, child: child),
        routes: [
          GoRoute(path: '/home', builder: (_, _) => const HomeTab()),
          GoRoute(path: '/movies', builder: (_, _) => const MoviesTab()),
          GoRoute(path: '/series', builder: (_, _) => const SeriesTab()),
          GoRoute(path: '/live', builder: (_, _) => const LiveTab()),
          GoRoute(path: '/categories', builder: (_, _) => const CategoriesTab()),
          GoRoute(path: '/my-list', builder: (_, _) => const MyListTab()),
          GoRoute(path: '/subscribe', builder: (_, _) => const SubscribeTab()),
          GoRoute(path: '/profile', builder: (_, _) => const ProfileTab()),
          GoRoute(path: '/page/:slug', builder: (_, state) => CustomPageTab(slug: state.pathParameters['slug'] ?? '')),
        ],
      ),

      // Full-screen pages pushed over the shell
      GoRoute(path: '/search', builder: (_, _) => const SearchPage()),
      GoRoute(path: '/settings', builder: (_, _) => const SettingsPage()),
      GoRoute(path: '/movie/:id', builder: (_, s) => MovieDetailScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0)),
      GoRoute(path: '/series/:id', builder: (_, s) => SeriesDetailScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0)),
      GoRoute(path: '/episode/:id', builder: (_, s) => EpisodeLaunchScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0)),
      GoRoute(path: '/category/:id', builder: (_, s) => CategoryScreen(id: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, title: s.extra as String?)),
      GoRoute(path: '/person/:id', builder: (_, s) => PersonScreen(tmdbId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0)),
      GoRoute(path: '/guide', builder: (_, _) => const GuideScreen()),
      GoRoute(path: '/guide/:id', builder: (_, s) => ChannelGuideScreen(channelId: int.tryParse(s.pathParameters['id'] ?? '') ?? 0, channel: s.extra is Channel ? s.extra as Channel : null)),
      GoRoute(
        path: '/player',
        builder: (_, s) {
          final req = s.extra;
          if (req is! PlaybackRequest) return const _BadRoute();
          return PlayerScreen(request: req);
        },
      ),
    ],
  );
});

class _BadRoute extends StatelessWidget {
  const _BadRoute();
  @override
  Widget build(BuildContext context) => Scaffold(appBar: AppBar(), body: const Center(child: Text('Nothing to play')));
}
