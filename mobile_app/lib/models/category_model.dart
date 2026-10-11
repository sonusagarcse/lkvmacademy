class CategoryModel {
  final int id;
  final String name;
  final String slug;
  final String icon;
  final String description;
  final int courseCount;

  CategoryModel({
    required this.id,
    required this.name,
    required this.slug,
    required this.icon,
    required this.description,
    required this.courseCount,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: int.tryParse(json['id'].toString()) ?? 0,
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      icon: json['icon'] ?? 'fa-laptop-code',
      description: json['description'] ?? '',
      courseCount: int.tryParse(json['course_count'].toString()) ?? 0,
    );
  }
}
