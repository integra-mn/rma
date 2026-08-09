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

        $stats = [
            'open_rmas'    => db_val("SELECT COUNT(*) FROM rma_requests r
                                      JOIN rma_statuses s ON s.id = r.status_id
                                      WHERE s.is_terminal = 0 AND r.deleted_at IS NULL{$fr}"),
            'in_repair'    => db_val("SELECT COUNT(*) FROM repair_jobs j
                                      JOIN repair_statuses s ON s.id = j.status_id
                                      JOIN rma_requests r ON r.id = j.rma_id
                                      WHERE s.code = 'in_progress' AND j.deleted_at IS NULL{$fr}"),
            'sla_breached' => db_val("SELECT COUNT(*) FROM rma_requests
                                      WHERE sla_breached = 1 AND deleted_at IS NULL{$fp}"),
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
            JOIN repair_statuses s ON s.id = j.status_id
            JOIN rma_requests    r ON r.id = j.rma_id
            LEFT JOIN customers  c ON c.id = r.customer_id
            LEFT JOIN users      u ON u.id = j.technician_id
            WHERE s.is_terminal = 0 AND j.deleted_at IS NULL{$fr}
            ORDER BY COALESCE(j.started_at, j.created_at) DESC
            LIMIT 10
        ");

        // Repaired — Waiting Pickup. RMA is still alive, but every repair
        // job on it is already in a terminal status (work done; case open
        // pending pickup / invoice / delivery).
        $waiting_pickup = db_rows("
            SELECT r.id, r.rma_number, r.created_at,
                   c.name AS customer_name,
                   rs.code AS status_code, rs.label AS status_label, rs.color AS status_color,
                   MAX(j.completed_at) AS last_completed_at,
                   DATEDIFF(NOW(), r.created_at) AS days_open
            FROM rma_requests r
            JOIN rma_statuses rs ON rs.id = r.status_id
            JOIN repair_jobs  j  ON j.rma_id = r.id AND j.deleted_at IS NULL
            JOIN repair_statuses js ON js.id = j.status_id
            LEFT JOIN customers c ON c.id = r.customer_id
            WHERE r.deleted_at IS NULL AND rs.is_terminal = 0{$fr}
            GROUP BY r.id, c.name, rs.code, rs.label, rs.color
            HAVING SUM(CASE WHEN js.is_terminal = 0 THEN 1 ELSE 0 END) = 0
            ORDER BY last_completed_at DESC
            LIMIT 10
        ");

        include views_path('layout/header.php');
        include views_path('dashboard/index.php');
        include views_path('layout/footer.php');
    }
}
