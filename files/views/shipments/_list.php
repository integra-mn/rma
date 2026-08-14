<?php defined('RMS') or die('Direct access not permitted'); ?>
<?php if (empty($shipments)): ?>
  <p style="font-size:13px;color:var(--text-muted);"><?= __('ship.none') ?></p>
<?php else: ?>
  <table class="data-table">
    <thead><tr>
      <th><?= __('label.rma') ?></th>
      <th><?= __('ship.direction') ?></th>
      <th><?= __('ship.courier') ?></th>
      <th><?= __('ship.tracking') ?></th>
      <th><?= __('ship.status') ?></th>
      <th><?= __('ship.dispatched') ?></th>
      <th><?= __('ship.delivered') ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($shipments as $sh):
          $url = courier_tracking_url($sh['courier_tracking_url'] ?? null, $sh['tracking_number'] ?? null);
          $sc  = shipment_status_color($sh['status']); ?>
        <tr onclick="window.location='/rma/<?= (int)$sh['rma_id'] ?>'" style="cursor:pointer;">
          <td style="font-weight:500;">
            <a href="/rma/<?= (int)$sh['rma_id'] ?>" style="color:var(--text-primary);text-decoration:none;"><?= htmlspecialchars($sh['rma_number']) ?></a>
          </td>
          <td><?= $sh['direction'] === 'inbound' ? __('ship.dir_inbound') : __('ship.dir_outbound') ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($sh['courier_name'] ?? '—') ?></td>
          <td onclick="event.stopPropagation();">
            <?php if ($url): ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars($sh['tracking_number']) ?></a>
            <?php else: ?>
              <?= htmlspecialchars($sh['tracking_number'] ?: '—') ?>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-status" style="background:<?= $sc ?>22;color:<?= $sc ?>;border:0.5px solid <?= $sc ?>66;"><?= shipment_status_label($sh['status']) ?></span></td>
          <td style="color:var(--text-muted);"><?= $sh['dispatched_at'] ? format_date($sh['dispatched_at']) : '—' ?></td>
          <td style="color:var(--text-muted);"><?= $sh['delivered_at'] ? format_date($sh['delivered_at']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
