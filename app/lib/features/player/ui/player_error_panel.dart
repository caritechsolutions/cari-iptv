import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'playback_error.dart';

/// Error state under the video stage: title, plain explanation, actions, and
/// the technical text behind a "Details" toggle. Scrollable, so long
/// messages can never overlap anything above or below.
class PlayerErrorPanel extends StatefulWidget {
  const PlayerErrorPanel({super.key, required this.error, required this.live, required this.title, this.subtitle, required this.onBack, this.onRetry});

  final PlaybackError error;
  final bool live;
  final String title;
  final String? subtitle;
  final VoidCallback onBack;

  /// Null hides the Retry button (formats the device cannot decode).
  final VoidCallback? onRetry;

  @override
  State<PlayerErrorPanel> createState() => _PlayerErrorPanelState();
}

class _PlayerErrorPanelState extends State<PlayerErrorPanel> {
  bool _details = false;

  @override
  Widget build(BuildContext context) {
    final e = widget.error;
    final theme = Theme.of(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(widget.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18)),
          if (widget.subtitle != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(widget.subtitle!, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, fontSize: 13))),
          const SizedBox(height: 18),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(e.kind == PlaybackErrorKind.unsupportedFormat ? Icons.videocam_off_outlined : Icons.error_outline_rounded, color: theme.colorScheme.error),
              const SizedBox(width: 10),
              Expanded(child: Text(e.headline(live: widget.live), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600))),
            ],
          ),
          const SizedBox(height: 8),
          Text(e.message, style: const TextStyle(color: Colors.white70, fontSize: 13, height: 1.35)),
          const SizedBox(height: 16),
          Wrap(
            spacing: 12,
            runSpacing: 8,
            children: [
              OutlinedButton.icon(onPressed: widget.onBack, icon: const Icon(Icons.arrow_back_rounded, size: 18), label: const Text('Back')),
              if (widget.onRetry != null) FilledButton.icon(onPressed: widget.onRetry, icon: const Icon(Icons.refresh), label: const Text('Retry')),
            ],
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            style: TextButton.styleFrom(alignment: Alignment.centerLeft, foregroundColor: Colors.white54),
            onPressed: () => setState(() => _details = !_details),
            icon: Icon(_details ? Icons.expand_less_rounded : Icons.expand_more_rounded, size: 18),
            label: Text(_details ? 'Hide details' : 'Details'),
          ),
          if (_details)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.white10, borderRadius: BorderRadius.circular(8)),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (e.codec != null) _kv('Codec', e.codec!),
                  if (e.mime != null) _kv('Format', e.mime!),
                  if (e.formatSupported != null) _kv('Decoder', e.formatSupported!),
                  _kv('Type', e.kind.name),
                  const SizedBox(height: 6),
                  SelectableText(e.technical, style: const TextStyle(fontFamily: 'monospace', fontSize: 11, color: Colors.white60)),
                  const SizedBox(height: 6),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton.icon(
                      onPressed: () {
                        Clipboard.setData(ClipboardData(text: e.technical));
                        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Copied')));
                      },
                      icon: const Icon(Icons.copy_rounded, size: 16),
                      label: const Text('Copy'),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _kv(String k, String v) => Padding(
        padding: const EdgeInsets.only(bottom: 2),
        child: RichText(
          text: TextSpan(
            style: const TextStyle(fontSize: 12, color: Colors.white60),
            children: [TextSpan(text: '$k: ', style: const TextStyle(color: Colors.white38)), TextSpan(text: v)],
          ),
        ),
      );
}
