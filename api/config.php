<?php
/**
 * LKVM LMS - Mobile REST API Configuration & Common Helpers
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Content-Type: application/json; charset=UTF-8");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Suppress unexpected display errors to avoid corrupting JSON output
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/db.php';
lms_ensure_schema($con);

function json_res($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

function get_request_data() {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return array_merge($_REQUEST, $json);
    }
    return $_REQUEST;
}

function get_auth_user($con) {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
    
    // Check Bearer token or User-Id header
    $token = '';
    if (preg_match('/Bearer\s+(\S+)/', $auth, $matches)) {
        $token = $matches[1];
    } elseif (!empty($_REQUEST['token'])) {
        $token = $_REQUEST['token'];
    }

    if (!empty($token)) {
        // Token format: base64(userId:role:timestamp:hash) or simple base64(userId:role)
        $decoded = base64_decode($token, true);
        if ($decoded && strpos($decoded, ':') !== false) {
            $parts = explode(':', $decoded);
            $userId = intval($parts[0] ?? 0);
            $role = $parts[1] ?? 'student';
            
            if ($role === 'admin' || $role === 'instructor') {
                $q = mysqli_query($con, "SELECT id, name, username, email, role FROM lms_admins WHERE id = $userId AND status = 1");
                if ($q && $user = mysqli_fetch_assoc($q)) {
                    $user['user_type'] = 'admin';
                    return $user;
                }
            } else {
                $q = mysqli_query($con, "SELECT id, name, email, phone, status FROM lms_students WHERE id = $userId AND status = 1");
                if ($q && $user = mysqli_fetch_assoc($q)) {
                    $user['user_type'] = 'student';
                    return $user;
                }
            }
        }
    }
    
    // Fallback: check session if any
    if (isset($_SESSION['student_id'])) {
        $uid = intval($_SESSION['student_id']);
        $q = mysqli_query($con, "SELECT id, name, email, phone, status FROM lms_students WHERE id = $uid AND status = 1");
        if ($q && $user = mysqli_fetch_assoc($q)) {
            $user['user_type'] = 'student';
            return $user;
        }
    }
    
    if (isset($_SESSION['lms_admin_id'])) {
        $uid = intval($_SESSION['lms_admin_id']);
        $q = mysqli_query($con, "SELECT id, name, username, email, role FROM lms_admins WHERE id = $uid AND status = 1");
        if ($q && $user = mysqli_fetch_assoc($q)) {
            $user['user_type'] = 'admin';
            return $user;
        }
    }

    return null;
}

function generate_token($userId, $role = 'student') {
    return base64_encode($userId . ':' . $role . ':' . time() . ':' . substr(md5($userId . $role . 'secret_lms_salt'), 0, 8));
}
