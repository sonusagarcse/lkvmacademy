import 'package:flutter_test/flutter_test.dart';
import 'package:mobile_app/models/lesson_model.dart';
import 'package:mobile_app/widgets/interactive_lecture_player.dart';

void main() {
  group('InteractiveLecturePlayer Utility & Component Tests', () {
    test('extractYoutubeId correctly identifies valid YouTube formats', () {
      expect(
        InteractiveLecturePlayer.extractYoutubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        'dQw4w9WgXcQ',
      );
      expect(
        InteractiveLecturePlayer.extractYoutubeId('https://youtu.be/dQw4w9WgXcQ'),
        'dQw4w9WgXcQ',
      );
      expect(
        InteractiveLecturePlayer.extractYoutubeId('https://www.youtube.com/embed/dQw4w9WgXcQ'),
        'dQw4w9WgXcQ',
      );
      expect(
        InteractiveLecturePlayer.extractYoutubeId('https://www.youtube.com/shorts/dQw4w9WgXcQ'),
        'dQw4w9WgXcQ',
      );
    });

    test('extractYoutubeId returns null for direct video streams', () {
      expect(
        InteractiveLecturePlayer.extractYoutubeId('https://example.com/videos/lesson1.mp4'),
        isNull,
      );
      expect(
        InteractiveLecturePlayer.extractYoutubeId('https://courses.lkvmbihar.in/uploads/demo.m3u8'),
        isNull,
      );
    });

    test('LessonModel creates properly and marks locked appropriately', () {
      final lesson = LessonModel(
        id: 101,
        courseId: 1,
        title: 'Introduction to Computer Fundamentals',
        description: 'First chapter of ADCA Course',
        contentType: 'video',
        contentUrl: 'uploads/videos/lesson1.mp4',
        duration: '15 mins',
        isPreview: false,
        sortOrder: 1,
        isCompleted: false,
        isLocked: false,
      );

      expect(lesson.title, 'Introduction to Computer Fundamentals');
      expect(lesson.contentType, 'video');
      expect(lesson.isLocked, isFalse);
    });
  });
}
