<?php
require_once __DIR__ . '/config.php';

$data = get_request_data();
$action = $data['action'] ?? ($_GET['action'] ?? 'login');

// 1. Student Login
if ($action === 'login' || $action === 'student_login') {
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        json_res(false, 'Please provide both email and password.', null, 400);
    }

    $stmt = $con->prepare("SELECT * FROM lms_students WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $user = $res->fetch_assoc()) {
        if ($user['status'] == 0) {
            json_res(false, 'Your account is deactivated.', null, 403);
        }

        if (password_verify($password, $user['password'])) {
            // Check email verification if SMTP is active
            $smtp_config = lms_get_smtp_config($con);
            if ($smtp_config['enabled'] && isset($user['email_verified']) && $user['email_verified'] == 0) {
                $otp = strval(rand(100000, 999999));
                $exp = date('Y-m-d H:i:s', time() + 900);
                mysqli_query($con, "UPDATE lms_students SET otp_code = '$otp', otp_expires_at = '$exp' WHERE id = {$user['id']}");
                lms_send_otp_email($con, $user['email'], $user['name'], $otp);

                json_res(false, 'Please verify your email address. A fresh 6-digit OTP has been sent to your Gmail.', [
                    'requires_otp' => true,
                    'email' => $user['email']
                ], 403);
            }

            $token = generate_token($user['id'], 'student');
            unset($user['password']);
            $user['user_type'] = 'student';
            json_res(true, 'Login successful!', [
                'user' => $user,
                'token' => $token
            ]);
        } else {
            json_res(false, 'Invalid password. Please check your credentials.', null, 401);
        }
    } else {
        json_res(false, 'No account found with this email.', null, 404);
    }
}

// 2. Student Register
if ($action === 'register' || $action === 'student_register') {
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        json_res(false, 'Name, email and password are required.', null, 400);
    }

    if (strlen($password) < 6) {
        json_res(false, 'Password must be at least 6 characters.', null, 400);
    }

    $chk = $con->prepare("SELECT id, email_verified FROM lms_students WHERE email = ? LIMIT 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    $chkRes = $chk->get_result();

    if ($chkRes->num_rows > 0) {
        $existing = $chkRes->fetch_assoc();
        // If account exists but never verified, re-send OTP
        if (isset($existing['email_verified']) && $existing['email_verified'] == 0) {
            $otp = strval(rand(100000, 999999));
            $exp = date('Y-m-d H:i:s', time() + 900);
            mysqli_query($con, "UPDATE lms_students SET otp_code = '$otp', otp_expires_at = '$exp' WHERE id = {$existing['id']}");
            lms_send_otp_email($con, $email, $name, $otp);

            json_res(true, 'An account with this email is pending verification. A fresh OTP was sent to your Gmail.', [
                'requires_otp' => true,
                'email' => $email,
                'user_id' => $existing['id']
            ], 200);
        }

        json_res(false, 'An account with this email already exists. Please login.', null, 409);
    }
    $chk->close();

    $smtp_config = lms_get_smtp_config($con);
    $requires_otp = $smtp_config['enabled'];
    $email_verified = $requires_otp ? 0 : 1;
    $otp = $requires_otp ? strval(rand(100000, 999999)) : null;
    $exp = $requires_otp ? date('Y-m-d H:i:s', time() + 900) : null;

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $con->prepare("INSERT INTO lms_students (name, email, password, phone, status, email_verified, otp_code, otp_expires_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?)");
    $ins->bind_param("ssssiss", $name, $email, $hash, $phone, $email_verified, $otp, $exp);

    if ($ins->execute()) {
        $userId = $ins->insert_id;

        if ($requires_otp) {
            lms_send_otp_email($con, $email, $name, $otp);
            json_res(true, 'Registration initiated! Please enter the 6-digit OTP sent to your Gmail.', [
                'requires_otp' => true,
                'email' => $email,
                'user_id' => $userId
            ], 200);
        } else {
            $token = generate_token($userId, 'student');
            json_res(true, 'Account created successfully!', [
                'requires_otp' => false,
                'user' => [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'user_type' => 'student'
                ],
                'token' => $token
            ], 201);
        }
    } else {
        json_res(false, 'Failed to create account.', null, 500);
    }
}

