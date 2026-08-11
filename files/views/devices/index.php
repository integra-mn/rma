<?php defined('RMS') or die('Direct access not permitted');
$dtab = $_GET['dtab'] ?? 'brands';
?>

<!-- Devices page — sub-tabs and all content capped at --w-content to match
     the rest of Administration. Padding is provided by admin/index.php, don't
     add it again here or it doubles up. -->
<div style="max-width:var(--w-content);">

  <!-- Sub-tabs -->
  <div class="tab-bar">
    <?php foreach ([
      'brands'       => __('catalog.brands'),
      'models'       => __('catalog.models'),
      'groups'       => __('devices.device_group'),
    ] as $t => $l): ?>
      <a href="/devices?dtab=<?= $t ?>"
         class="tab<?= $dtab===$t ? ' active' : '' ?>">
        <?= $l ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ── GROUPS ── -->
  <?php if ($dtab === 'groups'): ?>

    <!-- Add + Search -->
    <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;">
      <?php if (can('devices','edit')): ?>
        <button type="button" class="btn btn-primary" style="min-width:140px;"
                onclick="toggleForm('group-add-form')"><?= __('devices.add_group') ?></button>
      <?php endif; ?>
      <div style="width:300px;flex-shrink:0;">
        <input type="text" id="group-search" placeholder="<?= __('devices.search_groups') ?>"
               oninput="filterTable('group-table', this.value)" style="width:100%;">
      </div>
    </div>

    <!-- Add form -->
    <?php if (can('devices','edit')): ?>
    <div id="group-add-form" style="display:none;margin-bottom:1.25rem;" class="card">
      <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('devices.new_group') ?></h2>
      <form method="POST" action="/devices/category/store">
      <?= csrf_field() ?>
        <div class="form-grid" style="">
          <div class="field"><label><?= __('devices.group_name') ?> *</label><input type="text" name="name" required placeholder="<?= __('devices.group_name_ph') ?>"></div>
          <div class="field"><label><?= __('devices.sku_prefix') ?></label><input type="text" name="sku_prefix" placeholder="<?= __('devices.sku_prefix_ph') ?>" maxlength="6"></div>
          <div class="field"><label><?= __('devices.sort_order') ?></label><input type="number" name="sort_order" value="0" min="0"></div>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
          <button type="button" class="btn" onclick="toggleForm('group-add-form')"><?= __('btn.cancel') ?></button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
      <p style="font-size:13px;color:var(--text-muted);"><?= __('devices.no_groups') ?></p>
    <?php else: ?>
      <table class="data-table" id="group-table">
        <thead>
          <tr>
            <th><?= __('label.name') ?></th>
            <th><?= __('devices.sku_prefix') ?></th>
            <th style="text-align:center;"><?= __('devices.sort') ?></th>
            <?php if (can('devices','edit')): ?><th style="text-align:right;"><?= __('label.actions') ?></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>">
              <td style="font-weight:500;"><?= htmlspecialchars($c['name']) ?></td>
              <td style="color:var(--accent);"><?= htmlspecialchars($c['sku_prefix'] ?? '—') ?></td>
              <td style="text-align:center;color:var(--text-muted);"><?= (int)$c['sort_order'] ?></td>
              <?php if (can('devices','edit')): ?>
              <td style="text-align:right;">
                <button type="button" class="btn-link" onclick='editGroup(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)'><?= __('btn.edit') ?></button>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  <!-- ── BRANDS ── -->
  <?php elseif ($dtab === 'brands'): ?>

    <!-- Add + Search -->
    <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;">
      <?php if (can('devices','edit')): ?>
        <button type="button" class="btn btn-primary" style="min-width:140px;"
                onclick="toggleForm('brand-add-form')"><?= __('devices.add_brand') ?></button>
      <?php endif; ?>
      <div style="width:300px;flex-shrink:0;">
        <input type="text" id="brand-search" placeholder="<?= __('devices.search_brands') ?>"
               oninput="filterTable('brand-table', this.value)" style="width:100%;">
      </div>
    </div>

    <!-- Add form -->
    <?php if (can('devices','edit')): ?>
    <div id="brand-add-form" style="display:none;margin-bottom:1.25rem;" class="card">
      <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('devices.new_brand') ?></h2>
      <form method="POST" action="/devices/brand/store">
      <?= csrf_field() ?>
        <div class="form-grid" style="">
          <div class="field"><label><?= __('catalog.brand_name') ?> *</label><input type="text" name="name" required placeholder="<?= __('devices.brand_name_ph') ?>"></div>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
          <button type="button" class="btn" onclick="toggleForm('brand-add-form')"><?= __('btn.cancel') ?></button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if (empty($brands)): ?>
      <p style="font-size:13px;color:var(--text-muted);"><?= __('catalog.no_brands') ?></p>
    <?php else: ?>
      <table class="data-table" id="brand-table">
        <thead>
          <tr>
            <th><?= __('devices.brand') ?></th>
            <th style="text-align:center;"><?= __('catalog.models') ?></th>
            <?php if (can('devices','edit')): ?><th style="text-align:right;"><?= __('label.actions') ?></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($brands as $b): ?>
            <tr data-name="<?= strtolower(htmlspecialchars($b['name'])) ?>">
              <td style="font-weight:500;"><?= htmlspecialchars($b['name']) ?></td>
              <td style="text-align:center;color:var(--text-muted);"><?= (int)$b['model_count'] ?></td>
              <?php if (can('devices','edit')): ?>
              <td style="text-align:right;">
                <button type="button" class="btn-link" onclick='editBrand(<?= htmlspecialchars(json_encode($b), ENT_QUOTES) ?>)'><?= __('btn.edit') ?></button>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  <!-- ── MODELS ── -->
  <?php elseif ($dtab === 'models'): ?>

    <!-- Add + Search -->
    <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;">
      <?php if (can('devices','edit')): ?>
        <button type="button" class="btn btn-primary" style="min-width:140px;"
                onclick="toggleForm('model-add-form')"><?= __('catalog.add_model') ?></button>
      <?php endif; ?>
      <div style="width:300px;flex-shrink:0;">
        <input type="text" id="model-search" placeholder="<?= __('devices.search_models') ?>"
               oninput="filterTable('model-table', this.value)" style="width:100%;">
      </div>
    </div>

    <!-- Add form -->
    <?php if (can('devices','edit')): ?>
    <div id="model-add-form" style="display:none;margin-bottom:1.25rem;" class="card">
      <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('devices.new_model') ?></h2>
      <form method="POST" action="/devices/model/store">
      <?= csrf_field() ?>
        <div class="form-grid" style="">
          <div class="field"><label><?= __('catalog.model_name') ?> *</label><input type="text" name="name" required placeholder="<?= __('devices.model_name_ph') ?>"></div>
          <div class="field">
            <label><?= __('devices.brand') ?> *</label>
            <select name="brand_id" required>
              <option value=""><?= __('rma.select_brand') ?></option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= __('devices.group') ?> *</label>
            <select name="category_id" required>
              <option value=""><?= __('devices.select_group') ?></option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
          <button type="button" class="btn" onclick="toggleForm('model-add-form')"><?= __('btn.cancel') ?></button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if (empty($models)): ?>
      <p style="font-size:13px;color:var(--text-muted);"><?= __('catalog.no_models') ?></p>
    <?php else: ?>
      <table class="data-table" id="model-table">
        <thead>
          <tr>
            <th><?= __('devices.model') ?></th>
            <th><?= __('devices.brand') ?></th>
            <th><?= __('devices.group') ?></th>
            <?php if (can('devices','edit')): ?><th style="text-align:right;"><?= __('label.actions') ?></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($models as $m): ?>
            <tr data-name="<?= strtolower(htmlspecialchars($m['name'] . ' ' . $m['brand_name'])) ?>">
              <td style="font-weight:500;"><?= htmlspecialchars($m['name']) ?></td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($m['brand_name']) ?></td>
              <td style="color:var(--text-muted);"><?= htmlspecialchars($m['category_name']) ?></td>
              <?php if (can('devices','edit')): ?>
              <td style="text-align:right;">
                <button type="button" class="btn-link" onclick='editModel(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)'><?= __('btn.edit') ?></button>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php if (can('devices','edit')): ?>
