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
            <span class="badge" style="<?= ($j['status_code'] ?? '') === 'cancelled' ? 'background:#fcebeb;color:#a32d2d;border:0.5px solid #f09595;' : 'background:'.htmlspecialchars($j['status_color']).'22;color:'.htmlspecialchars($j['status_color']).';'.'border:0.5px solid '.htmlspecialchars($j['status_color']).'66;' ?>">
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

  <?php if ($total > $per_page): ?>
    <div style="margin-top:1rem;display:flex;gap:6px;font-size:13px;">
      <?php $pages = ceil($total / $per_page); ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $status_f ? '&status='.urlencode($status_f) : '' ?>" data-page="<?= $i ?>"
           style="padding:5px 10px;border:0.5px solid <?= $i === $page ? 'var(--accent)' : 'var(--border)' ?>;border-radius:6px;color:<?= $i === $page ? 'var(--accent)' : 'var(--text-primary)' ?>;text-decoration:none;">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
