<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php include views_path('parts/_tabs.php'); ?>

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Search row with Add button inline -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <?php if (can('parts', 'create')): ?>
      <button type="button" class="btn btn-primary" style="min-width:140px;"
              onclick="document.getElementById('add-form').style.display=document.getElementById('add-form').style.display==='none'?'block':'none'">
        <?= __('parts.add_part') ?>
      </button>
    <?php endif; ?>
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="parts-search" placeholder="<?= __('parts.search_name_sku') ?>"
             oninput="filterParts(this.value)" style="width:100%;">
    </div>
  </div>

  <!-- Add form (hidden) -->
  <?php if (can('parts', 'create')): ?>
  <div id="add-form" style="display:none;margin-bottom:1.25rem;" class="card">
    <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('parts.new_part') ?></h2>
    <form method="POST" action="/parts/store">
      <?= csrf_field() ?>
      <div class="form-grid" style="">
        <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" required></div>
        <div class="field">
          <label><?= __('parts.brand') ?></label>
          <?php $brands = db_rows('SELECT id, name FROM device_brands WHERE is_active = 1 ORDER BY name'); ?>
          <select name="brand_id">
            <option value=""><?= __('parts.select_brand') ?></option>
            <option value="0"><?= __('parts.universal') ?></option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('parts.device_group') ?></label>
          <?php $cats = db_rows('SELECT id, name, sku_prefix FROM device_categories WHERE is_active = 1 ORDER BY sort_order, name'); ?>
          <select name="category_id">
            <option value=""><?= __('parts.any_universal') ?></option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?><?= $c['sku_prefix'] ? ' ('.$c['sku_prefix'].'-XXXXXX)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('parts.parts_group') ?></label>
          <?php $part_groups = db_rows('SELECT id, name FROM part_groups WHERE is_active = 1 ORDER BY sort_order, name'); ?>
          <select name="part_group_id">
            <option value=""><?= __('parts.unclassified') ?></option>
            <?php foreach ($part_groups as $g): ?>
              <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('parts.supplier_sku') ?></label><input type="text" name="supplier_sku" placeholder="<?= __('parts.supplier_part_number') ?>"></div>
        <div class="field">
          <label><?= __('parts.supplier') ?></label>
          <select name="supplier_id">
            <option value=""><?= __('parts.no_supplier') ?></option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('parts.unit_price') ?></label><input type="text" name="unit_price" value="0.00"></div>
        <div class="field">
          <label><?= __('parts.vat_rate') ?></label>
          <select name="vat_rate_id">
            <option value=""><?= __('parts.none') ?></option>
            <?php foreach ($vat_rates as $v): ?>
              <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('parts.reorder_level') ?></label><input type="number" name="reorder_level" value="5" min="0"></div>
      </div>
      <div class="field"><label><?= __('label.description') ?></label><textarea name="description" rows="2"></textarea></div>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;"><?= __('parts.sku_autogen') ?></p>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('add-form').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($parts)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('parts.no_parts') ?></p>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>SKU</th>
          <th><?= __('parts.part') ?></th>
          <th><?= __('parts.supplier') ?></th>
          <th><?= __('parts.sku_supplier') ?></th>
          <th style="text-align:center;"><?= __('parts.unit_price') ?></th>
          <th style="text-align:center;"><?= __('parts.total_stock') ?></th>
          <th style="text-align:center;"><?= __('parts.reorder_at') ?></th>
          <th style="text-align:center;"><?= __('label.active') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($parts as $p): ?>
          <tr data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
              data-sku="<?= strtolower(htmlspecialchars($p['internal_sku'] ?? '')) ?>"
              data-supplier-sku="<?= strtolower(htmlspecialchars($p['supplier_sku'] ?? '')) ?>">
            <td style="font-size:12px;color:var(--accent);"><?= htmlspecialchars($p['internal_sku'] ?? '—') ?></td>
            <td style="font-weight:500;"><?= htmlspecialchars($p['name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($p['supplier_name'] ?? '—') ?></td>
            <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($p['supplier_sku'] ?? '—') ?></td>
            <td style="text-align:center;">€<?= number_format((float)$p['unit_price'], 2) ?></td>
            <td style="text-align:center;">
              <span style="color:<?= (int)$p['total_stock'] === 0 ? '#a32d2d' : ((int)$p['total_stock'] <= (int)$p['min_stock'] ? '#854f0b' : 'var(--text-primary)') ?>;font-weight:<?= (int)$p['total_stock'] <= (int)$p['min_stock'] ? '500' : '400' ?>;">
                <?= (int)$p['total_stock'] ?>
              </span>
            </td>
            <td style="text-align:center;color:var(--text-muted);"><?= (int)$p['min_stock'] ?></td>
            <td style="text-align:center;">
              <span class="badge" style="background:<?= $p['is_active'] ? 'var(--accent-bg)' : 'var(--bg-subtle)' ?>;color:<?= $p['is_active'] ? 'var(--accent-text)' : 'var(--text-muted)' ?>;">
                <?= $p['is_active'] ? __('label.yes') : __('label.no') ?>
              </span>
            </td>
            <td>
              <?php if (can('parts', 'edit')): ?>
                <button onclick="editPart(<?= htmlspecialchars(json_encode($p)) ?>)" class="btn-link"><?= __('btn.edit') ?></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php
      // Shared pager — see views/_partials/pager.php.
      $pg_page = $page; $pg_total = $total; $pg_per_page = $per_page;
      $pg_query = ['tab'=>'parts','q'=>$search];
      include views_path('_partials/pager.php');
    ?>
  <?php endif; ?>
</div>

<!-- Edit modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('parts.edit_part') ?></h2>
    <form method="POST" id="edit-form">
      <?= csrf_field() ?>
      <div class="form-grid" style="">
        <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" id="e-name" required></div>
        <div class="field">
          <label><?= __('parts.brand') ?></label>
          <select name="brand_id" id="e-brand">
            <option value=""><?= __('parts.select_brand') ?></option>
            <option value="0"><?= __('parts.universal') ?></option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('parts.device_group') ?></label>
          <select name="category_id" id="e-category">
            <option value=""><?= __('parts.any_universal') ?></option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('parts.parts_group') ?></label>
          <select name="part_group_id" id="e-part-group">
            <option value=""><?= __('parts.unclassified') ?></option>
            <?php foreach ($part_groups as $g): ?>
              <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('parts.supplier_sku') ?></label><input type="text" name="supplier_sku" id="e-sku"></div>
        <div class="field">
          <label><?= __('parts.supplier') ?></label>
          <select name="supplier_id" id="e-supplier">
            <option value=""><?= __('parts.no_supplier') ?></option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('parts.unit_price') ?></label><input type="text" name="unit_price" id="e-price"></div>
        <div class="field">
          <label><?= __('parts.vat_rate') ?></label>
          <select name="vat_rate_id" id="e-vat">
            <option value=""><?= __('parts.none') ?></option>
            <?php foreach ($vat_rates as $v): ?>
              <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('parts.reorder_level') ?></label><input type="number" name="reorder_level" id="e-reorder" min="0"></div>
      </div>
      <div class="field"><label><?= __('label.description') ?></label><textarea name="description" id="e-desc" rows="2"></textarea></div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:1rem;">
        <input type="checkbox" name="is_active" id="e-active" value="1">
        <label for="e-active" style="font-size:13px;margin-bottom:0;"><?= __('label.active') ?></label>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('edit-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function filterParts(q) {
  q = q.toLowerCase().trim();
  let visible = 0;
  document.querySelectorAll('.data-table tbody tr[data-name]').forEach(row => {
    const match = !q
      || row.dataset.name.includes(q)
      || row.dataset.sku.includes(q)
      || row.dataset.supplierSku.includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  if (counter) counter.textContent = visible + ' <?= __('label.total') ?>';
}
function editPart(p) {
  document.getElementById('edit-form').action = '/parts/' + p.id + '/update';
  document.getElementById('e-name').value       = p.name || '';
  document.getElementById('e-brand').value      = p.brand_id      || '';
  document.getElementById('e-category').value   = p.category_id   || '';
  document.getElementById('e-part-group').value = p.part_group_id || '';
  document.getElementById('e-sku').value      = p.supplier_sku || '';
  document.getElementById('e-supplier').value = p.supplier_id || '';
  document.getElementById('e-price').value    = parseFloat(p.unit_price || 0).toFixed(2);
  document.getElementById('e-vat').value      = p.vat_rate_id || '';
  document.getElementById('e-reorder').value  = p.min_stock || p.reorder_level || 5;
  document.getElementById('e-desc').value     = p.description || '';
  document.getElementById('e-active').checked = p.is_active == 1;
  document.getElementById('edit-modal').style.display = 'flex';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
