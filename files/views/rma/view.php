<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:var(--w-form);">

  <!-- Header -->
  <div style="margin-bottom:1.5rem;">

    <!-- Created left, status pills right (same line) -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <p style="font-size:13px;color:var(--text-muted);margin:0;">
        <?= __('label.created') ?> <?= format_datetime($rma['created_at']) ?>
        <?php if ($rma['location_name'] ?? null): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($rma['location_name']) ?><?php endif; ?>
      </p>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
          <?php // On one line on purpose: a newline inside an inline-block
                // renders as a space, so a pill written across three lines is
                // wider than its padding and does not match the flat pills
                // beside it. ?>
          <span class="badge" style="<?= ($rma['status_code'] ?? '') === 'cancelled' ? 'background:#fcebeb;color:#a32d2d;' : 'background:'.htmlspecialchars($rma['status_color']).'22;color:'.htmlspecialchars($rma['status_color']).';' ?>"><?= status_label((string)($rma['status_code'] ?? ''), $rma['status_label']) ?></span>
          <?php if ($rma['is_warranty']): ?>
            <span class="badge" style="background:#e1f5ee;color:#085041;"><?= __('rma.warranty') ?></span>
          <?php else: ?>
            <?php
              $refusals = $rma['warranty_refusal'] ? json_decode($rma['warranty_refusal'], true) : [];
              // out_of_warranty on its own is the third state, not a refusal —
              // the device is simply past its cover and there is nothing to
              // refuse. Alongside other reasons it remains part of a refusal.
              $out_only = $refusals === ['out_of_warranty'];
            ?>
            <?php if ($out_only): ?>
              <span class="badge" style="background:#e8f3ff;color:#185fa5;"><?= __('rma.ref_out_of_warranty') ?></span>
            <?php elseif (!empty($refusals)): ?>
              <span class="badge" style="background:#fcebeb;color:#a32d2d;"><?= __('rma.warranty_refused') ?></span>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($rma['sla_breached']): ?>
            <span class="badge" style="background:#fcebeb;color:#a32d2d;"><?= __('rma.sla_breached') ?></span>
          <?php endif; ?>
        </div>
    </div>

  </div>

  <?php
    // Amber when it went out recently, grey when it was simply here before,
    // red when a case for it is open right now — that last one usually means a
    // second case for a device already on the shelf.
    $rp = $repeat ?? ['level' => 'none'];
    if (!empty($rp['open'])) {
        $rp_text  = __('rma.open_case_warning', ['rma' => $rp['open']['rma_number']]);
        $rp_style = 'background:#fcebeb;border:0.5px solid #f09595;color:#791f1f;';
    } elseif (($rp['level'] ?? 'none') === 'repeat') {
        // "nakon 0 dana" is not something anyone says.
        $rp_text  = (int)$rp['days'] === 0
            ? __('rma.repeat_warning_today', ['rma' => $rp['case']['rma_number'] ?? ''])
            : __('rma.repeat_warning', ['days' => (int)$rp['days'], 'rma' => $rp['case']['rma_number'] ?? '']);
        $rp_style = 'background:#faeeda;border:0.5px solid #ef9f27;color:#633806;';
    } elseif (($rp['level'] ?? 'none') === 'seen') {
        $rp_text  = __('rma.seen_before', ['count' => (int)$rp['visits']]);
        $rp_style = 'background:#f4f4f0;border:0.5px solid #d3d1c7;color:#5f5e5a;';
    } else {
        $rp_text = null;
    }
  ?>
  <?php if ($rp_text): ?>
    <?php $rp_ident = $rma['imei'] ?: ($rma['serial_number'] ?? ''); ?>
    <div style="<?= $rp_style ?>border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;">
      <strong><?= htmlspecialchars($rp_text) ?></strong>
      <?php if ($rp_ident !== ''): ?>
        <a href="/device/<?= rawurlencode($rp_ident) ?>" style="color:inherit;text-decoration:underline;"><?= __('rma.device_history') ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($success ?? null): ?>
    <div style="background:#e1f5ee;color:#085041;border:0.5px solid #5dcaa5;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div style="background:#fcebeb;color:#791f1f;border:0.5px solid #f09595;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 336px;gap:1rem;">

    <!-- Left column -->
    <div style="display:flex;flex-direction:column;">

      <!-- Details card -->
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-bottom:1rem;min-height:170px;display:flex;flex-direction:column;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
          <div style="font-size:16px;font-weight:500;margin-bottom:4px;"><?= htmlspecialchars($rma['customer_name'] ?? '—') ?><?= $rma['customer_phone'] ? '<span style="font-size:13px;font-weight:400;color:var(--text-muted);"> &nbsp;·&nbsp; '.$rma['customer_phone'].'</span>' : '' ?></div>
          <?php
            // Only worth offering when there is a history to read: this case
            // plus at least one other, so the device has been in twice or more.
            // $repeat already excludes this case, so one other is enough.
            $ident       = $rma['imei'] ?: ($rma['serial_number'] ?? '');
            $has_history = $ident !== '' && count($repeat['cases'] ?? []) >= 1;
          ?>
          <?php if ($has_history): ?>
            <a href="/device/<?= rawurlencode($ident) ?>"
               style="background:#f4f4f0;color:var(--accent);border:0.5px solid var(--accent);border-radius:6px;padding:3px 10px;font-size:11px;text-decoration:none;flex-shrink:0;white-space:nowrap;">
              <?= __('rma.device_history') ?>
            </a>
          <?php endif; ?>
          <?php if (can('rma', 'edit')): ?>
            <button type="button" id="identity-edit-btn"
                    style="background:#f4f4f0;color:#2c2c2a;border:0.5px solid #d3d1c7;border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer;font-family:inherit;flex-shrink:0;">
              <?= __('btn.edit') ?>
            </button>
          <?php endif; ?>
        </div>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('rma.model') ?>:</span> <?= htmlspecialchars(trim(($rma['brand_name'] ?? '').' '.($rma['model_name'] ?? '')) ?: '—') ?></div>
        <?php if (($rma['serial_number'] ?? null) || ($rma['imei'] ?? null)): ?>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span>
              <?php if ($rma['imei'] ?? null): ?><span style="color:var(--text-secondary);"><?= __('rma.imei') ?>:</span> <?= htmlspecialchars($rma['imei']) ?><?php endif; ?>
              <?php if (($rma['imei'] ?? null) && ($rma['serial_number'] ?? null)): ?> &nbsp;·&nbsp; <?php endif; ?>
              <?php if ($rma['serial_number'] ?? null): ?><span style="color:var(--text-secondary);"><?= __('rma.sn') ?>:</span> <?= htmlspecialchars($rma['serial_number']) ?><?php endif; ?>
            </span>
            <?php
              // The "Provjeri garanciju" button appears for Apple devices when
              // Apple's "Warranty check" toggle is on (Settings → Integrations → Apple).
              $show_warranty_btn = strcasecmp(trim((string)($rma['brand_name'] ?? '')), 'Apple') === 0
                  && (string)setting('gsx_warranty_check', '1') === '1';
            ?>
            <?php if (can('rma', 'edit') && $show_warranty_btn): ?>
              <button type="button" id="warranty-check-btn"
                      style="background:#f4f4f0;color:#2c2c2a;border:0.5px solid #d3d1c7;border-radius:6px;padding:3px 10px;font-size:11px;cursor:pointer;font-family:inherit;">
                <?= __('rma.check_apple_warranty') ?>
              </button>
            <?php endif; ?>
          </div>
          <!-- Result panel populated by fetch() below -->
          <div id="warranty-result" style="display:none;font-size:12px;margin:6px 0 4px;padding:8px 10px;border-radius:6px;border:0.5px solid transparent;line-height:1.5;"></div>
        <?php endif; ?>
        <?php // A walk-in has no partner, so the row is left out rather than
              // printed with a dash. Poslovnica below already worked this way. ?>
        <?php if (!empty($rma['partner_name'])): ?>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('rma.partner') ?>:</span> <?= htmlspecialchars($rma['partner_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($rma['partner_branch_name'])): ?>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('partners.branch') ?>:</span> <?= htmlspecialchars($rma['partner_branch_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($rma['service_box'])): ?>
          <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('rma.service_box') ?>:</span> <?= htmlspecialchars($rma['service_box']) ?></div>
        <?php endif; ?>
        <div style="font-size:13px;color:var(--text-muted);"><span style="color:var(--text-secondary);"><?= __('rma.technician') ?>:</span> <?= $rma['tech_name'] ? htmlspecialchars($rma['tech_name']) : __('rma.unassigned') ?></div>
      </div>

      <script>
      (function () {
        var btn = document.getElementById('warranty-check-btn');
        if (!btn) return;
        var panel = document.getElementById('warranty-result');
        btn.addEventListener('click', function () {
          btn.disabled = true; var original = btn.textContent;
          btn.textContent = 'Checking…';
          panel.style.display = 'none';

          fetch('/rma/<?= (int)$rma['id'] ?>/warranty-check', {method: 'POST'})
            .then(function (r) { return r.json(); })
            .then(function (res) {
              btn.disabled = false; btn.textContent = original;
              if (!res.ok || !res.result) {
                panel.style.display = 'block';
                panel.style.background = '#fcebeb';
                panel.style.borderColor = '#f09595';
                panel.style.color = '#791f1f';
                panel.textContent = res.error || 'Warranty lookup failed.';
                return;
              }
              var r = res.result;
              var bg = '#f4f4f0', border = '#e0ddd3', color = '#2c2c2a';
              if (r.status === 'covered')      { bg = '#e1f5ee'; border = '#5dcaa5'; color = '#085041'; }
              if (r.status === 'expired')      { bg = '#faeeda'; border = '#ef9f27'; color = '#633806'; }
              if (r.status === 'not_covered')  { bg = '#fcebeb'; border = '#f09595'; color = '#791f1f'; }
              panel.style.background = bg; panel.style.borderColor = border; panel.style.color = color;
              panel.style.display = 'block';

              var lines = [];
              lines.push('<strong>' + (r.coverage_label || r.status) + '</strong>');
              if (r.product)          lines.push(escapeHtml(r.product));
              if (r.purchase_date)    lines.push('Purchased ' + r.purchase_date);
              if (r.expiry_date)      lines.push('Coverage until ' + r.expiry_date);
              if (r.activation_locked === true) lines.push('<strong style="color:#791f1f;">⚠ Activation Lock active</strong>');
              if (r.find_my_active === true)    lines.push('<strong style="color:#791f1f;">⚠ Find My is on</strong>');
              panel.innerHTML = lines.join('<br>');
            })
            .catch(function () {
              btn.disabled = false; btn.textContent = original;
              panel.style.display = 'block';
              panel.style.background = '#fcebeb';
              panel.style.borderColor = '#f09595';
              panel.style.color = '#791f1f';
              panel.textContent = 'Network error.';
            });
        });
        function escapeHtml(s) {
          return String(s || '').replace(/[&<>"]/g, function (c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
          });
        }
      })();
      </script>

      <!-- Insurance claim. Only on a case that is one. -->
      <?php if (!empty($claim)): ?>
        <?php
          $cl_colours = [
            'new'       => ['#f4f4f0', '#5f5e5a'],
            'reported'  => ['#e8f3ff', '#185fa5'],
            'more_info' => ['#faeeda', '#633806'],
            'approved'  => ['#e1f5ee', '#085041'],
            'refused'   => ['#fcebeb', '#a32d2d'],
            'paid'      => ['#e1f5ee', '#085041'],
            'closed'    => ['#f4f4f0', '#5f5e5a'],
          ];
          [$cl_bg, $cl_fg] = $cl_colours[$claim['status']] ?? ['#f4f4f0', '#5f5e5a'];
          $cl_next = CLAIM_FLOW[$claim['status']] ?? [];

          // Overdue is worth shouting about: a claim reported late is refused,
          // and nothing else on this page would say so.
          $cl_due  = $claim['report_due_at'] ?? null;
          $cl_late = $cl_due && empty($claim['reported_at']) && strtotime($cl_due) < time();
        ?>
        <div style="background:#fff;border:0.5px solid <?= $cl_late ? '#f09595' : '#d3d1c7' ?>;border-radius:12px;padding:1.25rem;margin-bottom:1rem;">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
            <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin:0;"><?= __('ins.claim') ?></h2>
            <span class="badge" style="background:<?= $cl_bg ?>;color:<?= $cl_fg ?>;"><?= __('ins.claim_' . $claim['status']) ?></span>
            <?php if ($cl_late): ?>
              <span class="badge" style="background:#fcebeb;color:#a32d2d;"><?= __('ins.claim_overdue') ?></span>
            <?php endif; ?>
            <span style="margin-left:auto;font-size:12px;color:var(--text-muted);">
              <?= htmlspecialchars($claim['insurer_name']) ?> &nbsp;·&nbsp; <?= htmlspecialchars($claim['policy_number']) ?>
            </span>
          </div>

          <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;">
            <span style="color:var(--text-secondary);"><?= __('ins.incident_date') ?>:</span>
            <?= $claim['incident_date'] ? format_date($claim['incident_date']) : '—' ?>
            <?php if ($cl_due): ?>
              &nbsp;·&nbsp; <span style="color:var(--text-secondary);"><?= __('ins.claim_due') ?>:</span> <?= format_datetime($cl_due) ?>
            <?php endif; ?>
          </div>
          <?php if (!empty($claim['claim_number'])): ?>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;">
              <span style="color:var(--text-secondary);"><?= __('ins.claim_number') ?>:</span> <?= htmlspecialchars($claim['claim_number']) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($claim['approved_amount'])): ?>
            <?php $cl_split = claim_split((float)$claim['approved_amount'], (float)$claim['participation_pct']); ?>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;">
              <span style="color:var(--text-secondary);"><?= __('ins.claim_approved_amount') ?>:</span>
              <?= number_format((float)$claim['approved_amount'], 2, ',', '.') ?> &euro;
              &nbsp;·&nbsp; <?= __('ins.claim_split', [
                    'insurer'  => number_format($cl_split['insurer'], 2, ',', '.'),
                    'customer' => number_format($cl_split['customer'], 2, ',', '.'),
                    'pct'      => rtrim(rtrim(number_format((float)$claim['participation_pct'], 2, '.', ''), '0'), '.'),
              ]) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($claim['notes'])): ?>
            <div style="font-size:13px;color:var(--text-muted);white-space:pre-wrap;margin-top:6px;"><?= htmlspecialchars($claim['notes']) ?></div>
          <?php endif; ?>

          <?php if ($cl_next && can('rma', 'edit')): ?>
            <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/claim" style="margin-top:12px;">
              <?= csrf_field() ?>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
                <div class="field" style="margin:0;">
                  <label style="font-size:12px;"><?= __('ins.claim_move_to') ?></label>
                  <select name="status" id="claim-status" class="custom-select" onchange="claimFields()">
                    <?php foreach ($cl_next as $st): ?>
                      <option value="<?= $st ?>"><?= __('ins.claim_' . $st) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field" style="margin:0;" id="claim-number-field">
                  <label style="font-size:12px;"><?= __('ins.claim_number') ?></label>
                  <input type="text" name="claim_number" value="<?= htmlspecialchars($claim['claim_number'] ?? '') ?>">
                </div>
                <div class="field" style="margin:0;display:none;" id="claim-amount-field">
                  <label style="font-size:12px;"><?= __('ins.claim_approved_amount') ?></label>
                  <input type="text" name="approved_amount" placeholder="0,00">
                </div>
              </div>
              <input type="text" name="notes" placeholder="<?= __('rma.optional_note') ?>"
                     style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;margin-bottom:10px;">
              <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary" style="min-width:120px;"><?= __('btn.save') ?></button>
              </div>
            </form>
            <script>
            // The portal number belongs to reporting; the amount to a decision.
            // Showing both at once invites one to be filled in at the wrong step.
            function claimFields() {
              var to = document.getElementById('claim-status').value;
              document.getElementById('claim-number-field').style.display = (to === 'reported') ? '' : 'none';
              document.getElementById('claim-amount-field').style.display = (to === 'approved') ? '' : 'none';
            }
            claimFields();
            </script>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Complaint -->
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-bottom:1rem;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:8px;"><?= __('rma.complaint') ?></h2>
        <p style="font-size:13px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($rma['complaint'] ?? '') ?></p>
      </div>

      <!-- Reception Photos -->
      <?php
        $ev_repair_id = null;
        $ev_rma_id    = $rma['id'];
        $ev_stage     = 'reception';
        $ev_card_id   = 'rma_' . $rma['id'];
        include ROOT . '/views/partials/evidence_card.php';
      ?>

      <!-- Accessories -->
      <?php
        $acc_labels = ['battery'=>__('rma.acc_battery'),'charger'=>__('rma.acc_charger'),'sim_card'=>__('rma.acc_sim_card'),'headphones'=>__('rma.acc_headphones'),'packaging'=>__('rma.acc_packaging'),'memory_card'=>__('rma.acc_memory_card'),'protective_case'=>__('rma.acc_protective_case'),'purchase_receipt'=>__('rma.acc_purchase_receipt')];
        $acc_list = $rma['accessories'] ? json_decode($rma['accessories'], true) : [];
      ?>
      <?php if (!empty($acc_list) || !empty($rma['accessories_other'])): ?>
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-bottom:1rem;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:10px;"><?= __('rma.accessories') ?></h2>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <?php foreach ($acc_list as $a): ?>
            <span style="padding:4px 10px;background:var(--accent-bg,#e1f5ee);color:var(--accent-text,#085041);border-radius:6px;font-size:12px;font-weight:500;">
              <?= htmlspecialchars($acc_labels[$a] ?? $a) ?>
            </span>
          <?php endforeach; ?>
          <?php if ($rma['accessories_other']): ?>
            <span style="padding:4px 10px;background:var(--bg-subtle,#f4f4f0);color:var(--text-secondary);border-radius:6px;font-size:12px;">
              <?= htmlspecialchars($rma['accessories_other']) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($rma['diagnosis']): ?>
      <!-- Diagnosis -->
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-bottom:1rem;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:8px;"><?= __('rma.diagnosis') ?></h2>
        <p style="font-size:13px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($rma['diagnosis']) ?></p>
      </div>
      <?php endif; ?>

      <!-- Comments -->
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;flex:1;display:flex;flex-direction:column;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.comments') ?></h2>

        <?php if (empty($comments)): ?>
          <p style="font-size:13px;color:#888780;margin-bottom:1rem;"><?= __('rma.no_comments') ?></p>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <div style="margin-bottom:1rem;padding-bottom:1rem;border-bottom:0.5px solid #f1efe8;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                <span style="font-size:11px;padding:1px 6px;border-radius:4px;
                  background:<?= $c['visibility']==='customer' ? '#e6f1fb' : '#e1f5ee' ?>;
                  color:<?= $c['visibility']==='customer' ? '#0c447c' : '#085041' ?>;">
                  <?= $c['visibility'] ?>
                </span>
                <span style="font-size:12px;color:#888780;"><?= format_datetime($c['created_at']) ?> · <?= $c['author_name'] ? htmlspecialchars($c['author_name']) : __('rma.system') ?></span>
              </div>
              <p style="font-size:13px;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($c['body']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (can('rma', 'edit')): ?>
        <div style="margin-top:auto;padding-top:1rem;border-top:0.5px solid #f1efe8;">
          <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/comment">
      <?= csrf_field() ?>
            <div class="field">
              <textarea name="body" rows="3" placeholder="<?= __('rma.add_comment_placeholder') ?>" style="resize:none;"></textarea>
            </div>
            <div style="display:flex;gap:8px;">
              <!-- Staff observation / update, logged by staff. -->
              <button type="submit" name="kind" value="staff"
                      class="btn" style="flex:1;background:#e1f5ee;color:#085041;border-color:#5dcaa5;">
                <?= __('rma.staff_note') ?>
              </button>
              <!-- Customer's own words, recorded by staff on their behalf. -->
              <button type="submit" name="kind" value="customer"
                      class="btn" style="flex:1;background:#e6f1fb;color:#0c447c;border-color:#93c5fd;">
                <?= __('rma.customer_comment') ?>
              </button>
            </div>
          </form>
        </div>
        <?php endif; ?>

      </div>

    </div>

    <!-- Right column -->
    <div style="display:flex;flex-direction:column;">

      <!-- Actions -->
      <?php $existing_job = db_val('SELECT id FROM repair_jobs WHERE rma_id = ? AND deleted_at IS NULL', [(int)$rma['id']]); ?>
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-bottom:1rem;min-height:170px;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:0.75rem;"><?= __('label.actions') ?></h2>
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;">
          <?php if (can('repair', 'create')): ?>
            <?php if ($existing_job): ?>
              <a href="/repair/<?= (int)$existing_job ?>" class="btn" style="text-align:center;"><?= __('rma.view_repair_job') ?></a>
            <?php else: ?>
              <form method="POST" action="/repair/rma/<?= (int)$rma['id'] ?>/create" style="display:contents;">
      <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><?= __('rma.create_repair_job') ?></button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($rma['tracking_token']): ?>
            <a href="/track/<?= htmlspecialchars($rma['tracking_token']) ?>" target="_blank" class="btn" style="text-align:center;"><?= __('rma.tracking_page') ?></a>
          <?php endif; ?>
          <a href="/rma/<?= (int)$rma['id'] ?>/receipt" target="_blank" class="btn" style="text-align:center;"><?= __('rma.print_receipt') ?></a>
          <?php if (!empty($rma['customer_email'])): ?>
            <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/send-receipt" style="display:contents;">
      <?= csrf_field() ?>
              <button type="submit" class="btn"><?= __('rma.send_receipt') ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Status update -->
      <?php if (can('rma', 'edit')): ?>
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-bottom:1rem;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.current_status') ?></h2>
        <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/update">
      <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <select name="status_id" class="custom-select">
            <?php if (empty($status_current_offered)): ?>
              <!-- The case's own status is not this desk's to set, so the box
                   opens here rather than on a status they cannot choose. -->
              <option value=""><?= __('rma.status_keep') ?></option>
            <?php endif; ?>
            <?php foreach ($statuses as $st): ?>
              <option value="<?= (int)$st['id'] ?>" <?= (int)$rma['status_id'] === (int)$st['id'] ? 'selected' : '' ?>>
                <?= status_label((string)($st['code'] ?? ''), $st['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="note" placeholder="<?= __('rma.optional_note') ?>"
                 style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;margin-top:8px;margin-bottom:12px;">
          <div style="display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.update') ?></button>
          </div>
        </form>
      </div>

      <!-- Update Repair Details -->
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;margin-bottom:1rem;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.repair_details') ?></h2>
        <form method="POST" action="/rma/<?= (int)$rma['id'] ?>/update">
      <?= csrf_field() ?>
          <input type="hidden" name="action" value="details">
          <div class="field">
            <label><?= __('label.priority') ?></label>
            <select name="priority" class="custom-select">
              <?php foreach (['low'=>__('priority.low'),'normal'=>__('priority.normal'),'high'=>__('priority.high'),'urgent'=>__('priority.urgent')] as $val => $lbl): ?>
                <option value="<?= $val ?>" <?= $rma['priority'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= __('rma.technician') ?></label>
            <select name="assigned_tech" class="custom-select">
              <option value=""><?= __('rma.unassigned') ?></option>
              <?php foreach ($technicians as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)($rma['assigned_tech'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.update') ?></button>
          </div>
        </form>
      </div>



      <!-- Status timeline -->
      <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.25rem;flex:1;">
        <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.timeline') ?></h2>
        <?php if (empty($history)): ?>
          <p style="font-size:13px;color:#888780;"><?= __('rma.no_history') ?></p>
        <?php else: ?>
          <?php foreach ($history as $h): ?>
            <div style="display:flex;gap:10px;margin-bottom:10px;font-size:12px;">
              <div style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($h['status_color']) ?>;margin-top:3px;flex-shrink:0;"></div>
              <div>
                <span style="font-weight:500;"><?= status_label((string)($h['status_code'] ?? ''), $h['status_label']) ?></span>
                <?php if ($h['note']): ?>
                  <span style="color:#5f5e5a;"> — <?= history_note((string)$h['note']) ?></span>
                <?php endif; ?>
                <div style="color:#888780;margin-top:2px;">
                  <?= format_datetime($h['created_at']) ?>
                  <?= $h['changed_by_name'] ? ' &middot; '.$h['changed_by_name'] : '' ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php endif; ?>

    </div>
  </div>

  <?php include views_path('rma/_shipments.php'); ?>

</div>

<?php if (can('rma', 'edit')): ?>
<!-- Correcting a typo made at the counter. Only the three fields that had no
     way to be fixed: everything else on this page is already editable, and the
     complaint text deliberately is not - it is what the customer signed. -->
<div id="identity-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:460px;margin:1rem;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('rma.identity_edit') ?></h2>
    <form id="identity-form" method="POST" action="/rma/<?= (int)$rma['id'] ?>/update">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="identity">
      <div class="field">
        <label><?= __('rma.identity_customer') ?></label>
        <input type="text" name="customer_name" value="<?= htmlspecialchars($rma['customer_name'] ?? '') ?>"
               <?= $rma['customer_id'] ? '' : 'disabled' ?>>
      </div>
      <div class="field">
        <label><?= __('label.phone') ?></label>
        <!-- Same country-code + number control as the intake form, so a number
             corrected here is stored in exactly the shape one typed there. -->
        <?= phone_input('customer_phone', $rma['customer_phone'] ?? '') ?>
      </div>
      <div class="field">
        <label><?= __('rma.sn') ?></label>
        <input type="text" name="serial_number" value="<?= htmlspecialchars($rma['serial_number'] ?? '') ?>"
               <?= $rma['device_id'] ? '' : 'disabled' ?>>
      </div>
      <div class="field">
        <label><?= __('rma.imei') ?></label>
        <input type="text" name="imei" value="<?= htmlspecialchars($rma['imei'] ?? '') ?>"
               <?= $rma['device_id'] ? '' : 'disabled' ?>>
      </div>

      <?php // The device itself, not only the numbers on it: the wrong model can
            // be picked at the counter as easily as a wrong digit, and the two
            // dates are what warranty is argued from later. ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field">
          <label><?= __('rma.brand') ?></label>
          <select name="brand_id" id="f-brand" class="custom-select" onchange="filterIdentityModels()"
                  <?= $rma['device_id'] ? '' : 'disabled' ?>>
            <option value=""><?= __('rma.select_brand') ?></option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= (int)$b['id'] ?>" <?= (int)($rma['brand_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('rma.model') ?></label>
          <select name="model_id" id="f-model" class="custom-select" <?= $rma['device_id'] ? '' : 'disabled' ?>>
            <?php foreach ($models as $m): ?>
              <option value="<?= (int)$m['id'] ?>" data-brand="<?= (int)$m['brand_id'] ?>"
                      <?= (int)($rma['model_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="field">
          <label><?= __('rma.purchase_date') ?></label>
          <input type="text" class="datefield" data-name="purchase_date"
                 data-value="<?= htmlspecialchars((string)($rma['purchase_date'] ?? '')) ?>" style="width:100%;">
        </div>
        <div class="field">
          <label><?= __('rma.warranty_expiry') ?></label>
          <input type="text" class="datefield" data-name="warranty_expiry"
                 data-value="<?= htmlspecialchars((string)($rma['warranty_expiry'] ?? '')) ?>" style="width:100%;">
        </div>
      </div>
    </form>
    <div class="modal-actions" style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;">
      <button type="button" class="btn" onclick="document.getElementById('identity-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      <button type="submit" form="identity-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
    </div>
  </div>
</div>
<script>
// Only this brand's models, so a correction cannot pair a Samsung case with an
// Apple model. Runs on open as well as on change, since the case may already be
// on a brand with few models.
function filterIdentityModels() {
  var brand = document.getElementById('f-brand');
  var model = document.getElementById('f-model');
  if (!brand || !model) return;
  var want = brand.value;
  var firstVisible = null;
  Array.prototype.forEach.call(model.options, function (o) {
    var show = !want || o.dataset.brand === want;
    o.hidden   = !show;
    o.disabled = !show;
    if (show && firstVisible === null) firstVisible = o;
  });
  if (model.selectedOptions.length && model.selectedOptions[0].hidden && firstVisible) {
    firstVisible.selected = true;
  }
}

(function () {
  var btn = document.getElementById('identity-edit-btn');
  var box = document.getElementById('identity-modal');
  if (!btn || !box) return;
  btn.addEventListener('click', function () {
    box.style.display = 'flex';
    filterIdentityModels();
    var first = box.querySelector('input:not([type=hidden]):not([disabled])');
    if (first) first.focus();
  });
  /* Click the backdrop or press Escape to leave without saving. */
  box.addEventListener('click', function (e) { if (e.target === box) box.style.display = 'none'; });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && box.style.display === 'flex') box.style.display = 'none';
  });
})();
</script>
<?php endif; ?>
