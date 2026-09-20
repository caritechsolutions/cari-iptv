import 'bootstrap.dart';
import 'config/app_config.dart';

void main() => bootstrap(AppConfig.forFlavor(AppFlavor.dev));
