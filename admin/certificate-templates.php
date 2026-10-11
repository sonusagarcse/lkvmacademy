<?php
/**
 * LKVM LMS Admin - Visual Certificate Builder & Draggable Canvas Studio
 * Advanced drag & drop, student profile photo layer, add custom fields, resize elements, delete/restore elements,
 * auto-aspect ratio background detection and real-time live preview.
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/certificate_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

lms_ensure_certificates_table($con);

// Upload directories
$upload_dir = dirname(__DIR__) . '/uploads/certificates/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

$msg = '';
$msg_type = 'success';

// Helper to format sample data tags for visual builder preview
if (!function_exists('lms_format_sample_tags')) {
    function lms_format_sample_tags($text, $sample_uid = 'LKVM-202610-SAMPLE') {
        $replacements = [
            '{student_name}' => 'John Doe',
            '{roll_no}' => 'LKVM-0101',
            '{course_title}' => 'Diploma in Computer Applications',
            '{grade}' => 'A+ (Distinction)',
            '{issue_date}' => date('d F Y'),
            '{cert_uid}' => $sample_uid,
            '{student_email}' => 'john.doe@example.com',
            '{phone}' => '+91 98765 43210',
            '{instructor_name}' => 'Dr. Sonu Sagar',
            '{duration}' => '120 Hours',
            '{completion_percent}' => '100%',
            '{father_name}' => 'Mr. Guardian'
        ];
        foreach ($replacements as $tag => $val) {
            $text = str_ireplace($tag, (string)$val, (string)$text);
        }
        return $text;
    }
}

// Handle Custom Background & Asset Uploads + Visual Canvas Config
if (isset($_POST['save_canvas_builder'])) {
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

    // 1. Custom Background Image
    if (isset($_FILES['cert_bg_file']) && $_FILES['cert_bg_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cert_bg_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_exts)) {
            $new_name = 'cert_bg_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cert_bg_file']['tmp_name'], $upload_dir . $new_name)) {
                lms_set_setting($con, 'cert_custom_bg', 'uploads/certificates/' . $new_name);
            }
        }
    }

    // 2. Custom Logo Image
    if (isset($_FILES['cert_logo_file']) && $_FILES['cert_logo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cert_logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_exts)) {
            $new_name = 'cert_logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cert_logo_file']['tmp_name'], $upload_dir . $new_name)) {
                lms_set_setting($con, 'cert_logo_img', 'uploads/certificates/' . $new_name);
            }
        }
    }

    // 3. Custom Signature Image
    if (isset($_FILES['cert_sig_file']) && $_FILES['cert_sig_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cert_sig_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_exts)) {
            $new_name = 'cert_sig_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cert_sig_file']['tmp_name'], $upload_dir . $new_name)) {
                lms_set_setting($con, 'cert_signature_img', 'uploads/certificates/' . $new_name);
            }
        }
    }

    // 4. Custom Stamp / Seal Image
    if (isset($_FILES['cert_seal_file']) && $_FILES['cert_seal_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cert_seal_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_exts)) {
            $new_name = 'cert_seal_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cert_seal_file']['tmp_name'], $upload_dir . $new_name)) {
                lms_set_setting($con, 'cert_seal_img', 'uploads/certificates/' . $new_name);
            }
        }
    }

    // Decode Custom Fields JSON
    $custom_fields = [];
    if (!empty($_POST['custom_fields_json'])) {
        $decoded = json_decode($_POST['custom_fields_json'], true);
        if (is_array($decoded)) {
            $custom_fields = $decoded;
        }
    }

    // Canvas Configuration JSON with Free-Form Absolute Positions & Sizes
    $canvas_config = [
        'org_title' => trim($_POST['cfg_org_title'] ?? 'LKVM ACADEMY'),
        'org_font' => trim($_POST['cfg_org_font'] ?? 'jakarta'),
        'org_size' => intval($_POST['cfg_org_size'] ?? 18),
        'org_color' => trim($_POST['cfg_org_color'] ?? '#1e3a5f'),
        'show_logo' => isset($_POST['cfg_show_logo']) ? 1 : 0,
        'logo_size' => intval($_POST['cfg_logo_size'] ?? 42),
        'show_org' => isset($_POST['show_org']) ? 1 : 0,
        'pos_org_top' => floatval($_POST['pos_org_top'] ?? 8),
        'pos_org_left' => floatval($_POST['pos_org_left'] ?? 50),
        
        'heading_text' => trim($_POST['cfg_heading_text'] ?? 'Certificate'),
        'heading_sub' => trim($_POST['cfg_heading_sub'] ?? 'OF COMPLETION'),
        'heading_font' => trim($_POST['cfg_heading_font'] ?? 'playfair'),
        'heading_size' => intval($_POST['cfg_heading_size'] ?? 36),
        'heading_color' => trim($_POST['cfg_heading_color'] ?? '#1e3a5f'),
        'heading_sub_color' => trim($_POST['cfg_heading_sub_color'] ?? '#64748b'),
        'show_heading' => isset($_POST['show_heading']) ? 1 : 0,
        'pos_heading_top' => floatval($_POST['pos_heading_top'] ?? 21),
        'pos_heading_left' => floatval($_POST['pos_heading_left'] ?? 50),
        
        'present_text' => trim($_POST['cfg_present_text'] ?? 'This is proudly presented to'),
        'present_size' => intval($_POST['cfg_present_size'] ?? 13),
        'present_color' => trim($_POST['cfg_present_color'] ?? '#94a3b8'),
        'show_present' => isset($_POST['show_present']) ? 1 : 0,
        'pos_present_top' => floatval($_POST['pos_present_top'] ?? 36),
        'pos_present_left' => floatval($_POST['pos_present_left'] ?? 50),
        
        'student_font' => trim($_POST['cfg_student_font'] ?? 'great_vibes'),
        'student_size' => intval($_POST['cfg_student_size'] ?? 50),
        'student_color' => trim($_POST['cfg_student_color'] ?? '#1e3a5f'),
        'show_student' => isset($_POST['show_student']) ? 1 : 0,
        'pos_student_top' => floatval($_POST['pos_student_top'] ?? 46),
        'pos_student_left' => floatval($_POST['pos_student_left'] ?? 50),
        
        'body_text' => trim($_POST['cfg_body_text'] ?? 'For successfully completing all curriculum lectures and practical assessments of the program'),
        'body_size' => intval($_POST['cfg_body_size'] ?? 14),
        'body_color' => trim($_POST['cfg_body_color'] ?? '#475569'),
        'body_max_width' => intval($_POST['cfg_body_max_width'] ?? 640),
        'show_body' => isset($_POST['show_body']) ? 1 : 0,
        'pos_body_top' => floatval($_POST['pos_body_top'] ?? 58),
        'pos_body_left' => floatval($_POST['pos_body_left'] ?? 50),
        
        'course_font' => trim($_POST['cfg_course_font'] ?? 'jakarta'),
        'course_size' => intval($_POST['cfg_course_size'] ?? 17),
        'course_color' => trim($_POST['cfg_course_color'] ?? '#1e3a5f'),
        'show_course' => isset($_POST['show_course']) ? 1 : 0,
        'pos_course_top' => floatval($_POST['pos_course_top'] ?? 67),
        'pos_course_left' => floatval($_POST['pos_course_left'] ?? 50),
        
        'date_label' => trim($_POST['cfg_date_label'] ?? 'Date of Issue'),
        'date_size' => intval($_POST['cfg_date_size'] ?? 14),
        'date_color' => trim($_POST['cfg_date_color'] ?? '#1e3a5f'),
        'show_date' => isset($_POST['show_date']) ? 1 : 0,
        'pos_date_top' => floatval($_POST['pos_date_top'] ?? 81),
        'pos_date_left' => floatval($_POST['pos_date_left'] ?? 16),
        
        'sig_name' => trim($_POST['cfg_sig_name'] ?? 'Dr. Sonu Sagar'),
        'sig_title' => trim($_POST['cfg_sig_title'] ?? 'Director & Head of Academics'),
        'sig_size' => intval($_POST['cfg_sig_size'] ?? 14),
        'sig_color' => trim($_POST['cfg_sig_color'] ?? '#1e3a5f'),
        'sig_img_size' => intval($_POST['cfg_sig_img_size'] ?? 42),
        'show_sig' => isset($_POST['show_sig']) ? 1 : 0,
        'pos_sig_top' => floatval($_POST['pos_sig_top'] ?? 81),
        'pos_sig_left' => floatval($_POST['pos_sig_left'] ?? 84),
        
        'show_seal' => isset($_POST['cfg_show_seal']) ? 1 : 0,
        'seal_size' => intval($_POST['cfg_seal_size'] ?? 60),
        'pos_seal_top' => floatval($_POST['pos_seal_top'] ?? 79),
        'pos_seal_left' => floatval($_POST['pos_seal_left'] ?? 42),
        
        'show_qr' => isset($_POST['cfg_show_qr']) ? 1 : 0,
        'qr_size' => intval($_POST['cfg_qr_size'] ?? 50),
        'pos_qr_top' => floatval($_POST['pos_qr_top'] ?? 79),
        'pos_qr_left' => floatval($_POST['pos_qr_left'] ?? 58),

        // Student Profile Picture Layer
        'show_photo' => isset($_POST['show_photo']) ? 1 : 0,
        'photo_size' => intval($_POST['cfg_photo_size'] ?? 75),
        'photo_shape' => trim($_POST['cfg_photo_shape'] ?? 'passport'),
        'pos_photo_top' => floatval($_POST['pos_photo_top'] ?? 18),
        'pos_photo_left' => floatval($_POST['pos_photo_left'] ?? 84),
        
        'show_watermark' => isset($_POST['cfg_show_watermark']) ? 1 : 0,
        'show_border' => isset($_POST['cfg_show_border']) ? 1 : 0,
        'canvas_bg_color' => trim($_POST['cfg_canvas_bg_color'] ?? '#ffffff'),

        'show_uid' => isset($_POST['show_uid']) ? 1 : 0,
        'pos_uid_top' => floatval($_POST['pos_uid_top'] ?? 94),
        'pos_uid_left' => floatval($_POST['pos_uid_left'] ?? 50),

        // Custom Dynamically Added Fields
        'custom_fields' => $custom_fields
    ];

    lms_set_setting($con, 'cert_canvas_config', json_encode($canvas_config, JSON_UNESCAPED_UNICODE));
    
    // Sync globals
    lms_set_setting($con, 'cert_org_title', $canvas_config['org_title']);
    lms_set_setting($con, 'cert_signatory_name', $canvas_config['sig_name']);
    lms_set_setting($con, 'cert_signatory_title', $canvas_config['sig_title']);
    lms_set_setting($con, 'cert_show_qr', $canvas_config['show_qr'] ? '1' : '0');
    lms_set_setting($con, 'cert_show_watermark', $canvas_config['show_watermark'] ? '1' : '0');

    if (isset($_POST['cert_default_template'])) {
        lms_set_setting($con, 'cert_default_template', trim($_POST['cert_default_template']));
    }

    $msg = "Certificate layout, profile picture settings, and custom fields saved successfully!";
}

// Handle Remove Uploaded Custom Background
if (isset($_GET['remove_bg'])) {
    $old_bg = lms_get_setting($con, 'cert_custom_bg');
    if (!empty($old_bg) && file_exists(dirname(__DIR__) . '/' . $old_bg)) {
        @unlink(dirname(__DIR__) . '/' . $old_bg);
    }
    lms_set_setting($con, 'cert_custom_bg', '');
    lms_redirect("certificate-templates?msg=" . urlencode("Custom background removed."));
}

// Handle Remove Uploaded Signature
if (isset($_GET['remove_sig'])) {
    $old_sig = lms_get_setting($con, 'cert_signature_img');
    if (!empty($old_sig) && file_exists(dirname(__DIR__) . '/' . $old_sig)) {
        @unlink(dirname(__DIR__) . '/' . $old_sig);
    }
    lms_set_setting($con, 'cert_signature_img', '');
    lms_redirect("certificate-templates?msg=" . urlencode("Signature image removed."));
}

// Handle Remove Uploaded Seal
if (isset($_GET['remove_seal'])) {
    $old_seal = lms_get_setting($con, 'cert_seal_img');
    if (!empty($old_seal) && file_exists(dirname(__DIR__) . '/' . $old_seal)) {
        @unlink(dirname(__DIR__) . '/' . $old_seal);
    }
    lms_set_setting($con, 'cert_seal_img', '');
    lms_redirect("certificate-templates?msg=" . urlencode("Seal stamp removed."));
}

// Handle Set Default Template via quick action
if (isset($_GET['set_default_template'])) {
    $t_id = trim($_GET['set_default_template']);
    $avail = lms_get_certificate_templates();
    if (isset($avail[$t_id])) {
        lms_set_setting($con, 'cert_default_template', $t_id);
        lms_redirect("certificate-templates?tab=gallery&msg=" . urlencode("Default Certificate Template set to: " . $avail[$t_id]['name']));
    }
}

// Handle Reset Canvas to Default Layout
if (isset($_GET['reset_canvas_default'])) {
    lms_set_setting($con, 'cert_canvas_config', '');
    lms_redirect("certificate-templates?tab=studio&msg=" . urlencode("Canvas layout reset to standard default certificate design."));
}

$active_main_view = $_GET['tab'] ?? 'studio';

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Load settings
$templates = lms_get_certificate_templates();
$default_template = lms_get_setting($con, 'cert_default_template', 'classic_gold');
$cert_custom_bg = lms_get_setting($con, 'cert_custom_bg');
$cert_logo_img = lms_get_setting($con, 'cert_logo_img');
$cert_signature_img = lms_get_setting($con, 'cert_signature_img');
$cert_seal_img = lms_get_setting($con, 'cert_seal_img');

// Auto Calculate Aspect Ratio from Uploaded Image
$bg_aspect = "1000 / 707"; // default A4 Landscape
$bg_width = 1000;
$bg_height = 707;
if (!empty($cert_custom_bg)) {
    $abs_bg = dirname(__DIR__) . '/' . ltrim($cert_custom_bg, '/');
    if (file_exists($abs_bg)) {
        $size_info = @getimagesize($abs_bg);
        if ($size_info && $size_info[0] > 0 && $size_info[1] > 0) {
            $bg_width = $size_info[0];
            $bg_height = $size_info[1];
            $bg_aspect = "{$bg_width} / {$bg_height}";
        }
    }
}

// Load Canvas Config
$saved_cfg_raw = lms_get_setting($con, 'cert_canvas_config', '');
$cfg = !empty($saved_cfg_raw) ? json_decode($saved_cfg_raw, true) : [];

// Positions & properties
$pos_org_top = $cfg['pos_org_top'] ?? 8;
$pos_org_left = $cfg['pos_org_left'] ?? 50;
$show_org = isset($cfg['show_org']) ? (int)$cfg['show_org'] : 1;
$c_org_title = $cfg['org_title'] ?? lms_get_setting($con, 'cert_org_title', 'LKVM ACADEMY');
$c_org_font = $cfg['org_font'] ?? 'jakarta';
$c_org_size = $cfg['org_size'] ?? 18;
$c_org_color = $cfg['org_color'] ?? '#1e3a5f';
$c_show_logo = isset($cfg['show_logo']) ? (int)$cfg['show_logo'] : 1;
$c_logo_size = $cfg['logo_size'] ?? 42;

$pos_heading_top = $cfg['pos_heading_top'] ?? 21;
$pos_heading_left = $cfg['pos_heading_left'] ?? 50;
$show_heading = isset($cfg['show_heading']) ? (int)$cfg['show_heading'] : 1;
$c_heading_text = $cfg['heading_text'] ?? 'Certificate';
$c_heading_sub = $cfg['heading_sub'] ?? 'OF COMPLETION';
$c_heading_font = $cfg['heading_font'] ?? 'playfair';
$c_heading_size = $cfg['heading_size'] ?? 36;
$c_heading_color = $cfg['heading_color'] ?? '#1e3a5f';
$c_heading_sub_color = $cfg['heading_sub_color'] ?? '#64748b';

$pos_present_top = $cfg['pos_present_top'] ?? 36;
$pos_present_left = $cfg['pos_present_left'] ?? 50;
$show_present = isset($cfg['show_present']) ? (int)$cfg['show_present'] : 1;
$c_present_text = $cfg['present_text'] ?? 'This is proudly presented to';
$c_present_size = $cfg['present_size'] ?? 13;
$c_present_color = $cfg['present_color'] ?? '#94a3b8';

$pos_student_top = $cfg['pos_student_top'] ?? 46;
$pos_student_left = $cfg['pos_student_left'] ?? 50;
$show_student = isset($cfg['show_student']) ? (int)$cfg['show_student'] : 1;
$c_student_font = $cfg['student_font'] ?? 'great_vibes';
$c_student_size = $cfg['student_size'] ?? 50;
$c_student_color = $cfg['student_color'] ?? '#1e3a5f';

$pos_body_top = $cfg['pos_body_top'] ?? 58;
$pos_body_left = $cfg['pos_body_left'] ?? 50;
$show_body = isset($cfg['show_body']) ? (int)$cfg['show_body'] : 1;
$c_body_text = $cfg['body_text'] ?? 'For successfully completing all curriculum lectures and practical assessments of the program';
$c_body_size = $cfg['body_size'] ?? 14;
$c_body_color = $cfg['body_color'] ?? '#475569';
$c_body_max_width = $cfg['body_max_width'] ?? 640;

$pos_course_top = $cfg['pos_course_top'] ?? 67;
$pos_course_left = $cfg['pos_course_left'] ?? 50;
$show_course = isset($cfg['show_course']) ? (int)$cfg['show_course'] : 1;
$c_course_font = $cfg['course_font'] ?? 'jakarta';
$c_course_size = $cfg['course_size'] ?? 17;
$c_course_color = $cfg['course_color'] ?? '#1e3a5f';

$pos_date_top = $cfg['pos_date_top'] ?? 81;
$pos_date_left = $cfg['pos_date_left'] ?? 16;
$show_date = isset($cfg['show_date']) ? (int)$cfg['show_date'] : 1;
$c_date_label = $cfg['date_label'] ?? 'Date of Issue';
$c_date_size = $cfg['date_size'] ?? 14;
$c_date_color = $cfg['date_color'] ?? '#1e3a5f';

$pos_seal_top = $cfg['pos_seal_top'] ?? 79;
$pos_seal_left = $cfg['pos_seal_left'] ?? 42;
$c_show_seal = isset($cfg['show_seal']) ? (int)$cfg['show_seal'] : 1;
$c_seal_size = $cfg['seal_size'] ?? 60;

$pos_qr_top = $cfg['pos_qr_top'] ?? 79;
$pos_qr_left = $cfg['pos_qr_left'] ?? 58;
$c_show_qr = isset($cfg['show_qr']) ? (int)$cfg['show_qr'] : 1;
$c_qr_size = $cfg['qr_size'] ?? 50;

$pos_sig_top = $cfg['pos_sig_top'] ?? 81;
$pos_sig_left = $cfg['pos_sig_left'] ?? 84;
$show_sig = isset($cfg['show_sig']) ? (int)$cfg['show_sig'] : 1;
$c_sig_name = $cfg['sig_name'] ?? lms_get_setting($con, 'cert_signatory_name', 'Dr. Sonu Sagar');
$c_sig_title = $cfg['sig_title'] ?? lms_get_setting($con, 'cert_signatory_title', 'Director & Head of Academics');
$c_sig_size = $cfg['sig_size'] ?? 14;
$c_sig_color = $cfg['sig_color'] ?? '#1e3a5f';
$c_sig_img_size = $cfg['sig_img_size'] ?? 42;

$show_photo = isset($cfg['show_photo']) ? (int)$cfg['show_photo'] : 0;
$c_photo_size = $cfg['photo_size'] ?? 75;
$c_photo_shape = $cfg['photo_shape'] ?? 'passport';
$pos_photo_top = $cfg['pos_photo_top'] ?? 18;
$pos_photo_left = $cfg['pos_photo_left'] ?? 84;

$pos_uid_top = $cfg['pos_uid_top'] ?? 94;
$pos_uid_left = $cfg['pos_uid_left'] ?? 50;
$show_uid = isset($cfg['show_uid']) ? (int)$cfg['show_uid'] : 1;

$c_show_watermark = isset($cfg['show_watermark']) ? (int)$cfg['show_watermark'] : 1;
$c_show_border = isset($cfg['show_border']) ? (int)$cfg['show_border'] : 1;
$c_bg_color = $cfg['canvas_bg_color'] ?? '#ffffff';

// Custom Fields
$custom_fields = $cfg['custom_fields'] ?? [];

// Check sample certificate
$sample_cert_q = mysqli_query($con, "SELECT certificate_uid FROM lms_certificates ORDER BY id DESC LIMIT 1");
$sample_cert_uid = ($sample_cert_q && $row = mysqli_fetch_assoc($sample_cert_q)) ? $row['certificate_uid'] : 'LKVM-202610-SAMPLE';

$page_title = 'Visual Certificate Builder & Studio';
include 'includes/header.php';
?>

<!-- Google Fonts for Canvas -->
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;0,900;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Cinzel:wght@600;700;800;900&family=Great+Vibes&family=Alex+Brush&family=Montserrat:wght@400;600;700;800&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">

<style>
    /* Studio Header & Floating Canvas */
    .canvas-viewport-container {
        background: #0f172a;
        background-image: radial-gradient(#334155 1px, transparent 1px);
        background-size: 20px 20px;
        border-radius: 20px;
        padding: 24px;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: auto;
        box-shadow: inset 0 2px 14px rgba(0,0,0,0.5);
        min-height: 590px;
        position: relative;
    }
    
    .canvas-frame-box {
        width: 100%;
        max-width: 960px;
        position: relative;
        background: <?php echo htmlspecialchars($c_bg_color); ?>;
        <?php if (!empty($cert_custom_bg)): ?>
            background-image: url('../<?php echo htmlspecialchars($cert_custom_bg); ?>');
        <?php endif; ?>
        background-size: 100% 100%;
        background-repeat: no-repeat;
        aspect-ratio: <?php echo $bg_aspect; ?>;
        box-shadow: 0 20px 50px rgba(0,0,0,0.45);
        border-radius: 4px;
        overflow: hidden;
        user-select: none;
    }

    /* Draggable layers & Active Focus Styling */
    .canvas-layer {
        position: absolute;
        transform: translate(-50%, -50%);
        cursor: move;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px dashed transparent;
        transition: border-color 0.15s, box-shadow 0.15s;
        white-space: nowrap;
        text-align: center;
        z-index: 10;
        box-sizing: border-box;
    }
    .canvas-layer:hover {
        border-color: rgba(99, 102, 241, 0.6);
        background: rgba(255, 255, 255, 0.45);
    }
    .canvas-layer.active-selected {
        border: 2px dashed #4f46e5 !important;
        background: rgba(255, 255, 255, 0.9) !important;
        box-shadow: 0 4px 18px rgba(79, 70, 229, 0.3);
        z-index: 50;
    }
    
    /* Layer Title Tag */
    .canvas-layer .layer-badge {
        position: absolute;
        top: -20px;
        left: 50%;
        transform: translateX(-50%);
        background: #4f46e5;
        color: #fff;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 8px;
        display: none;
        letter-spacing: 0.5px;
        white-space: nowrap;
        pointer-events: none;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .canvas-layer.active-selected .layer-badge {
        display: block;
    }

    /* Floating Quick-Actions Toolbar on Selected Element */
    .canvas-layer .layer-quick-tools {
        position: absolute;
        bottom: -32px;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: #fff;
        border-radius: 20px;
        padding: 3px 8px;
        display: none;
        align-items: center;
        gap: 6px;
        z-index: 60;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        pointer-events: auto;
    }
    .canvas-layer.active-selected .layer-quick-tools {
        display: flex;
    }
    .layer-quick-btn {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 0.75rem;
        padding: 2px 6px;
        cursor: pointer;
        border-radius: 4px;
        transition: color 0.15s, background 0.15s;
    }
    .layer-quick-btn:hover {
        color: #fff;
        background: rgba(255,255,255,0.15);
    }
    .layer-quick-btn.btn-delete:hover {
        color: #ef4444;
        background: rgba(239,68,68,0.2);
    }

    /* Interactive Bottom-Right Resize Handle */
    .canvas-layer .resize-handle {
        position: absolute;
        bottom: -6px;
        right: -6px;
        width: 14px;
        height: 14px;
        background: #4f46e5;
        border: 2px solid #fff;
        border-radius: 50%;
        cursor: nwse-resize;
        display: none;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        z-index: 55;
    }
    .canvas-layer.active-selected .resize-handle {
        display: block;
    }

    /* Student Photo Box Styling */
    .student-photo-preview {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border: 2px solid #c9a84c;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        overflow: hidden;
    }
    .student-photo-preview.shape-passport {
        width: 65px;
        height: 80px;
        border-radius: 4px;
    }
    .student-photo-preview.shape-circle {
        width: 75px;
        height: 75px;
        border-radius: 50%;
    }
    .student-photo-preview.shape-rounded {
        width: 75px;
        height: 75px;
        border-radius: 12px;
    }

    /* Optional Built-in Border */
    .canvas-border-frame {
        position: absolute;
        top: 14px; left: 14px; right: 14px; bottom: 14px;
        border: 3px solid #c9a84c;
        pointer-events: none;
    }
    .canvas-border-inner {
        position: absolute;
        top: 6px; left: 6px; right: 6px; bottom: 6px;
        border: 1px solid rgba(201, 168, 76, 0.4);
        pointer-events: none;
    }

    /* Control Tabs */
    .builder-nav-tab {
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.86rem;
        padding: 9px 15px;
        color: #475569;
        border: 1px solid #e2e8f0;
        background: #fff;
        transition: all 0.2s;
        cursor: pointer;
    }
    .builder-nav-tab.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
    }

    /* Custom Field Item in List */
    .field-layer-row {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s;
    }
    .field-layer-row:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }
    .field-layer-row.is-selected {
        border-color: #4f46e5;
        background: #f5f3ff;
    }
