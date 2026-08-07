<?php
// helpers.php — Yahoo shared shell

/* Yahoo "yahoo!" purple italic wordmark — exact match */
function yahoo_logo_svg(int $h = 28): string {
    // Using font-style italic + exact purple #6001d2
    return '<svg height="' . $h . '" viewBox="0 0 130 ' . $h . '" xmlns="http://www.w3.org/2000/svg">
  <text x="0" y="' . ($h - 4) . '"
    font-family="\'Georgia\',\'Times New Roman\',serif"
    font-weight="700"
    font-style="italic"
    font-size="' . $h . '"
    fill="#6001d2"
    letter-spacing="-0.5">yahoo!</text>
</svg>';
}

function page_open(string $title, string $bg_class = ''): void {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no"/>
  <title>' . $title . ' | Yahoo</title>
  <link rel="stylesheet" href="style.css"/>
</head>
<body>
<nav class="yahoo-nav">
  <a href="index.php" class="yahoo-logo">' . yahoo_logo_svg(28) . '</a>
  <div class="nav-links">
    <a href="#">Help</a>
    <a href="#">Terms</a>
    <a href="#">Privacy</a>
  </div>
</nav>
<div class="page-bg ' . $bg_class . '">';
}

function page_close(): void {
    echo '</div>
<script src="script.js"></script>
</body>
</html>';
}
