<?php
defined('RMS') or die('Direct access not permitted');

/**
 * The claims queue — one person's work list.
 *
 * Grouped by what needs doing rather than by status, because that is the only
 * question the handler asks: what must go to the insurer today, what am I
 * waiting on them for, what are they waiting on me for, and what has been
 * approved but never paid.
 *
 * The order inside each group is the deadline, soonest first: a claim reported
 * late is refused, so the date is the only thing that ranks them.
 */
class ClaimsController {

    public function index(): void {
        require_login();
        require_permission('claims', 'view');

        $page_title = __('ins.title');

        // Everything the queue needs about a claim, in one shape.
        $base = "SELECT c.*, p.policy_number, p.participation_pct,
                        i.name AS insurer_name, i.portal_url,
                        r.rma_number, r.id AS rma_id,
                        cu.name AS customer_name,
                        d.imei, d.serial_number,
                        dm.name AS model_name, db2.name AS brand_name
                   FROM insurance_claims c
                   JOIN insurance_policies p ON p.id = c.policy_id
                   JOIN insurers i ON i.id = p.insurer_id
              LEFT JOIN rma_requests r ON r.id = c.rma_id
              LEFT JOIN customers cu ON cu.id = r.customer_id
              LEFT JOIN devices d ON d.id = r.device_id
              LEFT JOIN device_models dm ON dm.id = d.model_id
              LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                  WHERE c.deleted_at IS NULL";

        // Ordered by deadline with the undated last, so a claim whose insurer
        // has not told us their window never outranks one that is running out.
        $order = " ORDER BY CASE WHEN c.report_due_at IS NULL THEN 1 ELSE 0 END,
                            c.report_due_at ASC, c.id ASC";

        $this->to_report   = db_rows("{$base} AND c.status = 'new'{$order}");
        $this->with_them   = db_rows("{$base} AND c.status = 'reported'{$order}");
        $this->with_us     = db_rows("{$base} AND c.status = 'more_info'{$order}");
        $this->unpaid      = db_rows("{$base} AND c.status = 'approved'{$order}");

        // Overdue is counted rather than shown separately: it is a property of
        // a row in the first group, not a group of its own.
        $overdue = 0;
        foreach ($this->to_report as $c) {
            if (!empty($c['report_due_at']) && strtotime($c['report_due_at']) < time()) $overdue++;
        }

        $to_report = $this->to_report;
        $with_them = $this->with_them;
        $with_us   = $this->with_us;
        $unpaid    = $this->unpaid;

        include views_path('layout/header.php');
        include views_path('claims/index.php');
        include views_path('layout/footer.php');
    }

    private array $to_report = [];
    private array $with_them = [];
    private array $with_us   = [];
    private array $unpaid    = [];
}