</style>

<?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-circle-check me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Top Main View Navigation Switcher -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 p-3 bg-white rounded-4 border shadow-sm">
    <div class="d-flex align-items-center gap-2">
        <ul class="nav nav-pills p-1 bg-light rounded-pill border">
            <li class="nav-item">
                <button type="button" class="nav-link rounded-pill fw-bold px-3 py-2 <?php echo $active_main_view !== 'gallery' ? 'active' : ''; ?>" id="navBtnStudio" onclick="switchMainView('studio')">
                    <i class="fa fa-wand-magic-sparkles me-1"></i> Visual Canvas Studio
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link rounded-pill fw-bold px-3 py-2 <?php echo $active_main_view === 'gallery' ? 'active' : ''; ?>" id="navBtnGallery" onclick="switchMainView('gallery')">
                    <i class="fa fa-palette me-1"></i> Default Certificate Templates Gallery
                </button>
            </li>
        </ul>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold small">
            <i class="fa fa-star me-1"></i> Active Default: <?php echo htmlspecialchars($templates[$default_template]['name'] ?? 'Classic Gold'); ?>
        </span>
        <?php if (!empty($sample_cert_uid)): ?>
            <a href="../certificate-view?uid=<?php echo urlencode($sample_cert_uid); ?>" target="_blank" class="btn btn-outline-dark rounded-pill px-3 fw-bold btn-sm">
                <i class="fa fa-eye me-1"></i> View Live Sample
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- ================= DEFAULT CERTIFICATE TEMPLATES GALLERY ================= -->
<div id="galleryViewSection" style="<?php echo $active_main_view !== 'gallery' ? 'display:none;' : ''; ?>">
    <div class="admin-card p-4 bg-white border rounded-4 shadow-sm mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="fw-800 text-dark mb-1"><i class="fa fa-palette text-primary me-2"></i>Built-in Certificate Templates Gallery</h5>
                <p class="text-muted small mb-0">Browse our collection of industry-grade luxury certificate designs. Set any design as your active system default or preview with sample student credentials.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-bold">
                    <i class="fa fa-check-circle me-1"></i> Current Active: <?php echo htmlspecialchars($templates[$default_template]['name'] ?? 'Classic Gold'); ?>
                </span>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold btn-sm shadow-sm" onclick="switchMainView('studio')">
                    <i class="fa fa-wand-magic-sparkles me-1"></i> Open Canvas Studio
                </button>
            </div>
        </div>
    </div>

    <!-- Template Cards Grid -->
    <div class="row g-4 mb-4">
        <?php foreach ($templates as $t_id => $tpl): 
            $is_active_default = ($default_template === $t_id);
        ?>
        <div class="col-lg-4 col-md-6">
            <div class="admin-card h-100 p-0 overflow-hidden border rounded-4 shadow-sm tpl-gallery-card position-relative" style="<?php echo $is_active_default ? 'border: 2px solid ' . $tpl['primary_color'] . ' !important; box-shadow: 0 10px 25px rgba(0,0,0,0.08) !important;' : ''; ?>">
                
                <!-- Miniature Live Stylized Certificate Card Header -->
                <div class="p-4 position-relative" style="background: <?php echo $tpl['bg_gradient']; ?>; border-bottom: 1px solid #e2e8f0; min-height: 180px; display: flex; flex-direction: column; justify-content: space-between;">
                    
                    <!-- Corner Ornaments (Classic / Academic) -->
                    <?php if ($t_id === 'classic_gold' || $t_id === 'academic_crimson' || $t_id === 'emerald_prestige'): ?>
                        <div style="position: absolute; top: 8px; left: 8px; right: 8px; bottom: 8px; border: 1.5px solid <?php echo $tpl['primary_color']; ?>; opacity: 0.35; pointer-events: none; border-radius: 4px;"></div>
                    <?php elseif ($t_id === 'modern_blue'): ?>
                        <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0056D2, #0284c7);"></div>
                    <?php elseif ($t_id === 'minimal_dark'): ?>
                        <div style="position: absolute; top: 10px; left: 10px; right: 10px; bottom: 10px; border: 1px solid #cbd5e1; pointer-events: none;"></div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-start position-relative z-1">
                        <span class="badge rounded-pill text-white px-2 py-1 small fw-bold" style="background: <?php echo $tpl['primary_color']; ?>; font-size: 0.7rem;">
                            <i class="fa <?php echo $tpl['icon']; ?> me-1"></i> <?php echo htmlspecialchars($tpl['badge']); ?>
                        </span>
                        <?php if ($is_active_default): ?>
                            <span class="badge bg-success rounded-pill px-3 py-1 text-white fw-bold shadow-sm" style="font-size: 0.72rem;">
                                <i class="fa fa-circle-check me-1"></i> ACTIVE DEFAULT
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="text-center my-2 position-relative z-1">
                        <div style="font-size: 11px; letter-spacing: 2px; text-transform: uppercase; font-weight: 800; color: <?php echo $tpl['primary_color']; ?>;">
                            LKVM ACADEMY • CERTIFICATE
                        </div>
                        <div style="font-family: <?php echo in_array($t_id, ['classic_gold', 'emerald_prestige', 'academic_crimson']) ? "'Playfair Display', serif" : "'Plus Jakarta Sans', sans-serif"; ?>; font-weight: 800; font-size: 1.15rem; color: <?php echo $tpl['secondary_color']; ?>; letter-spacing: 1px; margin-top: 2px;">
                            OF COMPLETION
                        </div>
                        <div style="font-family: 'Great Vibes', cursive; font-size: 1.6rem; color: <?php echo $tpl['primary_color']; ?>; line-height: 1.1; margin-top: 4px;">
                            John Doe
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center position-relative z-1 small text-muted" style="font-size: 0.72rem;">
                        <span><i class="fa fa-calendar-alt me-1"></i> <?php echo date("d M Y"); ?></span>
                        <span style="font-family: 'Courier New', monospace; font-weight: 700;">LKVM-VERIFIED</span>
                    </div>
                </div>

                <!-- Card Body & Details -->
                <div class="p-3 p-xl-4 bg-white">
                    <h6 class="fw-800 text-dark mb-1"><?php echo htmlspecialchars($tpl['name']); ?></h6>
                    <p class="text-muted small mb-3" style="min-height: 40px;"><?php echo htmlspecialchars($tpl['desc']); ?></p>

                    <!-- Color Swatches & Typography Specs -->
                    <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3 mb-3 border">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-bold text-muted">Palette:</span>
                            <span class="rounded-circle d-inline-block border shadow-sm" style="width: 18px; height: 18px; background: <?php echo $tpl['primary_color']; ?>;" title="Primary Color: <?php echo $tpl['primary_color']; ?>"></span>
                            <span class="rounded-circle d-inline-block border shadow-sm" style="width: 18px; height: 18px; background: <?php echo $tpl['secondary_color']; ?>;" title="Secondary Color: <?php echo $tpl['secondary_color']; ?>"></span>
                        </div>
                        <span class="badge bg-white text-muted border small">
                            <?php echo ($t_id === 'custom_bg') ? 'Free-Form Canvas' : 'High-DPI Vector'; ?>
                        </span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-column gap-2">
                        <?php if (!$is_active_default): ?>
                            <a href="certificate-templates?set_default_template=<?php echo urlencode($t_id); ?>" class="btn btn-outline-primary rounded-pill fw-bold btn-sm w-100 py-2">
                                <i class="fa fa-star me-1"></i> Set as Active System Default
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-success rounded-pill fw-bold btn-sm w-100 py-2 disabled">
                                <i class="fa fa-check-circle me-1"></i> Current Active System Default
                            </button>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <?php if ($t_id !== 'custom_bg'): ?>
                                <a href="../certificate-view?uid=<?php echo urlencode($sample_cert_uid); ?>&template=<?php echo urlencode($t_id); ?>" target="_blank" class="btn btn-light border rounded-pill fw-bold btn-sm flex-grow-1 py-1 text-dark">
                                    <i class="fa fa-eye me-1"></i> Live Preview
                                </a>
                                <button type="button" class="btn btn-light border rounded-pill fw-bold btn-sm py-1 text-primary" onclick="applyTemplateThemeToCanvas('<?php echo $t_id; ?>')" title="Apply this design's colors and fonts into Canvas Studio">
                                    <i class="fa fa-wand-magic me-1"></i> Load in Studio
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="switchMainView('studio')" class="btn btn-primary rounded-pill fw-bold btn-sm flex-grow-1 py-1">
                                    <i class="fa fa-arrow-right me-1"></i> Open Studio Canvas
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- /#galleryViewSection -->

