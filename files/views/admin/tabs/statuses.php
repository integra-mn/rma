<?php
defined('RMS') or die('Direct access not permitted');
// One list. Statusi servisa is gone: it held a second vocabulary for the same
// case — "U toku" beside "Na dijagnostici" — and all six of its statuses now
// exist here, marked "Na cemu se koristi = oboje". The bench still reads the
// old table until step 2 repoints it; nothing on this screen governed that, so
// removing the tab changes no behaviour.
$lang_en = (current_user()['lang'] ?? setting('default_lang', 'en')) === 'en';
$active  = $rma_statuses;
$type   = 'rma';   // the store/update handlers still route on it
?>

<div>
  <div style="margin-bottom:1rem;">
    <button type="button" class="btn btn-primary" style="min-width:140px;" onclick="openModal('<?= $type ?>')">
      <?= __('admin.add_rma_status') ?>
    </button>
  </div>
  <table class="data-table" style="table-layout:fixed;width:100%;">
    <thead>
      <tr>
        <th style="width:100px;"><?= __('label.color') ?></th>
        <?php // The English name is only worth a column to somebody reading in
              // English; in Montenegrin it is a second copy of the next column.
              // Kod is gone altogether — it belongs to the database, and the
              // edit dialog still shows it to anyone who needs it. ?>
        <?php if ($lang_en): ?>
          <th style="width:300px;"><?= __('admin.status_label') ?></th>
          <th style="width:300px;"><?= __('admin.status_label_me') ?></th>
        <?php else: ?>
          <th style="width:340px;"><?= __('admin.status_label') ?></th>
        <?php endif; ?>
        <?php // Same order as the dialog: the three yes/no answers, then the
              // two "which of these" ones. Reading a row should feel like
              // reading the form that produced it. ?>
        <th style="width:110px;text-align:center;"><?= __('admin.status_notify') ?></th>
        <th style="width:110px;text-align:center;"><?= __('admin.status_recur') ?></th>
        <th style="width:110px;text-align:center;"><?= __('admin.status_terminal_col') ?></th>
        <th style="width:190px;"><?= __('admin.status_roles') ?></th>
        <th style="width:150px;"><?= __('admin.status_applies') ?></th>
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
          <?php if ($lang_en): ?>
            <td style="font-weight:500;"><?= htmlspecialchars($s['label']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['label_me'] ?? '') ?: '—' ?></td>
          <?php else: ?>
            <?php // Falls back to the English name when no ME one is set, or a
                  // half-filled status would show a blank row and look broken. ?>
            <td style="font-weight:500;"><?= htmlspecialchars(($s['label_me'] ?? '') !== '' ? $s['label_me'] : $s['label']) ?></td>
          <?php endif; ?>
          <?php
            // Three columns, one question each, both answers spelled out. A
            // dash for one side and nothing for the other made the reader work
            // out which way each column ran.
            $yn = fn(bool $on) => $on
              ? '<span class="badge badge-pill-fixed" style="background:#e1f5ee;color:#085041;border:0.5px solid #5dcaa5;">' . __('label.yes') . '</span>'
              : '<span class="badge badge-pill-fixed" style="background:#f4f4f0;color:#5f5e5a;border:0.5px solid #d3d1c7;">' . __('label.no') . '</span>';
          ?>
          <td style="text-align:center;"><?= $yn(!empty($s['notify'])) ?></td>
          <td style="text-align:center;"><?= $yn((int)($s['can_recur'] ?? 1) === 1) ?></td>
          <td style="text-align:center;"><?= $yn((int)($s['is_terminal'] ?? 0) === 1) ?></td>
            <?php $roles = status_roles($s['roles'] ?? null); ?>
            <td>
              <?php if (!$roles): ?>
                <span style="color:var(--text-muted);"><?= __('admin.status_roles_all') ?></span>
              <?php else: ?>
                <?php foreach ($roles as $r): ?>
                  <span class="badge badge-pill-fixed" style="background:#eef1f7;color:#3b4a63;border:0.5px solid #b9c4d6;margin-right:4px;"><?= htmlspecialchars(role_label($r)) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </td>
            <?php
              // Both desks, or one. Shown beside Postavlja because the two
              // answer neighbouring questions: who may set it, and where.
              $scope = status_applies_to($s['applies_to'] ?? null);
              $scope_label = ['rma' => __('admin.status_applies_rma'),
                              'repair' => __('admin.status_applies_repair'),
                              'both' => __('admin.status_applies_both')][$scope];
            ?>
            <td>
              <span class="badge badge-pill-fixed" style="<?= $scope === 'both'
                    ? 'background:#e1f5ee;color:#085041;border:0.5px solid #5dcaa5;'
                    : 'background:#eef1f7;color:#3b4a63;border:0.5px solid #b9c4d6;' ?>"><?= $scope_label ?></span>
              <?php if ((int)($s['is_terminal_job'] ?? 0) === 1 && $scope !== 'rma'): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px;"><?= __('admin.status_terminal_job_short') ?></div>
              <?php endif; ?>
            </td>
          <td style="text-align:center;color:var(--text-muted);"><?= (int)$s['sort_order'] ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <button type="button" class="btn-link"
              onclick="editStatus('<?= $type ?>', <?= htmlspecialchars(json_encode($s)) ?>)"><?= __('btn.edit') ?></button>
            <?php
              // A status any case has ever been in cannot go: rma_status_history
              // points at it, and deleting would blank a line of somebody's
              // case history. Unused ones are free to remove.
              $used = (int)($status_usage[(int)$s['id']] ?? 0);
            ?>
            <?php if ($used === 0): ?>
              <form method="POST" action="/admin/status/delete" style="display:inline;margin-left:10px;"
                    data-confirm="<?= htmlspecialchars(__('admin.status_confirm_delete'), ENT_QUOTES) ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn-link" style="color:#a32d2d;"><?= __('btn.delete') ?></button>
              </form>
            <?php else: ?>
              <span style="margin-left:10px;font-size:11px;color:var(--text-muted);"
                    title="<?= htmlspecialchars(__('admin.status_in_use_hint'), ENT_QUOTES) ?>"><?= __('admin.status_in_use') ?></span>
            <?php endif; ?>
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
          <input type="checkbox" name="notify" id="f-notify" value="1"
                 style="width:auto;height:auto;">
          <?= __('admin.status_notify_label') ?>
        </label>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
          <input type="checkbox" name="can_recur" id="f-recur" value="1"
                 style="width:auto;height:auto;">
          <?= __('admin.status_recur_label') ?>
        </label>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
          <input type="checkbox" name="is_terminal" id="f-terminal" value="1"
                 style="width:auto;height:auto;">
          <?= __('admin.status_terminal') ?>
        </label>
      </div>
      <div class="field">
        <label><?= __('admin.status_roles_label') ?></label>
        <div style="display:flex;gap:16px;margin-top:4px;">
          <?php foreach (STATUS_ROLES as $r): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
              <input type="checkbox" name="roles[]" value="<?= $r ?>"
                     class="f-role" data-role="<?= $r ?>"
                     style="width:auto;height:auto;">
              <?= htmlspecialchars(role_label($r)) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php // Boxes rather than a dropdown, because "oboje" was never a third
            // thing — there are two records a status can sit on, and both can be
            // true. It also reads like Postavlja right above it: same shape,
            // same gesture, same kind of answer. Unticking both is refused on
            // save, which is the one thing the dropdown got for free. ?>
      <div class="field">
        <label><?= __('admin.status_applies_label') ?></label>
        <div style="display:flex;gap:16px;margin-top:4px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
            <input type="checkbox" name="applies[]" value="rma" id="f-applies-rma"
                   style="width:auto;height:auto;">
            <?= __('admin.status_applies_rma') ?>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
            <input type="checkbox" name="applies[]" value="repair" id="f-applies-repair"
                   onchange="syncJobTerminal()" style="width:auto;height:auto;">
            <?= __('admin.status_applies_repair') ?>
          </label>
        </div>
      </div>
      <?php // Hidden while the status is case-only: there is no bench work to
            // finish, so the tick would be a question about nothing. ?>
      <div class="field" id="f-job-terminal-wrap">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
          <input type="checkbox" name="is_terminal_job" id="f-terminal-job" value="1"
                 style="width:auto;height:auto;">
          <?= __('admin.status_terminal_job_label') ?>
        </label>
        <p style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= __('admin.status_terminal_job_hint') ?></p>
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

