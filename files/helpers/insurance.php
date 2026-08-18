<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Insurance — the check made at the counter, before a device is accepted.
 *
 * Refusals are administrative, not about the kind of damage: an expired policy,
 * an allowance used up, damage this policy never covered. Every one of those is
 * knowable while the customer is still standing there, which is the whole point
 * of asking here rather than discovering it a week later when the claim bounces.
 *
 * Four questions, in order:
 *   1. is there a policy for this device?
 *   2. was the INCIDENT inside its period?
 *   3. is this damage on THIS policy's list?
 *   4. is there allowance left?
 *
 * Reasoning in files/docs/OSIGURANJE.md. Nothing here talks to an insurer.
 */

/** A coverage item's label in the reader's language, as status_label does. */
function coverage_label(array $item, ?string $lang = null): string {
    $lang = $lang ?? (current_user()['lang'] ?? setting('default_lang', 'en'));
    if ($lang === 'me' && trim((string)($item['label_me'] ?? '')) !== '') {
        return (string)$item['label_me'];
    }
    return (string)($item['label'] ?? '');
}

/** Coverage is stored as comma-separated item codes, like rma_statuses.roles. */
function insurance_coverage(?string $csv): array {
    if ($csv === null || trim($csv) === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $csv)), fn($c) => $c !== ''));
}

/**
 * The policy that applies to this device for an incident on this date.
 *
 * Never "the newest policy": insurers issue a fresh policy rather than renewing,
 * so a device accumulates them, and the one that answers is the one whose period
 * contains the incident. Matched on IMEI or serial — the number written on the
 * device and on the paper — rather than on a device row.
 */
function policy_for_device(string $identifier, ?string $incident_date = null): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') return null;

    $on = $incident_date ?: date('Y-m-d');
    return db_row(
        "SELECT p.*, i.name AS insurer_name, i.report_hours
           FROM insurance_policies p
           JOIN insurers i ON i.id = p.insurer_id
          WHERE p.deleted_at IS NULL
            AND (p.imei = ? OR p.serial_number = ?)
            AND ? BETWEEN p.starts_on AND p.ends_on
          ORDER BY p.ends_on DESC, p.id DESC
          LIMIT 1",
        [$identifier, $identifier, $on]
    );
}

/** Any policy for this device, whatever its dates — so an expired one can be named. */
function policy_any_for_device(string $identifier): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') return null;
    return db_row(
        "SELECT p.*, i.name AS insurer_name, i.report_hours
           FROM insurance_policies p
           JOIN insurers i ON i.id = p.insurer_id
          WHERE p.deleted_at IS NULL AND (p.imei = ? OR p.serial_number = ?)
          ORDER BY p.ends_on DESC, p.id DESC LIMIT 1",
        [$identifier, $identifier]
    );
}

/**
 * How much of a policy's allowance is gone, and how much is merely promised.
 *
 * Only approved claims consume it — a refusal costs the customer nothing
 * (Rajo, 2026-08-15). A claim still with the insurer has consumed nothing
 * either, but it cannot be treated as free or the counter would promise cover
 * on a third claim while the second is undecided, so it is counted apart.
 */
function policy_claim_counts(int $policy_id): array {
    $rows = db_rows(
        "SELECT status, COUNT(*) AS n FROM insurance_claims
          WHERE policy_id = ? AND deleted_at IS NULL GROUP BY status",
        [$policy_id]
    );
    $used = $pending = 0;
    foreach ($rows as $r) {
        $n = (int)$r['n'];
        if (in_array($r['status'], ['approved', 'paid', 'closed'], true))          $used    += $n;
        elseif (in_array($r['status'], ['new', 'reported', 'more_info'], true))    $pending += $n;
        // 'refused' counts as neither.
    }
    return ['used' => $used, 'pending' => $pending];
}

/**
 * The four questions, answered for one device.
 *
 * Returns:
 *   covered   bool    may this be taken in as an insurance case
 *   reason    string  why not: 'no_policy' | 'expired' | 'not_covered' | 'no_allowance'
 *   policy    array|null
 *   used/pending/allowed  ints for the counter panel
 *   participation  percentage the customer pays, from the policy
 *
 * The count is OUR record. A claim made directly with the insurer or through
 * another service centre is invisible here, so until a portal can be read the
 * number is presented as ours rather than as fact.
 */
function insurance_check(string $identifier, ?string $incident_date, ?string $damage_code): array {
    $out = [
        'covered' => false, 'reason' => 'no_policy', 'policy' => null,
        'used' => 0, 'pending' => 0, 'allowed' => 0, 'participation' => 0.0,
        'coverage' => [],
    ];

    $policy = policy_for_device($identifier, $incident_date);
    if (!$policy) {
        // Naming the expired policy is more use than silence: the counter can
        // tell the customer their cover ran out rather than that none exists.
        $lapsed = policy_any_for_device($identifier);
        if ($lapsed) { $out['reason'] = 'expired'; $out['policy'] = $lapsed; }
        return $out;
    }

    $counts = policy_claim_counts((int)$policy['id']);
    $out['policy']        = $policy;
    $out['used']          = $counts['used'];
    $out['pending']       = $counts['pending'];
    $out['allowed']       = (int)$policy['claims_allowed'];
    $out['participation'] = (float)$policy['participation_pct'];
    $out['coverage']      = insurance_coverage($policy['coverage']);

    // Damage this policy never covered is not a claim at all — it is an
    // ordinary paid repair, and the customer should hear so at the counter.
    if ($damage_code !== null && $damage_code !== ''
        && $out['coverage'] && !in_array($damage_code, $out['coverage'], true)) {
        $out['reason'] = 'not_covered';
        return $out;
    }

    // Pending counts against the allowance for this decision: it may yet be
    // approved, and promising a third claim on the strength of an undecided
    // second is how a refusal happens.
    if ($out['allowed'] > 0 && ($counts['used'] + $counts['pending']) >= $out['allowed']) {
        $out['reason'] = 'no_allowance';
        return $out;
    }

    $out['covered'] = true;
    $out['reason']  = '';
    return $out;
}
