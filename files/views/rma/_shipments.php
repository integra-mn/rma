<?php defined('RMS') or die('Direct access not permitted'); ?>
<?php if (can('shipments', 'view')): ?>
<?php
  // Options for a courier <select>, pre-selecting $sel.
  $courier_opts = function ($sel) use ($couriers) {
      $out = '<option value="">' . htmlspecialchars(__('ship.no_courier')) . '</option>';
      foreach ($couriers as $c) {
          $s = (int) $sel === (int) $c['id'] ? ' selected' : '';
          $out .= '<option value="' . (int) $c['id'] . '"' . $s . '>' . htmlspecialchars($c['name']) . '</option>';
      }
      return $out;
  };
?>
<div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-top:1.25rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;"><?= __('ship.title') ?></h2>
    <?php if (can('shipments', 'create')): ?>
      <button type="button" class="btn btn-sm btn-primary"
              onclick="var f=document.getElementById('ship-add');f.style.display=f.style.display==='none'?'block':'none';">
        <?= __('ship.add') ?>
      </button>
    <?php endif; ?>
  </div>

  <!-- Add form -->
  <?php if (can('shipments', 'create')): ?>
  <div id="ship-add" style="display:none;margin-bottom:1rem;padding:1rem;background:var(--bg-subtle);border-radius:10px;">
    <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/shipment/store">
      <?= csrf_field() ?>
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('ship.direction') ?></label>
          <select name="direction" class="custom-select">
            <option value="inbound"><?= __('ship.dir_inbound') ?></option>
            <option value="outbound"><?= __('ship.dir_outbound') ?></option>
          </select>
        </div>
        <div class="field"><label><?= __('ship.courier') ?></label>
          <select name="courier_id" class="custom-select"><?= $courier_opts($partner_courier_id) ?></select>
        </div>
        <div class="field"><label><?= __('ship.tracking') ?></label><input type="text" name="tracking_number" autocomplete="off"></div>
        <div class="field"><label><?= __('ship.status') ?></label>
          <select name="status" class="custom-select">
            <?php foreach (SHIPMENT_STATUSES as $s): ?><option value="<?= $s ?>"><?= shipment_status_label($s) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('ship.dispatched') ?></label><input type="date" name="dispatched_at"></div>
        <div class="field"><label><?= __('ship.delivered') ?></label><input type="date" name="delivered_at"></div>
        <div class="field"><label><?= __('ship.cost') ?></label><input type="number" step="0.01" min="0" name="cost"></div>
        <div class="field"><label><?= __('ship.notes') ?></label><input type="text" name="notes"></div>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('ship-add').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- List -->
  <?php if (empty($shipments)): ?>
    <p style="font-size:13px;color:#888780;"><?= __('ship.none') ?></p>
  <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data-table">
      <thead><tr>
        <th><?= __('ship.direction') ?></th>
        <th><?= __('ship.courier') ?></th>
        <th><?= __('ship.tracking') ?></th>
        <th><?= __('ship.status') ?></th>
        <th style="text-align:right;"><?= __('ship.cost') ?></th>
        <th><?= __('ship.dispatched') ?></th>
        <th><?= __('ship.delivered') ?></th>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($shipments as $sh):
          $url = courier_tracking_url($sh['courier_tracking_url'] ?? null, $sh['tracking_number'] ?? null);
          $sc  = shipment_status_color($sh['status']); ?>
        <tr>
          <td style="font-weight:500;"><?= $sh['direction'] === 'inbound' ? __('ship.dir_inbound') : __('ship.dir_outbound') ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($sh['courier_name'] ?? '—') ?></td>
          <td>
            <?php if ($url): ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="color:var(--accent);text-decoration:none;"><?= htmlspecialchars($sh['tracking_number']) ?></a>
            <?php else: ?>
              <?= htmlspecialchars($sh['tracking_number'] ?: '—') ?>
            <?php endif; ?>
          </td>
          <td><span class="badge" style="background:<?= $sc ?>22;color:<?= $sc ?>;border:0.5px solid <?= $sc ?>66;"><?= shipment_status_label($sh['status']) ?></span></td>
          <td style="text-align:right;color:var(--text-secondary);"><?= $sh['cost'] !== null ? htmlspecialchars(number_format((float)$sh['cost'], 2)) : '—' ?></td>
          <td style="color:var(--text-muted);"><?= $sh['dispatched_at'] ? format_date($sh['dispatched_at']) : '—' ?></td>
          <td style="color:var(--text-muted);"><?= $sh['delivered_at'] ? format_date($sh['delivered_at']) : '—' ?></td>
          <td style="text-align:right;white-space:nowrap;">
            <a href="/rma/<?= (int)$rma['id'] ?>/shipment/<?= (int)$sh['id'] ?>/label" target="_blank" class="btn btn-sm"><?= __('ship.label') ?></a>
            <?php if (can('shipments', 'edit')): ?>
              <button type="button" class="btn btn-sm" onclick='editShipment(<?= htmlspecialchars(json_encode($sh, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)'><?= __('btn.edit') ?></button>
              <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/shipment/delete" style="display:inline;"
                    data-confirm="<?= htmlspecialchars(__('ship.confirm_delete'), ENT_QUOTES) ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$sh['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><?= __('btn.delete') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<!-- Edit modal -->
