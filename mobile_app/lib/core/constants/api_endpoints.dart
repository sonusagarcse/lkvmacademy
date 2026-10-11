import 'package:flutter/foundation.dart';

class ApiEndpoints {
  // Automatic default based on platform:
  // - Web: http://localhost
  // - Android Emulator: http://10.0.2.2
  // - Physical Phone on same Wi-Fi: change to your PC's IP, e.g. 'http://10.128.51.121'
  // Production server host for both Android APK and Web:
  static String host = 'https://courses.lkvmbihar.in';

  static String get baseUrl => '$host/courses/api';

  static String get auth => '$baseUrl/auth.php';
  static String get courses => '$baseUrl/courses.php';
  static String get categories => '$baseUrl/categories.php';
  static String get lessons => '$baseUrl/lessons.php';
  static String get enroll => '$baseUrl/enroll.php';
  static String get progress => '$baseUrl/progress.php';
  static String get dashboard => '$baseUrl/dashboard.php';

  // Format media asset url if relative path is given
  static String resolveAssetUrl(String? path) {
    if (path == null || path.isEmpty) {
      return 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800';
    }
    if (path.startsWith('http://') || path.startsWith('https://')) {
      return path;
    }
    return '$host/courses/${path.replaceFirst(RegExp(r'^/'), '')}';
  }
}
