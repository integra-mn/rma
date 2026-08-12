<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <!-- Add + Search -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <a href="/portal/rma/new" class="btn btn-primary"><?= __('rma.new') ?></a>
    <form method="GET" action="/portal/rma" style="display:flex;gap:8px;margin:0;">
      <div style="width:240px;flex-shrink:0;">
        <input type="text" name="q" placeholder="<?= __('portal.search_placeholder') ?>"
               value="<?= htmlspecialchars($search ?? '') ?>" style="width:100%;">
      </div>
    </form>
  </div>

  <?php if (empty($rmas)): ?>
    <p style="font-size:13px;color:var(--text-muted);">
      <?= ($search ?? '') !== '' ? __('portal.no_match') : __('portal.no_rmas_click') ?>
    </p>
  <?php else: ?>
    <table class="data-table" id="rma-table" style="table-layout:fixed;width:100%;">
      <thead>
        <tr>
          <th style="width:16%;">RMA</th>
          <th style="width:22%;"><?= __('rma.customer') ?></th>
          <th style="width:22%;"><?= __('rma.device') ?></th>
          <th style="width:18%;"><?= __('label.status') ?></th>
          <th style="width:12%;"><?= __('track.submitted') ?></th>
          <th style="width:10%;text-align:right;"><?= __('label.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rmas as $r): ?>
          <tr onclick="window.location='/portal/rma/<?= (int)$r['id'] ?>'" style="cursor:pointer;">
            <td style="font-weight:500;"><?= htmlspecialchars($r['rma_number']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['customer_name'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);">
              <?= htmlspecialchars(trim(($r['brand_name'] ?? '') . ' ' . ($r['model_name'] ?? ''))) ?: '—' ?>
            </td>
            <td>
              <span class="badge" style="background:<?= htmlspecialchars($r['status_color']) ?>22;color:<?= htmlspecialchars($r['status_color']) ?>;border:0.5px solid <?= htmlspecialchars($r['status_color']) ?>66;">
                <?= status_label((string)($r['status_code'] ?? ''), $r['status_label']) ?>
              </span>
            </td>
            <td style="color:var(--text-muted);"><?= format_date($r['created_at']) ?></td>
            <td style="text-align:right;">
              <a href="/portal/rma/<?= (int)$r['id'] ?>" onclick="event.stopPropagation()" class="btn btn-sm"><?= __('misc.view') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <style>
      /* Ellipsis on long cells so rows stay single-line. */
      #rma-table td, #rma-table th {
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
    </style>
  <?php endif; ?>

</div>