<!-- ================= CUSTOM VISUAL CANVAS STUDIO ================= -->
<div id="studioViewSection" style="<?php echo $active_main_view === 'gallery' ? 'display:none;' : ''; ?>">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-800 mb-1"><i class="fa fa-wand-magic-sparkles text-primary me-2"></i>Certificate Studio: Add, Resize & Position Elements</h4>
            <p class="text-muted small mb-0">Drag elements to move, <strong>drag corner handles to resize font size & photo</strong>, click <i class="fa fa-plus text-primary"></i> to add new fields or student photo.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary rounded-pill px-3 fw-bold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddCustomText">
                <i class="fa fa-tags me-1"></i> Add Custom Text with Tags
            </button>
            <div class="dropdown">
                <button class="btn btn-success rounded-pill px-3 fw-bold btn-sm dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-plus-circle me-1"></i> Quick Presets
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg rounded-3 border-0 py-2">
                    <li><h6 class="dropdown-header text-uppercase small fw-bold">Student Assets</h6></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="enableStudentPhoto()"><i class="fa fa-image-portrait text-success me-2"></i> Student Profile Picture</a></li>
                    <li><a class="dropdown-item py-2 fw-semibold text-primary" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAddCustomText"><i class="fa fa-wand-magic-sparkles text-primary me-2"></i> Custom Text with Tags (Advanced)</a></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="addCustomField('roll_no', 'Student Roll / Reg No', 'Roll No: {roll_no}')"><i class="fa fa-id-card text-primary me-2"></i> Roll / Reg Number</a></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="addCustomField('grade', 'Grade / Score', 'Grade: A+ (Distinction)')"><i class="fa fa-star text-warning me-2"></i> Grade / Division</a></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="addCustomField('duration', 'Course Duration', 'Duration: 120 Hours')"><i class="fa fa-clock text-info me-2"></i> Course Duration / Hours</a></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="addCustomField('father_name', 'Father / Guardian Name', 'S/o: {father_name}')"><i class="fa fa-user-tag text-secondary me-2"></i> Father / Guardian Name</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header text-uppercase small fw-bold">Custom Labels</h6></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="addCustomField('custom_text', 'Custom Text Heading', 'SPECIAL ACHIEVEMENT')"><i class="fa fa-font text-dark me-2"></i> Custom Heading / Text</a></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="addCustomField('second_sig', 'Second Signatory', 'Controller of Examinations')"><i class="fa fa-pen-nib text-danger me-2"></i> Second Signatory / Controller</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3 fw-bold btn-sm" id="btnAutoCenter" title="Center all headline and text elements horizontally (Left: 50%)">
                <i class="fa fa-arrows-to-dot me-1"></i> Auto-Center
            </button>
            <button type="button" class="btn btn-outline-danger rounded-pill px-3 fw-bold btn-sm" id="btnResetDefaultLayout" title="Reset all elements to standard default certificate design layout">
                <i class="fa fa-rotate-left me-1"></i> Reset to Default Design
            </button>
            <?php if (!empty($sample_cert_uid)): ?>
                <a href="../certificate-view?uid=<?php echo urlencode($sample_cert_uid); ?>" target="_blank" class="btn btn-outline-dark rounded-pill px-3 fw-bold btn-sm">
                    <i class="fa fa-eye me-1"></i> View Live Sample
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="builderForm">
        <!-- Serialized Custom Fields Data -->
        <input type="hidden" name="custom_fields_json" id="customFieldsJson" value="<?php echo htmlspecialchars(json_encode($custom_fields, JSON_UNESCAPED_UNICODE)); ?>">

    <div class="row g-4">
        <!-- ================= LEFT COLUMN: VISUAL CANVAS WORKSPACE ================= -->
        <div class="col-xl-7">
            <div class="admin-card p-4 sticky-top" style="top: 80px; z-index: 10;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold small">
                        <i class="fa fa-expand me-1"></i> Canvas Auto-Sized: <?php echo "{$bg_width} × {$bg_height}px ({$bg_aspect})"; ?>
                    </span>
                    <span class="badge bg-light text-muted border small fw-semibold">
                        <i class="fa fa-hand-back-fist me-1"></i> Click to Select • Drag to Move • Drag Dot to Resize
                    </span>
                </div>

                <!-- Canvas Visual Viewport with Auto Aspect Ratio -->
                <div class="canvas-viewport-container">
                    <div class="canvas-frame-box" id="liveCanvas">
                        <!-- Border Frame (Optional) -->
                        <div class="canvas-border-frame" id="canvasBorderFrame" style="<?php echo $c_show_border ? '' : 'display:none;'; ?>">
                            <div class="canvas-border-inner"></div>
                        </div>

                        <!-- 1. Layer: Org Title / Logo -->
                        <div class="canvas-layer" id="layerOrg" data-id="org" style="top: <?php echo $pos_org_top; ?>%; left: <?php echo $pos_org_left; ?>%; <?php echo $show_org ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Institute Header</span>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($c_show_logo): ?>
                                    <?php if (!empty($cert_logo_img)): ?>
                                        <img src="../<?php echo htmlspecialchars($cert_logo_img); ?>" id="canvasLogoImg" style="max-height: <?php echo $c_logo_size; ?>px;">
                                    <?php else: ?>
                                        <i class="fa fa-graduation-cap text-warning fs-5" id="canvasLogoIcon"></i>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <span id="canvasOrgTitle" style="
                                    font-family: <?php echo $c_org_font === 'playfair' ? "'Playfair Display', serif" : ($c_org_font === 'cinzel' ? "'Cinzel', serif" : "'Plus Jakarta Sans', sans-serif"); ?>;
                                    font-size: <?php echo $c_org_size; ?>px;
                                    color: <?php echo $c_org_color; ?>;
                                    font-weight: 800;
                                    letter-spacing: 2px;
                                "><?php echo htmlspecialchars($c_org_title); ?></span>
                            </div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('org', -1)" title="Decrease Size"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('org', 1)" title="Increase Size"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('org')" title="Delete/Hide Element"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="org"></div>
                        </div>

                        <!-- 2. Layer: Heading & Subheading -->
                        <div class="canvas-layer" id="layerHeading" data-id="heading" style="top: <?php echo $pos_heading_top; ?>%; left: <?php echo $pos_heading_left; ?>%; <?php echo $show_heading ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Certificate Heading</span>
                            <div>
                                <div id="canvasHeadingText" style="
                                    font-family: <?php echo $c_heading_font === 'cinzel' ? "'Cinzel', serif" : ($c_heading_font === 'jakarta' ? "'Plus Jakarta Sans', sans-serif" : "'Playfair Display', serif"); ?>;
                                    font-size: <?php echo $c_heading_size; ?>px;
                                    color: <?php echo $c_heading_color; ?>;
                                    font-weight: 800;
                                    letter-spacing: 4px;
                                    text-transform: uppercase;
                                    line-height: 1;
                                "><?php echo htmlspecialchars($c_heading_text); ?></div>
                                <div id="canvasHeadingSub" style="
                                    font-family: 'Plus Jakarta Sans', sans-serif;
                                    font-size: 12px;
                                    color: <?php echo $c_heading_sub_color; ?>;
                                    font-weight: 700;
                                    letter-spacing: 2.5px;
                                    text-transform: uppercase;
                                    margin-top: 2px;
                                "><?php echo htmlspecialchars($c_heading_sub); ?></div>
                            </div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('heading', -2)" title="Decrease Size"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('heading', 2)" title="Increase Size"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('heading')" title="Delete/Hide Element"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="heading"></div>
                        </div>

                        <!-- 3. Layer: Presentation Lead -->
                        <div class="canvas-layer" id="layerPresent" data-id="present" style="top: <?php echo $pos_present_top; ?>%; left: <?php echo $pos_present_left; ?>%; <?php echo $show_present ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Lead Wording</span>
                            <div id="canvasPresentText" style="
                                font-size: <?php echo $c_present_size; ?>px;
                                color: <?php echo $c_present_color; ?>;
                                letter-spacing: 2px;
                                text-transform: uppercase;
                                font-weight: 600;
                            "><?php echo htmlspecialchars($c_present_text); ?></div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('present', -1)"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('present', 1)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('present')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="present"></div>
                        </div>

                        <!-- 4. Layer: Recipient Name -->
                        <div class="canvas-layer" id="layerStudent" data-id="student" style="top: <?php echo $pos_student_top; ?>%; left: <?php echo $pos_student_left; ?>%; <?php echo $show_student ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Student Full Name</span>
                            <div id="canvasStudentName" style="
                                font-family: <?php echo $c_student_font === 'alex_brush' ? "'Alex Brush', cursive" : ($c_student_font === 'playfair' ? "'Playfair Display', serif" : "'Great Vibes', cursive"); ?>;
                                font-size: <?php echo $c_student_size; ?>px;
                                color: <?php echo $c_student_color; ?>;
                                line-height: 1.1;
                            ">John Doe (Student Name)</div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('student', -2)"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('student', 2)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('student')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="student"></div>
                        </div>

                        <!-- 5. Layer: Student Profile Photo -->
                        <div class="canvas-layer" id="layerPhoto" data-id="photo" style="top: <?php echo $pos_photo_top; ?>%; left: <?php echo $pos_photo_left; ?>%; <?php echo $show_photo ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Student Photo</span>
                            <div class="student-photo-preview shape-<?php echo htmlspecialchars($c_photo_shape); ?>" id="canvasPhotoBox" style="width: <?php echo $c_photo_size; ?>px; height: <?php echo intval($c_photo_size * 1.2); ?>px;">
                                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150" style="width: 100%; height: 100%; object-fit: cover;" alt="Student Photo">
                            </div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('photo', -4)"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('photo', 4)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('photo')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="photo"></div>
                        </div>

                        <!-- 6. Layer: Body Text -->
                        <div class="canvas-layer" id="layerBody" data-id="body" style="top: <?php echo $pos_body_top; ?>%; left: <?php echo $pos_body_left; ?>%; max-width: <?php echo $c_body_max_width; ?>px; <?php echo $show_body ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Body Description</span>
                            <div id="canvasBodyText" style="
                                font-size: <?php echo $c_body_size; ?>px;
                                color: <?php echo $c_body_color; ?>;
                                line-height: 1.5;
                                white-space: normal;
                            "><?php echo htmlspecialchars($c_body_text); ?></div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('body', -1)"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('body', 1)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('body')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="body"></div>
                        </div>

                        <!-- 7. Layer: Course Title -->
                        <div class="canvas-layer" id="layerCourse" data-id="course" style="top: <?php echo $pos_course_top; ?>%; left: <?php echo $pos_course_left; ?>%; <?php echo $show_course ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Course Title</span>
                            <div id="canvasCourseTitle" style="
                                font-family: <?php echo $c_course_font === 'cinzel' ? "'Cinzel', serif" : ($c_course_font === 'playfair' ? "'Playfair Display', serif" : "'Plus Jakarta Sans', sans-serif"); ?>;
                                font-size: <?php echo $c_course_size; ?>px;
                                color: <?php echo $c_course_color; ?>;
                                font-weight: 800;
                            ">"Diploma in Computer Applications (DCA)"</div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('course', -1)"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('course', 1)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('course')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="course"></div>
                        </div>

                        <!-- 8. Layer: Date -->
                        <div class="canvas-layer" id="layerDate" data-id="date" style="top: <?php echo $pos_date_top; ?>%; left: <?php echo $pos_date_left; ?>%; <?php echo $show_date ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Issue Date</span>
                            <div class="text-center">
                                <div id="canvasDateVal" style="font-weight: 700; font-size: <?php echo $c_date_size; ?>px; color: <?php echo $c_date_color; ?>;"><?php echo date('d F Y'); ?></div>
                                <div id="canvasDateLabel" style="font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; border-top: 1.5px solid #c9a84c; padding-top: 4px; margin-top: 2px; color: #94a3b8;"><?php echo htmlspecialchars($c_date_label); ?></div>
                            </div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('date', -1)"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('date', 1)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('date')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="date"></div>
                        </div>

                        <!-- 9. Layer: Seal Stamp -->
                        <div class="canvas-layer" id="layerSeal" data-id="seal" style="top: <?php echo $pos_seal_top; ?>%; left: <?php echo $pos_seal_left; ?>%; <?php echo $c_show_seal ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Official Seal</span>
                            <?php if (!empty($cert_seal_img)): ?>
                                <img src="../<?php echo htmlspecialchars($cert_seal_img); ?>" id="canvasSealImg" style="max-height: <?php echo $c_seal_size; ?>px; max-width: <?php echo $c_seal_size; ?>px; object-fit: contain;">
                            <?php else: ?>
                                <div id="canvasSealIcon" class="d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: <?php echo $c_seal_size; ?>px; height: <?php echo $c_seal_size; ?>px; background: linear-gradient(135deg, #c9a84c, #a17d2e); color: #fff;">
                                    <i class="fa fa-award fs-4"></i>
                                </div>
                            <?php endif; ?>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('seal')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="seal"></div>
                        </div>

                        <!-- 10. Layer: QR Code -->
                        <div class="canvas-layer" id="layerQr" data-id="qr" style="top: <?php echo $pos_qr_top; ?>%; left: <?php echo $pos_qr_left; ?>%; <?php echo $c_show_qr ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">QR Code</span>
                            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px; display: inline-block;">
                                <img id="canvasQrImg" src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://lkvmbihar.in/courses/certificate-verify" style="width: <?php echo $c_qr_size; ?>px; height: <?php echo $c_qr_size; ?>px; display: block;" alt="QR Code">
                            </div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('qr')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="qr"></div>
                        </div>

                        <!-- 11. Layer: Signature & Authority -->
                        <div class="canvas-layer" id="layerSig" data-id="sig" style="top: <?php echo $pos_sig_top; ?>%; left: <?php echo $pos_sig_left; ?>%; <?php echo $show_sig ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Signatory & Signature</span>
                            <div class="text-center">
                                <?php if (!empty($cert_signature_img)): ?>
                                    <img src="../<?php echo htmlspecialchars($cert_signature_img); ?>" id="canvasSigImg" style="max-height: <?php echo $c_sig_img_size; ?>px; max-width: 140px; object-fit: contain; margin-bottom: 2px;">
                                <?php endif; ?>
                                <div id="canvasSigName" style="font-weight: 700; font-size: <?php echo $c_sig_size; ?>px; color: <?php echo $c_sig_color; ?>;"><?php echo htmlspecialchars($c_sig_name); ?></div>
                                <div id="canvasSigTitle" style="font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; border-top: 1.5px solid #c9a84c; padding-top: 4px; margin-top: 2px; color: #94a3b8;"><?php echo htmlspecialchars($c_sig_title); ?></div>
                            </div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('sig', -1)"><i class="fa fa-minus"></i></button>
                                <button type="button" class="layer-quick-btn" onclick="adjustLayerFontSize('sig', 1)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('sig')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="sig"></div>
                        </div>

                        <!-- 12. Layer: Credential UID Bar -->
                        <div class="canvas-layer" id="layerUid" data-id="uid" style="top: <?php echo $pos_uid_top; ?>%; left: <?php echo $pos_uid_left; ?>%; <?php echo $show_uid ? '' : 'display:none;'; ?>">
                            <span class="layer-badge">Credential UID Bar</span>
                            <div style="font-size: 10px; color: #64748b; font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: 1.5px;">
                                CREDENTIAL ID: <?php echo htmlspecialchars($sample_cert_uid); ?> • VERIFIED
                            </div>
                            <div class="layer-quick-tools">
                                <button type="button" class="layer-quick-btn btn-delete" onclick="deleteLayer('uid')"><i class="fa fa-trash"></i></button>
                            </div>
                            <div class="resize-handle" data-layer="uid"></div>
                        </div>

                        <!-- Custom Dynamically Added Fields Container -->
                        <div id="customFieldsCanvasContainer">
                            <?php foreach ($custom_fields as $cf): ?>
                                <?php if (!empty($cf['id'])): ?>
                                    <div class="canvas-layer custom-dynamic-layer" id="layer_<?php echo htmlspecialchars($cf['id']); ?>" data-id="<?php echo htmlspecialchars($cf['id']); ?>" style="top: <?php echo floatval($cf['top'] ?? 50); ?>%; left: <?php echo floatval($cf['left'] ?? 50); ?>%;">
                                        <span class="layer-badge"><?php echo htmlspecialchars($cf['label'] ?? 'Custom Field'); ?></span>
                                        <div class="custom-field-text" style="
                                            font-family: <?php echo ($cf['fontFamily'] ?? '') === 'playfair' ? "'Playfair Display', serif" : (($cf['fontFamily'] ?? '') === 'cinzel' ? "'Cinzel', serif" : (($cf['fontFamily'] ?? '') === 'great_vibes' ? "'Great Vibes', cursive" : "'Plus Jakarta Sans', sans-serif")); ?>;
                                            font-size: <?php echo intval($cf['fontSize'] ?? 14); ?>px;
                                            color: <?php echo htmlspecialchars($cf['color'] ?? '#1e3a5f'); ?>;
                                            font-weight: <?php echo htmlspecialchars($cf['fontWeight'] ?? '700'); ?>;
                                        "><?php echo htmlspecialchars(lms_format_sample_tags($cf['text'] ?? 'Custom Field', $sample_cert_uid)); ?></div>
                                        <div class="layer-quick-tools">
                                            <button type="button" class="layer-quick-btn" onclick="openInspectorForLayer('<?php echo htmlspecialchars($cf['id']); ?>')" title="Inspect & Edit"><i class="fa fa-sliders"></i></button>
                                            <button type="button" class="layer-quick-btn" onclick="adjustCustomFieldFontSize('<?php echo htmlspecialchars($cf['id']); ?>', -1)"><i class="fa fa-minus"></i></button>
                                            <button type="button" class="layer-quick-btn" onclick="adjustCustomFieldFontSize('<?php echo htmlspecialchars($cf['id']); ?>', 1)"><i class="fa fa-plus"></i></button>
                                            <button type="button" class="layer-quick-btn btn-delete" onclick="deleteCustomField('<?php echo htmlspecialchars($cf['id']); ?>')"><i class="fa fa-trash"></i></button>
                                        </div>
                                        <div class="resize-handle" data-layer="<?php echo htmlspecialchars($cf['id']); ?>"></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted"><i class="fa fa-info-circle me-1"></i> Positions and sizes are saved automatically with responsive high-DPI scaling.</small>
                    <button type="submit" name="save_canvas_builder" class="btn btn-primary rounded-pill px-5 fw-bold shadow">
                        <i class="fa fa-save me-2"></i> Save Certificate Design & Layout
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= RIGHT COLUMN: FIELD POSITION & STYLE CONTROLS ================= -->
        <div class="col-xl-5">
            <!-- Hidden Position Inputs for Dragged Coordinates -->
            <input type="hidden" name="pos_org_top" id="inputPosOrgTop" value="<?php echo $pos_org_top; ?>">
            <input type="hidden" name="pos_org_left" id="inputPosOrgLeft" value="<?php echo $pos_org_left; ?>">

            <input type="hidden" name="pos_heading_top" id="inputPosHeadingTop" value="<?php echo $pos_heading_top; ?>">
            <input type="hidden" name="pos_heading_left" id="inputPosHeadingLeft" value="<?php echo $pos_heading_left; ?>">

            <input type="hidden" name="pos_present_top" id="inputPosPresentTop" value="<?php echo $pos_present_top; ?>">
            <input type="hidden" name="pos_present_left" id="inputPosPresentLeft" value="<?php echo $pos_present_left; ?>">

            <input type="hidden" name="pos_student_top" id="inputPosStudentTop" value="<?php echo $pos_student_top; ?>">
            <input type="hidden" name="pos_student_left" id="inputPosStudentLeft" value="<?php echo $pos_student_left; ?>">

            <input type="hidden" name="pos_body_top" id="inputPosBodyTop" value="<?php echo $pos_body_top; ?>">
            <input type="hidden" name="pos_body_left" id="inputPosBodyLeft" value="<?php echo $pos_body_left; ?>">

            <input type="hidden" name="pos_course_top" id="inputPosCourseTop" value="<?php echo $pos_course_top; ?>">
            <input type="hidden" name="pos_course_left" id="inputPosCourseLeft" value="<?php echo $pos_course_left; ?>">

            <input type="hidden" name="pos_date_top" id="inputPosDateTop" value="<?php echo $pos_date_top; ?>">
            <input type="hidden" name="pos_date_left" id="inputPosDateLeft" value="<?php echo $pos_date_left; ?>">

            <input type="hidden" name="pos_seal_top" id="inputPosSealTop" value="<?php echo $pos_seal_top; ?>">
            <input type="hidden" name="pos_seal_left" id="inputPosSealLeft" value="<?php echo $pos_seal_left; ?>">

            <input type="hidden" name="pos_qr_top" id="inputPosQrTop" value="<?php echo $pos_qr_top; ?>">
            <input type="hidden" name="pos_qr_left" id="inputPosQrLeft" value="<?php echo $pos_qr_left; ?>">

            <input type="hidden" name="pos_sig_top" id="inputPosSigTop" value="<?php echo $pos_sig_top; ?>">
            <input type="hidden" name="pos_sig_left" id="inputPosSigLeft" value="<?php echo $pos_sig_left; ?>">

            <input type="hidden" name="pos_photo_top" id="inputPosPhotoTop" value="<?php echo $pos_photo_top; ?>">
            <input type="hidden" name="pos_photo_left" id="inputPosPhotoLeft" value="<?php echo $pos_photo_left; ?>">

            <input type="hidden" name="pos_uid_top" id="inputPosUidTop" value="<?php echo $pos_uid_top; ?>">
            <input type="hidden" name="pos_uid_left" id="inputPosUidLeft" value="<?php echo $pos_uid_left; ?>">

            <!-- Hidden Inputs to Persist All Element Sizes, Fonts & Colors -->
            <input type="hidden" name="cfg_org_size" id="inputCfgOrgSize" value="<?php echo $c_org_size; ?>">
            <input type="hidden" name="cfg_org_font" id="inputCfgOrgFont" value="<?php echo htmlspecialchars($c_org_font); ?>">
            <input type="hidden" name="cfg_org_color" id="inputCfgOrgColor" value="<?php echo htmlspecialchars($c_org_color); ?>">
            <input type="hidden" name="cfg_logo_size" id="inputCfgLogoSize" value="<?php echo $c_logo_size; ?>">

            <input type="hidden" name="cfg_heading_text" id="inputCfgHeadingText" value="<?php echo htmlspecialchars($c_heading_text); ?>">
            <input type="hidden" name="cfg_heading_sub" id="inputCfgHeadingSub" value="<?php echo htmlspecialchars($c_heading_sub); ?>">
            <input type="hidden" name="cfg_heading_size" id="inputCfgHeadingSize" value="<?php echo $c_heading_size; ?>">
            <input type="hidden" name="cfg_heading_font" id="inputCfgHeadingFont" value="<?php echo htmlspecialchars($c_heading_font); ?>">
            <input type="hidden" name="cfg_heading_color" id="inputCfgHeadingColor" value="<?php echo htmlspecialchars($c_heading_color); ?>">
            <input type="hidden" name="cfg_heading_sub_color" id="inputCfgHeadingSubColor" value="<?php echo htmlspecialchars($c_heading_sub_color); ?>">

            <input type="hidden" name="cfg_present_text" id="inputCfgPresentText" value="<?php echo htmlspecialchars($c_present_text); ?>">
            <input type="hidden" name="cfg_present_size" id="inputCfgPresentSize" value="<?php echo $c_present_size; ?>">
            <input type="hidden" name="cfg_present_color" id="inputCfgPresentColor" value="<?php echo htmlspecialchars($c_present_color); ?>">

            <input type="hidden" name="cfg_student_size" id="inputCfgStudentSize" value="<?php echo $c_student_size; ?>">
            <input type="hidden" name="cfg_student_font" id="inputCfgStudentFont" value="<?php echo htmlspecialchars($c_student_font); ?>">
            <input type="hidden" name="cfg_student_color" id="inputCfgStudentColor" value="<?php echo htmlspecialchars($c_student_color); ?>">

            <input type="hidden" name="cfg_photo_size" id="inputCfgPhotoSize" value="<?php echo $c_photo_size; ?>">
            <input type="hidden" name="cfg_photo_shape" id="inputCfgPhotoShape" value="<?php echo htmlspecialchars($c_photo_shape); ?>">

            <input type="hidden" name="cfg_body_text" id="inputCfgBodyText" value="<?php echo htmlspecialchars($c_body_text); ?>">
            <input type="hidden" name="cfg_body_size" id="inputCfgBodySize" value="<?php echo $c_body_size; ?>">
            <input type="hidden" name="cfg_body_color" id="inputCfgBodyColor" value="<?php echo htmlspecialchars($c_body_color); ?>">
            <input type="hidden" name="cfg_body_max_width" id="inputCfgBodyMaxWidth" value="<?php echo $c_body_max_width; ?>">

            <input type="hidden" name="cfg_course_size" id="inputCfgCourseSize" value="<?php echo $c_course_size; ?>">
            <input type="hidden" name="cfg_course_font" id="inputCfgCourseFont" value="<?php echo htmlspecialchars($c_course_font); ?>">
            <input type="hidden" name="cfg_course_color" id="inputCfgCourseColor" value="<?php echo htmlspecialchars($c_course_color); ?>">

            <input type="hidden" name="cfg_date_label" id="inputCfgDateLabel" value="<?php echo htmlspecialchars($c_date_label); ?>">
            <input type="hidden" name="cfg_date_size" id="inputCfgDateSize" value="<?php echo $c_date_size; ?>">
            <input type="hidden" name="cfg_date_color" id="inputCfgDateColor" value="<?php echo htmlspecialchars($c_date_color); ?>">

            <input type="hidden" name="cfg_seal_size" id="inputCfgSealSize" value="<?php echo $c_seal_size; ?>">
            <input type="hidden" name="cfg_qr_size" id="inputCfgQrSize" value="<?php echo $c_qr_size; ?>">

            <input type="hidden" name="cfg_sig_size" id="inputCfgSigSize" value="<?php echo $c_sig_size; ?>">
            <input type="hidden" name="cfg_sig_color" id="inputCfgSigColor" value="<?php echo htmlspecialchars($c_sig_color); ?>">
            <input type="hidden" name="cfg_sig_img_size" id="inputCfgSigImgSize" value="<?php echo $c_sig_img_size; ?>">

            <div class="admin-card p-4 mb-4">
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button type="button" class="builder-nav-tab active" data-tab="tab-bg-assets"><i class="fa fa-image me-1"></i> Background</button>
                    <button type="button" class="builder-nav-tab" data-tab="tab-element-inspector"><i class="fa fa-sliders me-1"></i> Selected Element</button>
                    <button type="button" class="builder-nav-tab" data-tab="tab-layers-list"><i class="fa fa-layer-group me-1"></i> All Layers</button>
                    <button type="button" class="builder-nav-tab" data-tab="tab-signatory-seal"><i class="fa fa-stamp me-1"></i> Assets & Sig</button>
                </div>

                <!-- TAB 1: BACKGROUND & ARTWORK -->
                <div class="builder-pane active" id="tab-bg-assets">
                    <h6 class="fw-800 text-dark mb-3 pb-2 border-bottom">1. Certificate Background Canvas</h6>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Upload Background Canvas Image</label>
                        <input type="file" name="cert_bg_file" id="inputCertBgFile" class="form-control rounded-3" accept="image/*">
                        <small class="text-muted">Upload any certificate border artwork (PNG/JPG/WebP). Canvas will automatically match its exact dimensions!</small>
                        <?php if (!empty($cert_custom_bg)): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 mt-2 bg-light rounded-3 border">
                                <span class="small text-muted text-truncate"><i class="fa fa-image text-primary me-1"></i> <?php echo basename($cert_custom_bg); ?></span>
                                <a href="certificate-templates?remove_bg=1" class="text-danger small fw-bold text-decoration-none" onclick="return confirm('Remove background image?')">Remove</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Canvas Color</label>
                            <input type="color" name="cfg_canvas_bg_color" id="inputCanvasBgColor" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($c_bg_color); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Preset Fallback Style</label>
                            <select name="cert_default_template" class="form-select">
                                <?php foreach ($templates as $k => $t): ?>
                                    <option value="<?php echo $k; ?>" <?php echo $default_template === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3 p-3 bg-light rounded-3 border">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="cfg_show_border" id="inputShowBorder" value="1" <?php echo $c_show_border ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-dark" for="inputShowBorder">
                            Show Built-in Golden Border Frame
                        </label>
                        <small class="text-muted d-block ps-5">Disable if your uploaded background canvas already contains borders.</small>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small fw-bold text-dark"><i class="fa fa-palette text-primary me-1"></i> Active System Default:</span>
                            <span class="badge bg-primary rounded-pill"><?php echo htmlspecialchars($templates[$default_template]['name'] ?? 'Classic Gold'); ?></span>
                        </div>
                        <p class="small text-muted mb-2">Want to use the built-in luxury certificate templates or reset this canvas layout to standard design?</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill fw-bold" onclick="switchMainView('gallery')">
                                <i class="fa fa-th-large me-1"></i> Browse Templates Gallery
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill fw-bold" onclick="resetCanvasToDefaultDesign()">
                                <i class="fa fa-rotate-left me-1"></i> Reset Canvas Layout
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: ELEMENT INSPECTOR (LIVE SIZE & STYLE FOR SELECTED ELEMENT) -->
                <div class="builder-pane" id="tab-element-inspector" style="display:none;">
                    <h6 class="fw-800 text-dark mb-3 pb-2 border-bottom d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-sliders text-primary me-2"></i>Element Inspector</span>
                        <span class="badge bg-primary rounded-pill px-3 py-1" id="inspectorActiveName">Select an element</span>
                    </h6>

                    <div id="inspectorContent">
                        <p class="text-muted small">Click any element on the canvas to inspect and edit its size, text, color, and font.</p>
                    </div>

                    <div id="inspectorControls" style="display: none;">
                        <div class="mb-3" id="inspectorTextGroup">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-muted mb-0">Text Content / Template</label>
                                <span class="badge bg-light text-primary border" id="inspectorTagsBadge" style="display: none;"><i class="fa fa-tags me-1"></i>Tags</span>
                            </div>
                            <input type="text" class="form-control" id="inspectorTextInput">

                            <!-- Dynamic Replacement Tags Chips -->
                            <div id="inspectorTagsHelper" class="mt-2" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="small text-muted fw-bold"><i class="fa fa-wand-magic-sparkles text-primary me-1"></i> Click to Insert Student Tag:</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{student_name}')" title="Student Full Name">{student_name}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{roll_no}')" title="Student Roll/Reg No">{roll_no}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{course_title}')" title="Course Title">{course_title}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{grade}')" title="Grade / Score">{grade}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{issue_date}')" title="Issue Date">{issue_date}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{cert_uid}')" title="Unique Certificate ID">{cert_uid}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{student_email}')" title="Student Email">{student_email}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{phone}')" title="Student Mobile No">{phone}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{duration}')" title="Course Duration">{duration}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{completion_percent}')" title="Completion %">{completion_percent}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{instructor_name}')" title="Instructor Name">{instructor_name}</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoInspector('{father_name}')" title="Father/Guardian Name">{father_name}</button>
                                </div>
                                <div class="p-2 bg-light rounded-2 border text-muted small" id="inspectorLiveSamplePreview">
                                    <strong class="text-primary"><i class="fa fa-eye me-1"></i>Sample:</strong> <span id="inspectorSamplePreviewText" class="text-dark fw-semibold"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold text-muted mb-0">Font Size / Box Scale</label>
                                <span class="badge bg-light text-dark border" id="inspectorSizeVal">24px</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-light btn-sm border" id="inspectorBtnSizeMinus"><i class="fa fa-minus"></i></button>
                                <input type="range" class="form-range flex-grow-1" id="inspectorSizeRange" min="8" max="150" step="1">
                                <button type="button" class="btn btn-light btn-sm border" id="inspectorBtnSizePlus"><i class="fa fa-plus"></i></button>
                            </div>
                        </div>

                        <div class="row g-2 mb-3" id="inspectorFontGroup">
                            <div class="col-8">
                                <label class="form-label small fw-bold text-muted">Font Family</label>
                                <select class="form-select form-select-sm" id="inspectorFontSelect">
                                    <option value="jakarta">Plus Jakarta Sans</option>
                                    <option value="playfair">Playfair Display (Serif)</option>
                                    <option value="cinzel">Cinzel Roman (Classic)</option>
                                    <option value="great_vibes">Great Vibes (Calligraphy)</option>
                                    <option value="alex_brush">Alex Brush (Calligraphy)</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold text-muted">Text Color</label>
                                <input type="color" class="form-control form-control-color w-100" id="inspectorColorInput">
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Top Position (%)</label>
                                <input type="number" step="0.5" class="form-control form-control-sm" id="inspectorTopInput">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted">Left Position (%)</label>
                                <input type="number" step="0.5" class="form-control form-control-sm" id="inspectorLeftInput">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" id="inspectorBtnDelete">
                                <i class="fa fa-trash me-1"></i> Delete Element
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="centerActiveLayerHorizontally()" title="Center this element horizontally (Left: 50%)">
                                <i class="fa fa-arrows-left-right-to-line me-1"></i> Center (50%)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: ALL LAYERS & RESTORE LIST -->
                <div class="builder-pane" id="tab-layers-list" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h6 class="fw-800 text-dark mb-0">3. Layers & Field Manager</h6>
                        <span class="badge bg-light text-muted border small" id="totalLayersCount">12 Active Layers</span>
                    </div>

                    <!-- Built-in System Elements -->
                    <div class="field-layer-row" data-target="photo">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-image-portrait text-success me-2"></i>Student Profile Photo</strong>
                            <small class="text-muted">Passport photo box</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_photo" id="toggle_photo" value="1" <?php echo $show_photo ? 'checked' : ''; ?> onchange="toggleLayerVisibility('photo', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="org">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-graduation-cap text-primary me-2"></i>Institute Title / Logo</strong>
                            <small class="text-muted">Header branding</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_org" id="toggle_org" value="1" <?php echo $show_org ? 'checked' : ''; ?> onchange="toggleLayerVisibility('org', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="heading">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-heading text-primary me-2"></i>Certificate Title / Heading</strong>
                            <small class="text-muted">"Certificate of Completion"</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_heading" id="toggle_heading" value="1" <?php echo $show_heading ? 'checked' : ''; ?> onchange="toggleLayerVisibility('heading', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="present">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-quote-left text-primary me-2"></i>Presentation Lead</strong>
                            <small class="text-muted">"This is proudly presented to"</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_present" id="toggle_present" value="1" <?php echo $show_present ? 'checked' : ''; ?> onchange="toggleLayerVisibility('present', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="student">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-user-graduate text-primary me-2"></i>Student Full Name</strong>
                            <small class="text-muted">Recipient name calligraphy</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_student" id="toggle_student" value="1" <?php echo $show_student ? 'checked' : ''; ?> onchange="toggleLayerVisibility('student', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="body">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-align-left text-primary me-2"></i>Body Description</strong>
                            <small class="text-muted">Citation paragraph</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_body" id="toggle_body" value="1" <?php echo $show_body ? 'checked' : ''; ?> onchange="toggleLayerVisibility('body', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="course">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-book text-primary me-2"></i>Course Title</strong>
                            <small class="text-muted">Program title</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_course" id="toggle_course" value="1" <?php echo $show_course ? 'checked' : ''; ?> onchange="toggleLayerVisibility('course', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="date">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-calendar-day text-primary me-2"></i>Issue Date</strong>
                            <small class="text-muted">Date badge</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_date" id="toggle_date" value="1" <?php echo $show_date ? 'checked' : ''; ?> onchange="toggleLayerVisibility('date', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="seal">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-award text-warning me-2"></i>Official Seal Stamp</strong>
                            <small class="text-muted">Embossed medal</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="cfg_show_seal" id="toggle_seal" value="1" <?php echo $c_show_seal ? 'checked' : ''; ?> onchange="toggleLayerVisibility('seal', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="qr">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-qrcode text-info me-2"></i>QR Code Verification</strong>
                            <small class="text-muted">Online verification scanner</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="cfg_show_qr" id="toggle_qr" value="1" <?php echo $c_show_qr ? 'checked' : ''; ?> onchange="toggleLayerVisibility('qr', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="sig">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-signature text-primary me-2"></i>Signatory Authority</strong>
                            <small class="text-muted">Name & signature</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_sig" id="toggle_sig" value="1" <?php echo $show_sig ? 'checked' : ''; ?> onchange="toggleLayerVisibility('sig', this.checked)">
                        </div>
                    </div>

                    <div class="field-layer-row" data-target="uid">
                        <div>
                            <strong class="small d-block text-dark"><i class="fa fa-fingerprint text-secondary me-2"></i>Credential UID Bar</strong>
                            <small class="text-muted">Unique ID & portal URL</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input m-0" type="checkbox" name="show_uid" id="toggle_uid" value="1" <?php echo $show_uid ? 'checked' : ''; ?> onchange="toggleLayerVisibility('uid', this.checked)">
                        </div>
                    </div>

                    <div class="mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-bold mb-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddCustomText">
                            <i class="fa fa-tags me-1"></i> + Add Custom Text with Tags
                        </button>
                    </div>

                    <!-- Custom Fields List Container -->
                    <div id="customFieldsSidebarList">
                        <!-- Rendered by JS -->
                    </div>
                </div>

                <!-- TAB 4: ASSETS, SIGNATURES & SEALS -->
                <div class="builder-pane" id="tab-signatory-seal" style="display:none;">
                    <h6 class="fw-800 text-dark mb-3 pb-2 border-bottom">4. Signatures & Official Assets</h6>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Institute Name</label>
                        <input type="text" name="cfg_org_title" id="inputOrgTitle" class="form-control" value="<?php echo htmlspecialchars($c_org_title); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Upload Logo Image</label>
                        <input type="file" name="cert_logo_file" class="form-control" accept="image/png,image/webp">
                        <?php if (!empty($cert_logo_img)): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 mt-2 bg-light rounded-3 border">
                                <img src="../<?php echo htmlspecialchars($cert_logo_img); ?>" style="max-height: 28px;">
                                <small class="text-muted">Logo Active</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Signatory Authority Name</label>
                        <input type="text" name="cfg_sig_name" id="inputSigName" class="form-control" value="<?php echo htmlspecialchars($c_sig_name); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Signatory Title / Designation</label>
                        <input type="text" name="cfg_sig_title" id="inputSigTitle" class="form-control" value="<?php echo htmlspecialchars($c_sig_title); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Upload Signature Image (PNG)</label>
                        <input type="file" name="cert_sig_file" class="form-control" accept="image/png,image/webp">
                        <?php if (!empty($cert_signature_img)): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 mt-2 bg-light rounded-3 border">
                                <img src="../<?php echo htmlspecialchars($cert_signature_img); ?>" style="max-height: 28px;">
                                <a href="certificate-templates?remove_sig=1" class="text-danger small fw-bold text-decoration-none" onclick="return confirm('Remove signature image?')">Remove</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Upload Seal / Stamp Image (PNG)</label>
                        <input type="file" name="cert_seal_file" class="form-control" accept="image/png,image/webp">
                        <?php if (!empty($cert_seal_img)): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 mt-2 bg-light rounded-3 border">
                                <img src="../<?php echo htmlspecialchars($cert_seal_img); ?>" style="max-height: 28px;">
                                <a href="certificate-templates?remove_seal=1" class="text-danger small fw-bold text-decoration-none" onclick="return confirm('Remove seal image?')">Remove</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
</div> <!-- /#studioViewSection -->

<!-- Modal: Add Custom Text with Dynamic Replacement Tags -->
<div class="modal fade" id="modalAddCustomText" tabindex="-1" aria-labelledby="modalAddCustomTextLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom px-4 py-3 bg-light rounded-top-4">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary p-2 rounded-3"><i class="fa fa-tags fs-5"></i></span>
                    <div>
                        <h5 class="modal-title fw-800 text-dark mb-0" id="modalAddCustomTextLabel">Add Custom Text with Student Data Tags</h5>
                        <small class="text-muted">Insert dynamic placeholders that automatically replace with actual student & course info.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Field Label / Identifier</label>
                    <input type="text" id="modalFieldLabel" class="form-control rounded-3" value="Student Roll & Grade" placeholder="e.g. Roll & Grade, Student Email, Note">
                    <small class="text-muted">Identifies this element in your layers manager</small>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-muted text-uppercase mb-0">Custom Text Content & Dynamic Tags</label>
                        <small class="text-primary fw-bold"><i class="fa fa-mouse-pointer me-1"></i>Click tag below to insert</small>
                    </div>
                    <textarea id="modalFieldText" class="form-control rounded-3 font-monospace" rows="3" placeholder="e.g. Roll No: {roll_no} • Grade: {grade}">Roll No: {roll_no} • Grade: {grade}</textarea>
                    
                    <!-- Dynamic Replacement Tags Chips -->
                    <div class="mt-2 p-3 bg-light rounded-3 border">
                        <span class="d-block small text-muted fw-bold mb-2"><i class="fa fa-wand-magic-sparkles text-primary me-1"></i> Click to Insert Student Data Tag:</span>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{student_name}')" title="Student Full Name">{student_name}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{roll_no}')" title="Student Roll/Registration No">{roll_no}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{course_title}')" title="Course Title">{course_title}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{grade}')" title="Student Grade / Division">{grade}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{issue_date}')" title="Issue Date">{issue_date}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{cert_uid}')" title="Certificate Unique ID">{cert_uid}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{student_email}')" title="Student Email Address">{student_email}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{phone}')" title="Student Contact Number">{phone}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{duration}')" title="Course Duration / Hours">{duration}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{completion_percent}')" title="Completion %">{completion_percent}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{instructor_name}')" title="Instructor / Faculty Name">{instructor_name}</button>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small" onclick="insertTagIntoModal('{father_name}')" title="Father / Guardian Name">{father_name}</button>
                        </div>
                        <div class="mt-2 p-2 bg-white rounded-2 border text-muted small" id="modalLivePreviewBox">
                            <strong class="text-primary"><i class="fa fa-eye me-1"></i>Live Real-Time Sample Preview:</strong>
                            <span id="modalLivePreviewText" class="text-dark fw-bold ms-1">Roll No: LKVM-0101 • Grade: A+ (Distinction)</span>
                        </div>
                    </div>
                </div>

                <!-- Typography & Styling -->
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Font Size</label>
                        <div class="input-group">
                            <input type="number" id="modalFontSize" class="form-control rounded-3" value="16" min="8" max="90">
                            <span class="input-group-text bg-white small">px</span>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Font Family</label>
                        <select id="modalFontFamily" class="form-select rounded-3">
                            <option value="jakarta" selected>Plus Jakarta Sans</option>
                            <option value="playfair">Playfair Display (Serif)</option>
                            <option value="cinzel">Cinzel Roman</option>
                            <option value="great_vibes">Great Vibes (Calligraphy)</option>
                            <option value="alex_brush">Alex Brush</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Font Weight</label>
                        <select id="modalFontWeight" class="form-select rounded-3">
                            <option value="400">Regular (400)</option>
                            <option value="600">Semi-Bold (600)</option>
                            <option value="700" selected>Bold (700)</option>
                            <option value="800">Extra Bold (800)</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Text Color</label>
                        <input type="color" id="modalFontColor" class="form-control form-control-color w-100 rounded-3" value="#1e3a5f">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-3 bg-light rounded-bottom-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="submitModalAddCustomText()">
                    <i class="fa fa-plus me-1"></i> Add to Certificate Canvas
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Free-Form Drag & Drop Engine + Visual Resizer + Custom Fields Engine -->
<script>
// State Store for Custom Dynamic Fields
let customFieldsState = <?php echo json_encode($custom_fields, JSON_UNESCAPED_UNICODE) ?: '[]'; ?>;

// Built-in Element Styles Store with direct two-way binding to form hidden inputs
const elementMeta = {
    photo: { 
        name: 'Student Profile Photo', 
        textEl: null, 
        textInputId: null,
        size: <?php echo $c_photo_size; ?>, 
        sizeInputId: 'inputCfgPhotoSize',
        font: 'jakarta', 
        fontInputId: null,
        color: '#c9a84c', 
        colorInputId: null,
        topId: 'inputPosPhotoTop', 
        leftId: 'inputPosPhotoLeft' 
    },
    org: { 
        name: 'Institute Header', 
        textEl: 'canvasOrgTitle', 
        textInputId: 'inputOrgTitle', 
        size: <?php echo $c_org_size; ?>, 
        sizeInputId: 'inputCfgOrgSize',
        font: '<?php echo $c_org_font; ?>', 
        fontInputId: 'inputCfgOrgFont',
        color: '<?php echo $c_org_color; ?>', 
        colorInputId: 'inputCfgOrgColor',
        topId: 'inputPosOrgTop', 
        leftId: 'inputPosOrgLeft' 
    },
    heading: { 
        name: 'Certificate Heading', 
        textEl: 'canvasHeadingText', 
        textInputId: 'inputCfgHeadingText', 
        size: <?php echo $c_heading_size; ?>, 
        sizeInputId: 'inputCfgHeadingSize',
        font: '<?php echo $c_heading_font; ?>', 
        fontInputId: 'inputCfgHeadingFont',
        color: '<?php echo $c_heading_color; ?>', 
        colorInputId: 'inputCfgHeadingColor',
        topId: 'inputPosHeadingTop', 
        leftId: 'inputPosHeadingLeft' 
    },
    present: { 
        name: 'Lead Wording', 
        textEl: 'canvasPresentText', 
        textInputId: 'inputCfgPresentText', 
        size: <?php echo $c_present_size; ?>, 
        sizeInputId: 'inputCfgPresentSize',
        font: 'jakarta', 
        fontInputId: null,
        color: '<?php echo $c_present_color; ?>', 
        colorInputId: 'inputCfgPresentColor',
        topId: 'inputPosPresentTop', 
        leftId: 'inputPosPresentLeft' 
    },
    student: { 
        name: 'Student Full Name', 
        textEl: 'canvasStudentName', 
        textInputId: null, 
        size: <?php echo $c_student_size; ?>, 
        sizeInputId: 'inputCfgStudentSize',
        font: '<?php echo $c_student_font; ?>', 
        fontInputId: 'inputCfgStudentFont',
        color: '<?php echo $c_student_color; ?>', 
        colorInputId: 'inputCfgStudentColor',
        topId: 'inputPosStudentTop', 
        leftId: 'inputPosStudentLeft' 
    },
    body: { 
        name: 'Body Description', 
        textEl: 'canvasBodyText', 
        textInputId: 'inputCfgBodyText', 
        size: <?php echo $c_body_size; ?>, 
        sizeInputId: 'inputCfgBodySize',
        font: 'jakarta', 
        fontInputId: null,
        color: '<?php echo $c_body_color; ?>', 
        colorInputId: 'inputCfgBodyColor',
        topId: 'inputPosBodyTop', 
        leftId: 'inputPosBodyLeft' 
    },
    course: { 
        name: 'Course Title', 
        textEl: 'canvasCourseTitle', 
        textInputId: null, 
        size: <?php echo $c_course_size; ?>, 
        sizeInputId: 'inputCfgCourseSize',
        font: '<?php echo $c_course_font; ?>', 
        fontInputId: 'inputCfgCourseFont',
        color: '<?php echo $c_course_color; ?>', 
        colorInputId: 'inputCfgCourseColor',
        topId: 'inputPosCourseTop', 
        leftId: 'inputPosCourseLeft' 
    },
    date: { 
        name: 'Issue Date', 
        textEl: 'canvasDateLabel', 
        textInputId: 'inputCfgDateLabel', 
        size: <?php echo $c_date_size; ?>, 
        sizeInputId: 'inputCfgDateSize',
        font: 'jakarta', 
        fontInputId: null,
        color: '<?php echo $c_date_color; ?>', 
        colorInputId: 'inputCfgDateColor',
        topId: 'inputPosDateTop', 
        leftId: 'inputPosDateLeft' 
    },
    seal: { 
        name: 'Official Seal Stamp', 
        textEl: null, 
        textInputId: null, 
        size: <?php echo $c_seal_size; ?>, 
        sizeInputId: 'inputCfgSealSize',
        font: 'jakarta', 
        fontInputId: null,
        color: '#c9a84c', 
        colorInputId: null,
        topId: 'inputPosSealTop', 
        leftId: 'inputPosSealLeft' 
    },
    qr: { 
        name: 'QR Code', 
        textEl: null, 
        textInputId: null, 
        size: <?php echo $c_qr_size; ?>, 
        sizeInputId: 'inputCfgQrSize',
        font: 'jakarta', 
        fontInputId: null,
        color: '#000000', 
        colorInputId: null,
        topId: 'inputPosQrTop', 
        leftId: 'inputPosQrLeft' 
    },
    sig: { 
        name: 'Signatory Authority', 
        textEl: 'canvasSigName', 
        textInputId: 'inputSigName', 
        size: <?php echo $c_sig_size; ?>, 
        sizeInputId: 'inputCfgSigSize',
        font: 'jakarta', 
        fontInputId: null,
        color: '<?php echo $c_sig_color; ?>', 
        colorInputId: 'inputCfgSigColor',
        topId: 'inputPosSigTop', 
        leftId: 'inputPosSigLeft' 
    },
    uid: { 
        name: 'Credential UID Bar', 
        textEl: null, 
        textInputId: null, 
        size: 10, 
        sizeInputId: null,
        font: 'jakarta', 
        fontInputId: null,
        color: '#64748b', 
        colorInputId: null,
        topId: 'inputPosUidTop', 
        leftId: 'inputPosUidLeft' 
    }
};

let currentSelectedLayerId = null;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Tab Navigation
    const navTabs = document.querySelectorAll('.builder-nav-tab');
    const panes = document.querySelectorAll('.builder-pane');

    navTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            navTabs.forEach(t => t.classList.remove('active'));
            panes.forEach(p => p.style.display = 'none');

            tab.classList.add('active');
            const targetPane = document.getElementById(tab.dataset.tab);
            if (targetPane) targetPane.style.display = 'block';
        });
    });

    // 2. Drag & Drop Engine
    initDragAndDrop();

    // 3. Render Custom Fields Sidebar List
    renderCustomFieldsSidebar();

    // 4. Background Image Upload Auto-Aspect Ratio Adjuster
    const bgFileInput = document.getElementById('inputCertBgFile');
    const canvas = document.getElementById('liveCanvas');
    if (bgFileInput && canvas) {
        bgFileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    canvas.style.backgroundImage = `url('${e.target.result}')`;
                    const img = new Image();
                    img.onload = function() {
                        canvas.style.aspectRatio = `${this.naturalWidth} / ${this.naturalHeight}`;
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // 5. Auto-Center Layout Preset
    const btnAutoCenter = document.getElementById('btnAutoCenter');
    if (btnAutoCenter) {
        btnAutoCenter.addEventListener('click', () => {
            const defaults = [
                { id: 'layerPhoto', top: 18, left: 84, hiddenTop: 'inputPosPhotoTop', hiddenLeft: 'inputPosPhotoLeft' },
                { id: 'layerOrg', top: 8, left: 50, hiddenTop: 'inputPosOrgTop', hiddenLeft: 'inputPosOrgLeft' },
                { id: 'layerHeading', top: 21, left: 50, hiddenTop: 'inputPosHeadingTop', hiddenLeft: 'inputPosHeadingLeft' },
                { id: 'layerPresent', top: 36, left: 50, hiddenTop: 'inputPosPresentTop', hiddenLeft: 'inputPosPresentLeft' },
                { id: 'layerStudent', top: 46, left: 50, hiddenTop: 'inputPosStudentTop', hiddenLeft: 'inputPosStudentLeft' },
                { id: 'layerBody', top: 58, left: 50, hiddenTop: 'inputPosBodyTop', hiddenLeft: 'inputPosBodyLeft' },
                { id: 'layerCourse', top: 67, left: 50, hiddenTop: 'inputPosCourseTop', hiddenLeft: 'inputPosCourseLeft' },
                { id: 'layerDate', top: 81, left: 16, hiddenTop: 'inputPosDateTop', hiddenLeft: 'inputPosDateLeft' },
                { id: 'layerSeal', top: 79, left: 42, hiddenTop: 'inputPosSealTop', hiddenLeft: 'inputPosSealLeft' },
                { id: 'layerQr', top: 79, left: 58, hiddenTop: 'inputPosQrTop', hiddenLeft: 'inputPosQrLeft' },
                { id: 'layerSig', top: 81, left: 84, hiddenTop: 'inputPosSigTop', hiddenLeft: 'inputPosSigLeft' },
                { id: 'layerUid', top: 94, left: 50, hiddenTop: 'inputPosUidTop', hiddenLeft: 'inputPosUidLeft' }
            ];

            defaults.forEach(d => {
                const el = document.getElementById(d.id);
                if (el) {
                    el.style.top = d.top + '%';
                    el.style.left = d.left + '%';
                }
                const ht = document.getElementById(d.hiddenTop);
                const hl = document.getElementById(d.hiddenLeft);
                if (ht) ht.value = d.top;
                if (hl) hl.value = d.left;
            });
            alert('Layout auto-aligned to center balanced diploma format!');
        });
    }

    // 6. Modal Field Text live preview typing listener
    const modalFieldText = document.getElementById('modalFieldText');
    if (modalFieldText) {
        modalFieldText.addEventListener('input', updateModalLivePreview);
    }

    // 7. Inspector Listeners
    setupInspectorControls();
});

