<?php
/**
 * LKVM LMS - Certificate View & Download Page
 * Renders high-fidelity, printable certificates supporting custom uploaded background canvas,
 * student passport profile picture, dynamic fields, custom element resizing, and positioning.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/certificate_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

lms_ensure_certificates_table($con);

$uid = trim($_GET['uid'] ?? '');
$download_mode = isset($_GET['download']);

if (empty($uid)) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Invalid Certificate</h2><p><a href='dashboard?tab=certificates'>Back to My Certificates</a></p></div>");
}

$cert = lms_get_certificate_by_uid($con, $uid);
if (!$cert) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Certificate Not Found</h2><p>The certificate ID <strong>" . htmlspecialchars($uid) . "</strong> does not exist.</p><p><a href='dashboard?tab=certificates'>Back to My Certificates</a></p></div>");
}

$is_active = ($cert['status'] === 'active');
$issue_date = date('d F Y', strtotime($cert['issue_date']));
$student_name = htmlspecialchars($cert['student_name']);
$course_title = htmlspecialchars($cert['course_title']);
$instructor_name = htmlspecialchars($cert['instructor_name'] ?? 'LKVM Faculty');
$cert_uid = htmlspecialchars($cert['certificate_uid']);

$site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');
$cert_org_title = lms_get_setting($con, 'cert_org_title', $site_name);
$verify_url = $coursesBaseUrl . '/certificate-verify?uid=' . urlencode($cert['certificate_uid']);

// Global Settings & Assets
$signatory_title = lms_get_setting($con, 'cert_signatory_title', 'Director & Head of Academics');
$cert_custom_bg = lms_get_setting($con, 'cert_custom_bg');
$cert_logo_img = lms_get_setting($con, 'cert_logo_img');
$cert_signature_img = lms_get_setting($con, 'cert_signature_img');
$cert_seal_img = lms_get_setting($con, 'cert_seal_img');

// Fetch Student Details & Profile Picture
$student_photo_url = '';
$student_extra_data = [
    'email' => $cert['student_email'] ?? '',
    'phone' => '',
    'grade' => 'A+ (Distinction)',
    'duration' => '120 Hours',
    'father_name' => 'Not Specified'
];

if (!empty($cert['user_id'])) {
    $st_res = mysqli_query($con, "SELECT profile_pic, email, phone FROM lms_students WHERE id = " . intval($cert['user_id']) . " LIMIT 1");
    if ($st_res && $st_row = mysqli_fetch_assoc($st_res)) {
        if (!empty($st_row['profile_pic']) && file_exists(__DIR__ . '/' . ltrim($st_row['profile_pic'], '/'))) {
            $student_photo_url = $st_row['profile_pic'];
        }
        if (!empty($st_row['email'])) {
            $student_extra_data['email'] = $st_row['email'];
        }
        if (!empty($st_row['phone'])) {
            $student_extra_data['phone'] = $st_row['phone'];
        }
    }
}
if (empty($student_photo_url)) {
    $student_photo_url = "https://ui-avatars.com/api/?name=" . urlencode($cert['student_name']) . "&background=6366f1&color=fff&size=200&bold=true";
}

// Auto Calculate Aspect Ratio from Uploaded Background Image
$bg_aspect = "1000 / 707";
$bg_width = 1000;
$bg_height = 707;
if (!empty($cert_custom_bg)) {
    $abs_bg = __DIR__ . '/' . ltrim($cert_custom_bg, '/');
    if (file_exists($abs_bg)) {
        $size_info = @getimagesize($abs_bg);
        if ($size_info && $size_info[0] > 0 && $size_info[1] > 0) {
            $bg_width = $size_info[0];
            $bg_height = $size_info[1];
            $bg_aspect = "{$bg_width} / {$bg_height}";
        }
    }
}

// Load Visual Canvas Builder Config
$saved_cfg_raw = lms_get_setting($con, 'cert_canvas_config', '');
$cfg = !empty($saved_cfg_raw) ? json_decode($saved_cfg_raw, true) : [];

$c_org_title = !empty($cfg['org_title']) ? $cfg['org_title'] : $cert_org_title;
$c_org_font = $cfg['org_font'] ?? 'jakarta';
$c_org_size = $cfg['org_size'] ?? 18;
$c_org_color = $cfg['org_color'] ?? '#1e3a5f';
$c_show_logo = isset($cfg['show_logo']) ? (int)$cfg['show_logo'] : 1;
$c_logo_size = $cfg['logo_size'] ?? 42;
$show_org = isset($cfg['show_org']) ? (int)$cfg['show_org'] : 1;
$pos_org_top = $cfg['pos_org_top'] ?? 8;
$pos_org_left = $cfg['pos_org_left'] ?? 50;

$c_heading_text = !empty($cfg['heading_text']) ? $cfg['heading_text'] : 'Certificate';
$c_heading_sub = !empty($cfg['heading_sub']) ? $cfg['heading_sub'] : 'OF COMPLETION';
$c_heading_font = $cfg['heading_font'] ?? 'playfair';
$c_heading_size = $cfg['heading_size'] ?? 36;
$c_heading_color = $cfg['heading_color'] ?? '#1e3a5f';
$c_heading_sub_color = $cfg['heading_sub_color'] ?? '#64748b';
$show_heading = isset($cfg['show_heading']) ? (int)$cfg['show_heading'] : 1;
$pos_heading_top = $cfg['pos_heading_top'] ?? 21;
$pos_heading_left = $cfg['pos_heading_left'] ?? 50;

$c_present_text = !empty($cfg['present_text']) ? $cfg['present_text'] : 'This is proudly presented to';
$c_present_size = $cfg['present_size'] ?? 13;
$c_present_color = $cfg['present_color'] ?? '#94a3b8';
$show_present = isset($cfg['show_present']) ? (int)$cfg['show_present'] : 1;
$pos_present_top = $cfg['pos_present_top'] ?? 36;
$pos_present_left = $cfg['pos_present_left'] ?? 50;

$c_student_font = $cfg['student_font'] ?? 'great_vibes';
$c_student_size = $cfg['student_size'] ?? 50;
$c_student_color = $cfg['student_color'] ?? '#1e3a5f';
$show_student = isset($cfg['show_student']) ? (int)$cfg['show_student'] : 1;
$pos_student_top = $cfg['pos_student_top'] ?? 46;
$pos_student_left = $cfg['pos_student_left'] ?? 50;

$c_body_text = !empty($cfg['body_text']) ? $cfg['body_text'] : 'For successfully completing all curriculum lectures and practical assessments of the program';
$c_body_size = $cfg['body_size'] ?? 14;
$c_body_color = $cfg['body_color'] ?? '#475569';
$c_body_max_width = $cfg['body_max_width'] ?? 640;
$show_body = isset($cfg['show_body']) ? (int)$cfg['show_body'] : 1;
$pos_body_top = $cfg['pos_body_top'] ?? 58;
$pos_body_left = $cfg['pos_body_left'] ?? 50;

$c_course_font = $cfg['course_font'] ?? 'jakarta';
$c_course_size = $cfg['course_size'] ?? 17;
$c_course_color = $cfg['course_color'] ?? '#1e3a5f';
$show_course = isset($cfg['show_course']) ? (int)$cfg['show_course'] : 1;
$pos_course_top = $cfg['pos_course_top'] ?? 67;
$pos_course_left = $cfg['pos_course_left'] ?? 50;

$c_date_label = !empty($cfg['date_label']) ? $cfg['date_label'] : 'Date of Issue';
$c_date_size = $cfg['date_size'] ?? 14;
$c_date_color = $cfg['date_color'] ?? '#1e3a5f';
$show_date = isset($cfg['show_date']) ? (int)$cfg['show_date'] : 1;
$pos_date_top = $cfg['pos_date_top'] ?? 81;
$pos_date_left = $cfg['pos_date_left'] ?? 16;

$c_sig_name = !empty($cfg['sig_name']) ? $cfg['sig_name'] : $instructor_name;
$c_sig_title = !empty($cfg['sig_title']) ? $cfg['sig_title'] : $signatory_title;
$c_sig_size = $cfg['sig_size'] ?? 14;
$c_sig_color = $cfg['sig_color'] ?? '#1e3a5f';
$c_sig_img_size = $cfg['sig_img_size'] ?? 42;
$show_sig = isset($cfg['show_sig']) ? (int)$cfg['show_sig'] : 1;
$pos_sig_top = $cfg['pos_sig_top'] ?? 81;
$pos_sig_left = $cfg['pos_sig_left'] ?? 84;

$c_show_seal = isset($cfg['show_seal']) ? (int)$cfg['show_seal'] : 1;
$c_seal_size = $cfg['seal_size'] ?? 60;
$pos_seal_top = $cfg['pos_seal_top'] ?? 79;
$pos_seal_left = $cfg['pos_seal_left'] ?? 42;

$c_show_qr = isset($cfg['show_qr']) ? (int)$cfg['show_qr'] : 1;
$c_qr_size = $cfg['qr_size'] ?? 50;
$pos_qr_top = $cfg['pos_qr_top'] ?? 79;
$pos_qr_left = $cfg['pos_qr_left'] ?? 58;

$show_photo = isset($cfg['show_photo']) ? (int)$cfg['show_photo'] : 0;
$c_photo_size = $cfg['photo_size'] ?? 75;
$c_photo_shape = $cfg['photo_shape'] ?? 'passport';
$pos_photo_top = $cfg['pos_photo_top'] ?? 18;
$pos_photo_left = $cfg['pos_photo_left'] ?? 84;

$c_show_watermark = isset($cfg['show_watermark']) ? (int)$cfg['show_watermark'] : 1;
$c_show_border = isset($cfg['show_border']) ? (int)$cfg['show_border'] : 1;
$c_bg_color = $cfg['canvas_bg_color'] ?? '#ffffff';

$show_uid = isset($cfg['show_uid']) ? (int)$cfg['show_uid'] : 1;
$pos_uid_top = $cfg['pos_uid_top'] ?? 94;
$pos_uid_left = $cfg['pos_uid_left'] ?? 50;

$custom_fields = $cfg['custom_fields'] ?? [];

// Helper to replace dynamic field placeholders
function format_custom_tag($text, $cert, $extra_data = []) {
    $replacements = [
        '{student_name}' => $cert['student_name'] ?? '',
        '{course_title}' => $cert['course_title'] ?? '',
        '{issue_date}' => date('d F Y', strtotime($cert['issue_date'] ?? 'now')),
        '{cert_uid}' => $cert['certificate_uid'] ?? '',
        '{instructor_name}' => !empty($cert['instructor_name']) ? $cert['instructor_name'] : 'LKVM Faculty',
        '{roll_no}' => 'LKVM-' . str_pad($cert['user_id'] ?? 101, 4, '0', STR_PAD_LEFT),
        '{student_email}' => $extra_data['email'] ?? ($cert['student_email'] ?? ''),
        '{phone}' => $extra_data['phone'] ?? '',
        '{grade}' => $extra_data['grade'] ?? 'A+ (Distinction)',
        '{duration}' => !empty($extra_data['duration']) ? $extra_data['duration'] : '120 Hours',
        '{completion_percent}' => (isset($cert['completion_percent']) ? intval($cert['completion_percent']) : 100) . '%',
        '{father_name}' => $extra_data['father_name'] ?? 'Not Specified'
    ];
    foreach ($replacements as $tag => $val) {
        $text = str_ireplace($tag, (string)$val, (string)$text);
    }
    return $text;
}

// Font stack helper
function get_font_stack($key) {
    switch ($key) {
        case 'playfair': return "'Playfair Display', Georgia, serif";
        case 'cinzel': return "'Cinzel', 'Times New Roman', serif";
        case 'great_vibes': return "'Great Vibes', cursive";
        case 'alex_brush': return "'Alex Brush', cursive";
        case 'jakarta':
        default: return "'Plus Jakarta Sans', sans-serif";
    }
}

// Template Key resolution
$templates = lms_get_certificate_templates();
$template_key = $cert['certificate_template'] ?? '';

// Check URL override for admin live preview
if (!empty($_GET['template']) && isset($templates[$_GET['template']])) {
    $template_key = trim($_GET['template']);
} elseif (!empty($cert['course_id'])) {
    $c_res = mysqli_query($con, "SELECT duration, certificate_signatory_title, certificate_template FROM lms_courses WHERE id = " . intval($cert['course_id']) . " LIMIT 1");
    if ($c_res && $c_row = mysqli_fetch_assoc($c_res)) {
        if (!empty($c_row['duration'])) {
            $student_extra_data['duration'] = $c_row['duration'];
        }
        if (!empty($c_row['certificate_signatory_title'])) {
            $signatory_title = $c_row['certificate_signatory_title'];
            $c_sig_title = $signatory_title;
        }
        if (empty($template_key) && !empty($c_row['certificate_template'])) {
            $template_key = $c_row['certificate_template'];
        }
    }
}

// Fallback to global setting if still empty
if (empty($template_key)) {
    $template_key = lms_get_setting($con, 'cert_default_template', 'classic_gold');
}

$current_tpl = $templates[$template_key] ?? $templates['classic_gold'];

// Determine if custom canvas should be rendered
$use_custom_canvas = ($template_key === 'custom_bg');
// If global default is custom_bg and template was classic_gold fallback without explicit choice
if (!$use_custom_canvas && empty($_GET['template']) && !empty($cert_custom_bg) && (empty($cert['certificate_template']) || $cert['certificate_template'] === 'custom_bg')) {
    $use_custom_canvas = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - <?php echo $student_name; ?> | <?php echo htmlspecialchars($cert_org_title); ?></title>
    
    <!-- Authentic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;0,900;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Cinzel:wght@600;700;800;900&family=Great+Vibes&family=Alex+Brush&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
        }

        .cert-toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .cert-toolbar a, .cert-toolbar button {
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .cert-toolbar a:hover, .cert-toolbar button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .cert-toolbar .btn-primary-custom {
            background: #0056D2;
            color: #fff;
            border-color: #0056D2;
        }
        .cert-toolbar .btn-primary-custom:hover {
            background: #00419e;
            color: #fff;
        }

        /* ===== BASE CERTIFICATE FRAME ===== */
        .certificate-frame {
            width: 1000px;
            max-width: 100%;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            overflow: hidden;
            position: relative;
        }
        
        .certificate-inner {
            padding: 16px;
        }
        
        .certificate-body {
            padding: 50px 60px 40px;
            position: relative;
            min-height: 680px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Watermark Background Seal */
        .cert-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 380px;
            height: 380px;
            opacity: 0.035;
            pointer-events: none;
            z-index: 1;
        }

        /* Corner ornaments */
        .corner-tl, .corner-tr, .corner-bl, .corner-br {
            position: absolute;
            font-size: 1.5rem;
            z-index: 2;
        }
        .corner-tl { top: 16px; left: 20px; }
        .corner-tr { top: 16px; right: 20px; }
        .corner-bl { bottom: 16px; left: 20px; }
        .corner-br { bottom: 16px; right: 20px; }

        .inner-border {
            position: absolute;
            top: 8px; left: 8px; right: 8px; bottom: 8px;
            border-radius: 2px;
            pointer-events: none;
            z-index: 2;
        }

        .cert-content {
            position: relative;
            z-index: 3;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .cert-logo-section {
            text-align: center;
            margin-bottom: 12px;
        }
        .cert-logo-icon {
            width: 56px; height: 56px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .cert-logo-img {
            max-height: 60px;
            max-width: 140px;
            object-fit: contain;
            margin-bottom: 8px;
        }
        .cert-org-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .cert-title-line {
            width: 90px;
            height: 2px;
            margin: 12px auto;
        }
        
        .cert-heading {
            font-size: 2.35rem;
            font-weight: 800;
            letter-spacing: 4px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 2px;
        }
        .cert-subheading {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            font-weight: 700;
            text-align: center;
        }

        .cert-presented-to {
            font-size: 0.85rem;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 20px;
            text-align: center;
        }

        .cert-student-name {
            font-family: 'Great Vibes', cursive;
            font-size: 3.3rem;
            text-align: center;
            margin: 6px 0 16px;
            line-height: 1.2;
        }

        .cert-course-line {
            text-align: center;
            font-size: 0.96rem;
            max-width: 640px;
            line-height: 1.7;
        }
        .cert-course-name {
            font-weight: 800;
            font-size: 1.12rem;
        }

        .cert-bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            margin-top: 32px;
            padding: 0 15px;
        }
        .cert-bottom-col {
            text-align: center;
            min-width: 180px;
        }
        .cert-bottom-col .label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            padding-top: 8px;
            margin-top: 6px;
        }
        .cert-bottom-col .value {
            font-weight: 700;
            font-size: 0.92rem;
            margin-bottom: 2px;
        }

        .cert-sig-img {
            max-height: 48px;
            max-width: 160px;
            object-fit: contain;
            display: block;
            margin: 0 auto 2px;
        }

        .cert-seal-box {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .cert-seal {
            width: 74px; height: 74px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18);
        }
        .cert-seal-img {
            max-height: 74px;
            max-width: 74px;
            object-fit: contain;
        }
        .cert-seal i {
            color: #fff;
            font-size: 1.8rem;
        }

        .cert-qr-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .cert-qr-box img {
            width: 62px;
            height: 62px;
            display: block;
        }

        .cert-verify-bar {
            text-align: center;
            margin-top: 22px;
            padding: 8px 0 0;
            width: 100%;
        }
        .cert-verify-bar .uid {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 2px;
        }
        .cert-verify-bar .url {
            font-size: 0.75rem;
        }

        /* ===== THEME: CUSTOM UPLOADED BACKGROUND & FREE-FORM CANVAS ===== */
        .tpl-custom_bg {
            aspect-ratio: <?php echo $bg_aspect; ?>;
            width: 1000px;
        }
        .tpl-custom_bg .certificate-inner {
            padding: 0;
            width: 100%;
            height: 100%;
        }
        .tpl-custom_bg .certificate-body {
            border: none;
            padding: 0;
            min-height: 0;
            width: 100%;
            height: 100%;
            display: block;
            position: relative;
            background: <?php echo !empty($cert_custom_bg) ? "url('" . htmlspecialchars($cert_custom_bg) . "') center center / 100% 100% no-repeat" : htmlspecialchars($c_bg_color); ?>;
        }
        
        .custom-canvas-layer {
            position: absolute;
            transform: translate(-50%, -50%);
            white-space: nowrap;
            text-align: center;
            z-index: 5;
            user-select: none;
        }

        .custom-canvas-border {
            position: absolute;
            top: 20px; left: 20px; right: 20px; bottom: 20px;
            border: 2px solid #c9a84c;
            pointer-events: none;
            z-index: 2;
        }
        .custom-canvas-border-inner {
            position: absolute;
            top: 6px; left: 6px; right: 6px; bottom: 6px;
            border: 1px solid rgba(201, 168, 76, 0.4);
        }

        /* Student Photo Styles */
        .student-photo-layer {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 2px solid #c9a84c;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .student-photo-layer.shape-passport {
            width: <?php echo $c_photo_size; ?>px;
            height: <?php echo intval($c_photo_size * 1.25); ?>px;
            border-radius: 4px;
        }
        .student-photo-layer.shape-circle {
            width: <?php echo $c_photo_size; ?>px;
            height: <?php echo $c_photo_size; ?>px;
            border-radius: 50%;
        }
        .student-photo-layer.shape-rounded {
            width: <?php echo $c_photo_size; ?>px;
            height: <?php echo $c_photo_size; ?>px;
            border-radius: 12px;
        }
        .student-photo-layer img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* Revoked Overlay */
        .revoked-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.88);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
        }
        .revoked-stamp {
            border: 6px solid #dc2626;
            border-radius: 12px;
            color: #dc2626;
            font-weight: 900;
            font-size: 3rem;
            padding: 12px 40px;
            transform: rotate(-15deg);
            letter-spacing: 6px;
            text-transform: uppercase;
            opacity: 0.75;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .cert-toolbar { display: none !important; }
            .certificate-frame { box-shadow: none; width: 100%; border-radius: 0; }
        }
        
        @media (max-width: 768px) {
            body { padding: 20px 10px; }
            .certificate-body { padding: 30px 20px; min-height: 500px; }
            .cert-heading { font-size: 1.6rem; letter-spacing: 2px; }
            .cert-student-name { font-size: 2.2rem; }
            .cert-bottom-row { flex-direction: column; gap: 20px; align-items: center; }
            .cert-seal { width: 60px; height: 60px; }
            .cert-seal i { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Toolbar (hidden in print) -->
<div class="cert-toolbar" id="certToolbar">
    <a href="dashboard?tab=certificates"><i class="fa fa-arrow-left"></i> My Classroom Hub</a>
    <button onclick="window.print()"><i class="fa fa-print"></i> Print Certificate</button>
    <button onclick="downloadCertificate()" class="btn-primary-custom"><i class="fa fa-download"></i> Download as Image</button>
    <button onclick="copyCertLink()"><i class="fa fa-link"></i> Copy Verify Link</button>
</div>

<!-- Certificate Frame -->
<div class="certificate-frame tpl-<?php echo $use_custom_canvas ? 'custom_bg' : htmlspecialchars($template_key); ?>" id="certificateFrame">
    <div class="certificate-inner">
        <div class="certificate-body">
            
            <?php if (!$is_active): ?>
            <div class="revoked-overlay">
                <div class="revoked-stamp">REVOKED</div>
            </div>
            <?php endif; ?>

            <?php if ($use_custom_canvas): ?>
                <!-- ================= CUSTOM UPLOADED BACKGROUND CANVAS LAYOUT ================= -->
                <?php if ($c_show_border): ?>
                    <div class="custom-canvas-border">
                        <div class="custom-canvas-border-inner"></div>
                    </div>
                <?php endif; ?>

                <!-- 1. Org Title / Logo -->
                <?php if ($show_org): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_org_top; ?>%; left: <?php echo $pos_org_left; ?>%;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <?php if ($c_show_logo): ?>
                                <?php if (!empty($cert_logo_img)): ?>
                                    <img src="<?php echo htmlspecialchars($cert_logo_img); ?>" style="max-height: <?php echo $c_logo_size; ?>px; object-fit: contain;">
                                <?php else: ?>
                                    <i class="fa fa-graduation-cap" style="font-size: 24px; color: <?php echo htmlspecialchars($c_org_color); ?>;"></i>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span style="font-family: <?php echo get_font_stack($c_org_font); ?>; font-size: <?php echo $c_org_size; ?>px; color: <?php echo htmlspecialchars($c_org_color); ?>; font-weight: 800; letter-spacing: 2px;">
                                <?php echo htmlspecialchars($c_org_title); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 2. Heading & Subheading -->
                <?php if ($show_heading): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_heading_top; ?>%; left: <?php echo $pos_heading_left; ?>%;">
                        <div style="font-family: <?php echo get_font_stack($c_heading_font); ?>; font-size: <?php echo $c_heading_size; ?>px; color: <?php echo htmlspecialchars($c_heading_color); ?>; font-weight: 800; letter-spacing: 4px; text-transform: uppercase; line-height: 1;">
                            <?php echo htmlspecialchars($c_heading_text); ?>
                        </div>
                        <?php if (!empty($c_heading_sub)): ?>
                            <div style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 13px; color: <?php echo htmlspecialchars($c_heading_sub_color); ?>; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; margin-top: 3px;">
                                <?php echo htmlspecialchars($c_heading_sub); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 3. Presentation Lead -->
                <?php if ($show_present): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_present_top; ?>%; left: <?php echo $pos_present_left; ?>%;">
                        <div style="font-size: <?php echo $c_present_size; ?>px; color: <?php echo htmlspecialchars($c_present_color); ?>; letter-spacing: 2px; text-transform: uppercase; font-weight: 600;">
                            <?php echo htmlspecialchars($c_present_text); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 4. Recipient Name -->
                <?php if ($show_student): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_student_top; ?>%; left: <?php echo $pos_student_left; ?>%;">
                        <div style="font-family: <?php echo get_font_stack($c_student_font); ?>; font-size: <?php echo $c_student_size; ?>px; color: <?php echo htmlspecialchars($c_student_color); ?>; line-height: 1.1;">
                            <?php echo $student_name; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 5. Student Profile Photo (Passport) -->
                <?php if ($show_photo): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_photo_top; ?>%; left: <?php echo $pos_photo_left; ?>%;">
                        <div class="student-photo-layer shape-<?php echo htmlspecialchars($c_photo_shape); ?>">
                            <img src="<?php echo htmlspecialchars($student_photo_url); ?>" alt="<?php echo $student_name; ?>">
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 6. Body Text -->
                <?php if ($show_body): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_body_top; ?>%; left: <?php echo $pos_body_left; ?>%; max-width: <?php echo $c_body_max_width; ?>px; white-space: normal;">
                        <div style="font-size: <?php echo $c_body_size; ?>px; color: <?php echo htmlspecialchars($c_body_color); ?>; line-height: 1.5;">
                            <?php echo htmlspecialchars(format_custom_tag($c_body_text, $cert, $student_extra_data)); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 7. Course Title -->
                <?php if ($show_course): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_course_top; ?>%; left: <?php echo $pos_course_left; ?>%;">
                        <div style="font-family: <?php echo get_font_stack($c_course_font); ?>; font-size: <?php echo $c_course_size; ?>px; color: <?php echo htmlspecialchars($c_course_color); ?>; font-weight: 800;">
                            "<?php echo $course_title; ?>"
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 8. Issue Date -->
                <?php if ($show_date): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_date_top; ?>%; left: <?php echo $pos_date_left; ?>%;">
                        <div>
                            <div style="font-weight: 700; font-size: <?php echo $c_date_size; ?>px; color: <?php echo htmlspecialchars($c_date_color); ?>;"><?php echo $issue_date; ?></div>
                            <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; border-top: 1.5px solid #c9a84c; padding-top: 4px; margin-top: 2px; color: #94a3b8;"><?php echo htmlspecialchars($c_date_label); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 9. Seal Stamp -->
                <?php if ($c_show_seal): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_seal_top; ?>%; left: <?php echo $pos_seal_left; ?>%;">
                        <?php if (!empty($cert_seal_img)): ?>
                            <img src="<?php echo htmlspecialchars($cert_seal_img); ?>" style="max-height: <?php echo $c_seal_size; ?>px; max-width: <?php echo $c_seal_size; ?>px; object-fit: contain;">
                        <?php else: ?>
                            <div style="width: <?php echo $c_seal_size; ?>px; height: <?php echo $c_seal_size; ?>px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #c9a84c, #a17d2e); color: #fff; box-shadow: 0 4px 14px rgba(201, 168, 76, 0.4);">
                                <i class="fa fa-award" style="font-size: <?php echo intval($c_seal_size * 0.45); ?>px;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 10. QR Code -->
                <?php if ($c_show_qr): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_qr_top; ?>%; left: <?php echo $pos_qr_left; ?>%;">
                        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 3px; display: inline-block;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode($verify_url); ?>" style="width: <?php echo $c_qr_size; ?>px; height: <?php echo $c_qr_size; ?>px; display: block;" alt="Scan to Verify" crossOrigin="anonymous">
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 11. Signatory & Signature -->
                <?php if ($show_sig): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_sig_top; ?>%; left: <?php echo $pos_sig_left; ?>%;">
                        <div>
                            <?php if (!empty($cert_signature_img)): ?>
                                <img src="<?php echo htmlspecialchars($cert_signature_img); ?>" style="max-height: <?php echo $c_sig_img_size; ?>px; max-width: 140px; object-fit: contain; margin-bottom: 2px;">
                            <?php endif; ?>
                            <div style="font-weight: 700; font-size: <?php echo $c_sig_size; ?>px; color: <?php echo htmlspecialchars($c_sig_color); ?>;"><?php echo htmlspecialchars($c_sig_name); ?></div>
                            <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; border-top: 1.5px solid #c9a84c; padding-top: 4px; margin-top: 2px; color: #94a3b8;"><?php echo htmlspecialchars($c_sig_title); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 12. Credential UID Bar -->
                <?php if ($show_uid): ?>
                    <div class="custom-canvas-layer" style="top: <?php echo $pos_uid_top; ?>%; left: <?php echo $pos_uid_left; ?>%;">
                        <div style="font-size: 10.5px; color: #64748b; font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: 1.5px;">
                            CREDENTIAL ID: <?php echo $cert_uid; ?> • ONLINE VERIFICATION: <?php echo htmlspecialchars($verify_url); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 13. Dynamic Custom Added Fields -->
                <?php foreach ($custom_fields as $cf): ?>
                    <?php if (!empty($cf['id']) && (!isset($cf['visible']) || $cf['visible'])): ?>
                        <div class="custom-canvas-layer" style="
                            top: <?php echo floatval($cf['top'] ?? 50); ?>%;
                            left: <?php echo floatval($cf['left'] ?? 50); ?>%;
                            font-family: <?php echo get_font_stack($cf['fontFamily'] ?? 'jakarta'); ?>;
                            font-size: <?php echo intval($cf['fontSize'] ?? 14); ?>px;
                            color: <?php echo htmlspecialchars($cf['color'] ?? '#1e3a5f'); ?>;
                            font-weight: <?php echo htmlspecialchars($cf['fontWeight'] ?? '700'); ?>;
                        ">
                            <?php echo htmlspecialchars(format_custom_tag($cf['text'] ?? '', $cert, $student_extra_data)); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php else: ?>
                <!-- ================= PRESET DIPLOMA TEMPLATES ================= -->
                <!-- Authentic Vector Watermark -->
                <?php if ($c_show_watermark): ?>
                    <svg class="cert-watermark" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="100" cy="100" r="90" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="4 2"/>
                        <circle cx="100" cy="100" r="75" fill="none" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M100 25 L120 75 L175 75 L130 110 L150 160 L100 130 L50 160 L70 110 L25 75 L80 75 Z" fill="none" stroke="currentColor" stroke-width="1"/>
                    </svg>
                <?php endif; ?>

                <div class="inner-border"></div>
                <span class="corner-tl">❖</span>
                <span class="corner-tr">❖</span>
                <span class="corner-bl">❖</span>
                <span class="corner-br">❖</span>
                
                <div class="cert-content">
                    <!-- Logo & Org Header -->
                    <div class="cert-logo-section">
                        <?php if ($c_show_logo): ?>
                            <?php if (!empty($cert_logo_img)): ?>
                                <img src="<?php echo htmlspecialchars($cert_logo_img); ?>" alt="<?php echo htmlspecialchars($c_org_title); ?>" class="cert-logo-img">
                            <?php else: ?>
                                <div class="cert-logo-icon">
                                    <i class="fa fa-graduation-cap"></i>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="cert-org-name"><?php echo htmlspecialchars($c_org_title); ?></div>
                    </div>
                    
                    <div class="cert-title-line"></div>
                    
                    <!-- Certificate Heading -->
                    <h1 class="cert-heading"><?php echo htmlspecialchars($c_heading_text); ?></h1>
                    <div class="cert-subheading"><?php echo htmlspecialchars($c_heading_sub); ?></div>
                    
                    <!-- Presented To -->
                    <div class="cert-presented-to"><?php echo htmlspecialchars($c_present_text); ?></div>
                    
                    <!-- Student Name -->
                    <div class="cert-student-name"><?php echo $student_name; ?></div>
                    
                    <!-- Course Description -->
                    <div class="cert-course-line">
                        <?php echo htmlspecialchars($c_body_text); ?><br>
                        <span class="cert-course-name">"<?php echo $course_title; ?>"</span><br>
                        authorized and issued by <?php echo htmlspecialchars($c_org_title); ?>
                    </div>
                    
                    <!-- Bottom Row: Date, Seal & QR, Signatory -->
                    <div class="cert-bottom-row">
                        <div class="cert-bottom-col">
                            <div class="value"><?php echo $issue_date; ?></div>
                            <div class="label"><?php echo htmlspecialchars($c_date_label); ?></div>
                        </div>
                        
                        <div class="cert-seal-box">
                            <?php if ($c_show_seal): ?>
                                <?php if (!empty($cert_seal_img)): ?>
                                    <img src="<?php echo htmlspecialchars($cert_seal_img); ?>" class="cert-seal-img" alt="Official Seal">
                                <?php else: ?>
                                    <div class="cert-seal" title="Authentic LKVM Verified Credential">
                                        <i class="fa fa-award"></i>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($c_show_qr): ?>
                                <div class="cert-qr-box" title="Scan with phone camera to verify authenticity online">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode($verify_url); ?>" alt="Scan to Verify" crossOrigin="anonymous">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="cert-bottom-col">
                            <?php if (!empty($cert_signature_img)): ?>
                                <img src="<?php echo htmlspecialchars($cert_signature_img); ?>" class="cert-sig-img" alt="Authority Signature">
                            <?php endif; ?>
                            <div class="value"><?php echo $c_sig_name; ?></div>
                            <div class="label"><?php echo htmlspecialchars($c_sig_title); ?></div>
                        </div>
                    </div>
                    
                    <!-- Verification Bar -->
                    <div class="cert-verify-bar">
                        <div class="uid">CREDENTIAL ID: <?php echo $cert_uid; ?></div>
                        <div class="url">Online Verification Portal: <?php echo htmlspecialchars($verify_url); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- html2canvas for download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    function downloadCertificate() {
        const frame = document.getElementById('certificateFrame');
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;
        
        html2canvas(frame, {
            scale: 2,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            logging: false
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Certificate_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $cert['certificate_uid']); ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            btn.innerHTML = originalText;
            btn.disabled = false;
        }).catch(err => {
            console.error('Download error:', err);
            // Fallback: print
            window.print();
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
    
    function copyCertLink() {
        const url = '<?php echo addslashes($verify_url); ?>';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => {
                const btn = event.target.closest('button');
                const orig = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-check"></i> Copied!';
                btn.style.background = '#dcfce7';
                btn.style.color = '#16a34a';
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.style.background = '';
                    btn.style.color = '';
                }, 2000);
            });
        } else {
            // Fallback
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            alert('Verification link copied!');
        }
    }
    
    <?php if ($download_mode): ?>
    // Auto-trigger download
    window.addEventListener('load', function() {
        setTimeout(downloadCertificate, 1000);
    });
    <?php endif; ?>
</script>
</body>
</html>
