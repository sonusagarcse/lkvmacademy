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

    json_res(true, 'Payment settings fetched', [
        'site_name' => $site_name,
        'currency_symbol' => $currency_symbol,
        'currency_code' => $currency_code,
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

// POST: Update Gateway Settings from Admin Panel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($con);
    if (!$user || !in_array($user['role'], ['admin', 'superadmin', 'instructor'])) {
        json_res(false, 'Unauthorized. Admin credentials required.', null, 401);
    }

    $data = get_request_data();
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

    json_res(true, 'Payment gateway settings saved successfully.');
}
