<?php
/**
 * LKVM LMS - SMTP Email Engine & Notification System
 * 
 * High-performance, standalone SMTP socket client (No Composer/PHPMailer required).
 * Supports Gmail, Hostinger, cPanel, SendGrid with TLS/SSL encryption and AUTH LOGIN.
 */

if (!function_exists('lms_ensure_email_schema')) {
function lms_ensure_email_schema($con) {
    if (!$con) return;

    // 1. Ensure OTP & verification columns exist in lms_students
    $chk_ver = mysqli_query($con, "SHOW COLUMNS FROM lms_students LIKE 'email_verified'");
    if ($chk_ver && mysqli_num_rows($chk_ver) == 0) {
        @mysqli_query($con, "ALTER TABLE lms_students ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`");
    }

    $chk_otp = mysqli_query($con, "SHOW COLUMNS FROM lms_students LIKE 'otp_code'");
    if ($chk_otp && mysqli_num_rows($chk_otp) == 0) {
        @mysqli_query($con, "ALTER TABLE lms_students ADD COLUMN `otp_code` VARCHAR(10) DEFAULT NULL AFTER `email_verified`");
    }

    $chk_exp = mysqli_query($con, "SHOW COLUMNS FROM lms_students LIKE 'otp_expires_at'");
    if ($chk_exp && mysqli_num_rows($chk_exp) == 0) {
        @mysqli_query($con, "ALTER TABLE lms_students ADD COLUMN `otp_expires_at` DATETIME DEFAULT NULL AFTER `otp_code`");
    }

    // 2. Ensure default SMTP settings exist in lms_settings
    $defaults = [
        'smtp_enabled'    => '0',
        'smtp_host'       => 'smtp.gmail.com',
        'smtp_port'       => '587',
        'smtp_username'   => '',
        'smtp_password'   => '',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => 'info@lkvmbihar.in',
        'smtp_from_name'  => 'LKVM Academy'
    ];

    foreach ($defaults as $k => $v) {
        $k_esc = mysqli_real_escape_string($con, $k);
        $chk = mysqli_query($con, "SELECT id FROM lms_settings WHERE setting_key = '$k_esc' LIMIT 1");
        if ($chk && mysqli_num_rows($chk) == 0) {
            $v_esc = mysqli_real_escape_string($con, $v);
            mysqli_query($con, "INSERT INTO lms_settings (setting_key, setting_value) VALUES ('$k_esc', '$v_esc')");
        }
    }
}
}

/**
 * Get configured SMTP settings array from DB.
 */
function lms_get_smtp_config($con) {
    lms_ensure_email_schema($con);
    return [
        'enabled'    => (lms_get_setting($con, 'smtp_enabled', '0') === '1'),
        'host'       => trim(lms_get_setting($con, 'smtp_host', 'smtp.gmail.com')),
        'port'       => intval(lms_get_setting($con, 'smtp_port', '587')),
        'username'   => trim(lms_get_setting($con, 'smtp_username', '')),
        'password'   => trim(lms_get_setting($con, 'smtp_password', '')),
        'encryption' => strtolower(trim(lms_get_setting($con, 'smtp_encryption', 'tls'))),
        'from_email' => trim(lms_get_setting($con, 'smtp_from_email', 'info@lkvmbihar.in')),
        'from_name'  => trim(lms_get_setting($con, 'smtp_from_name', 'LKVM Academy')),
    ];
}

/**
 * Low-level Standalone Socket SMTP Dispatcher.
 */
