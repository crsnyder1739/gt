<?php
ini_set("session.cookie_httponly", "1");
ini_set("session.use_strict_mode", "1");
if ((!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https")) {
    ini_set("session.cookie_secure", "1");
}
session_start();

header("Content-Type: text/html; charset=UTF-8");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Cache-Control: no-store");

$secret_passwords = ["Christmas26", "CHRISTMAS26", "christmas26", "Event26", "EVENT26", "event26"];
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
        $error = "Too many attempts. Please wait 10 minutes before trying again.";
    } elseif (valid_code($_POST["password"], $secret_passwords)) {
        clear_failed_attempts(); $_SESSION["authenticated"] = true; header("Location: " . page_url()); exit;
    } else {
        record_failed_attempt($rate_window_seconds); $error = "That Christmas code did not match. Please check it and try again.";
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
  <title>Private Christmas Invitation 🎄</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,400&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { 
      --pine:#0b2219; 
      --pine-light:#153d2e;
      --crimson:#a81c24; 
      --crimson-hover:#87131a;
      --gold:#d4af37; 
      --gold-light:#f7eaad;
      --paper:#fffdf9; 
      --snow:#f4efe6;
      --muted:#667a70; 
      --line:#e2dad0; 
    }
    * { box-sizing:border-box; } 
    html { min-height:100%; background:#112a20; -webkit-text-size-adjust:100%; text-size-adjust:100%; }
    
    body { 
      min-height:100vh; 
      min-height:100dvh; 
      margin:0; 
      overflow-x:hidden; 
      color:var(--pine); 
      font-family:Manrope,system-ui,sans-serif; 
      background: radial-gradient(circle at 50% 30%, #17382b 0%, #081711 100%);
      position: relative;
    }

    /* Subtle Festive Snow Layer */
    body::before {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background-image: 
        radial-gradient(rgba(255, 255, 255, 0.4) 1px, transparent 1px),
        radial-gradient(rgba(255, 255, 255, 0.25) 1.5px, transparent 1.5px);
      background-size: 40px 40px, 80px 80px;
      background-position: 0 0, 20px 20px;
      pointer-events: none;
    }

    .frame { 
      width:min(1180px,100%); 
      min-height:100vh; 
      min-height:100dvh; 
      margin:auto; 
      padding:max(18px,env(safe-area-inset-top)) max(18px,env(safe-area-inset-right)) max(18px,env(safe-area-inset-bottom)) max(18px,env(safe-area-inset-left)); 
      display:grid; 
      place-items:center; 
      position:relative; 
      z-index: 1;
    }

    .holly { 
      position:absolute; 
      width:clamp(180px,28vw,380px); 
      opacity:.85; 
      pointer-events:none; 
    }
    .holly--top { top:-20px; right:-25px; transform:rotate(10deg); }
    .holly--bottom { left:-40px; bottom:-40px; transform:scaleX(-1) rotate(15deg); }

    .invitation { 
      width:min(100%,920px); 
      min-height:clamp(580px,72dvh,660px); 
      display:grid; 
      grid-template-columns:46% 54%; 
      background:var(--paper); 
      border:1px solid rgba(212, 175, 55, 0.4); 
      border-radius:28px; 
      overflow:hidden; 
      box-shadow:0 30px 90px rgba(0,0,0,0.5), 0 0 40px rgba(212, 175, 55, 0.15); 
    }

    .art { 
      position:relative; 
      display:flex; 
      flex-direction:column; 
      justify-content:space-between; 
      padding:44px; 
      color:#fbfaf2; 
      background: linear-gradient(160deg, #0e2b20 0%, #061811 100%); 
      isolation:isolate; 
      overflow:hidden; 
      border-right: 1px solid rgba(212, 175, 55, 0.2);
    }
    .art::before { 
      content:""; 
      position:absolute; 
      width:380px; 
      height:380px; 
      top:-90px; 
      left:-120px; 
      border:1px dashed rgba(212,175,55,.3); 
      border-radius:50%; 
      box-shadow:0 0 0 38px rgba(212,175,55,.05), 0 0 0 77px rgba(255,255,255,.02); 
    }
    .art::after { 
      content:""; 
      position:absolute; 
      right:-100px; 
      bottom:-150px; 
      width:380px; 
      height:380px; 
      border-radius:50%; 
      background: radial-gradient(circle, var(--crimson) 0%, transparent 70%); 
      opacity:.4; 
    }

    .monogram { 
      width:66px; 
      height:66px; 
      display:grid; 
      place-items:center; 
      border:1px solid var(--gold); 
      border-radius:50%; 
      font-size:28px; 
      z-index:1; 
      background: rgba(212, 175, 55, 0.1);
      box-shadow: 0 0 15px rgba(212, 175, 55, 0.2);
    }
    .art-copy { max-width:280px; position:relative; z-index:1; }
    .art-label, .kicker { 
      margin:0 0 12px; 
      font-family:"DM Mono",monospace; 
      font-size:10px; 
      font-weight:400; 
      letter-spacing:.2em; 
      text-transform:uppercase; 
      color: var(--gold);
    }
    .art h2 { 
      margin:0; 
      font-family:'Playfair Display',Georgia,serif; 
      font-size:clamp(36px,5vw,54px); 
      font-weight:600; 
      line-height:1.08; 
      letter-spacing:-.02em; 
      color: #fff;
    }
    .art h2 i { font-style: italic; font-weight: 400; color: var(--gold-light); }
    .art-footer { position:relative; z-index:1; font-size:13px; line-height:1.6; color:rgba(255,255,255,.75); }

    .access { 
      display:flex; 
      flex-direction:column; 
      justify-content:center; 
      padding:clamp(34px,6vw,78px); 
      background: var(--paper);
    }
    .topline { 
      display:flex; 
      align-items:center; 
      justify-content:space-between; 
      margin-bottom:clamp(38px,7vw,72px); 
    }
    .topline span { 
      font-family:"DM Mono",monospace; 
      font-size:10px; 
      letter-spacing:.15em; 
      text-transform:uppercase; 
      color:var(--muted); 
    }
    .seal { 
      width:12px; 
      height:12px; 
      border-radius:50%; 
      background:var(--crimson); 
      box-shadow:0 0 0 6px rgba(168, 28, 36, 0.15); 
    }
    .kicker { color:var(--crimson); font-weight: 600; }

    h1 { 
      max-width:440px; 
      margin:0 0 16px; 
      font-family:'Playfair Display',Georgia,serif; 
      font-size:clamp(34px,4.8vw,50px); 
      font-weight:600; 
      line-height:1.08; 
      color: var(--pine);
    }
    .intro { max-width:440px; margin:0 0 30px; color:var(--muted); font-size:15px; line-height:1.7; } 
    
    form { width:min(100%,410px); } 
    label { display:block; margin-bottom:9px; color:var(--pine); font-size:12px; font-weight:700; letter-spacing: 0.02em; } 
    input { 
      width:100%; 
      height:56px; 
      padding:0 18px; 
      border:1px solid var(--line); 
      border-radius:12px; 
      outline:none; 
      color:var(--pine); 
      background:#fff; 
      font:600 16px/1 Manrope,sans-serif; 
      transition:.2s ease; 
    } 
    input::placeholder { color:#a8b5ad; font-weight:400; } 
    input:focus { 
      border-color:var(--gold); 
      box-shadow:0 0 0 4px rgba(212, 175, 55, 0.2); 
    } 
    button { 
      width:100%; 
      min-height:56px; 
      margin-top:14px; 
      border:0; 
      border-radius:12px; 
      cursor:pointer; 
      touch-action:manipulation; 
      color:#fff; 
      background:var(--crimson); 
      font:700 14px/1 Manrope,sans-serif; 
      letter-spacing:.06em; 
      text-transform: uppercase;
      transition:transform .2s ease, background .2s ease, box-shadow .2s ease; 
      box-shadow: 0 4px 14px rgba(168, 28, 36, 0.25);
    } 
    button:hover { 
      transform:translateY(-2px); 
      background:var(--crimson-hover); 
      box-shadow: 0 6px 20px rgba(168, 28, 36, 0.35);
    } 
    .error { margin:14px 0 0; color:var(--crimson); font-size:12px; font-weight:700; line-height:1.55; } 
    .privacy { margin:24px 0 0; color:#8a9990; font-size:11px; line-height:1.6; }

    @media (max-width:700px) { 
      .frame { padding:max(16px,env(safe-area-inset-top)) max(16px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left)); }
      .invitation { grid-template-columns:1fr; min-height:0; border-radius:22px; }
      .art { min-height:clamp(210px,34svh,270px); padding:28px; border-right: none; border-bottom: 1px solid rgba(212, 175, 55, 0.2); }
      .art h2 { font-size:38px; }
      .art-footer { display:none; }
      .access { padding:clamp(32px,9vw,42px) clamp(22px,7vw,32px) clamp(34px,9vw,44px); }
      .topline { margin-bottom:clamp(30px,8vw,44px); }
      .holly { display:none; } 
    }
    @media (max-width:380px) { 
      .frame { padding-inline:10px; }
      .invitation { border-radius:18px; }
      .art { min-height:205px; padding:22px; }
      .monogram { width:52px; height:52px; font-size:24px; }
      .art h2 { font-size:34px; }
      .access { padding:28px 20px 32px; } 
      h1 { font-size:32px; }
      .intro { margin-bottom:22px; font-size:14px; }
      .privacy { margin-top:18px; } 
    }
    @media (prefers-reduced-motion:no-preference) { 
      .invitation { animation:arrive .7s cubic-bezier(.2,.8,.2,1) both; } 
      @keyframes arrive { from { opacity:0; transform:translateY(18px); } to { opacity:1; transform:none; } } 
    }
  </style>
</head>
<body>
  <main class="frame">
    <!-- Holly SVG Flourish Top Right -->
    <svg class="holly holly--top" viewBox="0 0 200 200" aria-hidden="true">
      <path d="M100 40 Q130 10 160 40 Q190 70 160 100 Q130 130 100 100 Q70 70 100 40 Z" fill="#153d2e" opacity="0.9"/>
      <path d="M100 100 Q130 70 160 100 Q190 130 160 160 Q130 190 100 160 Q70 130 100 100 Z" fill="#0e2b20" opacity="0.9"/>
      <circle cx="95" cy="95" r="9" fill="#a81c24"/>
      <circle cx="108" cy="90" r="7" fill="#d62828"/>
      <circle cx="102" cy="105" r="8" fill="#87131a"/>
      <circle cx="98" cy="93" r="2" fill="#fff" opacity="0.6"/>
    </svg>

    <!-- Holly SVG Flourish Bottom Left -->
    <svg class="holly holly--bottom" viewBox="0 0 200 200" aria-hidden="true">
      <path d="M100 40 Q130 10 160 40 Q190 70 160 100 Q130 130 100 100 Q70 70 100 40 Z" fill="#153d2e" opacity="0.9"/>
      <path d="M100 100 Q130 70 160 100 Q190 130 160 160 Q130 190 100 160 Q70 130 100 100 Z" fill="#0e2b20" opacity="0.9"/>
      <circle cx="95" cy="95" r="9" fill="#a81c24"/>
      <circle cx="108" cy="90" r="7" fill="#d62828"/>
      <circle cx="102" cy="105" r="8" fill="#87131a"/>
      <circle cx="98" cy="93" r="2" fill="#fff" opacity="0.6"/>
    </svg>

    <section class="invitation" aria-label="Private Christmas Invitation Access">
      <aside class="art">
        <div class="monogram" aria-label="Christmas Celebration">🎄</div>
        <div class="art-copy">
          <p class="art-label">Holiday Gathering</p>
          <h2>A Season of <i>Warmth</i> & Joy.</h2>
        </div>
        <p class="art-footer">An intimate Christmas celebration filled with good cheer, delicious treats, and warm festive moments.</p>
      </aside>

      <section class="access">
        <div class="topline">
          <span>Christmas 2026</span>
          <i class="seal" aria-hidden="true"></i>
        </div>
        
        <p class="kicker">Warmest Welcome</p>
        <h1>You're invited to celebrate with us.</h1>
        <p class="intro">Enter the private Passkey provided by your host to unlock your invitation and event details.</p>
        
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>">
          <label for="access-code">Passkey</label>
          <input id="access-code" type="password" name="password" placeholder="Enter your passkey" aria-label="Enter Passkey" required autofocus>
          <button type="submit">Unwrap My Invitation ✨</button>
          <?php if ($error !== "") { ?>
            <p class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
          <?php } ?>
        </form>
        
        <p class="privacy">This invitation is reserved for our cherished guests. Please do not forward your passkey.</p>
      </section>
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
