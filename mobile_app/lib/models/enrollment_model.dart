class EnrollmentModel {
  final int enrollmentId;
  final int courseId;
  final String title;
  final String slug;
  final String thumbnail;
  final String instructor;
  final String level;
  final String duration;
  final double paidAmount;
  final String paymentMethod;
  final int totalLessons;
  final int completedLessons;
  final int progressPercent;
  final bool isCompleted;
  final String? enrolledAt;

  EnrollmentModel({
    required this.enrollmentId,
    required this.courseId,
    required this.title,
    required this.slug,
    required this.thumbnail,
    required this.instructor,
    required this.level,
    required this.duration,
    required this.paidAmount,
    required this.paymentMethod,
    required this.totalLessons,
    required this.completedLessons,
    required this.progressPercent,
    required this.isCompleted,
    this.enrolledAt,
  });

  factory EnrollmentModel.fromJson(Map<String, dynamic> json) {
    return EnrollmentModel(
      enrollmentId: int.tryParse(json['enrollment_id'].toString()) ?? 0,
      courseId: int.tryParse(json['course_id'].toString()) ?? 0,
      title: json['title'] ?? '',
      slug: json['slug'] ?? '',
      thumbnail: json['thumbnail'] ?? '',
      instructor: json['instructor'] ?? 'LKVM Faculty',
      level: json['level'] ?? 'All Levels',
      duration: json['duration'] ?? '',
      paidAmount: double.tryParse(json['paid_amount'].toString()) ?? 0.0,
      paymentMethod: json['payment_method'] ?? 'online',
      totalLessons: int.tryParse(json['total_lessons'].toString()) ?? 0,
      completedLessons: int.tryParse(json['completed_lessons'].toString()) ?? 0,
      progressPercent: int.tryParse(json['progress_percent'].toString()) ?? 0,
      isCompleted: json['is_completed'] == true || json['is_completed'] == 1,
      enrolledAt: json['enrolled_at'],
    );
  }
}
