import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../services/api_service.dart';
import '../../widgets/modern_button.dart';
import '../../widgets/custom_text_field.dart';

class AddLessonScreen extends StatefulWidget {
  final int courseId;
  final String courseTitle;

  const AddLessonScreen({
    super.key,
    required this.courseId,
    required this.courseTitle,
  });

  @override
  State<AddLessonScreen> createState() => _AddLessonScreenState();
}

class _AddLessonScreenState extends State<AddLessonScreen> {
  final _titleController = TextEditingController();
  final _urlController = TextEditingController(text: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  final _durationController = TextEditingController(text: '15 mins');
  final _descController = TextEditingController();

  String _contentType = 'youtube'; // 'youtube', 'video', 'pdf', 'text'
  bool _isPreview = false;
  bool _isLoading = false;
  String? _errorMessage;

  final List<String> _types = ['youtube', 'video', 'pdf', 'text'];

  @override
  void dispose() {
    _titleController.dispose();
    _urlController.dispose();
    _durationController.dispose();
    _descController.dispose();
    super.dispose();
  }

  Future<void> _submitLesson() async {
    final title = _titleController.text.trim();
    if (title.isEmpty) {
      setState(() => _errorMessage = 'Lesson title is required.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final payload = {
      'action': 'create',
      'course_id': widget.courseId,
      'title': title,
      'description': _descController.text.trim(),
      'content_type': _contentType,
      'content_url': _urlController.text.trim(),
      'duration': _durationController.text.trim(),
      'is_preview': _isPreview ? 1 : 0,
    };

    final res = await ApiService().addLesson(payload);

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (res['status'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Lecture added to course curriculum!'),
          backgroundColor: AppColors.success,
        ),
      );
      Navigator.of(context).pop(true);
    } else {
      setState(() {
        _errorMessage = res['message'] ?? 'Failed to add lesson.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Add Lecture'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                children: [
                  const Icon(Icons.school, color: AppColors.primaryLight, size: 20),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Adding to: ${widget.courseTitle}',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            if (_errorMessage != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.danger.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.danger.withOpacity(0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline, color: AppColors.danger, size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _errorMessage!,
                        style: const TextStyle(color: AppColors.danger, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            CustomTextField(
              controller: _titleController,
              label: 'Lecture Title *',
              hint: 'e.g. State Management with Provider',
              prefixIcon: Icons.title_rounded,
            ),
            const SizedBox(height: 16),

            // Content Type Selector
            Text(
              'Content Type',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: isDark ? AppColors.textDarkSecondary : AppColors.textLightPrimary,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF1E293B) : Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                ),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  isExpanded: true,
                  value: _contentType,
                  dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                  items: _types.map((t) {
                    return DropdownMenuItem<String>(
                      value: t,
                      child: Text(t.toUpperCase()),
                    );
                  }).toList(),
                  onChanged: (val) => setState(() => _contentType = val!),
                ),
              ),
            ),
            const SizedBox(height: 16),

            CustomTextField(
              controller: _urlController,
              label: 'Content URL / Video Link',
              hint: 'https://youtube.com/... or mp4 or pdf url',
              prefixIcon: Icons.link_rounded,
            ),
            const SizedBox(height: 16),

            CustomTextField(
              controller: _durationController,
              label: 'Estimated Duration',
              hint: '15 mins',
              prefixIcon: Icons.timer_outlined,
            ),
            const SizedBox(height: 16),

            CustomTextField(
              controller: _descController,
              label: 'Lecture Notes / Summary',
              hint: 'Key takeaways and code references...',
              maxLines: 3,
            ),
            const SizedBox(height: 16),

            // Preview switch
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Free Preview Lecture', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                      Text('Allow students to watch without enrolling', style: TextStyle(fontSize: 12, color: Colors.grey)),
                    ],
                  ),
                  Switch(
                    value: _isPreview,
                    activeColor: AppColors.primaryLight,
                    onChanged: (val) => setState(() => _isPreview = val),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 28),

            ModernButton(
              text: 'Save & Attach Lecture',
              icon: Icons.check_circle_rounded,
              isLoading: _isLoading,
              onPressed: _submitLesson,
            ),
          ],
        ),
      ),
    );
  }
}
