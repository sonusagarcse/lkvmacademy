import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../screens/student/student_main_nav.dart';
import '../../screens/instructor/instructor_dashboard_screen.dart';

class AuthRedirect {
  /// Redirects the user after login or registration:
  /// - To the Learning page (index 2) if they have any purchased/enrolled courses.
  /// - To the Explore tab (index 1) if they have no purchased courses.
  /// - To the Instructor dashboard if they are an administrator.
  static Future<void> redirect(BuildContext context) async {
    final auth = AuthService();
    if (auth.isAdmin) {
      if (!context.mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const InstructorDashboardScreen()),
        (route) => false,
      );
      return;
    }

    int targetIndex = 1; // Default: Explore tab
    try {
      final enrollments = await ApiService().getMyEnrollments();
      if (enrollments.isNotEmpty) {
        targetIndex = 2; // Learning page
      }
    } catch (_) {
      targetIndex = 1;
    }

    if (!context.mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(
        builder: (_) => StudentMainNav(initialIndex: targetIndex),
      ),
      (route) => false,
    );
  }
}
