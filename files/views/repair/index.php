<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form data-live-list method="GET" action="/repair" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:1.25rem;align-items:center;">
    <div style="width:340px;flex-shrink:0;">
      <input type="text" name="q" id="list-search" value="<?= htmlspecialchars($search) ?>"
             placeholder="<?= __('repair.search_placeholder') ?>" style="width:100%;" autocomplete="off">
    </div>
    <select name="status" style="width:160px;">
      <option value=""><?= __('rma.all_statuses') ?></option>
      <?php foreach ($statuses as $st): ?>
        <option value="<?= htmlspecialchars($st['code']) ?>" <?= $status_f === $st['code'] ? 'selected' : '' ?>>
          <?= status_label((string)($st['code'] ?? ''), $st['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($search || $status_f): ?>
      <a href="/repair" class="btn"><?= __('btn.clear') ?></a>
    <?php endif; ?>
  </form>

  <div id="list-results">
    <?php include views_path('repair/_results.php'); ?>
  </div>
</div>
<script src="/assets/js/live-list.js"></script>
