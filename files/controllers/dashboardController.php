<?php
defined('RMS') or die('Direct access not permitted');

class DashboardController {

    public function index(): void {
        require_login();
        $page_title = __('nav.dashboard');
        $user       = current_user();

        // Partners only ever see figures for RMAs/invoices submitted by their
        // own company. $pid is a controlled integer, so inlining it is safe.
        // An unlinked partner matches partner_id = 0 (i.e. nothing).
        $is_partner = ($user['role'] ?? '') === 'partner';
        $pid = $is_partner ? (int)(current_partner_id() ?? 0) : 0;
        $fr = $is_partner ? " AND r.partner_id = {$pid}" : '';        // when "r" alias is present
        $fp = $is_partner ? " AND partner_id = {$pid}"   : '';        // when querying a table directly

        // Otkazano is the one status on the pickup list that does not prove the
        // device is here: a case can be called off at the counter before
        // anything arrives. rma_requests has no received_at column, so the
        // proof is the case having passed through Uredjaj primljen — the step
        // the counter takes when it accepts a device.
        $received = "EXISTS (SELECT 1 FROM rma_status_history h
                               JOIN rma_statuses hs ON hs.id = h.status_id
                              WHERE h.rma_id = r.id AND hs.code = 'device_received')";

        $stats = [
            'open_rmas'    => db_val("SELECT COUNT(*) FROM rma_requests r
                                      JOIN rma_statuses s ON s.id = r.status_id
                                      WHERE s.is_terminal = 0 AND r.deleted_at IS NULL{$fr}"),
            // Devices on the premises: one count per device with a repair job
            // still open — being diagnosed, waiting for a part, whatever it is —
            // because a job exists, so the device is here.
            // Counting jobs in 'in_progress' alone read 0 while four devices sat
            // in Servis, and disagreed with the Otvorene popravke list below
            // it, which has always counted every non-terminal job.
            'in_repair'    => db_val("SELECT COUNT(DISTINCT j.rma_id) FROM repair_jobs j
                                      JOIN rma_statuses s ON s.id = j.status_id
                                      JOIN rma_requests r ON r.id = j.rma_id
                                      -- The job flag, not the case one: Uredjaj
                                      -- popravljen ends the work while the case
                                      -- carries on to Otpremljeno.
                                      WHERE s.is_terminal_job = 0 AND j.deleted_at IS NULL
                                        AND r.deleted_at IS NULL{$fr}"),
            // Asked of the case, not of the repair jobs beneath it. Counting
            // jobs answered "has the workshop nothing left to do", which is not
            // the same question: a case whose only job was closed as Nema kvara
            // or Otkazano has no unfinished work either, and 52169 duly showed
            // up here while its status still read Na dijagnostici.
            //
            // The three codes are the outcomes that mean the device is finished
            // with and still on the shelf. Nepopravljivo is terminal — the case
            // is over — but the device must still go back to its owner, so it
            // belongs on this list. dispatched_at is what says it has left.
            'for_pickup'   => db_val("SELECT COUNT(*)
                                        FROM rma_requests r
                                        JOIN rma_statuses rs ON rs.id = r.status_id
                                       WHERE r.deleted_at IS NULL
                                         AND r.dispatched_at IS NULL
                                         AND (rs.code IN ('repaired', 'no_fault', 'unrepairable')
                                              OR (rs.code = 'cancelled' AND {$received})){$fr}"),
            // Announced by a partner but not yet here. Read from the status
            // rather than from a date, because that is what the counter sets
            // and what it changes the moment the courier arrives.
            'incoming'     => db_val("SELECT COUNT(*) FROM rma_requests r
                                        JOIN rma_statuses rs ON rs.id = r.status_id
                                       WHERE rs.code = 'awaiting_device'
                                         AND r.deleted_at IS NULL{$fr}"),
            'pending_invoices' => db_val("SELECT COUNT(*) FROM invoices
                                          WHERE status IN ('draft','sent','overdue')
                                          AND deleted_at IS NULL{$fp}"),
        ];

        // Open Repairs — every repair job in a non-terminal status
        // (started but not finished: job_created / in_progress / on_hold / …).
        $open_repairs = db_rows("
            SELECT j.id, j.rma_id, j.created_at, j.started_at,
                   r.rma_number, c.name AS customer_name,
                   s.code AS status_code, s.label AS status_label, s.color AS status_color,
                   u.name AS technician_name
            FROM repair_jobs j
            JOIN rma_statuses s ON s.id = j.status_id
            JOIN rma_requests    r ON r.id = j.rma_id
            LEFT JOIN customers  c ON c.id = r.customer_id
            LEFT JOIN users      u ON u.id = j.technician_id
            WHERE s.is_terminal_job = 0 AND j.deleted_at IS NULL{$fr}
            ORDER BY COALESCE(j.started_at, j.created_at) DESC
            LIMIT 10
        ");

        // Devices the workshop has finished with that are still here: repaired,
        // found fault-free, or beyond repair. Same rule as the card above —
        // read the case, not the jobs.
        //
        // The job join stays only to date the row (a device with no job at all,
        // marked Nepopravljivo at the counter, keeps a LEFT JOIN and falls back
        // to when the case was raised).
        $waiting_pickup = db_rows("
            SELECT r.id, r.rma_number, r.created_at,
                   c.name AS customer_name,
                   rs.code AS status_code, rs.label AS status_label, rs.color AS status_color,
                   MAX(j.completed_at) AS last_completed_at,
                   DATEDIFF(NOW(), r.created_at) AS days_open
            FROM rma_requests r
            JOIN rma_statuses rs ON rs.id = r.status_id
            LEFT JOIN repair_jobs j ON j.rma_id = r.id AND j.deleted_at IS NULL
            LEFT JOIN customers c ON c.id = r.customer_id
            WHERE r.deleted_at IS NULL
              AND r.dispatched_at IS NULL
              AND (rs.code IN ('repaired', 'no_fault', 'unrepairable')
                   OR (rs.code = 'cancelled' AND {$received})){$fr}
            GROUP BY r.id, c.name, rs.code, rs.label, rs.color
            ORDER BY COALESCE(MAX(j.completed_at), r.created_at) DESC
            LIMIT 10
        ");

        include views_path('layout/header.php');
        include views_path('dashboard/index.php');
        include views_path('layout/footer.php');
    }
}
