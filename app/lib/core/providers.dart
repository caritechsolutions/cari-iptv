import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import 'network/api_client.dart';
import 'storage/cache_store.dart';
import 'storage/token_store.dart';
import 'util/url_resolver.dart';

/// Overridden in [bootstrap] with the selected flavour.
final appConfigProvider = Provider<AppConfig>((_) => throw UnimplementedError('appConfigProvider must be overridden'));

/// Overridden in [bootstrap] once Hive is open.
final cacheStoreProvider = Provider<CacheStore>((_) => throw UnimplementedError('cacheStoreProvider must be overridden'));

final tokenStoreProvider = Provider<TokenStore>((_) => SecureTokenStore());

final urlResolverProvider = Provider<UrlResolver>((ref) => UrlResolver(ref.watch(appConfigProvider).apiBaseUrl));

final apiClientProvider = Provider<ApiClient>((ref) {
  final client = ApiClient(
    config: ref.watch(appConfigProvider),
    tokenStore: ref.watch(tokenStoreProvider),
    cache: ref.watch(cacheStoreProvider),
  );
  ref.onDispose(client.dispose);
  return client;
});

/// True while the device reports no network at all.
final isOfflineProvider = StreamProvider<bool>((ref) async* {
  final connectivity = Connectivity();
  bool offline(List<ConnectivityResult> r) => r.isEmpty || r.every((e) => e == ConnectivityResult.none);
  yield offline(await connectivity.checkConnectivity());
  yield* connectivity.onConnectivityChanged.map(offline);
});
