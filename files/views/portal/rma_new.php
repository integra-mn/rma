<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:var(--w-form);">

  <?php if ($error ?? null): ?>
    <div class="alert alert-danger" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="/portal/rma/store" autocomplete="off">
    <?= csrf_field() ?>

    <!-- Customer -->
    <div class="card" style="margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;">
        <?= __('portal.customer_heading') ?>
      </h2>
      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('portal.full_name') ?> *</label>
          <input type="text" name="cust_name" required
                 value="<?= htmlspecialchars($old['cust_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label><?= __('label.phone') ?></label>
          <input type="text" name="cust_phone"
                 value="<?= htmlspecialchars($old['cust_phone'] ?? '') ?>">
        </div>
        <div class="field">
          <label><?= __('label.email') ?></label>
          <input type="email" name="cust_email"
                 value="<?= htmlspecialchars($old['cust_email'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Device -->
    <div class="card" style="margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('rma.device') ?></h2>
      <div class="form-grid" style="">
        <div class="field">
          <label><?= __('reports.brand') ?> *</label>
          <select name="brand_id" id="brand_select" required>
            <option value="">— <?= __('rma.select_brand') ?> —</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?= (int)$b['id'] ?>"
                      <?= (int)($old['brand_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('reports.model') ?> *</label>
          <select name="model_id" id="model_select" required>
            <option value="">— <?= __('rma.select_brand_first') ?> —</option>
          </select>
        </div>
        <div class="field">
          <label><?= __('rma.serial_number') ?>
            <span style="font-size:11px;color:var(--text-muted);"><?= __('portal.serial_hint') ?></span>
          </label>
          <input type="text" name="serial_number"
                 value="<?= htmlspecialchars($old['serial_number'] ?? '') ?>">
        </div>
        <div class="field">
          <label>IMEI
            <span style="font-size:11px;color:var(--text-muted);"><?= __('portal.imei_hint') ?></span>
          </label>
          <input type="text" name="imei" inputmode="numeric"
                 value="<?= htmlspecialchars($old['imei'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Issue -->
    <div class="card" style="margin-bottom:1rem;">
      <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('portal.issue') ?></h2>
      <div class="field" style="margin-bottom:12px;">
        <label><?= __('rma.complaint') ?> *</label>
        <textarea name="complaint" rows="4" required
                  placeholder="<?= __('portal.complaint_placeholder') ?>"><?= htmlspecialchars($old['complaint'] ?? '') ?></textarea>
      </div>
      <div class="field" style="margin-bottom:24px;">
        <label style="margin-bottom:8px;"><?= __('portal.accessories_label') ?></label>
        <?php
        $acc_options = [
          'battery'          => __('portal.acc_battery'),
          'charger'          => __('portal.acc_charger'),
          'sim_card'         => __('portal.acc_sim_card'),
          'headphones'       => __('portal.acc_headphones'),
          'packaging'        => __('portal.acc_packaging'),
          'memory_card'      => __('portal.acc_memory_card'),
          'protective_case'  => __('portal.acc_protective_case'),
          'purchase_receipt' => __('portal.acc_purchase_receipt'),
        ];
        $acc_selected = is_array($old['accessories'] ?? null) ? $old['accessories'] : [];
        ?>
        <div style="display:flex;flex-wrap:wrap;gap:10px;" id="acc-buttons">
          <?php foreach ($acc_options as $key => $label): $on = in_array($key, $acc_selected, true); ?>
            <button type="button" id="acc-btn-<?= $key ?>"
                    data-key="<?= $key ?>" data-active="<?= $on ? '1' : '0' ?>"
                    onclick="toggleAcc('<?= $key ?>')"
                    style="display:inline-flex;align-items:center;padding:7px 12px;font-size:13px;border-radius:8px;cursor:pointer;line-height:1;box-sizing:border-box;min-height:34px;user-select:none;
                           border:0.5px solid <?= $on ? 'var(--accent,#1D9E75)' : 'var(--border,#d3d1c7)' ?>;
                           background:<?= $on ? 'var(--accent-bg,#e1f5ee)' : '#fff' ?>;
                           color:<?= $on ? 'var(--accent-text,#085041)' : 'var(--text-secondary,#5f5e5a)' ?>;">
              <?= $label ?>
            </button>
          <?php endforeach; ?>
        </div>
        <!-- Hidden inputs — rebuilt by toggleAcc() on click. Seeded here so
             sticky-form state survives a validation-error round trip. -->
        <div id="acc-inputs">
          <?php foreach ($acc_selected as $k): if (isset($acc_options[$k])): ?>
            <input type="hidden" name="accessories[]" value="<?= htmlspecialchars($k) ?>">
          <?php endif; endforeach; ?>
        </div>
      </div>
      <div class="field" style="margin-bottom:24px;">
        <label><?= __('portal.other_accessories') ?></label>
        <input type="text" name="accessories_other"
               placeholder="<?= __('portal.other_acc_placeholder') ?>"
               value="<?= htmlspecialchars($old['accessories_other'] ?? '') ?>">
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" id="is_warranty" name="is_warranty" value="1"
               <?= !empty($old['is_warranty']) ? 'checked' : '' ?>>
        <label for="is_warranty" style="font-size:13px;margin-bottom:0;"><?= __('portal.is_warranty') ?></label>
      </div>
    </div>

    <div style="display:flex;gap:8px;">
      <button type="submit" class="btn btn-primary"><?= __('portal.submit_rma') ?></button>
      <a href="/portal/rma" class="btn"><?= __('btn.cancel') ?></a>
    </div>
  </form>

</div>

<script>
/*
 * Brand → Model cascading dropdown.
 *
 * We pre-load all active models keyed by brand_id once, then rebuild the
 * model <select> whenever the brand changes. No AJAX, no flicker.
 */
(function () {
  const MODELS_BY_BRAND = <?= json_encode(array_reduce(
      $models,
      function ($acc, $m) {
          $acc[(int)$m['brand_id']][] = ['id' => (int)$m['id'], 'name' => $m['name']];
          return $acc;
      },
      []
  ), JSON_UNESCAPED_UNICODE) ?>;

  const OLD_MODEL = <?= (int)($old['model_id'] ?? 0) ?>;

  const brandSel = document.getElementById('brand_select');
  const modelSel = document.getElementById('model_select');

  function rebuildModels() {
    const bid = parseInt(brandSel.value, 10);
    const list = MODELS_BY_BRAND[bid] || [];
    modelSel.innerHTML = '';
    if (!bid) {
      modelSel.innerHTML = '<option value="">— pick brand first —</option>';
      return;
    }
    if (list.length === 0) {
      modelSel.innerHTML = '<option value="">— no models yet —</option>';
      return;
    }
    const head = document.createElement('option');
    head.value = ''; head.textContent = '— select model —';
    modelSel.appendChild(head);
    list.forEach(m => {
      const o = document.createElement('option');
      o.value = m.id; o.textContent = m.name;
      if (m.id === OLD_MODEL) o.selected = true;
      modelSel.appendChild(o);
    });
  }

  brandSel.addEventListener('change', rebuildModels);
  // Populate on load so sticky-form submissions retain the model too.
  rebuildModels();
})();

/*
 * Accessory toggle buttons. Each click flips data-active, restyles the
 * button, and rebuilds the hidden <input name="accessories[]"> set so the
 * form POSTs the current selection.
 */
function toggleAcc(key) {
  const btn    = document.getElementById('acc-btn-' + key);
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
</script>
