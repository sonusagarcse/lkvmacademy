import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../models/course_model.dart';
import '../../services/api_service.dart';
import '../../widgets/modern_button.dart';
import '../../widgets/custom_text_field.dart';
import 'classroom_screen.dart';

class CheckoutScreen extends StatefulWidget {
  final CourseModel course;
  const CheckoutScreen({super.key, required this.course});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String _selectedMethod = 'upi'; // 'upi', 'card', 'razorpay', 'free'
  final _utrController = TextEditingController();
  final _cardNumberCtrl = TextEditingController(text: '4532 •••• •••• 8821');
  final _expiryCtrl = TextEditingController(text: '12/28');
  final _cvvCtrl = TextEditingController(text: '786');

  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    if (widget.course.isFree || widget.course.effectivePrice <= 0) {
      _selectedMethod = 'free';
    }
  }

  @override
  void dispose() {
    _utrController.dispose();
    _cardNumberCtrl.dispose();
    _expiryCtrl.dispose();
    _cvvCtrl.dispose();
    super.dispose();
  }

  Future<void> _processPayment() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    String? payId;
    if (_selectedMethod == 'upi') {
      final utr = _utrController.text.trim();
      if (utr.isEmpty || utr.length < 6) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Please enter a valid 12-digit UPI UTR / Reference Number.';
        });
        return;
      }
      payId = 'UPI_$utr';
    } else if (_selectedMethod == 'card') {
      payId = 'CARD_SIM_${DateTime.now().millisecondsSinceEpoch}';
    } else if (_selectedMethod == 'razorpay') {
      payId = 'pay_${DateTime.now().millisecondsSinceEpoch}';
    }

    final res = await ApiService().enrollCourse(
      courseId: widget.course.id,
      paymentMethod: _selectedMethod,
      paymentId: payId,
    );

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (res['status'] == true) {
      _showSuccessDialog(res['data']);
    } else {
      setState(() {
        _errorMessage = res['message'] ?? 'Payment failed. Please try again.';
      });
    }
  }

  void _showSuccessDialog(dynamic data) {
    showModalBottomSheet(
      context: context,
      isDismissible: false,
      enableDrag: false,
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
                width: 70,
                height: 70,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(colors: [Color(0xFF10B981), Color(0xFF059669)]),
                ),
                child: const Icon(Icons.check_rounded, color: Colors.white, size: 42),
              ),
              const SizedBox(height: 20),
              const Text(
                'Enrollment Confirmed! 🎉',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              Text(
                'You have successfully enrolled in "${widget.course.title}". You now have full lifetime access to all lectures and certificate generation.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 14, color: Colors.grey),
              ),
              const SizedBox(height: 24),
              ModernButton(
                text: 'Launch Classroom Now',
                icon: Icons.play_arrow_rounded,
                onPressed: () {
                  Navigator.of(context).pop(); // close sheet
                  Navigator.of(context).pop(true); // close checkout with success
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ClassroomScreen(
                        course: widget.course,
                        initialLessonIndex: 0,
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

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final c = widget.course;
    final isFree = c.isFree || c.effectivePrice <= 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout & Enrollment'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Course Summary Card
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF1E293B) : Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                ),
              ),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(14),
                    child: Container(
                      width: 80,
                      height: 80,
                      color: AppColors.primary.withOpacity(0.12),
                      child: const Center(
                        child: Icon(Icons.school, size: 36, color: AppColors.primaryLight),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          c.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Instructor: ${c.instructor}',
                          style: const TextStyle(fontSize: 12, color: Colors.grey),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          isFree ? 'FREE ACCESS' : '₹${c.effectivePrice.toStringAsFixed(2)}',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: isFree ? AppColors.success : AppColors.primaryLight,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

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

            // Payment Options
            const Text(
              'Select Payment Option',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 12),

            if (isFree) ...[
              _buildMethodTile(
                title: 'Free Student Enrollment',
                subtitle: 'Zero cost • Instant full course access',
                icon: Icons.card_giftcard_rounded,
                value: 'free',
              ),
            ] else ...[
              _buildMethodTile(
                title: 'UPI Instant QR / UTR',
                subtitle: 'GooglePay, PhonePe, Paytm, BHIM UPI',
                icon: Icons.qr_code_2_rounded,
                value: 'upi',
              ),
              if (_selectedMethod == 'upi') _buildUpiSection(isDark),
              const SizedBox(height: 10),
              _buildMethodTile(
                title: 'Razorpay Online Gateway',
                subtitle: 'Net Banking, Wallets & Cards',
                icon: Icons.bolt_rounded,
                value: 'razorpay',
              ),
              const SizedBox(height: 10),
              _buildMethodTile(
                title: 'Credit / Debit Card',
                subtitle: 'Visa, MasterCard, RuPay',
                icon: Icons.credit_card_rounded,
                value: 'card',
              ),
              if (_selectedMethod == 'card') _buildCardSection(isDark),
            ],

            const SizedBox(height: 24),

            // Order Breakdown Card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(
                  color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0),
                ),
              ),
              child: Column(
                children: [
                  _buildPriceRow('Course Fee', '₹${c.price.toStringAsFixed(2)}'),
                  if (c.discountPrice != null && c.discountPrice! < c.price) ...[
                    const SizedBox(height: 8),
                    _buildPriceRow(
                      'Special Discount',
                      '- ₹${(c.price - c.discountPrice!).toStringAsFixed(2)}',
                      textColor: AppColors.success,
                    ),
                  ],
                  const SizedBox(height: 8),
                  _buildPriceRow('Taxes & Gateway Fees', '₹0.00'),
                  const Divider(height: 24),
                  _buildPriceRow(
                    'Total Payable',
                    isFree ? '₹0.00 (FREE)' : '₹${c.effectivePrice.toStringAsFixed(2)}',
                    isBold: true,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 28),

            ModernButton(
              text: isFree
                  ? 'Confirm Free Enrollment'
                  : 'Pay ₹${c.effectivePrice.toStringAsFixed(0)} & Enroll',
              icon: Icons.lock_outline_rounded,
              isLoading: _isLoading,
              onPressed: _processPayment,
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildMethodTile({
    required String title,
    required String subtitle,
    required IconData icon,
    required String value,
  }) {
    final isSelected = _selectedMethod == value;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return GestureDetector(
      onTap: () => setState(() => _selectedMethod = value),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E293B) : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.primaryLight : (isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0)),
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: isSelected ? AppColors.primary.withOpacity(0.15) : Colors.grey.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: isSelected ? AppColors.primaryLight : Colors.grey),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 2),
                  Text(subtitle, style: const TextStyle(fontSize: 12, color: Colors.grey)),
                ],
              ),
            ),
            Radio<String>(
              value: value,
              groupValue: _selectedMethod,
              activeColor: AppColors.primaryLight,
              onChanged: (val) => setState(() => _selectedMethod = val!),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildUpiSection(bool isDark) {
    return Container(
      margin: const EdgeInsets.only(top: 8, bottom: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF161F30) : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          const Text(
            'Scan QR code with any UPI app to pay',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.black12),
            ),
            child: Image.network(
              'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=upi://pay?pa=lkvm@upi&pn=LKVM%20Academy&am=${widget.course.effectivePrice}&cu=INR',
              width: 150,
              height: 150,
              errorBuilder: (_, __, ___) => const Icon(Icons.qr_code_2, size: 100),
            ),
          ),
          const SizedBox(height: 10),
          const Text(
            'UPI ID: lkvm@upi',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
          ),
          const SizedBox(height: 16),
          CustomTextField(
            controller: _utrController,
            label: '12-Digit UTR / Reference Number',
            hint: 'e.g. 329182749102',
            prefixIcon: Icons.receipt_long_rounded,
          ),
        ],
      ),
    );
  }

  Widget _buildCardSection(bool isDark) {
    return Container(
      margin: const EdgeInsets.only(top: 8, bottom: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF161F30) : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          CustomTextField(
            controller: _cardNumberCtrl,
            label: 'Card Number',
            prefixIcon: Icons.credit_card,
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: CustomTextField(
                  controller: _expiryCtrl,
                  label: 'Expiry',
                  hint: 'MM/YY',
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: CustomTextField(
                  controller: _cvvCtrl,
                  label: 'CVV',
                  hint: '•••',
                  obscureText: true,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPriceRow(String label, String value, {bool isBold = false, Color? textColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: isBold ? 15 : 13,
            fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: isBold ? 16 : 13,
            fontWeight: isBold ? FontWeight.w800 : FontWeight.w600,
            color: textColor ?? (isBold ? AppColors.primaryLight : null),
          ),
        ),
      ],
    );
  }
}
