// Applies a brand folder to the project before a build.
//
//   dart run tool/apply_brand.dart <brand> [--no-icons]
//
// Reads brands/<brand>/brand.json and:
//   1. copies logo/icon/icon_foreground/splash PNGs into assets/branding/
//   2. writes android/brand.properties (applicationId, appName, keyPropertiesFile,
//      versionCode, versionName) which android/app/build.gradle.kts reads;
//      VERSION_CODE / VERSION_NAME are per brand (optional: without them the
//      pubspec.yaml version applies, as before)
//   3. generates android/app/src/main/res/xml/network_security_config.xml from
//      CLEARTEXT_HOSTS (empty = HTTPS only)
//      BILLING_UI (auto = follow the server switch, on, off)
//      LOGO_HAS_NAME (true = the logo carries the brand name, so it is not printed beside it)
//      logo_dark.png (optional) = logo for dark backgrounds; without it the logo sits on a light plate
//   4. writes tool/generated/icons.yaml + splash.yaml and runs
//      flutter_launcher_icons and flutter_native_splash (skip with --no-icons);
//      ICON_BACKGROUND_COLOR / SPLASH_COLOR (optional) override BACKGROUND_COLOR
//      for the adaptive icon layer and the splash screen colour
//   5. iOS: CFBundleDisplayName, ATS exceptions for the cleartext hosts and
//      the Google URL scheme (IOS_GOOGLE_CLIENT_ID) in ios/Runner/Info.plist;
//      bundle id, Sign in with Apple entitlement and (APPLE_TEAM_ID env)
//      the team in ios/Runner.xcodeproj
//
// No Dart source is touched: the brand values reach the app through
// --dart-define-from-file=brands/<brand>/brand.json at build time (tool/build_brand.sh).
import 'dart:convert';
import 'dart:io';

const requiredKeys = [
  'BRAND_KEY', 'APP_NAME', 'APPLICATION_ID', 'API_BASE_URL_DEV', 'API_BASE_URL_PROD', //
  'PRIMARY_COLOR', 'ACCENT_COLOR', 'BACKGROUND_COLOR', 'SURFACE_COLOR', //
  'PRIVACY_URL', 'TERMS_URL', 'DELETE_ACCOUNT_URL',
];
const images = ['logo.png', 'icon.png', 'icon_foreground.png', 'splash.png'];

