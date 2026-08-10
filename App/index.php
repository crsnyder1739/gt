<?php
ini_set("session.cookie_httponly", "1");
ini_set("session.use_strict_mode", "1");

if (
    (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https")
) {
    ini_set("session.cookie_secure", "1");
}

session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Cache-Control: no-store");

$secret_passwords = ["Party26", "PARTY26", "party26"];
$error = "";
$max_attempts = 10;
$rate_window_seconds = 600;

if (!function_exists("hash_equals")) {
    function hash_equals($known_string, $user_string)
    {
        if (!is_string($known_string) || !is_string($user_string)) {
            return false;
        }

        if (strlen($known_string) !== strlen($user_string)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < strlen($known_string); $i++) {
            $result |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }

        return $result === 0;
    }
}

function password_is_valid($password, $secret_passwords)
{
    foreach ($secret_passwords as $secret_password) {
        if (hash_equals($secret_password, $password)) {
            return true;
        }
    }

    return false;
}

function base_url()
{
    return strtok($_SERVER["REQUEST_URI"], "?");
}

function secure_random_string($length = 32)
{
    if (function_exists("random_bytes")) {
        return bin2hex(random_bytes($length));
    }

    if (function_exists("openssl_random_pseudo_bytes")) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }

    $value = "";
    for ($i = 0; $i < $length; $i++) {
        $value .= chr(mt_rand(0, 255));
    }

    return bin2hex($value);
}

