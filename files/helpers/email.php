<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Send RMA receipt email to customer with QR code tracking link
 */
function send_rma_receipt(int $rma_id): bool {
    $rma = db_row("SELECT r.*,
                          c.name as customer_name, c.email as customer_email,
                          s.label as status_label,
                          dm.name as model_name, db2.name as brand_name,
                          d.serial_number,
                          l.name as location_name, l.phone as location_phone, l.email as location_email
                   FROM rma_requests r
                   JOIN rma_statuses s ON s.id = r.status_id
                   LEFT JOIN customers c ON c.id = r.customer_id
                   LEFT JOIN devices d ON d.id = r.device_id
                   LEFT JOIN device_models dm ON dm.id = d.model_id
                   LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                   LEFT JOIN locations l ON l.id = r.location_id
                   WHERE r.id = ?", [$rma_id]);

    if (!$rma || empty($rma['customer_email'])) return false;

    // Get tracking token
    $token = db_val('SELECT token FROM rma_tracking_tokens WHERE rma_id = ?', [$rma_id]);
    if (!$token) return false;

    $tracking_url = $token ? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rma.integra.mn') . '/track/' . $token : '';
    $qr_base64    = $tracking_url ? generate_qr_base64($tracking_url, 180) : '';

    $subject = 'RMA Receipt — ' . $rma['rma_number'];
    $body    = build_receipt_html($rma, $tracking_url, $qr_base64);

    return send_email($rma['customer_email'], $rma['customer_name'], $subject, $body);
}

/**
 * Build HTML receipt email body
 */
function build_receipt_html(array $rma, string $tracking_url, string $qr_base64): string {
    $device = trim(($rma['brand_name'] ?? '') . ' ' . ($rma['model_name'] ?? ''));
    $date   = format_date($rma['created_at']);

    return "<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f0; margin: 0; padding: 20px; }
  .wrap { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; }
  .header { background: #1A1A1F; padding: 24px 32px; }
  .header img { height: 28px; }
  .body { padding: 32px; }
  .rma-number { font-size: 28px; font-weight: 600; color: #1D9E75; margin: 0 0 4px; }
  .meta { font-size: 13px; color: #888780; margin-bottom: 24px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  td { padding: 10px 0; border-bottom: 0.5px solid #e8e6e0; font-size: 14px; }
  td:first-child { color: #888780; width: 140px; }
  .qr-section { text-align: center; padding: 24px; background: #f4f4f0; border-radius: 8px; margin-bottom: 24px; }
  .qr-section p { font-size: 13px; color: #5f5e5a; margin: 12px 0 4px; }
  .qr-section a { font-size: 12px; color: #1D9E75; word-break: break-all; }
  .footer { padding: 20px 32px; background: #f4f4f0; font-size: 12px; color: #888780; text-align: center; }
</style>
</head>
<body>
<div class='wrap'>
  <div class='header'>
    <span style='color:#fff;font-size:18px;font-weight:600;letter-spacing:0.02em;'>Integra</span>
  </div>
  <div class='body'>
    <p style='font-size:13px;color:#888780;margin:0 0 8px;'>RMA Receipt</p>
    <p class='rma-number'>{$rma['rma_number']}</p>
    <p class='meta'>Submitted on {$date} · {$rma['location_name']}</p>

    <table>
      <tr><td>Customer</td><td><strong>" . htmlspecialchars($rma['customer_name'] ?? '—') . "</strong></td></tr>
      " . ($device ? "<tr><td>Device</td><td>" . htmlspecialchars($device) . "</td></tr>" : '') . "
      " . ($rma['serial_number'] ? "<tr><td>Serial</td><td style='font-family:monospace;'>" . htmlspecialchars($rma['serial_number']) . "</td></tr>" : '') . "
      <tr><td>Status</td><td>" . htmlspecialchars($rma['status_label']) . "</td></tr>
      " . ($rma['is_warranty'] ? "<tr><td>Warranty</td><td>Yes</td></tr>" : '') . "
      " . ($rma['estimated_completion'] ? "<tr><td>Est. completion</td><td>" . format_date($rma['estimated_completion']) . "</td></tr>" : '') . "
    </table>

    <div class='qr-section'>
      " . ($qr_base64 ? "<img src='{$qr_base64}' width='150' height='150' alt='QR Code'>" : '') . "
      <p>Scan to track your repair</p>
      <a href='" . htmlspecialchars($tracking_url) . "'>" . htmlspecialchars($tracking_url) . "</a>
    </div>

    <p style='font-size:13px;color:#5f5e5a;'>
      If you have any questions, please contact us at
      " . htmlspecialchars($rma['location_phone'] ?? '') . "
      " . ($rma['location_email'] ? "or <a href='mailto:" . htmlspecialchars($rma['location_email']) . "'>" . htmlspecialchars($rma['location_email']) . "</a>" : '') . "
    </p>
  </div>
  <div class='footer'>
    &copy; " . date('Y') . " Integra d.o.o. &nbsp;·&nbsp; This is an automated message.
  </div>
</div>
</body>
</html>";
}

/**
 * Send email via configured SMTP or PHP mail()
 */
function send_email(string $to, string $to_name, string $subject, string $html_body, array $attachments = []): bool {
    return send_email_result($to, $to_name, $subject, $html_body, $attachments)['ok'];
}

/**
 * Send an email, returning ['ok' => bool, 'error' => string].
 *
 * Uses PHPMailer (vendored) rather than hand-rolled SMTP so that authentication
 * failures actually surface, TLS certificates are verified, UTF-8 subjects are
 * encoded correctly (Montenegrin diacritics), and headers cannot be injected via
 * customer-supplied names.
 *
 * $attachments: list of ['path' => …, 'name' => …] or ['data' => …, 'name' => …].
 * Add 'cid' => 'some-id' to embed the image in the message body instead of
 * attaching it — reference it as <img src="cid:some-id">. Needed for logos:
 * mail clients block remote images and do not render SVG, and this app is not
 * publicly reachable, so a normal URL would show a broken image.
 */
function send_email_result(string $to, string $to_name, string $subject, string $html_body, array $attachments = []): array {
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $host     = setting('smtp_host', '');
    $port     = (int) setting('smtp_port', 587);
    $user     = setting('smtp_user', '');
    $pass     = setting('smtp_pass', '');
    $from     = setting('smtp_from', '');
    $fromname = setting('smtp_from_name', 'Integra RMA');
    $enc      = setting('smtp_encryption', 'tls');

    if ($host === '' || $from === '') {
        return ['ok' => false, 'error' => __('settings.smtp_not_configured')];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => __('settings.smtp_invalid_email')];
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);   // true = throw on error
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->Timeout    = 15;

        if ($user !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
        }

        // Verified TLS. The CA bundle must be configured (curl.cainfo /
        // openssl.cafile on Windows; ca-certificates on Linux).
        $mail->SMTPSecure = match ($enc) {
            'ssl'   => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
            'none'  => '',
            default => PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
        };
        if ($enc === 'none') $mail->SMTPAutoTLS = false;

        // Strip CR/LF so a crafted customer name cannot inject headers.
        $clean = static fn(string $v): string => trim(preg_replace('/\s+/u', ' ', $v));

        $mail->setFrom($from, $clean($fromname));
        $mail->addAddress($to, $clean($to_name));
        $mail->addReplyTo($from, $clean($fromname));

        $mail->isHTML(true);
        $mail->Subject = $clean($subject);           // PHPMailer MIME-encodes this
        $mail->Body    = $html_body;
        $mail->AltBody = trim(html_entity_decode(strip_tags($html_body), ENT_QUOTES, 'UTF-8'));

        foreach ($attachments as $a) {
            $name = $clean($a['name'] ?? (!empty($a['path']) ? basename($a['path']) : 'attachment'));
            if (!empty($a['cid']) && !empty($a['path']) && is_readable($a['path'])) {
                // Shown inside the message body, not listed as an attachment.
                $mail->addEmbeddedImage($a['path'], $a['cid'], $name);
            } elseif (!empty($a['path']) && is_readable($a['path'])) {
                $mail->addAttachment($a['path'], $name);
            } elseif (isset($a['data'])) {
                $mail->addStringAttachment($a['data'], $name);
            }
        }

        $mail->send();
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        // ErrorInfo carries the SMTP conversation detail; the exception message
        // alone is often just "SMTP Error: Could not authenticate."
        $detail = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        error_log('Email send failed: ' . $detail);
        return ['ok' => false, 'error' => $detail];
    }
}
