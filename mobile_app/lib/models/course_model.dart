import 'lesson_model.dart';

class CourseModel {
  final int id;
  final String title;
  final String slug;
  final String shortDesc;
  final String longDesc;
  final String thumbnail;
  final String previewVideo;
  final double price;
  final double? discountPrice;
  final bool isFree;
  final String duration;
  final String level;
  final String instructor;
  final int categoryId;
  final String? categoryName;
  final bool isFeatured;
  final int lessonsCount;
  final int studentsCount;
  final bool isEnrolled;
  final int progressPercent;
  final int completedLessonsCount;
  final List<LessonModel> lessons;

  CourseModel({
    required this.id,
    required this.title,
    required this.slug,
    required this.shortDesc,
    required this.longDesc,
    required this.thumbnail,
    required this.previewVideo,
    required this.price,
    this.discountPrice,
    required this.isFree,
    required this.duration,
    required this.level,
    required this.instructor,
    required this.categoryId,
    this.categoryName,
    required this.isFeatured,
    this.lessonsCount = 0,
    this.studentsCount = 0,
    this.isEnrolled = false,
    this.progressPercent = 0,
    this.completedLessonsCount = 0,
    this.lessons = const [],
  });

  double get effectivePrice => isFree ? 0.0 : (discountPrice ?? price);

  factory CourseModel.fromJson(Map<String, dynamic> json) {
    List<LessonModel> lessonList = [];
    if (json['lessons'] is List) {
      lessonList = (json['lessons'] as List)
          .map((item) => LessonModel.fromJson(item))
          .toList();
    }

    return CourseModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      title: json['title'] ?? '',
      slug: json['slug'] ?? '',
      shortDesc: json['short_desc'] ?? '',
      longDesc: json['long_desc'] ?? '',
      thumbnail: json['thumbnail'] ?? '',
      previewVideo: json['preview_video'] ?? '',
      price: double.tryParse(json['price'].toString()) ?? 0.0,
      discountPrice: json['discount_price'] != null ? double.tryParse(json['discount_price'].toString()) : null,
      isFree: json['is_free'] == true || json['is_free'] == 1 || json['is_free'] == '1',
      duration: json['duration'] ?? '10 Hours',
      level: json['level'] ?? 'All Levels',
      instructor: json['instructor'] ?? 'Senior Faculty',
      categoryId: int.tryParse(json['category_id'].toString()) ?? 0,
      categoryName: json['category_name'],
      isFeatured: json['is_featured'] == true || json['is_featured'] == 1 || json['is_featured'] == '1',
      lessonsCount: int.tryParse(json['lessons_count'].toString()) ?? lessonList.length,
      studentsCount: int.tryParse(json['students_count'].toString()) ?? 0,
      isEnrolled: json['is_enrolled'] == true || json['is_enrolled'] == 1,
      progressPercent: int.tryParse(json['progress_percent'].toString()) ?? 0,
      completedLessonsCount: int.tryParse(json['completed_lessons_count'].toString()) ?? 0,
      lessons: lessonList,
    );
  }
}
