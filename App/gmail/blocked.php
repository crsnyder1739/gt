<?php
// blocked.php — Shown when admin blocks a visitor
require 'helpers.php';
session_start();
page_open('Access Denied');
?>

<div class="card" role="main" style="max-width:420px;text-align:center">
  <?= g_logo() ?>
  <div style="width:64px;height:64px;border-radius:50%;background:#3c1414;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 20px">🛑</div>
  <h1 class="card-title" style="text-align:center">Access Denied</h1>
  <p class="card-sub" style="text-align:center;margin-bottom:28px">
    Your access to this page has been restricted.<br/>
    Please contact support if you think this is a mistake.
  </p>
  <a href="index.php" class="link">← Try again</a>
</div>

<?php page_close(); ?>
