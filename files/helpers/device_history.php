<?php
defined('RMS') or die('Direct access not permitted');

/**
 * A device's past visits — and when it last left the building.
 *
 * The clock for a repeated repair starts when Integra dispatched the device,
 * not when the customer got it back (Rajo, 2026-08-17): a partner may sit on a
 * phone for a month and an owner may not switch it on, and neither is Integra's
 * doing. Dispatch is also the last moment Integra actually observes.
 *
 * Nothing here is about vendor penalties. It is an internal quality signal —
 * a device back within weeks means somebody should read what was done last
 * time before starting again.
 */

/**
 * Work out when a case's device left, and store it on the RMA.
 *
 * Recomputed from source rather than set once, so it repairs itself if a
 * shipment date is corrected afterwards. Two sources, earliest wins:
 *
 *   1. the first outbound shipment with a dispatch date — the courier flow;
 *   2. the first time the case entered Otpremljeno, or failing that Zatvoreno
 *      — a counter handover leaves no shipment behind.
 *
 * Zatvoreno counts because a closed case is one whose device has gone home.
 * Otkazano and Nepopravljivo deliberately do not: a cancelled case may never
 * have received the device, and an unrepairable one may still be on a shelf
 * awaiting a decision.
 */
function stamp_rma_dispatch(int $rma_id): ?string {
    if ($rma_id <= 0) return null;

    $from_shipment = db_val(
        "SELECT MIN(dispatched_at) FROM delivery_shipments
          WHERE rma_id = ? AND direction = 'outbound' AND dispatched_at IS NOT NULL",
        [$rma_id]
    );

    // Preferring dispatched over closed rather than taking the earlier of the
    // two: a case that went out and was closed a week later left when it went
    // out, and MIN() over both codes would say the same thing only by luck.
    $from_status = db_val(
        "SELECT MIN(h.created_at) FROM rma_status_history h
           JOIN rma_statuses s ON s.id = h.status_id
          WHERE h.rma_id = ? AND s.code = 'dispatched'",
        [$rma_id]
    ) ?: db_val(
        "SELECT MIN(h.created_at) FROM rma_status_history h
           JOIN rma_statuses s ON s.id = h.status_id
          WHERE h.rma_id = ? AND s.code = 'closed'",
        [$rma_id]
    );

    $dates = array_filter([$from_shipment, $from_status]);
    $when  = $dates ? min($dates) : null;

    $now = db_val('SELECT dispatched_at FROM rma_requests WHERE id = ?', [$rma_id]);
    if ((string)$now !== (string)$when) {
        db_update('rma_requests', ['dispatched_at' => $when], 'id = ?', [$rma_id]);
    }
    return $when ?: null;
}

/**
 * Every case for the physical device carrying this IMEI or serial.
 *
 * Keyed on the identifier, never on devices.id: one phone can hold several
 * device rows. The intake match is an offer — the green "Use this device"
 * button — so a case saved without pressing it creates a second row for the
 * same handset. IMEI 359168420215834 already has four (2026-08-17). Reading by
 * row would show a quarter of that phone's history and raise no warning on the
 * visit this whole feature exists to catch.
 *
 * Location scope and the partner restriction apply as they do on the RMA list:
 * a device's history is only ever the part of it this user could already see.
 */
/**
 * Every device row that is this same physical handset.
 *
 * Rows carrying the identifier, then rows carrying any identifier those rows
 * carry — a duplicate entered by IMEI may hold a serial the original does not,
 * and both are the same phone.
 */
function device_row_ids(string $identifier): array {
    $identifier = trim($identifier);
    if ($identifier === '') return [];

    $rows = db_rows('SELECT id, imei, serial_number FROM devices WHERE imei = ? OR serial_number = ?',
                    [$identifier, $identifier]);
    if (!$rows) return [];

    $idents = [];
    foreach ($rows as $r) {
        foreach ([$r['imei'], $r['serial_number']] as $v) {
            if ($v !== null && trim((string)$v) !== '') $idents[] = $v;
        }
    }
    $idents = array_values(array_unique($idents));
    $ph     = implode(',', array_fill(0, count($idents), '?'));

    return array_map('intval', array_column(
        db_rows("SELECT id FROM devices WHERE imei IN ({$ph}) OR serial_number IN ({$ph})",
                array_merge($idents, $idents)),
        'id'
    ));
}

