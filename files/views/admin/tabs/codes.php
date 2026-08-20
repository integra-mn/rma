<?php
defined('RMS') or die('Direct access not permitted');
// Which kind is on screen. Same `sub` pattern the Statusi tab uses, so the two
// lists behave alike and the URL stays readable.
$sub = $_GET['sub'] ?? 'error';
if (!in_array($sub, REPAIR_CODE_KINDS, true)) $sub = 'error';

$rows = array_values(array_filter($codes, fn($c) => $c['kind'] === $sub));
// Same rule as Statusi: one name column, in the language the app is set to.
$lang_en = (current_user()['lang'] ?? setting('default_lang', 'en')) === 'en';
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
    <?php // A real form now: with TCL's 1,257 codes the filtering has to happen
          // in the database, so choosing one reloads rather than hiding rows.
          // width, not min-width — a select grows to its widest option, and the
          // brand list stretches to fit "Crnogorski Telekom" while the type
          // list would sit at its floor. ?>
    <form method="GET" action="/administration" style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="tab" value="codes">
      <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
      <input type="search" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
             placeholder="<?= __('codes.search') ?>" style="width:200px;">
      <select name="brand" onchange="this.form.submit()" style="width:200px;">
        <option value=""><?= __('codes.all_brands') ?></option>
        <?php foreach ($filter_brands as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= (int)($_GET['brand'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="category" onchange="this.form.submit()" style="width:200px;">
        <option value=""><?= __('codes.all_types') ?></option>
        <?php foreach ($filter_categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)($_GET['category'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (!$rows): ?>
    <div class="card" style="text-align:center;padding:2rem;color:var(--text-muted);font-size:13px;">
      <?= __('codes.empty') ?>
    </div>
  <?php else: ?>
  <table class="data-table" style="table-layout:fixed;width:100%;">
    <thead>
      <tr>
        <?php // Percentages, and Akcije given a share of its own — the pixel
              // widths here left it unset, so it swallowed whatever the other
              // columns did not claim. ?>
        <th style="width:5%;"><?= __('label.code') ?></th>
        <th style="width:63%;"><?= __('admin.status_label') ?></th>
        <th style="width:8%;text-align:center;"><?= __('codes.scope') ?></th>
        <th style="width:8%;text-align:center;"><?= __('label.sort_order') ?></th>
        <th style="width:8%;text-align:center;"><?= __('label.status') ?></th>
        <th style="width:8%;text-align:right;"><?= __('label.actions') ?></th>
      </tr>
    </thead>
    <tbody id="codes-body">
      <?php foreach ($rows as $c): ?>
        <tr>
          <?php // Plain text, like every other cell. The monospace face at 12px
                // set the code apart as something technical; it is the thing
                // this screen exists for. ?>
          <td style="font-weight:500;"><?= htmlspecialchars($c['code']) ?></td>
          <?php // Falls back to the English name when no ME one is set. ?>
          <td><?= htmlspecialchars(
                $lang_en ? $c['label'] : (($c['label_me'] ?? '') !== '' ? $c['label_me'] : $c['label'])
          ) ?></td>
          <?php // The brand alone, now the column is called Marka. It used to
                // read "TCL · Smartwatch", which no longer matches the heading
                // and would not fit 10% anyway. Device type is a filter now. ?>
          <td style="font-size:12px;color:var(--text-muted);text-align:center;"><?= htmlspecialchars($c['brand_name'] ?? '') ?: '—' ?></td>
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
