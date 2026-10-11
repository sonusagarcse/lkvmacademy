import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../models/category_model.dart';
import '../../services/api_service.dart';
import '../../widgets/modern_button.dart';
import '../../widgets/custom_text_field.dart';

class AddCourseScreen extends StatefulWidget {
  const AddCourseScreen({super.key});

  @override
  State<AddCourseScreen> createState() => _AddCourseScreenState();
}

class _AddCourseScreenState extends State<AddCourseScreen> {
  final _formKey = GlobalKey<FormState>();

  final _titleController = TextEditingController();
  final _instructorController = TextEditingController(text: 'Prof. Aryan Verma');
  final _durationController = TextEditingController(text: '12 Hours');
  final _priceController = TextEditingController(text: '1499');
  final _discountController = TextEditingController(text: '799');
  final _shortDescController = TextEditingController();
  final _longDescController = TextEditingController();
  final _thumbnailController = TextEditingController(
    text: 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=800',
  );
  final _previewVideoCtrl = TextEditingController(text: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  final _firstLessonTitleCtrl = TextEditingController(text: '1. Introduction & Setup');
  final _firstLessonUrlCtrl = TextEditingController(text: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

  List<CategoryModel> _categories = [];
  int? _selectedCategoryId;
  String _selectedLevel = 'All Levels';
  bool _isFree = false;
  bool _isLoading = false;
  String? _errorMessage;

  final List<String> _levels = ['All Levels', 'Beginner', 'Intermediate', 'Expert'];

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _instructorController.dispose();
    _durationController.dispose();
    _priceController.dispose();
    _discountController.dispose();
    _shortDescController.dispose();
    _longDescController.dispose();
    _thumbnailController.dispose();
    _previewVideoCtrl.dispose();
    _firstLessonTitleCtrl.dispose();
    _firstLessonUrlCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    final cats = await ApiService().getCategories();
    if (mounted) {
      setState(() {
        _categories = cats;
        if (cats.isNotEmpty) {
          _selectedCategoryId = cats.first.id;
        }
      });
    }
  }

  Future<void> _submitCourse() async {
    if (!_formKey.currentState!.validate()) return;

    final title = _titleController.text.trim();
    if (title.isEmpty) {
      setState(() => _errorMessage = 'Please enter a course title.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final payload = {
      'action': 'create',
      'title': title,
      'category_id': _selectedCategoryId ?? 1,
      'instructor': _instructorController.text.trim(),
      'level': _selectedLevel,
      'duration': _durationController.text.trim(),
      'is_free': _isFree ? 1 : 0,
      'price': _isFree ? 0.0 : (double.tryParse(_priceController.text) ?? 0.0),
      'discount_price': _isFree ? null : double.tryParse(_discountController.text),
      'short_desc': _shortDescController.text.trim().isNotEmpty
          ? _shortDescController.text.trim()
          : 'Master $title from beginner to advanced level.',
      'long_desc': _longDescController.text.trim().isNotEmpty
          ? _longDescController.text.trim()
          : 'Complete hands-on curriculum with real world projects and certificate.',
      'thumbnail': _thumbnailController.text.trim(),
      'preview_video': _previewVideoCtrl.text.trim(),
      'is_featured': 1,
      'initial_lesson_title': _firstLessonTitleCtrl.text.trim(),
      'initial_lesson_url': _firstLessonUrlCtrl.text.trim(),
      'initial_lesson_type': 'youtube',
    };

    final res = await ApiService().createCourse(payload);

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (res['status'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('🎉 Course created and published successfully!'),
          backgroundColor: AppColors.success,
        ),
      );
      Navigator.of(context).pop(true);
    } else {
      setState(() {
        _errorMessage = res['message'] ?? 'Failed to create course.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Add New Course'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Course Information',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              const Text(
                'Fill in the course curriculum details and pricing below.',
                style: TextStyle(color: Colors.grey, fontSize: 13),
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
                label: 'Course Title *',
                hint: 'e.g. Complete Flutter & Dart Masterclass 2026',
                prefixIcon: Icons.menu_book_rounded,
                validator: (val) => val == null || val.isEmpty ? 'Title is required' : null,
              ),
              const SizedBox(height: 16),

              // Category Selector
              Text(
                'Category',
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
                  child: DropdownButton<int>(
                    isExpanded: true,
                    value: _selectedCategoryId,
                    dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                    items: _categories.map((c) {
                      return DropdownMenuItem<int>(
                        value: c.id,
                        child: Text(c.name),
                      );
                    }).toList(),
                    onChanged: (val) => setState(() => _selectedCategoryId = val),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              Row(
                children: [
                  Expanded(
                    child: CustomTextField(
                      controller: _instructorController,
                      label: 'Instructor Name',
                      hint: 'Prof. Name',
                      prefixIcon: Icons.person_outline,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Level',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: isDark ? AppColors.textDarkSecondary : AppColors.textLightPrimary,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14),
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
                              value: _selectedLevel,
                              dropdownColor: isDark ? const Color(0xFF1E293B) : Colors.white,
                              items: _levels.map((lvl) {
                                return DropdownMenuItem<String>(
                                  value: lvl,
                                  child: Text(lvl, style: const TextStyle(fontSize: 13)),
                                );
                              }).toList(),
                              onChanged: (val) => setState(() => _selectedLevel = val!),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              CustomTextField(
                controller: _durationController,
                label: 'Duration',
                hint: 'e.g. 15 Hours or 4 Weeks',
                prefixIcon: Icons.timer_outlined,
              ),
              const SizedBox(height: 16),

              // Free Course Switch
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
                        Text('Free Course', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                        Text('Check if students can enroll for ₹0', style: TextStyle(fontSize: 12, color: Colors.grey)),
                      ],
                    ),
                    Switch(
                      value: _isFree,
                      activeColor: AppColors.success,
                      onChanged: (val) => setState(() => _isFree = val),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              if (!_isFree) ...[
                Row(
                  children: [
                    Expanded(
                      child: CustomTextField(
                        controller: _priceController,
                        label: 'Original Price (₹)',
                        hint: '999',
                        keyboardType: TextInputType.number,
                        prefixIcon: Icons.currency_rupee_rounded,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: CustomTextField(
                        controller: _discountController,
                        label: 'Discounted Price (₹)',
                        hint: '499',
                        keyboardType: TextInputType.number,
                        prefixIcon: Icons.local_offer_outlined,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
              ],

              CustomTextField(
                controller: _thumbnailController,
                label: 'Cover Thumbnail Image URL',
                hint: 'https://...',
                prefixIcon: Icons.image_outlined,
              ),
              const SizedBox(height: 16),

              CustomTextField(
                controller: _shortDescController,
                label: 'Short Overview',
                hint: 'A quick 1-2 sentence pitch of what students will master.',
                maxLines: 2,
              ),
              const SizedBox(height: 16),

              CustomTextField(
                controller: _longDescController,
                label: 'Detailed Curriculum & Syllabus',
                hint: 'Detailed requirements, modules, projects included in the course...',
                maxLines: 4,
              ),
              const SizedBox(height: 24),

              // Initial Lesson Block
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF161F30) : const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.video_collection_rounded, color: AppColors.primaryLight, size: 20),
                        SizedBox(width: 8),
                        Text(
                          'Add First Lecture (Previewable)',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    CustomTextField(
                      controller: _firstLessonTitleCtrl,
                      label: 'Lesson Title',
                      hint: 'Introduction to Course',
                    ),
                    const SizedBox(height: 10),
                    CustomTextField(
                      controller: _firstLessonUrlCtrl,
                      label: 'YouTube / Video Stream URL',
                      hint: 'https://youtube.com/watch?v=...',
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 32),

              ModernButton(
                text: 'Publish Course to Catalog',
                icon: Icons.rocket_launch_rounded,
                isLoading: _isLoading,
                onPressed: _submitCourse,
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    );
  }
}
