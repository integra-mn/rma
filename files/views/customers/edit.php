<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:640px;">

  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:1.5rem;">
    <form method="POST" action="/customers/<?= (int)$customer['id'] ?>/update">
      <?= csrf_field() ?>
      <?php $clang = $customer['lang'] ?? 'me'; ?>
      <!-- Same field order as the add form; two columns because this is a
           narrow detail page, not the full-width list screen. -->
      <div class="form-grid" style="grid-template-columns:repeat(2,1fr)">
        <div class="field"><label><?= __('label.name') ?></label><input type="text" name="name" required value="<?= htmlspecialchars($customer['name']) ?>"></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="text" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>"></div>
        <div class="field">
          <label><?= __('customers.lang') ?></label>
          <select name="lang" class="custom-select">
            <option value="me" <?= $clang === 'me' ? 'selected' : '' ?>><?= __('lang.me') ?></option>
            <option value="en" <?= $clang === 'en' ? 'selected' : '' ?>><?= __('lang.en') ?></option>
          </select>
        </div>
        <div class="field"><label><?= __('label.address') ?></label><input type="text" name="address" value="<?= htmlspecialchars($customer['address'] ?? '') ?>"></div>
        <div class="field"><label><?= __('customers.zip_code') ?></label><input type="text" name="zip_code" value="<?= htmlspecialchars($customer['zip_code'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.city') ?></label><input type="text" name="city" value="<?= htmlspecialchars($customer['city'] ?? '') ?>"></div>
        <div class="field"><label><?= __('label.country') ?></label><input type="text" name="country" value="<?= htmlspecialchars($customer['country'] ?? 'Montenegro') ?>"></div>
      </div>
      <p style="font-size:12px;color:var(--text-muted);margin:-6px 0 12px;"><?= __('customers.lang_hint') ?></p>
      <div class="field">
        <label><?= __('label.notes') ?></label>
        <textarea name="notes" rows="1" style="height:40px;min-height:40px;padding:10px;line-height:18px;resize:none;overflow:auto;"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save_changes') ?></button>
        <a href="/customers" class="btn"><?= __('btn.cancel') ?></a>
        <?php if (can('customers', 'edit') && $rma_count === 0): ?>
          <form method="POST" action="/customers/<?= (int)$customer['id'] ?>/delete"
                data-confirm="<?= __('msg.confirm_delete') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger"><?= __('customers.delete') ?></button>
          </form>
        <?php endif; ?>
      </div>
    </form>
  </div>
  <p style="font-size:13px;color:var(--text-muted);">
    <?= __('customers.rma_count', ['count' => $rma_count]) ?>
  </p>
</div>
