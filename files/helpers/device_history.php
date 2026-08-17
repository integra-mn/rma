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
