<?php
/**
 * The Kodovi table on its own, so live search can swap it without redrawing
 * the page. Rendered inline on first load and returned alone for ?ajax=1.
 */
defined('RMS') or die('Direct access not permitted');
?>
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
<?php
  // Shared pager. sub, and any filter in play, travel with the page number so
  // paging inside a filtered list stays inside it.
  $pg_page     = $code_page ?? 1;
  $pg_total    = $code_total ?? count($rows);
  $pg_per_page = $code_per_page ?? 100;
  $pg_query    = ['tab' => 'codes', 'sub' => $sub, 'q' => $_GET['q'] ?? '',
                  'brand' => $_GET['brand'] ?? '', 'category' => $_GET['category'] ?? ''];
  include views_path('_partials/pager.php');
?>
<?php endif; ?>
