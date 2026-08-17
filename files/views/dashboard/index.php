<?php defined('RMS') or die('Direct access not permitted'); ?>

<style>
/* All dashboard list tables share one fixed column layout so the sections
   (Nedavne reklamacije, Otvorene popravke, …) line up identically. */
.dash-table { table-layout: fixed; }
.dash-table th, .dash-table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dash-table th:nth-child(1), .dash-table td:nth-child(1) { width: 20%; }
.dash-table th:nth-child(2), .dash-table td:nth-child(2) { width: 24%; }
.dash-table th:nth-child(3), .dash-table td:nth-child(3) { width: 22%; }
.dash-table th:nth-child(4), .dash-table td:nth-child(4) { width: 18%; }
.dash-table th:nth-child(5), .dash-table td:nth-child(5) { width: 16%; }
</style>

<div style="padding:1.5rem;">

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1rem;align-items:stretch;">
    <?php
    $dcards = [
      ['label'=> __('dashboard.open_rmas'),       'value'=> (int)$stats['open_rmas'],       'color'=>'var(--accent)'],
      ['label'=> __('dashboard.in_repair'),        'value'=> (int)$stats['in_repair'],        'color'=>'#e8860a'],
      ['label'=> __('dashboard.for_pickup'),       'value'=> (int)$stats['for_pickup'],       'color'=>'#1D9E75'],
      ['label'=> __('dashboard.sla_breached'),     'value'=> (int)$stats['sla_breached'],     'color'=> $stats['sla_breached'] > 0 ? '#a32d2d' : 'var(--accent)'],
      ['label'=> __('dashboard.pending_invoices'), 'value'=> (int)$stats['pending_invoices'], 'color'=>'var(--text-secondary)'],
    ];
    foreach ($dcards as $c): ?>
      <div class="card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;margin-top:0;">
        <div style="font-size:28px;font-weight:600;color:<?= $c['color'] ?>;"><?= $c['value'] ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $c['label'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php
    $recent = db_rows("SELECT r.rma_number, r.id, r.priority, r.created_at,
                               s.code as status_code, s.label as status_label, s.color as status_color,
                               c.name as customer_name
                        FROM rma_requests r
                        JOIN rma_statuses s ON s.id = r.status_id
                        LEFT JOIN customers c ON c.id = r.customer_id
                        WHERE r.deleted_at IS NULL
                        ORDER BY r.created_at DESC LIMIT 10");
  ?>
  <?php if (!empty($recent)): ?>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);"><?= __('dashboard.recent_rmas') ?></h2>
      <a href="/rma" style="font-size:12px;color:var(--accent);text-decoration:none;"><?= __('dashboard.view_all') ?></a>
    </div>
    <table class="data-table dash-table">
      <thead>
        <tr>
          <th><?= __('label.rma') ?></th>
          <th><?= __('rma.customer') ?></th>
          <th><?= __('label.status') ?></th>
          <th><?= __('label.priority') ?></th>
          <th style="text-align:right;"><?= __('label.created') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr onclick="window.location='/rma/<?= (int)$r['id'] ?>'">
            <td style="font-weight:500;"><?= htmlspecialchars($r['rma_number']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['customer_name'] ?? '—') ?></td>
            <td>
              <span class="badge badge-status" style="<?= ($r['status_code'] ?? '') === 'cancelled' ? 'background:#fcebeb;color:#a32d2d;border:0.5px solid #f09595;' : 'background:'.htmlspecialchars($r['status_color']).'22;color:'.htmlspecialchars($r['status_color']).';'.'border:0.5px solid '.htmlspecialchars($r['status_color']).'66;' ?>">
                <?= status_label($r['status_code'] ?? '', $r['status_label']) ?>
              </span>
            </td>
            <td>
              <?php $pc = match($r['priority']) { 'urgent'=>'#a32d2d','high'=>'#854f0b','low'=>'#085041',default=>'var(--text-muted)' }; ?>
              <span style="font-size:12px;color:<?= $pc ?>;"><?= __('priority.'.$r['priority']) ?></span>
            </td>
            <td style="color:var(--text-muted);text-align:right;"><?= format_date($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ── Open Repairs ─────────────────────────────────────── -->
  <?php if (!empty($open_repairs)): ?>
  <div class="card" style="margin-top:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);"><?= __('dashboard.open_repairs') ?></h2>
      <a href="/repair" style="font-size:12px;color:var(--accent);text-decoration:none;"><?= __('dashboard.view_all') ?></a>
    </div>
    <table class="data-table dash-table">
      <thead>
        <tr>
          <th><?= __('label.work_order') ?></th>
          <th><?= __('rma.customer') ?></th>
          <th><?= __('label.status') ?></th>
          <th><?= __('rma.technician') ?></th>
          <th style="text-align:right;"><?= __('dashboard.started') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($open_repairs as $j):
          $started = $j['started_at'] ?? $j['created_at'];
        ?>
          <tr onclick="window.location='/rma/<?= (int)$j['rma_id'] ?>'" style="cursor:pointer;">
            <td style="font-weight:500;"><?= htmlspecialchars($j['rma_number']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($j['customer_name'] ?? '—') ?></td>
            <td>
              <span class="badge badge-status" style="background:<?= htmlspecialchars($j['status_color']) ?>22;color:<?= htmlspecialchars($j['status_color']) ?>;border:0.5px solid <?= htmlspecialchars($j['status_color']) ?>66;">
                <?= status_label($j['status_code'] ?? '', $j['status_label']) ?>
              </span>
            </td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($j['technician_name'] ?? '—') ?></td>
            <td style="color:var(--text-muted);text-align:right;"><?= format_date($started) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ── Repaired — Waiting Pickup ───────────────────────── -->
  <?php if (!empty($waiting_pickup)): ?>
  <div class="card" style="margin-top:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
      <!-- Same key as the card above it: one name for one thing, in both
           languages, so the two can never drift apart. -->
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);"><?= __('dashboard.for_pickup') ?></h2>
      <a href="/rma" style="font-size:12px;color:var(--accent);text-decoration:none;"><?= __('dashboard.view_all') ?></a>
    </div>
    <table class="data-table dash-table">
      <thead>
        <tr>
          <th><?= __('label.rma') ?></th>
          <th><?= __('rma.customer') ?></th>
          <th><?= __('track.rma_status') ?></th>
          <th><?= __('dashboard.repaired') ?></th>
          <th style="text-align:right;"><?= __('dashboard.days_open') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($waiting_pickup as $w): ?>
          <tr onclick="window.location='/rma/<?= (int)$w['id'] ?>'" style="cursor:pointer;">
            <td style="font-weight:500;"><?= htmlspecialchars($w['rma_number']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($w['customer_name'] ?? '—') ?></td>
            <td>
              <span class="badge badge-status" style="background:<?= htmlspecialchars($w['status_color']) ?>22;color:<?= htmlspecialchars($w['status_color']) ?>;border:0.5px solid <?= htmlspecialchars($w['status_color']) ?>66;">
                <?= status_label($w['status_code'] ?? '', $w['status_label']) ?>
              </span>
            </td>
            <td style="color:var(--text-muted);"><?= format_date($w['last_completed_at']) ?></td>
            <td style="text-align:right;color:<?= (int)$w['days_open'] > 14 ? '#a32d2d' : 'var(--text-muted)' ?>;"><?= (int)$w['days_open'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
