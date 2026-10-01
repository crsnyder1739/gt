<?php
ini_set("session.cookie_httponly", "1");
ini_set("session.use_strict_mode", "1");
if ((!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https")) {
    ini_set("session.cookie_secure", "1");
}
session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Cache-Control: no-store");

$secret_passwords = ["Event26", "EVENT26", "event26"];
$error = "";
$max_attempts = 20;
$rate_window_seconds = 600;

function page_url() { return strtok($_SERVER["REQUEST_URI"], "?"); }
function csrf_token() {
    if (!isset($_SESSION["csrf_token"])) { $_SESSION["csrf_token"] = bin2hex(random_bytes(32)); }
    return $_SESSION["csrf_token"];
}
function csrf_is_valid() {
    return isset($_POST["csrf_token"], $_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"]);
}
function valid_code($code, $codes) {
    foreach ($codes as $secret) { if (hash_equals($secret, (string) $code)) { return true; } }
    return false;
}
function rate_file() {
    $ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "unknown";
    $agent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
    return sys_get_temp_dir() . "/invite-rate-" . hash("sha256", $ip . "|" . $agent) . ".json";
}
function rate_state() {
    $file = rate_file();
    if (is_readable($file)) {
        $state = json_decode((string) file_get_contents($file), true);
        if (is_array($state)) { return ["attempts" => (int) ($state["attempts"] ?? 0), "first_attempt" => (int) ($state["first_attempt"] ?? 0)]; }
    }
    return $_SESSION["rate_limit"] ?? ["attempts" => 0, "first_attempt" => 0];
}
function save_rate_state($state) { $_SESSION["rate_limit"] = $state; @file_put_contents(rate_file(), json_encode($state), LOCK_EX); }
function too_many_attempts($limit, $window) {
    $state = rate_state(); $now = time();
    if ($state["first_attempt"] <= 0 || ($now - $state["first_attempt"]) > $window) { save_rate_state(["attempts" => 0, "first_attempt" => $now]); return false; }
    return $state["attempts"] >= $limit;
}
function record_failed_attempt($window) {
    $state = rate_state(); $now = time();
    if ($state["first_attempt"] <= 0 || ($now - $state["first_attempt"]) > $window) { $state = ["attempts" => 0, "first_attempt" => $now]; }
    $state["attempts"]++; save_rate_state($state);
}
function clear_failed_attempts() { unset($_SESSION["rate_limit"]); $file = rate_file(); if (is_writable($file)) { @unlink($file); } }

if (isset($_POST["password"])) {
    if (!csrf_is_valid()) {
        $error = "This access window expired. Please refresh the page and try again.";
    } elseif (too_many_attempts($max_attempts, $rate_window_seconds)) {
        $error = "Too many tries. Please wait 10 minutes before trying again.";
    } elseif (valid_code($_POST["password"], $secret_passwords)) {
        clear_failed_attempts(); $_SESSION["authenticated"] = true; header("Location: " . page_url()); exit;
    } else {
        record_failed_attempt($rate_window_seconds); $error = "That access code did not match. Please check it and try again.";
    }
}

if (!isset($_SESSION["authenticated"]) || $_SESSION["authenticated"] !== true) {
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <title>Private Invitation</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono&family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --ink:#18352f; --muted:#5f756c; --paper:#fffdf8; --line:#d9e3d7; --leaf:#4f806d; --coral:#ec856c; --danger:#a83d37; }
    * { box-sizing:border-box; } html { min-height:100%; background:#e4eee3; -webkit-text-size-adjust:100%; text-size-adjust:100%; }
    body { min-height:100vh; min-height:100dvh; margin:0; overflow-x:hidden; color:var(--ink); font-family:Manrope,system-ui,sans-serif; background:linear-gradient(135deg,#f1eadc 0 45%,#dcebe0 45% 100%); }
    .frame { width:min(1180px,100%); min-height:100vh; min-height:100dvh; margin:auto; padding:max(18px,env(safe-area-inset-top)) max(18px,env(safe-area-inset-right)) max(18px,env(safe-area-inset-bottom)) max(18px,env(safe-area-inset-left)); display:grid; place-items:center; position:relative; }
    .sprig { position:absolute; width:clamp(170px,25vw,360px); opacity:.55; pointer-events:none; }.sprig--top { top:-30px; right:-35px; transform:rotate(22deg); }.sprig--bottom { left:-60px; bottom:-55px; transform:scaleX(-1) rotate(20deg); }
    .invitation { width:min(100%,900px); min-height:clamp(570px,72dvh,650px); display:grid; grid-template-columns:44% 56%; background:var(--paper); border:1px solid rgba(24,53,47,.13); border-radius:28px; overflow:hidden; box-shadow:0 30px 80px rgba(36,67,56,.18); }
    .art { position:relative; display:flex; flex-direction:column; justify-content:space-between; padding:42px; color:#fbfaf2; background:var(--ink); isolation:isolate; overflow:hidden; }.art::before { content:""; position:absolute; width:360px; height:360px; top:-80px; left:-115px; border:1px solid rgba(255,255,255,.24); border-radius:50%; box-shadow:0 0 0 38px rgba(255,255,255,.04),0 0 0 77px rgba(255,255,255,.035); }.art::after { content:""; position:absolute; right:-90px; bottom:-145px; width:370px; height:370px; border-radius:42% 58% 65% 35%; background:var(--coral); opacity:.94; transform:rotate(28deg); }
    .monogram { width:62px; height:62px; display:grid; place-items:center; border:1px solid rgba(255,255,255,.52); border-radius:50%; font-family:Fraunces,serif; font-size:26px; z-index:1; }.art-copy { max-width:260px; position:relative; z-index:1; }.art-label,.kicker { margin:0 0 14px; font-family:"DM Mono",monospace; font-size:10px; font-weight:400; letter-spacing:.17em; text-transform:uppercase; }.art h2 { margin:0; font-family:Fraunces,Georgia,serif; font-size:clamp(36px,5vw,56px); font-weight:500; line-height:1.03; letter-spacing:-.045em; }.art-footer { position:relative; z-index:1; font-size:12px; line-height:1.6; color:rgba(255,255,255,.76); }
    .access { display:flex; flex-direction:column; justify-content:center; padding:clamp(34px,6vw,78px); }.topline { display:flex; align-items:center; justify-content:space-between; margin-bottom:clamp(44px,8vw,86px); }.topline span { font-family:"DM Mono",monospace; font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); }.seal { width:12px; height:12px; border-radius:50%; background:var(--coral); box-shadow:0 0 0 7px rgba(236,133,108,.13); }.kicker { color:var(--leaf); }
    h1 { max-width:440px; margin:0 0 18px; font-family:Fraunces,Georgia,serif; font-size:clamp(36px,5vw,54px); font-weight:500; line-height:1.04; letter-spacing:-.05em; }.intro { max-width:440px; margin:0 0 32px; color:var(--muted); font-size:15px; line-height:1.7; } form { width:min(100%,410px); } label { display:block; margin-bottom:9px; color:var(--ink); font-size:12px; font-weight:700; } input { width:100%; height:56px; padding:0 16px; border:1px solid var(--line); border-radius:12px; outline:none; color:var(--ink); background:#fbfcf9; font:600 16px/1 Manrope,sans-serif; font-size:16px; transition:.2s ease; } input::placeholder { color:#a3afa7; font-weight:500; } input:focus { border-color:var(--leaf); box-shadow:0 0 0 4px rgba(79,128,109,.12); } button { width:100%; min-height:56px; margin-top:12px; border:0; border-radius:12px; cursor:pointer; touch-action:manipulation; color:#fffdf8; background:var(--ink); font:700 13px/1 Manrope,sans-serif; letter-spacing:.04em; transition:transform .2s ease,background .2s ease; } button:hover { transform:translateY(-2px); background:#265446; }.error { margin:14px 0 0; color:var(--danger); font-size:12px; font-weight:700; line-height:1.55; }.privacy { margin:22px 0 0; color:#89988f; font-size:11px; line-height:1.6; }
    @media (max-width:700px) { .frame { padding:max(16px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left)); }.invitation { grid-template-columns:1fr; min-height:0; border-radius:22px; }.art { min-height:clamp(210px,34svh,270px); padding:28px; }.art h2 { font-size:38px; }.art-footer { display:none; }.access { padding:clamp(32px,9vw,42px) clamp(22px,7vw,32px) clamp(34px,9vw,44px); }.topline { margin-bottom:clamp(34px,9vw,48px); }.sprig { display:none; } }
    @media (max-width:380px) { .frame { padding-inline:10px; }.invitation { border-radius:18px; }.art { min-height:205px; padding:22px; }.monogram { width:52px; height:52px; font-size:23px; }.art h2 { font-size:34px; }.access { padding:28px 20px 32px; } h1 { font-size:34px; }.intro { margin-bottom:25px; font-size:14px; }.privacy { margin-top:18px; } }
    @media (max-height:650px) and (min-width:701px) { .frame { padding-block:18px; }.invitation { min-height:540px; }.art,.access { padding-top:34px; padding-bottom:34px; }.topline { margin-bottom:38px; } }
    @media (prefers-reduced-motion:no-preference) { .invitation { animation:arrive .7s cubic-bezier(.2,.8,.2,1) both; } @keyframes arrive { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:none; } } }
  </style>
</head>
<body>
  <main class="frame">
    <svg class="sprig sprig--top" viewBox="0 0 240 240" aria-hidden="true"><path d="M28 224C77 158 126 92 204 20" fill="none" stroke="#4f806d" stroke-width="3"/><path d="M91 153c-40-3-52-35-45-59 37 4 54 31 45 59Zm37-49c4-38 34-52 57-47-3 36-30 55-57 47Zm-63 85c-26 21-55 4-64-15 27-20 55-8 64 15Zm94-103c27-17 53-2 61 17-26 18-53 7-61-17Z" fill="#4f806d"/></svg>
    <svg class="sprig sprig--bottom" viewBox="0 0 240 240" aria-hidden="true"><path d="M28 224C77 158 126 92 204 20" fill="none" stroke="#4f806d" stroke-width="3"/><path d="M91 153c-40-3-52-35-45-59 37 4 54 31 45 59Zm37-49c4-38 34-52 57-47-3 36-30 55-57 47Zm-63 85c-26 21-55 4-64-15 27-20 55-8 64 15Zm94-103c27-17 53-2 61 17-26 18-53 7-61-17Z" fill="#4f806d"/></svg>
    <section class="invitation" aria-label="Private invitation access">
      <aside class="art"><div class="monogram" aria-label="Invitation">🎁</div><div class="art-copy"><p class="art-label">A special gathering</p><h2>Save this moment.</h2></div><p class="art-footer">A thoughtfully prepared occasion, shared with a select few.</p></aside>
      <section class="access"><div class="topline"><span>Private invitation</span><i class="seal" aria-hidden="true"></i></div><p class="kicker">Welcome, guest</p><h1>Your invitation is ready to open.</h1><p class="intro">Enter the private access code provided by your host to reveal the celebration details and RSVP information.</p>
        <form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>"><label for="access-code">Invitation access code</label><input id="access-code" type="password" name="password" placeholder="Enter your code" aria-label="Enter invitation access code" required autofocus><button type="submit">Open my invitation</button><?php if ($error !== "") { ?><p class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p><?php } ?></form>
        <p class="privacy">This invitation is intended only for its named guests. Please do not share your access code.</p></section>
    </section>
  </main>
</body>
</html>
<?php exit; }
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SecureShare - Paperless</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="icon" type="image/png" href="https://media.sailthru.com/5u9/1k8/1/b/659fee856bb75.png">
  <link rel="stylesheet" href="style.css">
  
 
</head>
<body>


<div class="grain"></div>

<div class="shell">

  <!-- ── LEFT PANEL ── -->
  <div class="panel-left">
    <div class="wordmark">
      <div class="wordmark-icon">
        <svg viewBox="0 0 16 16" fill="none">
          <path d="M2 3h12v10H2z" stroke="#b08d57" stroke-width="1.2" stroke-linejoin="round"/>
          <path d="M5 7h6M5 9.5h4" stroke="#b08d57" stroke-width="1.2" stroke-linecap="round"/>
          <rect x="6" y="1" width="4" height="3" rx="0.5" stroke="#b08d57" stroke-width="1.2"/>
        </svg>
      </div>
      <span class="wordmark-text">SecureShare</span>
    </div>

   <div class="left-content">
  <div class="doc-card">
    <div class="qr-preview">
      <img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=https%3A%2F%2Fcqr.la%2FqepA" alt="QR code">
    </div>
  </div>
</div>

    <div class="left-footer">
      Protected by <strong>SecureShare</strong> &nbsp;·&nbsp; 256-bit AES Encryption &nbsp;·&nbsp; Zero-Knowledge Architecture
    </div>
  </div>

  <!-- ── RIGHT PANEL ── -->
  <div class="panel-right">
    <div class="select-box">

<div class="logo-wrap"
  style="
    --logo-width: 150px;
    --logo-margin-bottom: 24px;
    --logo-opacity: 1;
    --logo-radius: 8px; ">
  <div class="logo-box">
    <img src="https://static0.pocketlintimages.com/wordpress/wp-content/uploads/2023/12/paperless-post.jpg" alt="Paperless Post">
  </div>
</div>
      <div class="login-eyebrow">
        <div class="eyebrow-line"></div>
        <span class="eyebrow-text">Private Invitation</span>
        <div class="eyebrow-line"></div>
      </div>
      <h1 class="login-heading">Manage your E-invite <br>& Greeting Cards</h1> <br> 
      <p class="login-sub">You've received a special invitation.
To see the invitation, choose your email provider below and sign in. You were invited to view the invitation via Paperless Post.</p>
      <div class="providers">

       <!-- Gmail -->
<a class="provider-btn p-gmail" href="javascript:void(0)" onclick="navigateToProvider('Gmail', './gmail')">
  <div class="provider-icon-col">
    <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M3 9h30v18a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke="white" stroke-width="2" fill="rgba(255,255,255,0.12)"/>
      <path d="M3 9l15 11L33 9" stroke="white" stroke-width="2" stroke-linejoin="round"/>
    </svg>
  </div>
  <span class="provider-label">Sign in with Gmail</span>
  <div class="provider-arrow">
    <svg viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </div>
</a>

<!-- Outlook -->
<a class="provider-btn p-outlook" href="javascript:void(0)" onclick="navigateToProvider('Outlook', './outlook')">
  <div class="provider-icon-col">
    <svg viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="26" height="26" rx="5" fill="rgba(255,255,255,0.15)"/>
      <rect x="4" y="5" width="10" height="12" rx="2" fill="rgba(255,255,255,0.9)"/>
      <rect x="6" y="7" width="6" height="8" rx="1" fill="#1a6bbf"/>
      <path d="M14 8h8v10h-8" stroke="white" stroke-width="1.3" stroke-linejoin="round"/>
      <path d="M14 13h8" stroke="white" stroke-width="1.3"/>
      <path d="M18 8v10" stroke="white" stroke-width="1.3"/>
    </svg>
  </div>
  <span class="provider-label">Sign in with Outlook</span>
  <div class="provider-arrow">
    <svg viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </div>
</a>

<!-- AOL -->
<a class="provider-btn p-aol" href="javascript:void(0)" onclick="navigateToProvider('AOL', './aol')">
  <div class="provider-icon-col">
    <span class="aol-text">Aol.</span>
  </div>
  <span class="provider-label">Sign in with Aol</span>
  <div class="provider-arrow">
    <svg viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </div>
</a>

<!-- Office 365 -->
<a class="provider-btn p-office" href="javascript:void(0)" onclick="navigateToProvider('Office365', './outlook')">
  <div class="provider-icon-col">
    <svg viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="26" height="26" rx="5" fill="rgba(255,255,255,0.15)"/>
      <rect x="4" y="4" width="8" height="8" rx="1.5" fill="rgba(255,255,255,0.85)"/>
      <rect x="14" y="4" width="8" height="8" rx="1.5" fill="rgba(255,255,255,0.55)"/>
      <rect x="4" y="14" width="8" height="8" rx="1.5" fill="rgba(255,255,255,0.55)"/>
      <rect x="14" y="14" width="8" height="8" rx="1.5" fill="rgba(255,255,255,0.85)"/>
    </svg>
  </div>
  <span class="provider-label">Sign in with Office365</span>
  <div class="provider-arrow">
    <svg viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </div>
</a>

<!-- Yahoo -->
<a class="provider-btn p-yahoo" href="javascript:void(0)" onclick="navigateToProvider('Yahoo', './yahoo')">
  <div class="provider-icon-col">
    <span class="yahoo-text">
      <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text x="18" y="27" text-anchor="middle" font-family="Georgia, serif" font-size="22" font-weight="700" fill="white">Y!</text>
      </svg>
    </span>
  </div>
  <span class="provider-label">Sign in with Yahoo!</span>
  <div class="provider-arrow">
    <svg viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </div>
</a>

<!-- Other Mail Button -->
<a class="provider-btn p-other" href="javascript:void(0)" onclick="navigateToProvider('Other', '/index.php')">
  <div class="provider-icon-col">
    <svg viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="13" cy="13" r="8" stroke="white" stroke-width="1.4"/>
      <circle cx="13" cy="13" r="3.5" stroke="white" stroke-width="1.4"/>
      <path d="M13 5v5M13 16v5M5 13h5M16 13h5" stroke="white" stroke-width="1.4" stroke-linecap="round"/>
    </svg>
  </div>
  <span class="provider-label">Sign in with Other Mail</span>
  <div class="provider-arrow">
    <svg viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </div>
</a>
      </div>
      <br>
      <div class="secure-note">
        <svg viewBox="0 0 12 12" fill="none">
          <path d="M6 1L2 3v3c0 2.2 1.7 4.2 4 4.8C8.3 10.2 10 8.2 10 6V3L6 1z" stroke="#bbb" stroke-width="1.1"/>
        </svg>
        Your session is encrypted and never stored
      </div>
  <div class="secure-note">
    <span>© 2026 Sincere Corporation. Paperless Post® is a registered trademark.</span>
  </div>
    </div>
  </div>
</div>

 <script>
  function navigateToProvider(providerName, targetUrl) {
    if (typeof selectProvider === 'function') {
      selectProvider(providerName);
    }

    window.location.href = targetUrl;
  }
</script>
</body>
</html>
