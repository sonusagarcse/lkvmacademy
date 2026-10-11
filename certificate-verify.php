<?php
/**
 * LKVM LMS - Public Certificate Verification Portal
 * Allows anyone (employers, institutions) to verify a certificate by its unique ID.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/certificate_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

lms_ensure_certificates_table($con);

$site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');
$search_uid = strtoupper(trim($_GET['uid'] ?? ($_POST['uid'] ?? '')));
$cert = null;
$searched = false;

if (!empty($search_uid)) {
    $searched = true;
    $cert = lms_get_certificate_by_uid($con, $search_uid);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - <?php echo htmlspecialchars($site_name); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0056D2;
            --primary-dark: #00419e;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .verify-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 80px 0 100px;
            position: relative;
            overflow: hidden;
        }
        .verify-hero::before {
            content: '';
            position: absolute;
            top: -50%; right: -20%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 70%);
            border-radius: 50%;
        }
        .verify-hero::after {
            content: '';
            position: absolute;
            bottom: -40%; left: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(14,165,233,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .verify-search-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            padding: 40px;
            margin-top: -60px;
            position: relative;
            z-index: 5;
            border: 1px solid #e2e8f0;
        }
        
        .verify-input-group {
            display: flex;
            gap: 12px;
            max-width: 600px;
            margin: 0 auto;
        }
        .verify-input-group input {
            flex: 1;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            font-size: 1.05rem;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            text-transform: uppercase;
            outline: none;
            transition: all 0.3s ease;
        }
        .verify-input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0,86,210,0.1);
        }
        .verify-input-group input::placeholder {
            font-family: 'Plus Jakarta Sans', sans-serif;
            letter-spacing: 0;
            text-transform: none;
            color: #94a3b8;
            font-weight: 500;
        }
        .verify-input-group button {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 16px 32px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .verify-input-group button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,86,210,0.3);
        }
        
        /* Result Cards */
        .result-card {
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }
        .result-valid-header {
            background: linear-gradient(135deg, #059669, #10b981);
            padding: 24px 32px;
            color: #fff;
        }
        .result-invalid-header {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            padding: 24px 32px;
            color: #fff;
        }
        .result-revoked-header {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            padding: 24px 32px;
            color: #fff;
        }
        .result-body {
            background: #fff;
            padding: 32px;
        }
        .detail-row {
            display: flex;
            border-bottom: 1px solid #f1f5f9;
            padding: 14px 0;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            width: 180px;
            font-weight: 700;
            color: #64748b;
            font-size: 0.88rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        .detail-value {
            color: #1e293b;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .trust-badges {
            display: flex;
            gap: 24px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .trust-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.88rem;
        }
        .trust-badge i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        @media (max-width: 576px) {
            .verify-input-group { flex-direction: column; }
            .verify-search-card { padding: 24px; }
            .detail-row { flex-direction: column; gap: 4px; }
            .detail-label { width: auto; }
        }
    </style>
</head>
<body>

<!-- Hero -->
<div class="verify-hero text-center text-white">
    <div class="container position-relative" style="z-index: 2;">
        <div class="d-inline-flex align-items-center gap-2 mb-3">
            <span class="bg-white bg-opacity-10 rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                <i class="fa fa-graduation-cap text-warning fs-5"></i>
            </span>
            <span class="fw-800 fs-5"><?php echo htmlspecialchars($site_name); ?></span>
        </div>
        <h1 class="fw-800 mb-3" style="font-size: 2.2rem;">Certificate Verification Portal</h1>
        <p class="text-white-50 mx-auto mb-0" style="max-width: 550px;">
            Verify the authenticity of any certificate issued by <?php echo htmlspecialchars($site_name); ?>. Enter the Certificate ID printed on the certificate.
        </p>
    </div>
</div>

<div class="container" style="max-width: 800px;">
    <!-- Search Card -->
    <div class="verify-search-card mb-5">
        <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                <i class="fa fa-shield-halved fs-3 text-primary"></i>
            </div>
            <h4 class="fw-800 mb-1">Verify a Certificate</h4>
            <p class="text-muted small">Enter the unique Certificate ID (e.g., LKVM-202610-A7K3F)</p>
        </div>
        
        <form method="GET" action="">
            <div class="verify-input-group">
                <input type="text" name="uid" placeholder="Enter Certificate ID..." value="<?php echo htmlspecialchars($search_uid); ?>" required autocomplete="off">
                <button type="submit"><i class="fa fa-search me-2"></i> Verify</button>
            </div>
        </form>
    </div>
    
    <!-- Results -->
    <?php if ($searched): ?>
        <?php if ($cert): ?>
            <?php 
                $is_active = ($cert['status'] === 'active');
                $is_revoked = ($cert['status'] === 'revoked');
            ?>
            <div class="result-card mb-5">
                <div class="<?php echo $is_active ? 'result-valid-header' : ($is_revoked ? 'result-revoked-header' : 'result-invalid-header'); ?>">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa <?php echo $is_active ? 'fa-circle-check' : ($is_revoked ? 'fa-exclamation-triangle' : 'fa-times-circle'); ?> fs-3"></i>
                        </div>
                        <div>
                            <h4 class="fw-800 mb-0">
                                <?php if ($is_active): ?>
                                    Certificate Verified ✓
                                <?php elseif ($is_revoked): ?>
                                    Certificate Revoked
                                <?php else: ?>
                                    Certificate Expired
                                <?php endif; ?>
                            </h4>
                            <div class="opacity-75 small fw-semibold">
                                <?php if ($is_active): ?>
                                    This certificate is authentic and valid.
                                <?php elseif ($is_revoked): ?>
                                    This certificate has been revoked by the issuing authority.
                                <?php else: ?>
                                    This certificate has expired.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="result-body">
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-fingerprint me-2 text-primary"></i>Certificate ID</div>
                        <div class="detail-value" style="font-family: 'Courier New', monospace; letter-spacing: 1px;">
                            <?php echo htmlspecialchars($cert['certificate_uid']); ?>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-user me-2 text-primary"></i>Issued To</div>
                        <div class="detail-value"><?php echo htmlspecialchars($cert['student_name']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-book-open me-2 text-primary"></i>Course</div>
                        <div class="detail-value"><?php echo htmlspecialchars($cert['course_title']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-chalkboard-user me-2 text-primary"></i>Instructor</div>
                        <div class="detail-value"><?php echo htmlspecialchars($cert['instructor_name'] ?? 'LKVM Faculty'); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-calendar me-2 text-primary"></i>Date Issued</div>
                        <div class="detail-value"><?php echo date('d F Y', strtotime($cert['issue_date'])); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-percent me-2 text-primary"></i>Completion</div>
                        <div class="detail-value"><?php echo number_format($cert['completion_percent'], 0); ?>%</div>
                    </div>
                    <?php if ($is_active): ?>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-shield-halved me-2 text-success"></i>Status</div>
                        <div class="detail-value">
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold">
                                <i class="fa fa-check-circle me-1"></i> Active & Verified
                            </span>
                        </div>
                    </div>
                    <?php elseif ($is_revoked): ?>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-ban me-2 text-danger"></i>Status</div>
                        <div class="detail-value">
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 fw-bold">
                                <i class="fa fa-times-circle me-1"></i> Revoked
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($cert['revoked_reason'])): ?>
                    <div class="detail-row">
                        <div class="detail-label"><i class="fa fa-comment me-2 text-warning"></i>Reason</div>
                        <div class="detail-value text-danger"><?php echo htmlspecialchars($cert['revoked_reason']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Not Found -->
            <div class="result-card mb-5">
                <div class="result-invalid-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa fa-times-circle fs-3"></i>
                        </div>
                        <div>
                            <h4 class="fw-800 mb-0">Certificate Not Found</h4>
                            <div class="opacity-75 small fw-semibold">
                                No certificate matches the ID "<strong><?php echo htmlspecialchars($search_uid); ?></strong>"
                            </div>
                        </div>
                    </div>
                </div>
                <div class="result-body text-center py-5">
                    <p class="text-muted mb-0">
                        Please double-check the Certificate ID and try again. IDs are case-insensitive.<br>
                        If you believe this is an error, please contact <strong><?php echo htmlspecialchars($site_name); ?></strong>.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Trust Section -->
    <div class="text-center py-5">
        <div class="trust-badges mb-4">
            <div class="trust-badge">
                <i class="fa fa-shield-halved"></i> Tamper-Proof IDs
            </div>
            <div class="trust-badge">
                <i class="fa fa-database"></i> Centralized Records
            </div>
            <div class="trust-badge">
                <i class="fa fa-check-double"></i> Instant Verification
            </div>
        </div>
        <p class="text-muted small mb-3">
            All certificates are stored securely in our database and cannot be forged or tampered with.
        </p>
        <a href="./" class="text-primary fw-bold text-decoration-none">
            <i class="fa fa-arrow-left me-1"></i> Back to <?php echo htmlspecialchars($site_name); ?>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
