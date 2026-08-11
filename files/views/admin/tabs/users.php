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
        <!-- Poslovnica, staff version: one of OUR service points. Swapped for
             the partner pair below when the role is Partner, so the field
             always answers the same question — which branch does this person
             sit in — with the list that is true for them. -->
        <div class="field" id="a-loc-wrap">
          <label><?= __('label.location') ?></label>
          <select name="location_id" id="a-location" class="custom-select">
            <option value=""><?= __('users.no_location') ?></option>
            <?php foreach ($locations as $l): ?>
              <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="a-partner-wrap" style="display:none;">
          <label><?= __('users.partner') ?></label>
          <select name="partner_id" id="a-partner" class="custom-select">
            <option value=""><?= __('users.select_partner') ?></option>
            <?php foreach ($partners as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="a-branch-wrap" style="display:none;">
          <label><?= __('partners.branch') ?></label>
          <select name="partner_branch_id" id="a-branch" class="custom-select">
            <option value=""><?= __('users.no_branch') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.role') ?></label>
          <select name="role" id="a-role" class="custom-select">
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
          <select name="access_scope" id="a-scope" class="custom-select">
            <option value="lan"><?= __('users.access_lan') ?></option>
            <option value="any"><?= __('users.access_any') ?></option>
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
          <?php
            // One column, one question: which branch does this person sit in.
            // Integra staff show our service point; partner staff show their own
            // poslovnica, prefixed with the partner so the list stays readable.
            if (($u['role'] ?? '') === 'partner') {
              $where = $u['branch_name']
                     ? ($u['partner_name'] ? $u['partner_name'] . ' - ' . $u['branch_name'] : $u['branch_name'])
                     : ($u['partner_name'] ?: '—');
            } else {
              $where = $u['location_name'] ?: '—';
            }
          ?>
          <td style="color:var(--text-secondary);"><?= htmlspecialchars($where) ?></td>
          <td style="color:var(--text-muted);"><?= strtoupper($u['lang'] ?? 'en') ?></td>
          <td style="text-align:center;">
            <span class="badge" style="background:<?= $u['is_active']?'var(--accent-bg)':'var(--bg-subtle)' ?>;color:<?= $u['is_active']?'var(--accent-text)':'var(--text-muted)' ?>;">
              <?= $u['is_active'] ? __('label.active') : __('label.disabled') ?>
            </span>
            <?php if (!empty($u['require_2fa'])): ?>
              <span class="badge" title="<?= __('users.require_2fa') ?>" style="background:var(--bg-subtle);color:var(--text-secondary);margin-left:4px;">2FA</span>
            <?php endif; ?>
            <?php if (!empty($u['totp_confirmed_at'])): ?>
              <span class="badge" title="<?= __('profile.totp') ?>" style="background:var(--bg-subtle);color:#1D9E75;margin-left:4px;">APP</span>
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
            <?php
              // Only the fields the edit form actually uses. json_encode($u)
              // shipped the whole row into this attribute — including
              // password_hash and, worse, totp_secret, which is the live 2FA
              // shared secret and would let anyone reading the page source
              // generate valid codes for every account.
              $edit_data = [
                'id'           => (int)$u['id'],
                'name'         => $u['name'],
                'email'        => $u['email'],
                'phone'        => $u['phone'],
                'role'         => $u['role'],
                'location_id'  => $u['location_id'],
                'partner_id'   => $u['partner_id'] ?? null,
                'branch_id'    => $u['branch_id'] ?? null,
                'lang'         => $u['lang'],
                'is_active'    => (int)$u['is_active'],
                'require_2fa'  => (int)$u['require_2fa'],
                'access_scope' => $u['access_scope'] ?? 'any',
              ];
            ?>
            <button onclick="editUser(<?= htmlspecialchars(json_encode($edit_data)) ?>)" class="btn-link"><?= __('btn.edit') ?></button>
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
      <!-- Field order set by Rajo: identity, then placement, then security. -->
      <div class="form-grid" style="">
        <div class="field"><label><?= __('users.full_name') ?></label><input type="text" name="name" id="e-name" required></div>
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
        <div class="field"><label><?= __('label.phone') ?></label><input type="tel" name="phone" id="e-phone" required></div>
        <div class="field"><label><?= __('label.email') ?></label><input type="email" name="email" id="e-email" required></div>
        <!-- Same swap as the add form: Integra location for staff, partner +
             poslovnica for partner-side people. -->
        <div class="field" id="e-loc-wrap">
          <label><?= __('label.location') ?></label>
          <select name="location_id" id="e-location" class="custom-select">
            <option value=""><?= __('users.no_location') ?></option>
            <?php foreach ($locations as $l): ?>
              <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="e-partner-wrap" style="display:none;">
          <label><?= __('users.partner') ?></label>
          <select name="partner_id" id="e-partner" class="custom-select">
            <option value=""><?= __('users.select_partner') ?></option>
            <?php foreach ($partners as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="e-branch-wrap" style="display:none;">
          <label><?= __('partners.branch') ?></label>
          <select name="partner_branch_id" id="e-branch" class="custom-select">
            <option value=""><?= __('users.no_branch') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('nav.language') ?></label>
          <select name="lang" id="e-lang" class="custom-select">
            <option value="en"><?= __('users.lang_en') ?></option>
            <option value="me"><?= __('users.lang_me') ?></option>
          </select>
        </div>
        <div class="field">
          <label><?= __('users.reset_password') ?></label>
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
        <div class="field">
          <label><?= __('label.active') ?></label>
          <select name="is_active" id="e-active" class="custom-select">
            <option value="1"><?= __('label.yes') ?></option>
            <option value="0"><?= __('label.no') ?></option>
          </select>
        </div>
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
  document.getElementById('e-active').value   = (u.is_active == 1) ? '1' : '0';
  document.getElementById('e-2fa').value      = (u.require_2fa == 1) ? '1' : '0';
  document.getElementById('e-scope').value    = (u.access_scope === 'lan') ? 'lan' : 'any';
  // Partner-side people: their partner, and the branch within it. Filled
  // before the rebuild below so the custom-select shows the right label.
  if (editBinder) {
    editBinder.partner.value = u.partner_id || '';
    editBinder.fillBranches(u.branch_id || null);
    editBinder.applyRole();
  }
  // Refresh the custom-select buttons to reflect the values we just set.
  ['e-lang','e-location','e-role','e-2fa','e-scope','e-active','e-partner'].forEach(function(id){
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

// New accounts: staff default to LAN-only, partners to anywhere. Mirrors
// default_access_scope() in helpers/auth.php — the admin sees the safe default
// rather than having to remember it. Still freely changeable before saving.
(function () {
  var role  = document.getElementById('a-role');
  var scope = document.getElementById('a-scope');
  if (!role || !scope) return;

  role.addEventListener('change', function () {
    scope.value = (role.value === 'partner') ? 'any' : 'lan';
    if (scope._customRebuild) scope._customRebuild();
  });
})();

// ── Partner / poslovnica fields ─────────────────────────────────
//
// "Which branch does this person sit in" is one question with two different
// answers depending on who they work for: an Integra service point for our own
// staff, one of the partner's own offices for theirs. So the form shows one or
// the other, never both, and the branch list is narrowed to the chosen partner
// — never the full set across all partners.
var ALL_BRANCHES = <?= json_encode($all_branches ?? [], JSON_UNESCAPED_UNICODE) ?>;

function branchBinder(prefix) {
  var role    = document.getElementById(prefix + '-role');
  var locWrap = document.getElementById(prefix + '-loc-wrap');
  var pWrap   = document.getElementById(prefix + '-partner-wrap');
  var bWrap   = document.getElementById(prefix + '-branch-wrap');
  var partner = document.getElementById(prefix + '-partner');
  var branch  = document.getElementById(prefix + '-branch');
  if (!role || !locWrap || !pWrap || !bWrap || !partner || !branch) return null;

  var placeholder = branch.options[0] ? branch.options[0].textContent : '';

  function fillBranches(selected) {
    var pid  = parseInt(partner.value, 10) || 0;
    var mine = ALL_BRANCHES.filter(function (b) { return parseInt(b.partner_id, 10) === pid; });

    branch.innerHTML = '';
    var first = document.createElement('option');
    first.value = '';
    first.textContent = placeholder;
    branch.appendChild(first);

    mine.forEach(function (b) {
      var o = document.createElement('option');
      o.value = b.id;
      o.textContent = b.city ? b.name + ' - ' + b.city : b.name;
      if (selected && parseInt(selected, 10) === parseInt(b.id, 10)) o.selected = true;
      branch.appendChild(o);
    });
    if (branch._customRebuild) branch._customRebuild();
  }

  function applyRole() {
    var isPartner = role.value === 'partner';
    locWrap.style.display = isPartner ? 'none' : '';
    pWrap.style.display   = isPartner ? '' : 'none';
    bWrap.style.display   = isPartner ? '' : 'none';
  }

  role.addEventListener('change', applyRole);
  partner.addEventListener('change', function () { fillBranches(null); });

  return { applyRole: applyRole, fillBranches: fillBranches, partner: partner };
}

var addBinder  = branchBinder('a');
var editBinder = branchBinder('e');
if (addBinder) addBinder.applyRole();
</script>
