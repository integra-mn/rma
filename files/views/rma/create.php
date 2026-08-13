<?php
defined('RMS') or die('Direct access not permitted');

?>

<div style="padding:1.5rem;max-width:var(--w-form);">

  <?php if ($error ?? null): ?>
    <div style="background:#fcebeb;color:#791f1f;border:0.5px solid #f09595;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/rma/store" id="rma-form" onsubmit="return validateRmaForm()">
      <?= csrf_field() ?>

    <!-- Section: Customer Details -->
    <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.5rem;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.customer_details') ?></h2>

      <!-- Row 1: Search Customer + Partner + Poslovnica. Three columns, matching
           the rest of this section. The branch stays visible whether or not a
           partner is chosen — it used to appear only once one was, which moved
           everything below it and made the field easy to miss. Its options are
           filled from the chosen partner. -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:8px;">
        <div>
          <label style="display:block;font-size:12px;color:var(--text-secondary);margin-bottom:3px;"><?= __('rma.search_customer') ?></label>
          <!-- The box sits inside the RMA form, so Enter submitted the whole
               thing and reloaded the page. Enter now runs the search instead,
               without waiting for the debounce. -->
          <input type="text" id="cust-search-input" placeholder="<?= __('rma.search_phone_email') ?>"
                 oninput="searchCustomers(this.value)"
                 onkeydown="if (event.key === 'Enter') { event.preventDefault(); searchCustomers(this.value, true); }"
                 autocomplete="off" style="width:100%;">
        </div>
        <div>
          <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.partner') ?></label>
          <select name="partner_id" id="rma-partner" class="search-select">
            <option value=""><?= __('rma.select_partner') ?></option>
            <?php foreach ($partners as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('partners.branch') ?></label>
          <select name="partner_branch_id" id="rma-branch" class="search-select">
            <option value=""><?= __('rma.select_branch') ?></option>
          </select>
        </div>
      </div>

      <!-- Match confirmation — content built by JS -->
      <div id="cust-match-confirm" style="display:none;background:#fef9ec;border:0.5px solid #e9c642;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:8px;"></div>

      <!-- No results prompt -->
      <div id="cust-no-results" style="display:none;background:#f4f4f0;border:0.5px solid #d3d1c7;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:8px;">
        <p style="font-weight:500;color:#5f5e5a;margin-bottom:8px;"><?= __('rma.no_customer_add') ?></p>
        <div style="display:flex;gap:8px;">
          <button type="button" class="btn btn-primary" onclick="confirmNewCustomer()"><?= __('rma.yes_add_new') ?></button>
          <button type="button" class="btn" onclick="dismissNoResults()"><?= __('rma.no_search_again') ?></button>
        </div>
      </div>

      <input type="hidden" name="customer_id" id="customer_id_input">

      <!-- Customer fields -->
      <div id="walkin-form">
        <!-- Row 2: Name | Phone | Email -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px;margin-bottom:0;">
          <div class="field"><label><?= __('label.name') ?></label><input type="text" name="walkin_name" id="walkin_name" oninput="checkDuplicates()" placeholder="<?= __('rma.name_surname') ?>"></div>
          <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="walkin_phone" id="walkin_phone" oninput="checkDuplicates()"></div>
          <div class="field"><label><?= __('label.email') ?></label><input type="email" name="walkin_email" id="walkin_email" oninput="checkDuplicates()"></div>
        </div>
        <!-- Row 3: Address | Zip Code | City -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:0;">
          <div class="field"><label><?= __('label.address') ?></label><input type="text" name="walkin_address" id="walkin_address" placeholder=""></div>
          <div class="field"><label><?= __('rma.postal_code') ?></label><input type="text" name="walkin_zip" placeholder=""></div>
          <div class="field"><label><?= __('label.city') ?></label><input type="text" name="walkin_city" id="walkin_city" placeholder=""></div>
        </div>
        <div id="duplicate-warning" style="display:none;margin-top:8px;"></div>
      </div>

    </div>

    <!-- Section: Device Details -->
    <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.5rem;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.device_details') ?></h2>

      <!-- Device fields -->
      <div id="device-new">
        <!-- Device match notice (shown when SN/IMEI matches existing record) -->
        <div id="device-match-notice" style="display:none;background:var(--accent-bg,#e1f5ee);border:0.5px solid var(--accent,#1D9E75);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--accent-text,#085041);margin-bottom:12px;">
          <strong><?= __('rma.existing_device_found') ?></strong> <span id="device-match-text"></span>
          <button type="button" onclick="useExistingDevice()" class="btn btn-sm" style="margin-left:10px;"><?= __('rma.use_this_device') ?></button>
          <button type="button" onclick="dismissDeviceMatch()" style="background:none;border:none;cursor:pointer;font-size:12px;color:var(--accent,#1D9E75);margin-left:6px;"><?= __('rma.dismiss') ?></button>
        </div>
        <input type="hidden" name="device_id" id="device-id-input">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.brand') ?></label>
            <select name="brand_id" id="brand-select" onchange="filterModels()" class="search-select">
              <option value=""><?= __('rma.select_brand') ?></option>
              <?php foreach ($brands as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.model') ?></label>
            <select name="model_id" id="model-select" class="search-select">
              <option value=""><?= __('rma.select_brand_first') ?></option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.serial_number') ?></label>
            <input type="text" name="serial_number" id="serial-number-input" placeholder="<?= __('rma.input_serial') ?>"
                   oninput="checkDeviceMatch()"
                   style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.imei') ?></label>
            <input type="text" name="imei" id="imei-input" placeholder="<?= __('rma.input_imei') ?>" maxlength="15" inputmode="numeric" pattern="\d{15}"
                   oninput="checkDeviceMatch();validateImei(this)"
                   style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.color') ?></label>
            <input type="text" name="color" placeholder="<?= __('rma.optional') ?>"
                   style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.capacity_ram') ?></label>
            <input type="text" name="capacity" placeholder="<?= __('rma.optional') ?>"
                   style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.service_box') ?></label>
            <input type="text" name="service_box" placeholder="<?= __('rma.storage_box') ?>"
                   style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.purchase_date') ?></label>
            <input type="text" class="datefield" data-name="purchase_date" style="width:100%;">
          </div>
          <div>
            <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.warranty_expiry') ?></label>
            <input type="text" class="datefield" data-name="warranty_expiry" style="width:100%;">
          </div>
        </div>
      </div>
    </div>

    <!-- Section: Accessories -->
    <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.5rem;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.accessories_with_device') ?></h2>
      <div style="display:flex;flex-wrap:wrap;gap:10px;" id="acc-buttons">
        <?php
        $accessories = [
          'battery'          => __('rma.acc_battery'),
          'charger'          => __('rma.acc_charger'),
          'sim_card'         => __('rma.acc_sim_card'),
          'headphones'       => __('rma.acc_headphones'),
          'packaging'        => __('rma.acc_packaging'),
          'memory_card'      => __('rma.acc_memory_card'),
          'protective_case'  => __('rma.acc_protective_case'),
          'purchase_receipt' => __('rma.acc_purchase_receipt'),
        ];
        foreach ($accessories as $key => $label): ?>
          <button type="button" id="acc-btn-<?= $key ?>"
                  data-key="<?= $key ?>" data-active="0"
                  onclick="toggleAcc('<?= $key ?>')"
                  style="display:inline-flex;align-items:center;padding:7px 12px;font-size:13px;border-radius:8px;cursor:pointer;border:0.5px solid var(--border,#d3d1c7);background:#fff;color:var(--text-secondary,#5f5e5a);user-select:none;line-height:1;box-sizing:border-box;min-height:34px;">
            <?= $label ?>
          </button>
        <?php endforeach; ?>
      </div>
      <!-- Hidden inputs populated by JS -->
      <div id="acc-inputs"></div>
      <div class="field" style="margin-top:12px;">
        <label><?= __('rma.other_accessories') ?></label>
        <input type="text" name="accessories_other" placeholder="">
      </div>
    </div>

    <!-- Section: Warranty Details -->
    <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.5rem;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.warranty_details') ?></h2>

      <div style="display:flex;gap:10px;margin-bottom:1rem;">
        <button type="button" id="btn-warranty-yes"
                onclick="setWarranty(1)"
                style="display:inline-flex;align-items:center;padding:7px 12px;font-size:13px;border-radius:8px;cursor:pointer;border:0.5px solid var(--border,#d3d1c7);background:#fff;color:var(--text-secondary,#5f5e5a);user-select:none;line-height:1;box-sizing:border-box;min-height:34px;">
          <?= __('rma.under_warranty') ?>
        </button>
        <button type="button" id="btn-warranty-no"
                onclick="setWarranty(0)"
                style="display:inline-flex;align-items:center;padding:7px 12px;font-size:13px;border-radius:8px;cursor:pointer;border:0.5px solid var(--border,#d3d1c7);background:#fff;color:var(--text-secondary,#5f5e5a);user-select:none;line-height:1;box-sizing:border-box;min-height:34px;">
          <?= __('rma.warranty_refused') ?>
        </button>
      </div>
      <input type="hidden" name="is_warranty" id="is_warranty_val" value="">

      <!-- Warranty refusal reasons -->
      <div id="warranty-refusal" style="margin-top:12px;">
        <p style="font-size:12px;font-weight:500;color:#5f5e5a;margin-bottom:10px;"><?= __('rma.refusal_reason') ?></p>
        <div style="display:flex;flex-wrap:wrap;gap:10px;" id="ref-buttons">
          <?php
          $refusal_reasons = [
            'physical_damage'     => __('rma.ref_physical_damage'),
            'liquid_damage'       => __('rma.ref_liquid_damage'),
            'unauthorized_repair' => __('rma.ref_unauthorized_repair'),
            'out_of_warranty'     => __('rma.ref_out_of_warranty'),
          ];
          foreach ($refusal_reasons as $key => $label): ?>
            <button type="button" id="ref-btn-<?= $key ?>"
                    data-key="<?= $key ?>" data-active="0"
                    onclick="toggleRef('<?= $key ?>')"
                    style="display:inline-flex;align-items:center;padding:7px 12px;font-size:13px;border-radius:8px;cursor:pointer;border:0.5px solid var(--border,#d3d1c7);background:#fff;color:var(--text-secondary,#5f5e5a);user-select:none;line-height:1;box-sizing:border-box;min-height:34px;">
              <?= $label ?>
            </button>
          <?php endforeach; ?>
        </div>
        <div id="ref-inputs"></div>
      </div>
    </div>

    <!-- Section: Customer Complaint -->
    <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.5rem;margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.complaint') ?></h2>
      <textarea name="complaint" required rows="5" placeholder="<?= __('rma.complaint_placeholder') ?>"
                style="width:100%;padding:8px 10px;font-size:13px;border:0.5px solid #d3d1c7;border-radius:8px;outline:none;resize:none;overflow-y:auto;"></textarea>
    </div>

    <!-- Section: Repair Details -->
    <div style="background:#fff;border:0.5px solid #d3d1c7;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
      <h2 style="font-size:14px;font-weight:500;color:#5f5e5a;margin-bottom:1rem;"><?= __('rma.repair_details') ?></h2>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.priority_level') ?></label>
          <select name="priority" class="search-select">
            <option value="low"><?= __('priority.low') ?></option>
            <option value="normal" selected><?= __('priority.normal') ?></option>
            <option value="high"><?= __('priority.high') ?></option>
            <option value="urgent"><?= __('priority.urgent') ?></option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('rma.assign_tech') ?></label>
          <select name="assigned_tech" class="search-select">
            <option value=""><?= __('rma.unassigned') ?></option>
            <?php foreach ($technicians as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;color:#5f5e5a;margin-bottom:3px;"><?= __('track.est_completion') ?></label>
          <div style="position:relative;" id="est-days-wrap">
            <button type="button" id="est-days-btn"
                    onclick="document.getElementById('est-days-drop').style.display=document.getElementById('est-days-drop').style.display==='block'?'none':'block'"
                    style="width:100%;height:36px;padding:0 10px;font-size:13px;border:0.5px solid var(--border);border-radius:8px;background:var(--bg-surface);color:var(--text-secondary);cursor:pointer;text-align:left;display:flex;align-items:center;justify-content:space-between;font-family:inherit;">
              <span id="est-days-label"><?= __('rma.select') ?></span>
              <svg viewBox="0 0 10 6" width="10" height="6" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 1l4 4 4-4"/></svg>
            </button>
            <div id="est-days-drop"
                 style="display:none;position:absolute;bottom:calc(100% + 6px);left:0;right:0;background:var(--bg-surface);border:0.5px solid var(--border);border-radius:10px;box-shadow:0 -4px 16px rgba(0,0,0,0.1);z-index:100;padding:4px;overflow:hidden;">
              <?php foreach (['1' => __('rma.days_1'), '2' => __('rma.days_2'), '3' => __('rma.days_3'), '5' => __('rma.days_5'), '7' => __('rma.days_7'), '10' => __('rma.days_10'), '15' => __('rma.days_15')] as $val => $label): ?>
                <div onclick="selectEstDays('<?= $val ?>', '<?= $label ?>')"
                     style="padding:8px 10px;font-size:13px;cursor:pointer;border-radius:7px;color:var(--text-primary);"
                     onmouseover="this.style.background='var(--bg-subtle)'" onmouseout="this.style.background=''">
                  <?= $label ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="estimated_completion" id="estimated_completion_val">
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-start;">
      <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save') ?></button>
      <a href="/rma" class="btn" style="min-width:100px;"><?= __('btn.cancel') ?></a>
    </div>

  </form>
</div>

<script>
const allModels = <?= json_encode(array_map(fn($m) => ['id'=>(int)$m['id'],'name'=>$m['name'],'brand_id'=>(int)$m['brand_id']], $models)) ?>;

// ── Customer search ───────────────────────────────────────────

let searchTimeout = null;

let pendingMatch = null;

// Labels, so the panel is not English inside a Montenegrin form.
const SEARCH_MSG = {
  found:    <?= json_encode(__('rma.match_found')) ?>,
  use:      <?= json_encode(__('rma.match_use')) ?>,
  addNew:   <?= json_encode(__('rma.match_new')) ?>,
  multiple: <?= json_encode(__('rma.match_multiple')) ?>,
  none:     <?= json_encode(__('rma.match_none')) ?>,
  failed:   <?= json_encode(__('rma.search_failed')) ?>
};

function searchCustomers(q, immediate) {
  clearTimeout(searchTimeout);
  const confirm   = document.getElementById('cust-match-confirm');
  const noResults = document.getElementById('cust-no-results');
  confirm.style.display = noResults.style.display = 'none';
  if (q.length < 3) {
    return;
  }

  searchTimeout = setTimeout(() => {
    fetch('/rma/customer-search?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        if (!data.length) {
          noResults.style.display = 'block';
          pendingMatch = null;
          return;
        }
        if (data.length === 1) {
          pendingMatch = data[0];
          const c = data[0];
          const detail = [c.name, c.phone_display||c.phone||'', c.email||'', c.city||''].filter(Boolean).join(' · ');
          confirm.innerHTML = `
            <p style="font-weight:500;color:#7a5c00;margin-bottom:8px;">${escHtml(SEARCH_MSG.found)}</p>
            <p style="color:#5f5e5a;margin-bottom:10px;">${escHtml(detail)}</p>
            <div style="display:flex;gap:8px;">
              <button type="button" class="btn btn-primary" onclick="confirmMatch()">${escHtml(SEARCH_MSG.use)}</button>
              <button type="button" class="btn" onclick="dismissMatch()">${escHtml(SEARCH_MSG.addNew)}</button>
            </div>`;
          confirm.style.display = 'block';
        } else {
          pendingMatch = null;
          confirm.innerHTML = `<p style="font-weight:500;color:#7a5c00;margin-bottom:8px;">${escHtml(SEARCH_MSG.multiple)}</p>`
            + data.map(c => `
              <div onclick="confirmDirect(${c.id},'${escHtml(c.name)}','${escHtml(c.phone_display||c.phone||'')}','${escHtml(c.email||'')}','${escHtml(c.city||'')}','${escHtml(c.address||'')}')"
                   style="padding:8px 10px;cursor:pointer;font-size:13px;border-radius:6px;margin-bottom:4px;border:0.5px solid #d3d1c7;"
                   onmouseover="this.style.background='#f4f4f0'" onmouseout="this.style.background=''">
                <strong>${escHtml(c.name)}</strong>
                <span style="color:#888780;margin-left:8px;">${escHtml(c.phone_display||c.phone||'')} ${c.email?'· '+escHtml(c.email):''}</span>
              </div>`).join('')
            + `<button type="button" class="btn" style="margin-top:6px;" onclick="dismissMatch()">${escHtml(SEARCH_MSG.none)}</button>`;
          confirm.style.display = 'block';
        }
      })
      .catch(() => {
        // Previously silent: a failed request left the form looking as though
        // nothing had been typed at all.
        noResults.style.display = 'none';
        confirm.innerHTML = '<p style="color:#791f1f;">' + escHtml(SEARCH_MSG.failed) + '</p>';
        confirm.style.display = 'block';
      });
  }, immediate ? 0 : 400);
}

function hideAllBanners() {
  document.getElementById('cust-match-confirm').style.display = 'none';
  document.getElementById('cust-no-results').style.display    = 'none';
}

function fillCustomerForm(id, name, phone, email, city, address) {
  document.getElementById('customer_id_input').value = id;
  document.getElementById('walkin_name').value    = name    || '';
  document.getElementById('walkin_phone').value   = phone   || '';
  document.getElementById('walkin_email').value   = email   || '';
  document.getElementById('walkin_city').value    = city    || '';
  document.getElementById('walkin_address').value = address || '';
  document.getElementById('cust-search-input').value = '';
  hideAllBanners();
  pendingMatch = null;
}

function confirmDirect(id, name, phone, email, city, address) {
  fillCustomerForm(id, name, phone, email, city, address);
}

function confirmMatch() {
  if (!pendingMatch) return;
  const c = pendingMatch;
  fillCustomerForm(
    c.id,
    c.name            || '',
    c.phone_display   || c.phone || '',
    c.email           || '',
    c.city            || '',
    c.address         || ''
  );
}

function confirmNewCustomer() {
  const q = document.getElementById('cust-search-input').value.trim();
  document.getElementById('customer_id_input').value = '';
  document.getElementById('cust-search-input').value = '';
  hideAllBanners();
  if (q) document.getElementById('walkin_name').value = q;
  document.getElementById('walkin_name').focus();
}

function dismissNoResults() {
  hideAllBanners();
  document.getElementById('cust-search-input').value = '';
  document.getElementById('cust-search-input').focus();
}

function dismissMatch() {
  document.getElementById('customer_id_input').value = '';
  document.getElementById('cust-search-input').value = '';
  ['walkin_name','walkin_address','walkin_city','walkin_phone','walkin_email'].forEach(function(fid) {
    var el = document.getElementById(fid); if (el) el.value = '';
  });
  hideAllBanners();
  pendingMatch = null;
}

function selectCustomer(id, name, phone, email) {
  fillCustomerForm(id, name, phone, email, '', '');
}

function clearCustomer() {
  document.getElementById('customer_id_input').value = '';
  ['walkin_name','walkin_address','walkin_city','walkin_phone','walkin_email'].forEach(function(fid) {
    var el = document.getElementById(fid); if (el) el.value = '';
  });
  hideAllBanners();
}

// ── Walk-in ───────────────────────────────────────────────────

function toggleWalkIn() {
  document.getElementById('walkin-form').style.display = 'block';
  document.getElementById('cust-results').style.display = 'none';
  clearCustomer();
}

function hideWalkIn() {
  document.getElementById('walkin-form').style.display = 'none';
}

// ── Duplicate detection ───────────────────────────────────────

let dupTimeout = null;

function checkDuplicates() {
  clearTimeout(dupTimeout);
  const name  = document.getElementById('walkin_name').value.trim();
  const phone = document.getElementById('walkin_phone').value.trim();
  const email = document.getElementById('walkin_email').value.trim();
  if (!name && !phone && !email) return;

  dupTimeout = setTimeout(() => {
    fetch('/customers/check-duplicate?name=' + encodeURIComponent(name) +
          '&phone=' + encodeURIComponent(phone) +
          '&email=' + encodeURIComponent(email))
      .then(r => r.json())
      .then(data => {
        const warn = document.getElementById('duplicate-warning');
        if (!data.length) { warn.style.display = 'none'; return; }
        warn.style.display = 'block';
        warn.innerHTML = `
          <div style="background:#faeeda;border:0.5px solid #ef9f27;border-radius:8px;padding:10px 12px;font-size:13px;color:#633806;">
            <strong>${DUP_TITLE}</strong>
            ${data.map(c => `
              <div style="margin-top:6px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                <span>${escHtml(c.name)} ${c.phone ? '· '+escHtml(c.phone) : ''} ${c.email ? '· '+escHtml(c.email) : ''}
                  <em style="font-size:11px;opacity:0.7;">${DUP_BY[c.match_type] || c.match_type}</em>
                </span>
                <button type="button" class="btn btn-sm"
                        onclick="selectCustomer(${c.id},'${escHtml(c.name)}','${escHtml(c.phone||'')}','${escHtml(c.email||'')}');hideWalkIn()">
                  ${DUP_LINK}
                </button>
              </div>`).join('')}
          </div>`;
      });
  }, 500);
}

// The banner was never visible, so nobody noticed it was written in English.
// It is about to be, and this app does not show English to a Montenegrin user.
const DUP_TITLE = <?= json_encode(__('rma.dup_found')) ?>;
const DUP_LINK  = <?= json_encode(__('rma.dup_link')) ?>;
const DUP_BY    = {
  email: <?= json_encode(__('rma.dup_by_email')) ?>,
  phone: <?= json_encode(__('rma.dup_by_phone')) ?>,
  name:  <?= json_encode(__('rma.dup_by_name')) ?>
};

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function filterModels() {
  const brandId = parseInt(document.getElementById('brand-select').value);
  const sel     = document.getElementById('model-select');
  if (!brandId) {
    sel.innerHTML = '<option value=""><?= htmlspecialchars(__('rma.select_brand_first'), ENT_QUOTES) ?></option>';
    return;
  }
  sel.innerHTML = '<option value=""><?= htmlspecialchars(__('rma.select_model'), ENT_QUOTES) ?></option>';
  allModels.filter(m => m.brand_id === brandId).forEach(m => {
    const o = document.createElement('option');
    o.value = m.id; o.textContent = m.name;
    sel.appendChild(o);
  });
}
// Populate models on initial load and on any brand change.
// Uses addEventListener (not just the inline onchange) so we catch programmatic
// mutations and browser autofill that sometimes doesn't dispatch 'change'.
document.addEventListener('DOMContentLoaded', function () {
  var brand = document.getElementById('brand-select');
  if (!brand) return;
  brand.addEventListener('change', filterModels);
  brand.addEventListener('input',  filterModels);
  filterModels();                 // run once now
  setTimeout(filterModels, 200);  // re-run after browser autofill settles
});

const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// Estimated completion days → date
function selectEstDays(days, label) {
  document.getElementById('est-days-label').textContent = label;
  document.getElementById('est-days-label').style.color = days ? 'var(--text-primary)' : 'var(--text-secondary)';
  document.getElementById('est-days-drop').style.display = 'none';
  const hid = document.getElementById('estimated_completion_val');
  if (!days) { hid.value = ''; return; }
  const d = new Date();
  d.setDate(d.getDate() + parseInt(days));
  hid.value = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
// Close dropdown on outside click
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('est-days-wrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('est-days-drop').style.display = 'none';
  }
});

