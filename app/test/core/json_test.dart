import 'package:cari_tv/core/util/json.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('tolerant json helpers', () {
    test('asInt accepts int, numeric string, double, bool', () {
      expect(asInt(12), 12);
      expect(asInt('12'), 12);
      expect(asInt('12.0'), 12);
      expect(asInt(12.7), 12);
      expect(asInt(true), 1);
      expect(asInt(null, 7), 7);
      expect(asInt('abc', 3), 3);
      expect(asIntOrNull(''), isNull);
    });

    test('asBool accepts 0/1, "0"/"1", true/false, "true"', () {
      expect(asBool(1), isTrue);
      expect(asBool(0), isFalse);
      expect(asBool('1'), isTrue);
      expect(asBool('0'), isFalse);
      expect(asBool(true), isTrue);
      expect(asBool('true'), isTrue);
      expect(asBool(null, true), isTrue);
    });

    test('asDouble accepts strings like "7.4"', () {
      expect(asDouble('7.4'), 7.4);
      expect(asDoubleOrNull('x'), isNull);
    });

    test('asStringList handles arrays, JSON strings and comma lists', () {
      expect(asStringList(['Action', 'Drama']), ['Action', 'Drama']);
      expect(asStringList('["Action","Drama"]'), ['Action', 'Drama']);
      expect(asStringList('Action, Drama'), ['Action', 'Drama']);
      expect(asStringList(null), isEmpty);
    });

    test('asJsonList reads lists and id-keyed maps', () {
      expect(asJsonList([{'a': 1}, 'x', {'b': 2}]).length, 2);
      expect(asJsonList({'12': {'a': 1}, '13': {'b': 2}}).length, 2);
      expect(asJsonList(null), isEmpty);
    });
  });
}
