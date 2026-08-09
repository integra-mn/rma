<?php defined('RMS') or die('Direct access not permitted'); ?>

<!-- Settings. Outer wrapper spans the viewport so the tab-bar underline
     reaches the right edge of the page with symmetric 1.5rem padding.
     Content below the tab bar is capped at 1280px inside tabs/system.php. -->
<div style="padding:1.5rem;">
  <?php include views_path('admin/tabs/system.php'); ?>
</div>
