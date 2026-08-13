<?php defined('RMS') or die('Direct access not permitted'); ?>

<!-- Same width tier as the partners list and its Novi partner card, so the
     card doesn't visibly narrow when you open a partner. Was a hardcoded
     720px, which predated the --w-* tiers. -->
<div style="padding:1.5rem;max-width:var(--w-content);">

  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('partners.details') ?></h2>
    <form method="POST" action="/partners/<?= (int)$partner['id'] ?>/update">
      <?= csrf_field() ?>
      <!-- Fixed 4-column rows in the same field order as Novi partner, so the
           card doesn't rearrange itself when you open a partner. The default
           form-grid is auto-fit, which at this width spread the fields over six
           ragged columns. -->
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('partners.company') ?> *</label><input type="text" name="name" required value="<?= htmlspecialchars($partner['name']) ?>"></div>
        <div class="field"><label><?= __('partners.contact_person') ?></label><input type="text" name="contact_person" value="<?= htmlspecialchars($partner['contact_person'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone" value="<?= htmlspecialchars($partner['phone'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" value="<?= htmlspecialchars($partner['email'] ?? '') ?>"></div>
      </div>
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address" value="<?= htmlspecialchars($partner['address'] ?? '') ?>"></div>
        <div class="field"><label><?= __('partners.zip_code') ?></label><input type="text" name="zip_code" value="<?= htmlspecialchars($partner['zip_code'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city" value="<?= htmlspecialchars($partner['city'] ?? '') ?>"></div>
        <!-- Country was missing here while update() still wrote $_POST['country'],
             so saving a partner silently cleared whatever was set on create. -->
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country" value="<?= htmlspecialchars($partner['country'] ?? '') ?>"></div>
      </div>
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('partners.tax_id') ?></label><input type="text" name="tax_id" value="<?= htmlspecialchars($partner['tax_id'] ?? '') ?>"></div>
        <div class="field"><label><?= __('ship.default_courier') ?></label>
          <select name="default_courier_id" class="custom-select">
            <option value=""><?= __('ship.no_courier') ?></option>
            <?php foreach (($couriers ?? []) as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($partner['default_courier_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Same one-line, non-resizable 40px treatment as Novi partner. -->
        <div class="field" style="grid-column:span 2;">
          <label><?= __('label.notes') ?></label>
          <textarea name="notes" rows="1"
                    style="height:40px;min-height:40px;padding:10px;line-height:18px;resize:none;overflow:auto;"><?= htmlspecialchars($partner['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <input type="checkbox" id="notify_customer" name="notify_customer" value="1"
               <?= ($partner['notify_customer'] ?? 1) ? 'checked' : '' ?>>
        <label for="notify_customer" style="font-size:13px;margin-bottom:0;"><?= __('partners.notify_customer') ?></label>
      </div>
      <p style="font-size:12px;color:var(--text-muted);margin:0 0 16px 24px;"><?= __('partners.notify_customer_hint') ?></p>

      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= $partner['is_active'] ? 'checked' : '' ?>>
        <label for="is_active" style="font-size:13px;margin-bottom:0;"><?= __('label.active') ?></label>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <a href="/partners" class="btn"><?= __('btn.cancel') ?></a>
        <?php if (can('partners', 'edit') && $rma_count === 0): ?>
          <!-- formaction, not a nested <form>: the HTML parser discards a form
               inside a form, so this button used to submit the outer form and
               silently *save* the partner instead of deleting it. -->
          <button type="submit" class="btn btn-danger"
                  formaction="/partners/<?= (int)$partner['id'] ?>/delete"
                  data-confirm="<?= __('msg.confirm_delete') ?>"><?= __('partners.delete') ?></button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;">
      <?= __('partners.users') ?>
      <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:6px;"><?= count($users) ?></span>
    </h2>
    <?php if (empty($users)): ?>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px;"><?= __('partners.no_users') ?></p>
    <?php else: ?>
      <table class="data-table" style="margin-bottom:1rem;">
        <thead>
          <tr>
            <th><?= __('label.name') ?></th>
            <th><?= __('label.email') ?></th>
            <th><?= __('label.type') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge" style="background:<?= $u['role']==='admin'?'#eeedfe':'var(--bg-subtle)' ?>;color:<?= $u['role']==='admin'?'#3c3489':'var(--text-secondary)' ?>;">
                  <?= htmlspecialchars($u['role']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>


  <!-- Branches (poslovnice) — the partner's own offices. Recorded as rows so
       RMAs can be reported per branch. -->
  <div class="card" style="margin-bottom:1.5rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;">
      <?= __('partners.branches') ?>
    </h2>

    <?php if (empty($branches)): ?>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:1rem;"><?= __('partners.no_branches') ?></p>
    <?php else: ?>
      <table class="data-table" style="margin-bottom:1rem;">
        <thead>
          <tr>
            <th><?= __('label.name') ?></th>
            <th><?= __('label.city') ?></th>
            <th><?= __('label.phone') ?></th>
            <th style="text-align:right;"><?= __('label.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($branches as $b): ?>
            <tr>
              <td style="font-weight:500;"><?= htmlspecialchars($b['name']) ?></td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($b['city'] ?? '—') ?></td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($b['phone'] ?? '—') ?></td>
              <td style="text-align:right;">
                <!-- Edit opens the popup; Delete lives in there, so the list
                     cannot destroy a branch with one stray click. -->
                <button type="button" class="btn-link"
                        onclick='editBranch(<?= htmlspecialchars(json_encode([
                          "id"    => (int)$b["id"],
                          "name"  => $b["name"],
                          "city"  => $b["city"],
                          "phone" => $b["phone"],
                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)'><?= __('btn.edit') ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if (can('partners', 'edit')): ?>
      <form method="POST" action="/partners/<?= (int)$partner['id'] ?>/branches/store">
        <?= csrf_field() ?>
        <div class="form-grid" style="grid-template-columns:repeat(3,1fr)">
          <div class="field"><label><?= __('label.name') ?></label><input type="text" name="branch_name" required></div>
          <div class="field"><label><?= __('label.city') ?></label><input type="text" name="branch_city"></div>
          <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="branch_phone"></div>
        </div>
        <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('partners.branch_add') ?></button>
      </form>
    <?php endif; ?>
  </div>


  <?php if (can('partners', 'edit')): ?>
  <!-- Branch editor. Delete lives in here rather than in the list, so removing
       a branch takes opening it first — the list row is one click from data
       that RMAs are counted against. -->
  <div id="branch-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
    <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;">
      <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('partners.branch_edit') ?></h2>
      <form id="branch-update-form" method="POST" action="/partners/<?= (int)$partner['id'] ?>/branches/update">
        <?= csrf_field() ?>
        <input type="hidden" name="branch_id" id="be-id">
        <div class="form-grid" style="grid-template-columns:repeat(3,1fr)">
          <div class="field"><label><?= __('label.name') ?> *</label><input type="text" name="branch_name" id="be-name" required></div>
          <div class="field"><label><?= __('label.city') ?></label><input type="text" name="branch_city" id="be-city"></div>
          <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="branch_phone" id="be-phone"></div>
        </div>
      </form>
      <div style="display:flex;gap:8px;align-items:center;">
        <button type="submit" form="branch-update-form" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save_changes') ?></button>
        <button type="button" class="btn" style="min-width:100px;"
                onclick="document.getElementById('branch-edit-modal').style.display='none'"><?= __('btn.cancel') ?></button>
        <form method="POST" action="/partners/<?= (int)$partner['id'] ?>/branches/delete"
              style="margin-left:auto;" data-confirm="<?= __('partners.branch_remove_confirm') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="branch_id" id="bd-id">
          <button type="submit" class="btn btn-danger" style="min-width:100px;"><?= __('btn.delete') ?></button>
        </form>
      </div>
    </div>
  </div>

  <script>
  function editBranch(b) {
    document.getElementById('be-id').value    = b.id;
    document.getElementById('be-name').value  = b.name  || '';
    document.getElementById('be-city').value  = b.city  || '';
    document.getElementById('be-phone').value = b.phone || '';
    document.getElementById('bd-id').value    = b.id;
    document.getElementById('branch-edit-modal').style.display = 'flex';
  }
  document.getElementById('branch-edit-modal').addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
  });
  </script>
  <?php endif; ?>

</div>
