<?php
defined('RMS') or die('Direct access not permitted');

// Role display names.
// 'super_admin' and 'admin' replace legacy ('main_admin' + admin_type='main')
// and ('main_admin' + admin_type='lite' / 'lite_admin') respectively. The
// legacy values are still matched here as a safety net for session state
// or external integrations that may still reference them during rollover.
function role_label(string $role): string {
    return match($role) {
        'super_admin', 'main_admin' => __('users.role_super_admin'),
        'admin', 'lite_admin'       => __('users.role_admin'),
        'reception'   => __('users.role_reception'),
        'technician'  => __('users.role_technician'),
        'partner'     => __('users.role_partner'),
        default       => ucwords(str_replace('_', ' ', $role)),
    };
}

/**
 * Is this user a "full access" admin — i.e. Super Admin or Admin?
 * Both have unrestricted CRUD; Super Admin is unique per install and is
 * the only one who can promote/demote other admins (enforced in adminController).
 */
function is_admin_user(?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) return false;
    return in_array($user['role'] ?? '', ['super_admin', 'admin', 'main_admin', 'lite_admin'], true);
}

function is_super_admin(?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) return false;
    if (($user['role'] ?? '') === 'super_admin') return true;
    // Legacy: main_admin with admin_type=main was Super Admin before the rename
    return ($user['role'] ?? '') === 'main_admin'
        && (($user['admin_type'] ?? '') === 'main');
}

// The complete set of module -> actions the app checks and the permission
// matrix exposes. Single source of truth for the editable matrix (view), the
// save handler, and the "full access" fallback below. Keep in sync with the
// can()/require_permission() calls across the app.
const PERMISSION_MATRIX = [
    'rma'       => ['view', 'create', 'edit'],
    'repair'    => ['view', 'create', 'edit'],
    'parts'     => ['view', 'create', 'edit', 'delete'],
    'shipments' => ['view', 'create', 'edit'],
    'customers' => ['view', 'create', 'edit'],
    'partners'  => ['view', 'edit'],
    // Device catalogue — brands, models, device groups. Its own module because
    // new models arrive constantly: whoever enters them should not need the
    // whole Administration section (locations, couriers, statuses) to do it.
    'devices'   => ['view', 'edit'],
    'reports'   => ['view'],
    // Evidence photos. Delete alone: uploading follows whoever may work the
    // case, but removing a photo destroys the record of how the device arrived,
    // so it is granted on purpose. How long staff keep that ability is a
    // separate question, answered by the evidence_delete_hours setting.
    'evidence'  => ['delete'],
    // The claims queue. Its own module because it is one person's work — the
    // office follows claims, the counter and Servis do not — and because
    // seeing it means seeing every insured customer's case at once.
    'claims'    => ['view', 'edit'],
    'invoicing' => ['view'],
    // Administration = Users, Locations, Couriers, Statuses.
    //
    // The four standard verbs, as elsewhere. A 'users' action used to sit
    // beside view/edit, which read as a contradiction — those two already
    // sound like the whole section — and it would have needed its own row on
    // the Permissions screen, which is ordered to match the sidebar and has no
    // Users entry there.
    //
    //   create — new location, courier, status or user account
    //   edit   — update or activate/deactivate any of them; reset someone's 2FA
    //   delete — remove a location, courier or user (statuses have no delete)
    'administration' => ['view', 'create', 'edit', 'delete'],
    'settings'  => ['view', 'edit'],
    'preferences' => ['theme', 'lang', 'integrations'],
];

// Permissions an editable role keeps no matter what the matrix says.
//
// Empty by design (Rajo, 2026-08-11): Settings belongs to Super Admin alone.
// Admin used to be pinned to settings.view/edit here so it could never be
// locked out of the permission editor — but that also meant those two boxes
// could not be unticked, which is the opposite of what is wanted now.
//
// Super Admin is unaffected: it bypasses the matrix entirely and is never
// stored, so there is always exactly one account that can restore access.
const ROLE_LOCKED_PERMISSIONS = [];

// Flat list of every "module.action" in the matrix (i.e. full access).
function all_permissions(): array {
    $out = [];
    foreach (PERMISSION_MATRIX as $module => $actions) {
        foreach ($actions as $a) $out[] = "{$module}.{$a}";
    }
    return $out;
}

function can(string $module, string $action): bool {
    $user = current_user();
    if (!$user) return false;

    // Super Admin: unrestricted (also legacy main_admin).
    if (is_super_admin($user)) return true;

    // Admin / Reception / Technician / Partner: editable permission sets,
    // stored in role_permissions and managed from Settings → Permissions.
    if (in_array($user['role'] ?? '', ['admin', 'reception', 'technician', 'partner'], true)) {
        return in_array("{$module}.{$action}", role_permissions($user['role']), true);
    }

    // Legacy admin variants (e.g. lite_admin) keep their previous full access.
    if (is_admin_user($user)) return true;

    return false;
}

