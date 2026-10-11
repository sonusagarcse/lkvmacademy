import 'dart:convert';
import 'package:http/http.dart' as http;
import '../core/constants/api_endpoints.dart';
import '../models/course_model.dart';
import '../models/category_model.dart';
import '../models/enrollment_model.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  String? _authToken;
  int? _currentUserId;

  void setAuth(String? token, int? userId) {
    _authToken = token;
    _currentUserId = userId;
  }

  Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (_authToken != null) 'Authorization': 'Bearer $_authToken',
  };

  // 1. Fetch Categories
  Future<List<CategoryModel>> getCategories() async {
    try {
      final res = await http.get(Uri.parse(ApiEndpoints.categories), headers: _headers);
      if (res.statusCode == 200) {
        final decoded = json.decode(res.body);
        if (decoded['status'] == true && decoded['data'] is List) {
          return (decoded['data'] as List).map((i) => CategoryModel.fromJson(i)).toList();
        }
      }
    } catch (e) {
      // Network or parse error
    }
    return [];
  }

  // 2. Fetch Courses List
  Future<List<CourseModel>> getCourses({
    int? categoryId,
    String? search,
    bool? isFree,
    bool? isFeatured,
    bool allStatus = false,
  }) async {
    try {
      final queryParams = <String, String>{};
      if (categoryId != null && categoryId > 0) queryParams['category_id'] = categoryId.toString();
      if (search != null && search.isNotEmpty) queryParams['search'] = search;
      if (isFree != null) queryParams['is_free'] = isFree ? '1' : '0';
      if (isFeatured != null && isFeatured) queryParams['featured'] = '1';
      if (allStatus) queryParams['all_status'] = '1';
      if (_currentUserId != null) queryParams['user_id'] = _currentUserId.toString();

      final uri = Uri.parse(ApiEndpoints.courses).replace(queryParameters: queryParams);
      final res = await http.get(uri, headers: _headers);

      if (res.statusCode == 200) {
        final decoded = json.decode(res.body);
        if (decoded['status'] == true && decoded['data'] != null && decoded['data']['courses'] is List) {
          return (decoded['data']['courses'] as List).map((i) => CourseModel.fromJson(i)).toList();
        }
      }
    } catch (e) {
      // Network error
    }
    return [];
  }

  // 3. Fetch Single Course Details
  Future<CourseModel?> getCourseDetails({int? id, String? slug}) async {
    try {
      final queryParams = <String, String>{};
      if (id != null) queryParams['id'] = id.toString();
      if (slug != null) queryParams['slug'] = slug;
      if (_currentUserId != null) queryParams['user_id'] = _currentUserId.toString();

      final uri = Uri.parse(ApiEndpoints.courses).replace(queryParameters: queryParams);
      final res = await http.get(uri, headers: _headers);

      if (res.statusCode == 200) {
        final decoded = json.decode(res.body);
        if (decoded['status'] == true && decoded['data'] != null) {
          return CourseModel.fromJson(decoded['data']);
        }
      }
    } catch (e) {
      // Return null on failure
    }
    return null;
  }

  // 4. Create Course (Instructor / Admin Feature)
  Future<Map<String, dynamic>> createCourse(Map<String, dynamic> courseData) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.courses),
        headers: _headers,
        body: json.encode(courseData),
      );
      return json.decode(res.body);
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 5. Add Lesson (Instructor Feature)
  Future<Map<String, dynamic>> addLesson(Map<String, dynamic> lessonData) async {
    try {
      final res = await http.post(
        Uri.parse(ApiEndpoints.lessons),
        headers: _headers,
        body: json.encode(lessonData),
      );
      return json.decode(res.body);
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 6. Enroll / Purchase Course (Student Feature)
  Future<Map<String, dynamic>> enrollCourse({
    required int courseId,
    required String paymentMethod,
    String? paymentId,
  }) async {
    try {
      final body = {
        'course_id': courseId,
        'payment_method': paymentMethod,
        if (paymentId != null) 'payment_id': paymentId,
        if (_currentUserId != null) 'user_id': _currentUserId,
      };

      final res = await http.post(
        Uri.parse(ApiEndpoints.enroll),
        headers: _headers,
        body: json.encode(body),
      );
      return json.decode(res.body);
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 7. Get Enrolled Courses (Student Feature)
  Future<List<EnrollmentModel>> getMyEnrollments() async {
    try {
      final queryParams = <String, String>{};
      if (_currentUserId != null) queryParams['user_id'] = _currentUserId.toString();

      final uri = Uri.parse(ApiEndpoints.enroll).replace(queryParameters: queryParams);
      final res = await http.get(uri, headers: _headers);

      if (res.statusCode == 200) {
        final decoded = json.decode(res.body);
        if (decoded['status'] == true && decoded['data'] != null && decoded['data']['courses'] is List) {
          return (decoded['data']['courses'] as List).map((i) => EnrollmentModel.fromJson(i)).toList();
        }
      }
    } catch (e) {
      // Network error
    }
    return [];
  }

  // 8. Toggle Lesson Progress (Mark Completed)
  Future<Map<String, dynamic>> toggleLessonProgress({
    required int lessonId,
    required int courseId,
    required bool completed,
  }) async {
    try {
      final body = {
        'lesson_id': lessonId,
        'course_id': courseId,
        'completed': completed ? 1 : 0,
        if (_currentUserId != null) 'user_id': _currentUserId,
      };

      final res = await http.post(
        Uri.parse(ApiEndpoints.progress),
        headers: _headers,
        body: json.encode(body),
      );
      return json.decode(res.body);
    } catch (e) {
      return {'status': false, 'message': 'Network error: $e'};
    }
  }

  // 9. Fetch Dashboard Stats
  Future<Map<String, dynamic>?> getDashboardStats({required String type}) async {
    try {
      final queryParams = <String, String>{
        'type': type,
        if (_currentUserId != null) 'user_id': _currentUserId.toString(),
      };
      final uri = Uri.parse(ApiEndpoints.dashboard).replace(queryParameters: queryParams);
      final res = await http.get(uri, headers: _headers);

      if (res.statusCode == 200) {
        final decoded = json.decode(res.body);
        if (decoded['status'] == true && decoded['data'] != null) {
          return decoded['data'];
        }
      }
    } catch (e) {
      // Network error
    }
    return null;
  }
}
