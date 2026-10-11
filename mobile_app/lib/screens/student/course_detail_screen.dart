import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../core/constants/api_endpoints.dart';
import '../../models/course_model.dart';
import '../../models/lesson_model.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../widgets/modern_button.dart';
import 'checkout_screen.dart';
import 'classroom_screen.dart';
import '../auth/login_screen.dart';

class CourseDetailScreen extends StatefulWidget {
  final int courseId;
  const CourseDetailScreen({super.key, required this.courseId});

  @override
  State<CourseDetailScreen> createState() => _CourseDetailScreenState();
}

class _CourseDetailScreenState extends State<CourseDetailScreen> {
  CourseModel? _course;
  bool _isLoading = true;
  bool _isDescriptionExpanded = false;

  @override
  void initState() {
    super.initState();
    _loadCourse();
  }

  Future<void> _loadCourse() async {
    setState(() => _isLoading = true);
    final course = await ApiService().getCourseDetails(id: widget.courseId);
    if (mounted) {
      setState(() {
        _course = course;
        _isLoading = false;
      });
    }
  }

  void _onEnrollOrContinue() {
    if (_course == null) return;

    final auth = AuthService();
    if (!auth.isAuthenticated) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => LoginScreen(
            onLoginSuccess: () {
              Navigator.of(context).pop();
              _loadCourse();
            },
          ),
        ),
      );
      return;
    }

    if (_course!.isEnrolled) {
      // Go directly to classroom
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ClassroomScreen(
            course: _course!,
            initialLessonIndex: 0,
          ),
        ),
      ).then((_) => _loadCourse());
    } else {
      // Go to Checkout Screen
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => CheckoutScreen(course: _course!),
        ),
      ).then((enrolled) {
        if (enrolled == true) {
          _loadCourse();
        }
      });
    }
  }

  void _onLessonTap(LessonModel lesson, int index) {
    if (_course == null) return;
    if (_course!.isEnrolled || (lesson.isPreview && !lesson.isLocked)) {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ClassroomScreen(
            course: _course!,
            initialLessonIndex: index,
          ),
        ),
      ).then((_) => _loadCourse());
    } else {
      _showLockedBottomSheet(lesson);
    }
  }

  void _showLockedBottomSheet(LessonModel lesson) {
    if (_course == null) return;
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
                width: 68,
                height: 68,
                decoration: BoxDecoration(
                  color: Colors.amber.withOpacity(0.15),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.lock_rounded, color: Colors.amber, size: 38),
              ),
              const SizedBox(height: 16),
              const Text(
                'Lecture Locked 🔒',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Text(
                'Unlock "${lesson.title}"',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: AppColors.primaryLight),
              ),
              const SizedBox(height: 8),
              Text(
                'To view this lecture, please enroll in "${_course!.title}". Get lifetime access to all ${_course!.lessons.length} lectures, PDF study materials, and Certificate.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 13, color: Colors.grey, height: 1.4),
              ),
              const SizedBox(height: 24),
              ModernButton(
                text: _course!.isFree
                    ? 'Enroll Now for Free'
                    : 'Enroll Now • ₹${_course!.effectivePrice.toStringAsFixed(0)}',
                icon: Icons.lock_open_rounded,
                onPressed: () {
                  Navigator.of(ctx).pop();
                  _onEnrollOrContinue();
                },
              ),
              const SizedBox(height: 10),
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text('Cancel', style: TextStyle(color: Colors.grey)),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    if (_isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_course == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Course Details')),
        body: const Center(child: Text('Course details could not be loaded.')),
      );
    }

    final c = _course!;

    return Scaffold(
      body: Stack(
        children: [
          CustomScrollView(
            slivers: [
              // 1. App Bar with Course Thumbnail Image
              SliverAppBar(
                expandedHeight: 240,
                pinned: true,
                flexibleSpace: FlexibleSpaceBar(
                  background: Stack(
                    fit: StackFit.expand,
                    children: [
                      Image.network(
                        ApiEndpoints.resolveAssetUrl(c.thumbnail),
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Container(
                          color: AppColors.primaryDark,
                          child: const Center(
                            child: Icon(Icons.school, size: 64, color: Colors.white70),
                          ),
                        ),
                      ),
                      Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [
                              Colors.black.withOpacity(0.3),
                              Colors.black.withOpacity(0.8),
                            ],
                          ),
                        ),
                      ),
                      if (c.previewVideo.isNotEmpty || c.lessons.any((l) => l.isPreview))
                        Center(
                          child: GestureDetector(
                            onTap: () {
                              if (c.lessons.isNotEmpty) {
                                final previewLesson = c.lessons.firstWhere(
                                  (l) => l.isPreview,
                                  orElse: () => c.lessons.first,
                                );
                                final idx = c.lessons.indexOf(previewLesson);
                                _onLessonTap(previewLesson, idx);
                              }
                            },
                            child: Container(
                              width: 60,
                              height: 60,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: Colors.white.withOpacity(0.9),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withOpacity(0.4),
                                    blurRadius: 16,
                                  )
                                ],
                              ),
                              child: const Icon(
                                Icons.play_arrow_rounded,
                                size: 36,
                                color: AppColors.primary,
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),

              // 2. Course Details Body
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Category & Badges
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(
                              color: AppColors.primary.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              c.categoryName ?? 'Category',
                              style: const TextStyle(
                                color: AppColors.primaryLight,
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(
                              color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              c.level,
                              style: TextStyle(
                                color: isDark ? Colors.white70 : Colors.black87,
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                          if (c.isEnrolled) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                              decoration: BoxDecoration(
                                color: AppColors.success.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.check_circle, size: 13, color: AppColors.success),
                                  SizedBox(width: 4),
                                  Text(
                                    'Enrolled',
                                    style: TextStyle(
                                      color: AppColors.success,
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 14),

                      // Title
                      Text(
                        c.title,
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.w800,
                          height: 1.25,
                        ),
                      ),
                      const SizedBox(height: 12),

                      // Duration, Lessons, Certificate badges
                      Row(
                        children: [
                          const Icon(Icons.timer_outlined, size: 16, color: Colors.grey),
                          const SizedBox(width: 6),
                          Text(c.duration, style: const TextStyle(color: Colors.grey, fontSize: 13)),
                          const SizedBox(width: 16),
                          const Icon(Icons.play_lesson_outlined, size: 16, color: Colors.grey),
                          const SizedBox(width: 6),
                          Text('${c.lessonsCount} Lectures', style: const TextStyle(color: Colors.grey, fontSize: 13)),
                          const SizedBox(width: 16),
                          const Icon(Icons.workspace_premium_outlined, size: 16, color: Colors.amber),
                          const SizedBox(width: 6),
                          const Text('Certificate', style: TextStyle(color: Colors.amber, fontSize: 13, fontWeight: FontWeight.w600)),
                        ],
                      ),
                      const SizedBox(height: 20),

                      // Instructor Card
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                          ),
                        ),
                        child: Row(
                          children: [
                            CircleAvatar(
                              radius: 24,
                              backgroundColor: AppColors.primary,
                              child: const Icon(Icons.person, color: Colors.white, size: 26),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'Instructor',
                                    style: TextStyle(fontSize: 11, color: Colors.grey, fontWeight: FontWeight.bold),
                                  ),
                                  Text(
                                    c.instructor,
                                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                                  ),
                                  const Text(
                                    'LKVM Certified Faculty',
                                    style: TextStyle(fontSize: 12, color: AppColors.primaryLight),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Description
                      const Text(
                        'About This Course',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _isDescriptionExpanded
                            ? (c.longDesc.isNotEmpty ? c.longDesc : c.shortDesc)
                            : c.shortDesc,
                        style: TextStyle(
                          fontSize: 14,
                          height: 1.6,
                          color: isDark ? Colors.white70 : Colors.black87,
                        ),
                      ),
                      if (c.longDesc.isNotEmpty && c.longDesc != c.shortDesc)
                        GestureDetector(
                          onTap: () => setState(() => _isDescriptionExpanded = !_isDescriptionExpanded),
                          child: Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: Text(
                              _isDescriptionExpanded ? 'Read Less' : 'Read More',
                              style: const TextStyle(
                                color: AppColors.primaryLight,
                                fontWeight: FontWeight.bold,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ),
                      const SizedBox(height: 24),

                      // Curriculum Syllabus Header
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Curriculum & Lessons',
                            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                          ),
                          Text(
                            '${c.lessons.length} Lectures',
                            style: const TextStyle(color: Colors.grey, fontSize: 13),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),

                      // Lessons List
                      if (c.lessons.isEmpty)
                        Container(
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                            color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Center(
                            child: Text(
                              'Lectures are currently being organized.',
                              style: TextStyle(color: Colors.grey),
                            ),
                          ),
                        )
                      else
                        ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: c.lessons.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final lesson = c.lessons[index];
                            final isAccessible = c.isEnrolled || lesson.isPreview;

                            return Container(
                              decoration: BoxDecoration(
                                color: isDark ? const Color(0xFF1E293B) : Colors.white,
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                  color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                                ),
                              ),
                              child: ListTile(
                                onTap: () => _onLessonTap(lesson, index),
                                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                                leading: Container(
                                  width: 40,
                                  height: 40,
                                  decoration: BoxDecoration(
                                    color: lesson.isCompleted
                                        ? AppColors.success.withOpacity(0.15)
                                        : (isAccessible
                                            ? AppColors.primary.withOpacity(0.12)
                                            : Colors.grey.withOpacity(0.12)),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Icon(
                                    lesson.isCompleted
                                        ? Icons.check_circle_rounded
                                        : (isAccessible ? Icons.play_arrow_rounded : Icons.lock_outline_rounded),
                                    color: lesson.isCompleted
                                        ? AppColors.success
                                        : (isAccessible ? AppColors.primaryLight : Colors.grey),
                                    size: 20,
                                  ),
                                ),
                                title: Text(
                                  '${index + 1}. ${lesson.title}',
                                  style: TextStyle(
                                    fontWeight: FontWeight.w600,
                                    fontSize: 14,
                                    color: isAccessible ? null : Colors.grey,
                                  ),
                                ),
                                subtitle: Row(
                                  children: [
                                    Text(
                                      lesson.duration,
                                      style: const TextStyle(fontSize: 12, color: Colors.grey),
                                    ),
                                    if (lesson.isPreview && !c.isEnrolled) ...[
                                      const SizedBox(width: 8),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: AppColors.accent.withOpacity(0.15),
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: const Text(
                                          'Free Preview',
                                          style: TextStyle(
                                            color: AppColors.accent,
                                            fontSize: 10,
                                            fontWeight: FontWeight.bold,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                                trailing: Icon(
                                  isAccessible ? Icons.chevron_right_rounded : Icons.lock_rounded,
                                  size: 18,
                                  color: isAccessible ? AppColors.primaryLight : Colors.grey,
                                ),
                              ),
                            );
                          },
                        ),
                    ],
                  ),
                ),
              ),
            ],
          ),

          // 3. Sticky Bottom Action Bar
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF0F172A) : Colors.white,
                border: Border(
                  top: BorderSide(
                    color: isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0),
                  ),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 16,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: SafeArea(
                child: Row(
                  children: [
                    // Price info
                    if (!c.isEnrolled) ...[
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Text(
                            'Course Price',
                            style: TextStyle(fontSize: 11, color: Colors.grey, fontWeight: FontWeight.bold),
                          ),
                          if (c.isFree)
                            const Text(
                              'FREE',
                              style: TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                color: AppColors.success,
                              ),
                            )
                          else
                            Row(
                              children: [
                                Text(
                                  '₹${c.effectivePrice.toStringAsFixed(0)}',
                                  style: const TextStyle(
                                    fontSize: 22,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primaryLight,
                                  ),
                                ),
                                if (c.discountPrice != null) ...[
                                  const SizedBox(width: 6),
                                  Text(
                                    '₹${c.price.toStringAsFixed(0)}',
                                    style: const TextStyle(
                                      fontSize: 13,
                                      decoration: TextDecoration.lineThrough,
                                      color: Colors.grey,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                        ],
                      ),
                      const SizedBox(width: 20),
                    ],

                    // Action Button
                    Expanded(
                      child: ModernButton(
                        text: c.isEnrolled
                            ? 'Continue Learning (${c.progressPercent}%)'
                            : (c.isFree ? 'Enroll for Free' : 'Enroll Now'),
                        icon: c.isEnrolled ? Icons.play_arrow_rounded : Icons.shopping_bag_outlined,
                        onPressed: _onEnrollOrContinue,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
