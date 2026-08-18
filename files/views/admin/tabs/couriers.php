<?php defined('RMS') or die(); ?>

  <?php if ($success ?? null): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error ?? null): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <p style="font-size:13px;color:var(--text-muted);margin-bottom:1rem;">
    <?= __('ship.courier_intro') ?>
  </p>

  <?php if (can('settings','edit')): ?>
  <div style="margin-bottom:1.25rem;">
    <button type="button" class="btn btn-primary" style="min-width:140px;"
            onclick="var f=document.getElementById('courier-add');f.style.display=f.style.display==='none'?'block':'none';">
      <?= __('ship.courier_add') ?>
    </button>
  </div>

  <div id="courier-add" style="display:none;margin-bottom:1.25rem;" class="card">
    <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('ship.courier_new') ?></h2>
    <form method="POST" action="/admin/courier/store">
      <?= csrf_field() ?>
      <div class="form-grid" style="">
        <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" required></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone"></div>
        <div class="field" style="grid-column:1/-1;">
          <label><?= __('ship.tracking_url') ?> <span style="font-size:11px;color:var(--text-muted);"><?= __('ship.tracking_url_hint') ?></span></label>
          <input type="text" name="tracking_url" placeholder="https://courier.example/track?id={tracking}">
        </div>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('courier-add').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($couriers)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('ship.no_couriers') ?></p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr>
        <th><?= __('label.name') ?></th>
        <th><?= __('ship.tracking_url') ?></th>
        <th><?= __('label.phone') ?></th>
        <th style="text-align:center;"><?= __('label.status') ?></th>
        <th style="text-align:right;"><?= __('label.actions') ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($couriers as $c): ?>
          <tr>
            <td style="font-weight:500;"><?= htmlspecialchars($c['name']) ?></td>
            <td style="color:var(--text-secondary);font-size:12px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($c['tracking_url'] ?: '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['phone'] ?: '—') ?></td>
            <td style="text-align:center;">
              <span class="badge" style="background:<?= $c['is_active'] ? 'var(--accent-bg)' : 'var(--bg-subtle)' ?>;color:<?= $c['is_active'] ? 'var(--accent-text)' : 'var(--text-muted)' ?>;"><?= $c['is_active'] ? __('label.active') : __('label.disabled') ?></span>
            </td>
            <td style="text-align:right;white-space:nowrap;">
              <?php if (can('settings','edit')): ?>
                <button type="button" class="btn-link" onclick='editCourier(<?= htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)'><?= __('btn.edit') ?></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php if (can('settings','edit')): ?>
<div id="courier-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('ship.courier_edit') ?></h2>
    <form id="courier-update-form" method="POST" action="/admin/courier/update">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="ce-id">
      <div class="form-grid" style="">
        <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" id="ce-name" required></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone" id="ce-phone"></div>
        <div class="field" style="grid-column:1/-1;"><label><?= __('ship.tracking_url') ?></label><input type="text" name="tracking_url" id="ce-url"></div>
      </div>
    </form>

    <!-- One row: Save/Cancel left, Disable/Delete pushed right -->
    <div class="modal-actions" style="display:flex;gap:8px;align-items:center;">
      <button type="submit" form="courier-update-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
      <button type="button" class="btn" onclick="document.getElementById('courier-edit-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      <form method="POST" action="/admin/courier/toggle" style="display:inline;margin-left:auto;">
        <?= csrf_field() ?><input type="hidden" name="id" id="ce-toggle-id">
        <button type="submit" class="btn btn-sm" id="ce-toggle-btn"><?= __('btn.disable') ?></button>
      </form>
      <form method="POST" action="/admin/courier/delete" style="display:inline;"
            data-confirm="<?= htmlspecialchars(__('ship.courier_confirm_delete'), ENT_QUOTES) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" id="ce-delete-id">
        <button type="submit" class="btn btn-sm btn-danger"><?= __('btn.delete') ?></button>
      </form>
    </div>
  </div>
</div>
<script>
function editCourier(c) {
  document.getElementById('ce-id').value    = c.id;
  document.getElementById('ce-name').value  = c.name || '';
  document.getElementById('ce-phone').value = c.phone || '';
  document.getElementById('ce-url').value   = c.tracking_url || '';
  document.getElementById('ce-toggle-id').value = c.id;
  document.getElementById('ce-delete-id').value = c.id;
  var t = document.getElementById('ce-toggle-btn');
  var active = (c.is_active == 1);
  t.textContent = active ? <?= json_encode(__('btn.disable')) ?> : <?= json_encode(__('btn.enable')) ?>;
  t.className = 'btn btn-sm' + (active ? ' btn-danger' : '');
  document.getElementById('courier-edit-modal').style.display = 'flex';
}
document.getElementById('courier-edit-modal').addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; });
</script>
<?php endif; ?>