// ── Which desk may set which RMA status ───────────────────────
//
// rma.edit says somebody may move a case along. This says how far: reception
// takes the device in and hands it back, Servis does everything in
// between. The answer lives on the status itself (rma_statuses.roles, a
// comma-separated list of role codes) beside `notify`, so it is admin-editable
// and a status added tomorrow carries its own.
//
// It matters because four statuses message the customer — without this,
// reception can mark a case Popravljeno and text somebody to come and collect
// a device still in Servis.

// The roles a status may be set by. Only these two are split; everybody else
// is answered by the rules below rather than by the list.
// Who may put a case into a status. Partner joined the list when the two
// status lists were merged: the portal produces Kreirano, so the desk that
// produces it should be nameable. Partners hold no rma.edit today, so ticking
// it grants nothing — it describes, rather than opens, a door.
const STATUS_ROLES = ['reception', 'technician', 'partner'];

/** Where a status may be used: the case, Servis job, or both. */
const STATUS_SCOPES = ['rma', 'repair', 'both'];

/** Normalise the stored value; anything unrecognised means the case. */
function status_applies_to(?string $v): string {
    $v = trim((string)$v);
    return in_array($v, STATUS_SCOPES, true) ? $v : 'rma';
}

/** Does this status belong on a repair job? */
function status_for_repair(array $status): bool {
    return in_array(status_applies_to($status['applies_to'] ?? null), ['repair', 'both'], true);
}

/** Does this status belong on a case? */
function status_for_rma(array $status): bool {
    return in_array(status_applies_to($status['applies_to'] ?? null), ['rma', 'both'], true);
}

/** Parse the stored list. Unknown or empty entries are dropped. */
function status_roles(?string $roles): array {
    if ($roles === null || trim($roles) === '') return [];
    $out = array_map('trim', explode(',', $roles));
    return array_values(array_intersect($out, STATUS_ROLES));
}

/**
 * Where a finished case may still go.
 *
 * A terminal status ends the case — Prijem odbijen - FMI aktivan, Otkazano,
 * Popravka nije moguca. What has not happened yet is the paperwork: the device
 * still goes back to its owner and the case still gets closed. So the only
 * moves left are Otpremljeno and Zatvoreno, and everything the case has been
 * through stays exactly as it was.
 *
 * An empty result would strand a case, so the current status is always in the
 * list — choosing it again changes nothing.
 */
// partner_confirmed is here because Otpremljeno is a finished status and the
// partner's confirmation follows it. No staff dropdown offers it - it is the
// partner's to set - but the rule should be true in general, not only for the
// screens that happen to enforce it.
const TERMINAL_EXITS = ['dispatched', 'partner_confirmed', 'closed'];

/**
 * Filter a status list down to what a case in $current may move to.
 *
 * Only bites on terminal statuses; anything mid-flow keeps the whole list.
 * Admins keep it too — somebody has to be able to undo a wrong turn, which is
 * the same exception can_recur already makes.
 */
function statuses_from(array $statuses, ?array $current, ?array $user = null): array {
    if (!$current || (int)($current['is_terminal'] ?? 0) !== 1) return $statuses;
    if (is_admin_user($user ?? current_user())) return $statuses;

    return array_values(array_filter($statuses, fn($s) =>
        (int)$s['id'] === (int)$current['id']
        || in_array($s['code'] ?? '', TERMINAL_EXITS, true)
    ));
}

/**
 * May this user move an RMA into this status?
 *
 * Admin and Super Admin: always. An absent technician must never leave the
 * counter unable to move a case, and somebody has to be able to correct a
 * mistake in either direction.
 *
 * An empty list means every role, so a newly added status is usable until
 * somebody narrows it deliberately — and so this returns true across a
 * database that has not run the migration yet.
 */
function can_set_status(array $status, ?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) return false;
    if (is_admin_user($user)) return true;

    $roles = status_roles($status['roles'] ?? null);
    if (!$roles) return true;

    return in_array($user['role'] ?? '', $roles, true);
}

/** The subset of a status list this user may actually set. */
function statuses_for_user(array $statuses, ?array $user = null): array {
    return array_values(array_filter(
        $statuses,
        fn(array $s) => can_set_status($s, $user)
    ));
}

// ── Deleting an evidence photo ────────────────────────────────
//
// Two gates. The tick in Podesavanja -> Dozvole says whether a role deletes
// photos at all; the window says for how long after the photo was taken. A bad
// angle or a blurred shot is spotted and retaken the same day — after that the
// photo is the record of how the device arrived, and only Super Admin removes
// it.
//
// Super Admin skips both, as everywhere else. `evidence_delete_hours` at 0
// means staff never delete; a large number means no practical limit.
function can_delete_evidence(?string $taken_at, ?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) return false;
    if (is_super_admin($user)) return true;
    if (!can('evidence', 'delete')) return false;

    $hours = (int) setting('evidence_delete_hours', '24');
    if ($hours <= 0) return false;
    if (!$taken_at) return false;   // undated photo: no way to judge its age

    $age = time() - strtotime($taken_at);
    return $age >= 0 && $age <= $hours * 3600;
}

