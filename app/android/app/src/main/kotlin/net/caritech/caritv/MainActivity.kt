// Package must equal the Gradle `namespace` (net.caritech.caritv): the manifest
// declares the activity as ".MainActivity", which Android resolves against the
// namespace. The namespace is a code identifier shared by all brands; the
// per-brand applicationId does not affect this class.
package net.caritech.caritv

import io.flutter.embedding.android.FlutterActivity

class MainActivity : FlutterActivity()
