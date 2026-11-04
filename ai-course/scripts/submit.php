<?php
/**
 * Course Form Handler
 * Submits lead to Bitrix24 CRM
 * SOURCE_ID: WEBFORM (CRM-form)
 */

require_once __DIR__ . '/config.php';

// CORS headers for same-site requests
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://systemmatica.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    // Try form data
    $data = [
        'name' => $_POST['name'] ?? '',
        'contact' => $_POST['contact'] ?? '',
        'email' => $_POST['email'] ?? '',
        'comment' => $_POST['comment'] ?? '',
        'tariff' => $_POST['tariff'] ?? ''
    ];
}

// Resolve tariff label
$tariffLabels = ['standard' => 'Стандарт', 'standard_plus' => 'Стандарт+', 'turnkey' => 'Под ключ', 'question' => 'Вопрос'];
$tariffValue = $data['tariff'] ?? 'question';
$tariffLabel = $tariffLabels[$tariffValue] ?? 'Вопрос';
$isQuestion = ($tariffValue === 'question');

// Validate required fields
if (empty($data['name']) || empty($data['contact'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Name and contact are required']);
    exit;
}

// Determine if contact is phone or telegram
$isTelegram = strpos($data['contact'], '@') === 0 || strpos($data['contact'], 't.me') !== false;

// Clean up telegram handle
$telegramHandle = '';
if ($isTelegram) {
    $telegramHandle = $data['contact'];
    if (strpos($telegramHandle, 't.me/') !== false) {
        $telegramHandle = '@' . explode('t.me/', $telegramHandle)[1];
    }
    if (strpos($telegramHandle, '@') !== 0) {
        $telegramHandle = '@' . $telegramHandle;
    }
}

// Prepare Bitrix lead data
$leadData = [
    'fields' => [
        'TITLE' => ($isQuestion ? '❓ ' : '') . 'Course 2026 [' . $tariffLabel . ']: ' . $data['name'],
        'NAME' => $data['name'],
        'SOURCE_ID' => 'WEBFORM',
        'SOURCE_DESCRIPTION' => 'Course Landing Page systemmatica.com/ai-course/',
        'COMMENTS' => 'Тариф: ' . $tariffLabel . "\nКонтакт: " . $data['contact'] . ($data['comment'] ? "\n\nКомментарий: " . $data['comment'] : ''),
    ]
];

// Add phone if not telegram
if (!$isTelegram && !empty($data['contact'])) {
    // Clean phone number
    $phone = preg_replace('/[^\d+]/', '', $data['contact']);
    $leadData['fields']['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'MOBILE']];
}

// Add telegram to custom field UF_CRM_1676310702 (Telegram link)
if ($isTelegram) {
    $telegramUrl = 'https://t.me/' . ltrim($telegramHandle, '@');
    $leadData['fields']['UF_CRM_1676310702'] = [$telegramUrl];
}

// Add email if provided
if (!empty($data['email'])) {
    $leadData['fields']['EMAIL'] = [['VALUE' => $data['email'], 'VALUE_TYPE' => 'WORK']];
}

// Submit to Bitrix24
$result = callBitrixAPI('crm.lead.add', $leadData);

// Log for debugging
error_log("Course form submission: " . json_encode([
    'data' => $data,
    'leadData' => $leadData,
    'result' => $result,
]));

// Build Telegram notification
$contactInfo = $isTelegram ? $telegramHandle : $data['contact'];
$message = $isQuestion ? "<b>❓ Вопрос по курсу</b>\n\n" : "<b>🎓 Новая заявка на курс!</b>\n\n";
if (!$isQuestion) {
    $message .= "<b>Тариф:</b> " . htmlspecialchars($tariffLabel) . "\n";
}
$message .= "<b>Имя:</b> " . htmlspecialchars($data['name']) . "\n";
$message .= "<b>Контакт:</b> " . htmlspecialchars($contactInfo) . "\n";
if (!empty($data['email'])) {
    $message .= "<b>Email:</b> " . htmlspecialchars($data['email']) . "\n";
}
if (!empty($data['comment'])) {
    $message .= "<b>Комментарий:</b> " . htmlspecialchars($data['comment']) . "\n";
}

if ($result && isset($result['result'])) {
    $leadId = $result['result'];
    $leadUrl = "https://b24-hv5wr2.bitrix24.ru/crm/lead/details/{$leadId}/";
    $message .= "\n<a href=\"{$leadUrl}\">Открыть лид #{$leadId}</a>";
} else {
    // CRM failed — note it in notification, but don't block the user
    $errorMsg = $result['error_description'] ?? $result['error'] ?? 'unknown';
    $message .= "\n⚠️ Лид в CRM не создан: " . htmlspecialchars($errorMsg);
    error_log("Bitrix CRM error: " . json_encode($result));
}

sendTelegramNotification($message);

echo json_encode([
    'success' => true,
    'lead_id' => $result['result'] ?? null
]);
