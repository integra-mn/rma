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
