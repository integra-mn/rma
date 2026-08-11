<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php include views_path('parts/_tabs.php'); ?>

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php include views_path('parts/_part_groups.php'); ?>

</div>
