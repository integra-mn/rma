<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Send RMA receipt email to customer with QR code tracking link
 */
function send_rma_receipt(int $rma_id): bool {
    $rma = db_row("SELECT r.*,
                          c.name as customer_name, c.email as customer_email, c.lang as customer_lang,
                          s.label as status_label,
                          dm.name as model_name, db2.name as brand_name,
                          d.serial_number,
                          l.name as location_name, l.phone as location_phone, l.email as location_email,
                          pa.name as partner_name, pa.email as partner_email,
                          pa.contact_person as partner_contact, pa.notify_customer as partner_notify_customer
                   FROM rma_requests r
                   JOIN rma_statuses s ON s.id = r.status_id
                   LEFT JOIN customers c ON c.id = r.customer_id
                   LEFT JOIN devices d ON d.id = r.device_id
                   LEFT JOIN device_models dm ON dm.id = d.model_id
                   LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                   LEFT JOIN locations l ON l.id = r.location_id
                   LEFT JOIN partners pa ON pa.id = r.partner_id
                   WHERE r.id = ?", [$rma_id]);

    if (!$rma) return false;

    // Get tracking token
    $token = db_val('SELECT token FROM rma_tracking_tokens WHERE rma_id = ?', [$rma_id]);
    if (!$token) return false;

    $tracking_url = $token ? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'rma.integra.mn') . '/track/' . $token : '';
    $qr_base64    = $tracking_url ? generate_qr_base64($tracking_url, 180) : '';

    // The customer's own language — never the staff member's. Falls back to ME
    // for records created before the field existed.
    $lang    = customer_lang($rma['customer_lang'] ?? null);
    $subject = __in($lang, 'receipt.subject', ['number' => $rma['rma_number']]);
    $body    = build_receipt_html($rma, $tracking_url, $qr_base64, $lang);

    $sent = false;
    foreach (rma_receipt_recipients($rma) as [$to, $name]) {
        if (send_email($to, $name, $subject, $body, email_logo_attachment())) $sent = true;
    }
    return $sent;
}

/**
 * Who hears about this RMA, as [address, name] pairs.
 *
 * A device brought in by a partner has two interested parties. The partner is
 * always told — their own email was on the table all along and never used for
 * this. The end user is told as well unless that partner is set to be the
 * single point of contact with their own customer.
 *
 * No partner means a walk-in: one party, the customer, exactly as before.
 *
 * A refused@ address is not an address. It is what reception records when
 * someone will not give an email, and until now the only guard here was
 * "is it empty" — so a receipt for a customer marked refused@apple.com was
 * addressed to apple.com, a real company, carrying that customer's repair
 * details. Placeholders are dropped, whoever holds them.
 */
function rma_receipt_recipients(array $rma): array {
    $out = [];
    $add = function (?string $email, ?string $name) use (&$out): void {
        $email = trim((string) $email);
        if ($email === '' || is_placeholder_email($email)) return;
        foreach ($out as [$have]) if (strcasecmp($have, $email) === 0) return;
        $out[] = [$email, $name ?: $email];
    };

    $has_partner = !empty($rma['partner_id']) && !empty($rma['partner_email']);
    if ($has_partner) {
        $add($rma['partner_email'], ($rma['partner_contact'] ?? '') ?: ($rma['partner_name'] ?? ''));
    }

    // Walk-in, or a partner who wants their customer kept in the loop.
    if (empty($rma['partner_id']) || (int) ($rma['partner_notify_customer'] ?? 1) === 1) {
        $add($rma['customer_email'], $rma['customer_name']);
    }

    // A partner set as sole contact but holding no email leaves nobody to tell.
    // Silence there looks identical to a successful send, so say it in the log.
    if (!$out) {
        error_log('RMA ' . ($rma['rma_number'] ?? '?') . ': nobody to email — '
                . 'partner=' . (($rma['partner_email'] ?? '') ?: 'none')
                . ' customer=' . (($rma['customer_email'] ?? '') ?: 'none'));
    }

    return $out;
}

/**
 * Build HTML receipt email body
 */
/**
 * The frame every email from this app shares: styles, dark header with the
 * company name, and the footer.
 *
 * Templates in Sabloni hold words, not markup. Anything typed there arrives
 * escaped and is dropped into $content here, so a stray tag cannot break the
 * message and nobody editing wording has to know that email HTML means tables
 * and inline styles rather than the HTML they know.
 *
 * The receipt builds its own $content and passes it through the same frame, so
 * a customer who gets a receipt and then a status update sees one sender rather
 * than two.
 */
