<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php include views_path('parts/_tabs.php'); ?>

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$active_count): ?>
    <!-- No active count -->
    <div class="card" style="max-width:480px;">
      <h2 style="font-size:15px;font-weight:500;margin-bottom:8px;"><?= __('parts.no_active_count') ?></h2>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:1.25rem;line-height:1.6;">
        <?= __('parts.inventory_intro') ?>
      </p>

      <?php if (can('settings', 'edit')): ?>
      <form method="POST" action="/inventory/start" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <?= csrf_field() ?>
        <div style="flex:1;min-width:160px;">
          <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:3px;"><?= __('label.location') ?></label>
          <select name="location_id">
            <?php foreach ($locations as $loc): ?>
              <option value="<?= (int)$loc['id'] ?>" <?= $loc_id === (int)$loc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($loc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="min-width:140px;"
                data-confirm="<?= htmlspecialchars(__('parts.confirm_start_count'), ENT_QUOTES) ?>">
          <?= __('parts.start_count') ?>
        </button>
      </form>
      <?php endif; ?>
    </div>

    <!-- Past counts -->
    <?php if (!empty($past_counts)): ?>
    <div style="margin-top:1.5rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('parts.past_counts') ?></h2>
      <table class="data-table">
        <thead>
          <tr>
            <th><?= __('parts.count_ref') ?></th>
            <th><?= __('label.location') ?></th>
            <th><?= __('label.status') ?></th>
            <th style="text-align:center;"><?= __('parts.items') ?></th>
            <th style="text-align:center;"><?= __('parts.variances') ?></th>
            <th><?= __('parts.started') ?></th>
            <th><?= __('parts.confirmed') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($past_counts as $pc): ?>
            <tr>
              <td style="font-weight:500;font-family:monospace;color:var(--accent);"><?= htmlspecialchars($pc['reference'] ?? ('#'.$pc['id'])) ?></td>
              <td><?= htmlspecialchars($pc['location_name']) ?></td>
              <td>
                <span class="badge" style="background:<?= $pc['status']==='confirmed'?'var(--accent-bg)':'var(--bg-subtle)' ?>;color:<?= $pc['status']==='confirmed'?'var(--accent-text)':'var(--text-muted)' ?>;"><?= __('status.' . $pc['status']) ?></span>
              </td>
              <td style="text-align:center;color:var(--text-muted);"><?= (int)$pc['item_count'] ?></td>
              <td style="text-align:center;color:<?= (int)$pc['total_variance'] > 0 ? '#854f0b' : 'var(--text-muted)' ?>;">
                <?= (int)$pc['total_variance'] ?>
              </td>
              <td style="color:var(--text-muted);"><?= format_date($pc['started_at']) ?></td>
              <td style="color:var(--text-muted);"><?= format_date($pc['confirmed_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- Active count -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:10px;">
      <div>
        <span style="font-size:14px;font-weight:500;font-family:monospace;color:var(--accent);"><?= htmlspecialchars($active_count['reference'] ?? ('#'.$active_count['id'])) ?></span>
        <span style="font-size:13px;color:var(--text-muted);margin-left:8px;"><?= htmlspecialchars($active_count['location_name']) ?></span>
        <span style="font-size:12px;color:var(--text-muted);margin-left:8px;"><?= __('parts.started') ?> <?= format_datetime($active_count['started_at']) ?> <?= __('parts.by') ?> <?= htmlspecialchars($active_count['created_by_name'] ?? '—') ?></span>
      </div>
      <?php if (can('settings', 'edit') && $active_count['status'] === 'active'): ?>
      <div style="display:flex;gap:8px;">
        <form method="POST" action="/inventory/<?= (int)$active_count['id'] ?>/cancel"
              data-confirm="<?= __('parts.confirm_cancel_count') ?>">
      <?= csrf_field() ?>
          <button type="submit" class="btn btn-danger"><?= __('parts.cancel_count') ?></button>
        </form>
        <form method="POST" action="/inventory/<?= (int)$active_count['id'] ?>/confirm"
              data-confirm="<?= __('parts.confirm_count') ?>">
      <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><?= __('parts.confirm_apply') ?></button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <?php
      $uncounted   = array_filter($count_items, fn($i) => $i['counted_qty'] === null);
      $variances   = array_filter($count_items, fn($i) => $i['counted_qty'] !== null && (int)$i['variance'] !== 0);
      $matched     = array_filter($count_items, fn($i) => $i['counted_qty'] !== null && (int)$i['variance'] === 0);
    ?>

    <!-- Progress summary -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:1.25rem;">
      <div class="stat-card">
        <p class="stat-label"><?= __('parts.total_parts') ?></p>
        <p class="stat-value"><?= count($count_items) ?></p>
      </div>
      <div class="stat-card">
        <p class="stat-label"><?= __('parts.counted') ?></p>
        <p class="stat-value"><?= count($count_items) - count($uncounted) ?></p>
      </div>
      <div class="stat-card" style="background:<?= count($uncounted) > 0 ? '#faeeda' : 'var(--bg-subtle)' ?>;">
        <p class="stat-label" style="color:<?= count($uncounted) > 0 ? '#633806' : 'var(--text-muted)' ?>;"><?= __('parts.uncounted') ?></p>
        <p class="stat-value" style="color:<?= count($uncounted) > 0 ? '#854f0b' : 'var(--text-primary)' ?>;"><?= count($uncounted) ?></p>
      </div>
      <div class="stat-card" style="background:<?= count($variances) > 0 ? '#fcebeb' : 'var(--bg-subtle)' ?>;">
        <p class="stat-label" style="color:<?= count($variances) > 0 ? '#791f1f' : 'var(--text-muted)' ?>;"><?= __('parts.variances') ?></p>
        <p class="stat-value" style="color:<?= count($variances) > 0 ? '#a32d2d' : 'var(--text-primary)' ?>;"><?= count($variances) ?></p>
      </div>
    </div>

    <!-- Count sheet -->
    <form method="POST" action="/inventory/<?= (int)$active_count['id'] ?>/save">
      <?= csrf_field() ?>
      <div style="display:flex;gap:8px;margin-bottom:1rem;align-items:center;">
        <div style="width:300px;flex-shrink:0;">
          <input type="text" id="inv-search" placeholder="<?= __('parts.search_parts') ?>"
                 oninput="filterInv(this.value)" style="width:100%;">
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin-bottom:0;">
          <input type="checkbox" onchange="showVariancesOnly(this.checked)"> <?= __('parts.show_variances_only') ?>
        </label>
        <button type="submit" class="btn btn-primary" style="margin-left:auto;"><?= __('parts.save_counts') ?></button>
      </div>

      <table class="data-table" id="inv-table">
        <thead>
          <tr>
            <th><?= __('parts.part') ?></th>
            <th><?= __('parts.internal_sku') ?></th>
            <th><?= __('parts.supplier') ?></th>
            <th style="text-align:center;"><?= __('parts.system_qty') ?></th>
            <th style="text-align:center;width:120px;"><?= __('parts.counted_qty') ?></th>
            <th style="text-align:center;"><?= __('parts.variance') ?></th>
            <th><?= __('parts.note') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($count_items as $item):
            $counted  = $item['counted_qty'];
            $variance = $counted !== null ? (int)$item['variance'] : null;
            $row_bg   = $variance !== null && $variance !== 0 ? 'background:#fcebeb22;' : '';
          ?>
            <tr style="<?= $row_bg ?>"
                data-name="<?= strtolower(htmlspecialchars($item['part_name'])) ?>"
                data-variance="<?= $variance !== null && $variance !== 0 ? '1' : '0' ?>">
              <td style="font-weight:500;"><?= htmlspecialchars($item['part_name']) ?></td>
              <td style="font-size:12px;color:var(--accent);"><?= htmlspecialchars($item['internal_sku'] ?? '—') ?></td>
              <td style="color:var(--text-secondary);font-size:12px;"><?= htmlspecialchars($item['supplier_name'] ?? '—') ?></td>
              <td style="text-align:center;color:var(--text-muted);"><?= (int)$item['system_qty'] ?></td>
              <td style="text-align:center;">
                <?php if ($active_count['status'] === 'active'): ?>
                  <input type="number" name="counted[<?= (int)$item['id'] ?>]"
                         value="<?= $counted !== null ? (int)$counted : '' ?>"
                         min="0" placeholder="—"
                         style="width:80px;text-align:center;"
                         onchange="updateVariance(this, <?= (int)$item['system_qty'] ?>)">
                <?php else: ?>
                  <?= $counted !== null ? (int)$counted : '—' ?>
                <?php endif; ?>
              </td>
              <td style="text-align:center;" id="var-<?= (int)$item['id'] ?>">
                <?php if ($variance !== null): ?>
                  <span style="font-weight:500;color:<?= $variance > 0 ? 'var(--accent)' : ($variance < 0 ? '#a32d2d' : 'var(--text-muted)') ?>;">
                    <?= $variance > 0 ? '+' : '' ?><?= $variance ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($active_count['status'] === 'active'): ?>
                  <input type="text" name="notes[<?= (int)$item['id'] ?>]"
                         value="<?= htmlspecialchars($item['note'] ?? '') ?>"
                         placeholder="<?= __('parts.optional_note') ?>" style="font-size:12px;">
                <?php else: ?>
                  <span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($item['note'] ?? '—') ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($active_count['status'] === 'active'): ?>
      <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary"><?= __('parts.save_counts') ?></button>
      </div>
      <?php endif; ?>
    </form>
  <?php endif; ?>

</div>

<script>
function filterInv(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#inv-table tbody tr').forEach(row => {
    row.style.display = (!q || row.dataset.name.includes(q)) ? '' : 'none';
  });
}

function showVariancesOnly(on) {
  document.querySelectorAll('#inv-table tbody tr').forEach(row => {
    row.style.display = (!on || row.dataset.variance === '1') ? '' : 'none';
  });
}

function updateVariance(input, systemQty) {
  const val     = input.value.trim();
  const cell    = input.closest('tr').querySelector('[id^="var-"]');
  const row     = input.closest('tr');
  if (!cell) return;
  if (val === '' || isNaN(val)) {
    cell.innerHTML = '<span style="color:var(--text-muted);">—</span>';
    row.style.background = '';
    row.dataset.variance = '0';
    return;
  }
  const variance = parseInt(val) - systemQty;
  const color    = variance > 0 ? 'var(--accent)' : (variance < 0 ? '#a32d2d' : 'var(--text-muted)');
  const prefix   = variance > 0 ? '+' : '';
  cell.innerHTML = `<span style="font-weight:500;color:${color};">${prefix}${variance}</span>`;
  row.style.background = variance !== 0 ? '#fcebeb22' : '';
  row.dataset.variance  = variance !== 0 ? '1' : '0';
}
</script>
