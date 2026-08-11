<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Partner-portal helpers. Partner users link to partners via the
 * partner_users pivot table (partner_id, user_id, role: 'admin'|'staff').
 */

/**
 * Return the partner_id the current user belongs to, or null if they
 * aren't linked to any partner. One user typically maps to one partner;
 * if somehow mapped to several we take the first row.
 */
function current_partner_id(): ?int {
    $uid = current_user_id();
    if (!$uid) return null;
    $row = db_row('SELECT partner_id FROM partner_users WHERE user_id = ? LIMIT 1', [$uid]);
    return $row ? (int)$row['partner_id'] : null;
}

/**
 * The poslovnica (branch) the current user sits in, or null.
 *
 * This is the partner's own office — NOT users.location_id, which names an
 * Integra service point and drives stock, invoicing and rotas.
 */
function current_partner_branch_id(): ?int {
    $uid = current_user_id();
    if (!$uid) return null;
    $row = db_row('SELECT branch_id FROM partner_users WHERE user_id = ? LIMIT 1', [$uid]);
    return $row && $row['branch_id'] ? (int)$row['branch_id'] : null;
}

/**
 * Accept a branch id only if it really belongs to that partner.
 *
 * Every screen that records a branch goes through here — staff intake, the
 * partner portal, the user form and the profile page. Without the check a
 * crafted post could file work under another partner's poslovnica, and the
 * per-branch figures would be quietly wrong with nothing to show for it.
 *
 * Soft-deleted branches are rejected for new work, but existing rows keep
 * pointing at them so past reports don't change.
 */
function valid_partner_branch_id(?int $partner_id, mixed $branch_id): ?int {
    $branch_id = (int) $branch_id;
    if (!$partner_id || !$branch_id) return null;

    $ok = db_val(
        'SELECT id FROM partner_branches
          WHERE id = ? AND partner_id = ? AND deleted_at IS NULL',
        [$branch_id, $partner_id]
    );
    return $ok ? $branch_id : null;
}

/**
 * Active branches for one partner, for populating a dropdown.
 */
function partner_branches(?int $partner_id): array {
    if (!$partner_id) return [];
    return db_rows(
        'SELECT id, name, city FROM partner_branches
          WHERE partner_id = ? AND deleted_at IS NULL AND is_active = 1
          ORDER BY name',
        [$partner_id]
    );
}

/**
 * Guard a /portal/* route. Requires the current user to have role='partner';
 * anyone else is bounced to the staff home page.
 */
function require_partner(): void {
    require_login();
    $user = current_user();
    if (($user['role'] ?? '') !== 'partner') {
        header('Location: /');
        exit;
    }
}
