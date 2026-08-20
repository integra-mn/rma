<?php
defined('RMS') or die('Direct access not permitted');
// Which kind is on screen. Same `sub` pattern the Statusi tab uses, so the two
// lists behave alike and the URL stays readable.
$sub = $_GET['sub'] ?? 'error';
if (!in_array($sub, REPAIR_CODE_KINDS, true)) $sub = 'error';

$rows = array_values(array_filter($codes, fn($c) => $c['kind'] === $sub));
?>

<!-- Sub-tabs: Kodovi greške | Kodovi rješenja -->
<div class="tab-bar">
  <?php foreach (repair_code_kinds() as $k => $label): ?>
    <a href="/administration?tab=codes&sub=<?= $k ?>"
       class="tab<?= $sub === $k ? ' active' : '' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<div>
  <div style="margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <button type="button" class="btn btn-primary" style="min-width:140px;" onclick="openCodeModal('<?= $sub ?>')">
      <?= __('codes.add') ?>
    </button>
    <?php // Filters narrow the list the same way the repair screen narrows the
          // dropdown, so what an admin sees here is what the bench will get. ?>
    <div style="display:flex;gap:8px;align-items:center;">
      <?php // width, not min-width: a select grows to its widest option, so two
            // with the same floor still render at different sizes - the brand
            // list stretched to fit "Crnogorski Telekom" while the type list
            // sat at its floor. 200px holds that name with room for a longer
            // one, and holds both filters to the same size. ?>
      <select id="filter-brand" onchange="filterCodes()" style="width:200px;">
        <option value=""><?= __('codes.all_brands') ?></option>
        <?php foreach ($brands as $b): ?>
          <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="filter-category" onchange="filterCodes()" style="width:200px;">
        <option value=""><?= __('codes.all_types') ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="card" style="text-align:center;padding:2rem;color:var(--text-muted);font-size:13px;">
      <?= __('codes.empty') ?>
    </div>
  <?php else: ?>
  <table class="data-table" style="table-layout:fixed;width:100%;">
    <thead>
      <tr>
        <th style="width:130px;"><?= __('label.code') ?></th>
        <th style="width:260px;"><?= __('admin.status_label') ?></th>
        <th style="width:260px;"><?= __('admin.status_label_me') ?></th>
        <th style="width:180px;"><?= __('codes.scope') ?></th>
        <th style="width:90px;text-align:center;"><?= __('label.sort_order') ?></th>
        <th style="width:90px;text-align:center;"><?= __('label.status') ?></th>
        <th style="text-align:right;"><?= __('label.actions') ?></th>
      </tr>
    </thead>
    <tbody id="codes-body">
      <?php foreach ($rows as $c): ?>
        <tr data-brand="<?= (int)($c['brand_id'] ?? 0) ?>" data-category="<?= (int)($c['category_id'] ?? 0) ?>">
          <td style="font-weight:500;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;"><?= htmlspecialchars($c['code']) ?></td>
          <td><?= htmlspecialchars($c['label']) ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['label_me'] ?? '') ?: '—' ?></td>
          <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars(repair_code_scope($c)) ?></td>
          <td style="text-align:center;color:var(--text-muted);"><?= (int)$c['sort_order'] ?></td>
          <td style="text-align:center;">
            <?php if ((int)$c['is_active'] === 1): ?>
              <span class="badge badge-pill-fixed" style="background:#e1f5ee;color:#085041;border:0.5px solid #5dcaa5;"><?= __('label.active') ?></span>
            <?php else: ?>
              <span class="badge badge-pill-fixed" style="background:#f4f4f0;color:#5f5e5a;border:0.5px solid #d3d1c7;"><?= __('label.inactive') ?></span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;">
            <button type="button" class="btn-link"
              onclick="editCode(<?= htmlspecialchars(json_encode($c)) ?>)"><?= __('btn.edit') ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p id="codes-none" style="display:none;font-size:13px;color:var(--text-muted);padding:1rem 0;"><?= __('codes.none_match') ?></p>
  <?php endif; ?>
</div>

