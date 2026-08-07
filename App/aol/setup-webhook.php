<?php
// setup-webhook.php — Run ONCE on live server, then DELETE this file
require 'config.php';

$url    = APP_URL . '/webhook.php';
$result = _tg_post('setWebhook', ['url' => $url]);

echo '<style>body{font-family:Arial,sans-serif;padding:30px;max-width:600px}code{background:#f5f5f5;padding:2px 6px;border-radius:4px}</style>';
echo "<h2>AOL Bot — Webhook Setup</h2>";
echo "<p>Setting webhook to:<br><code>{$url}</code></p>";
echo '<pre style="background:#f5f5f5;padding:16px;border-radius:6px">' . json_encode($result, JSON_PRETTY_PRINT) . '</pre>';
echo $result['ok']
    ? '<p style="color:green;font-size:18px">✅ Webhook set! Delete this file now.</p>'
    : '<p style="color:red;font-size:18px">❌ Failed — update APP_URL in config.php to your live HTTPS domain.</p>';