// Setup Dynamic Drag and Selection
function initDragAndDrop() {
    const canvas = document.getElementById('liveCanvas');
    const layers = document.querySelectorAll('.canvas-layer');
    let activeLayer = null;
    let isDragging = false;
    let isResizing = false;
    let startX = 0, startY = 0;
    let initialLeftPct = 0, initialTopPct = 0;
    let initialFontSize = 16;

    layers.forEach(layer => {
        // Double-click opens Element Inspector
        layer.ondblclick = (e) => {
            e.stopPropagation();
            openInspectorForLayer(layer.dataset.id);
        };

        // Click to select & drag
        layer.onmousedown = (e) => {
            if (e.target.closest('.layer-quick-tools')) return; // ignore toolbar clicks
            
            // Check if resize handle clicked
            if (e.target.classList.contains('resize-handle')) {
                e.preventDefault();
                e.stopPropagation();
                isResizing = true;
                activeLayer = layer;
                startX = e.clientX;
                startY = e.clientY;
                initialFontSize = getLayerFontSize(layer.dataset.id);
                document.addEventListener('mousemove', onResizeMove);
                document.addEventListener('mouseup', onMouseUp);
                return;
            }

            e.preventDefault();
            activeLayer = layer;
            isDragging = true;

            // Highlight selected layer
            selectLayer(layer.dataset.id);

            startX = e.clientX;
            startY = e.clientY;

            initialLeftPct = parseFloat(layer.style.left) || 50;
            initialTopPct = parseFloat(layer.style.top) || 50;

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        };
    });

    function onMouseMove(e) {
        if (!isDragging || !activeLayer) return;

        const rect = canvas.getBoundingClientRect();
        const deltaXPct = ((e.clientX - startX) / rect.width) * 100;
        const deltaYPct = ((e.clientY - startY) / rect.height) * 100;

        let newLeft = Math.round((initialLeftPct + deltaXPct) * 2) / 2;
        let newTop = Math.round((initialTopPct + deltaYPct) * 2) / 2;

        newLeft = Math.max(2, Math.min(98, newLeft));
        newTop = Math.max(2, Math.min(98, newTop));

        activeLayer.style.left = newLeft + '%';
        activeLayer.style.top = newTop + '%';

        const layerId = activeLayer.dataset.id;
        updateLayerCoordinates(layerId, newTop, newLeft);
    }

    function onResizeMove(e) {
        if (!isResizing || !activeLayer) return;

        const delta = (e.clientX - startX) + (e.clientY - startY);
        let newSize = Math.max(8, Math.min(160, Math.round(initialFontSize + delta / 3)));

        setLayerFontSize(activeLayer.dataset.id, newSize);
    }

    function onMouseUp() {
        isDragging = false;
        isResizing = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mousemove', onResizeMove);
        document.removeEventListener('mouseup', onMouseUp);
    }
}

