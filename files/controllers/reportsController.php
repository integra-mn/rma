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
        }

        include views_path('layout/header.php');
        include views_path('reports/index.php');
        include views_path('layout/footer.php');
    }

    // ── Export current report tab to XLS (Excel) or PDF ──────────────────
    public function export(): void {
        require_login();
        require_permission('reports', 'view');

        $tab         = in_array($_GET['tab'] ?? 'rma', ['rma','repairs','parts'], true) ? $_GET['tab'] : 'rma';
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

        $tab_labels = ['rma'=>__('nav.rma'), 'repairs'=>__('reports.repairs'), 'parts'=>__('nav.parts')];
        $title = __('nav.reports') . ' — ' . $tab_labels[$tab];
        $meta  = __('reports.from') . ': ' . format_date($date_from) . '   ' . __('reports.to') . ': ' . format_date($date_to);
        $fname = 'report-' . $tab . '-' . $date_from . '_' . $date_to;

        if ($format === 'xls') {
            $this->export_xls($fname, $title, $meta, $sections);
        } else {
            $this->export_pdf($fname, $title, $meta, $sections);
        }
    }

    private function export_xls(string $fname, string $title, string $meta, array $sections): void {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fname . '.xls"');
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>';
        echo '<h2>' . $h($title) . '</h2><p>' . $h($meta) . '</p>';
        foreach ($sections as $sec) {
            echo '<table border="1" cellspacing="0" cellpadding="4"><tr><td colspan="' . count($sec['columns']) . '" style="background:#e8e8e8;font-weight:bold;">' . $h($sec['title']) . '</td></tr><tr>';
            foreach ($sec['columns'] as $c) echo '<th style="background:#f5f5f5;">' . $h($c) . '</th>';
            echo '</tr>';
            foreach ($sec['rows'] as $row) {
                echo '<tr>';
                foreach ($row as $cell) echo '<td>' . $h($cell) . '</td>';
                echo '</tr>';
            }
            echo '</table><br>';
        }
        echo '</body></html>';
        exit;
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
