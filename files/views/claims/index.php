<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:var(--w-content, 1200px);">

  <!-- Four numbers, in the order the day goes: what leaves here, what is with
       them, what is with us, what is owed. -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1.5rem;align-items:stretch;grid-auto-rows:1fr;">
    <?php
    $cards = [
      ['label' => __('ins.q_to_report'), 'value' => count($to_report), 'color' => $overdue > 0 ? '#a32d2d' : '#e8860a'],
      ['label' => __('ins.q_with_them'), 'value' => count($with_them), 'color' => 'var(--text-secondary)'],
      ['label' => __('ins.q_with_us'),   'value' => count($with_us),   'color' => '#e8860a'],
      ['label' => __('ins.q_unpaid'),    'value' => count($unpaid),    'color' => 'var(--accent)'],
    ];
    foreach ($cards as $c): ?>
      <div class="card" style="text-align:center;display:flex;flex-direction:column;justify-content:center;margin-top:0;">
        <div style="font-size:28px;font-weight:600;color:<?= $c['color'] ?>;"><?= $c['value'] ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $c['label'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($overdue > 0): ?>
    <div style="background:#fcebeb;border:0.5px solid #f09595;color:#791f1f;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1.25rem;">
      <strong><?= __('ins.q_overdue', ['n' => $overdue]) ?></strong>
    </div>
  <?php endif; ?>

  <?php
  // One table shape for all four groups: the differences between them are which
  // claims are in them, not what a claim looks like.
  $groups = [
    ['title' => __('ins.q_to_report'), 'rows' => $to_report, 'due' => true],
    ['title' => __('ins.q_with_us'),   'rows' => $with_us,   'due' => false],
    ['title' => __('ins.q_with_them'), 'rows' => $with_them, 'due' => false],
    ['title' => __('ins.q_unpaid'),    'rows' => $unpaid,    'due' => false],
  ];
  ?>

  <?php foreach ($groups as $g): ?>
    <?php if (!$g['rows']) continue; ?>
    <div class="card" style="margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;">
        <?= $g['title'] ?> <span style="color:var(--text-muted);font-weight:400;">· <?= count($g['rows']) ?></span>
      </h2>
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:110px;"><?= __('label.rma') ?></th>
            <th><?= __('rma.customer') ?></th>
            <th><?= __('rma.model') ?></th>
            <th style="width:170px;"><?= __('ins.insurer') ?></th>
            <th style="width:140px;"><?= __('ins.claim_number') ?></th>
            <th style="width:150px;text-align:right;"><?= $g['due'] ? __('ins.claim_due') : __('ins.incident_date') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($g['rows'] as $c): ?>
            <?php $late = $g['due'] && !empty($c['report_due_at']) && strtotime($c['report_due_at']) < time(); ?>
            <tr onclick="window.location='/rma/<?= (int)$c['rma_id'] ?>'" style="cursor:pointer;">
              <td style="font-weight:500;"><?= htmlspecialchars($c['rma_number'] ?? '—') ?></td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['customer_name'] ?? '—') ?></td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars(trim(($c['brand_name'] ?? '') . ' ' . ($c['model_name'] ?? '')) ?: '—') ?></td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($c['insurer_name']) ?></td>
              <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($c['claim_number'] ?? '') ?: '—' ?></td>
              <td style="text-align:right;<?= $late ? 'color:#a32d2d;font-weight:500;' : 'color:var(--text-muted);' ?>">
                <?php if ($g['due']): ?>
                  <?= !empty($c['report_due_at']) ? format_datetime($c['report_due_at']) : '—' ?>
                <?php else: ?>
                  <?= !empty($c['incident_date']) ? format_date($c['incident_date']) : '—' ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>

  <?php if (!$to_report && !$with_them && !$with_us && !$unpaid): ?>
    <div class="card"><p style="font-size:13px;color:var(--text-muted);margin:0;"><?= __('ins.q_empty') ?></p></div>
  <?php endif; ?>

</div>
