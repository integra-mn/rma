<?php defined('RMS') or die('Direct access not permitted'); ?>

<?php if ($success ?? null): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error ?? null): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- ── Insurers ──────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
  <h2 style="font-size:15px;font-weight:500;margin:0;"><?= __('ins.insurers') ?></h2>
  <?php if (can('administration','create')): ?>
    <button type="button" class="btn btn-primary" style="min-width:170px;" onclick="insOpen()"><?= __('ins.insurer_add') ?></button>
  <?php endif; ?>
</div>

<?php if (empty($insurers)): ?>
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:2rem;"><?= __('ins.no_insurers') ?></p>
<?php else: ?>
  <table class="data-table" style="margin-bottom:2rem;">
    <thead>
      <tr>
        <th><?= __('label.name') ?></th>
        <th style="width:200px;"><?= __('ins.contact') ?></th>
        <th style="width:170px;"><?= __('label.phone') ?></th>
        <th style="width:150px;text-align:center;"><?= __('ins.report_hours') ?></th>
        <th style="text-align:right;width:100px;"><?= __('label.actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($insurers as $i): ?>
        <tr>
          <td style="font-weight:500;"><?= htmlspecialchars($i['name']) ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($i['contact_person'] ?? '') ?: '—' ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($i['phone'] ?? '') ?: '—' ?></td>
          <td style="text-align:center;color:var(--text-muted);">
            <?= (int)$i['report_hours'] > 0 ? (int)$i['report_hours'] : '—' ?>
          </td>
          <td style="text-align:right;">
            <?php if (can('administration','edit')): ?>
              <button type="button" class="btn-link" onclick="insEdit(<?= htmlspecialchars(json_encode($i)) ?>)"><?= __('btn.edit') ?></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<!-- ── Products ──────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
  <h2 style="font-size:15px;font-weight:500;margin:0;"><?= __('ins.products') ?></h2>
  <?php if (can('administration','create') && $insurers): ?>
    <button type="button" class="btn btn-primary" style="min-width:170px;" onclick="prodOpen()"><?= __('ins.product_add') ?></button>
  <?php endif; ?>
</div>

<?php if (empty($products)): ?>
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:2rem;"><?= __('ins.no_products') ?></p>
<?php else: ?>
  <table class="data-table" style="margin-bottom:2rem;">
    <thead>
      <tr>
        <th style="width:200px;"><?= __('ins.insurer') ?></th>
        <th><?= __('label.name') ?></th>
        <th><?= __('ins.covers') ?></th>
        <th style="width:120px;text-align:center;"><?= __('ins.participation') ?></th>
        <th style="width:110px;text-align:center;"><?= __('ins.claims_allowed') ?></th>
        <th style="text-align:right;width:100px;"><?= __('label.actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $pr): ?>
        <tr>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($pr['insurer_name'] ?? '') ?></td>
          <td style="font-weight:500;"><?= htmlspecialchars($pr['name']) ?></td>
          <td style="font-size:12px;color:var(--text-muted);">
            <?php
              $codes = insurance_coverage($pr['coverage'] ?? null);
              $names = [];
              foreach ($codes as $code) {
                  foreach ($coverage_items as $ci) {
                      if ($ci['code'] === $code) { $names[] = coverage_label($ci); break; }
                  }
              }
            ?>
            <?= $names ? htmlspecialchars(implode(', ', $names)) : '—' ?>
          </td>
          <td style="text-align:center;color:var(--text-muted);"><?= rtrim(rtrim(number_format((float)$pr['participation_pct'], 2, '.', ''), '0'), '.') ?>%</td>
          <td style="text-align:center;color:var(--text-muted);"><?= (int)$pr['claims_allowed'] ?></td>
          <td style="text-align:right;">
            <?php if (can('administration','edit')): ?>
              <button type="button" class="btn-link" onclick="prodEdit(<?= htmlspecialchars(json_encode($pr)) ?>)"><?= __('btn.edit') ?></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<!-- ── Coverage items ────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
  <h2 style="font-size:15px;font-weight:500;margin:0;"><?= __('ins.coverage') ?></h2>
  <?php if (can('administration','create')): ?>
    <button type="button" class="btn btn-primary" style="min-width:170px;" onclick="covOpen()"><?= __('ins.coverage_add') ?></button>
  <?php endif; ?>
</div>

<table class="data-table">
  <thead>
    <tr>
      <th><?= __('admin.status_label') ?></th>
      <th style="width:280px;"><?= __('admin.status_label_me') ?></th>
      <th style="width:180px;"><?= __('label.code') ?></th>
      <th style="width:100px;text-align:center;"><?= __('label.sort_order') ?></th>
      <th style="text-align:right;width:100px;"><?= __('label.actions') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($coverage_items as $c): ?>
      <tr>
        <td style="font-weight:500;"><?= htmlspecialchars($c['label']) ?></td>
        <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['label_me'] ?? '') ?: '—' ?></td>
        <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($c['code']) ?></td>
        <td style="text-align:center;color:var(--text-muted);"><?= (int)$c['sort_order'] ?></td>
        <td style="text-align:right;">
          <?php if (can('administration','edit')): ?>
            <button type="button" class="btn-link" onclick="covEdit(<?= htmlspecialchars(json_encode($c)) ?>)"><?= __('btn.edit') ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Insurer modal -->
<div id="ins-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:460px;margin:1rem;">
    <h2 id="ins-title" style="font-size:16px;font-weight:500;margin-bottom:1.25rem;"></h2>
    <form method="POST" id="ins-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="ins-id">
      <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" id="ins-name" required></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field"><label><?= __('ins.contact') ?></label><input type="text" name="contact_person" id="ins-contact"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone" id="ins-phone"></div>
      </div>
      <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" id="ins-email"></div>
      <div class="field">
        <label><?= __('ins.portal_url') ?></label>
        <input type="text" name="portal_url" id="ins-portal" placeholder="https://">
      </div>
      <div class="field">
        <label><?= __('ins.report_hours') ?></label>
        <input type="number" name="report_hours" id="ins-hours" min="0" max="8760" value="0">
        <p style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= __('ins.report_hours_hint') ?></p>
      </div>
      <div style="display:flex;gap:8px;margin-top:1rem;">
        <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save') ?></button>
        <button type="button" class="btn" style="min-width:100px;" onclick="document.getElementById('ins-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- Coverage item modal -->
<div id="cov-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:420px;margin:1rem;">
    <h2 id="cov-title" style="font-size:16px;font-weight:500;margin-bottom:1.25rem;"></h2>
    <form method="POST" id="cov-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="cov-id">
      <div class="field"><label><?= __('admin.status_label') ?> *</label><input type="text" name="label" id="cov-label" required></div>
      <div class="field"><label><?= __('admin.status_label_me') ?></label><input type="text" name="label_me" id="cov-label_me"></div>
      <div class="field">
        <label><?= __('label.code') ?> * <span style="font-size:11px;color:var(--text-muted);"><?= __('admin.status_code_hint') ?></span></label>
        <input type="text" name="code" id="cov-code" pattern="[a-z_]+" required>
      </div>
      <div class="field">
        <label><?= __('label.sort_order') ?></label>
        <input type="number" name="sort_order" id="cov-sort" min="0" max="999" value="10">
      </div>
      <div style="display:flex;gap:8px;margin-top:1rem;">
        <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save') ?></button>
        <button type="button" class="btn" style="min-width:100px;" onclick="document.getElementById('cov-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- Product modal -->
<div id="prod-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:460px;margin:1rem;">
    <h2 id="prod-title" style="font-size:16px;font-weight:500;margin-bottom:1.25rem;"></h2>
    <form method="POST" id="prod-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="prod-id">
      <div class="field">
        <label><?= __('ins.insurer') ?> *</label>
        <select name="insurer_id" id="prod-insurer" class="custom-select" required>
          <?php foreach ($insurers as $i): ?>
            <option value="<?= (int)$i['id'] ?>"><?= htmlspecialchars($i['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="name" id="prod-name" required></div>
      <div class="field">
        <label><?= __('ins.covers') ?></label>
        <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:4px;">
          <?php foreach ($coverage_items as $c): ?>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
              <input type="checkbox" name="coverage[]" value="<?= htmlspecialchars($c['code']) ?>"
                     class="prod-cov" data-code="<?= htmlspecialchars($c['code']) ?>" style="width:auto;height:auto;">
              <?= htmlspecialchars(coverage_label($c)) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field">
          <label><?= __('ins.participation') ?></label>
          <input type="number" name="participation_pct" id="prod-part" min="0" max="100" step="0.01" value="10">
        </div>
        <div class="field">
          <label><?= __('ins.claims_allowed') ?></label>
          <input type="number" name="claims_allowed" id="prod-allowed" min="0" max="99" value="1">
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:1rem;">
        <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save') ?></button>
        <button type="button" class="btn" style="min-width:100px;" onclick="document.getElementById('prod-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
const INS_TITLES = { add: <?= json_encode(__('ins.insurer_add')) ?>, edit: <?= json_encode(__('ins.insurer_edit')) ?> };
const COV_TITLES = { add: <?= json_encode(__('ins.coverage_add')) ?>, edit: <?= json_encode(__('ins.coverage_edit')) ?> };

function insOpen() {
  document.getElementById('ins-title').textContent = INS_TITLES.add;
  document.getElementById('ins-form').action = '/admin/insurer/store';
  ['ins-id','ins-name','ins-contact','ins-phone','ins-email','ins-portal'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('ins-hours').value = '0';
  document.getElementById('ins-modal').style.display = 'flex';
  document.getElementById('ins-name').focus();
}

function insEdit(i) {
  document.getElementById('ins-title').textContent = INS_TITLES.edit;
  document.getElementById('ins-form').action = '/admin/insurer/update';
  document.getElementById('ins-id').value      = i.id;
  document.getElementById('ins-name').value    = i.name || '';
  document.getElementById('ins-contact').value = i.contact_person || '';
  document.getElementById('ins-phone').value   = i.phone || '';
  document.getElementById('ins-email').value   = i.email || '';
  document.getElementById('ins-portal').value  = i.portal_url || '';
  document.getElementById('ins-hours').value   = i.report_hours || 0;
  document.getElementById('ins-modal').style.display = 'flex';
  document.getElementById('ins-name').focus();
}

const PROD_TITLES = { add: <?= json_encode(__('ins.product_add')) ?>, edit: <?= json_encode(__('ins.product_edit')) ?> };

function prodCoverage(csv) {
  const picked = (csv || '').split(',').map(c => c.trim()).filter(Boolean);
  document.querySelectorAll('.prod-cov').forEach(cb => { cb.checked = picked.includes(cb.dataset.code); });
}

function prodOpen() {
  document.getElementById('prod-title').textContent = PROD_TITLES.add;
  document.getElementById('prod-form').action = '/admin/product/store';
  document.getElementById('prod-id').value = '';
  document.getElementById('prod-name').value = '';
  document.getElementById('prod-part').value = '10';
  document.getElementById('prod-allowed').value = '1';
  prodCoverage('');
  document.getElementById('prod-modal').style.display = 'flex';
  document.getElementById('prod-name').focus();
}

function prodEdit(pr) {
  document.getElementById('prod-title').textContent = PROD_TITLES.edit;
  document.getElementById('prod-form').action = '/admin/product/update';
  document.getElementById('prod-id').value      = pr.id;
  document.getElementById('prod-insurer').value = pr.insurer_id;
  document.getElementById('prod-name').value    = pr.name || '';
  document.getElementById('prod-part').value    = pr.participation_pct;
  document.getElementById('prod-allowed').value = pr.claims_allowed;
  prodCoverage(pr.coverage);
  document.getElementById('prod-modal').style.display = 'flex';
  document.getElementById('prod-name').focus();
}

function covOpen() {
  document.getElementById('cov-title').textContent = COV_TITLES.add;
  document.getElementById('cov-form').action = '/admin/coverage/store';
  ['cov-id','cov-label','cov-label_me','cov-code'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('cov-sort').value = '10';
  document.getElementById('cov-modal').style.display = 'flex';
  document.getElementById('cov-label').focus();
}

function covEdit(c) {
  document.getElementById('cov-title').textContent = COV_TITLES.edit;
  document.getElementById('cov-form').action = '/admin/coverage/update';
  document.getElementById('cov-id').value       = c.id;
  document.getElementById('cov-label').value    = c.label || '';
  document.getElementById('cov-label_me').value = c.label_me || '';
  document.getElementById('cov-code').value     = c.code || '';
  document.getElementById('cov-sort').value     = c.sort_order || 0;
  document.getElementById('cov-modal').style.display = 'flex';
  document.getElementById('cov-label').focus();
}

// The code writes itself from the label on a new item, as it does for statuses.
document.getElementById('cov-label').addEventListener('input', function () {
  if (!document.getElementById('cov-id').value) {
    document.getElementById('cov-code').value =
      this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }
});

['ins-modal','cov-modal','prod-modal'].forEach(function (id) {
  document.getElementById(id).addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
  });
});
</script>
