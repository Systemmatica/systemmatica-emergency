<?php
/**
 * T-Bank Payment Handler for AI Course
 * Handles both card (redirect to PaymentURL) and SBP (QR code)
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://systemmatica.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$leadId = $input['lead_id'] ?? null;
$amount = $input['amount'] ?? null;
$description = $input['description'] ?? 'Course AI-Assistant';
$paymentType = $input['payment_type'] ?? 'card'; // 'card' or 'sbp'
$email = $input['email'] ?? null;

if (!$amount || !$leadId) {
    http_response_code(400);
    echo json_encode(['error' => 'lead_id and amount are required']);
    exit;
}

$orderId = "cr-{$leadId}-" . time();

// Step 1: Init payment
$initParams = [
    'TerminalKey' => TBANK_TERMINAL_KEY,
    'Amount' => (int)$amount,
    'OrderId' => $orderId,
    'Description' => $description,
    'SuccessURL' => TBANK_SUCCESS_URL,
    'FailURL' => TBANK_FAIL_URL,
    'NotificationURL' => TBANK_NOTIFICATION_URL,
];

// Pass email for electronic receipt (T-Bank sends check to this email)
if ($email) {
    $initParams['DATA'] = ['Email' => $email];
    $initParams['Receipt'] = [
        'Email' => $email,
        'Taxation' => 'usn_income', // USN (simplified tax system)
        'Items' => [[
            'Name' => $description,
            'Price' => (int)$amount,
            'Quantity' => 1,
            'Amount' => (int)$amount,
            'Tax' => 'none',
            'PaymentMethod' => 'full_prepayment',
            'PaymentObject' => 'service',
        ]],
    ];
}

$initParams['Token'] = generateToken($initParams, TBANK_TERMINAL_PASSWORD);
$initResponse = callTBankAPI(TBANK_API_URL . '/Init', $initParams);

if (!$initResponse || !isset($initResponse['Success']) || !$initResponse['Success']) {
    $errorMsg = $initResponse['Message'] ?? 'Init failed';
    $errorDetail = $initResponse['Details'] ?? '';
    error_log("T-Bank Init failed: " . json_encode($initResponse));
    http_response_code(500);
    echo json_encode(['error' => $errorMsg, 'details' => $errorDetail]);
    exit;
}

$paymentId = $initResponse['PaymentId'];
$paymentUrl = $initResponse['PaymentURL'] ?? null;

// For card payments, just return the PaymentURL for redirect
if ($paymentType === 'card') {
    echo json_encode([
        'success' => true,
        'payment_url' => $paymentUrl,
        'payment_id' => $paymentId,
        'order_id' => $orderId,
    ]);
    exit;
}

// For SBP, get QR code
$qrParams = [
    'TerminalKey' => TBANK_TERMINAL_KEY,
    'PaymentId' => (string)$paymentId,
    'DataType' => 'IMAGE',
];

$qrParams['Token'] = generateToken($qrParams, TBANK_TERMINAL_PASSWORD);
$qrResponse = callTBankAPI(TBANK_API_URL . '/GetQr', $qrParams);

if (!$qrResponse || !isset($qrResponse['Success']) || !$qrResponse['Success']) {
    error_log("T-Bank GetQR failed: " . json_encode($qrResponse));
    // Fallback to PaymentURL
    echo json_encode([
        'success' => true,
        'payment_url' => $paymentUrl,
        'payment_id' => $paymentId,
        'order_id' => $orderId,
        'qr' => null,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'payment_id' => $paymentId,
    'order_id' => $orderId,
    'payment_url' => $paymentUrl,
    'qr' => $qrResponse['Data'] ?? null,
]);
