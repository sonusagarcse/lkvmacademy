import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../services/api_service.dart';
import '../../widgets/modern_button.dart';
import '../../widgets/custom_text_field.dart';

class PaymentSettingsScreen extends StatefulWidget {
  const PaymentSettingsScreen({super.key});

  @override
  State<PaymentSettingsScreen> createState() => _PaymentSettingsScreenState();
}

class _PaymentSettingsScreenState extends State<PaymentSettingsScreen> {
  bool _enableUpi = true;
  bool _enableRazorpay = true;
  bool _enableCard = false;

  final _upiIdCtrl = TextEditingController(text: 'lkvm@upi');
  final _upiNameCtrl = TextEditingController(text: 'LKVM Computer Education');
  final _razorpayKeyCtrl = TextEditingController();

  bool _isLoading = true;
  bool _isSaving = false;
  String? _statusMsg;

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  @override
  void dispose() {
    _upiIdCtrl.dispose();
    _upiNameCtrl.dispose();
    _razorpayKeyCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadSettings() async {
    setState(() => _isLoading = true);
    final data = await ApiService().getPaymentSettings();
    if (mounted) {
      if (data != null && data['all_gateways'] is Map) {
        final all = data['all_gateways'];
        if (all['upi'] != null) {
          _enableUpi = all['upi']['enabled'] == true;
          _upiIdCtrl.text = all['upi']['upi_id'] ?? 'lkvm@upi';
          _upiNameCtrl.text = all['upi']['upi_name'] ?? 'LKVM Computer Education';
        }
        if (all['razorpay'] != null) {
          _enableRazorpay = all['razorpay']['enabled'] == true;
          _razorpayKeyCtrl.text = all['razorpay']['key_id'] ?? '';
        }
        if (all['card'] != null) {
          _enableCard = all['card']['enabled'] == true;
        }
      }
      setState(() => _isLoading = false);
    }
  }

  Future<void> _saveSettings() async {
    setState(() {
      _isSaving = true;
      _statusMsg = null;
    });

    final res = await ApiService().updatePaymentSettings({
      'enable_upi': _enableUpi ? 1 : 0,
      'enable_razorpay': _enableRazorpay ? 1 : 0,
      'enable_card': _enableCard ? 1 : 0,
      'upi_id': _upiIdCtrl.text.trim(),
      'upi_name': _upiNameCtrl.text.trim(),
      'razorpay_key_id': _razorpayKeyCtrl.text.trim(),
    });

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (res['status'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Payment gateway configuration saved successfully!'),
          backgroundColor: AppColors.success,
        ),
      );
      Navigator.of(context).pop(true);
    } else {
      setState(() {
        _statusMsg = res['message'] ?? 'Failed to update settings.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Payment Gateways Settings'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF0062FF)))
          : SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Active Checkout Gateways',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Enable or disable payment options below. Only enabled gateways will be visible to students on checkout.',
                    style: TextStyle(color: Colors.grey, fontSize: 13),
                  ),
                  const SizedBox(height: 20),

                  if (_statusMsg != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.danger.withOpacity(0.12),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.danger.withOpacity(0.3)),
                      ),
                      child: Text(
                        _statusMsg!,
                        style: const TextStyle(color: AppColors.danger, fontSize: 13),
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],

                  // 1. Direct UPI & QR Option
                  _buildGatewayCard(
                    isDark: isDark,
                    title: 'Direct UPI & QR Code',
                    subtitle: 'GPay, PhonePe, Paytm QR code with instant student UTR entry',
                    icon: Icons.qr_code_2_rounded,
                    accentColor: const Color(0xFF10B981),
                    isEnabled: _enableUpi,
                    onToggle: (val) => setState(() => _enableUpi = val),
                    children: [
                      CustomTextField(
                        controller: _upiIdCtrl,
                        label: 'UPI ID / VPA',
                        hint: 'e.g. lkvm@icici',
                        prefixIcon: Icons.account_balance_wallet_rounded,
                      ),
                      const SizedBox(height: 12),
                      CustomTextField(
                        controller: _upiNameCtrl,
                        label: 'Payee Account Name',
                        hint: 'e.g. LKVM Computer Education',
                        prefixIcon: Icons.business_rounded,
                      ),
                    ],
                  ),

                  const SizedBox(height: 16),

                  // 2. Razorpay Gateway Option
                  _buildGatewayCard(
                    isDark: isDark,
                    title: 'Razorpay Online Gateway',
                    subtitle: 'Cards, NetBanking, Wallets with automated verification',
                    icon: Icons.bolt_rounded,
                    accentColor: const Color(0xFF0062FF),
                    isEnabled: _enableRazorpay,
                    onToggle: (val) => setState(() => _enableRazorpay = val),
                    children: [
                      CustomTextField(
                        controller: _razorpayKeyCtrl,
                        label: 'Razorpay Key ID',
                        hint: 'rzp_live_... or rzp_test_...',
                        prefixIcon: Icons.key_rounded,
                      ),
                    ],
                  ),

                  const SizedBox(height: 16),

                  // 3. Credit / Debit Cards Option
                  _buildGatewayCard(
                    isDark: isDark,
                    title: 'Credit / Debit Card Checkout',
                    subtitle: 'Direct card checkout option (Visa, MasterCard, RuPay)',
                    icon: Icons.credit_card_rounded,
                    accentColor: const Color(0xFF06B6D4),
                    isEnabled: _enableCard,
                    onToggle: (val) => setState(() => _enableCard = val),
                    children: const [
                      Text(
                        'Direct card input form will be displayed on checkout when enabled.',
                        style: TextStyle(color: Colors.grey, fontSize: 12),
                      ),
                    ],
                  ),

                  const SizedBox(height: 28),

                  ModernButton(
                    text: 'Save Payment Gateway Settings',
                    icon: Icons.save_rounded,
                    isLoading: _isSaving,
                    onPressed: _saveSettings,
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
    );
  }

  Widget _buildGatewayCard({
    required bool isDark,
    required String title,
    required String subtitle,
    required IconData icon,
    required Color accentColor,
    required bool isEnabled,
    required ValueChanged<bool> onToggle,
    required List<Widget> children,
  }) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E293B) : Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isEnabled ? accentColor.withOpacity(0.5) : (isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0)),
          width: isEnabled ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(isDark ? 0.2 : 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: accentColor.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: accentColor, size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 2),
                    Text(subtitle, style: const TextStyle(fontSize: 11.5, color: Colors.grey)),
                  ],
                ),
              ),
              Switch(
                value: isEnabled,
                activeColor: accentColor,
                onChanged: onToggle,
              ),
            ],
          ),
          if (isEnabled && children.isNotEmpty) ...[
            const Divider(height: 24),
            ...children,
          ],
        ],
      ),
    );
  }
}
