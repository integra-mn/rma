<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php include views_path('parts/_tabs.php'); ?>

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Search row -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:stretch;flex-wrap:wrap;">
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="stock-search" placeholder="<?= __('parts.search_name_sku') ?>"
             oninput="filterStock(this.value)" style="width:100%;">
    </div>
    <?php if (allowed_location_ids() === null && count($locations) > 1): ?>
      <?php foreach ($locations as $loc): ?>
        <a href="?tab=stock&loc=<?= (int)$loc['id'] ?>"
           class="btn <?= $loc_id === (int)$loc['id'] ? 'btn-primary' : '' ?>">
          <?= htmlspecialchars($loc['name']) ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (empty($parts_stock)): ?>
    <p style="font-size:13px;color:var(--text-muted);"><?= __('parts.no_parts_catalog') ?></p>
  <?php else: ?>
    <?php
      $out_of_stock = array_filter($parts_stock, fn($p) => (int)($p['quantity'] ?? 0) === 0);
      $low_stock    = array_filter($parts_stock, fn($p) => (int)($p['quantity'] ?? 0) > 0 && (int)($p['quantity'] ?? 0) <= (int)$p['min_stock']);
    ?>
    <?php if (!empty($out_of_stock)): ?>
      <div style="background:#fcebeb;border:0.5px solid #f09595;border-radius:8px;padding:10px 14px;font-size:13px;color:#791f1f;margin-bottom:1rem;">
        <?= count($out_of_stock) ?> <?= __('parts.parts_out_of_stock') ?>
      </div>
    <?php elseif (!empty($low_stock)): ?>
      <div style="background:#faeeda;border:0.5px solid #ef9f27;border-radius:8px;padding:10px 14px;font-size:13px;color:#633806;margin-bottom:1rem;">
        <?= count($low_stock) ?> <?= __('parts.parts_below_reorder') ?>
      </div>
    <?php endif; ?>

    <table class="data-table" id="stock-table">
      <thead>
        <tr>
          <th>SKU</th>
          <th><?= __('parts.part') ?></th>
          <th><?= __('parts.supplier') ?></th>
          <th style="text-align:center;"><?= __('parts.unit_price') ?></th>
          <th style="text-align:center;"><?= __('parts.in_stock') ?></th>
          <th style="text-align:center;"><?= __('parts.reorder_at') ?></th>
          <?php if (can('settings', 'edit')): ?><th style="width:160px;"></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($parts_stock as $p):
          $qty     = (int)($p['quantity'] ?? 0);
          $reorder = (int)$p['min_stock'];
          $color   = $qty === 0 ? '#a32d2d' : ($qty <= $reorder ? '#854f0b' : 'var(--text-primary)');
        ?>
          <tr data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" data-sku="<?= strtolower(htmlspecialchars($p['internal_sku'] ?? '')) ?>">
            <td style="font-size:12px;color:var(--accent);"><?= htmlspecialchars($p['internal_sku'] ?? '—') ?></td>
            <td style="font-weight:500;"><?= htmlspecialchars($p['name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($p['supplier_name'] ?? '—') ?></td>
            <td style="text-align:center;">€<?= number_format((float)$p['unit_price'], 2) ?></td>
            <td style="text-align:center;font-weight:500;color:<?= $color ?>;"><?= $qty ?></td>
            <td style="text-align:center;color:var(--text-muted);"><?= $reorder ?></td>
            <?php if (can('settings', 'edit')): ?>
            <td>
              <form method="POST" action="/parts/stock/update" style="display:flex;gap:6px;align-items:center;">
      <?= csrf_field() ?>
                <input type="hidden" name="part_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="location_id" value="<?= (int)$loc_id ?>">
                <input type="number" name="quantity" value="<?= $qty ?>" min="0"
                       style="width:70px;padding:5px 8px;font-size:12px;">
                <button type="submit" class="btn btn-sm" title="<?= __('parts.admin_adjustment') ?>"><?= __('parts.adjust') ?></button>
              </form>
            </td>
            <?php else: ?>
            <td style="font-size:12px;color:var(--text-muted);"><?= __('parts.via_goods_receipt') ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
function filterStock(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#stock-table tbody tr').forEach(row => {
    const name = row.dataset.name || '';
    const sku  = row.dataset.sku  || '';
    row.style.display = (!q || name.includes(q) || sku.includes(q)) ? '' : 'none';
  });
}
</script>
