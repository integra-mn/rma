<?php defined('RMS') or die('Direct access not permitted'); ?>

<div style="padding:1.5rem;max-width:640px;">

  <?php if ($success ?? null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error ?? null): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Preferences -->
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('profile.preferences') ?></h2>
    <form method="POST" action="/profile/save">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preferences">

      <div class="form-grid" style="">
        <?php if (can('preferences', 'lang')): ?>
        <div class="field">
          <label><?= __('nav.language') ?></label>
          <select name="lang">
            <option value="en" <?= $user['lang'] === 'en' ? 'selected' : '' ?>>EN</option>
            <option value="me" <?= $user['lang'] === 'me' ? 'selected' : '' ?>>ME</option>
          </select>
        </div>
        <?php endif; ?>
        <?php if (can('preferences', 'theme')): ?>
        <div class="field">
          <label><?= __('nav.theme') ?></label>
          <select name="theme">
            <?php foreach (['midnight'=>__('profile.theme_light'),'ocean'=>__('profile.theme_blue'),'focus'=>__('profile.theme_contrast')] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $user['theme'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="field">
          <label><?= __('profile.phone_number') ?></label>
          <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+382 67 123 456">
        </div>
        <?php if (count($allowed_channels) > 1): ?>
        <div class="field">
          <label><?= __('profile.preferred_2fa') ?></label>
          <select name="preferred_2fa_channel">
            <?php foreach ($allowed_channels as $ch): ?>
              <option value="<?= htmlspecialchars($ch) ?>"
                <?= ($user['preferred_2fa_channel'] ?? $allowed_channels[0]) === $ch ? 'selected' : '' ?>>
                <?= match($ch) { 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', default => 'Email' } ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
          <div class="field">
            <label><?= __('profile.2fa_channel') ?></label>
            <p style="font-size:13px;padding:8px 0;color:var(--text-secondary);">
              <?= match($allowed_channels[0]) { 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', default => 'Email' } ?>
              <span style="font-size:12px;color:var(--text-muted);"> — <?= __('profile.set_by_admin') ?></span>
            </p>
          </div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save') ?></button>
    </form>
  </div>

  <!-- Change password -->
  <div class="card">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;"><?= __('profile.change_password') ?></h2>
    <form method="POST" action="/profile/save">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <div class="field">
        <label><?= __('profile.current_password') ?></label>
        <input type="password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label><?= __('profile.new_password') ?></label>
        <input type="password" name="new_password" required autocomplete="new-password">
      </div>
      <div class="field" style="margin-bottom:1rem;">
        <label><?= __('profile.confirm_password') ?></label>
        <input type="password" name="confirm_password" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save') ?></button>
    </form>
  </div>

  <!-- Authenticator app (TOTP) -->
  <?php
    $me_totp   = db_row('SELECT email, totp_secret, totp_confirmed_at FROM users WHERE id = ?', [current_user_id()]);
    $enrolled  = totp_is_enrolled($me_totp ?? []);
    // A secret generated but not yet confirmed lives in the session, so a
    // refresh doesn't hand out a different one mid-enrolment.
    $pending   = $_SESSION['totp_pending'] ?? null;
  ?>
  <div class="card" style="margin-top:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;">
      <?= __('profile.totp') ?>
    </h2>

    <?php if ($enrolled): ?>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:0.75rem;">
        ✅ <?= __('profile.totp_active', ['date' => format_date($me_totp['totp_confirmed_at'])]) ?>
      </p>
      <form method="POST" action="/profile/totp-disable"
            onsubmit="return false;" id="totp-disable-form">
        <?= csrf_field() ?>
        <button type="button" class="btn" style="min-width:120px;"
                onclick="appConfirm(<?= htmlspecialchars(json_encode(__('profile.totp_disable_confirm')), ENT_QUOTES) ?>, function(){ document.getElementById('totp-disable-form').onsubmit=null; document.getElementById('totp-disable-form').submit(); })">
          <?= __('profile.totp_disable') ?>
        </button>
      </form>

    <?php elseif ($pending): ?>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:0.75rem;">
        <?= __('profile.totp_scan_hint') ?>
      </p>
      <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
        <img src="<?= totp_qr_base64($pending, $me_totp['email'] ?? 'user', 200) ?>"
             alt="QR" width="200" height="200"
             style="border:0.5px solid var(--border);border-radius:8px;flex-shrink:0;">

        <div style="flex:1;min-width:228px;">
          <p style="font-size:12px;color:var(--text-muted);margin-bottom:4px;"><?= __('profile.totp_manual') ?></p>
          <code style="font-size:13px;letter-spacing:1px;word-break:break-all;"><?= htmlspecialchars($pending) ?></code>

          <form method="POST" action="/profile/totp-confirm" style="margin-top:10px;">
            <?= csrf_field() ?>
            <!-- Field and the two buttons share one width so the block lines
                 up: 2 x 110px + the 8px gap = 228px. Buttons flex to fill it,
                 so a longer translation cannot break the alignment. -->
            <div class="field" style="width:228px;max-width:100%;margin-bottom:8px;">
              <label><?= __('profile.totp_enter_code') ?></label>
              <input type="text" name="code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                     autocomplete="one-time-code" required
                     style="text-align:center;font-size:18px;letter-spacing:5px;">
            </div>
            <div style="display:flex;gap:8px;width:228px;max-width:100%;">
              <button type="submit" class="btn btn-primary" style="flex:1;margin-top:0;"><?= __('btn.confirm') ?></button>
              <a href="/profile/totp-cancel" class="btn" style="flex:1;text-align:center;margin-top:0;"><?= __('btn.cancel') ?></a>
            </div>
          </form>
        </div>
      </div>

    <?php else: ?>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:0.75rem;">
        <?= __('profile.totp_intro') ?>
      </p>
      <form method="POST" action="/profile/totp-start">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary" style="min-width:140px;"><?= __('profile.totp_setup') ?></button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Account info (read only).
       $user here comes from layout/header.php's current_user(), which only
       exposes session fields. Re-fetch the full row for admin-visible columns
       like last_login, created_at. -->
  <?php $acct = db_row('SELECT name, email, role, last_login, created_at FROM users WHERE id = ?', [current_user_id()]); ?>
  <div class="card" style="margin-top:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:0.75rem;"><?= __('profile.account') ?></h2>
    <table style="width:100%;font-size:13px;border-collapse:collapse;">
      <tr><td style="padding:5px 0;color:var(--text-muted);width:120px;"><?= __('label.name') ?></td><td><?= htmlspecialchars($acct['name'] ?? '') ?></td></tr>
      <tr><td style="padding:5px 0;color:var(--text-muted);"><?= __('label.email') ?></td><td><?= htmlspecialchars($acct['email'] ?? '') ?></td></tr>
      <tr><td style="padding:5px 0;color:var(--text-muted);"><?= __('profile.role') ?></td><td><?= htmlspecialchars(ucfirst(str_replace('_',' ',$acct['role'] ?? ''))) ?></td></tr>
      <tr><td style="padding:5px 0;color:var(--text-muted);"><?= __('profile.last_login') ?></td><td style="color:var(--text-muted);"><?= format_datetime($acct['last_login'] ?? null) ?></td></tr>
    </table>
  </div>

  <?php
  // ── Integrations: per-user vendor credentials ──
  // Gated by the preferences.integrations permission so partners never see it.
  // The tabs mirror the vendors enabled under Settings → Integrations; each
  // technician enters their own credentials, used when they submit repairs.
  if (can('preferences', 'integrations')):
    $uid = (int) $user['id'];
    $vendor_defs = [];

    // Apple GSX — available when its adapter is switched on.
    $apple = db_row(
        "SELECT v.id, a.is_active FROM vendors v
         JOIN vendor_adapters a ON a.vendor_id = v.id
         WHERE v.slug = 'apple' LIMIT 1"
    );
    if ($apple && (int)$apple['is_active'] === 1) {
        $vendor_defs['apple'] = ['id' => (int)$apple['id'], 'label' => 'Apple', 'fields' => [
            ['apple_id',   __('profile.apple_id'),     'text',   'you@integra.me'],
            ['auth_token', __('profile.bearer_token'), 'secret', ''],
            ['tech_id',    __('profile.tech_id'),      'text',   'ACiT / ACMT ID'],
        ]];
    }

    // TCL — available when enabled under Settings → Integrations.
    if ((string)setting('tcl_enabled', '0') === '1') {
        $tcl_id = (int) db_val("SELECT id FROM vendors WHERE slug = 'tcl' LIMIT 1");
        if ($tcl_id) {
            $vendor_defs['tcl'] = ['id' => $tcl_id, 'label' => 'TCL', 'fields' => [
                ['api_key', __('settings.api_key'), 'secret', ''],
                ['tech_id', __('profile.tech_id'),  'text',   ''],
            ]];
        }
    }
  ?>
  <div class="card" style="margin-bottom:1rem;">
    <h2 style="font-size:14px;font-weight:500;color:var(--text-secondary);margin-bottom:1rem;">
      <?= __('profile.integrations') ?>
    </h2>

    <?php if (!$vendor_defs): ?>
      <p style="font-size:13px;color:var(--text-muted);"><?= __('profile.integrations_none') ?></p>
    <?php else: ?>
      <div class="tab-bar">
        <?php $first = true; foreach ($vendor_defs as $slug => $vd): ?>
          <a href="#" class="tab<?= $first ? ' active' : '' ?>" data-vtab="<?= $slug ?>"
             onclick="return switchVendorTab(this, '<?= $slug ?>')"><?= htmlspecialchars($vd['label']) ?></a>
        <?php $first = false; endforeach; ?>
      </div>

      <?php $first = true; foreach ($vendor_defs as $slug => $vd):
          $row   = db_row('SELECT credentials, updated_at FROM user_vendor_credentials WHERE user_id = ? AND vendor_id = ?', [$uid, $vd['id']]);
          $creds = $row ? (json_decode((string)$row['credentials'], true) ?: []) : [];
      ?>
        <div class="vendor-panel" data-vpanel="<?= $slug ?>" style="<?= $first ? '' : 'display:none;' ?>">
          <form method="POST" action="/profile/save">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="vendor_credentials">
            <input type="hidden" name="vendor" value="<?= $slug ?>">

            <div class="form-grid" style="margin:0.75rem 0 1rem;grid-template-columns:1fr;max-width:60%;">
              <?php foreach ($vd['fields'] as [$fk, $flabel, $ftype, $fph]): ?>
                <div class="field"<?= $ftype === 'secret' ? ' style="grid-column:1/-1;"' : '' ?>>
                  <label><?= $flabel ?></label>
                  <?php if ($ftype === 'secret'): ?>
                    <input type="password" name="cred_<?= $fk ?>"
                           placeholder="<?= !empty($creds[$fk]) ? __('profile.token_keep') : htmlspecialchars($fph) ?>"
                           autocomplete="new-password">
                  <?php else: ?>
                    <input type="text" name="cred_<?= $fk ?>"
                           value="<?= htmlspecialchars($creds[$fk] ?? '') ?>"
                           placeholder="<?= htmlspecialchars($fph) ?>" autocomplete="off">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <button type="submit" class="btn btn-primary" style="min-width:100px;"><?= __('btn.save') ?></button>
              <?php if ($row): ?>
                <button type="submit" name="clear" value="1" class="btn"
                        data-confirm="<?= htmlspecialchars(__('profile.clear_confirm'), ENT_QUOTES) ?>"
                        style="min-width:100px;background:#fcebeb;color:#791f1f;border-color:#f09595;">
                  <?= __('profile.clear_credentials') ?>
                </button>
                <span style="font-size:12px;color:var(--text-muted);"><?= __('profile.gsx_saved') ?> <?= format_datetime($row['updated_at']) ?></span>
              <?php endif; ?>
            </div>
          </form>
        </div>
      <?php $first = false; endforeach; ?>

      <script>
      function switchVendorTab(el, slug) {
        document.querySelectorAll('[data-vtab]').forEach(function (t) { t.classList.toggle('active', t === el); });
        document.querySelectorAll('[data-vpanel]').forEach(function (p) {
          p.style.display = (p.getAttribute('data-vpanel') === slug) ? '' : 'none';
        });
        return false;
      }
      </script>
    <?php endif; ?>
  </div>
  <?php endif; /* can integrations */ ?>

</div>