// 2.2 Verify Registration OTP
if ($action === 'verify_otp' || $action === 'student_verify_otp') {
    $email = trim($data['email'] ?? '');
    $otp = trim($data['otp'] ?? ($data['otp_code'] ?? ''));

    if (empty($email) || empty($otp)) {
        json_res(false, 'Email and 6-digit OTP are required.', null, 400);
    }

    $stmt = $con->prepare("SELECT * FROM lms_students WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $user = $res->fetch_assoc()) {
        if (empty($user['otp_code']) || $user['otp_code'] !== $otp) {
            json_res(false, 'Invalid verification code. Please check and try again.', null, 400);
        }

        if (!empty($user['otp_expires_at']) && strtotime($user['otp_expires_at']) < time()) {
            json_res(false, 'OTP has expired. Please tap Resend OTP to get a new code.', null, 400);
        }

        $uid = intval($user['id']);
        mysqli_query($con, "UPDATE lms_students SET email_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = $uid");

        $token = generate_token($uid, 'student');
        unset($user['password']);
        unset($user['otp_code']);
        unset($user['otp_expires_at']);
        $user['email_verified'] = 1;
        $user['user_type'] = 'student';

        json_res(true, 'Email verified successfully! Welcome to LKVM Academy.', [
            'user' => $user,
            'token' => $token
        ]);
    } else {
        json_res(false, 'No account found with this email.', null, 404);
    }
}

// 2.3 Resend Verification OTP
if ($action === 'resend_otp') {
    $email = trim($data['email'] ?? '');
    if (empty($email)) {
        json_res(false, 'Email address is required.', null, 400);
    }

    $stmt = $con->prepare("SELECT id, name, email FROM lms_students WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $user = $res->fetch_assoc()) {
        $otp = strval(rand(100000, 999999));
        $exp = date('Y-m-d H:i:s', time() + 900);
        $uid = intval($user['id']);
        mysqli_query($con, "UPDATE lms_students SET otp_code = '$otp', otp_expires_at = '$exp' WHERE id = $uid");

        $mailRes = lms_send_otp_email($con, $user['email'], $user['name'], $otp);
        json_res(true, 'A new 6-digit OTP has been sent to your Gmail.', [
            'email' => $email,
            'delivered' => $mailRes['success']
        ]);
    } else {
        json_res(false, 'No account found with this email.', null, 404);
    }
}

// 2.5 Google Sign-In (SSO)
if ($action === 'google_login' || $action === 'google_signin') {
    $email = trim($data['email'] ?? '');
    $name = trim($data['name'] ?? '');

    if (empty($email)) {
        json_res(false, 'Google account email is required.', null, 400);
    }

    $stmt = $con->prepare("SELECT * FROM lms_students WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $user = $res->fetch_assoc()) {
        if ($user['status'] == 0) {
            json_res(false, 'Your account is deactivated.', null, 403);
        }
        $userId = $user['id'];
    } else {
        // Auto-register student via Google
        $displayName = !empty($name) ? $name : explode('@', $email)[0];
        $dummyPass = password_hash('Google_' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $ins = $con->prepare("INSERT INTO lms_students (name, email, password, status) VALUES (?, ?, ?, 1)");
        $ins->bind_param("sss", $displayName, $email, $dummyPass);
        $ins->execute();
        $userId = $ins->insert_id;
        $ins->close();

        $user = [
            'id' => $userId,
            'name' => $displayName,
            'email' => $email,
            'phone' => '',
            'status' => 1
        ];
    }
    $stmt->close();

    $token = generate_token($userId, 'student');
    unset($user['password']);
    $user['user_type'] = 'student';

    json_res(true, 'Google Sign-In successful!', [
        'user' => $user,
        'token' => $token
    ]);
}

// 3. Admin / Instructor Login
if ($action === 'admin_login' || $action === 'instructor_login') {
    $username = trim($data['username'] ?? ($data['email'] ?? ''));
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        json_res(false, 'Please provide username/email and password.', null, 400);
    }

    $stmt = $con->prepare("SELECT * FROM lms_admins WHERE (username = ? OR email = ?) AND status = 1 LIMIT 1");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $admin = $res->fetch_assoc()) {
        if (password_verify($password, $admin['password'])) {
            $token = generate_token($admin['id'], 'admin');
            unset($admin['password']);
            $admin['user_type'] = 'admin';
            json_res(true, 'Admin login successful!', [
                'user' => $admin,
                'token' => $token
            ]);
        } else {
            json_res(false, 'Invalid credentials.', null, 401);
        }
    } else {
        json_res(false, 'Administrator account not found.', null, 404);
    }
}

// 4. Current Profile & Verification
if ($action === 'profile' || $action === 'me') {
    $user = get_auth_user($con);
    if (!$user) {
        json_res(false, 'Unauthorized or session expired.', null, 401);
    }
    json_res(true, 'User authenticated.', ['user' => $user]);
}

json_res(false, 'Invalid action specified.', null, 400);
