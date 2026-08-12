<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Generate RMA receipt — either HTML print view or mPDF download
 */
function generate_rma_receipt_pdf(int $rma_id, string $mode = 'download', ?string $engine_override = null): void {
    $rma = db_row("SELECT r.*,
                          c.name as customer_name, c.email as customer_email,
                          c.phone as customer_phone, c.lang as customer_lang,
                          c.address as customer_address,
                          c.zip_code as customer_zip,
                          c.city as customer_city,
                          s.label as status_label, s.color as status_color,
                          dm.name as model_name, db2.name as brand_name,
                          d.serial_number, d.imei,
                          l.name as location_name, l.phone as location_phone,
                          l.email as location_email, l.address as location_address,
                          l.city as location_city, l.postal_code as location_postal
                   FROM rma_requests r
                   JOIN rma_statuses s ON s.id = r.status_id
                   LEFT JOIN customers c ON c.id = r.customer_id
                   LEFT JOIN devices d ON d.id = r.device_id
                   LEFT JOIN device_models dm ON dm.id = d.model_id
                   LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                   LEFT JOIN locations l ON l.id = r.location_id
                   WHERE r.id = ?", [$rma_id]);

    if (!$rma) { http_response_code(404); echo 'RMA not found'; return; }

    $token = db_val('SELECT token FROM rma_tracking_tokens WHERE rma_id = ?', [$rma_id]);
    $tracking_url = $token ? 'https://rma.integra.mn/track/' . $token : '';
    $qr_base64    = $tracking_url ? generate_qr_base64($tracking_url, 150) : '';

    $engine = $engine_override ?: setting('pdf_engine', 'html');

    if ($engine === 'mpdf' && file_exists(ROOT . '/vendor/autoload.php')) {
        generate_rma_pdf_mpdf($rma, $tracking_url, $qr_base64, $mode);
    } else {
        generate_rma_pdf_html($rma, $tracking_url, $qr_base64);
    }
}

/**
 * "14 dana" / "1 dan" — Montenegrin uses the singular for numbers ending in 1
 * except 11, and the same shape reads correctly in English (1 day / 14 days).
 */
function pdf_days(int $n, callable $t): string {
    $singular = ($n % 10 === 1 && $n % 100 !== 11);
    return $n . ' ' . ($t($singular ? 'pdf.days_one' : 'pdf.days_other'));
}

/**
 * HTML print view — opens in browser, user prints/saves as PDF
 */
/**
 * Format the accessories JSON + free-text "other" field into a single
 * comma-separated human-readable string. Returns null if the customer
 * brought nothing (so the caller can skip the section entirely).
 */
function format_rma_accessories(array $rma, ?string $lang = null): ?string {
    // Reuse the same keys the RMA form uses, so the receipt says exactly what
    // the person ticked - in the customer's language, not the employee's.
    $keys_map = [
        'battery'          => 'rma.acc_battery',
        'charger'          => 'rma.acc_charger',
        'sim_card'         => 'rma.acc_sim_card',
        'headphones'       => 'rma.acc_headphones',
        'packaging'        => 'rma.acc_packaging',
        'memory_card'      => 'rma.acc_memory_card',
        'protective_case'  => 'rma.acc_protective_case',
        'purchase_receipt' => 'rma.acc_purchase_receipt',
    ];

    $parts = [];
    if (!empty($rma['accessories'])) {
        $keys = json_decode((string) $rma['accessories'], true);
        if (is_array($keys)) {
            foreach ($keys as $k) {
                $parts[] = isset($keys_map[$k])
                    ? ($lang === null ? __($keys_map[$k]) : __in($lang, $keys_map[$k]))
                    : ucwords(str_replace('_', ' ', (string) $k));
            }
        }
    }
    $other = trim((string) ($rma['accessories_other'] ?? ''));
    if ($other !== '') $parts[] = $other;

    return $parts ? implode(', ', $parts) : null;
}

function generate_rma_pdf_html(array $rma, string $tracking_url, string $qr_base64): void {
    // Printed for the customer, so it follows THEIR language, not the
    // language of the employee at the counter.
    $t = fn(string $k): string => __in(customer_lang($rma['customer_lang'] ?? null), $k);

    // Second footer line. Empty when the location has neither, so a bare
    // "Telefon:" never prints.
    $footer_contact = implode(' &nbsp;|&nbsp; ', array_filter([
        trim((string)($rma['location_phone'] ?? '')) !== ''
          ? $t('pdf.footer_phone') . ': ' . htmlspecialchars(trim((string)$rma['location_phone'])) : '',
        trim((string)($rma['location_email'] ?? '')) !== ''
          ? $t('pdf.footer_email') . ': ' . htmlspecialchars(trim((string)$rma['location_email'])) : '',
    ], fn($v) => $v !== ''));

    $device = trim(($rma['brand_name'] ?? '') . ' ' . ($rma['model_name'] ?? ''));
    $date   = format_date($rma['created_at']);
    $app_name = setting('app_name', 'Integra RMA');
    $accessories = format_rma_accessories($rma, customer_lang($rma['customer_lang'] ?? null));

    // Most recent captured signature for this RMA, if any.
    $signature = db_row(
        "SELECT filename, signed_at FROM rma_signatures
         WHERE rma_id = ? AND signed_at IS NOT NULL AND deleted_at IS NULL
         ORDER BY signed_at DESC LIMIT 1",
        [(int)$rma['id']]
    );

    header('Content-Type: text/html; charset=UTF-8');
    $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="csrf-token" content="' . $csrf . '">
<title>RMA Receipt — ' . htmlspecialchars($rma['rma_number']) . '</title>
<link href="/assets/css/fonts.css" rel="stylesheet">
<style>
  /* A4 page: 210mm x 297mm. margin:0 suppresses the browser default
     page header/footer (URL, timestamp, page numbers). Our own margin
     is applied via padding on .page in the print media query below. */
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Montserrat", system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
         font-size: 13px; color: #2c2c2a; background: #f4f4f0; -webkit-font-smoothing: antialiased; }
  /* On-screen preview: 1080px wide card on a muted background.
     Print output: A4 (see @media print below) — same content, different container. */
  .page { width: 1080px; max-width: 100%; margin: 0 auto 24px; padding: 40px 56px;
          background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border-radius: 8px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid #1D9E75; }
  .logo img { height: 36px; width: auto; display: block; margin-bottom: 6px; }
  .company p { font-size: 10.5px; color: #888780; margin-top: 1px; line-height: 1.5; }
  .doc-title { text-align: right; }
  .doc-title h2 { font-size: 11px; font-weight: 600; color: #888780; text-transform: uppercase; letter-spacing: 0.08em; }
  .doc-title .rma-num { font-size: 24px; font-weight: 700; color: #1D9E75; line-height: 1.1; margin-top: 2px; }
  .doc-title .date { font-size: 10.5px; color: #888780; margin-top: 2px; }
  .two-col { display: flex; gap: 24px; margin-bottom: 18px; }
  .col { flex: 1; }
  .section-title { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888780; margin-bottom: 7px; border-bottom: 0.5px solid #e8e6e0; padding-bottom: 3px; }
  table.info { width: 100%; border-collapse: collapse; }
  table.info td { padding: 4px 0; vertical-align: top; font-size: 11.5px; border-bottom: 0.5px solid #f4f4f0; }
  table.info td:first-child { color: #888780; width: 95px; font-size: 10.5px; }
  /* Plain-text presentation for status & warranty (cleaner look). */
  .status-badge   { font-weight: 500; color: #1A1A1F; }
  .warranty-badge { font-weight: 500; color: #1A1A1F; }
  .warranty-badge::before { content: " · "; color: #888780; font-weight: 400; }
  .complaint-box { background: #f4f4f0; border-radius: 6px; padding: 11px 13px; font-size: 11.5px; line-height: 1.55; margin-bottom: 18px; }
  .qr-section { display: flex; align-items: flex-start; gap: 14px; background: #f4f4f0; border-radius: 8px; padding: 14px; margin-bottom: 18px; }
  .qr-section img { width: 88px; height: 88px; flex-shrink: 0; }
  .qr-text h3 { font-size: 11.5px; font-weight: 600; margin-bottom: 3px; }
  .qr-text p { font-size: 10.5px; color: #5f5e5a; line-height: 1.5; }
  .qr-text a { font-size: 9.5px; color: #1D9E75; word-break: break-all; }
  /* Company details live here rather than under the logo. Left-aligned, and
     pinned to the foot of the sheet when printed so it reads as stationery
     rather than as one more content block. */
  .footer { border-top: 0.5px solid #e8e6e0; padding-top: 10px; margin-top: 28px;
            text-align: left; font-size: 9.5px; color: #888780; }
  .signature-box { border: 0.5px solid #d3d1c7; border-radius: 6px; padding: 11px 13px; margin-bottom: 16px; }
  .signature-box p { font-size: 10.5px; color: #888780; margin-bottom: 36px; }
  .signature-line { border-top: 0.5px solid #2c2c2a; width: 200px; font-size: 9.5px; color: #888780; padding-top: 3px; }
  @media print {
    body { background: #fff; font-size: 14px; }
    /* 10mm matches the print padding on .page, so the footer lines up with the
       left edge of the content above it. */
    .footer { position: fixed; bottom: 8mm; left: 10mm; right: 10mm;
              margin-top: 0; background: #fff; }
    /* !important beats the inline display:flex on the toolbar */
    .no-print { display: none !important; }
    /* With @page margin:0, we rely on the page container padding
       to keep content away from the sheet edges. */
    .page { width: auto; max-width: none; margin: 0; padding: 10mm;
            background: transparent; box-shadow: none; border-radius: 0; }
    /* Scale up all the fine print for readability on paper. */
    .doc-title h2            { font-size: 12.5px; }
    .doc-title .rma-num      { font-size: 26px; }
    .doc-title .date         { font-size: 12px; }
    .company p               { font-size: 12px; }
    .section-title           { font-size: 11px; }
    table.info td            { font-size: 13px; }
    table.info td:first-child{ font-size: 12px; }
    /* Base already flattened the pills; just bump type size on print. */
    .status-badge,
    .warranty-badge { font-size: 13px; }
    .complaint-box           { font-size: 13px; line-height: 1.6; }
    .qr-text h3              { font-size: 13px; }
    .qr-text p               { font-size: 12px; }
    .qr-text a               { font-size: 11px; }
    .signature-box p         { font-size: 12px; }
    .signature-line          { font-size: 11px; }
  }
</style>
</head>
<body>

<div class="no-print" style="width:1080px;max-width:100%;height:72px;margin:0 auto;box-sizing:border-box;display:flex;align-items:center;justify-content:flex-end;gap:10px;font-family:Montserrat,system-ui,sans-serif;">
  <a href="/rma/' . (int)$rma['id'] . '/receipt?engine=mpdf&mode=download"
     style="background:#1D9E75;color:#fff;border:none;padding:7px 18px;border-radius:6px;cursor:pointer;font-weight:500;font-family:inherit;font-size:13px;text-decoration:none;">' . $t('pdf.btn_save') . '</a>
  <button onclick="window.print()" style="background:#2563EB;color:#fff;border:none;padding:7px 18px;border-radius:6px;cursor:pointer;font-weight:500;font-family:inherit;font-size:13px;">' . $t('pdf.btn_print') . '</button>
  <button onclick="window.close()" style="background:#DC2626;color:#fff;border:none;padding:7px 18px;border-radius:6px;cursor:pointer;font-weight:500;font-family:inherit;font-size:13px;">' . $t('pdf.btn_close') . '</button>
</div>

<div class="page">

  <div class="header">
    <div class="company">
      <div class="logo"><img src="/assets/integra.svg" alt="' . htmlspecialchars($app_name) . '"></div>
    </div>
    <div class="doc-title">
      <h2>' . $t('pdf.receipt_title') . '</h2>
      <div class="rma-num">' . htmlspecialchars($rma['rma_number']) . '</div>
      <div class="date">' . $date . '</div>
    </div>
  </div>

  <div class="two-col">
    <div class="col">
      <p class="section-title">' . $t('pdf.customer') . '</p>
      <table class="info">
        <tr><td>' . $t('pdf.name') . '</td><td><strong>' . htmlspecialchars($rma['customer_name'] ?? '—') . '</strong></td></tr>
        ' . ($rma['customer_phone'] ? '<tr><td>' . $t('pdf.phone') . '</td><td>' . htmlspecialchars(format_phone($rma['customer_phone'])) . '</td></tr>' : '') . '
        ' . ($rma['customer_email'] ? '<tr><td>' . $t('pdf.email') . '</td><td>' . htmlspecialchars($rma['customer_email']) . '</td></tr>' : '') . '
        ' . (function() use ($rma, $t) {
              // "Street, ZIP City" — zip + city share a space, the street
              // gets its own comma-separated segment.
              $addr = trim((string)($rma['customer_address'] ?? ''));
              $zip  = trim((string)($rma['customer_zip'] ?? ''));
              $city = trim((string)($rma['customer_city'] ?? ''));
              $locality = trim(($zip !== '' ? $zip . ' ' : '') . $city);
              $parts = array_filter([$addr, $locality], fn($s) => $s !== '');
              return $parts
                  ? '<tr><td>' . $t('pdf.address') . '</td><td>' . htmlspecialchars(implode(', ', $parts)) . '</td></tr>'
                  : '';
          })() . '
      </table>
    </div>
    <div class="col">
      <p class="section-title">' . $t('pdf.device') . '</p>
      <table class="info">
        ' . ($device ? '<tr><td>' . $t('pdf.model') . '</td><td><strong>' . htmlspecialchars($device) . '</strong></td></tr>' : '') . '
        ' . (!empty($rma['imei'])
              ? '<tr><td>' . $t('pdf.sn_imei') . '</td><td style="font-family:monospace;">' . htmlspecialchars($rma['imei']) . '</td></tr>'
              : (!empty($rma['serial_number'])
                  ? '<tr><td>' . $t('pdf.sn_imei') . '</td><td style="font-family:monospace;">' . htmlspecialchars($rma['serial_number']) . '</td></tr>'
                  : '')) . '
        <tr><td>' . $t('pdf.status') . '</td><td>' . $t('pdf.status_received') . (
              !empty($rma['is_warranty']) ? ' · ' . $t('pdf.status_warranty')
            : (!empty($rma['warranty_refusal']) ? ' · ' . $t('pdf.status_warranty_no') : '')
        ) . '</td></tr>
        ' . ($rma['estimated_completion']
              ? '<tr><td>' . $t('pdf.repair_time') . '</td><td>' . pdf_days(max(0, (int) round((strtotime($rma['estimated_completion']) - strtotime($rma['created_at'])) / 86400)), $t) . '</td></tr>'
              : '') . '
      </table>
    </div>
  </div>

  ' . ($accessories ? '
  <p class="section-title">' . $t('pdf.accessories') . '</p>
  <div class="complaint-box">' . htmlspecialchars($accessories) . '</div>
  ' : '') . '

  ' . ($rma['complaint'] ? '
  <p class="section-title">' . $t('pdf.reported_issue') . '</p>
  <div class="complaint-box">' . nl2br(htmlspecialchars($rma['complaint'])) . '</div>
  ' : '') . '

  ' . ($tracking_url ? '
  <div class="qr-section">
    ' . ($qr_base64 ? '<img src="' . $qr_base64 . '" alt="QR Code">' : '') . '
    <div class="qr-text">
      <h3>' . $t('pdf.track_title') . '</h3>
      <p>' . $t('pdf.track_hint') . '</p>
      <a href="' . htmlspecialchars($tracking_url) . '">' . htmlspecialchars($tracking_url) . '</a>
    </div>
  </div>
  ' : '') . '

  <div class="signature-box">
    <p>' . $t('pdf.signature_consent') . '</p>
    ' . ($signature
        ? '<div style="height:90px;display:flex;align-items:flex-end;margin:8px 0 4px;">
             <img src="/storage/' . htmlspecialchars($signature['filename']) . '" alt="' . $t('pdf.sig_alt') . '"
                  style="max-height:80px;max-width:320px;object-fit:contain;">
           </div>
           <div class="signature-line">' . $t('pdf.sig_signed', ['date' => format_datetime($signature['signed_at'])]) . '</div>'
        : '<div id="sig-placeholder" style="height:90px;display:flex;align-items:center;justify-content:space-between;margin:4px 0;">
             <span class="signature-line" style="border-top:0;padding-top:0;color:#b5b2a8;">' . $t('pdf.sig_awaiting') . '</span>
             <button type="button" class="no-print" id="sig-btn"
                     style="background:#1D9E75;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;">
               ' . $t('pdf.sig_get') . '
             </button>
           </div>
           <div id="sig-qr-wrap" class="no-print" style="display:none;align-items:center;gap:16px;padding:14px;background:#f4f4f0;border-radius:8px;margin:6px 0;">
             <div id="sig-qr" style="background:#fff;padding:8px;border-radius:6px;"></div>
             <div style="flex:1;font-size:12px;color:#5f5e5a;line-height:1.5;">
               <div style="font-weight:600;color:#2c2c2a;margin-bottom:4px;">' . $t('pdf.scan_any_device') . '</div>
               <div id="sig-status">' . $t('pdf.waiting_customer') . '&hellip;</div>
               <div style="color:#888780;margin-top:4px;font-size:11px;">' . $t('pdf.sig_station_hint') . '</div>
               <div style="margin-top:6px;word-break:break-all;" id="sig-url"></div>
             </div>
           </div>
           <!-- Served locally on purpose: a CDN would send every visitor IP to
                a third party on page load, which GDPR does not allow here. -->
           <script src="/assets/js/qrcode.min.js"></script>
           <script>
           (function () {
             var rmaId  = ' . (int)$rma['id'] . ';
             var btn    = document.getElementById("sig-btn");
             var wrap   = document.getElementById("sig-qr-wrap");
             var qrDiv  = document.getElementById("sig-qr");
             var statusEl = document.getElementById("sig-status");
             var urlEl  = document.getElementById("sig-url");
             var poll   = null, currentToken = null;

             btn.addEventListener("click", function () {
               btn.disabled = true; btn.textContent = "Generating\u2026";
               var csrf = document.querySelector("meta[name=csrf-token]").getAttribute("content");
               fetch("/signature/token", {
                 method: "POST",
                 headers: {
                   "Content-Type": "application/x-www-form-urlencoded",
                   "X-CSRF-Token": csrf
                 },
                 body: "rma_id=" + rmaId + "&_csrf=" + encodeURIComponent(csrf)
               })
                 .then(function (r) { return r.json(); })
                 .then(function (res) {
                   if (!res.success) {
                     btn.disabled = false; btn.textContent = "' . $t('pdf.sig_get') . '";
                     alert(res.error || "Could not generate signing link.");
                     return;
                   }
                   currentToken = res.token;
                   btn.style.display = "none";
                   wrap.style.display = "flex";
                   qrDiv.innerHTML = "";
                   new QRCode(qrDiv, {text: res.url, width: 140, height: 140,
                                      colorDark: "#000000", colorLight: "#ffffff"});
                   urlEl.textContent = res.url;
                   startPoll();
                 });
             });

             function startPoll() {
               if (poll) clearInterval(poll);
               poll = setInterval(function () {
                 fetch("/signature/status?token=" + encodeURIComponent(currentToken))
                   .then(function (r) { return r.json(); })
                   .then(function (s) {
                     if (s.visited && !s.signed) statusEl.textContent = "' . $t('pdf.sig_opened') . '\u2026";
                     if (s.signed && s.url) {
                       clearInterval(poll); poll = null;
                       statusEl.textContent = "' . $t('pdf.sig_received') . '\u2026";
                       setTimeout(function () { location.reload(); }, 600);
                     }
                   });
               }, 2000);
             }
           })();
           </script>'
      ) . '
  </div>

  <div class="footer">
    <div>' . implode(' &nbsp;|&nbsp; ', array_filter([
          htmlspecialchars(company_legal_name()),
          htmlspecialchars(trim((string)($rma['location_address'] ?? ''))),
          htmlspecialchars(trim(trim((string)($rma['location_postal'] ?? '')) . ' ' . trim((string)($rma['location_city'] ?? '')))),
        ], fn($v) => $v !== '')) . '</div>' . ($footer_contact !== '' ? '
    <div>' . $footer_contact . '</div>' : '') . '
  </div>

</div>
</body>
</html>';
}

/**
 * mPDF version — true PDF download
 */
function generate_rma_pdf_mpdf(array $rma, string $tracking_url, string $qr_base64, string $mode): void {
    // Printed for the customer, so it follows THEIR language, not the
    // language of the employee at the counter.
    $t = fn(string $k): string => __in(customer_lang($rma['customer_lang'] ?? null), $k);

    require_once ROOT . '/vendor/autoload.php';

    $device   = trim(($rma['brand_name'] ?? '') . ' ' . ($rma['model_name'] ?? ''));
    $date     = format_date($rma['created_at']);
    $app_name = setting('app_name', 'Integra RMA');
    $filename = 'RMA-' . $rma['rma_number'] . '.pdf';

    // Pull the most recent signature for embedding, if any.
    $signature = db_row(
        "SELECT filename, signed_at FROM rma_signatures
         WHERE rma_id = ? AND signed_at IS NOT NULL AND deleted_at IS NULL
         ORDER BY signed_at DESC LIMIT 1",
        [(int)$rma['id']]
    );

    $html = generate_rma_pdf_html_string($rma, $device, $date, $app_name, $tracking_url, $qr_base64, $signature);

    // mPDF font config: try Montserrat first (if TTFs exist in
    // assets/fonts/), fall back to DejaVu Sans (bundled with mPDF).
    $font_dir    = ROOT . '/assets/fonts';
    $has_mont    = is_file($font_dir . '/Montserrat-Regular.ttf');
    $extra_fonts = [];
    if ($has_mont) {
        $extra_fonts['montserrat'] = [
            'R'  => 'Montserrat-Regular.ttf',
            'B'  => is_file($font_dir . '/Montserrat-Bold.ttf')      ? 'Montserrat-Bold.ttf'      : 'Montserrat-Regular.ttf',
            'I'  => is_file($font_dir . '/Montserrat-Italic.ttf')    ? 'Montserrat-Italic.ttf'    : 'Montserrat-Regular.ttf',
            'BI' => is_file($font_dir . '/Montserrat-BoldItalic.ttf')? 'Montserrat-BoldItalic.ttf': 'Montserrat-Regular.ttf',
        ];
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode'           => 'utf-8',
        'format'         => setting('pdf_paper_size', 'A4'),
        'orientation'    => setting('pdf_orientation', 'P'),
        'margin_top'     => 10,
        'margin_bottom'  => 18,   // extra to accommodate the footer block
        'margin_left'    => 10,
        'margin_right'   => 10,
        'margin_footer'  => 6,
        'tempDir'        => ROOT . '/storage/tmp',
        'default_font'   => $has_mont ? 'montserrat' : 'dejavusans',
        'fontDir'        => $has_mont ? array_merge((new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'], [$font_dir]) : null,
        'fontdata'       => $has_mont ? ($extra_fonts + (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata']) : null,
    ]);

    // Page-bottom footer: app name + location contact details + print stamp.
    // Same three parts as the print view: legal entity, street, postcode+city.
    // Phone and email came off — the footer is stationery, not a contact card.
    // Used by the left cell below; without this it was an undefined variable
    // and the contact line simply never appeared.
    $footer_contact = array_filter([
        trim((string)($rma['location_phone'] ?? '')) !== ''
          ? $t('pdf.footer_phone') . ': ' . htmlspecialchars(trim((string)$rma['location_phone'])) : '',
        trim((string)($rma['location_email'] ?? '')) !== ''
          ? $t('pdf.footer_email') . ': ' . htmlspecialchars(trim((string)$rma['location_email'])) : '',
    ], fn($v) => $v !== '');

    $footer_parts = array_filter([
        htmlspecialchars(company_legal_name()),
        htmlspecialchars(trim((string)($rma['location_address'] ?? ''))),
        htmlspecialchars(trim(trim((string)($rma['location_postal'] ?? '')) . ' '
                            . trim((string)($rma['location_city'] ?? '')))),
    ], fn($v) => $v !== '');
    $mpdf->SetHTMLFooter(
        '<div style="border-top:0.5px solid #e8e6e0;padding-top:5px;font-size:8.5px;color:#888780;">'
      . '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
      . '<td style="text-align:left;">' . implode(' &nbsp;|&nbsp; ', $footer_parts)
      . ($footer_contact ? '<br>' . implode(' &nbsp;|&nbsp; ', $footer_contact) : '') . '</td>'
      . '<td style="text-align:right;">Printed ' . format_datetime(time()) . ' &nbsp;·&nbsp; Page {PAGENO}/{nbpg}</td>'
      . '</tr></table>'
      . '</div>'
    );

    $mpdf->SetTitle('RMA Receipt — ' . $rma['rma_number']);
    $mpdf->WriteHTML($html);

    if ($mode === 'download') {
        $mpdf->Output($filename, 'D');
    } else {
        $mpdf->Output($filename, 'I');
    }
}

function generate_rma_pdf_html_string(array $rma, string $device, string $date, string $app_name, string $tracking_url, string $qr_base64, ?array $signature = null): string {
    // Printed for the customer, so it follows THEIR language, not the
    // language of the employee at the counter.
    $t = fn(string $k): string => __in(customer_lang($rma['customer_lang'] ?? null), $k);

    // mPDF accepts local file paths in <img src>. Using absolute path so
    // relative URL resolution (which mPDF does poorly) doesn't bite us.
    $logo_path = ROOT . '/assets/integra.svg';
    $logo_tag  = file_exists($logo_path)
        ? '<img src="' . $logo_path . '" style="height:36px;width:auto;">'
        : '<strong style="font-size:18px;">' . htmlspecialchars($app_name) . '</strong>';

    return '<style>
      * { box-sizing: border-box; margin: 0; padding: 0; }
      body { font-family: montserrat, dejavusans, sans-serif; font-size: 11.5px; color: #2c2c2a; }
      .header { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #1D9E75; }
      .header table { width: 100%; border-collapse: collapse; }
      .header td { vertical-align: top; padding: 0; }
      .rma-num { font-size: 22px; font-weight: 700; color: #1D9E75; }
      .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888780; margin-bottom: 6px; border-bottom: 0.5px solid #e8e6e0; padding-bottom: 3px; }
      table.info td { padding: 4px 0; font-size: 10.5px; border-bottom: 0.5px solid #f4f4f0; }
      table.info td:first-child { color: #888780; width: 95px; font-size: 10px; }
      .status-badge { font-weight: 500; color: #1A1A1F; }
      .complaint-box { background: #f4f4f0; border-radius: 4px; padding: 10px; font-size: 10.5px; line-height: 1.5; margin: 12px 0; }
      .signature-box { border: 0.5px solid #d3d1c7; border-radius: 4px; padding: 10px; margin-top: 16px; }
      .signature-line { border-top: 0.5px solid #2c2c2a; width: 180px; font-size: 9px; color: #888780; padding-top: 3px; margin-top: 36px; }
    </style>

    <div class="header">
      <table>
        <tr>
          <td>' . $logo_tag . '</td>
          <td style="text-align:right;">
            <span style="font-size:10px;color:#888780;">' . $t('pdf.receipt_title') . '</span><br>
            <span class="rma-num">' . htmlspecialchars($rma['rma_number']) . '</span><br>
            <span style="font-size:10px;color:#888780;">' . $date . '</span>
          </td>
        </tr>
      </table>
    </div>

    <table width="100%" style="margin-bottom:14px;" cellspacing="0" cellpadding="0">
      <tr>
        <td width="50%" style="vertical-align:top;padding-right:10px;">
          <p class="section-title">' . $t('pdf.customer') . '</p>
          <table class="info" width="100%">
            <tr><td>' . $t('pdf.name') . '</td><td><strong>' . htmlspecialchars($rma['customer_name'] ?? '—') . '</strong></td></tr>
            ' . ($rma['customer_phone'] ? '<tr><td>' . $t('pdf.phone') . '</td><td>' . htmlspecialchars(format_phone($rma['customer_phone'])) . '</td></tr>' : '') . '
            ' . ($rma['customer_email'] ? '<tr><td>' . $t('pdf.email') . '</td><td>' . htmlspecialchars($rma['customer_email']) . '</td></tr>' : '') . '
            ' . (function() use ($rma, $t) {
                  $parts = array_filter([
                      trim((string)($rma['customer_address'] ?? '')),
                      trim((string)($rma['customer_zip'] ?? '')),
                      trim((string)($rma['customer_city'] ?? '')),
                  ], fn($s) => $s !== '');
                  return $parts
                      ? '<tr><td>' . $t('pdf.address') . '</td><td>' . htmlspecialchars(implode(', ', $parts)) . '</td></tr>'
                      : '';
              })() . '
          </table>
        </td>
        <td width="50%" style="vertical-align:top;padding-left:10px;">
          <p class="section-title">' . $t('pdf.device') . '</p>
          <table class="info" width="100%">
            ' . ($device ? '<tr><td>' . $t('pdf.model') . '</td><td><strong>' . htmlspecialchars($device) . '</strong></td></tr>' : '') . '
            ' . (!empty($rma['imei'])
                  ? '<tr><td>' . $t('pdf.sn_imei') . '</td><td>' . htmlspecialchars($rma['imei']) . '</td></tr>'
                  : (!empty($rma['serial_number'])
                      ? '<tr><td>' . $t('pdf.sn_imei') . '</td><td>' . htmlspecialchars($rma['serial_number']) . '</td></tr>'
                      : '')) . '
            <tr><td>' . $t('pdf.status') . '</td><td>' . $t('pdf.status_received') . (
                  !empty($rma['is_warranty']) ? ' · ' . $t('pdf.status_warranty')
                : (!empty($rma['warranty_refusal']) ? ' · ' . $t('pdf.status_warranty_no') : '')
            ) . '</td></tr>
            ' . ($rma['estimated_completion']
              ? '<tr><td>' . $t('pdf.repair_time') . '</td><td>' . pdf_days(max(0, (int) round((strtotime($rma['estimated_completion']) - strtotime($rma['created_at'])) / 86400)), $t) . '</td></tr>'
              : '') . '
          </table>
        </td>
      </tr>
    </table>

    ' . (($acc = format_rma_accessories($rma, customer_lang($rma['customer_lang'] ?? null))) ? '<p class="section-title">' . $t('pdf.accessories') . '</p><div class="complaint-box">' . htmlspecialchars($acc) . '</div>' : '') . '

    ' . ($rma['complaint'] ? '<p class="section-title">' . $t('pdf.reported_issue') . '</p><div class="complaint-box">' . nl2br(htmlspecialchars($rma['complaint'])) . '</div>' : '') . '

    ' . ($qr_base64 ? '<table width="100%" style="margin-top:12px;"><tr>
      <td style="vertical-align:top;padding-right:12px;width:100px;"><img src="' . $qr_base64 . '" width="90" height="90"></td>
      <td style="vertical-align:top;">
        <strong style="font-size:11px;">' . $t('pdf.track_title') . '</strong><br>
        <span style="font-size:10px;color:#5f5e5a;">Scan QR code or visit:<br>' . htmlspecialchars($tracking_url) . '</span>
      </td>
    </tr></table>' : '') . '

    <div class="signature-box">
      <span style="font-size:10px;color:#888780;">' . $t('pdf.signature_consent') . '</span>
      ' . ($signature
          ? '<div style="margin:6px 0 2px;"><img src="' . ROOT . '/storage/' . htmlspecialchars($signature['filename']) . '" style="height:60px;"></div>
             <div class="signature-line">' . $t('pdf.sig_signed', ['date' => format_datetime($signature['signed_at'])]) . '</div>'
          : '<div class="signature-line" style="margin-top:36px;">' . $t('pdf.signature_date') . '</div>') . '
    </div>';
}
