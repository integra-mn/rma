<?php
defined('RMS') or die('Direct access not permitted');

require_once __DIR__ . '/TclClient.php';

/**
 * TCL Smart Care adapter.
 *
 * Only warrantyLookup() is real. The other three operations belong to TCL's
 * repair-order API, which needs the RC symptom and solution codes — and those
 * are not in TCL's documents at all. They download from the portal as a
 * spreadsheet (Customer Service -> TCT SCS Tool -> RC Symptom and Solution).
 * Until that arrives, createRepair() would have to invent codes, so it says
 * plainly that it is not built rather than sending TCL something made up.
 */
class TclAdapter extends VendorAdapter {

    private TclClient $client;

    public function __construct(array $vendor, array $config) {
        parent::__construct($vendor, $config);
        $this->client = new TclClient($config);
    }

    // ── Warranty lookup ──────────────────────────────────────────────────

    public function warrantyLookup(string $identifier): array {
        $id = preg_replace('/[^A-Z0-9]/i', '', strtoupper($identifier));

        // The device's own purchase date, when the counter has recorded one.
        // TCL's manual: with it they date the warranty from the sale, without
        // it from the registration, and failing that from the day the device
        // left their warehouse — which can be a year out.
        $pop = db_val(
            'SELECT purchase_date FROM devices
              WHERE (UPPER(imei) = ? OR UPPER(serial_number) = ?) AND purchase_date IS NOT NULL
              ORDER BY id DESC LIMIT 1',
            [$id, $id]
        );

        $res = $this->client->imeiQuery($id, $pop ?: null);
        $row = $res['body'][0] ?? null;   // one identifier in, one row back

        if (!$res['ok'] || !is_array($row)) {
            return $this->unknown($res['error'] ?? $this->client->lastError(), $res['body']);
        }
        // Per-identifier result, separate from the HTTP status: TCL answers 200
        // and reports an unknown IMEI inside the row.
        if ((int)($row['resultCode'] ?? 0) !== 0) {
            return $this->unknown($row['resultDesc'] ?? 'IMEI not found', $row);
        }

        return [
            'status'            => $this->coverage($row),
            'expiry_date'       => $this->date($row['WarrantyExpireOn'] ?? null),
            'product'           => $this->product($row),
            'coverage_label'    => $row['WarrantyStatusText'] ?? null,
            // Registration is the closest TCL holds to a sale date. DeliveryDate
            // is when the device left them, so it is deliberately not used here:
            // it would overwrite a real receipt date with a wholesale one.
            'purchase_date'     => $this->date($row['RegisterDate'] ?? null),
            'activation_locked' => null,   // TCL does not report either
            'find_my_active'    => null,
            'raw'               => $row,
        ];
    }

    // ── Not built yet ────────────────────────────────────────────────────

    public function createRepair(array $rma): array {
        return ['success' => false, 'vendor_ref' => null, 'ra_number' => null,
                'message' => __('vendor.tcl_repair_not_ready'), 'raw' => []];
    }

    public function getRepairStatus(string $vendor_ref): array {
        return ['success' => false, 'message' => __('vendor.tcl_repair_not_ready'), 'raw' => []];
    }

    /** Administracija -> Integrations "Test". A login proves the credentials. */
    public function ping(): array {
        if (!$this->client->isConfigured()) {
            return ['ok' => false, 'message' => __('vendor.tcl_no_credentials')];
        }
        // Their own sample IMEI: a real lookup, no repair order created, and
        // the answer proves the whole chain — RSA, login, ticket, and whatever
        // the ticket header turns out to be.
        $res = $this->client->imeiQuery('358128870247894');

        return [
            'ok'      => (bool)$res['ok'],
            'status'  => $res['status'] ?? null,
            'message' => $res['ok'] ? __('vendor.tcl_ok')
                                    : ($res['error'] ?: ($this->client->lastError() ?: 'HTTP ' . $res['status'])),
            // What TCL sent back, so a failure is diagnosable from the log
            // rather than from a second run with a debugger attached.
            'body'    => $res['body'] ?? null,
        ];
    }

    // ── Response reading ─────────────────────────────────────────────────

    /**
     * TCL answers the warranty question three ways at once: WarrantyStatus,
     * WarrantyStatusText and WarrantyEndStatus. The text is the one they show
     * their own users, so it leads; the flag decides when the text is absent.
     */
    private function coverage(array $row): string {
        $text = strtolower((string)($row['WarrantyStatusText'] ?? ''));
        if (str_contains($text, 'in warranty'))  return 'covered';
        if (str_contains($text, 'out'))          return 'expired';

        if ((string)($row['WarrantyStatus'] ?? '') === '1') return 'covered';

        // An expiry in the past answers it whatever the flags say.
        $exp = $this->date($row['WarrantyExpireOn'] ?? null);
        if ($exp) return $exp >= date('Y-m-d') ? 'covered' : 'expired';

        return 'unknown';
    }

    private function product(array $row): ?string {
        $parts = array_filter([$row['ProductName'] ?? null, $row['CommercialReference'] ?? null]);
        return $parts ? implode(' · ', $parts) : null;
    }

    /** "2024-05-24T23:00:00" -> "2024-05-24". Nulls and junk stay null. */
    private function date(?string $v): ?string {
        if (!$v) return null;
        $ts = strtotime($v);
        // TCL sends 0001-01-01 for "never set" — a date, and a meaningless one.
        return ($ts && (int)date('Y', $ts) > 1900) ? date('Y-m-d', $ts) : null;
    }

    private function unknown(?string $message, mixed $raw): array {
        return [
            'status' => 'unknown', 'expiry_date' => null, 'product' => null,
            'coverage_label' => null, 'purchase_date' => null,
            'activation_locked' => null, 'find_my_active' => null,
            'message' => $message,
            'raw' => is_array($raw) ? $raw : ['response' => $raw],
        ];
    }
}