void main(List<String> args) async {
  if (args.isEmpty) {
    stderr.writeln('usage: dart run tool/apply_brand.dart <brand> [--no-icons]');
    exit(2);
  }
  final brand = args.first;
  final noIcons = args.contains('--no-icons');
  final root = _projectRoot();
  final dir = Directory('$root/brands/$brand');
  if (!dir.existsSync()) _fail('brand folder not found: ${dir.path}');
  final jsonFile = File('${dir.path}/brand.json');
  if (!jsonFile.existsSync()) _fail('missing ${jsonFile.path}');
  final cfg = (jsonDecode(jsonFile.readAsStringSync()) as Map).map((k, v) => MapEntry(k.toString(), v.toString()));
  for (final k in requiredKeys) {
    if ((cfg[k] ?? '').trim().isEmpty) _fail('brand.json is missing "$k"');
  }
  final appId = cfg['APPLICATION_ID']!.trim();
  if (!RegExp(r'^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$').hasMatch(appId)) {
    _fail('APPLICATION_ID "$appId" is not a valid Android application id');
  }
  final appName = cfg['APP_NAME']!.trim();
  final hosts = _hosts(cfg['CLEARTEXT_HOSTS'] ?? '');
  final billingUi = (cfg['BILLING_UI'] ?? 'auto').trim().toLowerCase();
  if (!const {'auto', 'on', 'off'}.contains(billingUi)) _fail('BILLING_UI must be auto, on or off (got "$billingUi")');

  // 1. images
  final branding = Directory('$root/assets/branding')..createSync(recursive: true);
  for (final img in images) {
    final src = File('${dir.path}/$img');
    if (!src.existsSync()) _fail('missing ${src.path}');
    src.copySync('${branding.path}/$img');
  }
  // Optional logo_dark.png (dark backgrounds). Copied when present, and a stale
  // copy from another brand is removed so the plate fallback is used instead.
  final darkLogo = File('${dir.path}/logo_dark.png');
  final darkTarget = File('${branding.path}/logo_dark.png');
  if (darkLogo.existsSync()) {
    darkLogo.copySync(darkTarget.path);
  } else if (darkTarget.existsSync()) {
    darkTarget.deleteSync();
  }
  final logoHasName = (cfg['LOGO_HAS_NAME'] ?? 'true').trim().toLowerCase();
  if (!const {'true', 'false', '1', '0', 'yes', 'no'}.contains(logoHasName)) _fail('LOGO_HAS_NAME must be true or false (got "$logoHasName")');
  stdout.writeln('✓ assets/branding/ ← brands/$brand/ (logo_dark.png: ${darkLogo.existsSync() ? 'yes' : 'no, light plate'}; LOGO_HAS_NAME=$logoHasName)');

  // 2. android/brand.properties
  final keyProps = File('${dir.path}/key.properties');
  final versionCode = (cfg['VERSION_CODE'] ?? '').trim();
  final versionName = (cfg['VERSION_NAME'] ?? '').trim();
  if (versionCode.isNotEmpty && (int.tryParse(versionCode) == null || int.parse(versionCode) <= 0)) _fail('VERSION_CODE must be a positive integer (got "$versionCode")');
  if (versionName.isNotEmpty && !RegExp(r'^\d+\.\d+\.\d+$').hasMatch(versionName)) _fail('VERSION_NAME must look like 1.2.3 (got "$versionName")');
  if (versionCode.isEmpty != versionName.isEmpty) _fail('VERSION_CODE and VERSION_NAME go together: set both or neither');
  final playUpdates = (cfg['PLAY_UPDATES'] ?? 'false').trim().toLowerCase();
  if (!const {'true', 'false', '1', '0', 'yes', 'no'}.contains(playUpdates)) _fail('PLAY_UPDATES must be true or false (got "$playUpdates")');
  final props = StringBuffer()
    ..writeln('# Generated by tool/apply_brand.dart — do not edit, do not commit.')
    ..writeln('brand=$brand')
    ..writeln('applicationId=$appId')
    ..writeln('appName=${appName.replaceAll('=', '\\=')}')
    ..writeln('versionCode=$versionCode')
    ..writeln('versionName=$versionName')
    ..writeln('keyPropertiesFile=${keyProps.existsSync() ? keyProps.absolute.path : ''}');
  File('$root/android/brand.properties').writeAsStringSync(props.toString());
  stdout.writeln('✓ android/brand.properties (applicationId=$appId, version=${versionCode.isEmpty ? 'pubspec' : '$versionName ($versionCode)'}, signing=${keyProps.existsSync() ? 'brand keystore' : 'debug key — no brands/$brand/key.properties'})');

  // 3. network security config
  final nsc = StringBuffer()
    ..writeln('<?xml version="1.0" encoding="utf-8"?>')
    ..writeln('<!-- Generated by tool/apply_brand.dart for brand "$brand" from CLEARTEXT_HOSTS. Do not edit by hand. -->')
    ..writeln('<network-security-config>')
    ..writeln('    <base-config cleartextTrafficPermitted="false">')
    ..writeln('        <trust-anchors><certificates src="system" /></trust-anchors>')
    ..writeln('    </base-config>');
  if (hosts.isNotEmpty) {
    nsc.writeln('    <!-- Plain http:// streams are allowed ONLY for these hosts. Any other http host fails to play. -->');
    nsc.writeln('    <domain-config cleartextTrafficPermitted="true">');
    for (final h in hosts) {
      nsc.writeln('        <domain includeSubdomains="true">$h</domain>');
    }
    nsc.writeln('    </domain-config>');
  }
  nsc.writeln('</network-security-config>');
  final nscFile = File('$root/android/app/src/main/res/xml/network_security_config.xml')..parent.createSync(recursive: true);
  nscFile.writeAsStringSync(nsc.toString());
  stdout.writeln('✓ network_security_config.xml (${hosts.isEmpty ? 'HTTPS only' : 'cleartext for: ${hosts.join(', ')}'})');

  // 4. icons + splash
  final gen = Directory('$root/tool/generated')..createSync(recursive: true);
  final iconBg = _hex((cfg['ICON_BACKGROUND_COLOR'] ?? '').trim().isEmpty ? cfg['BACKGROUND_COLOR']! : cfg['ICON_BACKGROUND_COLOR']!);
  final splashBg = _hex((cfg['SPLASH_COLOR'] ?? '').trim().isEmpty ? cfg['BACKGROUND_COLOR']! : cfg['SPLASH_COLOR']!);
  File('${gen.path}/icons.yaml').writeAsStringSync('''
flutter_launcher_icons:
  android: true
  ios: true
  remove_alpha_ios: true
  image_path: "assets/branding/icon.png"
  adaptive_icon_background: "$iconBg"
  adaptive_icon_foreground: "assets/branding/icon_foreground.png"
''');
  File('${gen.path}/splash.yaml').writeAsStringSync('''
flutter_native_splash:
  color: "$splashBg"
  image: assets/branding/splash.png
  android_12:
    color: "$splashBg"
    image: assets/branding/splash.png
''');
  if (!noIcons) {
    await _run(root, ['dart', 'run', 'flutter_launcher_icons', '-f', 'tool/generated/icons.yaml']);
    await _run(root, ['dart', 'run', 'flutter_native_splash:create', '--path=tool/generated/splash.yaml']);
    stdout.writeln('✓ launcher icons and splash regenerated (icon background $iconBg, splash $splashBg)');
  } else {
    stdout.writeln('· icons/splash skipped (--no-icons)');
  }

  // 5. iOS: display name, bundle id, ATS exceptions, Google URL scheme,
  //    Sign in with Apple entitlement. All derived from brand.json so the
  //    Xcode project never needs opening on a Mac.
  final plist = File('$root/ios/Runner/Info.plist');
  if (plist.existsSync()) {
    var text = plist.readAsStringSync();
    text = text.replaceFirstMapped(
      RegExp(r'(<key>CFBundleDisplayName</key>\s*<string>)[^<]*(</string>)'),
      (m) => '${m.group(1)}${_xml(appName)}${m.group(2)}',
    );
    text = _stripPlistKey(text, 'NSAppTransportSecurity');
    text = _stripPlistKey(text, 'CFBundleURLTypes');
    final extra = StringBuffer();
    if (hosts.isNotEmpty) {
      extra.writeln('\t\t<key>NSAppTransportSecurity</key>\n\t\t<dict>\n\t\t\t<key>NSExceptionDomains</key>\n\t\t\t<dict>');
      for (final h in hosts) {
        extra.writeln('\t\t\t\t<key>${_xml(h)}</key>\n\t\t\t\t<dict>\n\t\t\t\t\t<key>NSExceptionAllowsInsecureHTTPLoads</key>\n\t\t\t\t\t<true/>\n\t\t\t\t\t<key>NSIncludesSubdomains</key>\n\t\t\t\t\t<true/>\n\t\t\t\t</dict>');
      }
      extra.writeln('\t\t\t</dict>\n\t\t</dict>');
    }
    final iosGoogle = (cfg['IOS_GOOGLE_CLIENT_ID'] ?? '').trim();
    if (iosGoogle.isNotEmpty) {
      // Google's iOS sign-in returns to the app through the reversed client id scheme.
      final reversed = iosGoogle.split('.').reversed.join('.');
      extra.writeln('\t\t<key>CFBundleURLTypes</key>\n\t\t<array>\n\t\t\t<dict>\n\t\t\t\t<key>CFBundleTypeRole</key>\n\t\t\t\t<string>Editor</string>\n\t\t\t\t<key>CFBundleURLSchemes</key>\n\t\t\t\t<array>\n\t\t\t\t\t<string>${_xml(reversed)}</string>\n\t\t\t\t</array>\n\t\t\t</dict>\n\t\t</array>');
    }
    text = text.replaceFirst('\t</dict>\n</plist>', '$extra\t</dict>\n</plist>');
    plist.writeAsStringSync(text);
    stdout.writeln('✓ ios/Runner/Info.plist: display name "$appName"'
        '${hosts.isNotEmpty ? ', ATS exceptions for ${hosts.join(', ')}' : ', HTTPS only'}'
        '${iosGoogle.isNotEmpty ? ', Google URL scheme' : ''}');
  }

  final pbx = File('$root/ios/Runner.xcodeproj/project.pbxproj');
  if (pbx.existsSync()) {
    var text = pbx.readAsStringSync();
    // Runner target: the brand's id; the test target: <id>.RunnerTests
    text = text.replaceAllMapped(
      RegExp(r'PRODUCT_BUNDLE_IDENTIFIER = ([^;]+?)(\.RunnerTests)?;'),
      (m) => 'PRODUCT_BUNDLE_IDENTIFIER = $appId${m.group(2) ?? ''};',
    );
    // Sign in with Apple capability (Apple requires it alongside Google sign-in)
    if (!text.contains('CODE_SIGN_ENTITLEMENTS')) {
      text = text.replaceAll('INFOPLIST_FILE = Runner/Info.plist;', 'CODE_SIGN_ENTITLEMENTS = Runner/Runner.entitlements;\n\t\t\t\tINFOPLIST_FILE = Runner/Info.plist;');
    }
    final team = Platform.environment['APPLE_TEAM_ID']?.trim() ?? '';
    if (team.isNotEmpty) {
      text = text.replaceAll(RegExp(r'\t*DEVELOPMENT_TEAM = [^;]*;\n'), '');
      text = text.replaceAll('INFOPLIST_FILE = Runner/Info.plist;', 'DEVELOPMENT_TEAM = $team;\n\t\t\t\tINFOPLIST_FILE = Runner/Info.plist;');
    }
    pbx.writeAsStringSync(text);
    File('$root/ios/Runner/Runner.entitlements').writeAsStringSync(_entitlements);
    stdout.writeln('✓ ios/Runner.xcodeproj: bundle id $appId, Sign in with Apple entitlement${team.isNotEmpty ? ', team $team' : ''}');
  }

  stdout.writeln('\nBrand "$brand" applied. Build with:\n  ./tool/build_brand.sh $brand apk prod');
}

