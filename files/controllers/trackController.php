<?php
defined('RMS') or die('Direct access not permitted');

class TrackController {

    // ── Show verify form or tracking page ─────────────────────

    public function view(string $token): void {
        $rma = $this->rma_by_token($token);
        if (!$rma) {
            http_response_code(404);
            include views_path('track/not_found.php');
            return;
        }

        // Check lockout
        if ($this->is_locked($token)) {
            include views_path('track/locked.php');
            return;
        }

        // Already verified this session?
        $verified_key = 'track_verified_' . $token;
        if (!empty($_SESSION[$verified_key])) {
            $this->show_tracking($rma, $token);
            return;
        }

        $error = $_SESSION['track_error'] ?? null;
        unset($_SESSION['track_error']);
        include views_path('track/verify.php');
    }

    // ── Handle verification POST ──────────────────────────────

    public function verify(string $token): void {
        $rma = $this->rma_by_token($token);
        if (!$rma) { http_response_code(404); return; }

        if ($this->is_locked($token)) {
            include views_path('track/locked.php');
            return;
        }

        $input = trim($_POST['identifier'] ?? '');
        if (!$input) {
            $_SESSION['track_error'] = __('track.not_found');
            header("Location: /track/{$token}");
            exit;
        }

        // Normalize and compare
        $matched = $this->matches_rma($rma, $input);

        if (!$matched) {
            $this->record_attempt($token);
            $attempts = $this->attempt_count($token);
            if ($attempts >= 3) {
                $this->lock($token);
                include views_path('track/locked.php');
                return;
            }
            $_SESSION['track_error'] = __('track.not_found');
            header("Location: /track/{$token}");
            exit;
        }

        // Verified
        $_SESSION['track_verified_' . $token] = true;
        $this->clear_attempts($token);
        header("Location: /track/{$token}");
        exit;
    }

    // ── Render tracking page ──────────────────────────────────

