<?php
defined('RMS') or die('Direct access not permitted');

class GoodsReceiptController {

    // ── List ──────────────────────────────────────────────────

    public function index(): void {
        require_login();
        require_permission('parts', 'create');
        header('Location: /parts?tab=receipts');
        exit;
    }

    // ── Create draft ──────────────────────────────────────────

    public function store(): void {
        require_login();
        require_permission('parts', 'create');

        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $location_id = (int)($_POST['location_id'] ?? 0);

        if (!$supplier_id || !$location_id) {
            $_SESSION['form_error'] = __('parts.receipt_fields_required');
            header('Location: /parts/receipts');
            exit;
        }

        $id = db_insert('goods_receipts', [
            'supplier_id'        => $supplier_id,
            'location_id'        => $location_id,
            'reference'          => trim($_POST['reference'] ?? ''),
            'freight_cost'       => (float)str_replace(',', '.', $_POST['freight_cost'] ?? '0'),
            'default_margin_pct' => (float)str_replace(',', '.', $_POST['default_margin_pct'] ?? '0'),
            'notes'              => trim($_POST['notes'] ?? ''),
            'status'             => 'draft',
            'created_by'         => current_user_id(),
        ]);

        audit('created', 'goods_receipt', $id);
        header("Location: /parts/receipts/{$id}");
        exit;
    }

    // ── View / edit draft ─────────────────────────────────────

    public function view(string $id): void {
        require_login();
        require_permission('parts', 'create');

        $receipt = $this->get_receipt((int)$id);
        if (!$receipt) { http_response_code(404); include views_path('errors/404.php'); return; }

        $items    = $this->get_items((int)$id);
        $parts    = db_rows('SELECT id, name, internal_sku FROM parts WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name');
        $page_title = __('parts.receipt') . ' #' . $id;
        $success  = $_SESSION['form_success'] ?? null;
        $error    = $_SESSION['form_error'] ?? null;
        unset($_SESSION['form_success'], $_SESSION['form_error']);

        include views_path('layout/header.php');
        include views_path('parts/receipt_view.php');
        include views_path('layout/footer.php');
    }

    // ── Canonical template columns ────────────────────────────
    //
    // The batch-upload template contains exactly these columns, in this order.
    // Uploaded files must have the same headers (case-insensitive, whitespace
    // trimmed) — no extras, no missing. This prevents "import any XLS" mishaps.

    private const TEMPLATE_COLUMNS = [
        'name'           => ['required' => true,  'label' => 'Part name',           'width' => 36],
        'supplier_sku'   => ['required' => false, 'label' => "Supplier SKU",         'width' => 18],
        'quantity'       => ['required' => true,  'label' => 'Quantity',             'width' => 12],
        'supplier_price' => ['required' => true,  'label' => 'Supplier price (EUR)', 'width' => 20],
        'customs_pct'    => ['required' => false, 'label' => 'Customs %',            'width' => 12],
        'margin_pct'     => ['required' => false, 'label' => 'Margin % (optional)',  'width' => 18],
    ];

    // ── Download template (XLSX) ──────────────────────────────

    public function template(string $id): void {
        require_login();
        require_permission('parts', 'create');

        $receipt = $this->get_receipt((int)$id);
        if (!$receipt) { http_response_code(404); echo 'Receipt not found'; return; }

        require_once ROOT . '/vendor/autoload.php';

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Items');

        $col = 'A';
        foreach (self::TEMPLATE_COLUMNS as $key => $meta) {
            $sh->setCellValue("{$col}1", $key);
            $sh->getColumnDimension($col)->setWidth($meta['width']);
            // Header style — bold, gray background, bottom border
            $sh->getStyle("{$col}1")->getFont()->setBold(true);
            $sh->getStyle("{$col}1")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('EEECE5');
            $col++;
        }

        $last_col = chr(ord('A') + count(self::TEMPLATE_COLUMNS) - 1);
        $sh->getStyle("A1:{$last_col}1")->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sh->freezePane('A2');

        $filename = 'integra-goods-receipt-' . (int)$id . '-template.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
        $writer->save('php://output');
        exit;
    }

    // ── Import XLSX (strict) ──────────────────────────────────

