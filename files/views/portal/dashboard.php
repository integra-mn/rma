<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;">

  <?php if (!$partner): ?>
    <div class="alert alert-danger" style="margin-bottom:1rem;">
      <?= __('portal.no_partner') ?>
    </div>
  <?php else: ?>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:1rem;">
      <?= __('portal.welcome') ?> <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong>
      &nbsp;·&nbsp; <?= htmlspecialchars($partner['name']) ?>
    </p>
  <?php endif; ?>

  <!-- Stat cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:2rem;align-items:stretch;">
    <?php $dcards = [
      ['label' => __('dashboard.open_rmas'),        'value' => $stats['open'],         'color' => 'var(--accent)'],
      ['label' => __('dashboard.in_repair'),         'value' => $stats['in_repair'],    'color' => '#e8860a'],
      ['label' => __('portal.ready_pickup'),  'value' => $stats['ready_pickup'], 'color' => '#085041'],
      ['label' => __('reports.this_month'),        'value' => $stats['this_month'],   'color' => 'var(--text-secondary)'],
    ]; foreach ($dcards as $c): ?>
      <div class="card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;margin-top:0;">
        <div style="font-size:28px;font-weight:600;color:<?= $c['color'] ?>;"><?= (int)$c['value'] ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $c['label'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Recent RMAs -->
  <?php if (!empty($recent)): ?>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);"><?= __('dashboard.recent_rmas') ?></h2>
      <a href="/portal/rma" style="font-size:12px;color:var(--accent);text-decoration:none;"><?= __('dashboard.view_all') ?></a>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>RMA</th>
          <th><?= __('rma.customer') ?></th>
          <th><?= __('rma.device') ?></th>
          <th><?= __('label.status') ?></th>
          <th><?= __('track.submitted') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr onclick="window.location='/portal/rma/<?= (int)$r['id'] ?>'" style="cursor:pointer;">
            <td style="font-weight:500;"><?= htmlspecialchars($r['rma_number']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['customer_name'] ?? '—') ?></td>
            <td style="color:var(--text-secondary);">
              <?= htmlspecialchars(trim(($r['brand_name'] ?? '') . ' ' . ($r['model_name'] ?? ''))) ?: '—' ?>
            </td>
            <td>
              <span class="badge badge-status" style="background:<?= htmlspecialchars($r['status_color']) ?>22;color:<?= htmlspecialchars($r['status_color']) ?>;border:0.5px solid <?= htmlspecialchars($r['status_color']) ?>66;">
                <?= status_label((string)($r['status_code'] ?? ''), $r['status_label']) ?>
              </span>
            </td>
            <td style="color:var(--text-muted);"><?= format_date($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php elseif ($partner): ?>
    <p style="font-size:13px;color:var(--text-muted);">
      <?= __('portal.no_rmas_before') ?> <a href="/portal/rma/new" style="color:var(--accent);"><?= __('rma.new') ?></a> <?= __('portal.no_rmas_after') ?>
    </p>
  <?php endif; ?>

</div>