// Select Layer
function selectLayer(layerId) {
    currentSelectedLayerId = layerId;
    document.querySelectorAll('.canvas-layer').forEach(l => l.classList.remove('active-selected'));
    
    const targetLayer = document.querySelector(`.canvas-layer[data-id="${layerId}"]`);
    if (targetLayer) targetLayer.classList.add('active-selected');

    // Highlight in sidebar layers list
    document.querySelectorAll('.field-layer-row').forEach(r => r.classList.remove('is-selected'));
    const sidebarRow = document.querySelector(`.field-layer-row[data-target="${layerId}"]`);
    if (sidebarRow) sidebarRow.classList.add('is-selected');

    // Populate Inspector
    updateInspector(layerId);
}

// Open Inspector For Layer
function openInspectorForLayer(layerId) {
    selectLayer(layerId);
    const inspectorTab = document.querySelector('.builder-nav-tab[data-tab="tab-element-inspector"]');
    if (inspectorTab) inspectorTab.click();
}

// Center Active Layer Horizontally
function centerActiveLayerHorizontally() {
    if (!currentSelectedLayerId) return;
    const target = document.querySelector(`.canvas-layer[data-id="${currentSelectedLayerId}"]`);
    if (target) {
        target.style.left = '50%';
        const leftInp = document.getElementById('inspectorLeftInput');
        if (leftInp) leftInp.value = 50;
        updateLayerCoordinates(currentSelectedLayerId, parseFloat(target.style.top) || 50, 50);
    }
}