<!-- Modal -->
<div id="code-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:460px;margin:1rem;">
    <h2 id="code-modal-title" style="font-size:16px;font-weight:500;margin-bottom:1.25rem;"></h2>
    <form method="POST" id="code-form">
      <?= csrf_field() ?>
      <input type="hidden" name="kind" id="c-kind">
      <input type="hidden" name="id"   id="c-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field">
          <label><?= __('label.code') ?> *</label>
          <input type="text" name="code" id="c-code" maxlength="60" required>
        </div>
        <div class="field">
          <label><?= __('label.sort_order') ?></label>
          <input type="number" name="sort_order" id="c-sort" value="10" min="0" max="999">
        </div>
      </div>
      <div class="field">
        <label><?= __('admin.status_label') ?> *</label>
        <input type="text" name="label" id="c-label" maxlength="160" required>
      </div>
      <div class="field">
        <label><?= __('admin.status_label_me') ?></label>
        <input type="text" name="label_me" id="c-label_me" maxlength="160">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field">
          <label><?= __('rma.brand') ?></label>
          <select name="brand_id" id="c-brand">
            <option value=""><?= __('codes.any_brand') ?></option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('codes.device_type') ?></label>
          <select name="category_id" id="c-category">
            <option value=""><?= __('codes.any_type') ?></option>
            <?php foreach ($categories as $c2): ?>
              <option value="<?= (int)$c2['id'] ?>"><?= htmlspecialchars($c2['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field">
        <label><?= __('codes.note') ?></label>
        <textarea name="note" id="c-note" style="resize:none;height:80px;"></textarea>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
          <input type="checkbox" name="is_active" id="c-active" value="1" style="width:auto;height:auto;">
          <?= __('codes.active_label') ?>
        </label>
      </div>
      <div style="display:flex;gap:8px;margin-top:1rem;">
        <button type="submit" class="btn btn-primary" style="min-width:100px;" id="code-save"><?= __('btn.save') ?></button>
        <button type="button" class="btn" style="min-width:100px;" onclick="closeCodeModal()"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
const CODE_TITLES = { add: <?= json_encode(__('codes.add_title')) ?>, edit: <?= json_encode(__('codes.edit_title')) ?> };
const CODE_SAVE   = <?= json_encode(__('btn.save')) ?>;
const CODE_SAVE2  = <?= json_encode(__('btn.save_changes')) ?>;

// A row with no brand (or no type) applies to all of them, so it stays visible
// whatever the filter says — hiding it would suggest the bench will not be
// offered it, and the bench will.
function filterCodes() {
  const b = document.getElementById('filter-brand').value;
  const c = document.getElementById('filter-category').value;
  const body = document.getElementById('codes-body');
  if (!body) return;
  let shown = 0;
  body.querySelectorAll('tr').forEach(function (tr) {
    const rb = tr.dataset.brand, rc = tr.dataset.category;
    const ok = (!b || rb === '0' || rb === b) && (!c || rc === '0' || rc === c);
    tr.style.display = ok ? '' : 'none';
    if (ok) shown++;
  });
  document.getElementById('codes-none').style.display = shown ? 'none' : '';
}

function openCodeModal(kind) {
  document.getElementById('code-modal-title').textContent = CODE_TITLES.add;
  document.getElementById('code-form').action = '/admin/code/store';
  document.getElementById('c-kind').value = kind;
  document.getElementById('c-id').value = '';
  document.getElementById('c-code').value = '';
  document.getElementById('c-label').value = '';
  document.getElementById('c-label_me').value = '';
  document.getElementById('c-brand').value = '';
  document.getElementById('c-category').value = '';
  document.getElementById('c-note').value = '';
  document.getElementById('c-sort').value = '10';
  document.getElementById('c-active').checked = true;
  document.getElementById('code-save').textContent = CODE_SAVE;
  document.getElementById('code-modal').style.display = 'flex';
  document.getElementById('c-code').focus();
}

function editCode(c) {
  document.getElementById('code-modal-title').textContent = CODE_TITLES.edit;
  document.getElementById('code-form').action = '/admin/code/update';
  document.getElementById('c-kind').value = c.kind;
  document.getElementById('c-id').value = c.id;
  document.getElementById('c-code').value = c.code;
  document.getElementById('c-label').value = c.label;
  document.getElementById('c-label_me').value = c.label_me || '';
  document.getElementById('c-brand').value = c.brand_id || '';
  document.getElementById('c-category').value = c.category_id || '';
  document.getElementById('c-note').value = c.note || '';
  document.getElementById('c-sort').value = c.sort_order;
  document.getElementById('c-active').checked = parseInt(c.is_active) === 1;
  document.getElementById('code-save').textContent = CODE_SAVE2;
  document.getElementById('code-modal').style.display = 'flex';
  document.getElementById('c-code').focus();
}

function closeCodeModal() { document.getElementById('code-modal').style.display = 'none'; }

document.getElementById('code-modal').addEventListener('click', function (e) {
  if (e.target === this) closeCodeModal();
});
</script>
