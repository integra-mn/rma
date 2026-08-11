<?php defined('RMS') or die('Direct access not permitted'); ?>
<?php
/**
 * The Parts tab bar, in one place.
 *
 * It used to be copy-pasted into stock.php, index.php, receipts_tab.php and
 * inventory.php — four edits to add a tab, and four chances to miss one.
 *
 * Suppliers and Part groups joined here when they moved out of the sidebar and
 * out of Administration → Devices: both only ever describe parts.
 *
 * Links are absolute rather than `?tab=…` because Suppliers keeps its own
 * /suppliers URL; a relative link would resolve to /suppliers?tab=stock and
 * quietly go nowhere.
 */
?>
<div class="tab-bar">
  <?php foreach ([
    'stock'       => ['/parts?tab=stock',       __('parts.tab_stock')],
    'parts'       => ['/parts?tab=parts',       __('parts.tab_catalog')],
    'receipts'    => ['/parts?tab=receipts',    __('parts.tab_receipts')],
    'inventory'   => ['/parts?tab=inventory',   __('parts.tab_inventory')],
    'suppliers'   => ['/suppliers',             __('parts.tab_suppliers')],
    'part-groups' => ['/parts?tab=part-groups', __('parts.tab_part_groups')],
  ] as $t => [$href, $label]): ?>
    <a href="<?= $href ?>"
       class="tab<?= ($tab ?? 'stock') === $t ? ' active' : '' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>
