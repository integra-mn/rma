<?php defined('RMS') or die(); ?>
<?php
/**
 * Part groups — the taxonomy that classifies parts.
 *
 * Moved out of Administration > Devices, where it sat only because that is
 * where the edit screen was built. part_group_id is used by exactly one table,
 * `parts`, so it belongs here. Guarded by parts.edit for the same reason.
 */
?>
    <!-- Add + Search -->
    <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;">
      <?php if (can('parts','edit')): ?>
        <button type="button" class="btn btn-primary" style="min-width:140px;"
                onclick="toggleForm('part-group-add-form')"><?= __('devices.add_part_group') ?></button>
      <?php endif; ?>
      <div style="width:300px;flex-shrink:0;">
        <input type="text" id="part-group-search" placeholder="<?= __('devices.search_part_groups') ?>"
               oninput="filterTable('part-group-table', this.value)" style="width:100%;">
      </div>
    </div>

    <!-- Add form -->
    <?php if (can('parts','edit')): ?>
    <div id="part-group-add-form" style="display:none;margin-bottom:1.25rem;" class="card">
      <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('devices.new_part_group') ?></h2>
      <form method="POST" action="/devices/part-group/store">
        <?= csrf_field() ?>
        <div class="form-grid" style="">
          <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" required placeholder="<?= __('devices.part_group_name_ph') ?>"></div>
          <div class="field"><label><?= __('devices.sort_order') ?></label><input type="number" name="sort_order" value="0" min="0"></div>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
          <button type="button" class="btn" onclick="toggleForm('part-group-add-form')"><?= __('btn.cancel') ?></button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if (empty($part_groups)): ?>
      <p style="font-size:13px;color:var(--text-muted);"><?= __('devices.no_part_groups') ?></p>
    <?php else: ?>
      <table class="data-table" id="part-group-table">
        <thead>
          <tr>
            <th><?= __('label.name') ?></th>
            <th style="text-align:center;"><?= __('devices.parts_tagged') ?></th>
            <th style="text-align:center;"><?= __('devices.sort') ?></th>
            <th style="text-align:center;"><?= __('label.active') ?></th>
            <?php if (can('parts','edit')): ?><th style="text-align:right;"><?= __('label.actions') ?></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($part_groups as $g): ?>
            <tr data-name="<?= strtolower(htmlspecialchars($g['name'])) ?>">
              <td style="font-weight:500;"><?= htmlspecialchars($g['name']) ?></td>
              <td style="text-align:center;color:var(--text-muted);"><?= (int)$g['part_count'] ?></td>
              <td style="text-align:center;color:var(--text-muted);"><?= (int)$g['sort_order'] ?></td>
              <td style="text-align:center;"><?= $g['is_active'] ? '✓' : '—' ?></td>
              <?php if (can('parts','edit')): ?>
              <td style="text-align:right;">
                <button type="button" class="btn-link" onclick='editPartGroup(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)'><?= __('btn.edit') ?></button>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

<div id="part-group-edit-modal" class="dev-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('devices.edit_part_group') ?></h2>
    <form id="part-group-update-form" method="POST" action="/devices/part-group/update">
      <?= csrf_field() ?><input type="hidden" name="id" id="pe-id">
      <div class="form-grid" style="">
        <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" id="pe-name" required></div>
        <div class="field"><label><?= __('devices.sort_order') ?></label><input type="number" name="sort_order" id="pe-sort" min="0"></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <input type="checkbox" name="is_active" id="pe-active" value="1">
        <label for="pe-active" style="font-size:13px;margin-bottom:0;"><?= __('label.active') ?></label>
      </div>
    </form>
    <div class="modal-actions" style="display:flex;gap:8px;align-items:center;">
      <button type="submit" form="part-group-update-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
      <button type="button" class="btn" onclick="closeDevModal()"><?= __('btn.cancel') ?></button>
      <form method="POST" action="/devices/part-group/delete" style="margin-left:auto;" data-confirm="<?= htmlspecialchars(__('devices.confirm_delete_part_group'), ENT_QUOTES) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" id="pd-id">
        <button type="submit" class="btn btn-sm btn-danger"><?= __('btn.delete') ?></button>
      </form>
    </div>
  </div>
</div>

<script>
function toggleForm(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function filterTable(tableId, q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
    row.style.display = !q || row.dataset.name.includes(q) ? '' : 'none';
  });
}
function closeDevModal() { document.querySelectorAll('.dev-modal').forEach(function (m) { m.style.display = 'none'; }); }
function editPartGroup(g) {
  document.getElementById('pe-id').value = g.id; document.getElementById('pe-name').value = g.name || '';
  document.getElementById('pe-sort').value = g.sort_order || 0; document.getElementById('pe-active').checked = (g.is_active == 1);
  document.getElementById('pd-id').value = g.id; document.getElementById('part-group-edit-modal').style.display = 'flex';
}
document.querySelectorAll('.dev-modal').forEach(function (m) { m.addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; }); });
</script>
