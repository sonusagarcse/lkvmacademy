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

    $chk = $con->prepare("SELECT id FROM lms_students WHERE email = ? LIMIT 1");
    $chk->bind_param("s", $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        json_res(false, 'An account with this email already exists.', null, 409);
    }
    $chk->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $con->prepare("INSERT INTO lms_students (name, email, password, phone, status) VALUES (?, ?, ?, ?, 1)");
    $ins->bind_param("ssss", $name, $email, $hash, $phone);

    if ($ins->execute()) {
        $userId = $ins->insert_id;
        $token = generate_token($userId, 'student');
        json_res(true, 'Account created successfully!', [
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'user_type' => 'student'
            ],
            'token' => $token
        ], 201);
    } else {
        json_res(false, 'Failed to create account.', null, 500);
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
