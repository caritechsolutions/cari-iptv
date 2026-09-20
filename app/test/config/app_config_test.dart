import 'package:cari_tv/config/app_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppConfig', () {
    test('hex colour parsing accepts #RRGGBB, RRGGBB, #AARRGGBB, 0x', () {
      expect(AppConfig.parseHexColor('#6366F1', Colors.black), const Color(0xFF6366F1));
      expect(AppConfig.parseHexColor('6366f1', Colors.black), const Color(0xFF6366F1));
      expect(AppConfig.parseHexColor('#806366F1', Colors.black), const Color(0x806366F1));
      expect(AppConfig.parseHexColor('0xFF0EA5A4', Colors.black), const Color(0xFF0EA5A4));
      expect(AppConfig.parseHexColor('nope', Colors.black), Colors.black);
    });

    test('cleartext host list parsing', () {
      expect(AppConfig.parseHostList(''), isEmpty);
      expect(AppConfig.parseHostList('a.example.com, B.example.com  c.example.com,a.example.com'), ['a.example.com', 'b.example.com', 'c.example.com']);
    });

    test('defaults are the caritv placeholder and flavours differ', () {
      final dev = AppConfig.forFlavor(AppFlavor.dev);
      final prod = AppConfig.forFlavor(AppFlavor.prod);
      expect(dev.brandKey, 'caritv');
      expect(dev.apiV1, 'https://player.caritech.net/api/v1');
      expect(dev.platformTag, 'mobile-dev');
      expect(prod.platformTag, 'mobile');
      expect(dev.applicationId, endsWith('.dev'));
      expect(prod.applicationId, 'net.caritech.caritv');
      expect(dev.appName, contains('(dev)'));
      expect(prod.cleartextHosts, isEmpty);
      expect(prod.termsUrl, contains('#terms'));
    });
  });
}
