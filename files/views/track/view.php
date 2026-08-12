<?php
  // This page is read by the customer, not by staff, so status names follow
  // THEIR language — the same rule the receipt and the signing page use.
  // Without it status_label() falls back to the app default, and a customer
  // set to English would still be shown Montenegrin.
  $track_lang = customer_lang($rma['customer_lang'] ?? null);
  // Same escaping $tt() applies, so nothing on the page loses it.
  $tt = fn(string $k, array $r = []): string =>
      htmlspecialchars(__in($track_lang, $k, $r), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($track_lang) ?>">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/assets/img/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $tt('track.title') ?> — <?= htmlspecialchars($rma['rma_number']) ?></title>
  <?php $font_slug = strtolower(str_replace(' ', '-', setting('app_font', 'Montserrat'))); ?>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-400-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/<?= $font_slug ?>-500-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link href="/assets/css/fonts.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: <?= app_font_stack() ?>;
      background: #f4f4f0; color: #2c2c2a; padding: 24px;
      -webkit-font-smoothing: antialiased;
    }
    .wrap { width: 1080px; max-width: 100%; margin: 0 auto; }

    .logo-bar { margin-bottom: 18px; }
    .logo-bar img { height: 36px; width: auto; display: block; }

    /* Cards */
    .card {
      background: #fff; border: 0.5px solid #d3d1c7; border-radius: 12px;
      padding: 20px 22px; margin-bottom: 14px;
    }
    .card-title {
      font-size: 11px; font-weight: 600; color: #888780;
      text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px;
    }

    /* Hero */
    .hero { padding: 24px 26px; }
    .hero .rma-number { font-size: 26px; font-weight: 600; margin-bottom: 10px; letter-spacing: -0.01em; }
    .hero .pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .pill { display: inline-block; font-size: 13px; font-weight: 500; padding: 5px 14px; border-radius: 6px; }
    .pill-warranty { background: #faeeda; color: #633806; }
    .hero .eta { display: flex; gap: 28px; padding-top: 14px; border-top: 0.5px solid #e8e6e0; font-size: 13px; }
    .hero .eta-label { color: #888780; text-transform: uppercase; letter-spacing: 0.06em; font-size: 11px; font-weight: 600; }
    .hero .eta-value { color: #2c2c2a; font-weight: 500; margin-top: 2px; }

    /* 2-column row — collapses to single column on mobile */
    .row-2 {
      display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
      margin-bottom: 0;
    }
    .row-2 > .card { margin-bottom: 14px; }
    @media (max-width: 760px) { .row-2 { grid-template-columns: 1fr; gap: 0; } }

    /* Detail rows (k/v pairs) */
    .detail-row { display: flex; font-size: 13px; padding: 4px 0; }
    .detail-row .k { color: #888780; width: 70px; flex-shrink: 0; }
    .detail-row .v { color: #2c2c2a; }
    .mono { font-family: "SF Mono", Menlo, Consolas, monospace; }

    /* Prose for free-text fields (complaint, findings, resolution) */
    .prose { font-size: 13.5px; line-height: 1.6; color: #2c2c2a; white-space: pre-wrap; }
    .muted { color: #888780; font-size: 13px; font-style: italic; }

    /* Timeline (progress) */
    .timeline { position: relative; padding-left: 24px; }
    .timeline::before {
      content: ''; position: absolute; left: 6px; top: 8px; bottom: 8px;
      width: 2px; background: #e8e6e0;
    }
    .tl-item { position: relative; padding-bottom: 18px; }
    .tl-item:last-child { padding-bottom: 0; }
    .tl-dot {
      position: absolute; left: -22px; top: 3px;
      width: 12px; height: 12px; border-radius: 50%;
      border: 2px solid #fff; box-shadow: 0 0 0 1px #d3d1c7;
    }
    .tl-item.is-current .tl-dot {
      width: 16px; height: 16px; left: -24px; top: 1px;
      box-shadow: 0 0 0 1px var(--status-color, #1D9E75),
                  0 0 0 6px rgba(29,158,117,0.15);
    }
    .tl-label { font-size: 14px; font-weight: 500; line-height: 1.3; }
    .tl-item.is-current .tl-label { color: var(--status-color, #1D9E75); font-weight: 600; }
    .tl-date { font-size: 12px; color: #888780; margin-top: 2px; }
    .tl-note { font-size: 13px; color: #5f5e5a; margin-top: 4px; line-height: 1.5; }

    .comment {
      border-radius: 8px; padding: 10px 13px;
      font-size: 13px; line-height: 1.5; margin-bottom: 8px;
      border: 0.5px solid transparent;
    }
    .comment:last-child { margin-bottom: 0; }
    .comment-staff    { background: #f4f4f0; border-color: #e0ddd3; }
    .comment-customer { background: #e8f3ff; border-color: #c5dcf5; }
    .comment-head {
      display: flex; justify-content: space-between; align-items: baseline;
      gap: 10px; margin-bottom: 5px; font-size: 11px;
    }
    .comment-author {
      text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;
    }
    .comment-staff    .comment-author { color: #5f5e5a; }
    .comment-customer .comment-author { color: #1a5bb5; }
    .comment-date { color: #888780; }

    .meta-line { font-size: 11.5px; color: #888780; margin-top: 12px;
      padding-top: 10px; border-top: 0.5px solid #e8e6e0; }

    /* Evidence photos grid */
    .photo-grid {
      display: grid; gap: 8px;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }
    .photo-tile {
      position: relative; aspect-ratio: 1; border-radius: 8px;
      overflow: hidden; background: #e5e3dc;
      border: 0.5px solid #d3d1c7;
      display: block; cursor: pointer; background: none; padding: 0; text-align: left;
    }
    .photo-tile img { width: 100%; height: 100%; object-fit: cover; display: block;
      transition: transform .15s ease; }
    .photo-tile:hover img { transform: scale(1.03); }

    /* Lightbox overlay for full-size photo viewing */
    .lb-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.92);
      display: none; align-items: center; justify-content: center;
      z-index: 9999; -webkit-font-smoothing: antialiased;
    }
    .lb-overlay.open { display: flex; }
    /* .lb-stage sizes itself to the image's rendered box via inline width/height
       set by JS; the prev/next arrows are then positioned against its edges. */
    .lb-stage { position: relative; display: inline-block; line-height: 0; }
    .lb-img {
      max-width: calc(100vw - 120px); max-height: calc(100vh - 180px);
      display: block; object-fit: contain; border-radius: 4px;
      box-shadow: 0 8px 40px rgba(0,0,0,0.5);
    }
    /* White chevron arrows (SVG), no chrome. Anchored to image left/right edges. */
    .lb-prev, .lb-next {
      position: absolute; top: 50%; transform: translateY(-50%);
      background: transparent; border: none; cursor: pointer; padding: 12px 16px;
      display: flex; align-items: center; justify-content: center;
    }
    .lb-prev svg, .lb-next svg {
      width: 40px; height: 40px; stroke: #fff; stroke-width: 2.5;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.6));
    }
    .lb-prev { left: 4px; }
    .lb-next { right: 4px; }

    /* Close: plain X above image, aligned with its right edge. */
    .lb-close {
      position: absolute; top: -36px; right: 0;
      width: 28px; height: 28px;
      background: transparent; border: none; cursor: pointer;
      color: #fff; padding: 0;
      display: flex; align-items: center; justify-content: center;
    }
    .lb-close svg { width: 24px; height: 24px; stroke: #fff; stroke-width: 2;
                    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.6)); }

    /* Counter: plain text above image, aligned with its left edge. */
    .lb-counter {
      position: absolute; top: -30px; left: 0;
      color: #fff; font-size: 14px; font-weight: 500;
      line-height: 1.2; letter-spacing: 0.02em;
    }

    .lb-caption {
      position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%);
      color: #d3d1c7; font-size: 13px; background: rgba(0,0,0,0.5);
      padding: 6px 14px; border-radius: 20px; white-space: nowrap;
      max-width: calc(100vw - 40px); overflow: hidden; text-overflow: ellipsis;
      line-height: 1.2;
    }
    @media (max-width: 640px) {
      .lb-img { max-width: calc(100vw - 20px); max-height: calc(100vh - 140px); }
      .lb-prev, .lb-next { padding: 16px 12px; }
      .lb-prev svg, .lb-next svg { width: 50px; height: 50px; }
      .lb-prev { left: 2px; } .lb-next { right: 2px; }
      .lb-counter { font-size: 13px; top: -26px; }
    }

    .footer { text-align: center; font-size: 11.5px; color: #888780; margin-top: 24px; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="logo-bar">
    <img src="/assets/integra.svg" alt="Integra">
  </div>

  <!-- ── HERO ── -->
  <div class="card hero">
    <div class="rma-number"><?= htmlspecialchars($rma['rma_number']) ?></div>
    <div class="pills">
      <span class="pill"
            style="background:<?= htmlspecialchars($rma['status_color']) ?>22;color:<?= htmlspecialchars($rma['status_color']) ?>;">
        <?= status_label((string)($rma['status_code'] ?? ''), $rma['status_label'], $track_lang) ?>
      </span>
      <?php if ($rma['is_warranty']): ?>
        <span class="pill pill-warranty"><?= $tt('rma.warranty') ?></span>
      <?php endif; ?>
    </div>
    <?php if ($rma['created_at'] || ($vis['est_completion'] && $rma['estimated_completion'])): ?>
      <div class="eta">
        <?php if ($vis['est_completion'] && $rma['estimated_completion']): ?>
          <div>
            <div class="eta-label"><?= $tt('track.est_completion') ?></div>
            <div class="eta-value"><?= format_date($rma['estimated_completion']) ?></div>
          </div>
        <?php endif; ?>
        <div>
          <div class="eta-label"><?= $tt('track.submitted') ?></div>
          <div class="eta-value"><?= format_date($rma['created_at']) ?></div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Return shipment (device on its way back) ── -->
  <?php if (!empty($return_shipment) && !empty($return_shipment['tracking_number'])):
      $ret_url = courier_tracking_url($return_shipment['courier_tracking_url'] ?? null, $return_shipment['tracking_number']); ?>
  <div class="card" style="border:1px solid var(--accent, #1D9E75);">
    <p class="card-title"><?= $tt('track.return_shipment') ?></p>
    <p style="font-size:14px;line-height:1.5;margin-bottom:10px;"><?= $tt('track.return_shipment_msg') ?></p>
    <div style="display:flex;flex-wrap:wrap;gap:18px;font-size:14px;">
      <?php if (!empty($return_shipment['courier_name'])): ?>
        <span><?= $tt('ship.courier') ?>: <strong><?= htmlspecialchars($return_shipment['courier_name']) ?></strong></span>
      <?php endif; ?>
      <span><?= $tt('ship.tracking') ?>:
        <?php if ($ret_url): ?>
          <a href="<?= htmlspecialchars($ret_url) ?>" target="_blank" rel="noopener" style="font-weight:600;"><?= htmlspecialchars($return_shipment['tracking_number']) ?></a>
        <?php else: ?>
          <strong><?= htmlspecialchars($return_shipment['tracking_number']) ?></strong>
        <?php endif; ?>
      </span>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── ROW: Customer | Device ── -->
  <?php
  $has_customer = $rma['customer_name'] || $rma['customer_phone'] || $rma['customer_email'];
  $has_device   = $vis['device'] && ($rma['model_name'] || $rma['serial_number'] || !empty($rma['imei']));
  ?>
  <?php if ($has_customer || $has_device): ?>
  <div class="row-2">

    <?php if ($has_customer): ?>
    <div class="card">
      <p class="card-title"><?= $tt('rma.customer') ?></p>
      <?php if ($rma['customer_name']): ?>
        <div class="detail-row"><span class="k"><?= $tt('label.name') ?></span><span class="v"><strong><?= htmlspecialchars($rma['customer_name']) ?></strong></span></div>
      <?php endif; ?>
      <?php if ($rma['customer_phone']): ?>
        <div class="detail-row"><span class="k"><?= $tt('label.phone') ?></span><span class="v"><?= htmlspecialchars(format_phone($rma['customer_phone'])) ?></span></div>
      <?php endif; ?>
      <?php if ($rma['customer_email']): ?>
        <div class="detail-row"><span class="k"><?= $tt('label.email') ?></span><span class="v"><?= htmlspecialchars($rma['customer_email']) ?></span></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($has_device): ?>
    <div class="card">
      <p class="card-title"><?= $tt('track.device') ?></p>
      <?php if ($rma['brand_name'] || $rma['model_name']): ?>
        <div class="detail-row">
          <span class="k"><?= $tt('reports.model') ?></span>
          <span class="v"><strong><?= htmlspecialchars(trim(($rma['brand_name'] ?? '').' '.($rma['model_name'] ?? ''))) ?></strong></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($rma['imei'])): ?>
        <div class="detail-row">
          <span class="k">IMEI</span>
          <span class="v mono"><?= htmlspecialchars($rma['imei']) ?></span>
        </div>
      <?php elseif (!empty($rma['serial_number'])): ?>
        <div class="detail-row">
          <span class="k"><?= $tt('misc.serial') ?></span>
          <span class="v mono"><?= htmlspecialchars($rma['serial_number']) ?></span>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

  <!-- ── ROW: Accessories | Reported issue (complaint) ── -->
  <?php
  $acc_text = format_rma_accessories($rma, customer_lang($rma['customer_lang'] ?? null));
  $complaint = trim((string)($rma['complaint'] ?? ''));
  ?>
  <?php if ($acc_text || $complaint !== ''): ?>
  <div class="row-2">

    <?php if ($acc_text): ?>
    <div class="card">
      <p class="card-title"><?= $tt('portal.accessories_received') ?></p>
      <p class="prose"><?= htmlspecialchars($acc_text) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($complaint !== ''): ?>
    <div class="card">
      <p class="card-title"><?= $tt('portal.reported_issue') ?></p>
      <p class="prose"><?= htmlspecialchars($complaint) ?></p>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

  <!-- ── ROW: Technician findings | Work done ── -->
  <?php
  $rep = null;
  if ($vis['tech_notes'] && !empty($repair_jobs)) {
      foreach (array_reverse($repair_jobs) as $j) {
          if (trim((string)($j['description'] ?? '')) !== ''
              || trim((string)($j['resolution']  ?? '')) !== '') {
              $rep = $j; break;
          }
      }
  }
  $findings   = $rep ? trim((string)$rep['description']) : '';
  $resolution = $rep ? trim((string)$rep['resolution'])  : '';
  ?>
  <?php if ($findings !== '' || $resolution !== ''): ?>
  <div class="row-2">

    <div class="card">
      <p class="card-title"><?= $tt('portal.tech_findings') ?></p>
      <?php if ($findings !== ''): ?>
        <p class="prose"><?= htmlspecialchars($findings) ?></p>
      <?php else: ?>
        <p class="muted"><?= $tt('portal.not_diagnosed') ?></p>
      <?php endif; ?>
    </div>

    <div class="card">
      <p class="card-title"><?= $tt('portal.work_done') ?></p>
      <?php if ($resolution !== ''): ?>
        <p class="prose"><?= htmlspecialchars($resolution) ?></p>
      <?php else: ?>
        <p class="muted"><?= $tt('portal.work_not_done') ?></p>
      <?php endif; ?>
      <?php if (!empty($rep['technician_name']) || !empty($rep['completed_at'])): ?>
        <p class="meta-line">
          <?php if (!empty($rep['technician_name'])): ?><?= $tt('rma.technician') ?>: <?= htmlspecialchars($rep['technician_name']) ?><?php endif; ?>
          <?php if (!empty($rep['completed_at'])): ?><?= !empty($rep['technician_name']) ? ' · ' : '' ?><?= $tt('misc.completed') ?> <?= format_date($rep['completed_at']) ?><?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

  </div>
  <?php endif; ?>

  <!-- ── PHOTO EVIDENCE (full width, only if any) ── -->
  <?php if ($vis['tech_notes'] && !empty($photos)): ?>
  <div class="card">
    <p class="card-title"><?= $tt('misc.photo_evidence') ?></p>
    <div class="photo-grid" id="lb-grid">
      <?php foreach ($photos as $idx => $ph):
        $src = '/storage/' . $ph['filename'];
        $stage = (string)($ph['stage'] ?? '');
        // Caption = RMA number (dashes stripped) · date taken
        $rma_compact = str_replace('-', '', (string)$rma['rma_number']);
        $caption = trim($rma_compact
                     . ($ph['created_at'] ? ' · ' . format_datetime($ph['created_at']) : ''));
      ?>
        <button type="button" class="photo-tile"
                data-full="<?= htmlspecialchars($src) ?>"
                data-caption="<?= htmlspecialchars($caption) ?>"
                data-index="<?= $idx ?>"
                aria-label="<?= htmlspecialchars($ph['original_name'] ?? '') ?: $tt('misc.evidence_photo') ?>">
          <img src="<?= htmlspecialchars($src) ?>"
               alt="<?= htmlspecialchars($ph['original_name'] ?? '') ?: $tt('misc.evidence_photo') ?>"
               loading="lazy">
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Lightbox overlay -->
  <div class="lb-overlay" id="lb" role="dialog" aria-modal="true" aria-label="<?= $tt('misc.photo_viewer') ?>">
    <div class="lb-stage">
      <img class="lb-img" id="lb-img" alt="">
      <div class="lb-counter" id="lb-counter"></div>
      <button type="button" class="lb-close" id="lb-close" aria-label="<?= $tt('misc.close') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <button type="button" class="lb-prev" id="lb-prev" aria-label="<?= $tt('misc.previous') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <button type="button" class="lb-next" id="lb-next" aria-label="<?= $tt('misc.next') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
    <div class="lb-caption" id="lb-caption"></div>
  </div>

  <script>
  (function () {
    var grid = document.getElementById('lb-grid');
    if (!grid) return;
    var tiles    = grid.querySelectorAll('.photo-tile');
    var overlay  = document.getElementById('lb');
    var imgEl    = document.getElementById('lb-img');
    var capEl    = document.getElementById('lb-caption');
    var counter  = document.getElementById('lb-counter');
    var prevBtn  = document.getElementById('lb-prev');
    var nextBtn  = document.getElementById('lb-next');
    var total    = tiles.length;
    var current  = 0;

    function show(i) {
      // Clamp to [0, total-1] instead of wrapping. Ignore moves past the ends.
      if (i < 0 || i >= total) return;
      current = i;
      var t = tiles[current];
      imgEl.src = t.getAttribute('data-full');
      imgEl.alt = t.getAttribute('aria-label') || '';
      capEl.textContent = t.getAttribute('data-caption') || '';
      counter.textContent = (current + 1) + ' / ' + total;
      // Hide the arrow on the side that has no further photos.
      prevBtn.style.visibility = (current === 0)          ? 'hidden' : '';
      nextBtn.style.visibility = (current === total - 1)  ? 'hidden' : '';
    }

    function open(i) {
      show(i);
      overlay.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function close() {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
      imgEl.src = '';
    }

    tiles.forEach(function (t, i) {
      t.addEventListener('click', function () { open(i); });
    });
    document.getElementById('lb-close').addEventListener('click', function (e) {
      e.stopPropagation(); close();
    });
    prevBtn.addEventListener('click', function (e) { e.stopPropagation(); show(current - 1); });
    nextBtn.addEventListener('click', function (e) { e.stopPropagation(); show(current + 1); });

    // Close only when the click lands on the overlay backdrop itself (not on
    // the image or any button). .lb-stage wraps everything image-anchored.
    overlay.addEventListener('click', function (e) {
      if (e.target.closest('.lb-stage')) return;
      close();
    });

    document.addEventListener('keydown', function (e) {
      if (!overlay.classList.contains('open')) return;
      if (e.key === 'Escape')     close();
      if (e.key === 'ArrowLeft')  show(current - 1);
      if (e.key === 'ArrowRight') show(current + 1);
    });

    // Swipe navigation on touch devices — clamped (no wrap) and ignored when
    // the touch started on a button, so taps aren't misread as tiny swipes.
    var touchX = null;
    overlay.addEventListener('touchstart', function (e) {
      if (e.target.closest('.lb-prev, .lb-next, .lb-close')) { touchX = null; return; }
      touchX = e.touches[0].clientX;
    }, {passive: true});
    overlay.addEventListener('touchend', function (e) {
      if (touchX === null) return;
      var dx = e.changedTouches[0].clientX - touchX;
      touchX = null;
      if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
    });
  })();
  </script>
  <?php endif; ?>

  <!-- ── PROGRESS (full width) ── -->
  <?php if ($vis['status'] && !empty($history)): ?>
  <div class="card">
    <p class="card-title"><?= $tt('misc.progress') ?></p>
    <div class="timeline">
      <?php
      $last_i = count($history) - 1;
      foreach ($history as $i => $h):
        $is_current = ($i === $last_i);
        $color = htmlspecialchars($h['status_color'] ?? '#888780');
      ?>
        <div class="tl-item<?= $is_current ? ' is-current' : '' ?>"
             style="--status-color: <?= $color ?>;">
          <div class="tl-dot" style="background:<?= $color ?>;"></div>
          <div class="tl-label"><?= status_label((string)($h['status_code'] ?? ''), $h['status_label'], $track_lang) ?></div>
          <div class="tl-date"><?= format_datetime($h['created_at']) ?></div>
          <?php if (trim((string)$h['note']) !== ''): ?>
            <div class="tl-note"><?= htmlspecialchars($h['note']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Optional trailing cards: Comments + Delivery/Invoice side-by-side ── -->
  <?php if (!empty($comments)): ?>
  <div class="card">
    <p class="card-title"><?= $tt('rma.comments') ?></p>
    <?php foreach ($comments as $c):
      // Source is authoritative: staff type it either way, but `source`
      // says whose words these actually are.
      $is_customer = ($c['source'] ?? 'staff') === 'customer';
      $label = $is_customer ? $tt('misc.customer_comment') : $tt('misc.staff_note');
    ?>
      <div class="comment <?= $is_customer ? 'comment-customer' : 'comment-staff' ?>">
        <div class="comment-head">
          <span class="comment-author"><?= htmlspecialchars($label) ?></span>
          <span class="comment-date"><?= format_datetime($c['created_at']) ?></span>
        </div>
        <?= nl2br(htmlspecialchars($c['body'])) ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  $show_delivery = $vis['delivery'] && $shipment;
  $show_invoice  = $vis['invoice'] && $invoice;
  ?>
  <?php if ($show_delivery || $show_invoice): ?>
  <div class="row-2">

    <?php if ($show_delivery): ?>
    <div class="card">
      <p class="card-title"><?= $tt('misc.delivery') ?></p>
      <?php if (!empty($shipment['status'])): ?>
        <div class="detail-row"><span class="k"><?= $tt('label.status') ?></span><span class="v"><?= htmlspecialchars($shipment['status']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($shipment['tracking_number'])): ?>
        <div class="detail-row"><span class="k"><?= $tt('misc.tracking') ?></span><span class="v mono"><?= htmlspecialchars($shipment['tracking_number']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($shipment['delivered_at'])): ?>
        <div class="detail-row"><span class="k"><?= $tt('misc.delivered') ?></span><span class="v"><?= format_date($shipment['delivered_at']) ?></span></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($show_invoice): ?>
    <div class="card">
      <p class="card-title"><?= $tt('track.invoice') ?></p>
      <div class="detail-row"><span class="k"><?= $tt('misc.number') ?></span><span class="v"><?= htmlspecialchars($invoice['invoice_number']) ?></span></div>
      <div class="detail-row"><span class="k"><?= $tt('misc.amount') ?></span><span class="v"><?= number_format((float)$invoice['total'], 2) ?> <?= htmlspecialchars($invoice['currency']) ?></span></div>
      <div class="detail-row"><span class="k"><?= $tt('label.status') ?></span><span class="v"><?= htmlspecialchars(ucfirst($invoice['status'])) ?></span></div>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

  <p class="footer">&copy; <?= date('Y') ?> Integra</p>

</div>
</body>
</html>
