import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// In-app back arrow with the same behaviour as the system back button:
/// pops when there is something to pop, otherwise goes to [fallback] (a
/// screen opened from a cold-start deep link has nothing underneath it).
class AppBackButton extends StatelessWidget {
  const AppBackButton({super.key, this.fallback = '/home', this.color});
  final String fallback;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return BackButton(color: color, onPressed: () => leaveScreen(context, fallback: fallback));
  }
}

/// Pop if possible, otherwise replace the stack with [fallback].
void leaveScreen(BuildContext context, {String fallback = '/home'}) {
  if (context.canPop()) {
    context.pop();
  } else {
    context.go(fallback);
  }
}
