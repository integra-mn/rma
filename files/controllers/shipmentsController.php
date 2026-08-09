<?php
defined('RMS') or die('Direct access not permitted');

class ShipmentsController {

    // Global logistics view: every shipment across all RMAs, defaulting to the
    // ones still in motion (pending / shipped / in transit).
    public function index(): void {
        require_login();
        require_permission('shipments', 'view');

        $page_title = __('ship.list_title');
        $filter     = $_GET['status'] ?? 'active';
        $search     = trim($_GET['q'] ?? '');

        $where  = 'r.deleted_at IS NULL';
        $params = [];

        if ($filter === 'active') {
            $where .= " AND sh.status IN ('pending','shipped','in_transit')";
        } elseif (in_array($filter, SHIPMENT_STATUSES, true)) {
            $where   .= ' AND sh.status = ?';
            $params[] = $filter;
        } // 'all' → no status filter

        if ($search !== '') {
            $where   .= ' AND (sh.tracking_number LIKE ? OR r.rma_number LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Partners only ever see shipments for their own RMAs.
        if ((current_user()['role'] ?? '') === 'partner') {
            $where   .= ' AND r.partner_id = ?';
            $params[] = current_partner_id() ?? 0;
        }

        $shipments = db_rows(
            "SELECT sh.*, r.rma_number, c.name AS courier_name, c.tracking_url AS courier_tracking_url
             FROM delivery_shipments sh
             JOIN rma_requests r ON r.id = sh.rma_id
             LEFT JOIN couriers c ON c.id = sh.courier_id
             WHERE {$where}
             ORDER BY (sh.dispatched_at IS NULL), sh.dispatched_at DESC, sh.created_at DESC",
            $params
        );

        // Live search / filter: return just the results fragment.
        if (($_GET['ajax'] ?? '') === '1') {
            include views_path('shipments/_list.php');
            return;
        }

        include views_path('layout/header.php');
        include views_path('shipments/index.php');
        include views_path('layout/footer.php');
    }
}
