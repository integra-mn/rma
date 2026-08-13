<?php
/**
 * Shared list pager.
 *
 * Expects, set by the caller before including:
 *   $pg_page      current page (1-based)
 *   $pg_total     total rows matching the filter
 *   $pg_per_page  rows per page
 *   $pg_query     extra query-string parts to carry, e.g. ['q' => $search]
 *
 * Every list used to print page numbers 1..N in a loop. That is fine at three
 * pages and unusable at four hundred — 500 RMAs already put twenty buttons on
 * screen with no way to jump to the end. This stays the same width whatever
 * the total: first and last, a window around where you are, and an ellipsis
 * for the gap.
 *
 * data-page is what live-list.js listens for to change page without reloading
 * the whole screen. Every link carries it, so Previous and Next behave the
 * same way as the numbers.
 */
defined('RMS') or die('Direct access not permitted');

$pg_per_page = max(1, (int) ($pg_per_page ?? 25));
$pg_total    = (int) ($pg_total ?? 0);
$pg_page     = max(1, (int) ($pg_page ?? 1));
$pg_pages    = (int) ceil($pg_total / $pg_per_page);

// Nothing to page through. Returning from an include is fine and leaves the
// caller untouched.
if ($pg_pages <= 1) return;

$pg_query = array_filter($pg_query ?? [], fn($v) => $v !== '' && $v !== null);
$pg_href  = fn(int $p): string => '?' . http_build_query($pg_query + ['page' => $p]);

// Two either side of the current page. Enough to see where you are without
// the control changing width as you move through.
$pg_win  = 2;
$pg_from = max(1, $pg_page - $pg_win);
$pg_to   = min($pg_pages, $pg_page + $pg_win);

$pg_box  = 'padding:5px 10px;border:0.5px solid var(--border);border-radius:6px;'
         . 'text-decoration:none;color:var(--text-primary);';
$pg_now  = 'padding:5px 10px;border:0.5px solid var(--accent);border-radius:6px;'
         . 'color:var(--accent);';
$pg_off  = 'padding:5px 10px;border:0.5px solid var(--border);border-radius:6px;'
         . 'color:var(--text-muted);opacity:0.5;';
$pg_gap  = 'padding:5px 4px;color:var(--text-muted);';

$pg_first = ($pg_page - 1) * $pg_per_page + 1;
$pg_last  = min($pg_total, $pg_page * $pg_per_page);
?>
<div style="margin-top:1rem;display:flex;gap:6px;align-items:center;font-size:13px;flex-wrap:wrap;">

  <?php if ($pg_page > 1): ?>
    <a href="<?= $pg_href($pg_page - 1) ?>" data-page="<?= $pg_page - 1 ?>"
       style="<?= $pg_box ?>" rel="prev"><?= __('pager.prev') ?></a>
  <?php else: ?>
    <span style="<?= $pg_off ?>"><?= __('pager.prev') ?></span>
  <?php endif; ?>

  <?php if ($pg_from > 1): ?>
    <a href="<?= $pg_href(1) ?>" data-page="1" style="<?= $pg_box ?>">1</a>
    <?php if ($pg_from > 2): ?><span style="<?= $pg_gap ?>">&hellip;</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $pg_from; $i <= $pg_to; $i++): ?>
    <?php if ($i === $pg_page): ?>
      <span style="<?= $pg_now ?>" aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= $pg_href($i) ?>" data-page="<?= $i ?>" style="<?= $pg_box ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($pg_to < $pg_pages): ?>
    <?php if ($pg_to < $pg_pages - 1): ?><span style="<?= $pg_gap ?>">&hellip;</span><?php endif; ?>
    <a href="<?= $pg_href($pg_pages) ?>" data-page="<?= $pg_pages ?>" style="<?= $pg_box ?>"><?= $pg_pages ?></a>
  <?php endif; ?>

  <?php if ($pg_page < $pg_pages): ?>
    <a href="<?= $pg_href($pg_page + 1) ?>" data-page="<?= $pg_page + 1 ?>"
       style="<?= $pg_box ?>" rel="next"><?= __('pager.next') ?></a>
  <?php else: ?>
    <span style="<?= $pg_off ?>"><?= __('pager.next') ?></span>
  <?php endif; ?>

  <!-- Where you are in the whole set. The page numbers alone do not say how
       many rows there are, which is the first thing you want on a long list. -->
  <span style="margin-left:auto;color:var(--text-muted);">
    <?= __('pager.showing', ['from' => $pg_first, 'to' => $pg_last, 'total' => $pg_total]) ?>
  </span>
</div>
