import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';

/// Network image that resolves the API's relative paths and shows a neutral
/// placeholder when the image is missing or fails.
class AppImage extends ConsumerWidget {
  const AppImage(this.path, {super.key, this.fit = BoxFit.cover, this.width, this.height, this.borderRadius, this.icon = Icons.movie_outlined});

  final String? path;
  final BoxFit fit;
  final double? width;
  final double? height;
  final BorderRadius? borderRadius;
  final IconData icon;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final url = ref.watch(urlResolverProvider).resolve(path);
    final placeholder = Container(
      width: width,
      height: height,
      color: Theme.of(context).colorScheme.surface,
      alignment: Alignment.center,
      child: Icon(icon, color: Colors.white24, size: 28),
    );
    Widget child = url == null
        ? placeholder
        : CachedNetworkImage(
            imageUrl: url,
            fit: fit,
            width: width,
            height: height,
            fadeInDuration: const Duration(milliseconds: 150),
            placeholder: (_, _) => placeholder,
            errorWidget: (_, _, _) => placeholder,
          );
    if (borderRadius != null) child = ClipRRect(borderRadius: borderRadius!, child: child);
    return child;
  }
}