function validateImei(el) {
  const val = el.value.replace(/\D/g, '');
  el.value = val; // strip non-digits
  if (val.length > 0 && val.length < 15) {
    el.style.borderColor = '#f09595';
  } else if (val.length === 15) {
    el.style.borderColor = 'var(--accent)';
  } else {
    el.style.borderColor = '#d3d1c7';
  }
}

function validateRmaForm() {
  const sn      = document.getElementById('serial-number-input').value.trim();
  const imei    = document.getElementById('imei-input').value.trim();
  const warranty = document.getElementById('is_warranty_val').value;
  if (!sn && !imei) {
    appAlert(<?= json_encode(__('rma.err_serial_or_imei')) ?>);
    document.getElementById('serial-number-input').focus();
    return false;
  }
  if (imei && !/^\d{15}$/.test(imei)) {
    appAlert(<?= json_encode(__('rma.err_imei_digits')) ?>);
    document.getElementById('imei-input').focus();
    return false;
  }
  if (warranty === '') {
    appAlert(<?= json_encode(__('rma.err_warranty')) ?>);
    document.getElementById('btn-warranty-yes').scrollIntoView({behavior:'smooth', block:'center'});
    return false;
  }
  return true;
}

// Refusal buttons start non-clickable until warranty status is selected
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('#ref-buttons button').forEach(btn => {
    btn.disabled      = true;
    btn.style.opacity = '1';
    btn.style.cursor  = 'not-allowed';
  });
});
function checkDeviceMatch() {
  clearTimeout(deviceMatchTimer);
  const sn   = document.getElementById('serial-number-input').value.trim();
  const imei = document.getElementById('imei-input').value.trim();
  const query = sn || imei;
  if (query.length < 4) {
    document.getElementById('device-match-notice').style.display = 'none';
    document.getElementById('device-id-input').value = '';
    return;
  }
  deviceMatchTimer = setTimeout(() => {
    const params = sn ? 'sn=' + encodeURIComponent(sn) : 'imei=' + encodeURIComponent(imei);
    fetch('/rma/device-search?' + params)
      .then(r => r.json())
      .then(data => {
        if (data.id) {
          document.getElementById('device-match-text').textContent =
            data.brand + ' ' + data.model + ' — S/N: ' + (data.serial_number || '—');
          document.getElementById('device-match-notice').style.display = 'block';
          document.getElementById('device-match-notice').dataset.deviceId = data.id;
        } else {
          document.getElementById('device-match-notice').style.display = 'none';
          document.getElementById('device-id-input').value = '';
        }
      });
  }, 400);
}