<!-- ── Edit popups (Uredi): Save/Cancel left, Delete pushed right ── -->
<div id="group-edit-modal" class="dev-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('devices.edit_group') ?></h2>
    <form id="group-update-form" method="POST" action="/devices/category/update">
      <?= csrf_field() ?><input type="hidden" name="id" id="ge-id">
      <div class="form-grid" style="">
        <div class="field"><label><?= __('devices.group_name') ?> *</label><input type="text" name="name" id="ge-name" required></div>
        <div class="field"><label><?= __('devices.sku_prefix') ?></label><input type="text" name="sku_prefix" id="ge-sku" maxlength="6"></div>
        <div class="field"><label><?= __('devices.sort_order') ?></label><input type="number" name="sort_order" id="ge-sort" min="0"></div>
      </div>
    </form>
    <div class="modal-actions" style="display:flex;gap:8px;align-items:center;">
      <button type="submit" form="group-update-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
      <button type="button" class="btn" onclick="closeDevModal()"><?= __('btn.cancel') ?></button>
      <form method="POST" action="/devices/category/delete" style="margin-left:auto;" data-confirm="<?= htmlspecialchars(__('devices.confirm_delete_group'), ENT_QUOTES) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" id="gd-id">
        <button type="submit" class="btn btn-sm btn-danger"><?= __('btn.delete') ?></button>
      </form>
    </div>
  </div>
