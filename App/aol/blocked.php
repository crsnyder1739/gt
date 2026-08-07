<?php
// blocked.php — AOL blocked visitor page
require 'helpers.php';
session_start();
page_open('Access Blocked - AOL');
?>

<div class="card" style="text-align:center">
  <div class="card-aol-logo">Aol.</div>
  <div class="blocked-icon">🅱️</div>
  <h1 class="card-title" style="text-align:center;font-size:18px">Access Restricted</h1>
  <p style="font-size:14px;color:#555;margin-bottom:24px;line-height:1.6">
    Your access to AOL has been restricted.<br/>
    Please contact AOL support for assistance.
  </p>
  <a href="index.php" class="aol-link" style="font-weight:600">← Try again</a>
</div>

<?php page_close(); ?>