/**
 * The frame every email from this app shares.
 *
 * Follows the tracking page and the login screen rather than inventing a look
 * of its own: the app background, the logo at 36px above a white card, and the
 * same one-line copyright beneath it. A customer who follows the tracking link
 * from an email should not feel they have arrived somewhere else.
 *
 * Templates in Sabloni hold words, not markup. Whatever is typed there arrives
 * escaped and is dropped into $content, so a stray tag cannot break the message
 * and nobody editing wording needs to know that email HTML means tables and
 * inline styles.
 *
 * The logo is embedded, not linked: mail clients block remote images and do not
 * render SVG. Callers must pass email_logo_attachment() to send_email() or the
 * image will not appear — see notification_email_html() and send_rma_receipt().
 */
function email_shell(string $content, string $lang = 'me'): string {
    $company = htmlspecialchars(company_name(), ENT_QUOTES, 'UTF-8');
    // The footer signs off with the legal name, matching the tracking page:
    // "Integra", not the customer-facing "Integra Service".
    $legal   = htmlspecialchars(company_legal_name(), ENT_QUOTES, 'UTF-8');

    // Montserrat first with web-safe fallbacks: mail clients cannot load web
    // fonts, so anyone without it installed lands on Arial rather than a serif.
    $font = "'Montserrat',-apple-system,'Segoe UI',Arial,sans-serif";

    return "<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<style>
  html, body { height: 100%; margin: 0; padding: 0; }
  body { font-family: {$font}; background: #F4F4F4; color: #2c2c2a; }
  /* Measured off the live tracking page — the verify screen a customer
     actually lands on, not the status page behind it, which is what I had been
     copying. There the card is 480px with 40px padding all round, the logo is
     40px high inside it with 48px beneath, and everything is centred. */
  .card { background: #fff; border: 0.5px solid #d3d1c7; border-radius: 12px;
          padding: 40px; text-align: center; }
  .logo-bar { margin-bottom: 48px; }
  .logo-bar img { margin: 0 auto; }
  .rma-number { font-size: 28px; font-weight: 600; color: #1D9E75; margin: 0 0 4px; }
  .meta { font-size: 13px; color: #888780; margin-bottom: 24px; }
  table.details { width: 100%; border-collapse: collapse; margin-bottom: 24px; text-align: left; }
  table.details td { padding: 10px 0; border-bottom: 0.5px solid #e8e6e0; font-size: 14px; }
  table.details td:first-child { color: #888780; width: 140px; }
  .qr-section { text-align: center; padding: 24px; background: #F4F4F4; border-radius: 8px; margin-bottom: 24px; }
  .qr-section p { font-size: 13px; color: #5f5e5a; margin: 12px 0 4px; }
  .qr-section a { font-size: 12px; color: #1D9E75; word-break: break-all; }
  .msg { font-size: 15px; line-height: 1.6; color: #2c2c2a; margin: 0 0 24px; }
  .cta { display: inline-block; background: #1D9E75; color: #fff !important; text-decoration: none;
         font-size: 14px; font-weight: 600; padding: 11px 22px; border-radius: 8px; }
  .footer { text-align: center; font-size: 12px; color: #888780; margin: 32px 0 0; }
</style>
</head>
<body>
<!-- Vertical centring in email only works through a full-height table cell with
     valign=middle: flexbox and margin:auto are not supported, and Outlook
     renders through Word, which ignores most CSS positioning. Where a client
     sizes the message to its content instead of a full pane — most webmail —
     this degrades to balanced padding, which is what you want there anyway. -->
<table role='presentation' width='100%' height='100%' cellpadding='0' cellspacing='0'
       style='height:100%;min-height:100%;background:#F4F4F4;'>
  <tr>
    <td align='center' valign='middle' style='padding:24px;'>
      <table role='presentation' width='100%' cellpadding='0' cellspacing='0'
             style='width:100%;max-width:480px;margin:0 auto;'>
        <tr>
          <td>
            <div class='card'>
              <div class='logo-bar'>
                <img src='cid:integralogo' alt='{$company}' height='40' style='height:40px;width:auto;display:inline-block;border:0;'>
              </div>
{$content}
            </div>
            <p class='footer'>&copy; " . date('Y') . " {$legal}</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>";
}

/**
 * The logo, ready to embed. Empty when the file is missing, so a send never
 * fails over a picture.
 */
function email_logo_attachment(): array {
    $logo = dirname(__DIR__) . '/assets/integra-email.png';
    return is_readable($logo)
        ? [['path' => $logo, 'cid' => 'integralogo', 'name' => 'integra.png']]
        : [];
}

function build_receipt_html(array $rma, string $tracking_url, string $qr_base64, string $lang = 'me'): string {
    $device = trim(($rma['brand_name'] ?? '') . ' ' . ($rma['model_name'] ?? ''));
    $date   = format_date($rma['created_at']);
    // Customers know the business by this name, not by the app's internal one.
    // Settings → General → Naziv firme.
    $company = htmlspecialchars(company_name(), ENT_QUOTES, 'UTF-8');

    // Short alias — this template calls it a dozen times.
    $t = fn(string $key, array $r = []): string => __in($lang, $key, $r);

    // Only the middle. The frame around it — styles, header, footer —
    // comes from email_shell(), so a receipt and a status update arrive
    // looking like the same sender.
    $content = "<p style='font-size:13px;color:#888780;margin:0 0 8px;'>" . $t('receipt.title') . "</p>
    <p class='rma-number'>{$rma['rma_number']}</p>
    <p class='meta'>" . $t('receipt.submitted', ['date' => $date, 'location' => $rma['location_name'] ?? '']) . "</p>

    <table class='details'>
      <tr><td>" . $t('receipt.customer') . "</td><td><strong>" . htmlspecialchars($rma['customer_name'] ?? '—') . "</strong></td></tr>
      " . ($device ? "<tr><td>" . $t('receipt.device') . "</td><td>" . htmlspecialchars($device) . "</td></tr>" : '') . "
      " . ($rma['serial_number'] ? "<tr><td>" . $t('receipt.serial') . "</td><td style='font-family:monospace;'>" . htmlspecialchars($rma['serial_number']) . "</td></tr>" : '') . "
      <tr><td>" . $t('receipt.status') . "</td><td>" . htmlspecialchars($rma['status_label']) . "</td></tr>
      " . ($rma['is_warranty'] ? "<tr><td>" . $t('receipt.warranty') . "</td><td>" . $t('receipt.yes') . "</td></tr>" : '') . "
      " . ($rma['estimated_completion'] ? "<tr><td>" . $t('receipt.est_completion') . "</td><td>" . format_date($rma['estimated_completion']) . "</td></tr>" : '') . "
    </table>

    <div class='qr-section'>
      " . ($qr_base64 ? "<img src='{$qr_base64}' width='150' height='150' alt='QR Code'>" : '') . "
      <p>" . $t('receipt.scan') . "</p>
      <a href='" . htmlspecialchars($tracking_url) . "'>" . htmlspecialchars($tracking_url) . "</a>
    </div>

    <p style='font-size:13px;color:#5f5e5a;'>
      " . $t('receipt.questions') . "
      " . htmlspecialchars($rma['location_phone'] ?? '') . "
      " . ($rma['location_email'] ? "" . $t('receipt.or') . " <a href='mailto:" . htmlspecialchars($rma['location_email']) . "'>" . htmlspecialchars($rma['location_email']) . "</a>" : '') . "
    </p>";

    return email_shell($content, $lang);
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

    // Iskljuceno means no email leaves the app — not merely that email stops
    // being offered for 2FA. Without this the switch only removed email from
    // the login screen while notifications and receipts kept going out, which
    // is not what "off" says.
    if (!setting_on('smtp_enabled', true)) {
        return ['ok' => false, 'error' => __('settings.smtp_disabled')];
    }
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

/**
 * Tell whoever should hear that an RMA has moved to a new status.
 *
 * Three separate decisions, each answered where it belongs:
 *   - the status says WHETHER this step is worth a message (rma_statuses.notify)
 *   - the grid in Podesavanja says WHICH CHANNELS reach customers and partners
 *   - the partner switch says whether that partner's customer hears anything
 *
 * Called after the status has been written, so a failure to notify can never
 * roll back the status change itself.
 */
function notify_rma_status(int $rma_id): void {
    $rma = db_row("SELECT r.rma_number, r.partner_id,
                          s.code as status_code, s.label as status_label,
                          s.label_me as status_label_me, s.notify as status_notify,
                          c.name as customer_name, c.email as customer_email,
                          c.phone as customer_phone, c.lang as customer_lang,
                          pa.name as partner_name, pa.email as partner_email,
                          pa.contact_person as partner_contact,
                          pa.notify_customer as partner_notify_customer
                   FROM rma_requests r
                   JOIN rma_statuses s ON s.id = r.status_id
                   LEFT JOIN customers c ON c.id = r.customer_id
                   LEFT JOIN partners pa ON pa.id = r.partner_id
                   WHERE r.id = ?", [$rma_id]);

    if (!$rma || empty($rma['status_notify'])) return;

    $token = db_val('SELECT token FROM rma_tracking_tokens WHERE rma_id = ?', [$rma_id]);
    $url   = $token ? 'https://rma.integra.mn/track/' . $token : '';

    // The customer reads their own language; a partner reads the app default.
    // Sabloni first, the language file when a status has no row yet. The code
    // is per status, so wording can differ between "come and collect it" and
    // "we need a decision" without either being a code change.
    $for = function (string $lang) use ($rma, $url): array {
        $status = status_label((string) $rma['status_code'], (string) $rma['status_label'], $lang);
        $code   = 'status.' . $rma['status_code'];
        $repl   = [
            'number'       => $rma['rma_number'],
            'status'       => $status,
            'tracking_url' => $url,
            'url'          => $url,          // the older token name, still honoured
            'customer'     => $rma['customer_name'] ?? '',
        ];
        $mail = notification_text($code, 'email', $lang, $repl, 'notify.status_subject', 'notify.status_body');
        $text = notification_text($code, 'sms',   $lang, $repl, '',                      'notify.status_sms');
        return [$mail['subject'], $mail['body'], $text['body'], $status];
    };

    $customer_lang = customer_lang($rma['customer_lang'] ?? null);
    $partner_lang  = setting('default_lang', 'me');

    // Partner, by whichever channels the grid allows for partners.
    if (!empty($rma['partner_id'])) {
        [$subject, $body, $sms, $status_name] = $for($partner_lang);
        if (setting_on('notify_partner_email', true)
            && !empty($rma['partner_email']) && !is_placeholder_email($rma['partner_email'])) {
            send_email($rma['partner_email'],
                       ($rma['partner_contact'] ?? '') ?: ($rma['partner_name'] ?? ''),
                       $subject,
                       notification_email_html($rma, $status_name, $body, $url, $partner_lang),
                       email_logo_attachment());
        }
    }

    // Customer — unless this partner is the sole point of contact.
    $tell_customer = empty($rma['partner_id'])
                  || (int) ($rma['partner_notify_customer'] ?? 1) === 1;
    if (!$tell_customer) return;

    [$subject, $body, $sms, $status_name] = $for($customer_lang);

    if (setting_on('notify_customer_email', true)
        && !empty($rma['customer_email']) && !is_placeholder_email($rma['customer_email'])) {
        send_email($rma['customer_email'], $rma['customer_name'], $subject,
                   notification_email_html($rma, $status_name, $body, $url, $customer_lang),
                   email_logo_attachment());
    }

    if (setting_on('notify_customer_sms', true)) {
        foreach (rma_sms_recipients($rma) as $number) {
            sms_send($number, $sms);
        }
    }
}

/**
 * Wording for a notification, from Sabloni when there is a row for it.
 *
 * Falls back to the language file when there is none. The fallback is the point
 * rather than a nicety: a status added in Administracija has no template until
 * somebody writes one, and it must still be able to notify.
 *
 * Returns ['subject' => ?string, 'body' => string].
 */
function notification_text(string $code, string $channel, string $lang, array $repl,
                           string $fallback_subject_key, string $fallback_body_key): array {
    $row = db_row(
        'SELECT subject, body FROM notification_templates
          WHERE code = ? AND channel = ? AND lang = ? AND is_active = 1 LIMIT 1',
        [$code, $channel, $lang]
    );

    if ($row && trim((string) $row['body']) !== '') {
        return [
            'subject' => $row['subject'] !== null ? fill_tokens($row['subject'], $repl) : null,
            'body'    => fill_tokens($row['body'], $repl),
        ];
    }

    return [
        'subject' => $fallback_subject_key ? __in($lang, $fallback_subject_key, $repl) : null,
        'body'    => __in($lang, $fallback_body_key, $repl),
    ];
}

/**
 * Substitute :tokens in text that came from the database.
 *
 * Longest name first, for the same reason __() does it: with :to and :total
 * both in play, replacing :to first leaves "tal" behind.
 */
function fill_tokens(string $text, array $repl): string {
    uksort($repl, fn($a, $b) => strlen((string) $b) <=> strlen((string) $a));
    foreach ($repl as $k => $v) {
        $text = str_replace(':' . $k, (string) $v, $text);
    }
    return $text;
}

/**
 * A status notification, dressed in the app's email frame.
 *
 * The template supplies words only. They arrive escaped — a stray tag in a
 * template cannot break the message — with line breaks kept, and the tracking
 * link is drawn as a button rather than left as a bare URL in the middle of a
 * sentence.
 *
 * The link is also dropped from the body when it appears there: the template
 * text ends with the URL, and printing it twice, once as text and once as a
 * button, reads like a mistake.
 */
function notification_email_html(array $rma, string $status, string $body, string $url, string $lang): string {
    $t = fn(string $k, array $r = []): string => __in($lang, $k, $r);

    $words = trim(str_replace($url, '', $body));
    $words = nl2br(htmlspecialchars(rtrim($words, ": 	
")));

    $content = "<p style='font-size:13px;color:#888780;margin:0 0 8px;'>"
             . htmlspecialchars($t('label.rma')) . "</p>"
             . "<p class='rma-number'>" . htmlspecialchars($rma['rma_number']) . "</p>"
             . "<p class='msg'>" . $words . "</p>";

    if ($url !== '') {
        $content .= "<p style='margin:0 0 8px;'><a class='cta' href='" . htmlspecialchars($url) . "'>"
                  . htmlspecialchars($t('track.title')) . "</a></p>";
    }

    return email_shell($content, $lang);
}
