class UserModel {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String userType; // 'student' or 'admin'
  final String? token;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.userType,
    this.token,
  });

  bool get isAdmin => userType == 'admin' || userType == 'instructor';

  factory UserModel.fromJson(Map<String, dynamic> json, {String? token}) {
    return UserModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      name: json['name'] ?? 'Learner',
      email: json['email'] ?? '',
      phone: json['phone'],
      userType: json['user_type'] ?? 'student',
      token: token ?? json['token'],
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': email,
    'phone': phone,
    'user_type': userType,
    'token': token,
  };
}
