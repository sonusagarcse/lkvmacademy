import 'package:flutter/foundation.dart';

class ApiEndpoints {
  // Production server host:
  static String host = 'https://courses.lkvmbihar.in';

  // Automatically resolves API base URL:
  // - On live server (courses.lkvmbihar.in): https://courses.lkvmbihar.in/api
  // - On localhost: http://localhost/courses/api
  static String get baseUrl {
    if (host.contains('courses.lkvmbihar.in')) {
      return '$host/api';
    }
    return '$host/courses/api';
  }

  static String get auth => '$baseUrl/auth.php';
  static String get courses => '$baseUrl/courses.php';
  static String get categories => '$baseUrl/categories.php';
  static String get lessons => '$baseUrl/lessons.php';
  static String get enroll => '$baseUrl/enroll.php';
  static String get progress => '$baseUrl/progress.php';
  static String get dashboard => '$baseUrl/dashboard.php';
  static String get settings => '$baseUrl/settings.php';

  // Format media asset url if relative path is given
  static String resolveAssetUrl(String? path) {
    if (path == null || path.isEmpty) {
      return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800';
    }
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    final cleanPath = path.replaceFirst(RegExp(r'^/'), '');
    if (host.contains('courses.lkvmbihar.in')) {
      return '$host/$cleanPath';
    }
    return '$host/courses/$cleanPath';
  }
}