    private function show_tracking(array $rma, string $token): void {
        $history  = db_rows("SELECT h.*, s.code as status_code, s.label as status_label, s.color as status_color
                              FROM rma_status_history h
                              JOIN rma_statuses s ON s.id = h.status_id
                              WHERE h.rma_id = ? ORDER BY h.created_at ASC", [(int)$rma['id']]);

        $shipment = db_row("SELECT * FROM delivery_shipments
                            WHERE rma_id = ? AND direction = 'inbound'
                            ORDER BY created_at DESC LIMIT 1", [(int)$rma['id']]);

        // Return (outbound) shipment — shown to the customer with a tracking link
        // when their repaired device is on its way back.
        $return_shipment = db_row(
            "SELECT sh.*, c.name AS courier_name, c.tracking_url AS courier_tracking_url
             FROM delivery_shipments sh LEFT JOIN couriers c ON c.id = sh.courier_id
             WHERE sh.rma_id = ? AND sh.direction = 'outbound'
             ORDER BY sh.created_at DESC LIMIT 1", [(int)$rma['id']]);

        $invoice  = db_row("SELECT * FROM invoices
                            WHERE rma_id = ? AND deleted_at IS NULL
                            ORDER BY created_at DESC LIMIT 1", [(int)$rma['id']]);

        $comments = db_rows(
            "SELECT c.body, c.created_at, c.source, c.user_id,
                    u.name AS author_name, u.role AS author_role
             FROM rma_comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.rma_id = ? AND c.visibility = 'customer' AND c.deleted_at IS NULL
             ORDER BY c.created_at ASC",
            [(int)$rma['id']]
        );

        $repair_jobs = db_rows(
            "SELECT j.*, s.label as status_label, s.color as status_color,
                    u.name as technician_name
             FROM repair_jobs j
             LEFT JOIN rma_statuses s ON s.id = j.status_id
             LEFT JOIN users u           ON u.id = j.technician_id
             WHERE j.rma_id = ? AND j.deleted_at IS NULL
             ORDER BY j.created_at ASC",
            [(int)$rma['id']]
        );

        // Evidence photos for this RMA — either attached directly (reception
        // stage) or via one of the RMA's repair_jobs (repair stage).
        $photos = db_rows(
            "SELECT re.id, re.filename, re.original_name, re.stage, re.created_at
             FROM repair_evidence re
             LEFT JOIN repair_jobs j ON j.id = re.repair_job_id
             WHERE (re.rma_id = ? OR j.rma_id = ?)
               AND re.deleted_at IS NULL
             ORDER BY re.created_at ASC",
            [(int)$rma['id'], (int)$rma['id']]
        );

        // Visibility settings
        $vis = $this->visibility();

        include views_path('track/view.php');
    }

    // ── Helpers ───────────────────────────────────────────────

    private function rma_by_token(string $token): ?array {
        return db_row("SELECT r.*,
                              s.code as status_code, s.label as status_label, s.color as status_color,
                              c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.lang as customer_lang,
                              dm.name as model_name, db2.name as brand_name,
                              d.serial_number, d.imei
                       FROM rma_tracking_tokens t
                       JOIN rma_requests r ON r.id = t.rma_id
                       JOIN rma_statuses s ON s.id = r.status_id
                       LEFT JOIN customers c ON c.id = r.customer_id
                       LEFT JOIN devices d ON d.id = r.device_id
                       LEFT JOIN device_models dm ON dm.id = d.model_id
                       LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                       WHERE t.token = ? AND r.deleted_at IS NULL", [$token]);
    }

    private function matches_rma(array $rma, string $input): bool {
        $input = strtolower(trim($input));

        // Email match
        $email = strtolower(trim($rma['customer_email'] ?? ''));
        if ($email && $email === $input) return true;

        // Phone match — strip all non-digits, compare last 8 digits
        $clean_input = preg_replace('/\D/', '', $input);
        $clean_phone = preg_replace('/\D/', '', $rma['customer_phone'] ?? '');
        if ($clean_phone && strlen($clean_input) >= 6 &&
            str_ends_with($clean_phone, substr($clean_input, -8))) return true;

        return false;
    }

    // ── Lockout (DB-backed via auth_attempts) ────────────────────
    //
    // Uses the existing auth_attempts table with an identifier namespaced
    // as "track:<token-hash>". This survives a browser restart / cookie
    // clear, unlike the previous session-only counter.
    private const TRACK_MAX_ATTEMPTS = 3;
    private const TRACK_LOCKOUT_MIN  = 30;

    private function track_identifier(string $token): string {
        return 'track:' . hash('sha256', $token);
    }

    private function is_locked(string $token): bool {
        return $this->attempt_count($token) >= self::TRACK_MAX_ATTEMPTS;
    }

    private function lock(string $token): void {
        // Final failed attempt is already recorded; flag it as blocked so
        // admins can distinguish "locked out" events in the audit log.
        db_insert('auth_attempts', [
            'identifier' => $this->track_identifier($token),
            'ip_address' => client_ip(),
            'success'    => 0,
            'blocked'    => 1,
        ]);
    }

    private function record_attempt(string $token): void {
        db_insert('auth_attempts', [
            'identifier' => $this->track_identifier($token),
            'ip_address' => client_ip(),
            'success'    => 0,
        ]);
    }

    private function attempt_count(string $token): int {
        $window = date('Y-m-d H:i:s', strtotime('-' . self::TRACK_LOCKOUT_MIN . ' minutes'));
        return (int) db_val(
            'SELECT COUNT(*) FROM auth_attempts
             WHERE identifier = ? AND success = 0 AND created_at >= ?',
            [$this->track_identifier($token), $window]
        );
    }

    private function clear_attempts(string $token): void {
        // Mark past failures as handled by inserting a success row; keep
        // history for forensics rather than deleting.
        db_insert('auth_attempts', [
            'identifier' => $this->track_identifier($token),
            'ip_address' => client_ip(),
            'success'    => 1,
        ]);
        // And age out counted failures so the visitor isn't still "locked":
        // set created_at into the past for any un-handled failures.
        // db_translate() by hand, because this is a raw prepare: db_row() and
        // friends translate for you, and going straight to PDO does not. On
        // Postgres the untranslated DATE_SUB reached the server verbatim and
        // threw, which is why the tracking page 500ed the moment somebody
        // entered a correct phone number.
        db()->prepare(db_translate(
            "UPDATE auth_attempts SET created_at = DATE_SUB(NOW(), INTERVAL ? MINUTE)
             WHERE identifier = ? AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        ))->execute([
            self::TRACK_LOCKOUT_MIN + 1,
            $this->track_identifier($token),
            self::TRACK_LOCKOUT_MIN,
        ]);
    }

    private function visibility(): array {
        $rows = db_rows("SELECT field_key, is_visible FROM tracking_visibility_settings
                         WHERE role IN ('customer','all')");
        $vis  = [];
        foreach ($rows as $r) {
            $vis[$r['field_key']] = (bool)$r['is_visible'];
        }
        // Defaults if not configured
        return array_merge([
            'status'         => true,
            'device'         => true,
            'est_completion' => true,
            'delivery'       => true,
            'invoice'        => false,
            'tech_notes'     => true,  // show findings + resolution to customers by default
        ], $vis);
    }
}
