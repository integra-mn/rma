<?php defined('RMS') or die(); ?>

<?php
// Modules and their actions shown in the editable matrix. This mirrors
// PERMISSION_MATRIX in helpers/permissions.php (the single source of truth
// used by can() and the save handler); only the labels are added here.
// Ordered to match the sidebar, so finding a module here means looking in
// the same place you look in the app. `preferences` is last: it is the only
// entry with no sidebar section of its own.
$modules = [
    'rma'            => ['label' => __('nav.rma'),       'actions' => ['view', 'create', 'edit']],
    'repair'         => ['label' => __('nav.repairs'),   'actions' => ['view', 'create', 'edit']],
    'shipments'      => ['label' => __('nav.shipments'), 'actions' => ['view', 'create', 'edit']],
    'parts'          => ['label' => __('nav.parts'),     'actions' => ['view', 'create', 'edit', 'delete']],
    'devices'        => ['label' => __('nav.devices'),   'actions' => ['view', 'edit']],
    'partners'       => ['label' => __('nav.partners'),  'actions' => ['view', 'edit']],
    'customers'      => ['label' => __('nav.customers'), 'actions' => ['view', 'create', 'edit']],
    'invoicing'      => ['label' => __('nav.invoices'),  'actions' => ['view']],
    'reports'        => ['label' => __('nav.reports'),   'actions' => ['view']],
    'administration' => ['label' => __('nav.administration'), 'actions' => ['view', 'edit', 'users']],
    'settings'       => ['label' => __('nav.settings'),  'actions' => ['view', 'edit']],
    'preferences'    => ['label' => __('admin.perm_preferences'), 'actions' => ['theme', 'lang', 'integrations']],
];

// full = always full access (Super Admin only) — shown locked, not editable.
// Everyone else (including Admin now) pulls current grants from role_permissions.
$roles = [
    'super_admin' => ['label' => __('users.role_super_admin'), 'color' => '#6b2417', 'full' => true],
    'admin'       => ['label' => __('users.role_admin'),       'color' => '#854f0b', 'full' => false, 'perms' => role_permissions('admin')],
    'reception'   => ['label' => __('users.role_reception'),   'color' => '#0a6b7a', 'full' => false, 'perms' => role_permissions('reception')],
    'technician'  => ['label' => __('users.role_technician'),  'color' => '#1D9E75', 'full' => false, 'perms' => role_permissions('technician')],
    'partner'     => ['label' => __('users.role_partner'),     'color' => '#5a3a8a', 'full' => false, 'perms' => role_permissions('partner')],
];

// Must match permissions_save(), which is Super Admin only — otherwise the
// boxes look editable and every tick silently fails to save.
$can_edit  = is_super_admin();
?>

<!-- Editable permissions matrix -->
<form method="POST" action="/admin/permissions/save" id="perm-form">
  <?= csrf_field() ?>
  <div style="overflow-x:auto;">
  <table class="data-table">
    <thead>
      <tr>
        <th><?= __('admin.perm_module_action') ?></th>
        <?php foreach ($roles as $role): ?>
          <th style="text-align:center;white-space:nowrap;">
            <span><?= $role['label'] ?></span>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($modules as $mod => $m): ?>
        <tr>
          <td colspan="<?= count($roles) + 1 ?>"
              style="background:var(--bg-subtle);font-size:11px;font-weight:600;color:var(--text-muted);
                     text-transform:uppercase;letter-spacing:0.05em;padding:6px 12px;">
            <?= $m['label'] ?>
          </td>
        </tr>
        <?php foreach ($m['actions'] as $action): ?>
        <tr>
          <td style="color:var(--text-secondary);padding-left:1.5rem;"><?= __('admin.perm_action_' . $action) ?></td>
          <?php foreach ($roles as $code => $role): ?>
            <?php
            $key    = "{$mod}.{$action}";
            $locked = in_array($key, ROLE_LOCKED_PERMISSIONS[$code] ?? [], true);
            ?>
            <td style="text-align:center;">
              <?php if (!empty($role['full']) || $locked): ?>
                <!-- Always on, locked -->
                <input type="checkbox" checked disabled
                       title="<?= __('admin.perm_locked_title') ?>"
                       style="accent-color:var(--accent);cursor:not-allowed;width:16px;height:16px;">
              <?php else: ?>
                <?php $has = in_array($key, $role['perms']); ?>
                <input type="checkbox"
                       name="perm[<?= $code ?>][<?= $key ?>]" value="1"
                       <?= $has ? 'checked' : '' ?>
                       <?= $can_edit ? '' : 'disabled' ?>
                       style="accent-color:var(--accent);cursor:<?= $can_edit ? 'pointer' : 'not-allowed' ?>;width:16px;height:16px;">
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if ($can_edit): ?>
  <div style="margin-top:1rem;display:flex;gap:10px;align-items:center;min-height:18px;">
    <span id="perm-status" data-note="<?= htmlspecialchars(__('admin.perm_apply_note')) ?>"
          style="font-size:12px;color:var(--text-muted);"><?= __('admin.perm_apply_note') ?></span>
  </div>
  <?php else: ?>
  <p style="margin-top:1rem;font-size:12px;color:var(--text-muted);"><?= __('admin.perm_no_edit') ?></p>
  <?php endif; ?>
</form>

<?php if ($can_edit): ?>
<script>
// Permissions auto-save: ticking any box immediately posts the whole matrix,
// so there is no explicit Save button. On failure the box reverts.
(function () {
  var form = document.getElementById('perm-form');
  if (!form) return;
  var status = document.getElementById('perm-status');
  var note   = status ? status.getAttribute('data-note') : '';
  var MSG = {
    saving: <?= json_encode(__('admin.perm_saving')) ?>,
    saved:  <?= json_encode(__('admin.perm_saved')) ?>,
    failed: <?= json_encode(__('admin.perm_save_failed')) ?>
  };
  var timer;
  function show(text, color) {
    if (!status) return;
    status.textContent = text;
    status.style.color = color;
  }
  form.addEventListener('change', function (e) {
    var box = e.target;
    if (!box.matches('input[type="checkbox"]') || box.disabled) return;
    show(MSG.saving, 'var(--text-muted)');
    fetch(form.action, { method: 'POST', body: new FormData(form) })
      .then(function (r) { if (!r.ok) throw new Error(r.status); show(MSG.saved, 'var(--accent)'); })
      .catch(function () { box.checked = !box.checked; show(MSG.failed, '#791f1f'); })
      .finally(function () {
        clearTimeout(timer);
        timer = setTimeout(function () { show(note, 'var(--text-muted)'); }, 2000);
      });
  });
})();
</script>
<?php endif; ?>
