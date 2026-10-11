import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../core/constants/api_endpoints.dart';
import '../models/user_model.dart';
import 'api_service.dart';

class AuthService extends ChangeNotifier {
  static final AuthService _instance = AuthService._internal();
  factory AuthService() => _instance;
  AuthService._internal();

  UserModel? _user;
  bool _isLoading = true;

  UserModel? get user => _user;
  bool get isAuthenticated => _user != null;
  bool get isAdmin => _user?.isAdmin ?? false;
  bool get isLoading => _isLoading;

  // Initialize from local storage
  Future<void> init() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userDataStr = prefs.getString('user_data');
      if (userDataStr != null) {
        final map = json.decode(userDataStr);
        _user = UserModel.fromJson(map, token: prefs.getString('auth_token'));
        ApiService().setAuth(_user?.token, _user?.id);
      }
    } catch (e) {
      debugPrint('Auth init error: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // 1. Student Login
  Future<Map<String, dynamic>> loginStudent(String email, String password) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.auth),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'action': 'student_login',
          'email': email,
          'password': password,
        }),
      );

      final decoded = json.decode(res.body);
      if (decoded['status'] == true && decoded['data'] != null) {
        final userData = decoded['data']['user'];
        final token = decoded['data']['token'];
        _user = UserModel.fromJson(userData, token: token);

        await _saveUserSession(_user!, token);
        ApiService().setAuth(token, _user?.id);
        notifyListeners();
        return {'status': true, 'message': decoded['message'] ?? 'Login successful'};
      } else {
        final requiresOtp = decoded is Map && decoded['data'] != null && decoded['data']['requires_otp'] == true;
        return {
          'status': false,
          'requires_otp': requiresOtp,
          'email': email,
          'message': decoded['message'] ?? 'Login failed',
        };
      }
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 2. Student Register
  Future<Map<String, dynamic>> registerStudent({
    required String name,
    required String email,
    required String password,
    String? phone,
  }) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.auth),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'action': 'student_register',
          'name': name,
          'email': email,
          'password': password,
          'phone': phone ?? '',
        }),
      );

      final decoded = json.decode(res.body);
      if (decoded['status'] == true && decoded['data'] != null) {
        if (decoded['data']['requires_otp'] == true) {
          return {
            'status': true,
            'requires_otp': true,
            'email': email,
            'message': decoded['message'] ?? 'OTP verification code sent to your Gmail.',
          };
        }

        final userData = decoded['data']['user'];
        final token = decoded['data']['token'];
        _user = UserModel.fromJson(userData, token: token);

        await _saveUserSession(_user!, token);
        ApiService().setAuth(token, _user?.id);
        notifyListeners();
        return {'status': true, 'requires_otp': false, 'message': decoded['message'] ?? 'Registration successful'};
      } else {
        return {'status': false, 'message': decoded['message'] ?? 'Registration failed'};
      }
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 2.1 Verify Student OTP
  Future<Map<String, dynamic>> verifyOtp({
    required String email,
    required String otp,
  }) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.auth),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'action': 'verify_otp',
          'email': email,
          'otp': otp,
        }),
      );

      final decoded = json.decode(res.body);
      if (decoded['status'] == true && decoded['data'] != null) {
        final userData = decoded['data']['user'];
        final token = decoded['data']['token'];
        _user = UserModel.fromJson(userData, token: token);

        await _saveUserSession(_user!, token);
        ApiService().setAuth(token, _user?.id);
        notifyListeners();
        return {'status': true, 'message': decoded['message'] ?? 'Email verified successfully!'};
      } else {
        return {'status': false, 'message': decoded['message'] ?? 'Invalid verification code'};
      }
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 2.2 Resend Student OTP
  Future<Map<String, dynamic>> resendOtp({required String email}) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.auth),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'action': 'resend_otp',
          'email': email,
        }),
      );

      final decoded = json.decode(res.body);
      return {
        'status': decoded['status'] == true,
        'message': decoded['message'] ?? 'OTP dispatch updated',
      };
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 2.5 Google Sign-In
  Future<Map<String, dynamic>> loginWithGoogle({required String email, String? name}) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.auth),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'action': 'google_login',
          'email': email,
          'name': name ?? '',
        }),
      );

      final decoded = json.decode(res.body);
      if (decoded['status'] == true && decoded['data'] != null) {
        final userData = decoded['data']['user'];
        final token = decoded['data']['token'];
        _user = UserModel.fromJson(userData, token: token);

        await _saveUserSession(_user!, token);
        ApiService().setAuth(token, _user?.id);
        notifyListeners();
        return {'status': true, 'message': decoded['message'] ?? 'Google Sign-In successful'};
      } else {
        return {'status': false, 'message': decoded['message'] ?? 'Google Sign-In failed'};
      }
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 3. Admin Login (For adding and managing courses)
  Future<Map<String, dynamic>> loginAdmin(String username, String password) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.auth),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({
          'action': 'admin_login',
          'username': username,
          'password': password,
        }),
      );

      final decoded = json.decode(res.body);
      if (decoded['status'] == true && decoded['data'] != null) {
        final userData = decoded['data']['user'];
        final token = decoded['data']['token'];
        _user = UserModel.fromJson(userData, token: token);

        await _saveUserSession(_user!, token);
        ApiService().setAuth(token, _user?.id);
        notifyListeners();
        return {'status': true, 'message': decoded['message'] ?? 'Admin logged in'};
      } else {
        return {'status': false, 'message': decoded['message'] ?? 'Login failed'};
      }
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  Future<void> logout() async {
    _user = null;
    ApiService().setAuth(null, null);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('user_data');
      await prefs.remove('auth_token');
    } catch (_) {}
    notifyListeners();
  }

  Future<void> _saveUserSession(UserModel user, String? token) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('user_data', json.encode(user.toJson()));
      if (token != null) {
        await prefs.setString('auth_token', token);
      }
    } catch (_) {}
  }
}
