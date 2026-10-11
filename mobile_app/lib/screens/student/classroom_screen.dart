import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../models/course_model.dart';
import '../../models/lesson_model.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../widgets/modern_button.dart';
import '../../widgets/interactive_lecture_player.dart';
import '../auth/login_screen.dart';
import 'checkout_screen.dart';
import 'certificate_screen.dart';

class ClassroomScreen extends StatefulWidget {
  final CourseModel course;
  final int initialLessonIndex;

  const ClassroomScreen({
    super.key,
    required this.course,
    this.initialLessonIndex = 0,
  });

  @override
  State<ClassroomScreen> createState() => _ClassroomScreenState();
}

class _ClassroomScreenState extends State<ClassroomScreen> {
  late List<LessonModel> _lessons;
  late int _currentIndex;
  late bool _isEnrolled;
  bool _isMarkingProgress = false;

  @override
  void initState() {
    super.initState();
    _isEnrolled = widget.course.isEnrolled;
    _lessons = List.from(widget.course.lessons);
    _currentIndex = (widget.initialLessonIndex < _lessons.length)
        ? widget.initialLessonIndex
        : 0;

    // Safety check: If initial index is locked and user not enrolled,
    // try to default to the first free preview lesson instead of a locked one
    if (!_isEnrolled && _lessons.isNotEmpty && !_canAccessLesson(_lessons[_currentIndex])) {
      final firstPreviewIdx = _lessons.indexWhere((l) => l.isPreview && !l.isLocked);
      if (firstPreviewIdx != -1) {
        _currentIndex = firstPreviewIdx;
      }
    }
  }

  bool _canAccessLesson(LessonModel l) {
    if (_isEnrolled) return true;
    return l.isPreview && !l.isLocked;
  }

  LessonModel? get _currentLesson =>
      _lessons.isNotEmpty && _currentIndex < _lessons.length
          ? _lessons[_currentIndex]
          : null;

  int get _completedCount => _lessons.where((l) => l.isCompleted).length;

  int get _progressPercent => _lessons.isNotEmpty
      ? ((_completedCount / _lessons.length) * 100).round()
      : 0;

  Future<void> _refreshCourseAccess() async {
    final updated = await ApiService().getCourseDetails(id: widget.course.id);
    if (updated != null && mounted) {
      setState(() {
        _isEnrolled = updated.isEnrolled;
        _lessons = List.from(updated.lessons);
      });
    }
  }

