<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:720px;">

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
      <div class="form-grid" style="">
        <div class="field"><label><?= __('partners.company') ?> *</label><input type="text" name="name" required value="<?= htmlspecialchars($partner['name']) ?>"></div>
        <div class="field"><label><?= __('partners.tax_id') ?></label><input type="text" name="tax_id" value="<?= htmlspecialchars($partner['tax_id'] ?? '') ?>"></div>
        <div class="field"><label><?= __('partners.contact_person') ?></label><input type="text" name="contact_person" value="<?= htmlspecialchars($partner['contact_person'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" value="<?= htmlspecialchars($partner['email'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone" value="<?= htmlspecialchars($partner['phone'] ?? '') ?>"></div>
        <div class="field"><label><?= __('partners.branch') ?></label><input type="text" name="branch" value="<?= htmlspecialchars($partner['branch'] ?? '') ?>"></div>
        <div class="field"><label><?= __('partners.zip_code') ?></label><input type="text" name="zip_code" value="<?= htmlspecialchars($partner['zip_code'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city" value="<?= htmlspecialchars($partner['city'] ?? '') ?>"></div>
        <div class="field"><label><?= __('ship.default_courier') ?></label>
          <select name="default_courier_id" class="custom-select">
            <option value=""><?= __('ship.no_courier') ?></option>
            <?php foreach (($couriers ?? []) as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($partner['default_courier_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address" value="<?= htmlspecialchars($partner['address'] ?? '') ?>"></div>
      <div class="field"><label><?= __('label.notes') ?></label><textarea name="notes" rows="2"><?= htmlspecialchars($partner['notes'] ?? '') ?></textarea></div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= $partner['is_active'] ? 'checked' : '' ?>>
        <label for="is_active" style="font-size:13px;margin-bottom:0;"><?= __('label.active') ?></label>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <a href="/partners" class="btn"><?= __('btn.cancel') ?></a>
        <?php if (can('partners', 'edit') && $rma_count === 0): ?>
          <form method="POST" action="/partners/<?= (int)$partner['id'] ?>/delete"
                data-confirm="<?= __('msg.confirm_delete') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><?= __('partners.delete') ?></button>
          </form>
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

</div>
