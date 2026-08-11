<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php include views_path('parts/_tabs.php'); ?>

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Search row with Add button -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <?php if (can('parts', 'create')): ?>
      <button type="button" class="btn btn-primary" style="min-width:140px;"
              onclick="document.getElementById('add-receipt').style.display=document.getElementById('add-receipt').style.display==='none'?'block':'none'">
        <?= __('parts.new_receipt_btn') ?>
      </button>
    <?php endif; ?>
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="receipt-search" placeholder="<?= __('parts.search_supplier_ref') ?>"
             oninput="filterReceipts(this.value)" style="width:100%;">
    </div>
  </div>

  <!-- New receipt form (hidden) -->
  <?php if (can('parts', 'create')): ?>
  <div id="add-receipt" style="display:none;margin-bottom:1.25rem;" class="card">
    <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('parts.new_goods_receipt') ?></h2>
    <form method="POST" action="/parts/receipts/store">
      <?= csrf_field() ?>
      <?php $user = current_user(); ?>
      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('parts.supplier') ?> *</label>
          <select name="supplier_id" required>
            <option value=""><?= __('parts.select_supplier') ?></option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('parts.destination_location') ?> *</label>
          <select name="location_id" required>
            <option value=""><?= __('parts.select_location') ?></option>
            <?php foreach ($locations as $l): ?>
              <option value="<?= (int)$l['id'] ?>" <?= (int)($user['location_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($l['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('parts.supplier_invoice_ref') ?></label>
          <input type="text" name="reference" placeholder="INV-2024-001">
        </div>
        <div class="field">
          <label><?= __('parts.freight_cost') ?> (€)</label>
          <input type="text" name="freight_cost" value="0.00">
        </div>
        <div class="field">
          <label><?= __('parts.default_margin') ?> %</label>
          <input type="text" name="default_margin_pct" value="0.00">
        </div>
      </div>
      <div class="field" style="margin-bottom:12px;">
        <label><?= __('label.notes') ?></label>
        <textarea name="notes" rows="2"></textarea>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= __('parts.create_draft') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('add-receipt').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($receipts)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('parts.no_receipts') ?></p>
  <?php else: ?>
    <table class="data-table" id="receipts-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?= __('parts.supplier') ?></th>
          <th><?= __('label.location') ?></th>
          <th><?= __('parts.reference') ?></th>
          <th style="text-align:center;"><?= __('parts.lines_th') ?></th>
          <th style="text-align:center;"><?= __('parts.units_th') ?></th>
          <th style="text-align:right;"><?= __('parts.freight') ?></th>
          <th><?= __('label.status') ?></th>
          <th style="text-align:right;"><?= __('label.created') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($receipts as $r): ?>
          <tr onclick="window.location='/parts/receipts/<?= (int)$r['id'] ?>'"
              data-supplier="<?= strtolower(htmlspecialchars($r['supplier_name'])) ?>"
              data-ref="<?= strtolower(htmlspecialchars($r['reference'] ?? '')) ?>">
            <td style="font-weight:500;">#<?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['supplier_name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['location_name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['reference'] ?? '—') ?></td>
            <td style="text-align:center;color:var(--text-muted);"><?= (int)$r['item_count'] ?></td>
            <td style="text-align:center;color:var(--text-muted);"><?= (int)$r['total_units'] ?></td>
            <td style="text-align:right;">€<?= number_format((float)$r['freight_cost'], 2) ?></td>
            <td>
              <span class="badge" style="background:<?= $r['status']==='confirmed'?'var(--accent-bg)':'#faeeda' ?>;color:<?= $r['status']==='confirmed'?'var(--accent-text)':'#633806' ?>;">
                <?= ucfirst($r['status']) ?>
              </span>
            </td>
            <td style="color:var(--text-muted);text-align:right;"><?= format_date($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
function filterReceipts(q) {
  q = q.toLowerCase().trim();
  let visible = 0;
  document.querySelectorAll('#receipts-table tbody tr').forEach(row => {
    const match = !q || row.dataset.supplier.includes(q) || row.dataset.ref.includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  if (counter) counter.textContent = visible + ' <?= __('label.total') ?>';
}
</script>
