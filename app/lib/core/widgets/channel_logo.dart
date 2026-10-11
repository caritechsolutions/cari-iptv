import 'package:flutter/material.dart';

import 'app_image.dart';

/// A channel logo on a surface-coloured plate, filling the box it is given
/// with a modest inset and the logo's own aspect ratio preserved. Used by
/// the Live TV list, channel rails and tiles, the guide's channel column and
/// the in-player channel picker, so every logo gets the same treatment.
class ChannelLogo extends StatelessWidget {
  const ChannelLogo(this.url, {super.key, this.padding = 8, this.radius = 8});

  final String? url;
  final double padding;
  final double radius;

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(color: Theme.of(context).colorScheme.surface, borderRadius: BorderRadius.circular(radius)),
        padding: EdgeInsets.all(padding),
        child: AppImage(url, fit: BoxFit.contain, icon: Icons.live_tv_outlined),
      );
}