// Get Layer Font Size / Dimensions
function getLayerFontSize(layerId) {
    if (elementMeta[layerId]) {
        return elementMeta[layerId].size;
    }
    const cf = customFieldsState.find(f => f.id === layerId);
    if (cf) return parseInt(cf.fontSize) || 14;
    return 16;
}

// Set Layer Font Size / Dimensions and 2-way sync to form hidden inputs
function setLayerFontSize(layerId, size) {
    size = parseInt(size) || 14;
    size = Math.max(8, Math.min(160, size));

    if (elementMeta[layerId]) {
        elementMeta[layerId].size = size;

        // Sync to hidden input so saving form submits this value
        if (elementMeta[layerId].sizeInputId) {
            const hiddenInp = document.getElementById(elementMeta[layerId].sizeInputId);
            if (hiddenInp) hiddenInp.value = size;
        }

        const textEl = document.getElementById(elementMeta[layerId].textEl);
        if (textEl) textEl.style.fontSize = size + 'px';
        
        // Special: Student Photo box sizing
        if (layerId === 'photo') {
            const photoBox = document.getElementById('canvasPhotoBox');
            if (photoBox) {
                photoBox.style.width = size + 'px';
                photoBox.style.height = Math.round(size * 1.25) + 'px';
            }
        }
        // Special: Seal or QR Image sizing
        else if (layerId === 'seal') {
            const img = document.getElementById('canvasSealImg');
            const icon = document.getElementById('canvasSealIcon');
            if (img) { img.style.maxHeight = size + 'px'; img.style.maxWidth = size + 'px'; }
            if (icon) { icon.style.width = size + 'px'; icon.style.height = size + 'px'; }
        } else if (layerId === 'qr') {
            const img = document.getElementById('canvasQrImg');
            if (img) { img.style.width = size + 'px'; img.style.height = size + 'px'; }
        } else if (layerId === 'date') {
            const dateVal = document.getElementById('canvasDateVal');
            if (dateVal) dateVal.style.fontSize = size + 'px';
        }
    } else {
        const cf = customFieldsState.find(f => f.id === layerId);
        if (cf) {
            cf.fontSize = size;
            const layerEl = document.getElementById('layer_' + layerId);
            if (layerEl) {
                const textEl = layerEl.querySelector('.custom-field-text');
                if (textEl) textEl.style.fontSize = size + 'px';
            }
            syncCustomFieldsJSON();
        }
    }

    // Keep inspector range slider & label updated
    if (currentSelectedLayerId === layerId) {
        const sizeRange = document.getElementById('inspectorSizeRange');
        const sizeVal = document.getElementById('inspectorSizeVal');
        if (sizeRange) sizeRange.value = size;
        if (sizeVal) sizeVal.textContent = size + 'px';
    }
}