function device_cases(string $identifier): array {
    $ids = device_row_ids($identifier);
    if (!$ids) return [];

    $where  = 'r.device_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    $params = array_map('intval', $ids);

    $locs = allowed_location_ids();
    if ($locs !== null) {
        if (!$locs) return [];
        $where .= ' AND ' . location_scope_sql('r');
        $params = array_merge($params, $locs);
    }
    if ((current_user()['role'] ?? '') === 'partner') {
        $where   .= ' AND r.partner_id = ?';
        $params[] = current_partner_id() ?? 0;
    }

    return db_rows(
        "SELECT r.id, r.rma_number, r.created_at, r.dispatched_at, r.complaint,
                r.is_warranty, r.warranty_refusal,
                s.code AS status_code, s.label AS status_label, s.color AS status_color,
                c.name AS customer_name,
                p.name AS partner_name,
                u.name AS tech_name,
                rj.description AS findings, rj.resolution AS works
           FROM rma_requests r
           JOIN rma_statuses s ON s.id = r.status_id
           LEFT JOIN customers c ON c.id = r.customer_id
           LEFT JOIN partners  p ON p.id = r.partner_id
           LEFT JOIN users     u ON u.id = r.assigned_tech
           LEFT JOIN repair_jobs rj ON rj.id = (
               SELECT id FROM repair_jobs
                WHERE rma_id = r.id AND deleted_at IS NULL
                ORDER BY created_at DESC, id DESC LIMIT 1
           )
          WHERE {$where} AND r.deleted_at IS NULL
          ORDER BY r.created_at DESC",
        $params
    );
}

/** The device itself, as last recorded under this identifier. */
function device_by_identifier(string $identifier): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') return null;
    return db_row(
        "SELECT d.*, dm.name AS model_name, db2.name AS brand_name
           FROM devices d
           LEFT JOIN device_models dm ON dm.id = d.model_id
           LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
          WHERE d.imei = ? OR d.serial_number = ?
          ORDER BY d.id DESC LIMIT 1",
        [$identifier, $identifier]
    );
}

// ── One phone, one row ────────────────────────────────────────
//
// The intake form offers a match — the green "Use this device" — and creates a
// new row when nobody presses it. That is how IMEI 359168420215834 came to hold
// four rows for one handset. This does automatically what the button does by
// hand, so identity no longer depends on someone noticing a notice.
//
// Deliberately the same behaviour as the button, not more: the existing row is
// reused as it stands. Its customer and partner are left alone, because a phone
// that changes hands is still the same phone, and the case carries the new
// owner anyway.

/**
 * The id of the device carrying this IMEI or serial, creating one only if no
 * row carries either.
 *
 * @param array $data columns for a new row; 'imei' and 'serial_number' are
 *                    also what an existing row is matched on.
 */
function device_find_or_create(array $data): ?int {
    $imei   = trim((string)($data['imei'] ?? ''));
    $serial = trim((string)($data['serial_number'] ?? ''));

    // IMEI first: it identifies a handset worldwide, while a serial is only
    // unique within a maker's range and is the likelier of the two to collide.
    foreach ([['imei', $imei], ['serial_number', $serial]] as [$col, $val]) {
        if ($val === '') continue;
        $found = db_val("SELECT id FROM devices WHERE {$col} = ? ORDER BY id LIMIT 1", [$val]);
        if ($found) {
            // Fill a blank the new case can answer — a device first entered by
            // IMEI now arriving with its serial — without overwriting anything
            // already recorded.
            $row  = db_row('SELECT imei, serial_number FROM devices WHERE id = ?', [(int)$found]);
            $fill = [];
            if ($imei   !== '' && trim((string)($row['imei'] ?? '')) === '')          $fill['imei'] = $imei;
            if ($serial !== '' && trim((string)($row['serial_number'] ?? '')) === '') $fill['serial_number'] = $serial;
            if ($fill) db_update('devices', $fill, 'id = ?', [(int)$found]);
            return (int)$found;
        }
    }

    if (empty($data['model_id'])) return null;
    return db_insert('devices', $data);
}

// ── Has this device been here before? ─────────────────────────
//
// Two windows, both in Podesavanja. Inside the short one a device is coming
// back soon after it left, which is worth stopping for — amber. Inside the long
// one it is context, not an alarm — grey. Beyond, nothing: a device back after
// two years is just a device.
//
// Measured from when Integra dispatched it, never from when the customer
// collected it: a partner may hold a phone for a month and an owner may not
// switch it on, and neither is Integra's doing.

function repeat_repair_window(?int $brand_id = null): int {
    // $brand_id is accepted and ignored. If a vendor ever states its own rule,
    // it goes here and on device_brands — every caller already asks this way.
    return max(0, (int) setting('repeat_repair_days', '30'));
}

