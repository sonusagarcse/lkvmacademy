<?php
/**
 * LKVM LMS - My Certificates Page
 * Students view all their earned certificates, download, and share them.
 */
$page_title = 'My Certificates';
include 'includes/header.php';
require_once __DIR__ . '/includes/certificate_helper.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login?redirect=" . urlencode("certificates"));
    exit();
}

$user_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';

// Ensure certificates table
lms_ensure_certificates_table($con);

// Get all certificates for this student
$certificates = lms_get_student_certificates($con, $user_id);
$cert_count = count($certificates);

// Also check enrolled courses that are 100% complete but don't have certificates yet (auto-issue)
$enrolled_q = mysqli_query($con, "
    SELECT c.id, c.title, c.slug 
    FROM lms_enrollments e 
    JOIN lms_courses c ON e.course_id = c.id 
    WHERE e.user_id = $user_id AND e.status = 'completed'
");
while ($enrolled = mysqli_fetch_assoc($enrolled_q)) {
    $existing = lms_get_certificate($con, $user_id, $enrolled['id']);
    if (!$existing) {
        $cert = lms_try_auto_issue_certificate($con, $user_id, $enrolled['id']);
        if ($cert) {
            // Refresh the list
            $certificates = lms_get_student_certificates($con, $user_id);
            $cert_count = count($certificates);
        }
    }
}
?>

<style>
    .cert-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        color: #fff;
        padding: 60px 0 50px;
        position: relative;
        overflow: hidden;
    }
    .cert-hero::before {
        content: '';
        position: absolute;
        top: -40%; right: -20%;
        width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
        border-radius: 50%;
    }
    .cert-hero::after {
        content: '';
        position: absolute;
        bottom: -30%; left: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(14,165,233,0.12) 0%, transparent 70%);
        border-radius: 50%;
    }
    
    .cert-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .cert-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        border-color: #cbd5e1;
    }
    
    .cert-card-preview {
        background: linear-gradient(135deg, #fefce8 0%, #fffbeb 50%, #fef3c7 100%);
        border-bottom: 2px solid #f59e0b;
        padding: 32px 24px;
        position: relative;
        text-align: center;
    }
    .cert-card-preview::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23f59e0b' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        opacity: 0.8;
    }
    
    .cert-ornament {
        width: 64px; height: 64px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 12px;
        box-shadow: 0 4px 15px rgba(245,158,11,0.3);
        position: relative;
        z-index: 1;
    }
    .cert-ornament i { color: #fff; font-size: 1.6rem; }
    
    .cert-uid-badge {
        display: inline-block;
        background: rgba(15,23,42,0.06);
        border: 1px dashed #94a3b8;
        border-radius: 8px;
        padding: 4px 14px;
        font-family: 'Courier New', monospace;
        font-weight: 700;
        font-size: 0.82rem;
        color: #475569;
        letter-spacing: 1px;
    }
    
    .cert-status-active {
        background: #dcfce7;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }
    .cert-status-revoked {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    
    .cert-action-btn {
        border: none;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 700;
        font-size: 0.88rem;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .cert-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .cert-btn-view {
        background: var(--primary);
        color: #fff;
    }
    .cert-btn-view:hover { background: var(--primary-dark); color: #fff; }
    .cert-btn-download {
        background: #f1f5f9;
        color: #475569;
    }
    .cert-btn-download:hover { background: #e2e8f0; color: #1e293b; }
    .cert-btn-share {
        background: #f0fdf4;
        color: #16a34a;
    }
    .cert-btn-share:hover { background: #dcfce7; color: #15803d; }
    
    .empty-cert-state {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 24px;
        border: 2px dashed #cbd5e1;
        padding: 80px 40px;
        text-align: center;
    }
    .empty-cert-icon {
        width: 100px; height: 100px;
        background: linear-gradient(135deg, #eef2ff, #e0e7ff);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 24px;
    }
    .empty-cert-icon i {
        font-size: 2.4rem;
        color: var(--primary);
    }
</style>

<!-- Hero Section -->
<div class="cert-hero">
    <div class="container position-relative" style="z-index: 1;">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
            <div>
                <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-2 fw-bold small mb-3 d-inline-block border border-white border-opacity-10">
                    <i class="fa fa-award me-1 text-warning"></i> ACHIEVEMENT GALLERY
                </span>
                <h1 class="fw-800 mb-2" style="font-size: 2rem;">My Certificates</h1>
                <p class="text-white-50 mb-0" style="max-width: 500px;">
                    Your earned certificates from completed courses. Each certificate has a unique verification ID.
                </p>
            </div>
            <div class="d-flex gap-3">
                <div class="bg-white bg-opacity-10 backdrop-blur p-3 rounded-4 text-center border border-white border-opacity-10" style="min-width: 110px;">
                    <div class="fs-3 fw-800 text-warning"><?php echo $cert_count; ?></div>
                    <small class="text-white-50 fw-semibold">Certificates</small>
                </div>
                <a href="dashboard" class="btn btn-outline-light rounded-pill px-4 fw-bold d-flex align-items-center gap-2 align-self-center">
                    <i class="fa fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<div class="py-5 bg-light min-vh-100">
    <div class="container">
        <?php if ($cert_count > 0): ?>
            <div class="row g-4">
                <?php foreach ($certificates as $cert): 
                    $is_active = ($cert['status'] === 'active');
                    $issue_formatted = date('d M Y', strtotime($cert['issue_date']));
                    $thumb = $cert['course_thumbnail'] ?? '';
                    if (empty($thumb)) {
                        $thumb = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=600';
                    }
                ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="cert-card h-100 d-flex flex-column">
                        <!-- Certificate Preview -->
                        <div class="cert-card-preview">
                            <div class="cert-ornament">
                                <i class="fa fa-award"></i>
                            </div>
                            <div class="fw-800 text-dark mb-1" style="font-size: 1.1rem; position: relative; z-index: 1;">
                                Certificate of Completion
                            </div>
                            <div class="text-muted small fw-semibold position-relative" style="z-index: 1;">
                                <?php echo htmlspecialchars($cert['course_title']); ?>
                            </div>
                            <div class="mt-3 position-relative" style="z-index: 1;">
                                <span class="cert-uid-badge"><?php echo htmlspecialchars($cert['certificate_uid']); ?></span>
                            </div>
                        </div>
                        
                        <!-- Certificate Details -->
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge rounded-pill px-3 py-2 fw-bold small <?php echo $is_active ? 'cert-status-active' : 'cert-status-revoked'; ?>">
                                    <i class="fa <?php echo $is_active ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                    <?php echo $is_active ? 'VERIFIED' : 'REVOKED'; ?>
                                </span>
                                <span class="text-muted small fw-semibold">
                                    <i class="fa fa-calendar-alt me-1"></i> <?php echo $issue_formatted; ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="small text-muted fw-semibold mb-1">Awarded to</div>
                                <div class="fw-800 text-dark"><?php echo htmlspecialchars($cert['student_name']); ?></div>
                            </div>
                            
                            <?php if (!empty($cert['instructor_name'])): ?>
                            <div class="mb-3">
                                <div class="small text-muted fw-semibold mb-1">Instructor</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($cert['instructor_name']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex flex-wrap gap-2 mt-auto pt-3 border-top">
                                <?php if ($is_active): ?>
                                    <a href="certificate-view?uid=<?php echo urlencode($cert['certificate_uid']); ?>" class="cert-action-btn cert-btn-view flex-fill justify-content-center">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                    <a href="certificate-view?uid=<?php echo urlencode($cert['certificate_uid']); ?>&download=1" class="cert-action-btn cert-btn-download flex-fill justify-content-center">
                                        <i class="fa fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span class="cert-action-btn cert-btn-download flex-fill justify-content-center opacity-50" style="cursor: not-allowed;">
                                        <i class="fa fa-ban"></i> Certificate Revoked
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Verification Info -->
            <div class="mt-5 bg-white rounded-4 border p-4 p-md-5 shadow-sm">
                <div class="d-flex align-items-start gap-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px;">
                        <i class="fa fa-shield-halved fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-800 text-dark mb-2">Certificate Verification</h5>
                        <p class="text-muted mb-3">
                            All LKVM Academy certificates can be independently verified by employers and institutions using the unique Certificate ID. 
                            Share your certificate link or ask the verifier to visit our verification portal.
                        </p>
                        <a href="certificate-verify" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                            <i class="fa fa-search me-2"></i> Open Verification Portal
                        </a>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-cert-state">
                <div class="empty-cert-icon">
                    <i class="fa fa-award"></i>
                </div>
                <h3 class="fw-800 text-dark mb-2">No Certificates Earned Yet</h3>
                <p class="text-muted mx-auto mb-4" style="max-width: 520px;">
                    Complete all lessons in any enrolled course to earn your official certificate of completion.
                    Each certificate comes with a unique verification ID.
                </p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="dashboard" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow">
                        <i class="fa fa-graduation-cap me-2"></i> Continue Learning
                    </a>
                    <a href="./" class="btn btn-outline-primary rounded-pill px-5 py-3 fw-bold">
                        Browse Courses
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