// Adjust Layer Font Size (+ / -)
function adjustLayerFontSize(layerId, delta) {
    const current = getLayerFontSize(layerId);
    setLayerFontSize(layerId, current + delta);
}

function adjustCustomFieldFontSize(fieldId, delta) {
    adjustLayerFontSize(fieldId, delta);
}

// Enable Student Profile Photo
function enableStudentPhoto() {
    const toggle = document.getElementById('toggle_photo');
    if (toggle) toggle.checked = true;
    toggleLayerVisibility('photo', true);
    openInspectorForLayer('photo');
}

// Update Layer Coordinates in Hidden Inputs & State
function updateLayerCoordinates(layerId, top, left) {
    if (elementMeta[layerId]) {
        const ht = document.getElementById(elementMeta[layerId].topId);
        const hl = document.getElementById(elementMeta[layerId].leftId);
        if (ht) ht.value = top;
        if (hl) hl.value = left;
    } else {
        const cf = customFieldsState.find(f => f.id === layerId);
        if (cf) {
            cf.top = top;
            cf.left = left;
            syncCustomFieldsJSON();
        }
    }
    
    // Update inspector position fields if active
    const topInp = document.getElementById('inspectorTopInput');
    const leftInp = document.getElementById('inspectorLeftInput');
    if (topInp && leftInp && currentSelectedLayerId === layerId) {
        topInp.value = top;
        leftInp.value = left;
    }
}

// Delete or Hide Layer
function deleteLayer(layerId) {
    if (elementMeta[layerId]) {
        // Built-in layer: toggle off
        const toggle = document.getElementById('toggle_' + layerId);
        if (toggle) toggle.checked = false;
        toggleLayerVisibility(layerId, false);
    } else {
        // Custom field: remove permanently
        deleteCustomField(layerId);
    }
}

function toggleLayerVisibility(layerId, isVisible) {
    const el = document.querySelector(`.canvas-layer[data-id="${layerId}"]`);
    if (el) el.style.display = isVisible ? 'block' : 'none';
}

// Helper to replace tags for live sample display on the canvas & inspector
function renderSampleTagsPreview(text) {
    if (!text) return '';
    const sampleData = {
        '{student_name}': 'John Doe',
        '{roll_no}': 'LKVM-0101',
        '{course_title}': 'Diploma in Computer Applications',
        '{grade}': 'A+ (Distinction)',
        '{issue_date}': '<?php echo date("d F Y"); ?>',
        '{cert_uid}': '<?php echo htmlspecialchars($sample_cert_uid); ?>',
        '{student_email}': 'student@example.com',
        '{phone}': '+91 98765 43210',
        '{instructor_name}': '<?php echo htmlspecialchars($c_sig_name); ?>',
        '{duration}': '120 Hours',
        '{completion_percent}': '100%',
        '{father_name}': 'Mr. Guardian'
    };
    let out = String(text);
    for (const [tag, val] of Object.entries(sampleData)) {
        const reg = new RegExp(tag.replace(/([{}])/g, '\\$1'), 'gi');
        out = out.replace(reg, val);
    }
    return out;
}

// Insert Tag into Inspector Text Field
function insertTagIntoInspector(tag) {
    const textInp = document.getElementById('inspectorTextInput');
    if (!textInp) return;

    const start = textInp.selectionStart !== null ? textInp.selectionStart : textInp.value.length;
    const end = textInp.selectionEnd !== null ? textInp.selectionEnd : textInp.value.length;
    const val = textInp.value;
    textInp.value = val.substring(0, start) + tag + val.substring(end);
    textInp.focus();
    textInp.selectionStart = textInp.selectionEnd = start + tag.length;

    // Trigger input event to sync canvas & inspector
    textInp.dispatchEvent(new Event('input'));
}

// Insert Tag into Modal Field Text
function insertTagIntoModal(tag) {
    const textInp = document.getElementById('modalFieldText');
    if (!textInp) return;

    const start = textInp.selectionStart !== null ? textInp.selectionStart : textInp.value.length;
    const end = textInp.selectionEnd !== null ? textInp.selectionEnd : textInp.value.length;
    const val = textInp.value;
    textInp.value = val.substring(0, start) + tag + val.substring(end);
    textInp.focus();
    textInp.selectionStart = textInp.selectionEnd = start + tag.length;

    updateModalLivePreview();
}

// Update Modal Live Preview
function updateModalLivePreview() {
    const textInp = document.getElementById('modalFieldText');
    const previewBox = document.getElementById('modalLivePreviewText');
    if (textInp && previewBox) {
        previewBox.textContent = renderSampleTagsPreview(textInp.value) || '(Enter text to preview)';
    }
}