// Every caller below tolerates the role boxes being absent. They were once
// hidden on the repair sub-tab; that tab is gone, but a missing element must
// still not throw.
function setRecur(on) {
  var box = document.getElementById('f-recur');
  if (box) box.checked = !!on;
}

function setRoles(csv) {
  const picked = (csv || '').split(',').map(s => s.trim()).filter(Boolean);
  document.querySelectorAll('.f-role').forEach(cb => {
    cb.checked = picked.includes(cb.dataset.role);
  });
}

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
  document.getElementById('f-notify').checked = false;
  setRoles('');
  setRecur(true);
  setApplies('rma');
  var tj = document.getElementById('f-terminal-job');
  if (tj) { tj.checked = false; }
  syncJobTerminal();
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
  document.getElementById('f-notify').checked = !!parseInt(s.notify);
  setRoles(s.roles);
  // Absent on repair statuses and on a database that has not run the migration;
  // both mean "can recur", which is the harmless answer.
  setRecur(s.can_recur === undefined || !!parseInt(s.can_recur));
  setApplies(s.applies_to || 'rma');
  var tj2 = document.getElementById('f-terminal-job');
  if (tj2) { tj2.checked = parseInt(s.is_terminal_job || 0) === 1; }
  syncJobTerminal();
  document.getElementById('modal-save').textContent = SAVE_CHANGES_LABEL;
  document.getElementById('status-modal').style.display = 'flex';
  document.getElementById('f-label').focus();
}

// "Finishes the work" only means something where work happens.
function syncJobTerminal() {
  var rep = document.getElementById('f-applies-repair');
  var box = document.getElementById('f-job-terminal-wrap');
  if (!rep || !box) return;
  box.style.display = rep.checked ? '' : 'none';
}

// 'rma' | 'repair' | 'both' in the column, two booleans on screen.
function setApplies(v) {
  var r = document.getElementById('f-applies-rma');
  var p = document.getElementById('f-applies-repair');
  if (!r || !p) return;
  v = v || 'rma';
  r.checked = (v === 'rma' || v === 'both');
  p.checked = (v === 'repair' || v === 'both');
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