function lms_send_smtp_socket($config, $toEmail, $toName, $subject, $htmlBody) {
    $host = $config['host'];
    $port = $config['port'];
    $encryption = $config['encryption'];
    $username = $config['username'];
    $password = $config['password'];
    $fromEmail = !empty($config['from_email']) ? $config['from_email'] : $username;
    $fromName = !empty($config['from_name']) ? $config['from_name'] : 'LKVM Academy';

    if (empty($host) || empty($port)) {
        return ['success' => false, 'message' => 'SMTP Host or Port is not configured.'];
    }

    $timeout = 15;
    $socketHost = ($encryption === 'ssl') ? "ssl://$host" : $host;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client("$socketHost:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'message' => "Could not connect to SMTP server $host:$port ($errstr: #$errno)"];
    }

    stream_set_timeout($socket, $timeout);

    $getResponse = function($sock) {
        $data = '';
        while (!feof($sock)) {
            $line = fgets($sock, 515);
            if ($line === false) break;
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $sendCommand = function($sock, $cmd, $expectedCode) use ($getResponse) {
        fputs($sock, $cmd . "\r\n");
        $res = $getResponse($sock);
        $code = substr(trim($res), 0, 3);
        if ($code != $expectedCode) {
            return ['ok' => false, 'res' => $res, 'code' => $code];
        }
        return ['ok' => true, 'res' => $res];
    };

    // 1. Initial greeting
    $greeting = $getResponse($socket);
    if (substr(trim($greeting), 0, 3) != '220') {
        fclose($socket);
        return ['success' => false, 'message' => "Invalid greeting from SMTP server: $greeting"];
    }

    // 2. EHLO
    $clientHost = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    $r = $sendCommand($socket, "EHLO $clientHost", '250');
    if (!$r['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "EHLO failed: " . $r['res']];
    }

    // 3. STARTTLS if required
    if ($encryption === 'tls') {
        $r = $sendCommand($socket, "STARTTLS", '220');
        if (!$r['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "STARTTLS negotiation failed: " . $r['res']];
        }

        $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$crypto) {
            fclose($socket);
            return ['success' => false, 'message' => "TLS encryption handshake failed with server."];
        }

        // Resend EHLO after TLS handshake
        $r = $sendCommand($socket, "EHLO $clientHost", '250');
        if (!$r['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "EHLO after TLS failed: " . $r['res']];
        }
    }

    // 4. AUTH LOGIN
    if (!empty($username) && !empty($password)) {
        $r = $sendCommand($socket, "AUTH LOGIN", '334');
        if (!$r['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "AUTH LOGIN rejected: " . $r['res']];
        }

        $r = $sendCommand($socket, base64_encode($username), '334');
        if (!$r['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "Username rejected by SMTP: " . $r['res']];
        }

        $r = $sendCommand($socket, base64_encode($password), '235');
        if (!$r['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "SMTP Authentication failed. For Gmail, make sure to use an App Password (not your normal password): " . $r['res']];
        }
    }

    // 5. MAIL FROM
    $r = $sendCommand($socket, "MAIL FROM:<$fromEmail>", '250');
    if (!$r['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "MAIL FROM rejected: " . $r['res']];
    }

    // 6. RCPT TO
    $r = $sendCommand($socket, "RCPT TO:<$toEmail>", '250');
    if (!$r['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "Recipient <$toEmail> rejected: " . $r['res']];
    }

    // 7. DATA
    $r = $sendCommand($socket, "DATA", '354');
    if (!$r['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "DATA command rejected: " . $r['res']];
    }

    // 8. Headers & Body
    $headers = [];
    $headers[] = "Date: " . date('r');
    $headers[] = "To: =?UTF-8?B?" . base64_encode($toName) . "?= <$toEmail>";
    $headers[] = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>";
    $headers[] = "Reply-To: <$fromEmail>";
    $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
    $headers[] = "Message-ID: <" . md5(uniqid(microtime(), true)) . "@$clientHost>";
    $headers[] = "X-Mailer: LKVM Academy SMTP Engine";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: base64";

    $messagePayload = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n.";

    fputs($socket, $messagePayload . "\r\n");
    $dataRes = $getResponse($socket);

    if (substr(trim($dataRes), 0, 3) != '250') {
        fclose($socket);
        return ['success' => false, 'message' => "Message body rejected by SMTP: $dataRes"];
    }

    // 9. QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return ['success' => true, 'message' => "Email sent successfully to $toEmail via SMTP!"];
}

/**
 * Main General Email Sender.
 */
function lms_send_email($con, $toEmail, $toName, $subject, $htmlContent) {
    $config = lms_get_smtp_config($con);

    if ($config['enabled']) {
        return lms_send_smtp_socket($config, $toEmail, $toName, $subject, $htmlContent);
    }

    // Fallback: Use standard PHP mail() if SMTP is not enabled
    $fromEmail = !empty($config['from_email']) ? $config['from_email'] : 'no-reply@lkvmbihar.in';
    $fromName = !empty($config['from_name']) ? $config['from_name'] : 'LKVM Academy';

    $headers = [
        "MIME-Version: 1.0",
        "Content-type: text/html; charset=UTF-8",
        "From: $fromName <$fromEmail>",
        "Reply-To: $fromEmail",
        "X-Mailer: PHP/" . phpversion()
    ];

    $sent = @mail($toEmail, $subject, $htmlContent, implode("\r\n", $headers));
    if ($sent) {
        return ['success' => true, 'message' => 'Email sent via server mail handler.'];
    }

    return ['success' => false, 'message' => 'SMTP is disabled and server mail transport is unavailable.'];
}

/**
 * Base Responsive HTML Email Wrapper Template.
 */
function lms_email_template($title, $contentHtml, $footerNote = '') {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { margin: 0; padding: 0; background-color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
            .wrapper { width: 100%; table-layout: fixed; background-color: #0f172a; padding: 40px 0; }
            .container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.25); border: 1px solid #1e293b; }
            .header { background: linear-gradient(135deg, #0052cc 0%, #0062ff 50%, #2563eb 100%); padding: 36px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: 0.5px; }
            .header p { margin: 6px 0 0; color: rgba(255,255,255,0.85); font-size: 13px; font-weight: 500; }
            .body-content { padding: 36px 32px; font-size: 15px; line-height: 1.6; color: #334155; }
            .btn-action { display: inline-block; padding: 14px 32px; background: #0062ff; color: #ffffff !important; font-weight: 700; font-size: 14px; text-decoration: none; border-radius: 12px; margin: 20px 0; box-shadow: 0 6px 16px rgba(0,98,255,0.35); }
            .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 24px 30px; text-align: center; font-size: 12px; color: #64748b; }
            .footer a { color: #0062ff; text-decoration: none; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>LKVM ACADEMY</h1>
                    <p>Advance Diploma in Computer Applications & IT Learning</p>
                </div>
                <div class="body-content">
                    ' . $contentHtml . '
                </div>
                <div class="footer">
                    <p style="margin: 0 0 6px;">' . ($footerNote ?: 'You received this notification regarding your LKVM Academy student account.') . '</p>
                    <p style="margin: 0;">© ' . date('Y') . ' LKVM Computer Education. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * 1. Send OTP Verification Email.
 */
function lms_send_otp_email($con, $toEmail, $toName, $otpCode) {
    $subject = "Your Verification Code: $otpCode - LKVM Academy";

    $content = '
        <h2 style="margin-top:0; color:#0f172a; font-size:22px; font-weight:800;">Verify Your Email Address 🔐</h2>
        <p>Dear <strong>' . htmlspecialchars($toName) . '</strong>,</p>
        <p>Thank you for registering at <strong>LKVM Academy</strong>! To complete your registration and secure your student account, please enter the following 6-digit One-Time Password (OTP):</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <div style="display: inline-block; background: #f1f5f9; border: 2px dashed #0062ff; border-radius: 16px; padding: 18px 36px;">
                <span style="font-size: 36px; font-weight: 900; letter-spacing: 8px; color: #0062ff; font-family: monospace;">' . htmlspecialchars($otpCode) . '</span>
            </div>
            <p style="margin: 10px 0 0; font-size: 12px; color: #64748b; font-weight: 600;">Valid for 15 minutes only</p>
        </div>

        <div style="background: #eff6ff; border-left: 4px solid #0062ff; padding: 14px 18px; border-radius: 8px; margin: 20px 0; font-size: 13px; color: #1e40af;">
            <strong>Important Security Advice:</strong> Never share this OTP with anyone. LKVM staff will never ask for your verification code or account password.
        </div>
        <p>If you did not initiate this registration, please disregard this email.</p>
    ';

    $html = lms_email_template("Email Verification OTP", $content);
    return lms_send_email($con, $toEmail, $toName, $subject, $html);
}

/**
 * 2. Send Course Purchase / Enrollment Confirmation Email.
 */
function lms_send_enrollment_email($con, $toEmail, $toName, $courseTitle, $amount, $paymentId, $courseSlug) {
    $subject = "Enrollment Confirmed: $courseTitle - LKVM Academy";
    $formattedAmount = ($amount > 0) ? "₹" . number_format($amount, 2) : "FREE";
    $portalUrl = "https://courses.lkvmbihar.in/learn?slug=" . urlencode($courseSlug);

    $content = '
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display:inline-block; width:60px; height:60px; line-height:60px; border-radius:50%; background:#ecfdf5; color:#059669; font-size:28px;">✓</div>
            <h2 style="margin: 12px 0 4px; color:#0f172a; font-size:22px; font-weight:800;">Course Enrollment Successful! 🎉</h2>
            <p style="margin:0; color:#64748b; font-size:14px;">Welcome to your new learning journey</p>
        </div>

        <p>Dear <strong>' . htmlspecialchars($toName) . '</strong>,</p>
        <p>Congratulations! You are now officially enrolled in <strong>' . htmlspecialchars($courseTitle) . '</strong>. All course lectures, downloadable study notes, and assignments are unlocked for you.</p>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; margin: 24px 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Course:</td>
                    <td style="padding: 6px 0; color: #0f172a; font-weight: 700; text-align: right;">' . htmlspecialchars($courseTitle) . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Amount Paid:</td>
                    <td style="padding: 6px 0; color: #059669; font-weight: 800; text-align: right;">' . $formattedAmount . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Transaction Reference:</td>
                    <td style="padding: 6px 0; color: #0f172a; font-family: monospace; font-size: 12px; text-align: right;">' . htmlspecialchars($paymentId) . '</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Enrollment Date:</td>
                    <td style="padding: 6px 0; color: #0f172a; text-align: right;">' . date('d M Y, h:i A') . '</td>
                </tr>
            </table>
        </div>

        <div style="text-align: center; margin: 28px 0;">
            <a href="' . $portalUrl . '" class="btn-action">Start Learning Now →</a>
        </div>

        <p style="font-size: 13px; color: #64748b;">You can also access your course lectures anytime from the <strong>LKVM Mobile App</strong> or student web dashboard.</p>
    ';

    $html = lms_email_template("Course Enrollment Confirmation", $content);
    return lms_send_email($con, $toEmail, $toName, $subject, $html);
}

/**
 * 3. Send Course Completion & Certificate Email.
 */
function lms_send_course_completed_email($con, $toEmail, $toName, $courseTitle, $certUid) {
    $subject = "🎓 Congratulations on Completing $courseTitle! - Your Certificate is Ready";
    $certUrl = "https://courses.lkvmbihar.in/certificate-view.php?uid=" . urlencode($certUid);

    $content = '
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display:inline-block; width:64px; height:64px; line-height:64px; border-radius:50%; background:#fef3c7; color:#d97706; font-size:32px;">🎓</div>
            <h2 style="margin: 14px 0 4px; color:#0f172a; font-size:22px; font-weight:800;">Congratulations, Graduate! 🏆</h2>
            <p style="margin:0; color:#64748b; font-size:14px;">100% Course Curriculum Completed</p>
        </div>

        <p>Dear <strong>' . htmlspecialchars($toName) . '</strong>,</p>
        <p>We are thrilled to congratulate you on successfully completing all video lectures and requirements for <strong>' . htmlspecialchars($courseTitle) . '</strong>!</p>

        <p>Your dedication and commitment to advancing your technical skills have paid off. Your verified <strong>Certificate of Completion</strong> has been officially issued and registered in our public credential registry.</p>

        <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; border-radius: 16px; padding: 22px; margin: 24px 0; text-align: center;">
            <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: #f59e0b; font-weight: 800;">Verified Credential ID</div>
            <div style="font-size: 20px; font-weight: 800; font-family: monospace; margin: 8px 0; letter-spacing: 1px;">' . htmlspecialchars($certUid) . '</div>
            <div style="font-size: 12px; color: rgba(255,255,255,0.7);">Issued by LKVM Computer Education Examination Board</div>
        </div>

        <div style="text-align: center; margin: 28px 0;">
            <a href="' . $certUrl . '" class="btn-action" style="background:#f59e0b; box-shadow:0 6px 16px rgba(245,158,11,0.4);">View & Download Official Certificate →</a>
        </div>

        <p style="font-size: 13px; color: #64748b;">You can download the high-resolution PDF or print this certificate to showcase on your resume, LinkedIn profile, and job applications.</p>
    ';

    $html = lms_email_template("Course Completion & Certificate", $content);
    return lms_send_email($con, $toEmail, $toName, $subject, $html);
}

/**
 * 4. Admin Diagnostic: Test SMTP Connection.
 */
function lms_test_smtp_connection($con, $testEmail) {
    $config = lms_get_smtp_config($con);

    if (!$config['enabled']) {
        return ['success' => false, 'message' => 'SMTP is currently disabled. Please enable SMTP and enter credentials before testing.'];
    }

    $subject = "LKVM Academy SMTP Live Test Delivery";
    $content = '
        <h2 style="color:#059669; margin-top:0;">✓ SMTP Configuration Test Successful!</h2>
        <p>This test email confirms that your SMTP mail server settings are configured correctly in the <strong>LKVM LMS Admin Panel</strong>.</p>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin:20px 0; font-size:13px;">
            <strong>Server Handshake Details:</strong><br>
            • SMTP Host: ' . htmlspecialchars($config['host']) . '<br>
            • Port: ' . htmlspecialchars($config['port']) . '<br>
            • Encryption: ' . strtoupper(htmlspecialchars($config['encryption'])) . '<br>
            • From Address: ' . htmlspecialchars($config['from_email']) . '<br>
            • Timestamp: ' . date('r') . '
        </div>
        <p>Transactional emails for Student OTP Verification, Course Purchases, and Certificates are active and ready.</p>
    ';

    $html = lms_email_template("SMTP Test Delivery", $content);
    return lms_send_smtp_socket($config, $testEmail, 'LMS Administrator', $subject, $html);
}
