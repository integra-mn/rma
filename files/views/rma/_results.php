<?php defined('RMS') or die('Direct access not permitted'); ?>
<?php if (empty($rmas)): ?>
  <p style="font-size:13px;color:var(--text-muted);"><?= __('rma.no_rmas') ?></p>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th><?= __('rma.number') ?></th>
        <th><?= __('rma.customer') ?></th>
        <th><?= __('rma.partner') ?></th>
        <th><?= __('label.status') ?></th>
        <th><?= __('label.priority') ?></th>
        <th><?= __('rma.technician') ?></th>
        <th style="text-align:right;"><?= __('label.created') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rmas as $r): ?>
        <tr onclick="window.location='/rma/<?= (int)$r['id'] ?>'">
          <td style="font-weight:500;">
            <a href="/rma/<?= (int)$r['id'] ?>" style="color:var(--text-primary);text-decoration:none;">
              <?= htmlspecialchars($r['rma_number']) ?>
            </a>
            <?php if ($r['sla_breached']): ?>
              <span class="badge" style="background:#fcebeb;color:#a32d2d;margin-left:4px;">SLA</span>
            <?php endif; ?>
            <?php if ($r['is_warranty']): ?>
              <span class="badge" style="background:var(--accent-bg);color:var(--accent-text);margin-left:4px;"><?= __('rma.warranty') ?></span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['customer_name'] ?? '—') ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['partner_name'] ?? '—') ?></td>
          <td>
            <span class="badge" style="<?= ($r['status_code'] ?? '') === 'cancelled' ? 'background:#fcebeb;color:#a32d2d;border:0.5px solid #f09595;' : 'background:'.htmlspecialchars($r['status_color']).'22;color:'.htmlspecialchars($r['status_color']).';'.'border:0.5px solid '.htmlspecialchars($r['status_color']).'66;' ?>">
              <?= status_label((string)($r['status_code'] ?? ''), $r['status_label']) ?>
            </span>
          </td>
          <td>
            <?php $pc = match($r['priority']) { 'urgent'=>'#a32d2d','high'=>'#854f0b','low'=>'#085041',default=>'var(--text-muted)' }; ?>
            <span style="font-size:12px;color:<?= $pc ?>;"><?= __('priority.'.$r['priority']) ?></span>
          </td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['tech_name'] ?? '—') ?></td>
          <td style="color:var(--text-muted);text-align:right;"><?= format_date($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($total > $per_page): ?>
    <div style="margin-top:1rem;display:flex;gap:6px;align-items:center;font-size:13px;">
      <?php
        $pages = ceil($total / $per_page);
        $qs    = http_build_query(array_filter(['q'=>$search,'status'=>$status_f,'priority'=>$priority_f]));
      ?>
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?><?= $qs ? '&'.$qs : '' ?>" data-page="<?= $i ?>"
           style="padding:5px 10px;border:0.5px solid <?= $i === $page ? 'var(--accent)' : 'var(--border)' ?>;
                  border-radius:6px;color:<?= $i === $page ? 'var(--accent)' : 'var(--text-primary)' ?>;text-decoration:none;">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
