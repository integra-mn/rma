<?php defined('RMS') or die('Direct access not permitted'); ?>

<style>
  /* 2-column row grid. `.card + .card { margin-top: 1rem }` from the global
     CSS targets stacked cards; it also matches grid siblings, which makes the
     right-hand card shorter than the left one. Zeroing it inside rma-row-2
     restores equal heights (grid's default align-items: stretch). */
  .rma-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
  .rma-row-2 > .card { margin-top:0; }
  @media (max-width: 760px) { .rma-row-2 { grid-template-columns:1fr; } }
</style>

<div style="padding:1.5rem;max-width:var(--w-form);">

  <?php if (!empty($flash_success)): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash_error)): ?>
    <div class="alert alert-danger" style="margin-bottom:1rem;"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>

  <!-- ── HEADER ── -->
  <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:1.25rem;">
    <div>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 8px 0;">
        Submitted <?= format_datetime($rma['created_at']) ?>
        <?php if ($rma['location_name'] ?? null): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($rma['location_name']) ?><?php endif; ?>
      </p>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span class="badge" style="background:<?= htmlspecialchars($rma['status_color']) ?>22;color:<?= htmlspecialchars($rma['status_color']) ?>;border:0.5px solid <?= htmlspecialchars($rma['status_color']) ?>66;"><?= status_label((string)($rma['status_code'] ?? ''), $rma['status_label']) ?></span>
        <?php
          // The partner is told which of the three states their case is in, not
          // just whether it is a warranty claim. Silence for the other two read
          // as "not decided yet" while the answer was Van garancije — the thing
          // a partner most wants to know, since it decides who pays.
          $p_war = warranty_mode($rma['is_warranty'] ?? 0, $rma['warranty_refusal'] ?? null);
        ?>
        <?php if ($p_war === 'yes'): ?>
          <span class="badge" style="background:#e1f5ee;color:#085041;border:0.5px solid #5dcaa5;"><?= __('rma.warranty') ?></span>
        <?php elseif ($p_war === 'out'): ?>
          <span class="badge" style="background:#e8f3ff;color:#185fa5;border:0.5px solid #c5dcf5;"><?= __('rma.ref_out_of_warranty') ?></span>
        <?php elseif ($p_war === 'refused'): ?>
          <span class="badge" style="background:#fcebeb;color:#a32d2d;border:0.5px solid #f09595;"><?= __('rma.warranty_refused') ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <?php
      // Context-aware action button: exactly one is shown based on current status.
      // See portalController::rma_dispatch() / rma_received() for the matching guards.
      $status_code = $rma['status_code'] ?? '';
      ?>
      <?php if (in_array($status_code, ['draft', 'submitted'], true)): ?>
        <button type="button" class="btn"
                onclick="document.getElementById('dispatch-form').style.display='block';this.style.display='none';">
          <?= __('portal.confirm_dispatched') ?>
        </button>
      <?php elseif ($status_code === 'dispatched'): ?>
        <form method="POST" action="/portal/rma/<?= (int)$rma['id'] ?>/received" style="display:inline;"
              data-confirm="<?= __('portal.confirm_received_js') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn"><?= __('portal.confirm_received') ?></button>
        </form>
      <?php endif; ?>

      <a href="/portal/rma/<?= (int)$rma['id'] ?>/receipt" class="btn btn-primary" target="_blank"><?= __('portal.print_receipt') ?></a>
    </div>
  </div>

  <?php if (in_array($status_code, ['draft', 'submitted'], true)): ?>
    <!-- Inline disclosure for dispatch confirmation + optional tracking number -->
    <div id="dispatch-form" class="card" style="display:none;margin-bottom:14px;">
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px;">
        <?= __('portal.dispatch_help') ?>
      </p>
      <form method="POST" action="/portal/rma/<?= (int)$rma['id'] ?>/dispatch"
            style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?= csrf_field() ?>
        <input type="text" name="tracking" maxlength="200" placeholder="<?= __('portal.tracking_placeholder') ?>"
               style="flex:1;min-width:240px;">
        <button type="submit" class="btn btn-primary"><?= __('portal.confirm_dispatch') ?></button>
        <button type="button" class="btn"
                onclick="document.getElementById('dispatch-form').style.display='none';document.querySelector('[onclick*=dispatch-form]').style.display='inline-flex';">
          <?= __('btn.cancel') ?>
        </button>
      </form>
    </div>
  <?php endif; ?>

  <!-- ── ROW: Customer | Device ── -->
  <div class="rma-row-2">

    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('rma.customer') ?></p>
      <?php if ($rma['customer_name']): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:70px;display:inline-block;"><?= __('label.name') ?></span><strong><?= htmlspecialchars($rma['customer_name']) ?></strong></div>
      <?php endif; ?>
      <?php if ($rma['customer_phone']): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:70px;display:inline-block;"><?= __('label.phone') ?></span><?= htmlspecialchars($rma['customer_phone']) ?></div>
      <?php endif; ?>
      <?php if ($rma['customer_email']): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:70px;display:inline-block;"><?= __('label.email') ?></span><?= htmlspecialchars($rma['customer_email']) ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('rma.device') ?></p>
      <?php if ($rma['brand_name'] || $rma['model_name']): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:70px;display:inline-block;"><?= __('reports.model') ?></span><strong><?= htmlspecialchars(trim(($rma['brand_name'] ?? '').' '.($rma['model_name'] ?? ''))) ?></strong></div>
      <?php endif; ?>
      <?php if (!empty($rma['imei'])): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:70px;display:inline-block;">IMEI</span><span style="font-family:monospace;"><?= htmlspecialchars($rma['imei']) ?></span></div>
      <?php elseif (!empty($rma['serial_number'])): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:70px;display:inline-block;"><?= __('misc.serial') ?></span><span style="font-family:monospace;"><?= htmlspecialchars($rma['serial_number']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── ROW: Accessories | Complaint ── -->
  <?php
  $acc_text = function_exists('format_rma_accessories') ? format_rma_accessories($rma) : '';
  $complaint = trim((string)($rma['complaint'] ?? ''));
  ?>
  <?php if ($acc_text || $complaint !== ''): ?>
  <div class="rma-row-2">
    <?php if ($acc_text): ?>
    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('portal.accessories_received') ?></p>
      <p style="font-size:13.5px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($acc_text) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($complaint !== ''): ?>
    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('portal.reported_issue') ?></p>
      <p style="font-size:13.5px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($complaint) ?></p>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── ROW: Findings | Work done ── -->
  <?php
  $rep = null;
  if (!empty($repair_jobs)) {
      foreach (array_reverse($repair_jobs) as $j) {
          if (trim((string)($j['description'] ?? '')) !== ''
              || trim((string)($j['resolution']  ?? '')) !== '') {
              $rep = $j; break;
          }
      }
  }
  $findings   = $rep ? trim((string)$rep['description']) : '';
  $resolution = $rep ? trim((string)$rep['resolution'])  : '';
  ?>
  <?php if ($findings !== '' || $resolution !== ''): ?>
  <div class="rma-row-2">
    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('portal.tech_findings') ?></p>
      <?php if ($findings !== ''): ?>
        <p style="font-size:13.5px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($findings) ?></p>
      <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;font-style:italic;"><?= __('portal.not_diagnosed') ?></p>
      <?php endif; ?>
    </div>
    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('portal.work_done') ?></p>
      <?php if ($resolution !== ''): ?>
        <p style="font-size:13.5px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($resolution) ?></p>
      <?php else: ?>
        <p style="color:var(--text-muted);font-size:13px;font-style:italic;"><?= __('portal.work_not_done') ?></p>
      <?php endif; ?>
      <?php if (!empty($rep['completed_at'])): ?>
        <p style="font-size:11.5px;color:var(--text-muted);margin-top:12px;padding-top:10px;border-top:0.5px solid #e8e6e0;">
          <?= __('misc.completed') ?> <?= format_date($rep['completed_at']) ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Photo evidence (if any) ── -->
  <?php if (!empty($photos)): ?>
  <div class="card" style="margin-bottom:14px;">
    <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('misc.photo_evidence') ?></p>
    <div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));">
      <?php foreach ($photos as $ph):
        $src = '/storage/' . $ph['filename'];
      ?>
        <a href="<?= htmlspecialchars($src) ?>" target="_blank" rel="noopener"
           style="aspect-ratio:1;border-radius:8px;overflow:hidden;background:#e5e3dc;border:0.5px solid #d3d1c7;display:block;">
          <img src="<?= htmlspecialchars($src) ?>" alt="<?= __('misc.evidence_photo') ?>"
               style="width:100%;height:100%;object-fit:cover;display:block;transition:transform .15s ease;"
               onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'"
               loading="lazy">
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Progress timeline ── -->
  <?php if (!empty($history)): ?>
  <div class="card" style="margin-bottom:14px;">
    <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('misc.progress') ?></p>
    <div style="position:relative;padding-left:24px;">
      <div style="position:absolute;left:6px;top:8px;bottom:8px;width:2px;background:#e8e6e0;"></div>
      <?php
      $last_i = count($history) - 1;
      foreach ($history as $i => $h):
        $is_current = ($i === $last_i);
        $color = htmlspecialchars($h['status_color'] ?? '#888780');
      ?>
        <div style="position:relative;padding-bottom:18px;">
          <div style="position:absolute;left:-<?= $is_current ? 24 : 22 ?>px;top:<?= $is_current ? 1 : 3 ?>px;width:<?= $is_current ? 16 : 12 ?>px;height:<?= $is_current ? 16 : 12 ?>px;border-radius:50%;border:2px solid #fff;background:<?= $color ?>;box-shadow:0 0 0 1px <?= $is_current ? $color : '#d3d1c7' ?><?= $is_current ? ', 0 0 0 6px '.$color.'26' : '' ?>;"></div>
          <div style="font-size:14px;font-weight:<?= $is_current ? 600 : 500 ?>;color:<?= $is_current ? $color : 'inherit' ?>;line-height:1.3;"><?= status_label((string)($h['status_code'] ?? ''), $h['status_label']) ?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= format_datetime($h['created_at']) ?></div>
          <?php if (trim((string)$h['note']) !== ''): ?>
            <div style="font-size:13px;color:#5f5e5a;margin-top:4px;line-height:1.5;"><?= history_note((string)$h['note']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Comments ── -->
  <div class="card" style="margin-bottom:14px;">
    <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('rma.comments') ?></p>

    <?php if (!empty($comments)): ?>
      <?php foreach ($comments as $c):
        $is_customer = ($c['source'] ?? 'staff') === 'customer';
        $label = $is_customer ? __('misc.customer_comment') : __('misc.staff_note');
      ?>
        <div style="border-radius:8px;padding:10px 13px;font-size:13px;line-height:1.5;margin-bottom:8px;border:0.5px solid <?= $is_customer ? '#c5dcf5' : '#e0ddd3' ?>;background:<?= $is_customer ? '#e8f3ff' : '#f4f4f0' ?>;">
          <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin-bottom:5px;font-size:11px;">
            <span style="text-transform:uppercase;letter-spacing:0.06em;font-weight:600;color:<?= $is_customer ? '#1a5bb5' : '#5f5e5a' ?>;"><?= htmlspecialchars($label) ?></span>
            <span style="color:var(--text-muted);"><?= format_datetime($c['created_at']) ?></span>
          </div>
          <?= nl2br(htmlspecialchars($c['body'])) ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;"><?= __('rma.no_comments') ?></p>
    <?php endif; ?>

    <!-- Partner adds a comment. Rows land with source=customer, visibility=customer
         so staff (at /rma/{id}) and the public /track/ page both see them. -->
    <form method="POST" action="/portal/rma/<?= (int)$rma['id'] ?>/comment"
          style="margin-top:12px;padding-top:12px;border-top:0.5px solid var(--border);">
      <?= csrf_field() ?>
      <textarea name="body" rows="3" required maxlength="2000"
                placeholder="<?= __('portal.comment_placeholder') ?>"
                style="width:100%;font-size:13px;margin-bottom:8px;"></textarea>
      <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary"><?= __('portal.post_comment') ?></button>
      </div>
    </form>
  </div>

  <!-- ── Delivery / Invoice (if any) ── -->
  <?php if ($shipment || $invoice): ?>
  <div class="rma-row-2">
    <?php if ($shipment): ?>
    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('misc.delivery') ?></p>
      <?php if (!empty($shipment['status'])): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:90px;display:inline-block;"><?= __('label.status') ?></span><?= htmlspecialchars($shipment['status']) ?></div>
      <?php endif; ?>
      <?php if (!empty($shipment['tracking_number'])): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:90px;display:inline-block;"><?= __('misc.tracking') ?></span><span style="font-family:monospace;"><?= htmlspecialchars($shipment['tracking_number']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($shipment['delivered_at'])): ?>
        <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:90px;display:inline-block;"><?= __('misc.delivered') ?></span><?= format_date($shipment['delivered_at']) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($invoice): ?>
    <div class="card">
      <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;"><?= __('track.invoice') ?></p>
      <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:90px;display:inline-block;"><?= __('misc.number') ?></span><?= htmlspecialchars($invoice['invoice_number']) ?></div>
      <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:90px;display:inline-block;"><?= __('misc.amount') ?></span><?= number_format((float)$invoice['total'], 2) ?> <?= htmlspecialchars($invoice['currency']) ?></div>
      <div style="font-size:13px;padding:4px 0;"><span style="color:var(--text-muted);width:90px;display:inline-block;"><?= __('label.status') ?></span><?= htmlspecialchars(ucfirst($invoice['status'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
