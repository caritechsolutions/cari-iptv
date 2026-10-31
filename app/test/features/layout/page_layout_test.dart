// Which layout a page shows: the one linked to it in Pages & Nav first, then
// (for Home) the platform default, then null → the built-in screen. Mirrors
// the web player's pageLayouts rule so both platforms behave the same.
import 'package:cari_tv/features/auth/state/auth_notifier.dart';
import 'package:cari_tv/features/layout/state/layout_providers.dart';
import 'package:cari_tv/models/layout.dart';
import 'package:cari_tv/models/navigation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

import '../../support/test_harness.dart';

AppLayout layout(int id, String name) => AppLayout.fromJson({'id': id, 'name': name, 'platform': 'mobile', 'status': 'published', 'sections': []});
AppPage page(String type, {int? layoutId}) => AppPage.fromJson({'id': type.hashCode, 'name': type, 'slug': type, 'page_type': type, 'layout_id': layoutId, 'is_system': 1, 'sort_order': 0});

void main() {
  late TestRepos repos;
  late ProviderContainer container;

  setUp(() {
    repos = TestRepos();
    container = ProviderContainer(overrides: testOverrides(auth: const AuthSignedIn(testUser), repos: repos));
    addTearDown(container.dispose);
    when(() => repos.layout.defaultLayout()).thenAnswer((_) async => layout(1, 'Default'));
    when(() => repos.layout.layoutById(7)).thenAnswer((_) async => layout(7, 'Home page layout'));
    when(() => repos.layout.layoutById(9)).thenAnswer((_) async => layout(9, 'Movies page layout'));
  });

  test('home uses the Home page layout when one is linked', () async {
    when(() => repos.layout.pages()).thenAnswer((_) async => [page('home', layoutId: 7), page('movies', layoutId: 9)]);
    expect((await container.read(homeLayoutProvider.future))?.id, 7);
    expect((await container.read(pageLayoutProvider('movies').future))?.id, 9);
    verifyNever(() => repos.layout.defaultLayout());
  });

  test('home falls back to the platform default; other pages to null', () async {
    when(() => repos.layout.pages()).thenAnswer((_) async => [page('home'), page('movies')]);
    expect((await container.read(homeLayoutProvider.future))?.id, 1);
    expect(await container.read(pageLayoutProvider('movies').future), isNull);
  });

  test('a page whose layout is unpublished (404) behaves as unlinked', () async {
    when(() => repos.layout.pages()).thenAnswer((_) async => [page('home', layoutId: 42)]);
    when(() => repos.layout.layoutById(42)).thenAnswer((_) async => null);
    expect((await container.read(homeLayoutProvider.future))?.id, 1);
  });
}
