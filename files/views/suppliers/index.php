<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Add + Search -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <?php if (can('suppliers', 'edit')): ?>
      <button type="button" class="btn btn-primary" style="min-width:140px;"
              onclick="document.getElementById('add-form').style.display=document.getElementById('add-form').style.display==='none'?'block':'none'">
        <?= __('suppliers.add') ?>
      </button>
    <?php endif; ?>
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="supplier-search" placeholder="<?= __('suppliers.search') ?>"
             oninput="filterSuppliers(this.value)" style="width:100%;">
    </div>
  </div>

  <!-- Add form -->
  <?php if (can('suppliers', 'edit')): ?>
  <div id="add-form" style="display:none;margin-bottom:1.25rem;" class="card">
    <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('suppliers.new') ?></h2>
    <form method="POST" action="/suppliers/store">
      <?= csrf_field() ?>
      <div class="form-grid" style="">
        <div class="field"><label><?= __('suppliers.company') ?> *</label><input type="text" name="name" required></div>
        <div class="field"><label><?= __('suppliers.contact_person') ?></label><input type="text" name="contact"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email"></div>
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city"></div>
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country"></div>
      </div>
      <div class="field"><label><?= __('label.notes') ?></label><textarea name="notes" rows="2"></textarea></div>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('add-form').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($suppliers)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('suppliers.no_results') ?></p>
  <?php else: ?>
    <table class="data-table" id="supplier-table" style="table-layout:fixed;width:100%;">
      <thead>
        <tr>
          <th style="width:10%;"><?= __('suppliers.company') ?></th>
          <th style="width:16%;"><?= __('suppliers.contact') ?></th>
          <th style="width:16%;"><?= __('label.phone') ?></th>
          <th style="width:22%;"><?= __('label.email') ?></th>
          <th style="width:16%;"><?= __('label.city') ?></th>
          <th style="width:10%;"><?= __('label.country') ?></th>
          <?php if (can('suppliers', 'edit')): ?><th style="width:10%;text-align:right;"><?= __('label.actions') ?></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($suppliers as $s): ?>
          <tr data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>"
              data-email="<?= strtolower(htmlspecialchars($s['email'] ?? '')) ?>"
              data-contact="<?= strtolower(htmlspecialchars($s['contact'] ?? '')) ?>">
            <td style="font-weight:500;"><?= htmlspecialchars($s['name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['contact'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['email'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['city'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($s['country'] ?? '—') ?></td>
            <?php if (can('suppliers', 'edit')): ?>
            <td style="text-align:right;">
              <button onclick="editSupplier(<?= htmlspecialchars(json_encode($s)) ?>)" class="btn-link"><?= __('btn.edit') ?></button>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Edit modal -->
<?php if (can('suppliers', 'edit')): ?>
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;max-height:90vh;overflow-y:auto;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('suppliers.edit') ?></h2>
    <form method="POST" action="/suppliers/update">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="e-id">
      <div class="form-grid" style="">
        <div class="field"><label><?= __('suppliers.company') ?> *</label><input type="text" name="name" id="e-name" required></div>
        <div class="field"><label><?= __('suppliers.contact_person') ?></label><input type="text" name="contact" id="e-contact"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone" id="e-phone"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" id="e-email"></div>
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address" id="e-address"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city" id="e-city"></div>
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country" id="e-country"></div>
      </div>
      <div class="field"><label><?= __('label.notes') ?></label><textarea name="notes" id="e-notes" rows="2"></textarea></div>
      <div style="display:flex;gap:8px;margin-top:4px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('edit-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function editSupplier(s) {
  document.getElementById('e-id').value      = s.id;
  document.getElementById('e-name').value    = s.name    || '';
  document.getElementById('e-contact').value = s.contact || '';
  document.getElementById('e-phone').value   = s.phone   || '';
  document.getElementById('e-email').value   = s.email   || '';
  document.getElementById('e-address').value = s.address || '';
  document.getElementById('e-city').value    = s.city    || '';
  document.getElementById('e-country').value = s.country || '';
  document.getElementById('e-notes').value   = s.notes   || '';
  document.getElementById('edit-modal').style.display = 'flex';
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
<?php endif; ?>

<style>
  /* Ellipsis-on-overflow so long emails/names don't wrap rows into 2 lines. */
  #supplier-table td, #supplier-table th {
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
</style>

<script>
function filterSuppliers(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#supplier-table tbody tr').forEach(row => {
    const match = !q || row.dataset.name.includes(q) || row.dataset.email.includes(q) || (row.dataset.contact || '').includes(q);
    row.style.display = match ? '' : 'none';
  });
}
</script>
