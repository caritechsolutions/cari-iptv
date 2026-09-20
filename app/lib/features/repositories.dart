import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/providers.dart';
import 'ads/data/ads_repository.dart';
import 'analytics/data/analytics_repository.dart';
import 'auth/data/auth_repository.dart';
import 'content/data/content_repository.dart';
import 'layout/data/layout_repository.dart';
import 'library/data/user_content_repository.dart';
import 'live/data/epg_repository.dart';
import 'recommendations/data/recommendation_repository.dart';

final authRepositoryProvider = Provider((ref) => AuthRepository(ref.watch(apiClientProvider)));
final contentRepositoryProvider = Provider((ref) => ContentRepository(ref.watch(apiClientProvider)));
final layoutRepositoryProvider = Provider((ref) => LayoutRepository(ref.watch(apiClientProvider)));
final userContentRepositoryProvider = Provider((ref) => UserContentRepository(ref.watch(apiClientProvider)));
final epgRepositoryProvider = Provider((ref) => EpgRepository(ref.watch(apiClientProvider)));
final adsRepositoryProvider = Provider((ref) => AdsRepository(ref.watch(apiClientProvider)));
final recommendationRepositoryProvider = Provider((ref) => RecommendationRepository(ref.watch(apiClientProvider)));
final analyticsRepositoryProvider = Provider((ref) {
  final repo = AnalyticsRepository(ref.watch(apiClientProvider));
  ref.onDispose(repo.dispose);
  return repo;
});
