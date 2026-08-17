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
          <span class="badge" style="<?= ($rma['status_code'] ?? '') === 'cancelled' ? 'background:#fcebeb;color:#a32d2d;border:0.5px solid #f09595;' : 'background:'.htmlspecialchars($rma['status_color']).'22;color:'.htmlspecialchars($rma['status_color']).';'.'border:0.5px solid '.htmlspecialchars($rma['status_color']).'66;' ?>">
            <?= status_label((string)($rma['status_code'] ?? ''), $rma['status_label']) ?>
          </span>
          <?php if ($rma['is_warranty']): ?>
            <span class="badge" style="background:#e1f5ee;color:#085041;border:0.5px solid #5dcaa5;"><?= __('rma.warranty') ?></span>
          <?php else: ?>
            <?php
              $refusals = $rma['warranty_refusal'] ? json_decode($rma['warranty_refusal'], true) : [];
              // out_of_warranty on its own is the third state, not a refusal —
              // the device is simply past its cover and there is nothing to
              // refuse. Alongside other reasons it remains part of a refusal.
              $out_only = $refusals === ['out_of_warranty'];
            ?>
            <?php if ($out_only): ?>
              <span class="badge" style="background:#e8f3ff;color:#185fa5;border:0.5px solid #c5dcf5;"><?= __('rma.ref_out_of_warranty') ?></span>
            <?php elseif (!empty($refusals)): ?>
              <span class="badge" style="background:#fcebeb;color:#a32d2d;border:0.5px solid #f09595;"><?= __('rma.warranty_refused') ?></span>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($rma['sla_breached']): ?>
            <span class="badge" style="background:#fcebeb;color:#a32d2d;"><?= __('rma.sla_breached') ?></span>
          <?php endif; ?>
        </div>
    </div>

  </div>

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
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:2px;"><span style="color:var(--text-secondary);"><?= __('rma.partner') ?>:</span> <?= htmlspecialchars($rma['partner_name'] ?? '—') ?></div>
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
        <!-- Said out loud, because it is not obvious: this is the customer
             record, not a label on this RMA. -->
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= __('rma.identity_customer_hint') ?></p>
      </div>
      <div class="field">
        <label><?= __('label.phone') ?></label>
        <!-- Same country-code + number control as the intake form, so a number
             corrected here is stored in exactly the shape one typed there. -->
        <?= phone_input('customer_phone', $rma['customer_phone'] ?? '') ?>
        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= __('rma.identity_phone_hint') ?></p>
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
    </form>
    <div class="modal-actions" style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem;">
      <button type="button" class="btn" onclick="document.getElementById('identity-modal').style.display='none'"><?= __('btn.cancel') ?></button>
      <button type="submit" form="identity-form" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
    </div>
  </div>
</div>
<script>
(function () {
  var btn = document.getElementById('identity-edit-btn');
  var box = document.getElementById('identity-modal');
  if (!btn || !box) return;
  btn.addEventListener('click', function () {
    box.style.display = 'flex';
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