</div>

<div id="brand-edit-modal" class="dev-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:460px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('devices.edit_brand') ?></h2>
    <form id="brand-update-form" method="POST" action="/devices/brand/update">
      <?= csrf_field() ?><input type="hidden" name="id" id="be-id">
      <div class="field" style="margin-bottom:10px;"><label><?= __('catalog.brand_name') ?> *</label><input type="text" name="name" id="be-name" required></div>
    </form>
    <div class="modal-actions" style="display:flex;gap:8px;align-items:center;">
      <button type="submit" form="brand-update-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
      <button type="button" class="btn" onclick="closeDevModal()"><?= __('btn.cancel') ?></button>
      <form method="POST" action="/devices/brand/delete" style="margin-left:auto;" data-confirm="<?= htmlspecialchars(__('devices.confirm_delete_brand'), ENT_QUOTES) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" id="bd-id">
        <button type="submit" class="btn btn-sm btn-danger"><?= __('btn.delete') ?></button>
      </form>
    </div>
  </div>
</div>

<div id="model-edit-modal" class="dev-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('devices.edit_model') ?></h2>
    <form id="model-update-form" method="POST" action="/devices/model/update">
      <?= csrf_field() ?><input type="hidden" name="id" id="me-id">
      <div class="form-grid" style="">
        <div class="field"><label><?= __('catalog.model_name') ?> *</label><input type="text" name="name" id="me-name" required></div>
        <div class="field"><label><?= __('devices.brand') ?> *</label>
          <select name="brand_id" id="me-brand" required>
            <?php foreach ($brands as $b): ?><option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('devices.group') ?> *</label>
          <select name="category_id" id="me-cat" required>
            <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
    </form>
    <div class="modal-actions" style="display:flex;gap:8px;align-items:center;">
      <button type="submit" form="model-update-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
      <button type="button" class="btn" onclick="closeDevModal()"><?= __('btn.cancel') ?></button>
      <form method="POST" action="/devices/model/delete" style="margin-left:auto;" data-confirm="<?= htmlspecialchars(__('devices.confirm_delete_model'), ENT_QUOTES) ?>">
        <?= csrf_field() ?><input type="hidden" name="id" id="md-id">
        <button type="submit" class="btn btn-sm btn-danger"><?= __('btn.delete') ?></button>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

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
function editGroup(c) {
  document.getElementById('ge-id').value = c.id; document.getElementById('ge-name').value = c.name || '';
  document.getElementById('ge-sku').value = c.sku_prefix || ''; document.getElementById('ge-sort').value = c.sort_order || 0;
  document.getElementById('gd-id').value = c.id; document.getElementById('group-edit-modal').style.display = 'flex';
}
function editBrand(b) {
  document.getElementById('be-id').value = b.id; document.getElementById('be-name').value = b.name || '';
  document.getElementById('bd-id').value = b.id; document.getElementById('brand-edit-modal').style.display = 'flex';
}
function editModel(m) {
  document.getElementById('me-id').value = m.id; document.getElementById('me-name').value = m.name || '';
  document.getElementById('me-brand').value = m.brand_id || ''; document.getElementById('me-cat').value = m.category_id || '';
  document.getElementById('md-id').value = m.id; document.getElementById('model-edit-modal').style.display = 'flex';
}
document.querySelectorAll('.dev-modal').forEach(function (m) { m.addEventListener('click', function (e) { if (e.target === this) this.style.display = 'none'; }); });
</script>
