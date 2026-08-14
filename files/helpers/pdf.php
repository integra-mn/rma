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

    $engine = $engine_override ?: setting('pdf_engine', 'html');

    // The QR is drawn on the same grey as the panel it sits in. The encoder
    // always paints white behind the modules, and a white QR on a grey panel is
    // exactly the white square it looked like.
    $qr_base64 = $tracking_url ? generate_qr_base64($tracking_url, 150, [244, 244, 244]) : '';

    if ($engine === 'mpdf' && file_exists(ROOT . '/vendor/autoload.php')) {
        generate_rma_pdf_mpdf($rma, $tracking_url, $qr_base64, $mode);
    } else {
        generate_rma_pdf_html($rma, $tracking_url, $qr_base64);
    }
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
    // Takes replacements too — without the second argument, ':date' in
    // pdf.sig_signed was never substituted and the receipt printed the
    // placeholder verbatim.
    $t = fn(string $k, array $r = []): string => __in(customer_lang($rma['customer_lang'] ?? null), $k, $r);

    // The whole footer on one line. Any part a location has not filled in is
    // dropped, so the separators never end up doubled or trailing.
    $footer_parts = array_filter([
        htmlspecialchars(company_legal_name()),
        htmlspecialchars(trim((string)($rma['location_address'] ?? ''))),
        htmlspecialchars(trim(trim((string)($rma['location_postal'] ?? '')) . ' '
                            . trim((string)($rma['location_city'] ?? '')))),
        trim((string)($rma['location_phone'] ?? '')) !== ''
          ? $t('pdf.footer_phone') . ': ' . htmlspecialchars(trim((string)$rma['location_phone'])) : '',
        trim((string)($rma['location_email'] ?? '')) !== ''
          ? $t('pdf.footer_email') . ': ' . htmlspecialchars(trim((string)$rma['location_email'])) : '',
    ], fn($v) => $v !== '');

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

    // Button labels, escaped once — each is used in the visible span and again
    // in title/aria-label, which is what a screen reader and a hover tooltip
    // read when the label itself is collapsed to an icon.
    $esc = fn(string $k): string => htmlspecialchars($t($k), ENT_QUOTES, 'UTF-8');
    $lbl_save  = $esc('pdf.btn_save');
    $lbl_print = $esc('pdf.btn_print');
    $lbl_close = $esc('pdf.btn_close');

    // Inline SVG, not an icon font: this page loads nothing but fonts.css and
    // has to render the same when it is printed or saved offline.
    $svg = fn(string $d): string =>
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
      . 'stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
    $icon_save  = $svg('<path d="M12 3v10"/><path d="M8 11l4 4 4-4"/>'
                     . '<path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>');
    $icon_print = $svg('<path d="M7 9V3h10v6"/>'
                     . '<path d="M7 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/>'
                     . '<rect x="7" y="14" width="10" height="7" rx="1"/>');
    $icon_close = $svg('<path d="M6 6l12 12"/><path d="M18 6L6 18"/>');

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
  body { font-family: ' . app_font_stack() . ';
         font-size: 14px; color: #2c2c2a; background: #F4F4F4; -webkit-font-smoothing: antialiased; }
  /* A real A4 sheet on screen — the same width, height and 10mm padding the
     printer uses, so the view matches what comes out.
     The column layout is what lets .footer sit on the bottom edge of the sheet
     on screen, the way it does on paper, instead of floating directly under
     the signature box. */
  .page { width: 210mm; min-height: 297mm; max-width: 100%; margin: 0 auto 24px;
          padding: 10mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,0.06);
          border-radius: 8px; display: flex; flex-direction: column; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid #1D9E75; }
  .logo img { height: 36px; width: auto; display: block; margin-bottom: 6px; }
  .company p { font-size: 12px; color: #888780; margin-top: 1px; line-height: 1.5; }
  .doc-title { text-align: right; }
  .doc-title h2 { font-size: 12.5px; font-weight: 600; color: #888780; text-transform: uppercase; letter-spacing: 0.08em; }
  .doc-title .rma-num { font-size: 26px; font-weight: 700; color: #1D9E75; line-height: 1.1; margin-top: 2px; }
  .doc-title .date { font-size: 12px; color: #888780; margin-top: 2px; }
  .two-col { display: flex; gap: 16px; margin-bottom: 18px; }
  /* Each column is its own panel, so the page reads as blocks top to bottom.
     Border rather than fill: fill means "what the customer told us" on this
     receipt, and a border survives printing, where the fills come off. */
  .col { flex: 1; border: 0.5px solid #d3d1c7; border-radius: 8px; padding: 12px 14px; }
  .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888780; margin-bottom: 7px; }
  table.info { width: 100%; border-collapse: collapse; }
  table.info td { padding: 4px 0; vertical-align: top; font-size: 13px; }
  table.info td:first-child { color: #888780; width: 95px; font-size: 12px; }
  /* Plain-text presentation for status & warranty (cleaner look). */
  .status-badge   { font-weight: 500; color: #1A1A1F; }
  .warranty-badge { font-weight: 500; color: #1A1A1F; }
  .warranty-badge::before { content: " · "; color: #888780; font-weight: 400; }
  .complaint-box { background: #F4F4F4; border-radius: 6px; padding: 11px 13px; font-size: 13px; line-height: 1.6; margin-bottom: 18px; }
  .qr-section { display: flex; align-items: flex-start; gap: 14px; background: #F4F4F4;
                border-radius: 8px; padding: 14px; margin-bottom: 18px; }
  .qr-section img { width: 88px; height: 88px; flex-shrink: 0; }
  .qr-text h3 { font-size: 13px; font-weight: 600; margin-bottom: 3px; }
  .qr-text p { font-size: 12px; color: #5f5e5a; line-height: 1.5; }
  .qr-text a { font-size: 11px; color: #1D9E75; word-break: break-all; }
  /* Company details live here rather than under the logo. Left-aligned, and
     pinned to the foot of the sheet when printed so it reads as stationery
     rather than as one more content block. */
  /* auto pushes it to the foot of the sheet. When the content is long enough
     to reach down here by itself, the 16px margin under the signature box plus
     the 10px padding here keeps the two apart. */
  /* Same colour as the | separators below it, so the rule and the marks
     under it carry equal weight. */
  .footer { border-top: 0.5px solid #888780; padding-top: 10px;
            margin-top: auto; text-align: left; font-size: 9.5px; color: #888780; }
  .signature-box { border: 0.5px solid #d3d1c7; border-radius: 6px; padding: 11px 13px; margin-bottom: 16px; }
  .signature-box p { font-size: 12px; color: #888780; margin-bottom: 36px; }
  .signature-line { border-top: 0.5px solid #2c2c2a; width: 200px; font-size: 11px; color: #888780; padding-top: 3px; }
  /* The button bar. These used to be inline on the element, where a media
     query cannot reach them — inline styles win — so the bar could not be
     moved anywhere. */
  #toolbar { width: 210mm; max-width: 100%; height: 64px; margin: 0 auto;
             display: flex; align-items: center; justify-content: flex-end;
             gap: 12px; font-family: ' . app_font_stack() . '; }
  #toolbar .tb { display: inline-flex; align-items: center; height: 40px;
                 padding: 0; border: none; border-radius: 8px; color: #fff;
                 cursor: pointer; text-decoration: none; font-family: inherit;
                 font-size: 13px; font-weight: 500; overflow: hidden;
                 white-space: nowrap; transition: box-shadow .15s ease; }
  #toolbar .tb svg  { width: 40px; height: 40px; padding: 10px; flex: 0 0 40px; display: block; }
  #toolbar .tb span { padding-right: 14px; }
  #toolbar .tb:hover, #toolbar .tb:focus-visible { box-shadow: 0 2px 8px rgba(0,0,0,0.18); }
  #toolbar .tb-save  { background: #1D9E75; }
  #toolbar .tb-print { background: #2563EB; }
  #toolbar .tb-close { background: #DC2626; }
  /* Wide enough for a margin beside the paper: a fixed column tucked against
     the right edge of the sheet. Fixed matters — the sheet is taller than most
     windows, and sitting in the flow above it the buttons scrolled out of
     reach the moment you read past the header, so printing meant scrolling
     back to the top first.

     The sheet is centred, so its right edge is half the window plus half of
     210mm, plus 24px — the same gap the sheet keeps from the top of the
     window, which the 24px top of this column lines up with. The buttons sit
     12px apart, tighter than their distance from the paper so they read as
     one group. Under the fit-to-height zoom both the sheet and this offset scale
     together, so they stay tucked in.

     Beside the sheet the three shrink to 40px squares and open their label on
     hover. They open to the RIGHT, into empty margin: opening leftwards would
     slide the label over the paper. The square itself never moves, so nothing
     shifts under the pointer.

     1120px is where the sheet stops leaving room, and it is the OPEN width
     that sets it, not the square: (1120 - 794) / 2 is 163px a side, just over
     the 24px gap plus the 136px of an open "Sacuvaj PDF". Sized to the square
     instead, the label would be clipped by the window edge on any screen
     between about 920 and 1114px. */
  @media screen and (min-width: 1120px) {
    #toolbar { position: fixed; top: 24px; left: calc(50% + 105mm + 24px);
               z-index: 10; width: auto; height: auto; margin: 0;
               flex-direction: column; align-items: flex-start; gap: 12px; }
    .page { margin-top: 24px; }
    #toolbar .tb span { max-width: 0; opacity: 0; padding-right: 0; overflow: hidden;
                        transition: max-width .18s ease, opacity .12s ease,
                                    padding-right .18s ease; }
    #toolbar .tb:hover span,
    #toolbar .tb:focus-visible span { max-width: 160px; opacity: 1; padding-right: 14px; }
    /* A tablet at the counter has no pointer to hover with, and would be left
       with three coloured squares and no way to find out what they are. */
    @media (hover: none) {
      #toolbar .tb span { max-width: 160px; opacity: 1; padding-right: 14px; }
    }
  }
  /* Too narrow for a side margin: keep them on top, but stuck to the window
     rather than to the document, so they are still reachable when scrolled.
     There is room across a row, so the labels stay out. */
  @media screen and (max-width: 1119px) {
    #toolbar { position: sticky; top: 0; z-index: 10; background: #F4F4F4; }
  }
  @media print {
    /* Browsers drop background colours when printing unless asked. Without this
       the grey blocks behind Dostavljena oprema and Opis reklamacije showed on
       screen and vanished on paper. */
    body, .signature-box {
      -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    /* On paper the grey fill behind Primljena oprema, Opis reklamacije and the
       tracking block reads as shading and costs toner on every receipt. A
       hairline marks the same boundary. Print only — the screen keeps the fill,
       and so does the PDF, which is built from its own stylesheet further down
       this file. */
    .complaint-box, .qr-section {
      background: transparent;
      border: 0.5px solid #d3d1c7;
    }
    body { background: #fff; }
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
  }
</style>
</head>
<body>

<div class="no-print" id="toolbar">
  <a href="/rma/' . (int)$rma['id'] . '/receipt?engine=mpdf&mode=download"
     class="tb tb-save" title="' . $lbl_save . '" aria-label="' . $lbl_save . '">' . $icon_save . '<span>' . $lbl_save . '</span></a>
  <button onclick="window.print()"
     class="tb tb-print" title="' . $lbl_print . '" aria-label="' . $lbl_print . '">' . $icon_print . '<span>' . $lbl_print . '</span></button>
  <button onclick="window.close()"
     class="tb tb-close" title="' . $lbl_close . '" aria-label="' . $lbl_close . '">' . $icon_close . '<span>' . $lbl_close . '</span></button>
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
           <div id="sig-qr-wrap" class="no-print" style="display:none;align-items:center;gap:16px;padding:14px;background:#F4F4F4;border-radius:8px;margin:6px 0;">
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
                     alert(res.error || "' . $t('sign.link_failed') . '");
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

  <div class="footer">' . implode(' &nbsp;|&nbsp; ', $footer_parts) . '</div>

</div>

<script>
/* The sheet is a fixed A4, so on a tall screen it left a band of empty
   background below it. Scale the whole preview up to use that spare height —
   the "fit page" behaviour a PDF viewer has.

   Only ever upwards: shrinking to fit a short window would make the preview
   smaller than the paper and it would stop telling the truth about what
   prints. A receipt whose content runs past one A4 measures taller than the
   window, so it is simply left alone. */
(function () {
  var bar  = document.getElementById("toolbar");
  var page = document.querySelector(".page");
  if (!bar || !page) return;

  function fit() {
    document.body.style.zoom = "";                 /* measure at 100% */
    /* A fixed toolbar is out of the flow and takes up no height of its own. */
    var inFlow = getComputedStyle(bar).position === "static" ? bar.offsetHeight : 0;
    var above  = parseFloat(getComputedStyle(page).marginTop) || 0;
    var natural = inFlow + above + page.offsetHeight + 24;   /* 24 = .page bottom margin */
    var z = window.innerHeight / natural;
    if (z > 1.005) document.body.style.zoom = z.toFixed(3);
  }
  function reset() { document.body.style.zoom = ""; }

  fit();
  window.addEventListener("resize", fit);
  /* Paper gets the real A4, never the screen zoom. */
  window.addEventListener("beforeprint", reset);
  window.addEventListener("afterprint", fit);
})();
</script>

</body>
</html>';
}

/**
 * mPDF version — true PDF download
 */
function generate_rma_pdf_mpdf(array $rma, string $tracking_url, string $qr_base64, string $mode): void {
    // Printed for the customer, so it follows THEIR language, not the
    // language of the employee at the counter.
    // Takes replacements too — without the second argument, ':date' in
    // pdf.sig_signed was never substituted and the receipt printed the
    // placeholder verbatim.
    $t = fn(string $k, array $r = []): string => __in(customer_lang($rma['customer_lang'] ?? null), $k, $r);

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

    // The PDF follows Settings → Izgled like the print view does, but it cannot
    // do it the same way. A browser renders the woff2 the app serves; mPDF can
    // only embed a real TTF or OTF, so the font is used only when its files are
    // sitting in assets/fonts/ under the usual <Font>-Regular.ttf naming.
    //
    // Today only Montserrat has them, so any other choice prints in DejaVu Sans
    // — legible, and plainly not the app font. Dropping the four TTFs for a
    // font into assets/fonts/ is all it takes to fix that; nothing here needs
    // editing.
    $font_dir    = ROOT . '/assets/fonts';
    $app_font    = setting('app_font', 'Montserrat');
    $file_stem   = str_replace(' ', '', $app_font);      // "Maven Pro" -> MavenPro-Regular.ttf
    $mpdf_name   = strtolower($file_stem);               // mPDF wants a lowercase key
    $regular     = $file_stem . '-Regular.ttf';
    $has_font    = is_file($font_dir . '/' . $regular);
    $extra_fonts = [];
    if ($has_font) {
        // A missing weight falls back to Regular rather than aborting: a PDF in
        // the right face without true italics beats no PDF at all.
        $variant = fn(string $suffix): string =>
            is_file($font_dir . "/{$file_stem}-{$suffix}.ttf") ? "{$file_stem}-{$suffix}.ttf" : $regular;
        $extra_fonts[$mpdf_name] = [
            'R'  => $regular,
            'B'  => $variant('Bold'),
            'I'  => $variant('Italic'),
            'BI' => $variant('BoldItalic'),
        ];
    } else {
        error_log("Receipt PDF: no TTF for app font '{$app_font}' in {$font_dir} — falling back to DejaVu Sans");
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
        'default_font'   => $has_font ? $mpdf_name : 'dejavusans',
        'fontDir'        => $has_font ? array_merge((new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'], [$font_dir]) : null,
        'fontdata'       => $has_font ? ($extra_fonts + (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata']) : null,
    ]);

    // Page-bottom footer: app name + location contact details + print stamp.
    // Same three parts as the print view: legal entity, street, postcode+city.
    // Phone and email came off — the footer is stationery, not a contact card.
    // One line: name, street, postcode+city, phone, email. Missing parts drop
    // out so the separators never double up.
    $footer_parts = array_filter([
        htmlspecialchars(company_legal_name()),
        htmlspecialchars(trim((string)($rma['location_address'] ?? ''))),
        htmlspecialchars(trim(trim((string)($rma['location_postal'] ?? '')) . ' '
                            . trim((string)($rma['location_city'] ?? '')))),
        trim((string)($rma['location_phone'] ?? '')) !== ''
          ? $t('pdf.footer_phone') . ': ' . htmlspecialchars(trim((string)$rma['location_phone'])) : '',
        trim((string)($rma['location_email'] ?? '')) !== ''
          ? $t('pdf.footer_email') . ': ' . htmlspecialchars(trim((string)$rma['location_email'])) : '',
    ], fn($v) => $v !== '');
    $mpdf->SetHTMLFooter(
        '<div style="border-top:0.5px solid #888780;padding-top:5px;font-size:8.5px;color:#888780;">'
      . '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
      . '<td style="text-align:left;">' . implode(' &nbsp;|&nbsp; ', $footer_parts)
      . '</td>'
      . '<td style="text-align:right;">' . $t('pdf.printed', ['date' => format_datetime(time())]) . '</td>'
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
    // Takes replacements too — without the second argument, ':date' in
    // pdf.sig_signed was never substituted and the receipt printed the
    // placeholder verbatim.
    $t = fn(string $k, array $r = []): string => __in(customer_lang($rma['customer_lang'] ?? null), $k, $r);

    // The same font the app and the print view use. mPDF keys fonts lowercase
    // with no spaces; if the caller could not register it — no TTF in
    // assets/fonts/ — this name simply does not resolve and the dejavusans
    // after it in the stack takes over.
    $pdf_font = strtolower(str_replace(' ', '', setting('app_font', 'Montserrat')));

    // mPDF accepts local file paths in <img src>. Using absolute path so
    // relative URL resolution (which mPDF does poorly) doesn't bite us.
    $logo_path = ROOT . '/assets/integra.svg';
    $logo_tag  = file_exists($logo_path)
        ? '<img src="' . $logo_path . '" style="height:36px;width:auto;">'
        : '<strong style="font-size:18px;">' . htmlspecialchars($app_name) . '</strong>';

    return '<style>
      * { box-sizing: border-box; margin: 0; padding: 0; }
      body { font-family: ' . $pdf_font . ', dejavusans, sans-serif; font-size: 14px; color: #2c2c2a; }
      .header { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #1D9E75; }
      .header table { width: 100%; border-collapse: collapse; }
      .header td { vertical-align: top; padding: 0; }
      .rma-num { font-size: 22px; font-weight: 700; color: #1D9E75; }
      .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888780; margin-bottom: 6px; }
      table.info td { padding: 4px 0; font-size: 13px; }
      table.info td:first-child { color: #888780; width: 95px; font-size: 10px; }
      .status-badge { font-weight: 500; color: #1A1A1F; }
      .complaint-box { background: #F4F4F4; border-radius: 4px; padding: 10px; font-size: 13px; line-height: 1.5; margin: 12px 0; }
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
        <td width="50%" style="vertical-align:top;border:0.5px solid #d3d1c7;border-radius:8px;padding:10px 12px;">
          <p class="section-title">' . $t('pdf.customer') . '</p>
          <table class="info" width="100%">
            <tr><td>' . $t('pdf.name') . '</td><td><strong>' . htmlspecialchars($rma['customer_name'] ?? '—') . '</strong></td></tr>
            ' . ($rma['customer_phone'] ? '<tr><td>' . $t('pdf.phone') . '</td><td>' . htmlspecialchars(format_phone($rma['customer_phone'])) . '</td></tr>' : '') . '
            ' . ($rma['customer_email'] ? '<tr><td>' . $t('pdf.email') . '</td><td>' . htmlspecialchars($rma['customer_email']) . '</td></tr>' : '') . '
          </table>
        </td>
        <td width="8" style="width:8px;"></td>
        <td width="50%" style="vertical-align:top;border:0.5px solid #d3d1c7;border-radius:8px;padding:10px 12px;">
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
          </table>
        </td>
      </tr>
    </table>

    ' . (($acc = format_rma_accessories($rma, customer_lang($rma['customer_lang'] ?? null))) ? '<p class="section-title">' . $t('pdf.accessories') . '</p><div class="complaint-box">' . htmlspecialchars($acc) . '</div>' : '') . '

    ' . ($rma['complaint'] ? '<p class="section-title">' . $t('pdf.reported_issue') . '</p><div class="complaint-box">' . nl2br(htmlspecialchars($rma['complaint'])) . '</div>' : '') . '

    ' . ($qr_base64 ? '<table width="100%" style="margin-top:12px;background:#F4F4F4;" cellpadding="8"><tr>
      <td style="vertical-align:top;padding-right:12px;width:100px;"><img src="' . $qr_base64 . '" width="90" height="90"></td>
      <td style="vertical-align:top;">
        <strong style="font-size:11px;">' . $t('pdf.track_title') . '</strong><br>
        <span style="font-size:11px;color:#5f5e5a;">' . $t('pdf.track_hint') . '<br>' . htmlspecialchars($tracking_url) . '</span>
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
