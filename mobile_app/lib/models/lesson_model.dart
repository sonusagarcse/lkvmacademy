class LessonModel {
  final int id;
  final int courseId;
  final String title;
  final String description;
  final String contentType; // 'youtube', 'video', 'pdf', 'text', 'file'
  final String contentUrl;
  final String duration;
  final bool isPreview;
  final int sortOrder;
  bool isCompleted;
  final bool isLocked;

  LessonModel({
    required this.id,
    required this.courseId,
    required this.title,
    required this.description,
    required this.contentType,
    required this.contentUrl,
    required this.duration,
    required this.isPreview,
    required this.sortOrder,
    this.isCompleted = false,
    this.isLocked = false,
  });

  factory LessonModel.fromJson(Map<String, dynamic> json) {
    final preview = json['is_preview'] == true || json['is_preview'] == 1 || json['is_preview'] == '1';
    final locked = json['is_locked'] == true || (json['content_url'] == '' && !preview);

    return LessonModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      courseId: int.tryParse(json['course_id'].toString()) ?? 0,
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      contentType: json['content_type'] ?? 'youtube',
      contentUrl: json['content_url'] ?? '',
      duration: json['duration'] ?? '10 mins',
      isPreview: preview,
      sortOrder: int.tryParse(json['sort_order'].toString()) ?? 0,
      isCompleted: json['is_completed'] == true || json['is_completed'] == 1 || json['is_completed'] == '1',
      isLocked: locked,
    );
  }
}