function useExistingDevice() {
  const notice = document.getElementById('device-match-notice');
  document.getElementById('device-id-input').value = notice.dataset.deviceId;
  notice.style.background   = '#e1f5ee';
  notice.querySelector('button').style.display = 'none';
}

function dismissDeviceMatch() {
  document.getElementById('device-match-notice').style.display = 'none';
  document.getElementById('device-id-input').value = '';
}

function setWarranty(val) {
  document.getElementById('is_warranty_val').value = val;
  const yes = document.getElementById('btn-warranty-yes');
  const no  = document.getElementById('btn-warranty-no');

  const setActive = (btn) => {
    btn.style.background  = 'var(--accent-bg, #e1f5ee)';
    btn.style.color       = 'var(--accent-text, #085041)';
    btn.style.borderColor = 'var(--accent, #1D9E75)';
  };
  const setInactive = (btn) => {
    btn.style.background  = '#fff';
    btn.style.color       = 'var(--text-secondary, #5f5e5a)';
    btn.style.borderColor = 'var(--border, #d3d1c7)';
  };

  val ? setActive(yes) : setInactive(yes);
  if (!val) {
    no.style.background  = '#fcebeb';
    no.style.color       = '#a32d2d';
    no.style.borderColor = '#f09595';
  } else {
    setInactive(no);
  }

  // Refusal buttons: always fully visible, clickable only when Warranty Refused
  document.querySelectorAll('#ref-buttons button').forEach(btn => {
    btn.disabled      = !!val;
    btn.style.opacity = '1';
    btn.style.cursor  = val ? 'not-allowed' : 'pointer';
    if (val) {
      btn.dataset.active    = '0';
      btn.style.background  = '#fff';
      btn.style.color       = 'var(--text-secondary, #5f5e5a)';
      btn.style.borderColor = 'var(--border, #d3d1c7)';
    }
  });
  if (val) document.getElementById('ref-inputs').innerHTML = '';
}


