# Flutter / plugins keep rules (Flutter's own rules are merged automatically).
-keep class io.flutter.** { *; }
-dontwarn io.flutter.embedding.**
# ExoPlayer/Media3 used by video_player
-dontwarn androidx.media3.**
