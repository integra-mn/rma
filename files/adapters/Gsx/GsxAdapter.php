<?php
defined('RMS') or die('Direct access not permitted');

require_once __DIR__ . '/GsxClient.php';

/**
 * Apple GSX adapter.
 *
 * The exact GSX endpoint paths, request schemas, and response shapes are
 * covered by Apple's NDA. This class intentionally keeps those specifics
 * in ONE place — private helpers on GsxClient — so an AASP with real API
 * docs can fill them in without touching the rest of the app.
 *
 * Until those real endpoints are wired in, warrantyLookup() returns a
 * structured mock response so the UI flow can be developed end-to-end.
 */
class GsxAdapter extends VendorAdapter {

    private GsxClient $client;

    public function __construct(array $vendor, array $config) {
        parent::__construct($vendor, $config);
        $this->client = new GsxClient($config);
    }

    // ── Warranty lookup ───────────────────────────────────────────────────
    public function warrantyLookup(string $identifier): array {
        // Normalise — GSX accepts both IMEI (15 digits) and serial (10-12
        // alphanumeric). Strip anything else.
        $id = preg_replace('/[^A-Z0-9]/i', '', strtoupper($identifier));

        $resp = $this->client->deviceWarranty($id);

        if (!$resp['ok']) {
            return [
                'status'            => 'unknown',
                'expiry_date'       => null,
                'product'           => null,
                'coverage_label'    => null,
                'purchase_date'     => null,
                'activation_locked' => null,
                'find_my_active'    => null,
                'raw'               => $resp,
            ];
        }

        // The GsxClient returns a normalised payload so callers don't have
        // to care about GSX's internal field names. See GsxClient::parse().
        $d = $resp['data'];
        return [
            'status'            => $d['coverage_status']   ?? 'unknown',
            'expiry_date'       => $d['coverage_expires']  ?? null,
            'product'           => $d['product_desc']      ?? null,
            'coverage_label'    => $d['coverage_label']    ?? null,
            'purchase_date'     => $d['purchase_date']     ?? null,
            'activation_locked' => $d['activation_locked'] ?? null,
            'find_my_active'    => $d['find_my_active']    ?? null,
            'raw'               => $d['raw'] ?? $d,
        ];
    }

    // ── Repair submission ─────────────────────────────────────────────────
    public function createRepair(array $rma): array {
        $resp = $this->client->createRepair($rma);
        return [
            'success'    => !empty($resp['ok']),
            'vendor_ref' => $resp['data']['repair_number'] ?? null,
            'ra_number'  => $resp['data']['ra_number']     ?? null,
            'message'    => $resp['message']               ?? null,
            'raw'        => $resp,
        ];
    }

    public function getRepairStatus(string $vendor_ref): array {
        return $this->client->repairStatus($vendor_ref);
    }

    // ── Health check ──────────────────────────────────────────────────────
    public function ping(): array {
        return $this->client->ping();
    }
}
