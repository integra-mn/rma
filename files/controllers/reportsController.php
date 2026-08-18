<?php
defined('RMS') or die('Direct access not permitted');

class ReportsController {

    public function index(): void {
        require_login();
        require_permission('reports', 'view');

        $page_title = __('nav.reports');
        $tab        = $_GET['tab']   ?? 'rma';

        // Shared filters
        $date_from  = $_GET['from']     ?? date('Y-m-01');        // first of current month
        $date_to    = $_GET['to']       ?? date('Y-m-d');
        $brand_id   = (int)($_GET['brand']    ?? 0);
        $location_id= (int)($_GET['location'] ?? 0);

        // Filter lists
        $brands    = db_rows('SELECT id, name FROM device_brands ORDER BY name');
        $locations = db_rows('SELECT id, name FROM locations WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');

        // Build base WHERE clauses
        $rma_where  = "r.deleted_at IS NULL AND r.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
        $rma_params = [$date_from, $date_to];

        if ($location_id) {
            $rma_where   .= ' AND r.location_id = ?';
            $rma_params[] = $location_id;
        }
        if ($brand_id) {
            $rma_where   .= ' AND db2.id = ?';
            $rma_params[] = $brand_id;
        }

        $rep_where  = "j.deleted_at IS NULL AND j.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
        $rep_params = [$date_from, $date_to];
        if ($location_id) {
            $rep_where   .= ' AND r.location_id = ?';
            $rep_params[] = $location_id;
        }
        if ($brand_id) {
            $rep_where   .= ' AND db2.id = ?';
            $rep_params[] = $brand_id;
        }

        if ($tab === 'rma') {
            // Summary cards
            $total_rma = (int) db_val(
                "SELECT COUNT(*) FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rma_where}", $rma_params);

            $open_rma = (int) db_val(
                "SELECT COUNT(*) FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 JOIN rma_statuses s ON s.id = r.status_id
                 WHERE {$rma_where} AND s.is_terminal = 0", $rma_params);

            $closed_rma = $total_rma - $open_rma;

            $avg_days = db_val(
                "SELECT ROUND(AVG(DATEDIFF(COALESCE(r.updated_at, NOW()), r.created_at)), 1)
                 FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rma_where}", $rma_params);

            // By status
            $by_status = db_rows(
                "SELECT s.label, s.color, COUNT(*) as cnt
                 FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 JOIN rma_statuses s ON s.id = r.status_id
                 WHERE {$rma_where}
                 GROUP BY s.id ORDER BY cnt DESC", $rma_params);

            // By location
            $by_location = db_rows(
                "SELECT l.name, COUNT(*) as cnt
                 FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 LEFT JOIN locations l ON l.id = r.location_id
                 WHERE {$rma_where}
                 GROUP BY r.location_id, l.name ORDER BY cnt DESC", $rma_params);

            // By partner branch (poslovnica). Only RMAs that carry a branch are
            // counted — walk-in intake has none, and listing it as a blank row
            // would read as an unnamed branch rather than "no branch".
            $by_branch = db_rows(
                "SELECT p.name as partner_name, pb.name as branch_name, COUNT(*) as cnt
                 FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 JOIN partner_branches pb ON pb.id = r.partner_branch_id
                 LEFT JOIN partners p ON p.id = pb.partner_id
                 WHERE {$rma_where}
                 GROUP BY pb.id, pb.name, p.name ORDER BY cnt DESC", $rma_params);

            // By brand
            $by_brand = db_rows(
                "SELECT COALESCE(db2.name, 'Unknown') as brand, COUNT(*) as cnt
                 FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rma_where}
                 GROUP BY db2.id ORDER BY cnt DESC", $rma_params);

            // Monthly trend — aggregated so it works under sql_mode=only_full_group_by
            $monthly = db_rows(
                "SELECT DATE_FORMAT(r.created_at, '%Y-%m') as month,
                        MIN(DATE_FORMAT(r.created_at, '%b %Y')) as label,
                        COUNT(*) as cnt
                 FROM rma_requests r
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rma_where}
                 GROUP BY DATE_FORMAT(r.created_at, '%Y-%m')
                 ORDER BY month", $rma_params);

        } elseif ($tab === 'repairs') {
            $total_repairs = (int) db_val(
                "SELECT COUNT(*) FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rep_where}", $rep_params);

            $completed_repairs = (int) db_val(
                "SELECT COUNT(*) FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rep_where} AND j.completed_at IS NOT NULL", $rep_params);

            $avg_repair_days = db_val(
                "SELECT ROUND(AVG(DATEDIFF(j.completed_at, j.created_at)), 1)
                 FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rep_where} AND j.completed_at IS NOT NULL", $rep_params);

            // By technician
            $by_technician = db_rows(
                "SELECT COALESCE(u.name, 'Unassigned') as tech,
                        COUNT(*) as total,
                        SUM(CASE WHEN j.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                        ROUND(AVG(CASE WHEN j.completed_at IS NOT NULL THEN DATEDIFF(j.completed_at, j.created_at) END), 1) as avg_days
                 FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 LEFT JOIN users u ON u.id = j.technician_id
                 WHERE {$rep_where}
                 GROUP BY j.technician_id, u.name ORDER BY total DESC", $rep_params);

            // By brand
            $by_brand = db_rows(
                "SELECT COALESCE(db2.name, 'Unknown') as brand, COUNT(*) as cnt
                 FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rep_where}
                 GROUP BY db2.id ORDER BY cnt DESC", $rep_params);

            // By model
            $by_model = db_rows(
                "SELECT COALESCE(dm.name, 'Unknown') as model,
                        COALESCE(db2.name, '') as brand,
                        COUNT(*) as cnt
                 FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rep_where}
                 GROUP BY d.model_id, dm.name, db2.name ORDER BY cnt DESC LIMIT 10", $rep_params);

            // Monthly
            $monthly = db_rows(
                "SELECT DATE_FORMAT(j.created_at, '%Y-%m') as month,
                        MIN(DATE_FORMAT(j.created_at, '%b %Y')) as label,
                        COUNT(*) as cnt
                 FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE {$rep_where}
                 GROUP BY DATE_FORMAT(j.created_at, '%Y-%m')
                 ORDER BY month", $rep_params);

        } elseif ($tab === 'parts') {
            $pu_where  = "pu.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
            $pu_params = [$date_from, $date_to];

            $total_used = (int) db_val(
                "SELECT SUM(pu.quantity) FROM part_usage pu WHERE {$pu_where}", $pu_params);

            $unique_parts = (int) db_val(
                "SELECT COUNT(DISTINCT pu.part_id) FROM part_usage pu WHERE {$pu_where}", $pu_params);

            // Most used parts
            $top_parts = db_rows(
                "SELECT p.name, p.internal_sku, SUM(pu.quantity) as qty,
                        ROUND(SUM(pu.quantity * pu.unit_cost), 2) as total_cost
                 FROM part_usage pu
                 JOIN parts p ON p.id = pu.part_id
                 WHERE {$pu_where}
                 GROUP BY pu.part_id, p.name, p.internal_sku ORDER BY qty DESC LIMIT 15", $pu_params);

            // Low stock
            $low_stock = db_rows(
                "SELECT p.name, p.internal_sku, ps.quantity as stock, p.min_stock
                 FROM parts p
                 JOIN parts_stock ps ON ps.part_id = p.id
                 WHERE p.deleted_at IS NULL AND ps.quantity <= p.min_stock
                 ORDER BY ps.quantity ASC");

            // Usage by month
            $monthly = db_rows(
                "SELECT DATE_FORMAT(pu.created_at, '%Y-%m') as month,
                        MIN(DATE_FORMAT(pu.created_at, '%b %Y')) as label,
                        SUM(pu.quantity) as qty
                 FROM part_usage pu
                 WHERE {$pu_where}
                 GROUP BY DATE_FORMAT(pu.created_at, '%Y-%m')
                 ORDER BY month", $pu_params);
        } elseif ($tab === 'repeat') {
            // Repeated repairs. A device is identified by what is written on it
            // — IMEI, or serial when it has none — never by devices.id, since
            // one handset can hold several rows.
            //
            // A repeat is a case opened while the same device's previous case
            // had already gone out. The gap is measured from that dispatch, not
            // from the customer collecting it: a partner may hold a phone for a
            // month and that is not Integra's doing.
            // One query, used by the screen and by the export. Two copies of a
            // join this shape would drift, and a report that disagrees with its
            // own export is worse than having no export.
            $repeat_rows = $this->repeat_repair_rows($rma_where, $rma_params);
        }

        include views_path('layout/header.php');
        include views_path('reports/index.php');
        include views_path('layout/footer.php');
    }

    // ── Export current report tab to XLS (Excel) or PDF ──────────────────
    /**
     * Devices that came back inside the reporting window.
     *
     * Shared by the screen and the export so the two cannot disagree.
     */
    private function repeat_repair_rows(string $rma_where, array $rma_params): array {
        $rp_days = repeat_repair_window();
        return db_rows(
                "SELECT c.id, c.rma_number, c.created_at, c.ident, c.brand, c.model,
                        c.location_id,
                        p.rma_number AS prev_rma, p.dispatched_at AS prev_out,
                        DATEDIFF(c.created_at, p.dispatched_at) AS days,
                        p.works AS prev_works
                   FROM (
                        SELECT r.id, r.rma_number, r.created_at, r.location_id,
                               COALESCE(NULLIF(d.imei, ''), d.serial_number) AS ident,
                               db2.name AS brand, dm.name AS model
                          FROM rma_requests r
                          JOIN devices d ON d.id = r.device_id
                          LEFT JOIN device_models dm ON dm.id = d.model_id
                          LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                         WHERE {$rma_where}
                           AND COALESCE(NULLIF(d.imei, ''), d.serial_number) IS NOT NULL
                   ) c
                   JOIN (
                        SELECT r.rma_number, r.dispatched_at,
                               COALESCE(NULLIF(d.imei, ''), d.serial_number) AS ident,
                               rj.resolution AS works
                          FROM rma_requests r
                          JOIN devices d ON d.id = r.device_id
                          LEFT JOIN repair_jobs rj ON rj.id = (
                              SELECT id FROM repair_jobs
                               WHERE rma_id = r.id AND deleted_at IS NULL
                               ORDER BY created_at DESC, id DESC LIMIT 1
                          )
                         WHERE r.deleted_at IS NULL AND r.dispatched_at IS NOT NULL
                   ) p ON p.ident = c.ident AND p.dispatched_at < c.created_at
                  WHERE DATEDIFF(c.created_at, p.dispatched_at) <= ?
                    -- p must be the visit immediately before this one, or a
                    -- device with three visits would report the oldest pair as
                    -- a repeat too.
                    AND NOT EXISTS (
                        SELECT 1
                          FROM rma_requests r3
                          JOIN devices d3 ON d3.id = r3.device_id
                         WHERE COALESCE(NULLIF(d3.imei, ''), d3.serial_number) = c.ident
                           AND r3.dispatched_at IS NOT NULL
                           AND r3.dispatched_at < c.created_at
                           AND r3.dispatched_at > p.dispatched_at
                    )
                  ORDER BY c.created_at DESC",
            array_merge($rma_params, [$rp_days])
        );
    }

    public function export(): void {
        require_login();
        require_permission('reports', 'view');

        $tab         = in_array($_GET['tab'] ?? 'rma', ['rma','repairs','parts','repeat'], true) ? $_GET['tab'] : 'rma';
        $format      = ($_GET['format'] ?? 'xls') === 'pdf' ? 'pdf' : 'xls';
        $date_from   = $_GET['from']     ?? date('Y-m-01');
        $date_to     = $_GET['to']       ?? date('Y-m-d');
        $brand_id    = (int)($_GET['brand']    ?? 0);
        $location_id = (int)($_GET['location'] ?? 0);

        // Same device joins + filters the on-screen report uses.
        $join = "LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id";

        $rma_where = "r.deleted_at IS NULL AND r.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
        $rma_params = [$date_from, $date_to];
        if ($location_id) { $rma_where .= ' AND r.location_id = ?'; $rma_params[] = $location_id; }
        if ($brand_id)    { $rma_where .= ' AND db2.id = ?';        $rma_params[] = $brand_id; }

        $rep_where = "j.deleted_at IS NULL AND j.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
        $rep_params = [$date_from, $date_to];
        if ($location_id) { $rep_where .= ' AND r.location_id = ?'; $rep_params[] = $location_id; }
        if ($brand_id)    { $rep_where .= ' AND db2.id = ?';        $rep_params[] = $brand_id; }

        $metric = [__('reports.metric'), __('reports.value')];
        $count  = [__('reports.brand'), __('reports.count')];
        $sections = [];

        if ($tab === 'rma') {
            $total = (int) db_val("SELECT COUNT(*) FROM rma_requests r {$join} WHERE {$rma_where}", $rma_params);
            $open  = (int) db_val("SELECT COUNT(*) FROM rma_requests r {$join} JOIN rma_statuses s ON s.id=r.status_id WHERE {$rma_where} AND s.is_terminal=0", $rma_params);
            $avg   = db_val("SELECT ROUND(AVG(DATEDIFF(COALESCE(r.updated_at,NOW()),r.created_at)),1) FROM rma_requests r {$join} WHERE {$rma_where}", $rma_params);
            $sections[] = ['title'=>__('reports.summary'), 'columns'=>$metric, 'rows'=>[
                [__('reports.total_rmas'), $total], [__('reports.open'), $open],
                [__('reports.closed'), $total - $open], [__('reports.avg_days_open'), $avg ?? '—'],
            ]];
            $rows = db_rows("SELECT s.label, COUNT(*) cnt FROM rma_requests r {$join} JOIN rma_statuses s ON s.id=r.status_id WHERE {$rma_where} GROUP BY s.id ORDER BY cnt DESC", $rma_params);
            $sections[] = ['title'=>__('reports.by_status'), 'columns'=>[__('label.status'), __('reports.count')], 'rows'=>array_map(fn($r)=>[$r['label'], (int)$r['cnt']], $rows)];
            $rows = db_rows("SELECT COALESCE(db2.name,'Unknown') brand, COUNT(*) cnt FROM rma_requests r {$join} WHERE {$rma_where} GROUP BY db2.id ORDER BY cnt DESC", $rma_params);
            $sections[] = ['title'=>__('reports.by_brand'), 'columns'=>$count, 'rows'=>array_map(fn($r)=>[$r['brand'], (int)$r['cnt']], $rows)];
            // l.name must be in GROUP BY: Postgres only lets you select a column
            // you didn't group by when it's functionally dependent on the
            // grouping key, and r.location_id is a column of a different table.
            // MySQL accepted it, which is why this survived the port.
            $rows = db_rows("SELECT l.name, COUNT(*) cnt FROM rma_requests r {$join} LEFT JOIN locations l ON l.id=r.location_id WHERE {$rma_where} GROUP BY r.location_id, l.name ORDER BY cnt DESC", $rma_params);
            $sections[] = ['title'=>__('reports.by_location'), 'columns'=>[__('label.location'), __('reports.count')], 'rows'=>array_map(fn($r)=>[$r['name'] ?? '—', (int)$r['cnt']], $rows)];
            // Partner branches. Inner join on the branch: RMAs without one are
            // walk-ins, not an unnamed branch. Section is skipped when empty so
            // exports don't carry an empty table before branches are in use.
            $rows = db_rows("SELECT p.name partner_name, pb.name branch_name, COUNT(*) cnt FROM rma_requests r {$join} JOIN partner_branches pb ON pb.id=r.partner_branch_id LEFT JOIN partners p ON p.id=pb.partner_id WHERE {$rma_where} GROUP BY pb.id, pb.name, p.name ORDER BY cnt DESC", $rma_params);
            if ($rows) {
                $sections[] = ['title'=>__('reports.by_branch'),
                               'columns'=>[__('rma.partner'), __('partners.branch'), __('reports.count')],
                               'rows'=>array_map(fn($r)=>[$r['partner_name'] ?? '—', $r['branch_name'], (int)$r['cnt']], $rows)];
            }
            $rows = db_rows("SELECT MIN(DATE_FORMAT(r.created_at,'%b %Y')) label, COUNT(*) cnt FROM rma_requests r {$join} WHERE {$rma_where} GROUP BY DATE_FORMAT(r.created_at,'%Y-%m') ORDER BY MIN(r.created_at)", $rma_params);
            $sections[] = ['title'=>__('reports.monthly_trend'), 'columns'=>[__('reports.month'), __('reports.rmas')], 'rows'=>array_map(fn($r)=>[$r['label'], (int)$r['cnt']], $rows)];

        } elseif ($tab === 'repairs') {
            $total = (int) db_val("SELECT COUNT(*) FROM repair_jobs j JOIN rma_requests r ON r.id=j.rma_id {$join} WHERE {$rep_where}", $rep_params);
            $done  = (int) db_val("SELECT COUNT(*) FROM repair_jobs j JOIN rma_requests r ON r.id=j.rma_id {$join} WHERE {$rep_where} AND j.completed_at IS NOT NULL", $rep_params);
            $avg   = db_val("SELECT ROUND(AVG(DATEDIFF(j.completed_at,j.created_at)),1) FROM repair_jobs j JOIN rma_requests r ON r.id=j.rma_id {$join} WHERE {$rep_where} AND j.completed_at IS NOT NULL", $rep_params);
            $sections[] = ['title'=>__('reports.summary'), 'columns'=>$metric, 'rows'=>[
                [__('reports.total_repairs'), $total], [__('reports.completed'), $done],
                [__('reports.in_progress'), $total - $done], [__('reports.avg_days_close'), $avg ?? '—'],
            ]];
            $rows = db_rows("SELECT COALESCE(u.name,'Unassigned') tech, COUNT(*) total, SUM(CASE WHEN j.completed_at IS NOT NULL THEN 1 ELSE 0 END) done, ROUND(AVG(CASE WHEN j.completed_at IS NOT NULL THEN DATEDIFF(j.completed_at,j.created_at) END),1) avg_days FROM repair_jobs j JOIN rma_requests r ON r.id=j.rma_id {$join} LEFT JOIN users u ON u.id=j.technician_id WHERE {$rep_where} GROUP BY j.technician_id, u.name ORDER BY total DESC", $rep_params);
            $sections[] = ['title'=>__('reports.by_technician'), 'columns'=>[__('rma.technician'), __('label.total'), __('reports.done'), __('reports.avg_days')], 'rows'=>array_map(fn($r)=>[$r['tech'], (int)$r['total'], (int)$r['done'], $r['avg_days'] ?? '—'], $rows)];
            $rows = db_rows("SELECT COALESCE(db2.name,'Unknown') brand, COUNT(*) cnt FROM repair_jobs j JOIN rma_requests r ON r.id=j.rma_id {$join} WHERE {$rep_where} GROUP BY db2.id ORDER BY cnt DESC", $rep_params);
            $sections[] = ['title'=>__('reports.by_brand'), 'columns'=>$count, 'rows'=>array_map(fn($r)=>[$r['brand'], (int)$r['cnt']], $rows)];
            $rows = db_rows("SELECT COALESCE(dm.name,'Unknown') model, COALESCE(db2.name,'') brand, COUNT(*) cnt FROM repair_jobs j JOIN rma_requests r ON r.id=j.rma_id {$join} WHERE {$rep_where} GROUP BY d.model_id, dm.name, db2.name ORDER BY cnt DESC LIMIT 10", $rep_params);
            $sections[] = ['title'=>__('reports.top_models'), 'columns'=>[__('reports.model'), __('reports.brand'), __('reports.count')], 'rows'=>array_map(fn($r)=>[$r['model'], $r['brand'], (int)$r['cnt']], $rows)];
            $rows = db_rows("SELECT MIN(DATE_FORMAT(j.created_at,'%b %Y')) label, COUNT(*) cnt FROM repair_jobs j JOIN rma_requests r ON r.id=j.rma_id {$join} WHERE {$rep_where} GROUP BY DATE_FORMAT(j.created_at,'%Y-%m') ORDER BY MIN(j.created_at)", $rep_params);
            $sections[] = ['title'=>__('reports.monthly_trend'), 'columns'=>[__('reports.month'), __('reports.repairs')], 'rows'=>array_map(fn($r)=>[$r['label'], (int)$r['cnt']], $rows)];

        } else { // parts
            $pu_where = "pu.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)";
            $pu_params = [$date_from, $date_to];
            $used   = (int) db_val("SELECT COALESCE(SUM(pu.quantity),0) FROM part_usage pu WHERE {$pu_where}", $pu_params);
            $uniq   = (int) db_val("SELECT COUNT(DISTINCT pu.part_id) FROM part_usage pu WHERE {$pu_where}", $pu_params);
            $low    = db_rows("SELECT p.name, p.internal_sku, ps.quantity stock, p.min_stock FROM parts p JOIN parts_stock ps ON ps.part_id=p.id WHERE p.deleted_at IS NULL AND ps.quantity <= p.min_stock ORDER BY ps.quantity ASC");
            $sections[] = ['title'=>__('reports.summary'), 'columns'=>$metric, 'rows'=>[
                [__('reports.parts_used'), $used], [__('reports.unique_parts'), $uniq], [__('reports.low_stock_items'), count($low)],
            ]];
            $rows = db_rows("SELECT p.name, p.internal_sku, SUM(pu.quantity) qty FROM part_usage pu JOIN parts p ON p.id=pu.part_id WHERE {$pu_where} GROUP BY pu.part_id, p.name, p.internal_sku ORDER BY qty DESC LIMIT 15", $pu_params);
            $sections[] = ['title'=>__('reports.most_used_parts'), 'columns'=>[__('reports.part'), 'SKU', __('reports.qty')], 'rows'=>array_map(fn($r)=>[$r['name'], $r['internal_sku'] ?? '—', (int)$r['qty']], $rows)];
            $sections[] = ['title'=>__('reports.low_stock_alert'), 'columns'=>[__('reports.part'), __('reports.stock'), __('reports.min')], 'rows'=>array_map(fn($r)=>[$r['name'], (int)$r['stock'], (int)$r['min_stock']], $low)];
            $rows = db_rows("SELECT MIN(DATE_FORMAT(pu.created_at,'%b %Y')) label, SUM(pu.quantity) qty FROM part_usage pu WHERE {$pu_where} GROUP BY DATE_FORMAT(pu.created_at,'%Y-%m') ORDER BY MIN(pu.created_at)", $pu_params);
            $sections[] = ['title'=>__('reports.monthly_usage'), 'columns'=>[__('reports.month'), __('reports.qty_used')], 'rows'=>array_map(fn($r)=>[$r['label'], (int)$r['qty']], $rows)];
        }

        if ($tab === 'repeat') {
            $rows = $this->repeat_repair_rows($rma_where, $rma_params);

            $by_model = [];
            foreach ($rows as $r) {
                $k = trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? '')) ?: '—';
                $by_model[$k]['count'] = ($by_model[$k]['count'] ?? 0) + 1;
                $by_model[$k]['days'][] = (int)$r['days'];
            }
            uasort($by_model, fn($a, $b) => $b['count'] <=> $a['count']);

            $sections[] = ['title' => __('reports.repeat_count'), 'columns' => $metric, 'rows' => [
                [__('reports.repeat_count'),  count($rows)],
                [__('reports.repeat_window'), repeat_repair_window()],
            ]];

            // By model first: three of one model coming back points at a part
            // or a procedure, which is the reason this report exists.
            $sections[] = [
                'title'   => __('reports.repeat_by_model'),
                'columns' => [__('rma.model'), __('reports.repeat_count'), __('reports.repeat_fastest')],
                'rows'    => array_map(
                    fn($model, $g) => [$model, $g['count'], min($g['days'])],
                    array_keys($by_model), array_values($by_model)
                ),
            ];

            $sections[] = [
                'title'   => __('reports.repeat_each'),
                'columns' => [__('label.rma'), __('rma.model'), __('rma.imei') . ' / ' . __('rma.sn'),
                              __('reports.repeat_previous'), __('reports.repeat_days'), __('pdf.works_done')],
                'rows'    => array_map(fn($r) => [
                    $r['rma_number'],
                    trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? '')) ?: '—',
                    (string)$r['ident'],
                    (string)$r['prev_rma'],
                    (int)$r['days'],
                    trim((string)($r['prev_works'] ?? '')),
                ], $rows),
            ];
        }

        $tab_labels = ['rma'=>__('nav.rma'), 'repairs'=>__('reports.repairs'), 'parts'=>__('nav.parts'), 'repeat'=>__('settings.repeat')];
        $title = __('nav.reports') . ' — ' . $tab_labels[$tab];
        $meta  = __('reports.from') . ': ' . format_date($date_from) . '   ' . __('reports.to') . ': ' . format_date($date_to);
        $fname = 'report-' . $tab . '-' . $date_from . '_' . $date_to;

        if ($format === 'xls') {
            $this->export_xls($fname, $title, $meta, $sections);
        } else {
            $this->export_pdf($fname, $title, $meta, $sections);
        }
    }

    /**
     * Real .xlsx via PhpSpreadsheet.
     *
     * This used to emit an HTML table named ".xls" with an Excel MIME type — an
     * old trick that Excel now greets with "the file format and extension don't
     * match… could be corrupted or unsafe", which is alarming on a file the user
     * just asked for. It also gave every cell a text type, so numbers would not
     * sum. PhpSpreadsheet is already a dependency (goods-receipt import/export).
     */
    /**
     * Real .xlsx via PhpSpreadsheet, laid out to match the sample Rajo adjusted
     * by hand (2026-08-10):
     *
     *   - Segoe UI 11, 25pt row height, vertically centred, no gridlines
     *   - column A is a narrow left margin; the table lives in B..E
     *   - a section's first column stretches across the spare columns so the
     *     figures always line up in the same right-hand columns, whatever the
     *     section's shape (2, 3 or 4 columns)
     *   - horizontal rules and an outline, but no vertical lines inside: the
     *     sample's borders read as a list rather than a grid
     *
     * It used to emit an HTML table named ".xls", which modern Excel rejects
     * with "the file format and extension don't match", and which made every
     * value text so nothing could be summed.
     */
    private function export_xls(string $fname, string $title, string $meta, array $sections): void {
        require_once ROOT . '/vendor/autoload.php';

        $Coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;
        $Fill  = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $Align = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;
        $Type  = \PhpOffice\PhpSpreadsheet\Cell\DataType::class;

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Segoe UI')->setSize(11);
        $ss->getDefaultStyle()->getAlignment()
           ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        $sh = $ss->getActiveSheet();
        $sh->setShowGridlines(false);   // the table draws its own rules
        // Sheet named after the report, without the date range — as in the
        // sample. Excel caps sheet names at 31 characters.
        $sheet_name = preg_replace('/-\d{4}-\d{2}-\d{2}_\d{4}-\d{2}-\d{2}$/', '', $fname);
        $sh->setTitle(mb_substr($sheet_name, 0, 31) ?: 'Report');

        // The table spans B..E; A is a narrow left margin and F trails it.
        $FIRST = 2;   // B
        $LAST  = 5;   // E
        foreach (['A' => 5.63, 'B' => 30.63, 'C' => 20.63, 'D' => 20.63, 'E' => 20.63, 'F' => 8.73] as $c => $w) {
            $sh->getColumnDimension($c)->setWidth($w);
        }

        $L = fn(int $i): string => $Coord::stringFromColumnIndex($i);

        // Which cells a row of $n values occupies: [[startIdx, endIdx], ...].
        // The first entry absorbs the spare width, so the figures stay in the
        // same columns no matter how many the section has.
        $slots = function (int $n) use ($FIRST, $LAST): array {
            $width = $LAST - $FIRST + 1;
            if ($n >= $width) {
                $out = [];
                for ($i = 0; $i < $n; $i++) $out[] = [$FIRST + $i, $FIRST + $i];
                return $out;
            }
            $out = [[$FIRST, $LAST - ($n - 1)]];
            for ($i = $n - 1; $i >= 1; $i--) {
                $col   = $LAST - $i + 1;
                $out[] = [$col, $col];
            }
            return $out;
        };

        $row = 2;                                   // the sample starts on row 2
        $sh->getRowDimension($row)->setRowHeight(25);
        $sh->setCellValue('B' . $row, $title);
        $sh->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;

        $sh->getRowDimension($row)->setRowHeight(25);
        $sh->setCellValue('B' . $row, $meta);
        $row += 2;                                  // blank spacer row

        foreach ($sections as $sec) {
            $cols  = $sec['columns'] ?? [];
            $n     = max(1, count($cols));
            $map   = $slots($n);
            $right = max($LAST, $FIRST + $n - 1);
            $endL  = $L($right);

            // Section title, spanning the table width.
            $sh->getRowDimension($row)->setRowHeight(25);
            $sh->setCellValue('B' . $row, $sec['title']);
            $sh->mergeCells("B{$row}:{$endL}{$row}");
            $sh->getStyle("B{$row}:{$endL}{$row}")->getFont()->setBold(true);
            $sh->getStyle("B{$row}:{$endL}{$row}")->getFill()
               ->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('E8E8E8');
            $sh->getStyle('B' . $row)->getAlignment()->setHorizontal($Align::HORIZONTAL_LEFT);
            $this->xls_rule($sh, $row, $FIRST, $right);
            $row++;

            // Column headers.
            $sh->getRowDimension($row)->setRowHeight(25);
            foreach ($cols as $i => $label) {
                [$a, $b] = $map[$i];
                $ref = $L($a) . $row;
                $sh->setCellValue($ref, $label);
                if ($b > $a) $sh->mergeCells($L($a) . $row . ':' . $L($b) . $row);
                $sh->getStyle($ref)->getFont()->setBold(true);
                $sh->getStyle($L($a) . $row . ':' . $L($b) . $row)->getFill()
                   ->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
                $sh->getStyle($ref)->getAlignment()
                   ->setHorizontal($i === 0 ? $Align::HORIZONTAL_LEFT : $Align::HORIZONTAL_CENTER);
            }
            $this->xls_rule($sh, $row, $FIRST, $right);
            $row++;

            // Data rows.
            foreach ($sec['rows'] as $r) {
                $sh->getRowDimension($row)->setRowHeight(25);
                foreach (array_values($r) as $i => $cell) {
                    if (!isset($map[$i])) continue;
                    [$a, $b] = $map[$i];
                    $ref = $L($a) . $row;

                    // Numbers stay numeric so Excel can sum them; anything else
                    // is written as text so "01" or an RMA number survives.
                    if (is_int($cell) || is_float($cell)
                        || (is_string($cell) && is_numeric($cell) && !preg_match('/^0\d/', $cell))) {
                        $sh->setCellValue($ref, $cell + 0);
                    } else {
                        $sh->setCellValueExplicit($ref, (string) $cell, $Type::TYPE_STRING);
                    }

                    if ($b > $a) $sh->mergeCells($L($a) . $row . ':' . $L($b) . $row);
                    $sh->getStyle($ref)->getAlignment()
                       ->setHorizontal($i === 0 ? $Align::HORIZONTAL_LEFT : $Align::HORIZONTAL_CENTER);
                }
                $this->xls_rule($sh, $row, $FIRST, $right);
                $row++;
            }

            $row++;   // blank row between sections
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fname . '.xlsx"');
        header('Cache-Control: max-age=0');

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        exit;
    }

    /**
     * Rule above and below a row plus the table's outer edges. No vertical
     * lines inside, matching the sample.
     */
    private function xls_rule(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sh,
                              int $row, int $first, int $last): void {
        $Border = \PhpOffice\PhpSpreadsheet\Style\Border::class;
        $Coord  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;

        $a = $Coord::stringFromColumnIndex($first) . $row;
        $b = $Coord::stringFromColumnIndex($last) . $row;

        $borders = $sh->getStyle("{$a}:{$b}")->getBorders();
        $borders->getTop()->setBorderStyle($Border::BORDER_THIN);
        $borders->getBottom()->setBorderStyle($Border::BORDER_THIN);
        $borders->getLeft()->setBorderStyle($Border::BORDER_THIN);
        $borders->getRight()->setBorderStyle($Border::BORDER_THIN);
    }

    private function export_pdf(string $fname, string $title, string $meta, array $sections): void {
        $html = $this->report_html($title, $meta, $sections);
        if (setting('pdf_engine', 'html') === 'mpdf' && file_exists(ROOT . '/vendor/autoload.php')) {
            require_once ROOT . '/vendor/autoload.php';
            $mpdf = new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','tempDir'=>ROOT.'/storage/tmp',
                'margin_top'=>12,'margin_bottom'=>12,'margin_left'=>10,'margin_right'=>10]);
            $mpdf->SetTitle($title);
            $mpdf->WriteHTML($html);
            $mpdf->Output($fname . '.pdf', 'D');
        } else {
            // HTML engine: print-ready page the user saves as PDF from the browser.
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
        }
        exit;
    }

    private function report_html(string $title, string $meta, array $sections): string {
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $body = '';
        foreach ($sections as $sec) {
            $body .= '<h3>' . $h($sec['title']) . '</h3><table><thead><tr>';
            foreach ($sec['columns'] as $c) $body .= '<th>' . $h($c) . '</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($sec['rows'] as $row) {
                $body .= '<tr>';
                foreach ($row as $cell) $body .= '<td>' . $h($cell) . '</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table>';
        }
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $h($title) . '</title>'
            . '<link href="/assets/css/fonts.css" rel="stylesheet"><style>'
            . 'body{font-family:Montserrat,system-ui,Arial,sans-serif;color:#2c2c2a;font-size:12px;padding:24px;}'
            . 'h1{font-size:18px;margin:0 0 4px;}.meta{color:#888;font-size:12px;margin-bottom:18px;}'
            . 'h3{font-size:13px;margin:18px 0 6px;color:#444;}'
            . 'table{border-collapse:collapse;width:100%;margin-bottom:8px;}'
            . 'th,td{border:0.5px solid #ccc;padding:5px 8px;text-align:left;font-size:12px;}th{background:#f4f4f0;}'
            . '@media print{.no-print{display:none;}}'
            . '</style></head><body>'
            . '<div class="no-print" style="margin-bottom:16px;"><button onclick="window.print()" style="background:#1D9E75;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font:inherit;">' . $h(__('reports.print_save_pdf')) . '</button></div>'
            . '<h1>' . $h($title) . '</h1><div class="meta">' . $h($meta) . '</div>' . $body
            . '</body></html>';
    }
}