// Submit Modal: Add Custom Text with Tags
function submitModalAddCustomText() {
    const labelInp = document.getElementById('modalFieldLabel');
    const textInp = document.getElementById('modalFieldText');
    const sizeInp = document.getElementById('modalFontSize');
    const fontInp = document.getElementById('modalFontFamily');
    const weightInp = document.getElementById('modalFontWeight');
    const colorInp = document.getElementById('modalFontColor');

    const label = (labelInp && labelInp.value.trim()) ? labelInp.value.trim() : 'Custom Field';
    const text = (textInp && textInp.value.trim()) ? textInp.value.trim() : 'Roll No: {roll_no} • Grade: {grade}';
    const size = parseInt(sizeInp ? sizeInp.value : 16) || 16;
    const font = fontInp ? fontInp.value : 'jakarta';
    const weight = weightInp ? weightInp.value : '700';
    const color = colorInp ? colorInp.value : '#1e3a5f';

    const fieldId = 'cf_' + Date.now();
    const newField = {
        id: fieldId,
        type: 'custom_text',
        label: label,
        text: text,
        top: 50,
        left: 50,
        fontSize: size,
        color: color,
        fontFamily: font,
        fontWeight: weight,
        visible: 1
    };

    customFieldsState.push(newField);
    syncCustomFieldsJSON();

    const fontStacks = {
        jakarta: "'Plus Jakarta Sans', sans-serif",
        playfair: "'Playfair Display', Georgia, serif",
        cinzel: "'Cinzel', serif",
        great_vibes: "'Great Vibes', cursive",
        alex_brush: "'Alex Brush', cursive"
    };
    const fontCss = fontStacks[font] || fontStacks.jakarta;

    // Append to Canvas
    const container = document.getElementById('customFieldsCanvasContainer');
    const layerEl = document.createElement('div');
    layerEl.className = 'canvas-layer custom-dynamic-layer';
    layerEl.id = 'layer_' + fieldId;
    layerEl.dataset.id = fieldId;
    layerEl.style.top = '50%';
    layerEl.style.left = '50%';
    layerEl.innerHTML = `
        <span class="layer-badge">${escapeHtml(label)}</span>
        <div class="custom-field-text" style="font-family: ${fontCss}; font-size: ${size}px; color: ${color}; font-weight: ${weight};">
            ${escapeHtml(renderSampleTagsPreview(text))}
        </div>
        <div class="layer-quick-tools">
            <button type="button" class="layer-quick-btn" onclick="openInspectorForLayer('${fieldId}')" title="Inspect & Edit"><i class="fa fa-sliders"></i></button>
            <button type="button" class="layer-quick-btn" onclick="adjustCustomFieldFontSize('${fieldId}', -1)"><i class="fa fa-minus"></i></button>
            <button type="button" class="layer-quick-btn" onclick="adjustCustomFieldFontSize('${fieldId}', 1)"><i class="fa fa-plus"></i></button>
            <button type="button" class="layer-quick-btn btn-delete" onclick="deleteCustomField('${fieldId}')"><i class="fa fa-trash"></i></button>
        </div>
        <div class="resize-handle" data-layer="${fieldId}"></div>
    `;

    container.appendChild(layerEl);

    // Re-bind drag & handlers
    initDragAndDrop();
    renderCustomFieldsSidebar();

    // Close Bootstrap Modal
    const modalEl = document.getElementById('modalAddCustomText');
    if (modalEl) {
        const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modalInstance.hide();
    }

    // Open Inspector for the new field
    openInspectorForLayer(fieldId);
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Add Custom Dynamic Field (from Quick Presets menu)
function addCustomField(type, defaultLabel, defaultText) {
    const fieldId = 'cf_' + Date.now();
    const newField = {
        id: fieldId,
        type: type,
        label: defaultLabel,
        text: defaultText,
        top: 50,
        left: 50,
        fontSize: 14,
        color: '#1e3a5f',
        fontFamily: 'jakarta',
        fontWeight: '700',
        visible: 1
    };

    customFieldsState.push(newField);
    syncCustomFieldsJSON();

    // Append to Canvas
    const container = document.getElementById('customFieldsCanvasContainer');
    const layerEl = document.createElement('div');
    layerEl.className = 'canvas-layer custom-dynamic-layer';
    layerEl.id = 'layer_' + fieldId;
    layerEl.dataset.id = fieldId;
    layerEl.style.top = '50%';
    layerEl.style.left = '50%';
    layerEl.innerHTML = `
        <span class="layer-badge">${escapeHtml(defaultLabel)}</span>
        <div class="custom-field-text" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; color: #1e3a5f; font-weight: 700;">
            ${escapeHtml(renderSampleTagsPreview(defaultText))}
        </div>
        <div class="layer-quick-tools">
            <button type="button" class="layer-quick-btn" onclick="openInspectorForLayer('${fieldId}')" title="Inspect & Edit"><i class="fa fa-sliders"></i></button>
            <button type="button" class="layer-quick-btn" onclick="adjustCustomFieldFontSize('${fieldId}', -1)"><i class="fa fa-minus"></i></button>
            <button type="button" class="layer-quick-btn" onclick="adjustCustomFieldFontSize('${fieldId}', 1)"><i class="fa fa-plus"></i></button>
            <button type="button" class="layer-quick-btn btn-delete" onclick="deleteCustomField('${fieldId}')"><i class="fa fa-trash"></i></button>
        </div>
        <div class="resize-handle" data-layer="${fieldId}"></div>
    `;

    container.appendChild(layerEl);

    initDragAndDrop();
    renderCustomFieldsSidebar();
    openInspectorForLayer(fieldId);
}

// Delete Custom Field
function deleteCustomField(fieldId) {
    customFieldsState = customFieldsState.filter(f => f.id !== fieldId);
    syncCustomFieldsJSON();

    const layerEl = document.getElementById('layer_' + fieldId);
    if (layerEl) layerEl.remove();

    renderCustomFieldsSidebar();

    // Reset inspector
    document.getElementById('inspectorContent').style.display = 'block';
    document.getElementById('inspectorControls').style.display = 'none';
    document.getElementById('inspectorActiveName').textContent = 'Select an element';
}

// Sync Custom Fields to Form Hidden Input
function syncCustomFieldsJSON() {
    const input = document.getElementById('customFieldsJson');
    if (input) {
        input.value = JSON.stringify(customFieldsState);
    }
}

// Render Custom Fields in Sidebar List
function renderCustomFieldsSidebar() {
    const container = document.getElementById('customFieldsSidebarList');
    if (!container) return;

    if (customFieldsState.length === 0) {
        container.innerHTML = '<div class="text-center py-2 text-muted small"><i class="fa fa-plus-circle me-1"></i> No custom text fields yet. Click "+ Add Custom Text with Tags" above!</div>';
        return;
    }

    let html = '<h6 class="fw-bold text-dark small mb-2 text-uppercase text-muted">Custom Added Fields (' + customFieldsState.length + ')</h6>';
    customFieldsState.forEach(cf => {
        html += `
            <div class="field-layer-row" data-target="${cf.id}">
                <div style="cursor: pointer;" onclick="openInspectorForLayer('${cf.id}')">
                    <strong class="small d-block text-dark"><i class="fa fa-tag text-success me-2"></i>${escapeHtml(cf.label)}</strong>
                    <small class="text-muted text-truncate d-inline-block" style="max-width: 180px;">${escapeHtml(cf.text)}</small>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-outline-primary btn-sm p-1 rounded-circle" style="width: 28px; height: 28px;" onclick="openInspectorForLayer('${cf.id}')" title="Edit Field">
                        <i class="fa fa-sliders"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm p-1 rounded-circle" style="width: 28px; height: 28px;" onclick="deleteCustomField('${cf.id}')" title="Delete Field">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

// Inspector Controls Setup
function setupInspectorControls() {
    const sizeRange = document.getElementById('inspectorSizeRange');
    const sizeVal = document.getElementById('inspectorSizeVal');
    const textInp = document.getElementById('inspectorTextInput');
    const colorInp = document.getElementById('inspectorColorInput');
    const fontSel = document.getElementById('inspectorFontSelect');
    const topInp = document.getElementById('inspectorTopInput');
    const leftInp = document.getElementById('inspectorLeftInput');
    const btnMinus = document.getElementById('inspectorBtnSizeMinus');
    const btnPlus = document.getElementById('inspectorBtnSizePlus');
    const btnDelete = document.getElementById('inspectorBtnDelete');

    if (sizeRange) {
        sizeRange.addEventListener('input', () => {
            if (!currentSelectedLayerId) return;
            setLayerFontSize(currentSelectedLayerId, parseInt(sizeRange.value));
        });
    }

    if (btnMinus) {
        btnMinus.addEventListener('click', () => {
            if (!currentSelectedLayerId) return;
            adjustLayerFontSize(currentSelectedLayerId, -1);
        });
    }

    if (btnPlus) {
        btnPlus.addEventListener('click', () => {
            if (!currentSelectedLayerId) return;
            adjustLayerFontSize(currentSelectedLayerId, 1);
        });
    }

    if (textInp) {
        textInp.addEventListener('input', () => {
            if (!currentSelectedLayerId) return;
            const val = textInp.value;
            if (elementMeta[currentSelectedLayerId]) {
                const textEl = document.getElementById(elementMeta[currentSelectedLayerId].textEl);
                if (textEl) textEl.textContent = renderSampleTagsPreview(val);
                if (elementMeta[currentSelectedLayerId].textInputId) {
                    const sourceInp = document.getElementById(elementMeta[currentSelectedLayerId].textInputId);
                    if (sourceInp) sourceInp.value = val;
                }
                updateInspectorLiveSample(val);
            } else {
                const cf = customFieldsState.find(f => f.id === currentSelectedLayerId);
                if (cf) {
                    cf.text = val;
                    const layerEl = document.getElementById('layer_' + currentSelectedLayerId);
                    if (layerEl) {
                        const txt = layerEl.querySelector('.custom-field-text');
                        if (txt) txt.textContent = renderSampleTagsPreview(val);
                    }
                    syncCustomFieldsJSON();
                    renderCustomFieldsSidebar();
                    updateInspectorLiveSample(val);
                }
            }
        });
    }

    if (colorInp) {
        colorInp.addEventListener('input', () => {
            if (!currentSelectedLayerId) return;
            const color = colorInp.value;
            if (elementMeta[currentSelectedLayerId]) {
                elementMeta[currentSelectedLayerId].color = color;
                if (elementMeta[currentSelectedLayerId].colorInputId) {
                    const hiddenCol = document.getElementById(elementMeta[currentSelectedLayerId].colorInputId);
                    if (hiddenCol) hiddenCol.value = color;
                }
                const textEl = document.getElementById(elementMeta[currentSelectedLayerId].textEl);
                if (textEl) textEl.style.color = color;
                if (currentSelectedLayerId === 'date') {
                    const dateVal = document.getElementById('canvasDateVal');
                    if (dateVal) dateVal.style.color = color;
                }
            } else {
                const cf = customFieldsState.find(f => f.id === currentSelectedLayerId);
                if (cf) {
                    cf.color = color;
                    const layerEl = document.getElementById('layer_' + currentSelectedLayerId);
                    if (layerEl) {
                        const txt = layerEl.querySelector('.custom-field-text');
                        if (txt) txt.style.color = color;
                    }
                    syncCustomFieldsJSON();
                }
            }
        });
    }

    if (fontSel) {
        fontSel.addEventListener('change', () => {
            if (!currentSelectedLayerId) return;
            const fontKey = fontSel.value;
            const fontStacks = {
                jakarta: "'Plus Jakarta Sans', sans-serif",
                playfair: "'Playfair Display', Georgia, serif",
                cinzel: "'Cinzel', serif",
                great_vibes: "'Great Vibes', cursive",
                alex_brush: "'Alex Brush', cursive"
            };
            const fontCss = fontStacks[fontKey] || fontStacks.jakarta;

            if (elementMeta[currentSelectedLayerId]) {
                elementMeta[currentSelectedLayerId].font = fontKey;
                if (elementMeta[currentSelectedLayerId].fontInputId) {
                    const hiddenFont = document.getElementById(elementMeta[currentSelectedLayerId].fontInputId);
                    if (hiddenFont) hiddenFont.value = fontKey;
                }
                const textEl = document.getElementById(elementMeta[currentSelectedLayerId].textEl);
                if (textEl) textEl.style.fontFamily = fontCss;
            } else {
                const cf = customFieldsState.find(f => f.id === currentSelectedLayerId);
                if (cf) {
                    cf.fontFamily = fontKey;
                    const layerEl = document.getElementById('layer_' + currentSelectedLayerId);
                    if (layerEl) {
                        const txt = layerEl.querySelector('.custom-field-text');
                        if (txt) txt.style.fontFamily = fontCss;
                    }
                    syncCustomFieldsJSON();
                }
            }
        });
    }

    if (topInp && leftInp) {
        topInp.addEventListener('input', () => {
            if (!currentSelectedLayerId) return;
            const topVal = parseFloat(topInp.value) || 0;
            const target = document.querySelector(`.canvas-layer[data-id="${currentSelectedLayerId}"]`);
            if (target) target.style.top = topVal + '%';
            updateLayerCoordinates(currentSelectedLayerId, topVal, parseFloat(leftInp.value) || 50);
        });

        leftInp.addEventListener('input', () => {
            if (!currentSelectedLayerId) return;
            const leftVal = parseFloat(leftInp.value) || 0;
            const target = document.querySelector(`.canvas-layer[data-id="${currentSelectedLayerId}"]`);
            if (target) target.style.left = leftVal + '%';
            updateLayerCoordinates(currentSelectedLayerId, parseFloat(topInp.value) || 50, leftVal);
        });
    }

    if (btnDelete) {
        btnDelete.addEventListener('click', () => {
            if (!currentSelectedLayerId) return;
            deleteLayer(currentSelectedLayerId);
        });
    }
}

// Update Inspector UI with Active Layer Data
function updateInspector(layerId) {
    const inspectorContent = document.getElementById('inspectorContent');
    const inspectorControls = document.getElementById('inspectorControls');
    const inspectorActiveName = document.getElementById('inspectorActiveName');
    const sizeRange = document.getElementById('inspectorSizeRange');
    const sizeVal = document.getElementById('inspectorSizeVal');
    const textInp = document.getElementById('inspectorTextInput');
    const colorInp = document.getElementById('inspectorColorInput');
    const fontSel = document.getElementById('inspectorFontSelect');
    const topInp = document.getElementById('inspectorTopInput');
    const leftInp = document.getElementById('inspectorLeftInput');
    const fontGroup = document.getElementById('inspectorFontGroup');
    const tagsHelper = document.getElementById('inspectorTagsHelper');

    if (!layerId) {
        inspectorContent.style.display = 'block';
        inspectorControls.style.display = 'none';
        inspectorActiveName.textContent = 'Select an element';
        return;
    }

    inspectorContent.style.display = 'none';
    inspectorControls.style.display = 'block';

    const layerEl = document.querySelector(`.canvas-layer[data-id="${layerId}"]`);
    const curTop = parseFloat(layerEl ? layerEl.style.top : 50) || 50;
    const curLeft = parseFloat(layerEl ? layerEl.style.left : 50) || 50;
    topInp.value = curTop;
    leftInp.value = curLeft;

    if (elementMeta[layerId]) {
        const meta = elementMeta[layerId];
        inspectorActiveName.textContent = meta.name;
        sizeRange.value = meta.size;
        sizeVal.textContent = meta.size + 'px';
        colorInp.value = meta.color || '#1e3a5f';
        fontSel.value = meta.font || 'jakarta';
        
        if (meta.textEl) {
            let val = '';
            if (meta.textInputId && document.getElementById(meta.textInputId)) {
                val = document.getElementById(meta.textInputId).value;
            } else {
                const textEl = document.getElementById(meta.textEl);
                if (textEl) val = textEl.textContent.trim();
            }
            textInp.value = val;
            document.getElementById('inspectorTextGroup').style.display = 'block';
            if (fontGroup) fontGroup.style.display = 'flex';
        } else {
            document.getElementById('inspectorTextGroup').style.display = 'none';
            if (fontGroup) fontGroup.style.display = 'none';
        }
        
        // Tags helper visibility
        if (tagsHelper) {
            if (layerId === 'body' || layerId === 'heading') {
                tagsHelper.style.display = 'block';
                updateInspectorLiveSample(textInp.value);
            } else {
                tagsHelper.style.display = 'none';
            }
        }
    } else {
        const cf = customFieldsState.find(f => f.id === layerId);
        if (cf) {
            inspectorActiveName.textContent = cf.label || 'Custom Text';
            sizeRange.value = cf.fontSize || 14;
            sizeVal.textContent = (cf.fontSize || 14) + 'px';
            colorInp.value = cf.color || '#1e3a5f';
            fontSel.value = cf.fontFamily || 'jakarta';
            textInp.value = cf.text || '';
            document.getElementById('inspectorTextGroup').style.display = 'block';
            if (fontGroup) fontGroup.style.display = 'flex';
            
            if (tagsHelper) {
                tagsHelper.style.display = 'block';
                updateInspectorLiveSample(cf.text || '');
            }
        }
    }
}

// Live update sample preview in Inspector
function updateInspectorLiveSample(rawText) {
    const previewSpan = document.getElementById('inspectorSamplePreviewText');
    if (previewSpan) {
        previewSpan.textContent = renderSampleTagsPreview(rawText);
    }
}

// Main Navigation View Switcher (Studio vs Templates Gallery)
function switchMainView(viewName) {
    const studioEl = document.getElementById('studioViewSection');
    const galleryEl = document.getElementById('galleryViewSection');
    const btnStudio = document.getElementById('navBtnStudio');
    const btnGallery = document.getElementById('navBtnGallery');

    if (viewName === 'gallery') {
        if (studioEl) studioEl.style.display = 'none';
        if (galleryEl) galleryEl.style.display = 'block';
        if (btnStudio) btnStudio.classList.remove('active');
        if (btnGallery) btnGallery.classList.add('active');
        window.history.replaceState(null, '', 'certificate-templates?tab=gallery');
    } else {
        if (studioEl) studioEl.style.display = 'block';
        if (galleryEl) galleryEl.style.display = 'none';
        if (btnStudio) btnStudio.classList.add('active');
        if (btnGallery) btnGallery.classList.remove('active');
        window.history.replaceState(null, '', 'certificate-templates?tab=studio');
    }
}

// Reset Entire Canvas Layout, Positions, Sizes & Colors to Default Design
function resetCanvasToDefaultDesign() {
    const defaultCoords = {
        org: { top: 8, left: 50 },
        heading: { top: 21, left: 50 },
        present: { top: 36, left: 50 },
        student: { top: 46, left: 50 },
        photo: { top: 18, left: 84 },
        body: { top: 58, left: 50 },
        course: { top: 67, left: 50 },
        date: { top: 81, left: 16 },
        seal: { top: 79, left: 42 },
        qr: { top: 79, left: 58 },
        sig: { top: 81, left: 84 },
        uid: { top: 94, left: 50 }
    };

    const defaultSizes = {
        org: 18,
        heading: 36,
        present: 13,
        student: 50,
        photo: 75,
        body: 14,
        course: 17,
        date: 14,
        seal: 60,
        qr: 50,
        sig: 14
    };

    // Reset coordinates
    for (const [id, pos] of Object.entries(defaultCoords)) {
        const el = document.getElementById(layerElementMap[id] || ('layer' + capitalize(id)));
        if (el) {
            el.style.top = pos.top + '%';
            el.style.left = pos.left + '%';
            if (id !== 'photo') el.style.display = 'block';
        }
        const topInp = document.getElementById(coordInputMap[id]?.top);
        if (topInp) topInp.value = pos.top;
        const leftInp = document.getElementById(coordInputMap[id]?.left);
        if (leftInp) leftInp.value = pos.left;

        const tog = document.getElementById('toggle_' + id);
        if (tog) tog.checked = (id !== 'photo');
    }

    // Reset sizes
    for (const [id, sz] of Object.entries(defaultSizes)) {
        setLayerFontSize(id, sz);
    }

    // Reset fonts
    const defaultFonts = [
        { inp: 'inputCfgOrgFont', val: 'jakarta' },
        { inp: 'inputCfgHeadingFont', val: 'playfair' },
        { inp: 'inputCfgStudentFont', val: 'great_vibes' },
        { inp: 'inputCfgCourseFont', val: 'jakarta' }
    ];
    defaultFonts.forEach(f => {
        const inp = document.getElementById(f.inp);
        if (inp) inp.value = f.val;
    });

    const hText = document.getElementById('canvasHeadingText');
    if (hText) hText.style.fontFamily = "'Playfair Display', serif";

    const sName = document.getElementById('canvasStudentName');
    if (sName) sName.style.fontFamily = "'Great Vibes', cursive";

    // Reset colors to classic gold defaults
    const colorResets = [
        { inp: 'inputCfgOrgColor', txt: 'canvasOrgTitle', color: '#1e3a5f' },
        { inp: 'inputCfgHeadingColor', txt: 'canvasHeadingText', color: '#1e3a5f' },
        { inp: 'inputCfgHeadingSubColor', txt: 'canvasHeadingSub', color: '#64748b' },
        { inp: 'inputCfgPresentColor', txt: 'canvasPresentText', color: '#94a3b8' },
        { inp: 'inputCfgStudentColor', txt: 'canvasStudentName', color: '#1e3a5f' },
        { inp: 'inputCfgBodyColor', txt: 'canvasBodyText', color: '#475569' },
        { inp: 'inputCfgCourseColor', txt: 'canvasCourseTitle', color: '#1e3a5f' },
        { inp: 'inputCfgDateColor', txt: 'canvasDateVal', color: '#1e3a5f' },
        { inp: 'inputCfgSigColor', txt: 'canvasSigName', color: '#1e3a5f' }
    ];

    colorResets.forEach(item => {
        const inp = document.getElementById(item.inp);
        if (inp) inp.value = item.color;
        const txt = document.getElementById(item.txt);
        if (txt) txt.style.color = item.color;
    });

    alert('Default certificate design layout restored! Click "Save Certificate Design & Layout" to persist changes, or continue editing.');
}

// Apply Template Theme to Canvas Studio
function applyTemplateThemeToCanvas(themeKey) {
    const themePresets = {
        classic_gold: {
            primary: '#1e3a5f',
            heading: '#1e3a5f',
            headingSub: '#64748b',
            headingFont: 'playfair',
            studentFont: 'great_vibes',
            courseFont: 'jakarta'
        },
        modern_blue: {
            primary: '#0056D2',
            heading: '#0056D2',
            headingSub: '#0284c7',
            headingFont: 'jakarta',
            studentFont: 'playfair',
            courseFont: 'jakarta'
        },
        emerald_prestige: {
            primary: '#059669',
            heading: '#064e3b',
            headingSub: '#047857',
            headingFont: 'playfair',
            studentFont: 'great_vibes',
            courseFont: 'jakarta'
        },
        academic_crimson: {
            primary: '#991b1b',
            heading: '#7f1d1d',
            headingSub: '#c2410c',
            headingFont: 'cinzel',
            studentFont: 'playfair',
            courseFont: 'jakarta'
        },
        minimal_dark: {
            primary: '#0f172a',
            heading: '#0f172a',
            headingSub: '#334155',
            headingFont: 'jakarta',
            studentFont: 'jakarta',
            courseFont: 'jakarta'
        }
    };

    const preset = themePresets[themeKey];
    if (!preset) return;

    // Switch view to Studio
    switchMainView('studio');

    // Apply colors
    const orgInp = document.getElementById('inputCfgOrgColor');
    if (orgInp) { orgInp.value = preset.primary; const el = document.getElementById('canvasOrgTitle'); if (el) el.style.color = preset.primary; }

    const headInp = document.getElementById('inputCfgHeadingColor');
    if (headInp) { headInp.value = preset.heading; const el = document.getElementById('canvasHeadingText'); if (el) el.style.color = preset.heading; }

    const headSubInp = document.getElementById('inputCfgHeadingSubColor');
    if (headSubInp) { headSubInp.value = preset.headingSub; const el = document.getElementById('canvasHeadingSub'); if (el) el.style.color = preset.headingSub; }

    const studInp = document.getElementById('inputCfgStudentColor');
    if (studInp) { studInp.value = preset.primary; const el = document.getElementById('canvasStudentName'); if (el) el.style.color = preset.primary; }

    const courseInp = document.getElementById('inputCfgCourseColor');
    if (courseInp) { courseInp.value = preset.primary; const el = document.getElementById('canvasCourseTitle'); if (el) el.style.color = preset.primary; }

    // Apply fonts
    const headFontInp = document.getElementById('inputCfgHeadingFont');
    if (headFontInp) {
        headFontInp.value = preset.headingFont;
        const fontStacks = {
            jakarta: "'Plus Jakarta Sans', sans-serif",
            playfair: "'Playfair Display', serif",
            cinzel: "'Cinzel', serif"
        };
        const hText = document.getElementById('canvasHeadingText');
        if (hText) hText.style.fontFamily = fontStacks[preset.headingFont] || fontStacks.jakarta;
    }

    const studFontInp = document.getElementById('inputCfgStudentFont');
    if (studFontInp) {
        studFontInp.value = preset.studentFont;
        const fontStacks = {
            jakarta: "'Plus Jakarta Sans', sans-serif",
            playfair: "'Playfair Display', serif",
            great_vibes: "'Great Vibes', cursive",
            alex_brush: "'Alex Brush', cursive"
        };
        const sName = document.getElementById('canvasStudentName');
        if (sName) sName.style.fontFamily = fontStacks[preset.studentFont] || fontStacks.great_vibes;
    }

    alert('Theme presets for "' + themeKey.replace('_', ' ').toUpperCase() + '" loaded into Canvas Studio! Click Save to apply.');
}

// Attach Auto-Center and Reset Default button handlers on document ready
document.addEventListener('DOMContentLoaded', () => {
    const btnAutoCenter = document.getElementById('btnAutoCenter');
    if (btnAutoCenter) {
        btnAutoCenter.addEventListener('click', () => {
            const centerLayers = ['org', 'heading', 'present', 'student', 'body', 'course', 'uid'];
            centerLayers.forEach(id => {
                const el = document.getElementById(layerElementMap[id] || ('layer' + capitalize(id)));
                if (el) el.style.left = '50%';
                const leftInp = document.getElementById(coordInputMap[id]?.left);
                if (leftInp) leftInp.value = 50;
                updateLayerCoordinates(id, parseFloat(el ? el.style.top : 50) || 50, 50);
            });
            alert('Headline, student name, and content elements centered horizontally (Left: 50%)!');
        });
    }

    const btnResetDefaultLayout = document.getElementById('btnResetDefaultLayout');
    if (btnResetDefaultLayout) {
        btnResetDefaultLayout.addEventListener('click', () => {
            if (!confirm('Are you sure you want to reset all element coordinates, font sizes, colors, and layout back to standard default certificate design?')) {
                return;
            }
            resetCanvasToDefaultDesign();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
