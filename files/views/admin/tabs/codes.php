<?php
defined('RMS') or die('Direct access not permitted');
// Which kind is on screen. Same `sub` pattern the Statusi tab uses, so the two
// lists behave alike and the URL stays readable.
$sub = $_GET['sub'] ?? 'error';
if (!in_array($sub, REPAIR_CODE_KINDS, true)) $sub = 'error';

// The controller already selected this kind and paged it; filtering again
// here would drop rows the pager has counted.
$rows = $codes;
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
    <form method="GET" action="/administration" data-live-list style="display:flex;gap:8px;align-items:center;">
      <input type="hidden" name="tab" value="codes">
      <input type="hidden" name="sub" value="<?= htmlspecialchars($sub) ?>">
      <input type="search" name="q" id="list-search" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
             placeholder="<?= __('codes.search') ?>" autocomplete="off" style="width:200px;">
      <select name="brand" style="width:200px;">
        <option value=""><?= __('codes.all_brands') ?></option>
        <?php foreach ($filter_brands as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= (int)($_GET['brand'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="category" style="width:200px;">
        <option value=""><?= __('codes.all_types') ?></option>
        <?php foreach ($filter_categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)($_GET['category'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div id="list-results">
    <?php include views_path('admin/tabs/_codes_results.php'); ?>
  </div>
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

<?php // Same script the Reklamacije and Popravke lists use: typing swaps the
      // table, the filters swap it, and the pager links inside the fragment
      // work through delegation. ?>
<script src="/assets/js/live-list.js"></script>
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
