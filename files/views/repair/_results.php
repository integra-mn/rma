<?php defined('RMS') or die('Direct access not permitted'); ?>
<?php if (empty($jobs)): ?>
  <p style="font-size:13px;color:var(--text-muted);"><?= __('repair.no_jobs') ?></p>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th><?= __('label.work_order') ?></th>
        <th><?= __('rma.customer') ?></th>
        <th><?= __('label.status') ?></th>
        <th><?= __('label.priority') ?></th>
        <th><?= __('rma.technician') ?></th>
        <th><?= __('repair.time') ?></th>
        <th><?= __('repair.parts') ?></th>
        <th style="text-align:right;"><?= __('label.created') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($jobs as $j): ?>
        <tr onclick="window.location='/repair/<?= (int)$j['id'] ?>'">
          <td style="font-weight:500;"><?= htmlspecialchars($j['rma_number']) ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($j['customer_name'] ?? '—') ?></td>
          <td>
            <span class="badge badge-status" style="<?= ($j['status_code'] ?? '') === 'cancelled' ? 'background:#fcebeb;color:#a32d2d;border:0.5px solid #f09595;' : 'background:'.htmlspecialchars($j['status_color']).'22;color:'.htmlspecialchars($j['status_color']).';'.'border:0.5px solid '.htmlspecialchars($j['status_color']).'66;' ?>">
              <?= status_label((string)($j['status_code'] ?? ''), $j['status_label']) ?>
            </span>
          </td>
          <td>
            <?php $pc = match($j['priority']) { 'urgent'=>'#a32d2d','high'=>'#854f0b','low'=>'#085041',default=>'var(--text-muted)' }; ?>
            <span style="font-size:12px;color:<?= $pc ?>;"><?= __('priority.'.$j['priority']) ?></span>
          </td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($j['tech_name'] ?? '—') ?></td>
          <td style="color:var(--text-muted);font-size:12px;">
            <?php $m = (int)$j['total_minutes']; echo $m ? floor($m/60).'h '.($m%60).'m' : '—'; ?>
          </td>
          <td style="color:var(--text-muted);font-size:12px;text-align:center;"><?= (int)$j['parts_used'] ?></td>
          <td style="color:var(--text-muted);text-align:right;"><?= format_date($j['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php
    // Shared pager — see views/_partials/pager.php.
    $pg_page = $page; $pg_total = $total; $pg_per_page = $per_page;
    $pg_query = ['q'=>$search,'status'=>$status_f];
    include views_path('_partials/pager.php');
  ?>
<?php endif; ?>