function closeSearchResults() {
  document.getElementById('cust-results').style.display = 'none';
  document.getElementById('cust-no-results').style.display = 'none';
  document.getElementById('cust-search-input').value = '';
}

function saveWalkIn() {
  const name  = document.getElementById('walkin_name').value.trim();
  const phone = document.getElementById('walkin_phone').value.trim();
  if (!name || !phone) {
    appAlert(<?= json_encode(__('rma.err_name_phone')) ?>);
    return;
  }
  // Show confirmation in the selected box
  const email = document.getElementById('walkin_email').value.trim();
  document.getElementById('cust-selected-name').textContent = name + ' (New)';
  document.getElementById('cust-selected-detail').textContent = [phone, email].filter(Boolean).join(' · ');
  document.getElementById('cust-selected').style.display = 'block';
  document.getElementById('customer_id_input').value = '';
  document.getElementById('walkin-form').style.display = 'none';
  document.getElementById('cust-search-input').value = '';
  document.getElementById('cust-results').style.display = 'none';
}

function toggleRef(key) {
  const btn    = document.getElementById('ref-btn-' + key);
  const active = btn.dataset.active === '1';
  btn.dataset.active = active ? '0' : '1';
  if (!active) {
    btn.style.background  = '#fcebeb';
    btn.style.color       = '#a32d2d';
    btn.style.borderColor = '#f09595';
  } else {
    btn.style.background  = '#fff';
    btn.style.color       = 'var(--text-secondary, #5f5e5a)';
    btn.style.borderColor = 'var(--border, #d3d1c7)';
  }
  // Rebuild hidden inputs
  const container = document.getElementById('ref-inputs');
  container.innerHTML = '';
  document.querySelectorAll('#ref-buttons button[data-active="1"]').forEach(b => {
    const inp = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'warranty_refusal[]';
    inp.value = b.dataset.key;
    container.appendChild(inp);
  });
}

