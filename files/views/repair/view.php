<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:var(--w-form);">

  <!-- Header — above cards -->
  <div style="margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <p style="font-size:13px;color:var(--text-muted);margin:0;"><?= __('label.created') ?> <?= format_datetime($job['created_at']) ?><?= ($job['location_name'] ?? null) ? ' &nbsp;&middot;&nbsp; '.htmlspecialchars($job['location_name']) : '' ?></p>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
          <?php // One line — see the RMA header: the whitespace would render. ?>
          <span class="badge" style="<?= ($job['status_code'] ?? '') === 'cancelled' ? 'background:#fcebeb;color:#a32d2d;' : (($job['status_code'] ?? '') === 'completed' ? 'background:var(--accent-bg);color:var(--accent-text);' : 'background:'.htmlspecialchars($job['status_color']).'22;color:'.htmlspecialchars($job['status_color']).';') ?>"><?= status_label((string)($job['status_code'] ?? ''), $job['status_label']) ?></span>
          <?php
            // Three states, not two. This header asked is_warranty and nothing
            // else, so a device whose period had simply expired was labelled
            // Garancija odbijena — a technician judging the cover void, which is
            // a different and heavier claim. warranty_mode() tells them apart,
            // as the RMA page and the list already do.
            $hdr_war = warranty_mode($job['is_warranty'] ?? 0, $job['warranty_refusal'] ?? null);
          ?>
          <?php if ($hdr_war === 'yes'): ?>
            <span class="badge" style="background:var(--accent-bg);color:var(--accent-text);"><?= __('rma.warranty') ?></span>
          <?php elseif ($hdr_war === 'out'): ?>
            <span class="badge" style="background:#e8f3ff;color:#185fa5;"><?= __('rma.ref_out_of_warranty') ?></span>
          <?php else: ?>
            <span class="badge" style="background:#fcebeb;color:#a32d2d;"><?= __('repair.warranty_refused') ?></span>
          <?php endif; ?>
        </div>
    </div>
  </div>

  <!-- Header row — two cards -->
  <div style="display:grid;grid-template-columns:1fr 336px;gap:1rem;margin-bottom:1rem;">

    <!-- Left card: customer details -->
    <div class="card">
      <div style="font-size:16px;font-weight:500;margin-bottom:4px;"><?= htmlspecialchars($job['customer_name'] ?? '—') ?></div>
      <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('repair.model') ?>:</span> <?= htmlspecialchars(trim(($job['brand_name'] ?? '').' '.($job['model_name'] ?? ''))) ?></div>
      <?php if (($job['serial_number'] ?? null) || ($job['imei'] ?? null)): ?>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;">
          <?php if ($job['imei'] ?? null): ?><span style="color:var(--text-secondary);">IMEI:</span> <?= htmlspecialchars($job['imei']) ?><?php endif; ?>
          <?php if (($job['imei'] ?? null) && ($job['serial_number'] ?? null)): ?> &nbsp;·&nbsp; <?php endif; ?>
          <?php if ($job['serial_number'] ?? null): ?><span style="color:var(--text-secondary);">S/N:</span> <?= htmlspecialchars($job['serial_number']) ?><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php // A device brought in by its owner has no partner and no branch;
            // both rows stay off the card rather than showing a dash. ?>
      <?php if (!empty($job['partner_name'])): ?>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('rma.partner') ?>:</span> <?= htmlspecialchars($job['partner_name']) ?></div>
      <?php endif; ?>
      <?php if (!empty($job['partner_branch_name'])): ?>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('partners.branch') ?>:</span> <?= htmlspecialchars($job['partner_branch_name']) ?></div>
      <?php endif; ?>
      <?php if (!empty($job['service_box'])): ?>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('rma.service_box') ?>:</span> <?= htmlspecialchars($job['service_box']) ?></div>
      <?php endif; ?>
      <div style="font-size:13px;color:var(--text-muted);"><span style="color:var(--text-secondary);"><?= __('rma.technician') ?>:</span> <?= htmlspecialchars($job['tech_name'] ?? '') ?: __('rma.unassigned') ?></div>

    </div>

    <!-- Right card: total time + status -->
    <div class="card" style="margin-top:0;">

      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:8px;">
        <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin:0;"><?= __('repair.current_status') ?></h2>
        <div style="text-align:right;">
          <?php $m = (int)$total_minutes; ?>
          <div style="font-size:20px;font-weight:500;color:var(--text-primary);line-height:1;"><?= $m ? floor($m/60).'h '.($m%60).'m' : '0m' ?></div>
          <div style="font-size:12px;color:var(--text-muted);"><?= __('repair.total_time_logged') ?></div>
        </div>
      </div>
      <?php if (can('repair', 'edit')): ?>
      <form method="POST" action="/repair/<?= (int)$job['id'] ?>/update">
      <?= csrf_field() ?>
        <input type="hidden" name="action" value="details">
        <div class="field" style="margin-bottom:12px;">
          <label><?= __('label.status') ?></label>
          <select name="status_id" class="custom-select">
            <?php foreach ($statuses as $st): ?>
              <option value="<?= (int)$st['id'] ?>" <?= (int)$job['status_id'] === (int)$st['id'] ? 'selected' : '' ?>>
                <?= status_label((string)($st['code'] ?? ''), $st['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;justify-content:flex-end;">
          <button type="submit" class="btn btn-primary" style="min-width:120px;"><?= __('btn.update') ?></button>
        </div>
      </form>
      <?php endif; ?>
    </div>

  </div>

  <?php if ($success ?? null): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
      var toast = document.getElementById('topbar-toast');
      if (!toast) return;
      toast.style.background = 'var(--accent-bg, #e1f5ee)';
      toast.style.color      = 'var(--accent-text, #085041)';
      toast.style.border     = '0.5px solid var(--accent, #1D9E75)';
      toast.textContent      = <?= json_encode($success) ?>;
      toast.style.display    = 'flex';
      toast.style.opacity    = '1';
      setTimeout(function() {
        toast.style.opacity = '0';
        setTimeout(function() { toast.style.display = 'none'; }, 2000);
      }, 4000);
    });
    </script>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Complaint -->
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;"><?= __('rma.complaint') ?></h2>
    <p style="font-size:13px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($job['complaint'] ?? '') ?></p>
  </div>

  <!-- Work notes -->
  <?php if (can('repair', 'edit')): ?>
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('repair.technician_notes') ?></h2>
    <form method="POST" action="/repair/<?= (int)$job['id'] ?>/update">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="details">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
        <div class="field" style="margin-bottom:0;">
          <label><?= __('repair.description_findings') ?></label>
          <textarea name="description" style="resize:none;height:150px;"><?= htmlspecialchars($job['description'] ?? '') ?></textarea>
        </div>
        <div class="field" style="margin-bottom:0;">
          <label><?= __('repair.resolution_action') ?></label>
          <textarea name="resolution" style="resize:none;height:150px;"><?= htmlspecialchars($job['resolution'] ?? '') ?></textarea>
        </div>
      </div>

      <?php // Under each box, the coded answer for the same thing: what was
            // wrong under the findings, what was done under the works. Typed
            // text stays the record — the code is what a vendor's system can
            // read. Only codes written for this brand and device type are
            // offered, plus the ones marked for every device. ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
        <div class="field" style="margin-bottom:0;">
          <label><?= __('codes.kind_error') ?></label>
          <?php if ($error_codes): ?>
            <select name="error_code_id">
              <option value=""><?= __('codes.none_selected') ?></option>
              <?php foreach ($error_codes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($job['error_code_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(repair_code_label($c)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <p style="font-size:12px;color:var(--text-muted);margin-top:6px;"><?= __('codes.none_for_device') ?></p>
          <?php endif; ?>
        </div>
        <div class="field" style="margin-bottom:0;">
          <label><?= __('codes.kind_resolution') ?></label>
          <?php if ($resolution_codes): ?>
            <select name="resolution_code_id">
              <option value=""><?= __('codes.none_selected') ?></option>
              <?php foreach ($resolution_codes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)($job['resolution_code_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(repair_code_label($c)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <p style="font-size:12px;color:var(--text-muted);margin-top:6px;"><?= __('codes.none_for_device') ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary" style="min-width:120px;"><?= __('btn.save') ?></button>
      </div>
    </form>
  </div>
  <?php elseif ($job['description'] || $job['resolution']): ?>
  <div class="card" style="margin-bottom:1rem;">
    <?php if ($job['description']): ?>
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;"><?= __('repair.findings') ?></h2>
      <p style="font-size:13px;line-height:1.6;white-space:pre-wrap;margin-bottom:1rem;"><?= htmlspecialchars($job['description']) ?></p>
    <?php endif; ?>
    <?php if ($job['resolution']): ?>
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;"><?= __('repair.resolution') ?></h2>
      <p style="font-size:13px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($job['resolution']) ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php
    // The codes, wherever the reader stands: the technician sees them chosen in
    // the form above, everyone else sees them here. Read back by id rather than
    // from the offered lists — a code later narrowed to another brand, or
    // switched off, must still show on the job that used it.
    $picked_error      = repair_code($job['error_code_id'] ?? null);
    $picked_resolution = repair_code($job['resolution_code_id'] ?? null);
  ?>
  <?php if ($picked_error || $picked_resolution): ?>
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:8px;"><?= __('codes.title') ?></h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
      <?php foreach ([[__('codes.kind_error'), $picked_error], [__('codes.kind_resolution'), $picked_resolution]] as [$lbl, $c]): ?>
        <div>
          <div style="color:var(--text-secondary);margin-bottom:2px;"><?= $lbl ?></div>
          <?php if ($c): ?>
            <div><?= htmlspecialchars(repair_code_label($c)) ?></div>
            <?php if (!empty($c['note'])): ?>
              <div style="color:var(--text-muted);font-size:12px;margin-top:2px;white-space:pre-wrap;"><?= htmlspecialchars($c['note']) ?></div>
            <?php endif; ?>
          <?php else: ?>
            <div style="color:var(--text-muted);">&mdash;</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Evidence -->
  <?php
    $ev_repair_id = $job['id'];
    $ev_rma_id    = $job['rma_id'] ?? null;
    $ev_stage     = 'repair';
    $ev_card_id   = 'repair_' . $job['id'];
    include ROOT . '/views/partials/evidence_card.php';
  ?>

  <!-- Warranty -->
  <?php if (can('repair', 'edit')): ?>
  <?php
    $refusal_options = [
      'physical_damage'     => __('repair.refuse_physical'),
      'liquid_damage'       => __('repair.refuse_liquid'),
      'unauthorized_repair' => __('repair.refuse_unauthorized'),
    ];
    $current_refusals = $job['warranty_refusal'] ? json_decode($job['warranty_refusal'], true) : [];
    // out_of_warranty stored on its own is the third state rather than a
    // refusal reason — which is how it was already recorded, so nothing in the
    // database changes shape. Worked out here rather than beside the buttons
    // because the form needs it too: "out" and "refused" both carry
    // is_warranty = 0, so that column alone cannot tell them apart.
    $war_mode = warranty_mode($job['is_warranty'], $current_refusals);
    $war_out  = $war_mode === 'out';
    // Van garancije is the end of the line — an expired period cannot become
    // unexpired — so the whole card is read-only there.
    $war_locked = warranty_is_locked($war_mode);
    $btn_off    = 'opacity:0.45;cursor:not-allowed;pointer-events:none;';
    $btn_style = "display:inline-flex;align-items:center;padding:7px 12px;font-size:13px;border-radius:8px;cursor:pointer;border:0.5px solid var(--border,#d3d1c7);background:#fff;color:var(--text-secondary,#5f5e5a);user-select:none;line-height:1;box-sizing:border-box;min-height:34px;font-family:inherit;";
    $btn_active = "background:var(--accent-bg,#e1f5ee);color:var(--accent-text,#085041);border-color:var(--accent,#1D9E75);";
    $btn_ref_active = "background:#fcebeb;color:#a32d2d;border-color:#f09595;";
  ?>
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('repair.warranty_status') ?></h2>
    <form method="POST" action="/repair/<?= (int)$job['id'] ?>/update">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="warranty">
      <input type="hidden" name="is_warranty" id="war-val" value="<?= (int)$job['is_warranty'] ?>"
             data-mode="<?= $war_mode ?>">

      <!-- Under Warranty / Warranty Refused toggle -->
      <div style="display:flex;gap:10px;margin-bottom:1rem;">
        <?php
          // A state you cannot move to is shown but not offered — greyed rather
          // than hidden, so the three states stay visible as a set and it is
          // obvious which one this case is in.
          $can_yes = warranty_can_change($war_mode, 'yes');
          $can_out = warranty_can_change($war_mode, 'out');
          $can_ref = warranty_can_change($war_mode, 'refused');
        ?>
        <button type="button" id="war-yes" onclick="setWarrantyRepair('yes')" <?= $can_yes ? '' : 'disabled' ?>
                style="<?= $btn_style ?><?= $war_mode === 'yes' ? $btn_active : '' ?><?= $can_yes ? '' : $btn_off ?>">
          <?= __('repair.under_warranty') ?>
        </button>
        <button type="button" id="war-out" onclick="setWarrantyRepair('out')" <?= $can_out ? '' : 'disabled' ?>
                style="<?= $btn_style ?><?= $war_mode === 'out' ? 'background:#e8f3ff;color:#185fa5;border-color:#c5dcf5;' : '' ?><?= $can_out ? '' : $btn_off ?>">
          <?= __('repair.refuse_out_of_warranty') ?>
        </button>
        <button type="button" id="war-no" onclick="setWarrantyRepair('refused')" <?= $can_ref ? '' : 'disabled' ?>
                style="<?= $btn_style ?><?= $war_mode === 'refused' ? 'background:#fcebeb;color:#a32d2d;border-color:#f09595;' : '' ?><?= $can_ref ? '' : $btn_off ?>">
          <?= __('repair.warranty_refused') ?>
        </button>
      </div>

      <?php if ($war_locked): ?>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:0;"><?= __('repair.warranty_locked') ?></p>
      <?php endif; ?>

      <!-- Refusal reasons + Save in same row. Both are left out entirely when
           the state can no longer change — there is nothing left to save. -->
      <?php if (!$war_locked): ?>
      <div id="war-reasons">
        <p style="font-size:12px;font-weight:500;color:var(--text-secondary);margin-bottom:10px;"><?= __('repair.refusal_reason') ?></p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
          <?php foreach ($refusal_options as $key => $label): ?>
            <button type="button" id="ref-<?= $key ?>"
                    onclick="toggleRefRepair('<?= $key ?>')"
                    data-active="<?= in_array($key, $current_refusals) ? '1' : '0' ?>"
                    <?php // Reasons belong to a refusal alone — the same rule the
                          // JS applies when a state button is pressed. Keyed off
                          // is_warranty before, which left them live on a job
                          // saved as Van garancije, where ticking one silently
                          // turned it into a refusal. ?>
                    <?= $war_mode !== 'refused' ? 'disabled' : '' ?>
                    style="<?= $btn_style ?><?= in_array($key, $current_refusals) ? $btn_ref_active : '' ?><?= $war_mode !== 'refused' ? 'opacity:1;cursor:not-allowed;pointer-events:none;' : '' ?>">
              <?= $label ?>
            </button>
          <?php endforeach; ?>
          <div style="margin-left:auto;">
            <button type="submit" class="btn btn-primary" style="min-width:120px;" onclick="return validateWarranty();"><?= __('btn.save') ?></button>
          </div>
        </div>
        <div id="war-ref-inputs">
          <?php foreach ($current_refusals as $r): ?>
            <input type="hidden" name="warranty_refusal[]" value="<?= htmlspecialchars($r) ?>">
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </form>
  </div>

  <!-- Apple GSX submission card (technician-only action, shown for Apple devices) -->
  <?php if (!empty($is_apple) && !empty($gsx_vendor_id)): ?>
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:10px;">Apple GSX</h2>

    <?php if (!empty($gsx_submission) && !empty($gsx_submission['vendor_ref'])): ?>
      <!-- Already submitted -->
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:13px;">
        <span class="badge" style="background:var(--accent-bg);color:var(--accent-text);border:0.5px solid var(--accent);">✓ <?= __('repair.submitted') ?></span>
        <span><strong><?= __('repair.gsx_repair_no') ?></strong> <span style="font-family:monospace;"><?= htmlspecialchars($gsx_submission['vendor_ref']) ?></span></span>
        <?php if (!empty($gsx_submission['ra_number'])): ?>
          <span style="color:var(--text-muted);">· RA <span style="font-family:monospace;"><?= htmlspecialchars($gsx_submission['ra_number']) ?></span></span>
        <?php endif; ?>
        <?php if (!empty($gsx_submission['submitted_at'])): ?>
          <span style="color:var(--text-muted);">· <?= format_datetime($gsx_submission['submitted_at']) ?></span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- Not yet submitted -->
      <div style="display:flex;align-items:center;gap:16px;">
        <p style="font-size:12px;color:var(--text-muted);margin:0;line-height:1.5;flex:1;">
          <?= __('repair.gsx_help_1') ?><br>
          <?= __('repair.gsx_help_2') ?>
        </p>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
          <button type="button" id="gsx-submit-btn" class="btn btn-primary" style="min-width:120px;"><?= __('repair.gsx_submit') ?></button>
          <span id="gsx-submit-msg" style="font-size:12px;color:var(--text-muted);"></span>
        </div>
      </div>
      <script>
      (function () {
        var btn = document.getElementById('gsx-submit-btn');
        var msg = document.getElementById('gsx-submit-msg');
        btn.addEventListener('click', function () {
          appConfirm(<?= json_encode(__('repair.gsx_confirm')) ?>, function () {
          var GSX_LABEL = <?= json_encode(__('repair.gsx_submit')) ?>;
          btn.disabled = true; btn.textContent = <?= json_encode(__('repair.gsx_submitting')) ?>; msg.textContent = '';
          fetch('/repair/<?= (int)$job['id'] ?>/submit-to-gsx', {method: 'POST'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
              if (res && res.ok) {
                msg.style.color = '#085041';
                msg.textContent = '✓ ' + <?= json_encode(__('repair.gsx_submitted')) ?>
                                          .replace(':ref', res.vendor_ref || '—');
                setTimeout(function () { location.reload(); }, 800);
              } else {
                btn.disabled = false; btn.textContent = GSX_LABEL;
                msg.style.color = '#791f1f';
                msg.textContent = '✗ ' + (res && res.error ? res.error : <?= json_encode(__('repair.gsx_failed')) ?>);
              }
            })
            .catch(function () {
              btn.disabled = false; btn.textContent = GSX_LABEL;
              msg.style.color = '#791f1f';
              msg.textContent = '✗ ' + <?= json_encode(__('misc.network_error')) ?>;
            });
          });
        });
      })();
      </script>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <script>
  function setWarrantyRepair(mode) {
    var val = mode === 'yes' ? 1 : 0;
    document.getElementById('war-val').value = val;
    var btnYes = document.getElementById('war-yes');
    var btnOut = document.getElementById('war-out');
    var btnNo  = document.getElementById('war-no');
    var active = 'var(--accent-bg,#e1f5ee)';
    var aColor = 'var(--accent-text,#085041)';
    var aBorder= 'var(--accent,#1D9E75)';
    var def    = '#fff';
    var dColor = 'var(--text-secondary,#5f5e5a)';
    var dBorder= 'var(--border,#d3d1c7)';
    [btnYes, btnOut, btnNo].forEach(function (b) {
      b.style.background = def; b.style.color = dColor; b.style.borderColor = dBorder;
    });
    if (mode === 'yes') {
      btnYes.style.background = active; btnYes.style.color = aColor; btnYes.style.borderColor = aBorder;
    } else if (mode === 'out') {
      // The app's light blue, not the red of a refusal.
      btnOut.style.background  = '#e8f3ff';
      btnOut.style.color       = '#185fa5';
      btnOut.style.borderColor = '#c5dcf5';
    } else {
      btnNo.style.background = '#fcebeb'; btnNo.style.color = '#a32d2d'; btnNo.style.borderColor = '#f09595';
    }
    // Disable/enable and reset refusal buttons
    document.querySelectorAll('#war-reasons button[id^="ref-"]').forEach(function(b) {
      b.disabled = mode !== 'refused';
      b.style.cursor = mode === 'refused' ? 'pointer' : 'not-allowed';
      b.style.pointerEvents = mode === 'refused' ? '' : 'none';
      b.style.opacity = '1';
      if (mode !== 'refused') {
        b.dataset.active    = '0';
        b.style.background  = '#fff';
        b.style.color       = dColor;
        b.style.borderColor = dBorder;
      }
    });
    // What each state posts, rebuilt from scratch every time so switching
    // between them cannot leave the previous state's rows behind:
    //   yes      is_warranty=1, nothing else
    //   out      is_warranty=0 and the single out_of_warranty marker, which is
    //            what makes it readable as its own state on the way back in
    //   refused  is_warranty=0 and whichever reasons are ticked
    document.getElementById('war-val').dataset.mode = mode;
    var box = document.getElementById('war-ref-inputs');
    box.innerHTML = '';
    if (mode === 'out') {
      var marker = document.createElement('input');
      marker.type = 'hidden'; marker.name = 'warranty_refusal[]';
      marker.value = 'out_of_warranty';
      box.appendChild(marker);
    } else if (mode === 'refused') {
      document.querySelectorAll('#war-reasons button[id^="ref-"][data-active="1"]').forEach(function (b) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'warranty_refusal[]';
        inp.value = b.id.replace('ref-', '');
        box.appendChild(inp);
      });
    }
  }
  function toggleRefRepair(key) {
    const btn = document.getElementById('ref-' + key);
    const active = btn.dataset.active === '1';
    btn.dataset.active = active ? '0' : '1';
    if (!active) {
      btn.style.background = '#fcebeb'; btn.style.color = '#a32d2d'; btn.style.borderColor = '#f09595';
    } else {
      btn.style.background = '#fff'; btn.style.color = 'var(--text-secondary,#5f5e5a)'; btn.style.borderColor = 'var(--border,#d3d1c7)';
    }
    const container = document.getElementById('war-ref-inputs');
    container.innerHTML = '';
    document.querySelectorAll('#war-reasons button[data-active="1"]').forEach(b => {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'warranty_refusal[]';
      inp.value = b.id.replace('ref-', '');
      container.appendChild(inp);
    });
  }
  function validateWarranty() {
    // Only a refusal needs justifying. Van garancije is a fact about the
    // device, like Pod garancijom, and saves with nothing else filled in —
    // asking why it is out of warranty has no answer to give. Checked on the
    // mode rather than is_warranty, because "out" and "refused" share the
    // same 0 and only the mode separates them.
    if (document.getElementById('war-val').dataset.mode !== 'refused') return true;

    const selected = document.querySelectorAll('#war-reasons button[id^="ref-"][data-active="1"]');
    if (selected.length === 0) {
      appAlert(<?= json_encode(__('rma.err_refusal_reason')) ?>);
      return false;
    }
    return true;
  }
  </script>
  <?php endif; ?>

  <!-- Parts used -->
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('repair.parts_used') ?></h2>

    <?php if (empty($parts_used)): ?>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:1rem;"><?= __('repair.no_parts') ?></p>
    <?php else: ?>
      <table class="data-table" style="margin-bottom:1rem;">
        <thead>
          <tr>
            <th style="width:100px;">SKU</th>
            <th><?= __('repair.part_name') ?></th>
            <th style="width:120px;text-align:center;"><?= __('repair.quantity') ?></th>
            <th style="width:120px;text-align:right;"><?= __('repair.unit_price') ?></th>
            <th style="width:120px;text-align:right;"><?= __('label.total') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($parts_used as $pu): ?>
            <tr>
              <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($pu['sku'] ?? '—') ?></td>
              <td><?= htmlspecialchars($pu['part_name']) ?></td>
              <td style="text-align:center;"><?= (int)$pu['quantity'] ?></td>
              <td style="text-align:right;color:var(--text-secondary);">€<?= number_format((float)$pu['unit_cost'], 2) ?></td>
              <td style="text-align:right;">€<?= number_format((float)$pu['unit_cost'] * (int)$pu['quantity'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr style="border-top:0.5px solid var(--border);">
            <td colspan="4" style="text-align:right;font-weight:500;color:var(--text-secondary);"><?= __('repair.parts_total') ?></td>
            <td style="text-align:right;font-weight:500;">
              €<?= number_format(array_sum(array_map(fn($p) => $p['unit_cost'] * $p['quantity'], $parts_used)), 2) ?>
            </td>
          </tr>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if (can('repair', 'edit')): ?>
    <?php
      // Parts-group options = distinct part_groups represented in the
      // already-filtered set (i.e. within this device's brand + kind).
      // Universal rows (no group) always stay visible and are handled by
      // the JS below.
      $part_group_options = [];
      foreach ($available_parts as $p) {
          if (!empty($p['part_group_id']) && !isset($part_group_options[$p['part_group_id']])) {
              $part_group_options[$p['part_group_id']] = $p['part_group_name']
                  ?: ('#' . $p['part_group_id']);
          }
      }
      asort($part_group_options, SORT_NATURAL | SORT_FLAG_CASE);
    ?>

    <form method="POST" action="/repair/<?= (int)$job['id'] ?>/part" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="part_id" id="part-id-input">

      <div style="width:180px;">
        <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:3px;"><?= __('repair.parts_group') ?></label>
        <select id="part-group">
          <option value="0"><?= __('repair.all_groups') ?></option>
          <?php foreach ($part_group_options as $gid => $gname): ?>
            <option value="<?= (int)$gid ?>"><?= htmlspecialchars($gname) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="flex:2;min-width:220px;position:relative;">
        <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:3px;">
          <?= __('repair.part_or_sku') ?>
          <span style="color:var(--text-muted);font-weight:400;"><?= __('repair.type_to_search') ?></span>
        </label>
        <input type="text" id="part-search" placeholder="<?= __('repair.part_search_placeholder') ?>" autocomplete="off"
               style="width:100%;" list="part-options">
        <datalist id="part-options"></datalist>
        <!-- absolute so the stock-hint sits below without pushing the column taller
             than the sibling form fields (form uses align-items: flex-end). -->
        <p id="part-hint" style="position:absolute;top:100%;left:0;margin-top:4px;font-size:11px;color:var(--text-muted);">&nbsp;</p>
      </div>

      <div style="width:100px;">
        <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:3px;text-align:center;"><?= __('repair.quantity') ?></label>
        <input type="number" name="quantity" value="1" min="1" max="999" style="text-align:center;">
      </div>

      <button type="submit" class="btn btn-primary" style="min-width:120px;"><?= __('repair.log_part') ?></button>
    </form>

    <script>
    // Parts dataset is embedded once; the visible datalist is rebuilt when
    // the Group dropdown changes. Universal parts (category_id = null) stay
    // visible for every group selection — they are consumables / tools that
    // apply everywhere.
    (function () {
      var ALL_PARTS = <?= json_encode(array_map(fn($p) => [
          'id'    => (int)$p['id'],
          'label' => ($p['internal_sku'] ?: '') . ' · ' . $p['name'],
          'group' => $p['part_group_id'] ? (int)$p['part_group_id'] : null,
          'stock' => (int)($p['stock'] ?? 0),
      ], $available_parts)) ?>;

      var input     = document.getElementById('part-search');
      var groupSel  = document.getElementById('part-group');
      var datalist  = document.getElementById('part-options');
      var idInput   = document.getElementById('part-id-input');
      var hint      = document.getElementById('part-hint');
      if (!input) return;

      var byValue = {};

      function rebuild() {
        var g = parseInt(groupSel.value, 10) || 0;
        datalist.innerHTML = '';
        byValue = {};

        // Branded = has a part_group assigned. When no group is selected,
        // show everything; otherwise show only matches.
        var branded = ALL_PARTS.filter(function (p) {
          return p.group !== null && (g === 0 || p.group === g);
        });
        // Universal = no part_group set; always visible.
        var universal = ALL_PARTS.filter(function (p) { return p.group === null; });

        branded.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.label;
          datalist.appendChild(opt);
          byValue[p.label] = p;
        });
        if (branded.length && universal.length) {
          var sep = document.createElement('option');
          sep.disabled = true;
          sep.label = '──── Universal / consumables ────';
          datalist.appendChild(sep);
        }
        universal.forEach(function (p) {
          var opt = document.createElement('option');
          opt.value = p.label;
          datalist.appendChild(opt);
          byValue[p.label] = p;
        });

        // If the current input value is no longer in the filtered set,
        // drop the hidden id so the form can't submit a now-invisible part.
        if (!byValue[input.value]) {
          idInput.value = '';
          hint.innerHTML = '&nbsp;';
        }
      }

      function resolve() {
        var hit = byValue[input.value];
        if (hit) {
          idInput.value = hit.id;
          hint.textContent = <?= json_encode(__('repair.stock_on_hand')) ?>.replace(':count', hit.stock);
          hint.style.color = hit.stock > 0 ? 'var(--text-muted)' : '#a32d2d';
        } else {
          idInput.value = '';
          hint.innerHTML = '&nbsp;';
        }
      }

      groupSel.addEventListener('change', rebuild);
      input.addEventListener('input',  resolve);
      input.addEventListener('change', resolve);
      rebuild();
    })();

    // Legacy helpers kept in case any other page still calls them.
    function syncPartFromSku(val) {
      var nameSel = document.getElementById('part-name-sel');
      if (!nameSel) return;
      nameSel.value = val;
      if (nameSel._customBtn) {
        var opt = nameSel.options[nameSel.selectedIndex];
        nameSel._customBtn.querySelector('span').textContent = opt ? opt.text : '—';
      }
    }
    function syncPartFromName(val) {
      var skuSel = document.getElementById('part-sku-sel');
      if (!skuSel) return;
      skuSel.value = val;
      if (skuSel._customBtn) {
        var opt = skuSel.options[skuSel.selectedIndex];
        skuSel._customBtn.querySelector('span').textContent = opt ? opt.text : '—';
      }
    }
    </script>
    <?php endif; ?>
  </div>

  <!-- Time log -->
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('repair.time_log') ?></h2>

    <?php if (empty($time_logs)): ?>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:1rem;"><?= __('repair.no_time') ?></p>
    <?php else: ?>
      <table class="data-table" style="margin-bottom:1rem;">
        <thead>
          <tr>
            <th style="width:120px;"><?= __('repair.user') ?></th>
            <th style="width:100px;"><?= __('repair.time') ?></th>
            <th><?= __('repair.note') ?></th>
            <th style="width:140px;text-align:right;"><?= __('repair.date_time') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($time_logs as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['user_name'] ?? '—') ?></td>
              <td style="font-size:13px;">
                <?= floor((int)$t['minutes']/60) ?>h <?= (int)$t['minutes']%60 ?>m
              </td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($t['note'] ?? '—') ?></td>
              <td style="text-align:right;color:var(--text-muted);font-size:13px;white-space:nowrap;"><?= format_datetime($t['logged_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if (can('repair', 'edit')): ?>
    <form method="POST" action="/repair/<?= (int)$job['id'] ?>/time" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <?= csrf_field() ?>
      <div style="width:90px;">
        <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:3px;"><?= __('repair.minutes') ?></label>
        <input type="number" name="minutes" min="1" max="999" placeholder="30">
      </div>
      <div style="flex:1;min-width:140px;">
        <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:3px;"><?= __('repair.note') ?></label>
        <input type="text" name="note" placeholder="<?= __('repair.note_placeholder') ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="min-width:120px;"><?= __('repair.log_time') ?></button>
    </form>
    <?php endif; ?>
  </div>

</div>

