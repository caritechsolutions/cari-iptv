import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../providers.dart';

Future<void> openExternal(BuildContext context, String url) async {
  final uri = Uri.tryParse(url);
  if (uri == null) return;
  final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Could not open $url')));
  }
}

/// "Privacy Policy · Terms of Service" links used on login, register and settings.
class LegalLinks extends ConsumerWidget {
  const LegalLinks({super.key, this.alignment = MainAxisAlignment.center});
  final MainAxisAlignment alignment;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(appConfigProvider);
    final style = Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.primary);
    // Wrap, not Row: survives large system fonts and narrow phones.
    return Wrap(
      alignment: switch (alignment) { MainAxisAlignment.start => WrapAlignment.start, MainAxisAlignment.end => WrapAlignment.end, _ => WrapAlignment.center },
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        TextButton(onPressed: () => openExternal(context, config.privacyUrl), child: Text('Privacy Policy', style: style)),
        Text('·', style: Theme.of(context).textTheme.bodySmall),
        TextButton(onPressed: () => openExternal(context, config.termsUrl), child: Text('Terms of Service', style: style)),
      ],
    );
  }
}