  void _promptEnrollment([LessonModel? targetedLesson]) {
    final user = AuthService().user;
    if (user == null) {
      showModalBottomSheet(
        context: context,
        backgroundColor: Colors.transparent,
        builder: (ctx) {
          final isDark = Theme.of(ctx).brightness == Brightness.dark;
          return Container(
            padding: const EdgeInsets.all(28),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF1E293B) : Colors.white,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 64,
                  height: 64,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.12),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.account_circle_outlined, color: AppColors.primaryLight, size: 36),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Account Required',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Text(
                  'Please sign in or create an account to enroll in "${widget.course.title}" and unlock full access to all lectures.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.grey, fontSize: 13, height: 1.4),
                ),
                const SizedBox(height: 24),
                ModernButton(
                  text: 'Sign In / Register',
                  icon: Icons.login_rounded,
                  onPressed: () async {
                    Navigator.of(ctx).pop();
                    final loggedIn = await Navigator.of(context).push<bool>(
                      MaterialPageRoute(builder: (_) => const LoginScreen()),
                    );
                    if (loggedIn == true && mounted) {
                      _promptEnrollment(targetedLesson);
                    }
                  },
                ),
              ],
            ),
          );
        },
      );
      return;
    }

    // User is logged in, navigate to Checkout Screen
    Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => CheckoutScreen(course: widget.course),
      ),
    ).then((enrolled) {
      if (enrolled == true) {
        _refreshCourseAccess();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('🎉 Enrollment confirmed! All course lectures unlocked.'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    });
  }

  void _showLockedBottomSheet(LessonModel lesson) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (ctx) {
        return Container(
          padding: const EdgeInsets.all(28),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E293B) : Colors.white,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 70,
                height: 70,
                decoration: BoxDecoration(
                  color: Colors.amber.withOpacity(0.15),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.lock_rounded, color: Colors.amber, size: 40),
              ),
              const SizedBox(height: 18),
              const Text(
                'Lecture Locked 🔒',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              Text(
                'Unlock "${lesson.title}"',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.primaryLight),
              ),
              const SizedBox(height: 8),
              Text(
                'This lecture is exclusively available to enrolled students of "${widget.course.title}". Enroll now to get instant lifetime access to all lectures, downloadable PDF notes, and certificate.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 13, color: Colors.grey, height: 1.4),
              ),
              const SizedBox(height: 20),
              // Perks
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF0F172A) : const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                  ),
                ),
                child: Column(
                  children: [
                    _buildPerkRow(Icons.ondemand_video_rounded, 'Access to all ${_lessons.length} video lectures'),
                    const SizedBox(height: 8),
                    _buildPerkRow(Icons.picture_as_pdf_rounded, 'Downloadable course materials & PDF notes'),
                    const SizedBox(height: 8),
                    _buildPerkRow(Icons.workspace_premium_rounded, 'Verifiable Certificate of Completion'),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              ModernButton(
                text: widget.course.isFree
                    ? 'Enroll Now for Free'
                    : 'Enroll Now • ₹${widget.course.effectivePrice.toStringAsFixed(0)}',
                icon: Icons.lock_open_rounded,
                onPressed: () {
                  Navigator.of(ctx).pop();
                  _promptEnrollment(lesson);
                },
              ),
              const SizedBox(height: 10),
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text('Continue Preview', style: TextStyle(color: Colors.grey)),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildPerkRow(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, size: 18, color: AppColors.primaryLight),
        const SizedBox(width: 10),
        Expanded(
          child: Text(text, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500)),
        ),
      ],
    );
  }

  Future<void> _toggleLessonCompletion() async {
    if (!_isEnrolled) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('🔒 Please enroll to track your progress and earn a Certificate.'),
          backgroundColor: AppColors.warning,
        ),
      );
      _promptEnrollment();
      return;
    }

    final lesson = _currentLesson;
    if (lesson == null) return;

    setState(() => _isMarkingProgress = true);

    final newStatus = !lesson.isCompleted;
    final res = await ApiService().toggleLessonProgress(
      lessonId: lesson.id,
      courseId: widget.course.id,
      completed: newStatus,
    );

    if (!mounted) return;
    setState(() {
      _isMarkingProgress = false;
      lesson.isCompleted = newStatus;
    });

    if (res['status'] == true) {
      if (res['data'] != null && res['data']['certificate_awarded'] == true) {
        _showCertificateCelebrationModal();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(newStatus ? 'Lesson completed! Great job.' : 'Lesson unmarked.'),
            backgroundColor: newStatus ? AppColors.success : Colors.grey,
            duration: const Duration(seconds: 2),
          ),
        );
      }
    }
  }

  void _showCertificateCelebrationModal() {
    showModalBottomSheet(
      context: context,
      isDismissible: false,
      backgroundColor: Colors.transparent,
      builder: (context) {
        final isDark = Theme.of(context).brightness == Brightness.dark;
        return Container(
          padding: const EdgeInsets.all(28),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E293B) : Colors.white,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(colors: [Color(0xFFF59E0B), Color(0xFFD97706)]),
                ),
                child: const Icon(Icons.workspace_premium_rounded, color: Colors.white, size: 44),
              ),
              const SizedBox(height: 18),
              const Text(
                'Congratulations! 🎓',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              Text(
                'You have completed 100% of "${widget.course.title}". Your official Certificate of Completion is now ready!',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 14, color: Colors.grey),
              ),
              const SizedBox(height: 24),
              ModernButton(
                text: 'View Official Certificate',
                icon: Icons.workspace_premium,
                gradient: const LinearGradient(colors: [Color(0xFFF59E0B), Color(0xFFB45309)]),
                onPressed: () {
                  Navigator.of(context).pop();
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => CertificateScreen(
                        courseTitle: widget.course.title,
                        studentName: AuthService().user?.name ?? 'Learner',
                        courseId: widget.course.id,
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _goToNextLesson() {
    if (_currentIndex < _lessons.length - 1) {
      final nextLesson = _lessons[_currentIndex + 1];
      if (_canAccessLesson(nextLesson)) {
        setState(() => _currentIndex++);
      } else {
        _showLockedBottomSheet(nextLesson);
      }
    }
  }

  void _goToPreviousLesson() {
    if (_currentIndex > 0) {
      final prevLesson = _lessons[_currentIndex - 1];
      if (_canAccessLesson(prevLesson)) {
        setState(() => _currentIndex--);
      } else {
        _showLockedBottomSheet(prevLesson);
      }
    }
  }

  Widget _buildLockedLectureGate(LessonModel lesson) {
    return AspectRatio(
      aspectRatio: 16 / 9,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 18),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF0F172A), Color(0xFF1E293B)],
          ),
          border: Border.all(color: Colors.amber.withOpacity(0.3), width: 1.5),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.amber.withOpacity(0.18),
                shape: BoxShape.circle,
                border: Border.all(color: Colors.amber, width: 2),
              ),
              child: const Icon(Icons.lock_rounded, color: Colors.amber, size: 32),
            ),
            const SizedBox(height: 10),
            const Text(
              'This Lecture is Locked 🔒',
              style: TextStyle(
                color: Colors.white,
                fontSize: 16,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              lesson.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.amber.shade200,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Sign in & enroll in this course to unlock all lectures, notes & certificate.',
              textAlign: TextAlign.center,
              maxLines: 2,
              style: TextStyle(color: Colors.white70, fontSize: 11),
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 38,
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.amber,
                  foregroundColor: Colors.black87,
                  elevation: 4,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.symmetric(horizontal: 18),
                ),
                icon: const Icon(Icons.lock_open_rounded, size: 18),
                label: Text(
                  widget.course.isFree
                      ? 'Enroll Free to Watch'
                      : 'Enroll Now • ₹${widget.course.effectivePrice.toStringAsFixed(0)}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                ),
                onPressed: () => _showLockedBottomSheet(lesson),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final lesson = _currentLesson;
    final isAccessible = lesson != null && _canAccessLesson(lesson);

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.course.title, maxLines: 1, overflow: TextOverflow.ellipsis),
        actions: [
          IconButton(
            icon: const Icon(Icons.workspace_premium_rounded, color: Colors.amber),
            tooltip: 'Certificate',
            onPressed: () {
              if (!_isEnrolled) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('🔒 Enroll in the course to unlock certificate generation.'),
                    backgroundColor: AppColors.warning,
                  ),
                );
                _promptEnrollment();
                return;
              }
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => CertificateScreen(
                    courseTitle: widget.course.title,
                    studentName: AuthService().user?.name ?? 'Learner',
                    courseId: widget.course.id,
                  ),
                ),
              );
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // 1. Live Interactive Lecture / Video Player OR Locked Lecture Gate
          if (lesson != null)
            isAccessible
                ? InteractiveLecturePlayer(
                    lesson: lesson,
                    courseTitle: widget.course.title,
                    fallbackThumbnail: widget.course.thumbnail,
                  )
                : _buildLockedLectureGate(lesson),

          // 2. Lesson Title, Navigation & Progress Strip
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Progress Strip
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        _isEnrolled
                            ? 'Course Progress: $_progressPercent%'
                            : 'Preview Mode',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                          color: _isEnrolled ? null : Colors.amber.shade700,
                        ),
                      ),
                      Text(
                        _isEnrolled
                            ? '$_completedCount of ${_lessons.length} Lectures Completed'
                            : 'Enroll to track progress',
                        style: const TextStyle(color: Colors.grey, fontSize: 12),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: _isEnrolled ? (_progressPercent / 100).clamp(0.0, 1.0) : 0.05,
                      minHeight: 6,
                      backgroundColor: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                      valueColor: AlwaysStoppedAnimation<Color>(
                        _isEnrolled ? AppColors.primaryLight : Colors.amber,
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),

                  if (lesson != null) ...[
                    // Lesson Index, Duration & Status Badge
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppColors.primary.withOpacity(0.12),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            'Lecture ${_currentIndex + 1} of ${_lessons.length}',
                            style: const TextStyle(
                              color: AppColors.primaryLight,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          lesson.duration,
                          style: const TextStyle(color: Colors.grey, fontSize: 12),
                        ),
                        const Spacer(),
                        if (!isAccessible)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: Colors.amber.withOpacity(0.2),
                              borderRadius: BorderRadius.circular(6),
                              border: Border.all(color: Colors.amber, width: 1),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.lock_rounded, size: 12, color: Colors.amber),
                                SizedBox(width: 4),
                                Text(
                                  'LOCKED',
                                  style: TextStyle(
                                    color: Colors.amber,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          )
                        else if (lesson.isPreview && !_isEnrolled)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: AppColors.accent.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'FREE PREVIEW',
                              style: TextStyle(
                                color: AppColors.accent,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 10),

                    // Lesson Title
                    Text(
                      lesson.title,
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, height: 1.3),
                    ),
                    const SizedBox(height: 12),

                    // Complete / Enroll Action Button
                    if (isAccessible && _isEnrolled)
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: lesson.isCompleted ? AppColors.success : AppColors.primary,
                          foregroundColor: Colors.white,
                          minimumSize: const Size(double.infinity, 48),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        icon: _isMarkingProgress
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : Icon(
                                lesson.isCompleted ? Icons.check_circle_rounded : Icons.radio_button_unchecked,
                                size: 20,
                              ),
                        label: Text(
                          lesson.isCompleted ? 'Completed (Tap to Unmark)' : 'Mark Lecture as Completed',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        onPressed: _isMarkingProgress ? null : _toggleLessonCompletion,
                      )
                    else if (!isAccessible)
                      ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.amber.shade700,
                          foregroundColor: Colors.white,
                          minimumSize: const Size(double.infinity, 48),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        icon: const Icon(Icons.lock_open_rounded, size: 20),
                        label: Text(
                          widget.course.isFree
                              ? 'Enroll Free to Access'
                              : 'Unlock This Lecture • ₹${widget.course.effectivePrice.toStringAsFixed(0)}',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        onPressed: () => _showLockedBottomSheet(lesson),
                      )
                    else
                      // Free preview mode message
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: AppColors.accent.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.accent.withOpacity(0.2)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.info_outline_rounded, size: 18, color: AppColors.accent),
                            const SizedBox(width: 10),
                            const Expanded(
                              child: Text(
                                'You are viewing a Free Preview lecture. Enroll to unlock the full course.',
                                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
                              ),
                            ),
                            TextButton(
                              onPressed: () => _promptEnrollment(lesson),
                              child: const Text('Enroll Now', style: TextStyle(fontWeight: FontWeight.bold)),
                            ),
                          ],
                        ),
                      ),

                    const SizedBox(height: 16),

                    // Prev / Next Controls
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            icon: const Icon(Icons.skip_previous_rounded, size: 18),
                            label: const Text('Previous'),
                            onPressed: _currentIndex > 0 ? _goToPreviousLesson : null,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: OutlinedButton.icon(
                            icon: const Icon(Icons.skip_next_rounded, size: 18),
                            label: const Text('Next Lecture'),
                            onPressed: _currentIndex < _lessons.length - 1 ? _goToNextLesson : null,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // Curriculum List Accordion
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'All Course Lectures',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
                        ),
                        Text(
                          '${_lessons.length} Lectures',
                          style: const TextStyle(fontSize: 12, color: Colors.grey),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _lessons.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (context, idx) {
                        final l = _lessons[idx];
                        final isCurrent = idx == _currentIndex;
                        final lAccessible = _canAccessLesson(l);

                        return Container(
                          decoration: BoxDecoration(
                            color: isCurrent
                                ? AppColors.primary.withOpacity(0.08)
                                : (isDark ? const Color(0xFF1E293B) : Colors.white),
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                              color: isCurrent
                                  ? AppColors.primaryLight
                                  : (isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0)),
                              width: isCurrent ? 1.5 : 1,
                            ),
                          ),
                          child: ListTile(
                            onTap: () {
                              if (lAccessible) {
                                setState(() => _currentIndex = idx);
                              } else {
                                _showLockedBottomSheet(l);
                              }
                            },
                            leading: Icon(
                              l.isCompleted
                                  ? Icons.check_circle_rounded
                                  : (lAccessible
                                      ? Icons.play_circle_outline_rounded
                                      : Icons.lock_outline_rounded),
                              color: l.isCompleted
                                  ? AppColors.success
                                  : (lAccessible
                                      ? (isCurrent ? AppColors.primaryLight : Colors.grey)
                                      : Colors.amber.shade700),
                              size: 22,
                            ),
                            title: Text(
                              '${idx + 1}. ${l.title}',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: isCurrent ? FontWeight.bold : FontWeight.w500,
                                color: isCurrent
                                    ? AppColors.primaryLight
                                    : (lAccessible ? null : Colors.grey),
                              ),
                            ),
                            subtitle: Row(
                              children: [
                                Text(l.duration, style: const TextStyle(fontSize: 11, color: Colors.grey)),
                                if (l.isPreview && !_isEnrolled) ...[
                                  const SizedBox(width: 6),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                    decoration: BoxDecoration(
                                      color: AppColors.accent.withOpacity(0.12),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: const Text(
                                      'PREVIEW',
                                      style: TextStyle(
                                        color: AppColors.accent,
                                        fontSize: 9,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            trailing: isCurrent
                                ? Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: AppColors.primary,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: const Text(
                                      'PLAYING',
                                      style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                                    ),
                                  )
                                : (!lAccessible
                                    ? const Icon(Icons.lock_rounded, size: 16, color: Colors.amber)
                                    : null),
                          ),
                        );
                      },
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
