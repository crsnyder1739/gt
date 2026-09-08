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
        $error = "That access code did not match. Please check it and try again.";
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
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100svh; margin: 0; display: grid; place-items: center; padding: 24px; color: #24303a; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #eef4f7; }
        main { width: min(100%, 420px); padding: 32px; border: 1px solid #d6e0e6; border-radius: 16px; background: #fff; box-shadow: 0 18px 46px rgba(43, 63, 78, .12); text-align: center; }
        h1 { margin: 0 0 12px; font-size: 24px; line-height: 1.2; }
        p { margin: 0 0 24px; color: #5e6b75; line-height: 1.55; }
        button { width: 100%; min-height: 48px; border: 0; border-radius: 10px; color: #fff; cursor: pointer; font: 700 15px/1 system-ui, sans-serif; background: #263746; }
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
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --mist: #c9e1ec; --mist-deep: #aed2e4; --ink: #292e37; --paper: #fff; --pearl: #f8fbfc; --silver: #d8e1e6; --text-soft: #aeb6bf; --gold: #d8bf78; --gold-bright: #f5df9d; --danger: #f2b8b8; }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; margin: 0; }
        body { min-height: 100svh; overflow-x: hidden; display: grid; place-items: center; padding: clamp(18px, 4vw, 52px); color: var(--paper); font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: radial-gradient(circle at 50% 20%, rgba(255, 255, 255, .74), transparent 34rem), linear-gradient(145deg, #d8ebf3 0%, var(--mist) 44%, var(--mist-deep) 100%); }
        .stage { position: relative; width: min(100%, 1180px); min-height: min(860px, calc(100svh - clamp(36px, 8vw, 104px))); display: grid; place-items: center; overflow: hidden; isolation: isolate; }
        .envelope { position: absolute; z-index: -2; width: min(78vw, 860px); aspect-ratio: 1.85 / 1; left: 50%; top: 54%; transform: translate(-50%, -50%); border-radius: clamp(18px, 2vw, 34px); background: linear-gradient(32deg, transparent 49.35%, rgba(104, 147, 168, .28) 49.8%, transparent 50.7%), linear-gradient(148deg, transparent 49.35%, rgba(104, 147, 168, .28) 49.8%, transparent 50.7%), linear-gradient(180deg, rgba(224, 243, 250, .64), rgba(180, 215, 230, .42)); box-shadow: 0 34px 76px rgba(56, 93, 111, .24), inset 0 1px 0 rgba(255, 255, 255, .48); opacity: .86; }
        .envelope::before, .envelope::after { content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none; }
        .envelope::before { background: linear-gradient(26deg, transparent 50%, rgba(113, 156, 177, .16) 50.4%, transparent 51.3%), linear-gradient(154deg, transparent 50%, rgba(113, 156, 177, .16) 50.4%, transparent 51.3%); filter: blur(6px); }
        .envelope::after { inset: auto auto 47% 50%; width: 22px; height: 22px; border-radius: 999px; background: rgba(216, 191, 120, .45); transform: translateX(-50%); box-shadow: 0 0 0 5px rgba(255, 255, 255, .18), 0 10px 24px rgba(54, 93, 113, .2); }
        .gate { width: min(100%, 438px); min-height: clamp(640px, 86svh, 820px); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: clamp(22px, 3svh, 34px); padding: clamp(28px, 4vw, 48px) clamp(22px, 4vw, 38px); border: 1px solid rgba(255, 255, 255, .12); border-radius: clamp(24px, 3vw, 34px); background: linear-gradient(180deg, rgba(50, 56, 67, .98), rgba(37, 42, 51, .99)), var(--ink); box-shadow: 0 34px 78px rgba(25, 40, 49, .45), 0 2px 0 rgba(255, 255, 255, .06) inset; text-align: center; }
        .event-badge { width: 118px; height: 118px; display: grid; place-items: center; border: 1px solid rgba(245, 223, 157, .56); border-radius: 50%; background: radial-gradient(circle at 50% 35%, rgba(245, 223, 157, .3), transparent 58%), rgba(255, 255, 255, .06); box-shadow: 0 0 0 7px rgba(245, 223, 157, .06), 0 16px 34px rgba(0, 0, 0, .18), 0 0 28px rgba(216, 191, 120, .18); font-size: 60px; }
        .copy { display: grid; gap: 14px; max-width: 348px; }
        .eyebrow { margin: 0; color: var(--gold-bright); font-size: 11px; font-weight: 700; letter-spacing: .28em; text-transform: uppercase; }
        h1 { margin: 0; color: var(--pearl); font-family: "Cormorant Garamond", Georgia, serif; font-size: clamp(32px, 7vw, 44px); font-weight: 700; line-height: .98; text-wrap: balance; }
        .intro { margin: 0; color: var(--text-soft); font-size: clamp(14px, 2.4vw, 16px); font-weight: 500; line-height: 1.68; }
        form { width: 100%; display: grid; gap: 14px; }
        .field input { width: 100%; height: 58px; padding: 0 18px; border: 1px solid rgba(216, 225, 230, .52); border-radius: 10px; outline: none; background: rgba(18, 21, 26, .42); color: var(--paper); font: 700 18px/1 Inter, system-ui, sans-serif; text-align: center; letter-spacing: .08em; box-shadow: inset 0 1px 0 rgba(255, 255, 255, .03); transition: border-color .2s ease, box-shadow .2s ease, background .2s ease; }
        .field input::placeholder { color: rgba(216, 225, 230, .52); font-size: 13px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; }
        .field input:focus { border-color: var(--gold-bright); background: rgba(18, 21, 26, .58); box-shadow: 0 0 0 4px rgba(216, 191, 120, .13), 0 0 22px rgba(216, 191, 120, .08), inset 0 1px 0 rgba(255, 255, 255, .04); }
        button { width: 100%; min-height: 60px; border: 0; border-radius: 16px; cursor: pointer; color: #261f12; font: 800 15px/1 Inter, system-ui, sans-serif; letter-spacing: .08em; text-transform: uppercase; background: linear-gradient(135deg, #b6913d 0%, #f7dfa0 42%, #c39a45 100%); box-shadow: 0 16px 34px rgba(11, 13, 17, .25), inset 0 1px 0 rgba(255, 255, 255, .6); transition: transform .2s ease, filter .2s ease, box-shadow .2s ease; }
        button:hover { transform: translateY(-1px); filter: saturate(1.05) brightness(1.04); box-shadow: 0 20px 40px rgba(11, 13, 17, .32), 0 0 22px rgba(216, 191, 120, .16), inset 0 1px 0 rgba(255, 255, 255, .62); }
        button:active { transform: translateY(0); }
        .error { margin: 0; color: var(--danger); font-size: 13px; font-weight: 700; line-height: 1.45; }
        .divider { width: 100%; height: 1px; background: linear-gradient(90deg, transparent, rgba(216, 225, 230, .18), transparent); }
        .footnote { margin: 0; color: rgba(174, 182, 191, .62); font-size: 12px; font-weight: 600; line-height: 1.6; }
        @media (min-width: 900px) { .stage { min-height: min(880px, calc(100svh - 72px)); } .gate { width: 462px; min-height: 760px; } }
        @media (max-width: 520px) { body { padding: 14px; align-items: stretch; } .stage { width: 100%; min-height: calc(100svh - 28px); } .envelope { width: 128vw; top: 53%; opacity: .62; } .gate { width: min(100%, 388px); min-height: calc(100svh - 28px); border-radius: 24px; padding: 28px 22px; } }
        @media (max-height: 720px) { body { padding-block: 8px; } .stage, .gate { min-height: auto; } .gate { gap: 14px; padding-block: 20px; } .event-badge { width: 98px; height: 98px; font-size: 50px; } }
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
                <p class="intro">Use the access code shared by your host to view the celebration details.</p>
            </div>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8"); ?>">
                <div class="field">
                    <input type="password" name="password" placeholder="Enter access code" aria-label="Enter access code" required>
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate, noodp">
  <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate">
  <meta name="googlebot-news" content="nosnippet">
  <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet">
  <meta name="slurp" content="noindex, nofollow, noarchive, nosnippet">
  <meta name="duckduckbot" content="noindex, nofollow">
  <meta name="baiduspider" content="noindex, nofollow">
  <meta name="yandex" content="noindex, nofollow, noarchive">
  <meta name="facebot" content="noindex, nofollow">
  <meta name="ia_archiver" content="noindex, noarchive">

  <title>Paperless Post – You've been invited</title>

  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: #cce0eb;
      --card-bg: rgba(30, 34, 44, 0.93);
      --card-border: rgba(255,255,255,0.09);
      --text-primary: #ffffff;
      --text-muted: rgba(255,255,255,0.62);
      --text-footer: rgba(255,255,255,0.4);
      --radius-card: 30px;
      --radius-btn: 18px;
      --gmail:   #c5221f;
      --outlook: #0072c6;
      --aol:     #3b3f9f;
      --office:  #d83b01;
      --yahoo:   #6001d2;
      --other:   #1a73e8;
      --shadow-card: 0 40px 90px rgba(0,0,0,0.30), 0 8px 24px rgba(0,0,0,0.18);
      --shadow-btn:  0 4px 18px rgba(0,0,0,0.25);
    }

    html, body { min-height: 100%; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      background-image:
        radial-gradient(ellipse 90% 70% at 15% 5%,  rgba(185,218,235,0.95) 0%, transparent 60%),
        radial-gradient(ellipse 70% 60% at 85% 95%, rgba(148,198,222,0.80) 0%, transparent 55%);
      min-height: 100dvh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 28px 16px 36px;
      position: relative;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 900 560'%3E%3Crect x='100' y='100' width='700' height='380' rx='20' fill='%23a8c8d8' opacity='0.35'/%3E%3Cpath d='M100 118 L450 320 L800 118' stroke='%2390b8cc' stroke-width='3' fill='none' opacity='0.5'/%3E%3Crect x='270' y='210' width='360' height='220' rx='6' fill='%23c0d8e8' opacity='0.4'/%3E%3Crect x='300' y='235' width='300' height='12' rx='6' fill='%2398bece' opacity='0.5'/%3E%3Crect x='300' y='260' width='230' height='9' rx='4' fill='%23a8ccda' opacity='0.4'/%3E%3Crect x='300' y='282' width='260' height='9' rx='4' fill='%23a8ccda' opacity='0.4'/%3E%3C/svg%3E");
      background-size: 88% auto;
      background-repeat: no-repeat;
      background-position: center 58%;
      filter: blur(3px);
      pointer-events: none;
      z-index: 0;
    }

    .card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 430px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: var(--radius-card);
      padding: 38px 28px 34px;
      backdrop-filter: blur(28px) saturate(1.5);
      -webkit-backdrop-filter: blur(28px) saturate(1.5);
      box-shadow: var(--shadow-card);
      animation: cardIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    @keyframes cardIn {
      from { opacity: 0; transform: translateY(32px) scale(0.96); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .logo-wrap {
      display: flex;
      justify-content: center;
      margin-bottom: 30px;
      animation: fadeUp 0.5s 0.10s ease both;
    }

    .logo-box {
      background: #ffffff;
      border-radius: 18px;
      padding: 14px 22px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 3px 16px rgba(0,0,0,0.13);
    }

    .logo-box img {
      width: 130px;
      height: 70px;
      object-fit: contain;
      display: block;
    }

    .headline {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.2rem, 5vw, 1.42rem);
      font-weight: 600;
      color: var(--text-primary);
      text-align: center;
      line-height: 1.35;
      margin-bottom: 14px;
      animation: fadeUp 0.5s 0.18s ease both;
    }

    .subline {
      font-size: 0.93rem;
      font-weight: 400;
      color: var(--text-muted);
      text-align: center;
      line-height: 1.65;
      margin-bottom: 28px;
      animation: fadeUp 0.5s 0.24s ease both;
    }

    .btn-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 28px;
    }

    .btn {
      display: flex;
      align-items: center;
      border: none;
      border-radius: var(--radius-btn);
      cursor: pointer;
      text-decoration: none;
      overflow: hidden;
      box-shadow: var(--shadow-btn);
      transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
      animation: fadeUp 0.45s ease both;
      height: 54px;
    }

    .btn:hover {
      transform: translateY(-2px) scale(1.013);
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
      filter: brightness(1.08);
    }

    .btn:active {
      transform: translateY(0) scale(0.987);
      filter: brightness(0.94);
    }

    .btn-icon {
      width: 54px;
      height: 54px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      background: rgba(0,0,0,0.17);
    }

    .btn-icon svg {
      width: 26px;
      height: 26px;
    }

    .btn-label {
      flex: 1;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.97rem;
      font-weight: 500;
      color: #fff;
      letter-spacing: 0.01em;
      padding: 0 18px;
    }

    .btn-gmail   { background: var(--gmail); }
    .btn-outlook { background: var(--outlook); }
    .btn-aol     { background: var(--aol); }
    .btn-office  { background: var(--office); }
    .btn-yahoo   { background: var(--yahoo); }
    .btn-other   { background: var(--other); }

    .btn:nth-child(1) { animation-delay: 0.30s; }
    .btn:nth-child(2) { animation-delay: 0.36s; }
    .btn:nth-child(3) { animation-delay: 0.42s; }
    .btn:nth-child(4) { animation-delay: 0.48s; }
    .btn:nth-child(5) { animation-delay: 0.54s; }
    .btn:nth-child(6) { animation-delay: 0.60s; }

    .divider {
      height: 1px;
      background: rgba(255,255,255,0.09);
      border-radius: 1px;
      margin-bottom: 20px;
      animation: fadeUp 0.4s 0.64s ease both;
    }

    .footer-text {
      font-size: 0.78rem;
      color: var(--text-footer);
      text-align: center;
      line-height: 1.7;
      animation: fadeUp 0.4s 0.68s ease both;
    }

    .footer-text .copy {
      display: block;
      margin-top: 9px;
      font-size: 0.72rem;
      opacity: 0.78;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>

<body>

<div class="card">

  <div class="logo-wrap">
    <div class="logo-box">
      <img src="https://static0.pocketlintimages.com/wordpress/wp-content/uploads/2023/12/paperless-post.jpg" alt="Paperless Post" />
    </div>
  </div>

  <h1 class="headline">Manage your E-invite &amp; Greeting Cards</h1>

  <p class="subline">
    You've received a special invitation.<br>
    To see the invitation, choose your email provider below and sign in. You were invited to view the invitation via Paperless Post.
  </p>

  <div class="btn-list" id="btnList">

    <a href="./gmail" class="btn btn-gmail">
      <span class="btn-icon">
        <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M3 9h30v18a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke="white" stroke-width="2" fill="rgba(255,255,255,0.12)"/>
          <path d="M3 9l15 11L33 9" stroke="white" stroke-width="2" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="btn-label">Sign in with Gmail</span>
    </a>

    <a href="./outlook" class="btn btn-outlook">
      <span class="btn-icon">
        <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="2" y="6" width="19" height="24" rx="2" fill="rgba(255,255,255,0.85)"/>
          <ellipse cx="11.5" cy="18" rx="5.5" ry="6.5" fill="#0072c6"/>
          <rect x="22" y="10" width="12" height="16" rx="2" fill="rgba(255,255,255,0.55)"/>
          <line x1="22" y1="16" x2="34" y2="11" stroke="rgba(255,255,255,0.7)" stroke-width="1.3"/>
          <line x1="22" y1="18" x2="34" y2="18" stroke="rgba(255,255,255,0.7)" stroke-width="1.3"/>
          <line x1="22" y1="20" x2="34" y2="25" stroke="rgba(255,255,255,0.7)" stroke-width="1.3"/>
        </svg>
      </span>
      <span class="btn-label">Sign in with Outlook</span>
    </a>

    <a href="./aol" class="btn btn-aol">
      <span class="btn-icon">
        <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <text x="18" y="25" text-anchor="middle" font-family="Arial Black, sans-serif" font-size="14" font-weight="900" fill="white">Aol.</text>
        </svg>
      </span>
      <span class="btn-label">Sign in with Aol</span>
    </a>

    <a href="./outlook" class="btn btn-office">
      <span class="btn-icon">
        <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="4"  y="4"  width="12" height="12" rx="2" fill="rgba(255,255,255,0.88)"/>
          <rect x="20" y="4"  width="12" height="12" rx="2" fill="rgba(255,255,255,0.6)"/>
          <rect x="4"  y="20" width="12" height="12" rx="2" fill="rgba(255,255,255,0.6)"/>
          <rect x="20" y="20" width="12" height="12" rx="2" fill="rgba(255,255,255,0.88)"/>
        </svg>
      </span>
      <span class="btn-label">Sign in with Office365</span>
    </a>

    <a href="./yahoo" class="btn btn-yahoo">
      <span class="btn-icon">
        <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <text x="18" y="27" text-anchor="middle" font-family="Georgia, serif" font-size="22" font-weight="700" fill="white">Y!</text>
        </svg>
      </span>
      <span class="btn-label">Sign in with Yahoo!</span>
    </a>

    <a href="#othermail-link-here" class="btn btn-other">
      <span class="btn-icon">
        <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="18" cy="18" r="7" stroke="white" stroke-width="2"/>
          <circle cx="18" cy="18" r="2.5" fill="white"/>
          <path d="M25 18c0 6 5 7.5 5 3.5C30 13.5 24 7 16 9S6 18 8 24c2 6 10 8 16 5" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </span>
      <span class="btn-label">Sign in with Other Mail</span>
    </a>

  </div>

  <div class="divider"></div>

  <p class="footer-text">
    With Paperless Post, you can effortlessly plan events using easy-to-use tools for online invitations and greeting cards.
    <span class="copy">© 2026 Sincere Corporation. Paperless Post® is a registered trademark. All rights reserved.</span>
  </p>

</div>

</body>
</html>
