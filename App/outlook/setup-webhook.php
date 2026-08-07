<?php
// setup-webhook.php — Run ONCE to register Telegram webhook, then DELETE this file
require 'config.php';

$webhook_url = APP_URL . '/webhook.php';
$result = _tg_post('setWebhook', ['url' => $webhook_url]);

echo '<style>body{font-family:Arial,sans-serif;padding:30px;max-width:600px}</style>';
echo "<h2>Outlook Bot — Webhook Setup</h2>";
echo "<p>Setting webhook to:<br><code>{$webhook_url}</code></p>";
echo '<pre>' . json_encode($result, JSON_PRETTY_PRINT) . '</pre>';

if (!empty($result['ok'])) {
    echo '<p style="color:green;font-size:18px">✅ Webhook registered! Delete this file now.</p>';
} else {
    echo '<p style="color:red;font-size:18px">❌ Failed. Update APP_URL in config.php to your live HTTPS domain.</p>';
}
