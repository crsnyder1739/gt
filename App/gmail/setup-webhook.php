<?php
// setup-webhook.php
// Visit this page ONCE after uploading to your server to register the Telegram webhook
// Then delete this file from your server for security

require 'config.php';

$webhook_url = APP_URL . '/webhook.php';

$result = _tg_post('setWebhook', ['url' => $webhook_url]);

echo '<pre>';
echo "Setting webhook to: {$webhook_url}\n\n";
echo json_encode($result, JSON_PRETTY_PRINT);
echo '</pre>';

if (!empty($result['ok'])) {
    echo '<p style="color:green;font-size:18px">✅ Webhook registered successfully! You can delete this file now.</p>';
} else {
    echo '<p style="color:red;font-size:18px">❌ Failed. Check your APP_URL in config.php</p>';
}
