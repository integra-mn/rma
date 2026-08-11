<?php defined('RMS') or die('Direct access not permitted'); ?>

<!-- Administration. Outer wrapper spans the viewport so the main tab-bar
     underline reaches the right edge with symmetric 1.5rem padding. Content
     below the tab bar is capped at 1200px. -->
<div style="padding:1.5rem;">

  <!-- Tabs -->
  <div class="tab-bar">
    <?php foreach (['users'=>__('admin.users'),'locations'=>__('admin.locations'),'couriers'=>__('nav.shipments'),'statuses'=>__('admin.statuses')] as $t => $l): ?>
      <a href="/administration?tab=<?= $t ?>"
         class="tab<?= $tab===$t ? ' active' : '' ?>">
        <?= $l ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- All tabs cap at --w-content. The full-width branch existed only for the
       device catalogue, which is now its own sidebar section at /devices. -->
  <div style="max-width:var(--w-content);">
    <?php if ($tab === 'users'): ?>
      <?php include views_path('admin/tabs/users.php'); ?>
    <?php elseif ($tab === 'locations'): ?>
      <?php include views_path('admin/tabs/locations.php'); ?>
    <?php elseif ($tab === 'couriers'): ?>
      <?php include views_path('admin/tabs/couriers.php'); ?>
    <?php elseif ($tab === 'statuses'): ?>
      <?php include views_path('admin/tabs/statuses.php'); ?>
    <?php endif; ?>
  </div>

</div>
