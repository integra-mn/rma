<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form data-live-list method="GET" action="/rma" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:1.25rem;align-items:center;">
    <div style="width:340px;flex-shrink:0;">
      <input type="text" name="q" id="list-search" value="<?= htmlspecialchars($search) ?>"
             placeholder="<?= __('rma.search_placeholder') ?>" style="width:100%;" autocomplete="off">
    </div>
    <select name="status" style="width:160px;">
      <option value=""><?= __('rma.all_statuses') ?></option>
      <?php foreach ($statuses as $st): ?>
        <option value="<?= (int)$st['id'] ?>" <?= $status_f === (int)$st['id'] ? 'selected' : '' ?>>
          <?= status_label((string)($st['code'] ?? ''), $st['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="priority" style="width:160px;">
      <option value=""><?= __('priority.all') ?></option>
      <?php foreach (['low','normal','high','urgent'] as $pr): ?>
        <option value="<?= $pr ?>" <?= $priority_f === $pr ? 'selected' : '' ?>><?= __('priority.'.$pr) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($search || $status_f || $priority_f): ?>
      <a href="/rma" class="btn"><?= __('btn.clear') ?></a>
    <?php endif; ?>
    <?php if (can('rma', 'create')): ?>
      <a href="/rma/create" class="btn btn-primary" style="min-width:140px;text-align:center;"><?= __('rma.new') ?></a>
    <?php endif; ?>
  </form>

  <div id="list-results">
    <?php include views_path('rma/_results.php'); ?>
  </div>
</div>
<script src="/assets/js/live-list.js?v=<?= @filemtime(ROOT . '/assets/js/live-list.js') ?>"></script>