function toggleAcc(key) {
  const btn   = document.getElementById('acc-btn-' + key);
  const active = btn.dataset.active === '1';
  btn.dataset.active = active ? '0' : '1';
  if (!active) {
    btn.style.background  = 'var(--accent-bg, #e1f5ee)';
    btn.style.color       = 'var(--accent-text, #085041)';
    btn.style.borderColor = 'var(--accent, #1D9E75)';
  } else {
    btn.style.background  = '#fff';
    btn.style.color       = 'var(--text-secondary, #5f5e5a)';
    btn.style.borderColor = 'var(--border, #d3d1c7)';
  }
  // Rebuild hidden inputs
  const container = document.getElementById('acc-inputs');
  container.innerHTML = '';
  document.querySelectorAll('#acc-buttons button[data-active="1"]').forEach(b => {
    const inp = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'accessories[]';
    inp.value = b.dataset.key;
    container.appendChild(inp);
  });
}

// ── Partner branches ────────────────────────────────────────────
// The branch list depends on the partner, so it is filtered here instead of
// with a round trip. The field itself is always visible; only its options
// depend on the partner.
(function () {
  var BRANCHES = <?= json_encode($partner_branches ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var partner = document.getElementById('rma-partner');
  var select  = document.getElementById('rma-branch');
  if (!partner || !select) return;

  var placeholder = select.options[0] ? select.options[0].textContent : '';

  function syncBranches() {
    var pid  = parseInt(partner.value, 10) || 0;
    var mine = BRANCHES.filter(function (b) { return parseInt(b.partner_id, 10) === pid; });

    select.innerHTML = '';
    var first = document.createElement('option');
    first.value = '';
    first.textContent = placeholder;
    select.appendChild(first);

    mine.forEach(function (b) {
      var o = document.createElement('option');
      o.value = b.id;
      o.textContent = b.city ? b.name + ' - ' + b.city : b.name;
      select.appendChild(o);
    });

  }

  partner.addEventListener('change', syncBranches);
  syncBranches();
})();
</script>