function repeat_seen_window(?int $brand_id = null): int {
    return max(0, (int) setting('repeat_seen_days', '180'));
}

/**
 * What to say about a device's past, if anything.
 *
 * Returns level 'repeat' (amber), 'seen' (grey) or 'none', plus the case that
 * prompted it. `open` is separate and firmer: a prior case that never left is
 * not a repeat repair, it is usually a second case opened for a device already
 * here.
 */
function device_repeat_state(string $identifier, ?int $exclude_rma_id = null): array {
    $cases = device_cases($identifier);
    if ($exclude_rma_id) {
        $cases = array_values(array_filter($cases, fn($c) => (int)$c['id'] !== $exclude_rma_id));
    }
    return device_repeat_verdict($cases);
}

/**
 * The verdict alone, over cases already fetched and already filtered — kept
 * apart from the reading so the rule can be exercised without a database.
 * Expects newest first, as device_cases() returns them.
 */
function device_repeat_verdict(array $cases): array {
    $none = ['level' => 'none', 'visits' => 0, 'days' => null, 'case' => null, 'open' => null, 'cases' => []];
    if (!$cases) return $none;

    // Still here: open case, never dispatched. Terminal ones are excluded —
    // a cancelled case is not a device sitting on a shelf.
    $open = null;
    foreach ($cases as $c) {
        if (empty($c['dispatched_at'])
            && !in_array($c['status_code'], ['closed', 'cancelled', 'unrepairable'], true)) {
            $open = $c;
            break;
        }
    }

    // Only visits the device actually came back from are counted (Rajo,
    // 2026-08-18): a case still open is the device being here now, not a past
    // visit, and counting it made a first-time device read "1 put".
    $completed = array_values(array_filter($cases, fn($c) => !empty($c['dispatched_at'])));

    // The most recent of those is the one the clock runs from.
    $last = $completed[0] ?? null;

    $state = $none;
    $state['visits'] = count($completed);
    $state['cases']  = $cases;
    $state['open']   = $open;

    if ($last) {
        $days = (int) floor((time() - strtotime($last['dispatched_at'])) / 86400);
        if ($days < 0) $days = 0;
        $state['days'] = $days;
        $state['case'] = $last;

        $short = repeat_repair_window();
        $long  = repeat_seen_window();
        if ($short > 0 && $days <= $short)     $state['level'] = 'repeat';
        elseif ($long > 0 && $days <= $long)   $state['level'] = 'seen';
    }
    // A device with nothing but an open case gets no grey line: `open` is
    // reported on its own, and it is a firmer message than "been here before".

    return $state;
}

/**
 * The case keeping this device here, if there is one.
 *
 * A handset already in Reklamacije or Popravke cannot be taken in again
 * (Rajo, 2026-08-18) — it is on the premises, so a second case is somebody
 * entering it twice rather than a device arriving. Only once every case for it
 * has reached a final status may it be booked in afresh.
 *
 * Deliberately unscoped, unlike device_cases(): whether a device is here is a
 * fact about the device, not about who is allowed to see the case. A counter in
 * another location would otherwise be told nothing and open the duplicate.
 */
function device_open_case(string $identifier): ?array {
    $ids = device_row_ids($identifier);
    if (!$ids) return null;

    $ph = implode(',', array_fill(0, count($ids), '?'));
    return db_row(
        "SELECT r.id, r.rma_number, s.code AS status_code, s.label AS status_label
           FROM rma_requests r
           JOIN rma_statuses s ON s.id = r.status_id
          WHERE r.device_id IN ({$ph})
            AND r.deleted_at IS NULL
            -- A device that has gone back cannot be held by the case it came in
            -- on. Otpremljeno is not terminal — the case stays open until it is
            -- closed — so reading is_terminal alone would refuse the very thing
            -- this app now watches for: a handset returning. Same distinction as
            -- the pickup list: the status says whether the CASE is finished, the
            -- dispatch date says whether the DEVICE is still here.
            AND r.dispatched_at IS NULL
            AND (
                  s.is_terminal = 0
                  -- Or the case is closed while the bench job is not, which
                  -- still means the device has not left.
                  OR EXISTS (
                      SELECT 1 FROM repair_jobs j
                        JOIN rma_statuses js ON js.id = j.status_id
                       WHERE j.rma_id = r.id AND j.deleted_at IS NULL AND js.is_terminal_job = 0
                  )
            )
          ORDER BY r.id DESC LIMIT 1",
        $ids
    );
}
