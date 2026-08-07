<?php
// fetch-assets.php — Given an email domain, fetch company logo + website screenshot
// Called via AJAX from index.php after email is entered
// Returns JSON: { logo_url, bg_url, domain, company_name }

header('Content-Type: application/json');
header('Cache-Control: no-store');

$email  = trim($_POST['email'] ?? '');
$domain = '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'invalid email']);
    exit;
}

// Extract domain from email
$parts  = explode('@', $email);
$domain = strtolower(trim($parts[1] ?? ''));

if (!$domain) {
    echo json_encode(['error' => 'no domain']);
    exit;
}

// ── 1. Company logo via Clearbit Logo API (free, no key needed) ──
// Returns the actual company logo PNG from their CDN
$logo_url = 'https://logo.clearbit.com/' . urlencode($domain);

// Verify the logo actually exists (Clearbit returns 200 for known domains, 404 for unknown)
$logo_ok = false;
$ch = curl_init($logo_url);
curl_setopt_array($ch, [
    CURLOPT_NOBODY         => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
]);
curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$logo_ok = ($http_code === 200);

// Fallback: Google favicon service if Clearbit doesn't have it
if (!$logo_ok) {
    $logo_url = 'https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=128';
}

// ── 2. Website screenshot via Screenshotone (free tier, no key fallback) ──
// Primary: Use screenshotone.com free API
// Fallback: Use thumbnail.ws free API
// Fallback 2: Use a solid colour based on domain hash

// Try thumbnail.ws (free, no API key needed)
$bg_url = 'https://api.thumbnail.ws/api/abcdef123456/thumbnail/get'
        . '?url=' . urlencode('https://' . $domain)
        . '&width=1280'
        . '&delay=2000';

// Better free option: s-shot.ru (free screenshot API)
$bg_url = 'https://mini.s-shot.ru/1280x800/JPEG/1280/Z100/?' . urlencode('https://' . $domain);

// ── 3. Company name from domain (strip TLD for display) ──
$name_parts   = explode('.', $domain);
$company_name = ucfirst($name_parts[0] ?? $domain);

// ── Cache the result in session so we don't re-fetch ──
session_start();
$_SESSION['domain_logo']    = $logo_url;
$_SESSION['domain_bg']      = $bg_url;
$_SESSION['domain_name']    = $company_name;
$_SESSION['domain_fetched'] = $domain;

echo json_encode([
    'logo_url'     => $logo_url,
    'bg_url'       => $bg_url,
    'domain'       => $domain,
    'company_name' => $company_name,
    'logo_ok'      => $logo_ok,
]);
