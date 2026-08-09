<?php
defined('RMS') or die('Direct access not permitted');

require_once ROOT . '/adapters/VendorAdapter.php';

/**
 * Instantiate the adapter for a vendor row. Returns null if the vendor
 * has no configured adapter or the class file is missing.
 *
 * $user_id — when given, the user's personal credentials (from
 * user_vendor_credentials) are merged on top of the shop-wide defaults.
 * This is how per-tech credentials work for operations like repair
 * submission where Apple wants to know which certified tech filed it.
 */
function vendor_adapter(int $vendor_id, ?int $user_id = null): ?VendorAdapter {
    // Per-user adapters need their own cache slot — two techs calling the
    // same vendor should get different adapter instances with different creds.
    static $cache = [];
    $cache_key = $vendor_id . ':' . ($user_id ?: 0);
    if (array_key_exists($cache_key, $cache)) return $cache[$cache_key];

    $row = db_row(
        "SELECT v.id, v.name, v.slug, a.adapter_class, a.endpoint_url,
                a.auth_type, a.credentials, a.is_active
         FROM vendors v
         JOIN vendor_adapters a ON a.vendor_id = v.id
         WHERE v.id = ? AND v.is_active = 1 AND a.is_active = 1",
        [$vendor_id]
    );
    if (!$row) return $cache[$cache_key] = null;

    // If a user was supplied, overlay their per-user creds on top of the
    // shop-wide ones. Missing keys in the user block are inherited from
    // shop defaults (e.g. cert_path, endpoint_url usually stay shop-wide).
    if ($user_id) {
        $user_row = db_row(
            'SELECT credentials FROM user_vendor_credentials WHERE user_id = ? AND vendor_id = ?',
            [$user_id, $vendor_id]
        );
        if ($user_row && !empty($user_row['credentials'])) {
            $shop_creds = json_decode((string)$row['credentials'], true) ?: [];
            $user_creds = json_decode((string)$user_row['credentials'], true) ?: [];
            $merged     = array_merge($shop_creds, array_filter($user_creds, fn($v) => $v !== ''));
            $row['credentials'] = json_encode($merged);
        }
    }

    // Resolve class name -> file under files/adapters/. e.g. GsxAdapter
    // lives at adapters/Gsx/GsxAdapter.php; SamsungAdapter at
    // adapters/Samsung/SamsungAdapter.php. Simple convention avoids needing
    // an autoloader.
    $class = $row['adapter_class'];
    $dir   = preg_replace('/Adapter$/', '', $class);
    $file  = ROOT . '/adapters/' . $dir . '/' . $class . '.php';
    if (!is_readable($file) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) {
        return $cache[$cache_key] = null;
    }
    require_once $file;
    if (!class_exists($class)) return $cache[$cache_key] = null;

    return $cache[$cache_key] = new $class($row, $row);
}

/**
 * Does this user have personal credentials set for this vendor?
 * Used by the UI to tell techs "you need to set up your credentials
 * before you can submit repairs" vs displaying them as ready.
 */
function user_has_vendor_credentials(int $user_id, int $vendor_id): bool {
    return (bool) db_val(
        'SELECT 1 FROM user_vendor_credentials WHERE user_id = ? AND vendor_id = ?',
        [$user_id, $vendor_id]
    );
}

/**
 * Upsert a user's credentials for a vendor. $creds is merged with any
 * existing row so partial updates keep the rest intact. Pass a field with
 * an empty string to explicitly clear it.
 */
function user_vendor_credentials_save(int $user_id, int $vendor_id, array $creds): void {
    $existing = db_row(
        'SELECT id, credentials FROM user_vendor_credentials WHERE user_id = ? AND vendor_id = ?',
        [$user_id, $vendor_id]
    );
    $current = $existing ? (json_decode((string)$existing['credentials'], true) ?: []) : [];
    // Blank field = keep existing (matches the pattern used elsewhere for
    // secret fields like SMTP password).
    foreach ($creds as $k => $v) {
        if ($v === '' && isset($current[$k])) continue; // blank => keep
        $current[$k] = $v;
    }
    $json = json_encode($current);
    if ($existing) {
        db_update('user_vendor_credentials', ['credentials' => $json], 'id = ?', [(int)$existing['id']]);
    } else {
        db_insert('user_vendor_credentials', [
            'user_id'     => $user_id,
            'vendor_id'   => $vendor_id,
            'credentials' => $json,
        ]);
    }
}

function user_vendor_credentials_clear(int $user_id, int $vendor_id): void {
    db()->prepare('DELETE FROM user_vendor_credentials WHERE user_id = ? AND vendor_id = ?')
        ->execute([$user_id, $vendor_id]);
}

/**
 * Warranty lookup with read-through cache.
 *
 * Checks `vendor_warranty_cache` first, returns the cached row if it's
 * still fresh. Otherwise hits the adapter, stores the result, and logs
 * the call to `vendor_sync_log`.
 */
function vendor_warranty_lookup(int $vendor_id, string $identifier, int $ttl_hours = 24): ?array {
    $id = trim($identifier);
    if ($id === '') return null;

    $cached = db_row(
        "SELECT status, expiry_date, raw_response, cached_at
         FROM vendor_warranty_cache
         WHERE vendor_id = ? AND serial_number = ? AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1",
        [$vendor_id, $id]
    );
    if ($cached) {
        $raw = json_decode((string)$cached['raw_response'], true) ?: [];
        $raw['_cached_at'] = $cached['cached_at'];
        return $raw;
    }

    $adapter = vendor_adapter($vendor_id);
    if (!$adapter) return null;

    $result = $adapter->warrantyLookup($id);

    db_insert('vendor_warranty_cache', [
        'vendor_id'     => $vendor_id,
        'serial_number' => $id,
        'status'        => $result['status'] ?? 'unknown',
        'expiry_date'   => $result['expiry_date'] ?? null,
        'raw_response'  => json_encode($result),
        'expires_at'    => date('Y-m-d H:i:s', strtotime('+' . max(1, $ttl_hours) . ' hours')),
    ]);

    db_insert('vendor_sync_log', [
        'vendor_id'   => $vendor_id,
        'rma_id'      => null,
        'action'      => 'warranty_lookup',
        'request'     => json_encode(['identifier' => $id]),
        'response'    => json_encode($result),
        'http_status' => null,
        'success'     => ($result['status'] ?? 'unknown') !== 'unknown' ? 1 : 0,
    ]);

    return $result;
}
