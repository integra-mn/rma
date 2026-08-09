<?php defined('RMS') or die(); ?>

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Add + Search -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <?php if (can('settings','edit')): ?>
    <button type="button" class="btn btn-primary" style="min-width:140px;"
            onclick="document.getElementById('add-form').style.display=document.getElementById('add-form').style.display==='none'?'block':'none'">
      <?= __('admin.loc_add') ?>
    </button>
    <?php endif; ?>
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="location-search" placeholder="<?= __('admin.loc_search') ?>"
             oninput="filterLocations(this.value)" style="width:100%;">
    </div>
  </div>

  <!-- Add form -->
  <?php if (can('settings','edit')): ?>
  <div id="add-form" style="display:none;margin-bottom:1.25rem;" class="card">
    <form method="POST" action="/admin/location/store">
      <?= csrf_field() ?>
      <!-- Row 1 -->
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('label.name') ?></label><input type="text" name="name" required></div>
        <div class="field"><label><?= __('label.code_location') ?></label><input type="text" name="code" required maxlength="5" style="text-transform:uppercase;"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email"></div>
      </div>
      <!-- Row 2 -->
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address"></div>
        <div class="field"><label><?= __('label.postal_code') ?></label><input type="text" name="postal_code"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city"></div>
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country" value="<?= __('label.country_default') ?>"></div>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('add-form').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Locations table -->
  <?php if (empty($locations)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('admin.loc_none') ?></p>
  <?php else: ?>
    <table class="data-table" id="location-table">
      <thead>
        <tr>
          <th><?= __('label.name') ?></th>
          <th><?= __('label.code') ?></th>
          <th><?= __('label.city') ?></th>
          <th><?= __('label.phone') ?></th>
          <th style="text-align:center;"><?= __('label.status') ?></th>
          <th style="text-align:right;"><?= __('label.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($locations as $loc): ?>
          <tr data-name="<?= strtolower(htmlspecialchars($loc['name'] ?? '')) ?>"
              data-code="<?= strtolower(htmlspecialchars($loc['code'] ?? '')) ?>"
              data-city="<?= strtolower(htmlspecialchars($loc['city'] ?? '')) ?>">
            <td style="font-weight:500;"><?= htmlspecialchars($loc['name']) ?></td>
            <td style="font-family:monospace;color:var(--accent);"><?= htmlspecialchars($loc['code'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($loc['city'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($loc['phone'] ?? '—') ?></td>
            <td style="text-align:center;">
              <span class="badge" style="background:<?= $loc['is_active'] ? 'var(--accent-bg)' : 'var(--bg-subtle)' ?>;color:<?= $loc['is_active'] ? 'var(--accent-text)' : 'var(--text-muted)' ?>;">
                <?= $loc['is_active'] ? __('label.active') : __('label.disabled') ?>
              </span>
            </td>
            <td style="text-align:right;white-space:nowrap;">
              <?php if (can('settings','edit')): ?>
                <button onclick="editLocation(<?= htmlspecialchars(json_encode($loc)) ?>)" class="btn-link"><?= __('btn.edit') ?></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<!-- Edit modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('admin.loc_edit') ?></h2>
    <form id="location-update-form" method="POST" action="/admin/location/update">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="e-id">
      <div class="form-grid" style="">
        <div class="field"><label><?= __('label.name') ?></label><input type="text" name="name" id="e-name" required></div>
        <div class="field"><label><?= __('label.code_location') ?></label><input type="text" name="code" id="e-code" maxlength="5" style="text-transform:uppercase;"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone" id="e-phone"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" id="e-email"></div>
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address" id="e-address"></div>
        <div class="field"><label><?= __('label.postal_code') ?></label><input type="text" name="postal_code" id="e-postal"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city" id="e-city"></div>
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country" id="e-country"></div>
      </div>
    </form>
    <div class="modal-actions" style="display:flex;gap:8px;align-items:center;">
      <button type="submit" form="location-update-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
      <button type="button" class="btn" onclick="document.getElementById('edit-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      <form method="POST" action="/admin/location/toggle" style="display:inline;margin-left:auto;">
        <?= csrf_field() ?><input type="hidden" name="id" id="e-toggle-id">
        <button type="submit" class="btn btn-sm" id="e-toggle-btn"><?= __('btn.disable') ?></button>
      </form>
      <form method="POST" action="/admin/location/delete" style="display:inline;"
            data-confirm="<?= htmlspecialchars(__('admin.loc_confirm_delete'), ENT_QUOTES) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" id="e-delete-id">
        <button type="submit" class="btn btn-sm btn-danger"><?= __('btn.delete') ?></button>
      </form>
    </div>
  </div>
</div>

<script>
function filterLocations(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#location-table tbody tr').forEach(row => {
    const match = !q
      || row.dataset.name.includes(q)
      || row.dataset.code.includes(q)
      || row.dataset.city.includes(q);
    row.style.display = match ? '' : 'none';
  });
}
function editLocation(l) {
  document.getElementById('e-id').value      = l.id;
  document.getElementById('e-name').value    = l.name || '';
  document.getElementById('e-code').value    = l.code || '';
  document.getElementById('e-city').value    = l.city || '';
  document.getElementById('e-postal').value  = l.postal_code || '';
  document.getElementById('e-country').value = l.country || '';
  document.getElementById('e-phone').value   = l.phone || '';
  document.getElementById('e-email').value   = l.email || '';
  document.getElementById('e-address').value = l.address || '';
  document.getElementById('e-toggle-id').value = l.id;
  document.getElementById('e-delete-id').value = l.id;
  var t = document.getElementById('e-toggle-btn');
  var active = (l.is_active == 1);
  t.textContent = active ? <?= json_encode(__('btn.disable')) ?> : <?= json_encode(__('btn.enable')) ?>;
  t.className = 'btn btn-sm' + (active ? ' btn-danger' : '');
  document.getElementById('edit-modal').style.display = 'flex';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