    public function import(string $id): void {
        require_login();
        require_permission('parts', 'create');

        $receipt = $this->get_receipt((int)$id);
        if (!$receipt || $receipt['status'] !== 'draft') {
            $_SESSION['form_error'] = __('parts.receipt_not_found');
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            $_SESSION['form_error'] = __('parts.no_file');
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        $file = $_FILES['import_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'xlsx') {
            $_SESSION['form_error'] = __('parts.only_xlsx');
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        // Strict-parse: header set must match the template exactly.
        [$rows, $parse_error] = $this->parse_xlsx_strict($file['tmp_name']);
        if ($parse_error !== null) {
            $_SESSION['form_error'] = $parse_error;
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        if (empty($rows)) {
            $_SESSION['form_error'] = __('parts.file_empty');
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        // Clear existing draft items
        db()->prepare('DELETE FROM goods_receipt_items WHERE receipt_id = ?')->execute([(int)$id]);

        $total_units = array_sum(array_column($rows, 'quantity'));

        foreach ($rows as $row) {
            $supplier_sku = trim((string)($row['supplier_sku'] ?? ''));
            $name         = trim((string)($row['name'] ?? ''));

            $match = match_part_by_sku('', $supplier_sku, $name);
            $part_id = $match['part']['id'] ?? null;

            $freight_per_unit = $total_units > 0
                ? (float)$receipt['freight_cost'] / $total_units
                : 0;

            $supplier_price = (float)$row['supplier_price'];
            $cif            = $supplier_price + $freight_per_unit;
            $customs        = (float)($row['customs_pct'] ?? 0);
            $cost_price     = $cif * (1 + $customs / 100);
            $margin         = $row['margin_pct'] !== null && $row['margin_pct'] !== ''
                              ? (float)$row['margin_pct']
                              : (float)$receipt['default_margin_pct'];
            $unit_price     = $cost_price * (1 + $margin / 100);

            db_insert('goods_receipt_items', [
                'receipt_id'      => (int)$id,
                'part_id'         => $part_id,
                'part_name_raw'   => $name,
                'sku_raw'         => $supplier_sku,
                'quantity'        => max(1, (int)$row['quantity']),
                'supplier_price'  => round($supplier_price, 4),
                'customs_duty_pct'=> round($customs, 2),
                'cost_price'      => round($cost_price, 4),
                'margin_pct'      => round($margin, 2),
                'unit_price'      => round($unit_price, 2),
            ]);
        }

        $_SESSION['form_success'] = __('parts.lines_imported', ['count'=>count($rows)]);
        header("Location: /parts/receipts/{$id}");
        exit;
    }

    // ── Update item prices ────────────────────────────────────

    public function update_items(string $id): void {
        require_login();
        require_permission('parts', 'create');

        $receipt = $this->get_receipt((int)$id);
        if (!$receipt || $receipt['status'] !== 'draft') {
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        $items     = $_POST['items'] ?? [];
        $total_units = array_sum(array_column($this->get_items((int)$id), 'quantity'));

        foreach ($items as $item_id => $data) {
            $item_id     = (int)$item_id;
            $supplier_p  = (float)str_replace(',', '.', $data['supplier_price'] ?? '0');
            $customs_pct = (float)str_replace(',', '.', $data['customs_duty_pct'] ?? '0');
            $qty         = (int)($data['quantity'] ?? 1);
            $margin_pct  = (float)str_replace(',', '.', $data['margin_pct'] ?? $receipt['default_margin_pct']);
            $override    = isset($data['unit_price_override']) && $data['unit_price_override'] !== ''
                         ? (float)str_replace(',', '.', $data['unit_price_override'])
                         : null;

            $freight_per_unit = $total_units > 0
                ? (float)$receipt['freight_cost'] / $total_units
                : 0;

            $cif        = $supplier_p + $freight_per_unit;
            $cost_price = $cif * (1 + $customs_pct / 100);
            $unit_price = $override ?? round($cost_price * (1 + $margin_pct / 100), 2);

            db_update('goods_receipt_items', [
                'part_id'             => (int)($data['part_id'] ?? 0) ?: null,
                'quantity'            => max(1, $qty),
                'supplier_price'      => round($supplier_p, 4),
                'customs_duty_pct'    => round($customs_pct, 2),
                'cost_price'          => round($cost_price, 4),
                'margin_pct'          => round($margin_pct, 2),
                'unit_price'          => round($unit_price, 2),
                'unit_price_override' => $override,
            ], 'id = ? AND receipt_id = ?', [$item_id, (int)$id]);
        }

        // Update freight on receipt header if changed
        if (isset($_POST['freight_cost'])) {
            db_update('goods_receipts', [
                'freight_cost'       => (float)str_replace(',', '.', $_POST['freight_cost']),
                'default_margin_pct' => (float)str_replace(',', '.', $_POST['default_margin_pct'] ?? '0'),
            ], 'id = ?', [(int)$id]);
        }

        $_SESSION['form_success'] = __('parts.prices_updated');
        header("Location: /parts/receipts/{$id}");
        exit;
    }

    // ── Confirm receipt ───────────────────────────────────────

    public function confirm(string $id): void {
        require_login();
        require_permission('parts', 'create');

        $receipt = $this->get_receipt((int)$id);
        if (!$receipt || $receipt['status'] !== 'draft') {
            $_SESSION['form_error'] = __('parts.receipt_not_found');
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        $items = $this->get_items((int)$id);
        if (empty($items)) {
            $_SESSION['form_error'] = __('parts.receipt_no_items');
            header("Location: /parts/receipts/{$id}");
            exit;
        }

        foreach ($items as $item) {
            if (!$item['part_id']) {
                $_SESSION['form_error'] = __('parts.part_not_matched', ['name'=>$item['part_name_raw']]);
                header("Location: /parts/receipts/{$id}");
                exit;
            }
        }

        $total_units = array_sum(array_column($items, 'quantity'));

        foreach ($items as $item) {
            $part_id     = (int)$item['part_id'];
            $location_id = (int)$receipt['location_id'];
            $qty         = (int)$item['quantity'];
            $sell_price  = (float)($item['unit_price_override'] ?? $item['unit_price']);
            $new_cost    = (float)$item['cost_price'];

            // Auto-create part if unmatched
            if (!$part_id) {
                $internal_sku = generate_internal_sku(null);
                $part_id = db_insert('parts', [
                    'name'         => $item['part_name_raw'],
                    'internal_sku' => $internal_sku,
                    'supplier_sku' => $item['sku_raw'] ?: null,
                    'unit_price'   => round($sell_price, 2),
                    'cost_price'   => round($new_cost, 4),
                    'is_active'    => 1,
                ]);
                // Update item with new part_id
                db_update('goods_receipt_items', ['part_id' => $part_id], 'id = ?', [(int)$item['id']]);
            }

            // Calculate weighted average cost
            $stock = db_row('SELECT * FROM parts_stock WHERE part_id = ? AND location_id = ?',
                            [$part_id, $location_id]);
            $existing_qty  = (int)($stock['quantity'] ?? 0);
            $existing_cost = (float)(db_val('SELECT cost_price FROM parts WHERE id = ?', [$part_id]) ?? 0);

            $wac = $existing_qty > 0
                ? (($existing_qty * $existing_cost) + ($qty * $new_cost)) / ($existing_qty + $qty)
                : $new_cost;

            // Update part cost_price (WAC) — unit_price only if override provided
            $price_update = ['cost_price' => round($wac, 4)];
            if ($item['unit_price_override'] !== null) {
                $price_update['unit_price'] = round($sell_price, 2);
            }
            db_update('parts', $price_update, 'id = ?', [$part_id]);

            // Update or create stock
            $stock = db_row('SELECT * FROM parts_stock WHERE part_id = ? AND location_id = ?',
                            [$part_id, $location_id]);
            if ($stock) {
                db_update('parts_stock',
                    ['quantity' => $stock['quantity'] + $qty],
                    'part_id = ? AND location_id = ?',
                    [$part_id, $location_id]
                );
            } else {
                db_insert('parts_stock', [
                    'part_id'     => $part_id,
                    'location_id' => $location_id,
                    'quantity'    => $qty,
                ]);
            }

            // Log stock movement
            db_insert('stock_movements', [
                'part_id'        => $part_id,
                'location_id'    => $location_id,
                'type'           => 'receive',
                'quantity'       => $qty,
                'reference_type' => 'goods_receipt',
                'reference_id'   => (int)$id,
                'reason'         => 'Goods receipt #' . $id . ' — ' . ($receipt['reference'] ?? ''),
                'created_by'     => current_user_id(),
            ]);
        }

        db_update('goods_receipts', [
            'status'       => 'confirmed',
            'confirmed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$id]);

        audit('confirmed', 'goods_receipt', (int)$id);
        $_SESSION['form_success'] = __('parts.receipt_confirmed');
        header("Location: /parts/receipts/{$id}");
        exit;
    }

    // ── Strict XLSX parser ────────────────────────────────────
    //
    // Returns [rows, error]. `error` is a human-readable string if the file
    // doesn't conform to the template (missing/extra/misspelled headers, bad
    // values in required cells), otherwise null.
    //
    // Headers must match self::TEMPLATE_COLUMNS exactly, case-insensitively,
    // in any order. Empty trailing rows are skipped.

    private function parse_xlsx_strict(string $path): array {
        require_once ROOT . '/vendor/autoload.php';

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $data = $reader->load($path)->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return [[], 'Could not read the .xlsx file. Make sure it is a valid Excel workbook.'];
        }

        if (empty($data) || empty($data[0])) {
            return [[], 'File is empty. Download the template and try again.'];
        }

        // Header validation: normalise (trim + lowercase), then compare sets.
        $header = array_map(
            fn($h) => strtolower(trim((string)$h)),
            $data[0]
        );
        $header = array_values(array_filter($header, fn($h) => $h !== ''));

        $expected = array_keys(self::TEMPLATE_COLUMNS);
        $missing  = array_diff($expected, $header);
        $extra    = array_diff($header, $expected);

        if ($missing || $extra) {
            $parts = [];
            if ($missing) $parts[] = 'missing: ' . implode(', ', $missing);
            if ($extra)   $parts[] = 'unexpected: ' . implode(', ', $extra);
            return [[], 'Column headers do not match the template (' . implode('; ', $parts)
                        . '). Download the template and use it as-is.'];
        }

        // Build rows keyed by canonical column name, enforce required fields.
        $rows = [];
        for ($i = 1, $n = count($data); $i < $n; $i++) {
            $raw = $data[$i];
            // Skip entirely empty rows.
            $has_any = false;
            foreach ($raw as $cell) {
                if (trim((string)$cell) !== '') { $has_any = true; break; }
            }
            if (!$has_any) continue;

            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = $raw[$idx] ?? null;
            }

            // Coerce numbers: Excel may return ints/floats directly, but also
            // accept comma decimals from hand-typed values ("1,25" → 1.25).
            $row['supplier_price'] = $this->to_float($row['supplier_price'] ?? null);
            $row['customs_pct']    = $this->to_float($row['customs_pct']    ?? null);
            $row['margin_pct']     = ($row['margin_pct'] ?? '') === ''
                                     ? null
                                     : $this->to_float($row['margin_pct']);
            $row['quantity']       = (int)($row['quantity'] ?? 0);
            $row['name']           = trim((string)($row['name'] ?? ''));
            $row['supplier_sku']   = trim((string)($row['supplier_sku'] ?? ''));

            // Per-row required validation.
            $errs = [];
            if ($row['name'] === '')           $errs[] = 'name is empty';
            if ($row['quantity'] <= 0)         $errs[] = 'quantity must be > 0';
            if ($row['supplier_price'] <= 0)   $errs[] = 'supplier_price must be > 0';

            if ($errs) {
                $rowno = $i + 1; // 1-based like Excel, header is row 1
                return [[], "Row {$rowno}: " . implode(', ', $errs) . '.'];
            }

            $rows[] = $row;
        }

        return [$rows, null];
    }

    private function to_float($v): float {
        if ($v === null || $v === '') return 0.0;
        if (is_numeric($v)) return (float)$v;
        return (float)str_replace([' ', ','], ['', '.'], (string)$v);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function get_receipt(int $id): ?array {
        return db_row("SELECT gr.*, s.name as supplier_name, l.name as location_name
                       FROM goods_receipts gr
                       JOIN suppliers s ON s.id = gr.supplier_id
                       JOIN locations l ON l.id = gr.location_id
                       WHERE gr.id = ?", [$id]);
    }

    private function get_items(int $receipt_id): array {
        return db_rows("SELECT gri.*, p.name as part_name_matched, p.internal_sku as part_sku_matched
                        FROM goods_receipt_items gri
                        LEFT JOIN parts p ON p.id = gri.part_id
                        WHERE gri.receipt_id = ?
                        ORDER BY gri.id", [$receipt_id]);
    }
}
