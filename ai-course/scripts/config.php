<?php
/**
 * Configuration for course landing form handler
 * Defines callBitrixAPI() and sendTelegramNotification()
 */

define('BITRIX_DOMAIN', 'b24-hv5wr2.bitrix24.ru');
define('BITRIX_WEBHOOK_TOKEN', '1/3deoa6puxzhc9c91');
define('TELEGRAM_BOT_TOKEN', '8287926398:AAH_xdZbcB6bRN-XC-9tCc6HrbmvqpmdxEY');
define('TELEGRAM_CHAT_ID', '330486332'); // Petr's DM

/**
 * Call Bitrix24 REST API via webhook
 */
function callBitrixAPI($method, $params = []) {
    $url = 'https://' . BITRIX_DOMAIN . '/rest/' . BITRIX_WEBHOOK_TOKEN . '/' . $method;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Bitrix API error: {$error}");
        return null;
    }

    return json_decode($response, true);
}

/**
 * Send notification to Telegram via Bot API
 */
function sendTelegramNotification($message) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Telegram notification error: {$error}");
    }
}