/**
 * Editable role -> list of "module.action" grants. Reads the role_permissions
 * table; if that table doesn't exist yet (pre-migration) it falls back to the
 * original defaults (Admin full, the three fixed roles from their constants)
 * so nothing breaks. Locked permissions are always merged in. Cached per
 * request. Super Admin is never stored here — it is always full-access.
 */
function role_permission_map(): array {
    static $map = null;
    if ($map !== null) return $map;

    try {
        $rows = db_rows('SELECT role, module, action FROM role_permissions');
        $map  = ['admin' => [], 'reception' => [], 'technician' => [], 'partner' => []];
        foreach ($rows as $r) {
            $map[$r['role']][] = "{$r['module']}.{$r['action']}";
        }
    } catch (\Throwable $e) {
        // Table not present yet — use the original defaults.
        $map = [
            'admin'      => all_permissions(),
            'reception'  => RECEPTION_PERMISSIONS,
            'technician' => TECHNICIAN_PERMISSIONS,
            'partner'    => PARTNER_PERMISSIONS,
        ];
    }

    // Always-on permissions can't be removed via the matrix.
    foreach (ROLE_LOCKED_PERMISSIONS as $role => $locked) {
        $map[$role] = array_values(array_unique(array_merge($map[$role] ?? [], $locked)));
    }
    return $map;
}

function role_permissions(string $role): array {
    return role_permission_map()[$role] ?? [];
}

function lite_admin_can(int $user_id, string $module, string $action): bool {
    static $cache = [];
    if (isset($cache[$user_id])) {
        return in_array("{$module}.{$action}", $cache[$user_id], true);
    }

    $profile = db_row('SELECT preset_id, overrides FROM admin_profiles WHERE user_id = ?', [$user_id]);
    if (!$profile) { $cache[$user_id] = []; return false; }

    // Load preset permissions
    $base = [];
    if ($profile['preset_id']) {
        $rows = db_rows(
            'SELECT p.module, p.action FROM permissions p
             JOIN permission_preset_items ppi ON ppi.permission_id = p.id
             WHERE ppi.preset_id = ?',
            [$profile['preset_id']]
        );
        $base = array_map(fn($r) => "{$r['module']}.{$r['action']}", $rows);
    }

    // Apply overrides
    $overrides = json_decode($profile['overrides'] ?? '{}', true) ?? [];
    foreach ($overrides as $perm_id => $granted) {
        $perm = db_row('SELECT module, action FROM permissions WHERE id = ?', [(int) $perm_id]);
        if (!$perm) continue;
        $key = "{$perm['module']}.{$perm['action']}";
        if ($granted) { $base[] = $key; }
        else          { $base = array_filter($base, fn($k) => $k !== $key); }
    }

    $cache[$user_id] = array_values(array_unique($base));
    return in_array("{$module}.{$action}", $cache[$user_id], true);
}

function require_permission(string $module, string $action): void {
    if (!can($module, $action)) {
        http_response_code(403);
        include views_path('errors/403.php');
        exit;
    }
}

// Location scope — Super Admin sees all locations; Admin and below are
// scoped to the locations on their profile (admin_profiles.location_ids,
// falling back to users.location_id).
function allowed_location_ids(): ?array {
    $user = current_user();
    if (!$user) return [];
    if (is_super_admin($user)) return null; // null = all
    return $user['location_ids'] ?? ($user['location_id'] ? [$user['location_id']] : []);
}

function location_scope_sql(string $alias = ''): string {
    $ids = allowed_location_ids();
    if ($ids === null) return '1=1';
    if (empty($ids)) return '1=0';
    $col = $alias ? "{$alias}.location_id" : 'location_id';
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    return "{$col} IN ({$ph})";
}

// ── Fixed role permission maps ────────────────────────────────

const TECHNICIAN_PERMISSIONS = [
    'evidence.delete',
    'rma.view', 'rma.edit',
    'repair.view', 'repair.create', 'repair.edit',
    'parts.view',
    'delivery.view', 'delivery.edit',
    'appointments.view',
    'customers.view',
    'reports.view',
];

const RECEPTION_PERMISSIONS = [
    'evidence.delete',
    'claims.view', 'claims.edit',
    'rma.view', 'rma.create', 'rma.edit',
    'repair.view',
    'customers.view', 'customers.create', 'customers.edit',
    'partners.view',
    'suppliers.view',
    'reports.view',
];

const PARTNER_PERMISSIONS = [
    'rma.view', 'rma.create',
    'delivery.view',
    'customers.view',
];
