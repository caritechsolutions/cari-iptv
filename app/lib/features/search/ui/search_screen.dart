import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/cards.dart';
import '../../../models/search_result.dart';
import '../../repositories.dart';

const _types = {'all': 'All', 'movie': 'Movies', 'series': 'TV Shows', 'channel': 'Channels'};

class SearchScreen extends ConsumerStatefulWidget {
  const SearchScreen({super.key});

  @override
  ConsumerState<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends ConsumerState<SearchScreen> {
  final _controller = TextEditingController();
  Timer? _debounce;
  String _type = 'all';
  String _query = '';
  AsyncValue<List<SearchResult>> _results = const AsyncData([]);
  int _seq = 0;

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () => _search(v));
  }

  Future<void> _search(String raw) async {
    final q = raw.trim();
    _query = q;
    if (q.length < 2) {
      setState(() => _results = const AsyncData([]));
      return;
    }
    final seq = ++_seq;
    setState(() => _results = const AsyncLoading());
    try {
      final list = await ref.read(contentRepositoryProvider).search(q, type: _type, limit: 30);
      if (seq != _seq || !mounted) return;
      setState(() => _results = AsyncData(list));
      ref.read(analyticsRepositoryProvider).track(list.isEmpty ? 'search_no_results' : 'search', page: 'search', metadata: {'q': q, 'type': _type});
    } catch (e, st) {
      if (seq != _seq || !mounted) return;
      setState(() => _results = AsyncError(e, st));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: TextField(
          controller: _controller,
          autofocus: true,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            hintText: 'Search movies, shows, channels',
            isDense: true,
            suffixIcon: _controller.text.isEmpty ? null : IconButton(icon: const Icon(Icons.clear), onPressed: () { _controller.clear(); _search(''); }),
          ),
          onChanged: _onChanged,
          onSubmitted: _search,
        ),
      ),
      body: Column(
        children: [
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                for (final e in _types.entries) ...[
                  ChoiceChip(label: Text(e.value), selected: _type == e.key, onSelected: (_) { setState(() => _type = e.key); _search(_query); }),
                  const SizedBox(width: 6),
                ],
              ],
            ),
          ),
          Expanded(
            child: _query.length < 2
                ? const EmptyView(message: 'Type at least 2 characters to search.', icon: Icons.search_rounded)
                : AsyncView<List<SearchResult>>(
                    value: _results,
                    onRetry: () => _search(_query),
                    isEmpty: (l) => l.isEmpty,
                    emptyMessage: 'No results for "$_query".',
                    emptyIcon: Icons.search_off_rounded,
                    builder: (list) => PosterGrid(cards: list.map((r) => r.toCard()).toList()),
                  ),
          ),
        ],
      ),
    );
  }
}
