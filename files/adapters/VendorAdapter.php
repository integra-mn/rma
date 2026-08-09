<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Common shape every vendor integration implements. Keep this interface
 * minimal — only the operations the app actually needs. Extend later as
 * we light up more workflows (parts order, diagnostics, etc.).
 *
 * Implementations live under files/adapters/<VendorName>/ and are wired
 * up via the `vendor_adapters.adapter_class` column.
 */
abstract class VendorAdapter {

    /** Vendor row (from `vendors` table) + adapter row (from `vendor_adapters`). */
    protected array $vendor;
    protected array $config;

    public function __construct(array $vendor, array $config) {
        $this->vendor = $vendor;
        $this->config = $config;
    }

    /**
     * Warranty / coverage lookup for a single device identifier.
     *
     * @param string $identifier  IMEI (15 digits) or serial number.
     * @return array {
     *     status: 'covered' | 'not_covered' | 'expired' | 'unknown',
     *     expiry_date: string|null,   // YYYY-MM-DD
     *     product: string|null,       // human-friendly product description
     *     coverage_label: string|null,// e.g. "AppleCare+"
     *     purchase_date: string|null,
     *     activation_locked: bool|null,
     *     find_my_active: bool|null,
     *     raw: array                  // full vendor response, for audit
     * }
     */
    abstract public function warrantyLookup(string $identifier): array;

    /**
     * Create a repair with the vendor. Returns the vendor's reference
     * number (GSX repair number, Samsung RMA#, etc.) or null on failure.
     *
     * @return array {
     *     success: bool,
     *     vendor_ref: string|null,
     *     ra_number: string|null,
     *     message: string|null,
     *     raw: array
     * }
     */
    abstract public function createRepair(array $rma): array;

    /**
     * Pull the current status of a previously-submitted vendor repair.
     * Useful for the status poller.
     */
    abstract public function getRepairStatus(string $vendor_ref): array;

    /**
     * Quick connectivity check — used by Admin → Integrations → "Test".
     */
    abstract public function ping(): array;
}
