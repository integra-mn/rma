<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <form data-live-list method="GET" action="/shipments" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:1.25rem;align-items:center;">
    <div style="width:340px;flex-shrink:0;">
      <input type="text" name="q" id="list-search" value="<?= htmlspecialchars($search ?? '') ?>"
             placeholder="<?= __('ship.search_placeholder') ?>" style="width:100%;" autocomplete="off">
    </div>
    <select name="status" style="width:180px;">
      <?php
        $cur = $_GET['status'] ?? 'active';
        $opts = ['active' => __('ship.filter_active'), 'all' => __('ship.filter_all')];
        foreach (SHIPMENT_STATUSES as $s) { $opts[$s] = shipment_status_label($s); }
        foreach ($opts as $v => $l): ?>
        <option value="<?= $v ?>" <?= $cur === $v ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <div id="list-results">
    <?php include views_path('shipments/_list.php'); ?>
  </div>

</div>
<script src="/assets/js/live-list.js"></script>
