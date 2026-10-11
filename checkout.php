<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$course_id = intval($_GET['id'] ?? 0);

// Ensure user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login?redirect=" . urlencode("checkout?id=$course_id"));
    exit();
}

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Learner';

// Fetch course details
$stmt = $con->prepare("SELECT * FROM lms_courses WHERE id = ? AND status = 1 LIMIT 1");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header("Location: ./");
    exit();
}

// Check if already enrolled
$check = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $student_id AND course_id = $course_id AND status = 'completed'");
if (mysqli_num_rows($check) > 0) {
    header("Location: learn?slug=" . urlencode($course['slug']));
    exit();
}

// Handle FREE Course Enrollment
if ($course['is_free'] == 1 || $course['price'] <= 0) {
    $pay_id = "FREE_ENROLL_" . time() . '_' . rand(100, 999);
    $ins = $con->prepare("INSERT INTO lms_enrollments (user_id, course_id, amount, payment_id, payment_method, status) VALUES (?, ?, 0.00, ?, 'free', 'completed')");
    if ($ins) {
        $ins->bind_param("iis", $student_id, $course_id, $pay_id);
        $ins->execute();
        $ins->close();
    }
    header("Location: learn?slug=" . urlencode($course['slug']) . "&enrolled=success");
    exit();
}

// Handle Manual UPI UTR Submission
$upi_error = "";
if (isset($_POST['submit_upi_payment'])) {
    $utr = trim($_POST['utr_number'] ?? '');
    if (empty($utr) || strlen($utr) < 6) {
        $upi_error = "Please enter a valid 12-digit UPI Reference / UTR Number.";
    } else {
        $pay_id = "UPI_" . preg_replace('/[^A-Za-z0-9]/', '', $utr);
        $stmt_upi = $con->prepare("INSERT INTO lms_enrollments (user_id, course_id, amount, payment_id, payment_method, status) VALUES (?, ?, ?, ?, 'upi', 'completed')");
        if ($stmt_upi) {
            $stmt_upi->bind_param("iids", $student_id, $course_id, $course['price'], $pay_id);
            $stmt_upi->execute();
            $stmt_upi->close();
            header("Location: learn?slug=" . urlencode($course['slug']) . "&enrolled=success");
            exit();
        }
    }
}

// Payment Settings
$razorpay_key = lms_get_setting($con, 'razorpay_key_id', '');
$upi_id = lms_get_setting($con, 'upi_id', 'lkvm@upi');
$upi_name = lms_get_setting($con, 'upi_name', 'LKVM Academy');
$site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');

