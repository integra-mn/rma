<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:var(--w-content, 1100px);">

  <!-- The device itself. Identified by what is written on it, not by a row id. -->
  <div class="card" style="margin-bottom:1rem;">
    <div style="font-size:16px;font-weight:500;margin-bottom:4px;">
      <?= htmlspecialchars(trim(($device['brand_name'] ?? '') . ' ' . ($device['model_name'] ?? '')) ?: '—') ?>
    </div>
    <?php if (!empty($device['imei'])): ?>
      <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;">
        <span style="color:var(--text-secondary);"><?= __('rma.imei') ?>:</span> <?= htmlspecialchars($device['imei']) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($device['serial_number'])): ?>
      <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;">
        <span style="color:var(--text-secondary);"><?= __('rma.sn') ?>:</span> <?= htmlspecialchars($device['serial_number']) ?>
      </div>
    <?php endif; ?>
    <div style="font-size:13px;color:var(--text-muted);">
      <span style="color:var(--text-secondary);"><?= __('rma.device_visits') ?>:</span> <?= count($cases) ?>
    </div>
  </div>

  <?php if (!$cases): ?>
    <div class="card"><p style="font-size:13px;color:var(--text-muted);margin:0;"><?= __('rma.device_no_history') ?></p></div>
  <?php else: ?>
    <?php foreach ($cases as $i => $c): ?>
      <?php
        // Days between this case arriving and the previous one leaving. The
        // previous case is the next row down — the list runs newest first —
        // and only a dispatched one counts: a case still open never left.
        $prev = $cases[$i + 1] ?? null;
        $gap  = null;
        if ($prev && !empty($prev['dispatched_at'])) {
            $gap = (int) floor((strtotime($c['created_at']) - strtotime($prev['dispatched_at'])) / 86400);
            if ($gap < 0) $gap = null;   // overlapping cases say nothing useful
        }
      ?>
      <div class="card" style="margin-bottom:10px;">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
          <a href="/rma/<?= (int)$c['id'] ?>" style="font-size:15px;font-weight:500;color:var(--accent);text-decoration:none;">
            <?= htmlspecialchars($c['rma_number']) ?>
          </a>
          <span class="badge badge-status" style="background:<?= htmlspecialchars($c['status_color']) ?>22;color:<?= htmlspecialchars($c['status_color']) ?>;border:0.5px solid <?= htmlspecialchars($c['status_color']) ?>66;"><?= status_label((string)($c['status_code'] ?? ''), $c['status_label']) ?></span>
          <?php if ($gap !== null): ?>
            <span class="badge" style="background:#faeeda;color:#633806;border:0.5px solid #ef9f27;"><?= __('rma.back_after_days', ['days' => $gap]) ?></span>
          <?php endif; ?>
          <span style="margin-left:auto;font-size:12px;color:var(--text-muted);">
            <?= format_date($c['created_at']) ?>
            <?php if (!empty($c['dispatched_at'])): ?>
              &nbsp;&rarr;&nbsp; <?= format_date($c['dispatched_at']) ?>
            <?php endif; ?>
          </span>
        </div>

        <?php if (!empty($c['complaint'])): ?>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <span style="color:var(--text-secondary);"><?= __('rma.complaint') ?>:</span>
            <?= htmlspecialchars($c['complaint']) ?>
          </div>
        <?php endif; ?>
        <?php if (trim((string)($c['works'] ?? '')) !== ''): ?>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">
            <span style="color:var(--text-secondary);"><?= __('pdf.works_done') ?>:</span>
            <?= htmlspecialchars($c['works']) ?>
          </div>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--text-muted);">
          <?php if (!empty($c['partner_name'])): ?><?= htmlspecialchars($c['partner_name']) ?> &nbsp;·&nbsp; <?php endif; ?>
          <?= htmlspecialchars($c['customer_name'] ?? '—') ?>
          <?php if (!empty($c['tech_name'])): ?> &nbsp;·&nbsp; <?= htmlspecialchars($c['tech_name']) ?><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
