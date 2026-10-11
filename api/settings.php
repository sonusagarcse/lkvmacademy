<?php
require_once __DIR__ . '/config.php';

// GET: Fetch Active Payment Gateway Options & Platform Settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');
    $currency_symbol = lms_get_setting($con, 'currency_symbol', '₹');
    $currency_code = lms_get_setting($con, 'currency_code', 'INR');
    
    $enable_upi = lms_get_setting($con, 'enable_upi', '1') === '1';
    $enable_razorpay = lms_get_setting($con, 'enable_razorpay', '1') === '1';
    $enable_card = lms_get_setting($con, 'enable_card', '0') === '1';

    $upi_id = lms_get_setting($con, 'upi_id', 'lkvm@upi');
    $upi_name = lms_get_setting($con, 'upi_name', 'LKVM Computer Education');
    $razorpay_key_id = lms_get_setting($con, 'razorpay_key_id', '');

    // List of ONLY enabled gateways for checkout display
    $enabled_gateways = [];
    if ($enable_upi) {
        $enabled_gateways[] = [
            'id' => 'upi',
            'title' => 'UPI Instant QR / UTR',
            'subtitle' => 'GooglePay, PhonePe, Paytm, BHIM UPI',
            'icon' => 'qr_code_2_rounded',
            'enabled' => true,
            'upi_id' => $upi_id,
            'upi_name' => $upi_name,
        ];
    }
    if ($enable_razorpay) {
        $enabled_gateways[] = [
            'id' => 'razorpay',
            'title' => 'Razorpay Online Gateway',
            'subtitle' => 'Net Banking, Wallets & Cards',
            'icon' => 'bolt_rounded',
            'enabled' => true,
            'key_id' => $razorpay_key_id,
        ];
    }
    if ($enable_card) {
        $enabled_gateways[] = [
            'id' => 'card',
            'title' => 'Credit / Debit Card',
            'subtitle' => 'Visa, MasterCard, RuPay',
            'icon' => 'credit_card_rounded',
            'enabled' => true,
        ];
    }

    $smtp_enabled = lms_get_setting($con, 'smtp_enabled', '0') === '1';
    $smtp_host = lms_get_setting($con, 'smtp_host', 'smtp.gmail.com');
    $smtp_port = lms_get_setting($con, 'smtp_port', '587');
    $smtp_username = lms_get_setting($con, 'smtp_username', '');
    $smtp_encryption = lms_get_setting($con, 'smtp_encryption', 'tls');
    $smtp_from_email = lms_get_setting($con, 'smtp_from_email', 'info@lkvmbihar.in');
    $smtp_from_name = lms_get_setting($con, 'smtp_from_name', 'LKVM Academy');

    json_res(true, 'Payment and platform settings fetched', [
        'site_name' => $site_name,
        'currency_symbol' => $currency_symbol,
        'currency_code' => $currency_code,
        'smtp' => [
            'enabled' => $smtp_enabled,
            'host' => $smtp_host,
            'port' => $smtp_port,
            'encryption' => $smtp_encryption,
            'from_email' => $smtp_from_email,
            'from_name' => $smtp_from_name,
        ],
        'gateways' => $enabled_gateways,
        'all_gateways' => [
            'upi' => [
                'id' => 'upi',
                'title' => 'Direct UPI & QR Code',
                'enabled' => $enable_upi,
                'upi_id' => $upi_id,
                'upi_name' => $upi_name,
            ],
            'razorpay' => [
                'id' => 'razorpay',
                'title' => 'Razorpay Gateway',
                'enabled' => $enable_razorpay,
                'key_id' => $razorpay_key_id,
            ],
            'card' => [
                'id' => 'card',
                'title' => 'Credit / Debit Card',
                'enabled' => $enable_card,
            ],
        ],
    ]);
}

// POST: Update Gateway & SMTP Settings from Admin Panel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($con);
    if (!$user || !in_array($user['role'], ['admin', 'superadmin', 'instructor'])) {
        json_res(false, 'Unauthorized. Admin credentials required.', null, 401);
    }

    $data = get_request_data();
    $action = $data['action'] ?? '';

    // Live Test SMTP Connection
    if ($action === 'test_smtp') {
        $testEmail = trim($data['test_email'] ?? $user['email']);
        $testRes = lms_test_smtp_connection($con, $testEmail);
        json_res($testRes['success'], $testRes['message'], $testRes);
    }

    if (isset($data['enable_upi'])) {
        lms_set_setting($con, 'enable_upi', ($data['enable_upi'] == '1' || $data['enable_upi'] === true) ? '1' : '0');
    }
    if (isset($data['enable_razorpay'])) {
        lms_set_setting($con, 'enable_razorpay', ($data['enable_razorpay'] == '1' || $data['enable_razorpay'] === true) ? '1' : '0');
    }
    if (isset($data['enable_card'])) {
        lms_set_setting($con, 'enable_card', ($data['enable_card'] == '1' || $data['enable_card'] === true) ? '1' : '0');
    }
    if (isset($data['upi_id'])) {
        lms_set_setting($con, 'upi_id', trim($data['upi_id']));
    }
    if (isset($data['upi_name'])) {
        lms_set_setting($con, 'upi_name', trim($data['upi_name']));
    }
    if (isset($data['razorpay_key_id'])) {
        lms_set_setting($con, 'razorpay_key_id', trim($data['razorpay_key_id']));
    }

    // SMTP Settings
    if (isset($data['smtp_enabled'])) {
        lms_set_setting($con, 'smtp_enabled', ($data['smtp_enabled'] == '1' || $data['smtp_enabled'] === true) ? '1' : '0');
    }
    if (isset($data['smtp_host'])) {
        lms_set_setting($con, 'smtp_host', trim($data['smtp_host']));
    }
    if (isset($data['smtp_port'])) {
        lms_set_setting($con, 'smtp_port', trim($data['smtp_port']));
    }
    if (isset($data['smtp_username'])) {
        lms_set_setting($con, 'smtp_username', trim($data['smtp_username']));
    }
    if (isset($data['smtp_password']) && !empty($data['smtp_password'])) {
        lms_set_setting($con, 'smtp_password', trim($data['smtp_password']));
    }
    if (isset($data['smtp_encryption'])) {
        lms_set_setting($con, 'smtp_encryption', trim($data['smtp_encryption']));
    }
    if (isset($data['smtp_from_email'])) {
        lms_set_setting($con, 'smtp_from_email', trim($data['smtp_from_email']));
    }
    if (isset($data['smtp_from_name'])) {
        lms_set_setting($con, 'smtp_from_name', trim($data['smtp_from_name']));
    }

    json_res(true, 'Settings updated successfully.');
}
