import '../core/util/json.dart';

/// Subscriber as returned by `/auth/login` (`user`) and `/auth/me`.
class User {
  const User({
    required this.id,
    required this.username,
    required this.email,
    required this.firstName,
    required this.lastName,
    required this.avatar,
    required this.maxConnections,
    required this.parentalPin,
    required this.adultEnabled,
  });

  final int id;
  final String username;
  final String email;
  final String firstName;
  final String lastName;
  final String? avatar;
  final int maxConnections;

  /// Account-level PIN (4 digits). The API returns it in cleartext.
  final String? parentalPin;
  final bool adultEnabled;

  String get displayName {
    final full = '$firstName $lastName'.trim();
    return full.isNotEmpty ? full : (username.isNotEmpty ? username : email);
  }

  factory User.fromJson(Json j) => User(
        id: asInt(j['id']),
        username: asString(j['username']),
        email: asString(j['email']),
        firstName: asString(j['first_name']),
        lastName: asString(j['last_name']),
        avatar: asStringOrNull(j['avatar']),
        maxConnections: asInt(j['max_connections'], 1),
        parentalPin: asStringOrNull(j['parental_pin']),
        adultEnabled: asBool(j['adult_enabled']),
      );

  Json toJson() => {
        'id': id,
        'username': username,
        'email': email,
        'first_name': firstName,
        'last_name': lastName,
        'avatar': avatar,
        'max_connections': maxConnections,
        'parental_pin': parentalPin,
        'adult_enabled': adultEnabled,
      };

  User copyWith({bool? adultEnabled, String? parentalPin}) => User(
        id: id,
        username: username,
        email: email,
        firstName: firstName,
        lastName: lastName,
        avatar: avatar,
        maxConnections: maxConnections,
        parentalPin: parentalPin ?? this.parentalPin,
        adultEnabled: adultEnabled ?? this.adultEnabled,
      );
}
