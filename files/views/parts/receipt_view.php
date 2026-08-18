<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:1100px;">

  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <a href="/parts/receipts" class="btn btn-sm">&larr; <?= __('parts.goods_receipts') ?></a>
    <h1 style="font-size:22px;font-weight:500;">
      <?= __('parts.receipt') ?> #<?= (int)$receipt['id'] ?>
      <?php if ($receipt['reference']): ?>
        <span style="font-size:14px;font-weight:400;color:var(--text-muted);margin-left:8px;"><?= htmlspecialchars($receipt['reference']) ?></span>
      <?php endif; ?>
    </h1>
    <span class="badge" style="background:<?= $receipt['status']==='confirmed'?'var(--accent-bg)':'#faeeda' ?>;color:<?= $receipt['status']==='confirmed'?'var(--accent-text)':'#633806' ?>;"><?= ucfirst($receipt['status']) ?></span>
  </div>

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Header info -->
  <div class="card" style="margin-bottom:1rem;">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;font-size:13px;">
      <div><span style="color:var(--text-muted);"><?= __('parts.supplier') ?></span><br><strong><?= htmlspecialchars($receipt['supplier_name']) ?></strong></div>
      <div><span style="color:var(--text-muted);"><?= __('label.location') ?></span><br><strong><?= htmlspecialchars($receipt['location_name']) ?></strong></div>
      <div><span style="color:var(--text-muted);"><?= __('parts.freight_cost') ?></span><br><strong>€<?= number_format((float)$receipt['freight_cost'], 2) ?></strong></div>
      <div><span style="color:var(--text-muted);"><?= __('parts.default_margin') ?></span><br><strong><?= number_format((float)$receipt['default_margin_pct'], 2) ?>%</strong></div>
      <div><span style="color:var(--text-muted);"><?= __('label.created') ?></span><br><?= format_date($receipt['created_at']) ?></div>
    </div>
  </div>

  <?php if ($receipt['status'] === 'draft'): ?>

  <!-- Batch upload section -->
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:0.5rem;"><?= __('parts.batch_upload') ?></h2>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:0.75rem;line-height:1.55;">
      <?= __('parts.import_intro') ?>
      <strong><?= __('parts.step1') ?></strong> — <?= __('parts.import_step1_text') ?>
      <strong><?= __('parts.step2') ?></strong> — <?= __('parts.import_step2_text') ?>
    </p>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:stretch;">

      <a href="/parts/receipts/<?= (int)$receipt['id'] ?>/template"
         class="btn" style="display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        <?= __('parts.download_template') ?> (.xlsx)
      </a>

      <form method="POST" action="/parts/receipts/<?= (int)$receipt['id'] ?>/import"
            enctype="multipart/form-data"
            style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="file" name="import_file" accept=".xlsx" required
               class="file-field" style="width:300px;">
        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="17 8 12 3 7 8"/>
            <line x1="12" y1="3" x2="12" y2="15"/>
          </svg>
          <?= __('parts.upload_items') ?>
        </button>
      </form>

    </div>

    <p style="font-size:11.5px;color:var(--text-muted);margin-top:0.6rem;line-height:1.5;">
      <?= __('parts.columns_label') ?> <code>name</code>, <code>supplier_sku</code>, <code>quantity</code>, <code>supplier_price</code>,
      <code>customs_pct</code>, <code>margin_pct</code>. <?= __('parts.columns_leave') ?> <code>margin_pct</code> <?= __('parts.columns_blank_default') ?>
      (<?= number_format((float)$receipt['default_margin_pct'], 2) ?>%). <?= __('parts.columns_replace') ?>
    </p>
  </div>

  <?php endif; ?>

  <!-- Items table -->
  <?php if (!empty($items)): ?>
  <div class="card" style="margin-bottom:1rem;overflow-x:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);">
        <?= __('parts.items') ?>
        <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:6px;"><?= count($items) ?> <?= __('parts.lines_word') ?> · <?= array_sum(array_column($items, 'quantity')) ?> <?= __('parts.units_word') ?></span>
      </h2>
      <?php if ($receipt['status'] === 'confirmed'): ?>
        <span style="font-size:12px;color:var(--accent);"><?= __('parts.confirmed_stock_updated') ?></span>
      <?php endif; ?>
    </div>

    <?php if ($receipt['status'] === 'draft'): ?>
    <form method="POST" action="/parts/receipts/<?= (int)$receipt['id'] ?>/items">
      <?= csrf_field() ?>
      <input type="hidden" name="freight_cost" value="<?= htmlspecialchars($receipt['freight_cost']) ?>">
      <input type="hidden" name="default_margin_pct" value="<?= htmlspecialchars($receipt['default_margin_pct']) ?>">
    <?php endif; ?>

    <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:900px;">
      <thead>
        <tr style="border-bottom:0.5px solid var(--border);">
          <th style="text-align:left;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.part') ?></th>
          <th style="text-align:left;padding:6px 8px;color:var(--text-secondary);font-weight:500;">SKU</th>
          <th style="text-align:center;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.qty') ?></th>
          <th style="text-align:right;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.supplier_eur') ?></th>
          <th style="text-align:right;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.customs_pct') ?></th>
          <th style="text-align:right;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.cost_price') ?></th>
          <th style="text-align:right;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.margin_pct') ?></th>
          <th style="text-align:right;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.sell_price') ?></th>
          <th style="text-align:right;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.override') ?></th>
          <?php if ($receipt['status'] === 'draft'): ?>
          <th style="text-align:left;padding:6px 8px;color:var(--text-secondary);font-weight:500;"><?= __('parts.match_part') ?></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item):
          $sell = $item['unit_price_override'] ?? $item['unit_price'];
          $unmatched = !$item['part_id'];
        ?>
          <tr style="border-bottom:0.5px solid var(--border-subtle);background:<?= $unmatched ? '#faeeda22' : 'transparent' ?>;">
            <td style="padding:6px 8px;font-weight:<?= $unmatched?'400':'500' ?>;color:<?= $unmatched?'#854f0b':'var(--text-primary)' ?>;">
              <?= htmlspecialchars($item['part_name_matched'] ?? $item['part_name_raw']) ?>
              <?php if ($unmatched): ?>
                <span style="font-size:10px;color:#854f0b;"> <?= __('parts.unmatched_tag') ?></span>
              <?php endif; ?>
            </td>
            <td style="padding:6px 8px;color:var(--text-muted);"><?= htmlspecialchars($item['part_sku_matched'] ?? $item['sku_raw'] ?? '—') ?></td>

            <?php if ($receipt['status'] === 'draft'): ?>
              <td style="padding:4px 6px;"><input type="number" name="items[<?= (int)$item['id'] ?>][quantity]" value="<?= (int)$item['quantity'] ?>" min="1" style="width:60px;padding:4px 6px;font-size:12px;" onchange="recalc(this)"></td>
              <td style="padding:4px 6px;"><input type="text" name="items[<?= (int)$item['id'] ?>][supplier_price]" value="<?= number_format((float)$item['supplier_price'],4,'.','') ?>" style="width:80px;padding:4px 6px;font-size:12px;text-align:right;" onchange="recalc(this)"></td>
              <td style="padding:4px 6px;"><input type="text" name="items[<?= (int)$item['id'] ?>][customs_duty_pct]" value="<?= number_format((float)$item['customs_duty_pct'],2,'.','') ?>" style="width:60px;padding:4px 6px;font-size:12px;text-align:right;" onchange="recalc(this)"></td>
              <td style="padding:6px 8px;text-align:right;color:var(--text-secondary);">€<?= number_format((float)$item['cost_price'],4) ?></td>
              <td style="padding:4px 6px;"><input type="text" name="items[<?= (int)$item['id'] ?>][margin_pct]" value="<?= number_format((float)$item['margin_pct'],2,'.','') ?>" style="width:60px;padding:4px 6px;font-size:12px;text-align:right;" onchange="recalc(this)"></td>
              <td style="padding:6px 8px;text-align:right;font-weight:500;">€<?= number_format((float)$item['unit_price'],2) ?></td>
              <td style="padding:4px 6px;"><input type="text" name="items[<?= (int)$item['id'] ?>][unit_price_override]" value="<?= $item['unit_price_override'] !== null ? number_format((float)$item['unit_price_override'],2,'.','') : '' ?>" placeholder="<?= __('parts.auto') ?>" style="width:70px;padding:4px 6px;font-size:12px;text-align:right;"></td>
              <td style="padding:4px 6px;">
                <select name="items[<?= (int)$item['id'] ?>][part_id]" style="width:140px;padding:4px 6px;font-size:12px;">
                  <option value=""><?= __('parts.unmatched_option') ?></option>
                  <?php foreach ($parts as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$item['part_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($p['name']) ?><?= $p['internal_sku'] ? ' ('.htmlspecialchars($p['internal_sku']).')' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
            <?php else: ?>
              <td style="padding:6px 8px;text-align:center;"><?= (int)$item['quantity'] ?></td>
              <td style="padding:6px 8px;text-align:right;">€<?= number_format((float)$item['supplier_price'],4) ?></td>
              <td style="padding:6px 8px;text-align:right;"><?= number_format((float)$item['customs_duty_pct'],2) ?>%</td>
              <td style="padding:6px 8px;text-align:right;color:var(--text-secondary);">€<?= number_format((float)$item['cost_price'],4) ?></td>
              <td style="padding:6px 8px;text-align:right;"><?= number_format((float)$item['margin_pct'],2) ?>%</td>
              <td style="padding:6px 8px;text-align:right;font-weight:500;">€<?= number_format((float)$item['unit_price'],2) ?></td>
              <td style="padding:6px 8px;text-align:right;color:var(--text-muted);"><?= $item['unit_price_override'] ? '€'.number_format((float)$item['unit_price_override'],2) : '—' ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($receipt['status'] === 'draft'): ?>
      <div style="margin-top:1rem;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary"><?= __('parts.save_prices') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('confirm-form').submit()">
          <?= __('parts.confirm_receipt_stock') ?>
        </button>
      </div>
    </form>

    <form method="POST" action="/parts/receipts/<?= (int)$receipt['id'] ?>/confirm" id="confirm-form"
          data-confirm="<?= __('parts.confirm_receipt') ?>">
      <?= csrf_field() ?>
    </form>
    <?php endif; ?>

  </div>

  <?php elseif ($receipt['status'] === 'draft'): ?>
    <div class="card">
      <p style="font-size:13px;color:var(--text-muted);"><?= __('parts.no_items_yet') ?></p>
    </div>
  <?php endif; ?>

</div>
