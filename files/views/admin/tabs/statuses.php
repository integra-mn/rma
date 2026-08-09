<?php
defined('RMS') or die('Direct access not permitted');
// Sub-tab state. `sub` query-string param controls which status set we show.
// Kept short (same naming style as devices' `dtab`) to stay URL-friendly.
$sub = $_GET['sub'] ?? 'rma';
if (!in_array($sub, ['rma', 'repair'], true)) $sub = 'rma';

$active = $sub === 'rma' ? $rma_statuses : $repair_statuses;
$type   = $sub; // 'rma' | 'repair' — passed to modal JS for store/update routing
?>

<!-- Sub-tabs: RMA Statuses | Repair Statuses -->
<div class="tab-bar">
  <?php foreach (['rma' => __('admin.rma_statuses'), 'repair' => __('admin.repair_statuses')] as $k => $label): ?>
    <a href="/administration?tab=statuses&sub=<?= $k ?>"
       class="tab<?= $sub === $k ? ' active' : '' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<div>
  <div style="margin-bottom:1rem;">
    <button type="button" class="btn btn-primary" style="min-width:140px;" onclick="openModal('<?= $type ?>')">
      <?= $sub === 'rma' ? __('admin.add_rma_status') : __('admin.add_repair_status') ?>
    </button>
  </div>
  <table class="data-table" style="table-layout:fixed;width:100%;">
    <thead>
      <tr>
        <th style="width:100px;"><?= __('label.color') ?></th>
        <th style="width:280px;"><?= __('admin.status_label') ?></th>
        <th style="width:280px;"><?= __('admin.status_label_me') ?></th>
        <th style="width:180px;"><?= __('label.code') ?></th>
        <th style="width:100px;text-align:center;"><?= __('label.sort_order') ?></th>
        <th style="text-align:right;"><?= __('label.actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($active as $s): ?>
        <tr>
          <td>
            <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?= htmlspecialchars($s['color']) ?>;"></span>
          </td>
          <td style="font-weight:500;"><?= htmlspecialchars($s['label']) ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['label_me'] ?? '') ?: '—' ?></td>
          <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($s['code']) ?></td>
          <td style="text-align:center;color:var(--text-muted);"><?= (int)$s['sort_order'] ?></td>
          <td style="text-align:right;">
            <button type="button" class="btn btn-sm"
              onclick="editStatus('<?= $type ?>', <?= htmlspecialchars(json_encode($s)) ?>)"><?= __('btn.edit') ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal -->
<div id="status-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:420px;margin:1rem;">
    <h2 id="modal-title" style="font-size:16px;font-weight:500;margin-bottom:1.25rem;"></h2>
    <form method="POST" id="status-form">
      <?= csrf_field() ?>
      <input type="hidden" name="type" id="f-type">
      <input type="hidden" name="id"   id="f-id">
      <div class="field">
        <label><?= __('admin.status_label') ?> *</label>
        <input type="text" name="label" id="f-label" required>
      </div>
      <div class="field">
        <label><?= __('admin.status_label_me') ?></label>
        <input type="text" name="label_me" id="f-label_me">
      </div>
      <div class="field">
        <label><?= __('label.code') ?> * <span style="font-size:11px;color:var(--text-muted);"><?= __('admin.status_code_hint') ?></span></label>
        <input type="text" name="code" id="f-code" pattern="[a-z_]+" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field">
          <label><?= __('label.color') ?></label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input type="color" name="color" id="f-color" value="#888780"
                   style="width:36px;height:36px;padding:2px;border:0.5px solid var(--border);border-radius:6px;cursor:pointer;">
            <input type="text" id="f-color-hex" value="#888780" maxlength="7"
                   style="flex:1;" oninput="syncColor(this.value)">
          </div>
        </div>
        <div class="field">
          <label><?= __('label.sort_order') ?></label>
          <input type="number" name="sort_order" id="f-sort" value="10" min="0" max="999">
        </div>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
          <input type="checkbox" name="is_terminal" id="f-terminal" value="1"
                 style="width:auto;height:auto;">
          <?= $sub === 'repair' ? __('admin.status_terminal_repair') : __('admin.status_terminal') ?>
        </label>
      </div>
      <div style="display:flex;gap:8px;margin-top:1rem;">
        <button type="submit" class="btn btn-primary" style="min-width:100px;" id="modal-save"><?= __('btn.save') ?></button>
        <button type="button" class="btn" style="min-width:100px;" onclick="closeModal()"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
const COLOR_PRESETS = ['#888780','#378ADD','#7F77DD','#1D9E75','#EF9F27','#A32D2D','#3B6D11','#E05AAB'];
const STATUS_TITLES = {
  add:  { rma: <?= json_encode(__('admin.add_rma_status_title')) ?>,  repair: <?= json_encode(__('admin.add_repair_status_title')) ?> },
  edit: { rma: <?= json_encode(__('admin.edit_rma_status_title')) ?>, repair: <?= json_encode(__('admin.edit_repair_status_title')) ?> }
};
const SAVE_LABEL = <?= json_encode(__('btn.save')) ?>;
const SAVE_CHANGES_LABEL = <?= json_encode(__('btn.save_changes')) ?>;

function openModal(type) {
  document.getElementById('modal-title').textContent = STATUS_TITLES.add[type] || STATUS_TITLES.add.rma;
  document.getElementById('status-form').action = '/admin/status/store';
  document.getElementById('f-type').value  = type;
  document.getElementById('f-id').value    = '';
  document.getElementById('f-label').value = '';
  document.getElementById('f-label_me').value = '';
  document.getElementById('f-code').value  = '';
  document.getElementById('f-color').value = '#888780';
  document.getElementById('f-color-hex').value = '#888780';
  document.getElementById('f-sort').value  = '10';
  document.getElementById('f-terminal').checked = false;
  document.getElementById('modal-save').textContent = SAVE_LABEL;
  document.getElementById('status-modal').style.display = 'flex';
  document.getElementById('f-label').focus();
}

function editStatus(type, s) {
  document.getElementById('modal-title').textContent = STATUS_TITLES.edit[type] || STATUS_TITLES.edit.rma;
  document.getElementById('status-form').action = '/admin/status/update';
  document.getElementById('f-type').value  = type;
  document.getElementById('f-id').value    = s.id;
  document.getElementById('f-label').value = s.label;
  document.getElementById('f-label_me').value = s.label_me || '';
  document.getElementById('f-code').value  = s.code;
  document.getElementById('f-color').value = s.color;
  document.getElementById('f-color-hex').value = s.color;
  document.getElementById('f-sort').value  = s.sort_order;
  document.getElementById('f-terminal').checked = !!parseInt(s.is_terminal);
  document.getElementById('modal-save').textContent = SAVE_CHANGES_LABEL;
  document.getElementById('status-modal').style.display = 'flex';
  document.getElementById('f-label').focus();
}

function closeModal() {
  document.getElementById('status-modal').style.display = 'none';
}

function syncColor(hex) {
  if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
    document.getElementById('f-color').value = hex;
  }
}

document.getElementById('f-color').addEventListener('input', function() {
  document.getElementById('f-color-hex').value = this.value;
});

// Auto-generate code from label
document.getElementById('f-label').addEventListener('input', function() {
  if (!document.getElementById('f-id').value) {
    document.getElementById('f-code').value = this.value.toLowerCase()
      .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }
});

document.getElementById('status-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