function csrf_token()
{
    if (!isset($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = secure_random_string(32);
    }

    return $_SESSION["csrf_token"];
}

function csrf_is_valid()
{
    return isset($_POST["csrf_token"])
        && isset($_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"]);
}

function client_rate_key()
{
    $ip = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "unknown";
    $agent = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";

    return hash("sha256", $ip . "|" . $agent);
}

function rate_file_path()
{
    return sys_get_temp_dir() . "/invite-rate-" . client_rate_key() . ".json";
}

function get_rate_state()
{
    $session_state = isset($_SESSION["rate_limit"]) && is_array($_SESSION["rate_limit"])
        ? $_SESSION["rate_limit"]
        : ["attempts" => 0, "first_attempt" => 0];

    $rate_file = rate_file_path();
    if (is_readable($rate_file)) {
        $decoded = json_decode((string) file_get_contents($rate_file), true);
        if (is_array($decoded)) {
            return [
                "attempts" => isset($decoded["attempts"]) ? (int) $decoded["attempts"] : 0,
                "first_attempt" => isset($decoded["first_attempt"]) ? (int) $decoded["first_attempt"] : 0,
            ];
        }
    }

    return $session_state;
}

function save_rate_state($state)
{
    $_SESSION["rate_limit"] = $state;
    @file_put_contents(rate_file_path(), json_encode($state), LOCK_EX);
}

function rate_limit_exceeded($max_attempts, $window_seconds)
{
    $state = get_rate_state();
    $now = time();

    if ($state["first_attempt"] <= 0 || ($now - $state["first_attempt"]) > $window_seconds) {
        save_rate_state(["attempts" => 0, "first_attempt" => $now]);
        return false;
    }

    return $state["attempts"] >= $max_attempts;
}

function record_failed_attempt($window_seconds)
{
    $state = get_rate_state();
    $now = time();

    if ($state["first_attempt"] <= 0 || ($now - $state["first_attempt"]) > $window_seconds) {
        $state = ["attempts" => 0, "first_attempt" => $now];
    }

    $state["attempts"]++;
    save_rate_state($state);
}

function clear_failed_attempts()
{
    unset($_SESSION["rate_limit"]);
    $rate_file = rate_file_path();

    if (is_writable($rate_file)) {
        @unlink($rate_file);
    }
}

function log_security_event($event, $details = [])
{
    $entry = [
        "time" => gmdate("c"),
        "event" => $event,
        "ip" => isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "",
        "user_agent" => isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "",
        "details" => $details,
    ];

    $log_file = __DIR__ . "/security-events.log";

    if ((file_exists($log_file) && is_writable($log_file)) || is_writable(__DIR__)) {
        @file_put_contents(
            $log_file,
            json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

if (isset($_POST["action"]) && $_POST["action"] === "clear_verification" && csrf_is_valid()) {
    unset($_SESSION["requires_verification"]);
    header("Location: " . base_url());
    exit;
}

if (isset($_GET["action"]) && $_GET["action"] === "logout") {
    session_unset();
    session_destroy();
    header("Location: " . base_url());
    exit;
}

if (isset($_POST["password"])) {
    if (!csrf_is_valid()) {
        log_security_event("csrf_failure");
        $error = "This access window expired. Please refresh the page and try again.";
    } elseif (rate_limit_exceeded($max_attempts, $rate_window_seconds)) {
        log_security_event("rate_limit_block");
        $error = "Too many tries. Please wait 10 minutes before trying again.";
    } elseif (password_is_valid($_POST["password"], $secret_passwords)) {
        clear_failed_attempts();
        $_SESSION["authenticated"] = true;
        header("Location: " . base_url());
        exit;
    } else {
        record_failed_attempt($rate_window_seconds);
        log_security_event("failed_passkey_attempt");
        $error = "That Passkey did not match. Please check it and try again.";
    }
}

if (
    isset($_SESSION["requires_verification"])
    && $_SESSION["requires_verification"] === true
    && (!isset($_SESSION["authenticated"]) || $_SESSION["authenticated"] !== true)
) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Quick Confirmation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #281e22; --muted: #756c6b; --line: #e8e1dc; --paper: #fdfbf9; --berry: #4d2037; --berry-dark: #351425; }
        * { box-sizing: border-box; }
        body { min-height: 100svh; margin: 0; display: grid; place-items: center; padding: 28px 22px; color: var(--ink); font-family: "DM Sans", Arial, sans-serif; background: linear-gradient(90deg, #fbf9f6 0 63%, #fffefd 63%); }
        body { min-height: 100svh; margin: 0; display: grid; place-items: center; padding: 28px 22px; color: var(--ink); font-family: "DM Sans", Arial, sans-serif; background: #faf8f5; }
        main { width: min(100%, 520px); padding: clamp(38px, 8vw, 58px); border: 1px solid rgba(106, 80, 67, .15); border-radius: 20px; background: rgba(255, 254, 253, .82); box-shadow: 0 22px 70px rgba(54, 37, 29, .055); text-align: center; }
        main::before { content: "PRIVATE INVITATION"; display: block; margin-bottom: 32px; color: #82746e; font-size: 11px; font-weight: 600; letter-spacing: .18em; }
        h1 { margin: 0 0 18px; font-family: "Cormorant Garamond", Georgia, serif; font-size: clamp(39px, 9vw, 52px); font-weight: 500; letter-spacing: -.045em; line-height: .93; }
        p { margin: 0 0 29px; color: var(--muted); font-size: 16px; font-weight: 500; line-height: 1.58; }
        button { width: 100%; min-height: 58px; border: 1px solid rgba(255,255,255,.24); border-radius: 999px; color: #fff; cursor: pointer; font: 600 15px/1 "DM Sans", Arial, sans-serif; letter-spacing: .02em; background: linear-gradient(112deg, var(--berry-dark), var(--berry) 58%, #71364e); box-shadow: 0 12px 24px rgba(77, 32, 55, .2), inset 0 1px 0 rgba(255,255,255,.22); transition: transform .18s ease, box-shadow .18s ease; }
        button:hover { transform: translateY(-2px); box-shadow: 0 17px 28px rgba(77, 32, 55, .26), inset 0 1px 0 rgba(255,255,255,.24); }
    </style>
</head>
<body>
    <main>
        <h1>One quick confirmation</h1>
        <p>Please confirm you would like to continue to the invitation.</p>
        <form method="POST">
            <input type="hidden" name="action" value="clear_verification">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>">
            <button type="submit">Continue to invitation</button>
        </form>
    </main>
</body>
</html>
<?php
    exit;
}

if (!isset($_SESSION["authenticated"]) || $_SESSION["authenticated"] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Private Celebration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #2c2022; --copy: #766d6b; --line: #e8e1dc; --paper: #fdfbf9; --berry: #4d2037; --berry-dark: #351425; --blush: #b98578; --error: #a1302a; }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; margin: 0; }
        body { min-height: 100svh; overflow-x: hidden; display: grid; place-items: center; padding: clamp(30px, 7vw, 92px) 22px 54px; color: var(--ink); font-family: "DM Sans", Arial, sans-serif; background: linear-gradient(90deg, #faf8f5 0 63%, #fffefd 63%); }
        body { min-height: 100svh; overflow-x: hidden; display: grid; place-items: center; padding: clamp(30px, 7vw, 92px) 22px 54px; color: var(--ink); font-family: "DM Sans", Arial, sans-serif; background: #faf8f5; }
        .stage { position: relative; width: min(100%, 712px); display: flex; flex-direction: column; align-items: center; }
        .envelope { width: 162px; height: 172px; display: grid; place-items: center; margin-bottom: clamp(48px, 7vw, 62px); border: 1px solid rgba(91, 64, 52, .16); border-radius: 18px 18px 42px 18px; background: linear-gradient(145deg, rgba(255,255,255,.96), rgba(249,243,238,.92)); box-shadow: 12px 13px 0 -10px rgba(185, 133, 120, .62), 0 18px 42px rgba(70, 42, 31, .06); transform: rotate(-2deg); }
        .envelope::before { content: "✦"; color: var(--berry); font-family: Georgia, serif; font-size: 57px; line-height: 1; transform: rotate(2deg); }
        .envelope::after { content: "THE OCCASION"; position: absolute; margin-top: 99px; color: #6e5b57; font-size: 8px; font-weight: 600; letter-spacing: .29em; transform: rotate(2deg) translateX(2px); }
        .gate { width: 100%; display: flex; flex-direction: column; align-items: stretch; padding: 0; color: var(--ink); background: transparent; text-align: left; }
        .event-badge { display: none; }
        .copy { width: 100%; max-width: 620px; display: block; }
        .eyebrow { width: 100%; display: flex; align-items: center; gap: 17px; margin: 0; color: #816f69; font-size: 12px; font-weight: 600; letter-spacing: .16em; line-height: 1; text-transform: uppercase; }
        .eyebrow::before, .eyebrow::after { content: ""; height: 1px; flex: 1; background: var(--line); }
        h1 { max-width: 580px; margin: clamp(52px, 7vw, 66px) 0 0; font-family: "Cormorant Garamond", Georgia, serif; font-size: clamp(50px, 7.5vw, 66px); font-weight: 500; letter-spacing: -.047em; line-height: .91; text-wrap: balance; }
        .intro { max-width: 574px; margin: 42px 0 0; color: var(--copy); font-size: clamp(16px, 2.2vw, 19px); font-weight: 500; letter-spacing: -.018em; line-height: 1.6; }
        form { width: 100%; max-width: 620px; display: grid; gap: 14px; margin-top: 48px; }
        .field { position: relative; }
        .field::before { content: "⌁"; position: absolute; z-index: 1; top: 50%; left: 20px; color: #a4867e; font: 24px/1 Georgia, serif; transform: translateY(-53%); }
        .field input { width: 100%; height: 66px; padding: 0 20px 0 50px; border: 1px solid #e1d7d1; border-radius: 14px; outline: none; color: var(--ink); font: 500 17px/1 "DM Sans", Arial, sans-serif; text-align: left; letter-spacing: 0; background: rgba(255,255,255,.76); box-shadow: inset 0 1px 0 rgba(255,255,255,.86); transition: border-color .18s ease, box-shadow .18s ease, background .18s ease; }
        .field input::placeholder { color: #a49a97; font-size: 16px; font-weight: 400; letter-spacing: 0; text-transform: none; }
        .field input:focus { border-color: #a87570; background: #fff; box-shadow: 0 0 0 4px rgba(168, 117, 112, .12); }
        button { position: relative; width: 100%; min-height: 66px; overflow: hidden; border: 1px solid rgba(255,255,255,.24); border-radius: 999px; cursor: pointer; color: #fff; font: 600 16px/1 "DM Sans", Arial, sans-serif; letter-spacing: .025em; text-transform: none; background: linear-gradient(108deg, var(--berry-dark), var(--berry) 56%, #71364e); box-shadow: 0 13px 25px rgba(77, 32, 55, .2), inset 0 1px 0 rgba(255,255,255,.22); transition: transform .18s ease, box-shadow .18s ease; }
        button::after { content: "→"; position: absolute; right: 25px; top: 50%; font: 25px/1 Georgia, serif; transform: translateY(-54%); transition: transform .18s ease; }
        button:hover { transform: translateY(-2px); box-shadow: 0 18px 30px rgba(77, 32, 55, .26), inset 0 1px 0 rgba(255,255,255,.24); }
        button:hover::after { transform: translate(4px, -54%); }
        button:active { transform: translateY(0); }
        .error { margin: 0; color: var(--error); font-size: 14px; font-weight: 500; line-height: 1.45; }
        .divider { width: 100%; max-width: 620px; height: 1px; margin-top: 35px; background: linear-gradient(90deg, transparent, var(--line), transparent); }
        .footnote { margin: 24px 0 0; color: #afaaa7; font-size: 13px; font-weight: 500; line-height: 1.6; text-align: center; }
        @media (max-width: 560px) { body { background: #fdfbf9; } .envelope { width: 142px; height: 151px; margin-bottom: 45px; } .envelope::before { font-size: 51px; } .envelope::after { margin-top: 85px; } .eyebrow { gap: 10px; font-size: 10px; letter-spacing: .12em; } h1 { margin-top: 48px; } .intro { margin-top: 34px; } }
        @media (max-width: 560px) { body { background: #faf8f5; } .envelope { width: 142px; height: 151px; margin-bottom: 45px; } .envelope::before { font-size: 51px; } .envelope::after { margin-top: 85px; } .eyebrow { gap: 10px; font-size: 10px; letter-spacing: .12em; } h1 { margin-top: 48px; } .intro { margin-top: 34px; } }
        @media (max-height: 720px) { body { padding-block: 26px; } .envelope { margin-bottom: 34px; } h1 { margin-top: 38px; } .intro { margin-top: 28px; } form { margin-top: 34px; } }
    </style>
</head>
<body>
    <main class="stage" aria-label="Private celebration access">
        <div class="envelope" aria-hidden="true"></div>

        <section class="gate">
            <div class="event-badge" aria-label="Private celebration invitation">🎁</div>

            <div class="copy">
                <p class="eyebrow">A Private Celebration</p>
                <h1>Your invitation awaits</h1>
                <p class="intro">Use the Passkey shared by your host to view the celebration details.</p>
            </div>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>">
                <div class="field">
                    <input type="password" name="password" placeholder="Enter passkey" aria-label="Enter passkey" required>
                </div>

                <button type="submit">View invitation</button>

                <?php if ($error !== "") { ?>
                    <p class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
                <?php } ?>
            </form>

            <div class="divider" aria-hidden="true"></div>
            <p class="footnote">Shared only with invited guests.</p>
        </section>
    </main>
</body>
</html>
<?php
    exit;
}
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
