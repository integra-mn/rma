<?php defined('RMS') or die(); ?>
  <!-- Add + Search -->
  <div style="display:flex;gap:8px;margin-bottom:1.25rem;align-items:center;flex-wrap:wrap;">
    <button type="button" class="btn btn-primary" style="min-width:140px;"
            onclick="document.getElementById('add-form').style.display=document.getElementById('add-form').style.display==='none'?'block':'none'">
      <?= __('users.add') ?>
    </button>
    <div style="width:300px;flex-shrink:0;">
      <input type="text" id="user-search" placeholder="<?= __('users.search') ?>"
             oninput="filterUsers(this.value)" style="width:100%;">
    </div>
  </div>
  <div id="add-form" style="display:none;margin-bottom:1.25rem;" class="card">
    <h2 style="font-size:15px;font-weight:500;margin-bottom:1rem;"><?= __('users.new') ?></h2>
    <form method="POST" action="/admin/user/store">
      <?= csrf_field() ?>
      <!-- Row 1 -->
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field"><label><?= __('users.full_name') ?></label><input type="text" name="name" required></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="tel" name="phone" required></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" required></div>
        <div class="field">
          <label><?= __('nav.language') ?></label>
          <select name="lang" class="custom-select">
            <option value="en"><?= __('users.lang_en') ?></option>
            <option value="me"><?= __('users.lang_me') ?></option>
          </select>
        </div>
      </div>
      <!-- Row 2 -->
      <div class="form-grid" style="grid-template-columns:repeat(4,1fr)">
        <div class="field">
          <label><?= __('label.location') ?></label>
          <select name="location_id" class="custom-select">
            <option value=""><?= __('users.no_location') ?></option>
            <?php foreach ($locations as $l): ?>
              <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.role') ?></label>
          <select name="role" class="custom-select">
            <option value="technician"><?= __('users.role_technician') ?></option>
            <option value="reception"><?= __('users.role_reception') ?></option>
            <option value="partner"><?= __('users.role_partner') ?></option>
            <option value="admin"><?= __('users.role_admin') ?></option>
            <option value="super_admin"><?= __('users.role_super_admin') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.initial_password') ?></label>
          <input type="password" name="password" required autocomplete="new-password">
        </div>
        <div class="field">
          <label>2FA</label>
          <select name="require_2fa" class="custom-select">
            <option value="0"><?= __('label.no') ?></option>
            <option value="1"><?= __('label.yes') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.access_scope') ?></label>
          <select name="access_scope" class="custom-select">
            <option value="any"><?= __('users.access_any') ?></option>
            <option value="lan"><?= __('users.access_lan') ?></option>
          </select>
        </div>
      </div>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:1rem;"><?= __('users.pw_change_hint') ?></p>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('add-form').style.display='none'"><?= __('btn.cancel') ?></button>
      </div>
    </form>
  </div>

  <!-- Users table -->
  <table class="data-table" id="user-table">
    <thead>
      <tr>
        <th><?= __('label.name') ?></th>
        <th><?= __('label.email') ?></th>
        <th><?= __('users.role') ?></th>
        <th><?= __('label.location') ?></th>
        <th><?= __('nav.language') ?></th>
        <th style="text-align:center;"><?= __('label.status') ?></th>
        <th><?= __('users.last_login') ?></th>
        <th style="text-align:right;"><?= __('label.actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>"
            data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>">
          <td style="font-weight:500;"><?= htmlspecialchars($u['name']) ?></td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php $role_colors = [
              'super_admin' => '#6b2417',
              'admin'       => 'var(--text-secondary)',
              'reception'   => '#0a6b7a',
              'technician'  => 'var(--text-secondary)',
              'partner'     => '#5a3a8a',
              // Legacy fallbacks in case a pre-rename row still lingers
              'main_admin'  => 'var(--text-secondary)',
              'lite_admin'  => '#185fa5',
            ]; ?>
            <span class="badge" style="background:var(--bg-subtle);color:<?= $role_colors[$u['role']] ?? 'var(--text-muted)' ?>;">
              <?= role_label($u['role']) ?>
            </span>
          </td>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($u['location_name'] ?? '—') ?></td>
          <td style="color:var(--text-muted);"><?= strtoupper($u['lang'] ?? 'en') ?></td>
          <td style="text-align:center;">
            <span class="badge" style="background:<?= $u['is_active']?'var(--accent-bg)':'var(--bg-subtle)' ?>;color:<?= $u['is_active']?'var(--accent-text)':'var(--text-muted)' ?>;">
              <?= $u['is_active'] ? __('label.active') : __('label.disabled') ?>
            </span>
            <?php if (!empty($u['require_2fa'])): ?>
              <span class="badge" title="<?= __('users.require_2fa') ?>" style="background:var(--bg-subtle);color:var(--text-secondary);margin-left:4px;">2FA</span>
            <?php endif; ?>
            <?php if (($u['access_scope'] ?? 'any') === 'lan'): ?>
              <!-- Only the restriction is badged; "anywhere" is the default and
                   badging every row would just add noise. No badge = can sign in
                   from outside. -->
              <span class="badge" title="<?= __('users.access_lan') ?>" style="background:var(--bg-subtle);color:#0a6b7a;margin-left:4px;">LAN</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-muted);font-size:12px;">
            <?= format_datetime($u['last_login'], 'Never') ?>
          </td>
          <td style="text-align:right;">
            <button onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)" class="btn-link"><?= __('btn.edit') ?></button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<!-- Edit modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:50;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;margin:1rem;max-height:90vh;overflow-y:auto;">
    <h2 style="font-size:16px;font-weight:500;margin-bottom:1rem;"><?= __('users.edit') ?></h2>
    <form method="POST" action="/admin/user/update">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="e-id">
      <div class="form-grid" style="">
        <div class="field"><label><?= __('users.full_name') ?></label><input type="text" name="name" id="e-name" required></div>
        <div class="field"><label><?= __('label.phone') ?></label><input type="tel" name="phone" id="e-phone" required></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" id="e-email" required></div>
        <div class="field">
          <label><?= __('nav.language') ?></label>
          <select name="lang" id="e-lang" class="custom-select">
            <option value="en"><?= __('users.lang_en') ?></option>
            <option value="me"><?= __('users.lang_me') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('label.location') ?></label>
          <select name="location_id" id="e-location" class="custom-select">
            <option value=""><?= __('users.no_location') ?></option>
            <?php foreach ($locations as $l): ?>
              <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.role') ?></label>
          <select name="role" id="e-role" class="custom-select">
            <option value="technician"><?= __('users.role_technician') ?></option>
            <option value="reception"><?= __('users.role_reception') ?></option>
            <option value="partner"><?= __('users.role_partner') ?></option>
            <option value="admin"><?= __('users.role_admin') ?></option>
            <option value="super_admin"><?= __('users.role_super_admin') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.new_password') ?></label>
          <input type="password" name="password" autocomplete="new-password">
        </div>
        <div class="field">
          <label>2FA</label>
          <select name="require_2fa" id="e-2fa" class="custom-select">
            <option value="0"><?= __('label.no') ?></option>
            <option value="1"><?= __('label.yes') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.access_scope') ?></label>
          <select name="access_scope" id="e-scope" class="custom-select">
            <option value="any"><?= __('users.access_any') ?></option>
            <option value="lan"><?= __('users.access_lan') ?></option>
          </select>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:1rem;">
        <input type="checkbox" name="is_active" id="e-active" value="1">
        <label for="e-active" style="font-size:13px;margin-bottom:0;"><?= __('label.active') ?></label>
      </div>
      <div class="modal-actions" style="display:flex;gap:8px;align-items:center;">
        <button type="submit" class="btn btn-primary"><?= __('btn.save') ?></button>
        <button type="button" class="btn" onclick="document.getElementById('edit-modal').style.display='none'"><?= __('btn.cancel') ?></button>
        <button type="button" id="e-delete-btn" class="btn btn-danger" style="margin-left:auto;" onclick="deleteUserFromModal()"><?= __('btn.delete') ?></button>
      </div>
    </form>
    <!-- Delete action — separate form (can't nest inside the update form above). -->
    <form method="POST" action="/admin/user/delete" id="user-delete-form" style="display:none;">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="d-id">
    </form>
  </div>
</div>

<script>
function filterUsers(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#user-table tbody tr').forEach(row => {
    const match = !q || row.dataset.name.includes(q) || row.dataset.email.includes(q);
    row.style.display = match ? '' : 'none';
  });
}
var CURRENT_USER_ID = <?= (int)current_user_id() ?>;
function editUser(u) {
  document.getElementById('e-id').value       = u.id;
  document.getElementById('e-name').value     = u.name || '';
  document.getElementById('e-email').value    = u.email || '';
  document.getElementById('e-phone').value    = u.phone || '';
  document.getElementById('e-role').value     = u.role || 'technician';
  document.getElementById('e-location').value = u.location_id || '';
  document.getElementById('e-lang').value     = u.lang || 'en';
  document.getElementById('e-active').checked = u.is_active == 1;
  document.getElementById('e-2fa').value      = (u.require_2fa == 1) ? '1' : '0';
  document.getElementById('e-scope').value    = (u.access_scope === 'lan') ? 'lan' : 'any';
  // Refresh the custom-select buttons to reflect the values we just set.
  ['e-lang','e-location','e-role','e-2fa','e-scope'].forEach(function(id){
    var s = document.getElementById(id);
    if (s && s._customRebuild) s._customRebuild();
  });
  // Delete lives in this window now; hide it when editing your own account.
  document.getElementById('d-id').value = u.id;
  document.getElementById('e-delete-btn').style.display = (u.id == CURRENT_USER_ID) ? 'none' : '';
  document.getElementById('edit-modal').style.display = 'flex';
}
function deleteUserFromModal() {
  appConfirm(<?= json_encode(__('users.confirm_delete')) ?>, function () {
    document.getElementById('user-delete-form').submit();
  });
}
document.getElementById('edit-modal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
