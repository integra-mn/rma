<?php
defined('RMS') or die('Direct access not permitted');

// FROM / TO flip by direction: outbound = shop → customer, inbound = customer → shop.
$shop = [
    'name'    => $rma['location_name'] ?? setting('app_name', 'Integra'),
    'address' => trim(($rma['location_address'] ?? '') . ', ' . ($rma['location_city'] ?? ''), ', '),
    'phone'   => $rma['location_phone'] ?? '',
];
// Prefer the partner as the counterparty when the RMA came from a partner.
$other = ($rma['partner_name'] ?? '')
    ? ['name' => $rma['partner_name'], 'address' => trim(($rma['partner_address'] ?? '') . ', ' . ($rma['partner_city'] ?? ''), ', '), 'phone' => $rma['partner_phone'] ?? '']
    : ['name' => $rma['customer_name'] ?? '', 'address' => trim(($rma['customer_address'] ?? '') . ', ' . ($rma['customer_zip'] ?? '') . ' ' . ($rma['customer_city'] ?? '')), 'phone' => $rma['customer_phone'] ?? ''];

$from = $shipment['direction'] === 'outbound' ? $shop  : $other;
$to   = $shipment['direction'] === 'outbound' ? $other : $shop;
$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$track_url = courier_tracking_url($shipment['courier_tracking_url'] ?? null, $shipment['tracking_number'] ?? null);
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= __('ship.label') ?> — <?= $h($rma['rma_number']) ?></title>
<link href="/assets/css/fonts.css" rel="stylesheet">
<style>
  @page { size: A6 landscape; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Montserrat", system-ui, Arial, sans-serif; color: #1a1a1f; background: #f4f4f0; font-size: 13px; }
  .label { width: 148mm; max-width: 100%; margin: 16px auto; background: #fff; border: 1px solid #000; }
  .row { display: flex; }
  .head { justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding: 10px 14px; }
  .head .rma { font-size: 22px; font-weight: 700; }
  .head .dir { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; border: 1px solid #000; border-radius: 4px; padding: 3px 8px; }
  .party { flex: 1; padding: 12px 14px; }
  .party + .party { border-left: 1px solid #000; }
  .party h3 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #555; margin-bottom: 4px; }
  .party .name { font-size: 15px; font-weight: 600; }
  .party .line { font-size: 12px; color: #333; margin-top: 2px; }
  .track { border-top: 2px solid #000; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; }
  .track .no { font-size: 18px; font-weight: 700; font-family: monospace; letter-spacing: 1px; }
  .track .courier { font-size: 12px; color: #333; }
  .toolbar { width: 148mm; max-width: 100%; margin: 8px auto; display: flex; gap: 8px; justify-content: flex-end; }
  .toolbar button { background: #1D9E75; color: #fff; border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; }
  .toolbar button.close { background: #DC2626; }
  @media print { .toolbar { display: none; } body { background: #fff; } .label { margin: 0; border: 0; } }
</style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()"><?= __('rma.print_receipt') ?></button>
    <button type="button" class="close" onclick="window.close()"><?= __('btn.cancel') ?></button>
  </div>

  <div class="label">
    <div class="row head">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#555;">RMA</div>
        <div class="rma"><?= $h($rma['rma_number']) ?></div>
      </div>
      <div class="dir"><?= $shipment['direction'] === 'outbound' ? $h(__('ship.dir_outbound')) : $h(__('ship.dir_inbound')) ?></div>
    </div>

    <div class="row">
      <div class="party">
        <h3><?= $h(__('ship.from')) ?></h3>
        <div class="name"><?= $h($from['name'] ?: '—') ?></div>
        <?php if ($from['address']): ?><div class="line"><?= $h($from['address']) ?></div><?php endif; ?>
        <?php if ($from['phone']): ?><div class="line"><?= $h($from['phone']) ?></div><?php endif; ?>
      </div>
      <div class="party">
        <h3><?= $h(__('ship.to')) ?></h3>
        <div class="name"><?= $h($to['name'] ?: '—') ?></div>
        <?php if ($to['address']): ?><div class="line"><?= $h($to['address']) ?></div><?php endif; ?>
        <?php if ($to['phone']): ?><div class="line"><?= $h($to['phone']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="track">
      <div>
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#555;"><?= $h(__('ship.tracking')) ?></div>
        <div class="no"><?= $h($shipment['tracking_number'] ?: '—') ?></div>
      </div>
      <div class="courier"><?= $h($shipment['courier_name'] ?: '—') ?></div>
    </div>
  </div>
</body>
</html>
