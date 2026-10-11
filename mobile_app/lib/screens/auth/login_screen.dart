import 'package:flutter/material.dart';
import '../../core/constants/app_colors.dart';
import '../../core/utils/auth_redirect.dart';
import '../../services/auth_service.dart';
import '../../widgets/modern_button.dart';
import '../../widgets/custom_text_field.dart';
import '../../widgets/otp_verification_dialog.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  final VoidCallback? onLoginSuccess;
  const LoginScreen({super.key, this.onLoginSuccess});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _adminUserCtrl = TextEditingController();
  final _adminPassCtrl = TextEditingController();

  bool _isAdminTab = false;
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _adminUserCtrl.dispose();
    _adminPassCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    Map<String, dynamic> res;
    if (_isAdminTab) {
      res = await AuthService().loginAdmin(
        _adminUserCtrl.text.trim(),
        _adminPassCtrl.text,
      );
    } else {
      res = await AuthService().loginStudent(
        _emailController.text.trim(),
        _passwordController.text,
      );
    }

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (res['status'] == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Login successful!'),
          backgroundColor: AppColors.success,
        ),
      );
      if (widget.onLoginSuccess != null) {
        widget.onLoginSuccess!();
      } else {
        await AuthRedirect.redirect(context);
      }
    } else {
      if (res['requires_otp'] == true) {
        final email = _emailController.text.trim();
        OtpVerificationDialog.show(
          context,
          email: email,
          onVerified: () async {
            if (widget.onLoginSuccess != null) {
              widget.onLoginSuccess!();
            } else {
              await AuthRedirect.redirect(context);
            }
          },
        );
        return;
      }

      setState(() {
        _errorMessage = res['message'] ?? 'Authentication failed.';
      });
    }
  }



  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Account Login'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Text(
                _isAdminTab ? 'Admin Portal' : 'Welcome Back',
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                _isAdminTab
                    ? 'Sign in with your administrative credentials to manage courses'
                    : 'Sign in to access your enrolled courses and digital certificates',
                style: TextStyle(
                  fontSize: 14,
                  color: isDark ? Colors.white60 : Colors.black54,
                ),
              ),
              const SizedBox(height: 24),

              // Role Selector Tabs
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF1E293B) : const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: GestureDetector(
                        onTap: () => setState(() {
                          _isAdminTab = false;
                          _errorMessage = null;
                        }),
                        child: Container(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          decoration: BoxDecoration(
                            color: !_isAdminTab ? AppColors.primary : Colors.transparent,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Center(
                            child: Text(
                              'Student / Learner',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: !_isAdminTab ? Colors.white : (isDark ? Colors.white70 : Colors.black87),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                    Expanded(
                      child: GestureDetector(
                        onTap: () => setState(() {
                          _isAdminTab = true;
                          _errorMessage = null;
                        }),
                        child: Container(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          decoration: BoxDecoration(
                            color: _isAdminTab ? AppColors.primary : Colors.transparent,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Center(
                            child: Text(
                              'Admin / Management',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: _isAdminTab ? Colors.white : (isDark ? Colors.white70 : Colors.black87),
                              ),
                            ),
                          ),
                        ),
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
                    color: AppColors.danger.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
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

              // Form fields
              if (!_isAdminTab) ...[
                CustomTextField(
                  controller: _emailController,
                  label: 'Email Address',
                  hint: 'Enter your student email',
                  prefixIcon: Icons.email_outlined,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 16),
                CustomTextField(
                  controller: _passwordController,
                  label: 'Password',
                  hint: 'Enter your password',
                  prefixIcon: Icons.lock_outline,
                  obscureText: true,
                ),
              ] else ...[
                CustomTextField(
                  controller: _adminUserCtrl,
                  label: 'Admin Username / Email',
                  hint: 'admin or admin@lkvm.org',
                  prefixIcon: Icons.admin_panel_settings_outlined,
                ),
                const SizedBox(height: 16),
                CustomTextField(
                  controller: _adminPassCtrl,
                  label: 'Admin Password',
                  hint: 'Enter admin password',
                  prefixIcon: Icons.lock_outline,
                  obscureText: true,
                ),
              ],

              const SizedBox(height: 28),
              ModernButton(
                text: _isAdminTab ? 'Sign In as Administrator' : 'Sign In as Student',
                isLoading: _isLoading,
                onPressed: _handleLogin,
              ),

              if (!_isAdminTab) const SizedBox(height: 24),

              // Register Link for Students
              if (!_isAdminTab)
                Center(
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        "Don't have an account? ",
                        style: TextStyle(color: isDark ? Colors.white60 : Colors.black54),
                      ),
                      GestureDetector(
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => RegisterScreen(
                                onRegisterSuccess: widget.onLoginSuccess,
                              ),
                            ),
                          );
                        },
                        child: const Text(
                          'Register now',
                          style: TextStyle(
                            color: AppColors.primaryLight,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
