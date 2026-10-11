<?php
require_once dirname(__DIR__) . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

$msg = "";
$msg_type = "success";

// Handle Saving General Settings
if (isset($_POST['save_settings'])) {
    $keys = [
        'site_name',
        'site_tagline',
        'contact_email',
        'contact_phone',
        'currency_symbol',
        'currency_code',
        'razorpay_key_id',
        'razorpay_key_secret',
        'upi_id',
        'upi_name'
    ];

    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            lms_set_setting($con, $key, trim($_POST[$key]));
        }
    }
    $msg = "Settings updated successfully!";
}

// Handle Password Change
if (isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $admin_id = $_SESSION['lms_admin_id'] ?? 1;
    $res = mysqli_query($con, "SELECT password FROM lms_admins WHERE id = $admin_id LIMIT 1");
    if ($res && $admin_data = mysqli_fetch_assoc($res)) {
        if (!password_verify($current_pass, $admin_data['password'])) {
            $msg = "Error: Current password is incorrect!";
            $msg_type = "danger";
        } elseif (strlen($new_pass) < 6) {
            $msg = "Error: New password must be at least 6 characters long.";
            $msg_type = "danger";
        } elseif ($new_pass !== $confirm_pass) {
            $msg = "Error: New password and confirmation do not match.";
            $msg_type = "danger";
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            mysqli_query($con, "UPDATE lms_admins SET password = '$new_hash' WHERE id = $admin_id");
            $msg = "Admin password changed successfully!";
        }
    }
}

// Load current settings
$site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');
$site_tagline = lms_get_setting($con, 'site_tagline', 'Master Skills of the Future');
$contact_email = lms_get_setting($con, 'contact_email', 'info@lkvm.org');
$contact_phone = lms_get_setting($con, 'contact_phone', '+91 9801664500');
$currency_symbol = lms_get_setting($con, 'currency_symbol', '₹');
$currency_code = lms_get_setting($con, 'currency_code', 'INR');
$razorpay_key_id = lms_get_setting($con, 'razorpay_key_id', '');
$razorpay_key_secret = lms_get_setting($con, 'razorpay_key_secret', '');
$upi_id = lms_get_setting($con, 'upi_id', 'lkvm@upi');
$upi_name = lms_get_setting($con, 'upi_name', 'LKVM Computer Education');

$page_title = 'LMS Settings & Payments';
include 'includes/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-info-circle me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- General & Payment Settings -->
    <div class="col-lg-8">
        <div class="admin-card p-4 mb-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4">
                    <i class="fa fa-sliders fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-800 mb-0">Academy Information</h5>
                    <small class="text-muted">General branding and contact details for the course platform</small>
                </div>
            </div>

            <form method="POST">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Academy / LMS Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($site_name); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Tagline</label>
                        <input type="text" name="site_tagline" class="form-control" value="<?php echo htmlspecialchars($site_tagline); ?>">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Support Email</label>
                        <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($contact_email); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Support Phone / WhatsApp</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($contact_phone); ?>" required>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Payment Configuration -->
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-4">
                        <i class="fa fa-wallet fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-800 mb-0">Payment Gateways & UPI</h5>
                        <small class="text-muted">Configure Razorpay or Direct UPI QR payments for course purchases</small>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Currency Symbol</label>
                        <input type="text" name="currency_symbol" class="form-control" value="<?php echo htmlspecialchars($currency_symbol); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Currency Code</label>
                        <input type="text" name="currency_code" class="form-control" value="<?php echo htmlspecialchars($currency_code); ?>" required>
                    </div>
                </div>

                <div class="bg-light p-3 rounded-3 mb-3">
                    <h6 class="fw-bold mb-2"><i class="fa fa-credit-card text-primary me-2"></i>Razorpay Gateway (Online Cards/NetBanking/UPI)</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Razorpay Key ID</label>
                            <input type="text" name="razorpay_key_id" class="form-control" placeholder="rzp_live_... or rzp_test_..." value="<?php echo htmlspecialchars($razorpay_key_id); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Razorpay Key Secret</label>
                            <input type="password" name="razorpay_key_secret" class="form-control" placeholder="Key Secret" value="<?php echo htmlspecialchars($razorpay_key_secret); ?>">
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">Optional: Leave blank to offer direct UPI QR code payment exclusively.</small>
                </div>

                <div class="bg-light p-3 rounded-3 mb-4">
                    <h6 class="fw-bold mb-2"><i class="fa fa-qrcode text-success me-2"></i>Direct UPI & QR Code Settings</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Academy UPI ID / VPA</label>
                            <input type="text" name="upi_id" class="form-control" placeholder="e.g. lkvm@icici" value="<?php echo htmlspecialchars($upi_id); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Payee / Account Name</label>
                            <input type="text" name="upi_name" class="form-control" placeholder="e.g. LKVM Computer Academy" value="<?php echo htmlspecialchars($upi_name); ?>">
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">Shown on checkout for students to scan with Google Pay, PhonePe, or Paytm.</small>
                </div>

                <button type="submit" name="save_settings" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow-sm">
                    <i class="fa fa-save me-2"></i> Save Academy Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Password Management -->
    <div class="col-lg-4">
        <div class="admin-card p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="bg-dark bg-opacity-10 text-dark p-3 rounded-4">
                    <i class="fa fa-shield-halved fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-800 mb-0">Admin Security</h5>
                    <small class="text-muted">Update your portal password</small>
                </div>
            </div>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required placeholder="••••••••">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">New Password</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Minimum 6 characters">
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-uppercase text-muted">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                </div>

                <button type="submit" name="change_password" class="btn btn-dark w-100 py-3 rounded-pill fw-bold">
                    <i class="fa fa-key me-2"></i> Update Password
                </button>
            </form>

            <div class="border-top mt-4 pt-3 text-muted small">
                <i class="fa fa-database me-1 text-primary"></i> LMS Database: <strong>lkvm_courses</strong><br>
                <i class="fa fa-user me-1 text-primary"></i> Current Admin: <strong><?php echo htmlspecialchars($_SESSION['lms_admin_username'] ?? 'admin'); ?></strong>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