// Build UPI Link & QR Code
$upi_amount = number_format($course['price'], 2, '.', '');
$upi_note = urlencode("Course: " . substr($course['title'], 0, 25));
$upi_uri = "upi://pay?pa=" . urlencode($upi_id) . "&pn=" . urlencode($upi_name) . "&am=" . $upi_amount . "&cu=INR&tn=" . $upi_note;
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($upi_uri);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($course['title']); ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary: #6366f1; --dark: #0f172a; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; min-height: 100vh; padding: 40px 15px; }
        .checkout-box { background: #ffffff; border-radius: 28px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.04); border: 1px solid #e2e8f0; max-width: 800px; margin: 0 auto; overflow: hidden; }
        .tab-btn.active { background: #eef2ff !important; color: var(--primary) !important; border-color: var(--primary) !important; }
    </style>
</head>
<body>

<div class="checkout-box">
    <!-- Header -->
    <div class="p-4 p-md-5 border-bottom bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <a href="details?slug=<?php echo urlencode($course['slug']); ?>" class="text-decoration-none small text-muted mb-2 d-inline-block">
                <i class="fa fa-arrow-left me-1"></i> Back to Course
            </a>
            <h3 class="fw-800 mb-1 text-dark">Confirm Enrollment</h3>
            <p class="text-muted small mb-0">Enrolling as <strong><?php echo htmlspecialchars($student_name); ?></strong></p>
        </div>
        <div class="text-md-end">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                <i class="fa fa-lock me-1"></i> Secure 256-bit SSL
            </span>
        </div>
    </div>

    <div class="p-4 p-md-5">
        <div class="row g-4">
            <!-- Order Summary -->
            <div class="col-lg-6">
                <h5 class="fw-800 text-dark mb-3">Order Summary</h5>
                
                <div class="p-4 rounded-4 bg-light mb-4 border">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="<?php echo htmlspecialchars($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=150'); ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 60px; height: 60px;">
                        <div>
                            <div class="fw-800 text-dark" style="font-size: 0.95rem; line-height: 1.4;"><?php echo htmlspecialchars($course['title']); ?></div>
                            <small class="text-muted"><i class="fa fa-user me-1"></i><?php echo htmlspecialchars($course['instructor']); ?></small>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Course Fee</span>
                        <span class="fw-bold text-dark">₹<?php echo number_format($course['price'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>GST / Taxes (0%)</span>
                        <span class="fw-bold text-success">Included</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-800 text-dark">Total Payable</span>
                        <span class="fw-800 text-primary fs-4">₹<?php echo number_format($course['price'], 2); ?></span>
                    </div>
                </div>

                <div class="p-3 rounded-3 bg-white border small text-muted">
                    <div class="d-flex align-items-center gap-2 mb-1 text-dark fw-bold">
                        <i class="fa fa-circle-check text-success"></i> Instant Access Guaranteed
                    </div>
                    <span>Upon payment confirmation, all lectures and study materials are unlocked immediately in your student dashboard.</span>
                </div>
            </div>

            <!-- Payment Options -->
            <div class="col-lg-6">
                <h5 class="fw-800 text-dark mb-3">Choose Payment Mode</h5>

                <?php if (!empty($upi_error)): ?>
                    <div class="alert alert-danger rounded-4 py-2 px-3 small mb-3">
                        <?php echo htmlspecialchars($upi_error); ?>
                    </div>
                <?php endif; ?>

                <!-- Razorpay Gateway Option (if Key ID configured) -->
                <?php if (!empty($razorpay_key)): ?>
                    <div class="p-4 rounded-4 border bg-white mb-3 shadow-sm">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="fw-bold text-dark"><i class="fa fa-credit-card text-primary me-2"></i>Pay Online (Card / NetBanking)</span>
                            <span class="badge bg-light text-dark border">Razorpay</span>
                        </div>
                        <button id="rzp-button" class="btn btn-primary w-100 py-3 rounded-pill fw-800 shadow">
                            Pay ₹<?php echo number_format($course['price'], 0); ?> Online
                        </button>
                    </div>

                    <div class="text-center my-3 text-muted small fw-bold">-- OR PAY VIA UPI QR CODE --</div>
                <?php endif; ?>

                <!-- UPI QR Code Option -->
                <div class="p-4 rounded-4 border bg-white shadow-sm text-center">
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold small mb-2">
                        <i class="fa fa-qrcode me-1"></i> Instant Scan & Pay
                    </span>
                    <h6 class="fw-800 mb-3 text-dark">Scan QR with any UPI App</h6>

                    <div class="mb-3 d-inline-block p-2 bg-white rounded-3 border shadow-sm">
                        <img src="<?php echo $qr_code_url; ?>" alt="UPI QR Code" style="width: 170px; height: 170px;">
                    </div>

                    <div class="small text-muted mb-3">
                        UPI ID: <strong class="text-dark"><?php echo htmlspecialchars($upi_id); ?></strong><br>
                        Payee: <strong><?php echo htmlspecialchars($upi_name); ?></strong>
                    </div>

                    <!-- UTR Form -->
                    <form method="POST">
                        <div class="mb-3 text-start">
                            <label class="form-label small fw-bold text-muted text-uppercase">12-Digit UPI Transaction / UTR No *</label>
                            <input type="text" name="utr_number" class="form-control" placeholder="e.g. 412356789012" required pattern="[0-9A-Za-z]{6,30}">
                            <small class="text-muted">Found in your Google Pay, PhonePe, or Paytm receipt</small>
                        </div>

                        <button type="submit" name="submit_upi_payment" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow">
                            <i class="fa fa-check-circle me-1"></i> Verify & Unlock Course
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($razorpay_key)): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('rzp-button')?.addEventListener('click', function(e) {
    e.preventDefault();
    var options = {
        "key": "<?php echo $razorpay_key; ?>",
        "amount": "<?php echo round($course['price'] * 100); ?>",
        "currency": "INR",
        "name": "<?php echo htmlspecialchars($site_name); ?>",
        "description": "Enrollment for <?php echo htmlspecialchars($course['title']); ?>",
        "handler": function (response) {
            window.location.href = "process_payment.php?course_id=<?php echo $course_id; ?>&payment_id=" + response.razorpay_payment_id;
        },
        "prefill": {
            "name": "<?php echo htmlspecialchars($student_name); ?>"
        },
        "theme": {
            "color": "#6366f1"
        }
    };
    var rzp = new Razorpay(options);
    rzp.open();
});
</script>
<?php endif; ?>

</body>
</html>