<?php if (can('shipments', 'edit')): ?>
<div id="ship-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:1.5rem;width:100%;max-width:640px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('ship.edit') ?></h2>
    <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/shipment/update">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="se-id">
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('ship.direction') ?></label>
          <select name="direction" id="se-direction" class="custom-select">
            <option value="inbound"><?= __('ship.dir_inbound') ?></option>
            <option value="outbound"><?= __('ship.dir_outbound') ?></option>
          </select>
        </div>
        <div class="field"><label><?= __('ship.courier') ?></label>
          <select name="courier_id" id="se-courier" class="custom-select"><?= $courier_opts(0) ?></select>
        </div>
        <div class="field"><label><?= __('ship.tracking') ?></label><input type="text" name="tracking_number" id="se-tracking" autocomplete="off"></div>
        <div class="field"><label><?= __('ship.status') ?></label>
          <select name="status" id="se-status" class="custom-select">
            <?php foreach (SHIPMENT_STATUSES as $s): ?><option value="<?= $s ?>"><?= shipment_status_label($s) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label><?= __('ship.dispatched') ?></label><input type="date" name="dispatched_at" id="se-dispatched"></div>
        <div class="field"><label><?= __('ship.delivered') ?></label><input type="date" name="delivered_at" id="se-delivered"></div>
        <div class="field"><label><?= __('ship.cost') ?></label><input type="number" step="0.01" min="0" name="cost" id="se-cost"></div>
        <div class="field"><label><?= __('ship.notes') ?></label><input type="text" name="notes" id="se-notes"></div>
      </div>
      <div class="modal-actions" style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('ship-edit-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>
</div>
<script>
function editShipment(sh) {
  var g = function (id) { return document.getElementById(id); };
  g('se-id').value        = sh.id;
  g('se-direction').value = sh.direction || 'inbound';
  g('se-courier').value   = sh.courier_id || '';
  g('se-tracking').value  = sh.tracking_number || '';
  g('se-status').value    = sh.status || 'pending';
  g('se-cost').value      = (sh.cost === null || sh.cost === undefined) ? '' : sh.cost;
  g('se-dispatched').value= (sh.dispatched_at || '').slice(0, 10);
  g('se-delivered').value = (sh.delivered_at || '').slice(0, 10);
  g('se-notes').value     = sh.notes || '';
  g('ship-edit-modal').style.display = 'flex';
}
document.getElementById('ship-edit-modal').addEventListener('click', function (e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
<?php endif; ?>
<?php endif; ?>