String _projectRoot() {
  var d = Directory.current;
  while (!File('${d.path}/pubspec.yaml').existsSync()) {
    if (d.parent.path == d.path) _fail('run from the app/ folder');
    d = d.parent;
  }
  return d.path;
}

List<String> _hosts(String raw) => raw.split(RegExp(r'[,\s]+')).map((h) => h.trim().toLowerCase()).where((h) => h.isNotEmpty).toSet().toList();

String _hex(String v) {
  var h = v.trim().replaceFirst('#', '');
  if (h.length == 8) h = h.substring(2);
  if (h.length != 6 || int.tryParse(h, radix: 16) == null) _fail('bad colour "$v" (use #RRGGBB)');
  return '#${h.toUpperCase()}';
}

String _xml(String s) => s.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

Future<void> _run(String root, List<String> cmd) async {
  stdout.writeln('→ ${cmd.join(' ')}');
  final p = await Process.start(cmd.first, cmd.sublist(1), workingDirectory: root, mode: ProcessStartMode.inheritStdio);
  final code = await p.exitCode;
  if (code != 0) _fail('command failed (${cmd.join(' ')})');
}

Never _fail(String msg) {
  stderr.writeln('apply_brand: $msg');
  exit(1);
}

/// Remove a top-level key (and its value, however deeply nested) from the
/// Info.plist text, so re-applying a brand never duplicates entries.
String _stripPlistKey(String plist, String key) {
  final keyTag = '<key>$key</key>';
  final start = plist.indexOf(keyTag);
  if (start < 0) return plist;
  // Value starts at the next tag after the key
  var pos = plist.indexOf('<', start + keyTag.length);
  final selfClosing = RegExp(r'^<(true|false|date|data)/>');
  final open = RegExp(r'^<(dict|array|string|integer|real|date|data)>');
  var end = pos;
  if (selfClosing.hasMatch(plist.substring(pos))) {
    end = plist.indexOf('/>', pos) + 2;
  } else {
    final m = open.firstMatch(plist.substring(pos))!;
    final tag = m.group(1)!;
    var depth = 0;
    final re = RegExp('<(/?)$tag>');
    for (final t in re.allMatches(plist, pos)) {
      depth += t.group(1)!.isEmpty ? 1 : -1;
      if (depth == 0) {
        end = t.end;
        break;
      }
    }
  }
  // Also eat the line's leading tabs and trailing newline
  var lineStart = start;
  while (lineStart > 0 && plist[lineStart - 1] == '\t') {
    lineStart--;
  }
  if (end < plist.length && plist[end] == '\n') end++;
  return plist.substring(0, lineStart) + plist.substring(end);
}

const _entitlements = '''<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>com.apple.developer.applesignin</key>
	<array>
		<string>Default</string>
	</array>
</dict>
</plist>
''';
