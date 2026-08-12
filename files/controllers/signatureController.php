<?php
defined('RMS') or die('Direct access not permitted');

class SignatureController {

    // ── Mint a signing token (staff clicks "Get signature" on receipt) ─────
    public function token(): void {
        require_login();
        require_permission('rma', 'edit');
        header('Content-Type: application/json');

        $rma_id = (int)($_POST['rma_id'] ?? 0);
        if (!$rma_id) {
            echo json_encode(['success' => false, 'error' => 'Missing rma_id']);
            return;
        }

        // Scope check: main admin OR RMA's location in allowed_location_ids.
        $rma = db_row('SELECT id, location_id FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [$rma_id]);
        if (!$rma) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'RMA not found']);
            return;
        }
        $allowed_locs = allowed_location_ids();
        if ($allowed_locs !== null && !in_array((int)$rma['location_id'], array_map('intval', $allowed_locs), true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        // Invalidate any prior pending tokens for this RMA — one active at a time.
        db()->prepare(
            'UPDATE rma_signatures SET deleted_at = NOW()
             WHERE rma_id = ? AND signed_at IS NULL AND deleted_at IS NULL'
        )->execute([$rma_id]);

        $token      = bin2hex(random_bytes(20));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        db_insert('rma_signatures', [
            'rma_id'     => $rma_id,
            'token'      => $token,
            'expires_at' => $expires_at,
        ]);

        $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $url    = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/sign/' . $token;

        echo json_encode(['success' => true, 'token' => $token, 'url' => $url]);
    }

    // ── Mobile signing page — customer scans QR, signs on their device ────
    public function mobile(string $token): void {
        $sig = db_row(
            'SELECT s.*, r.rma_number, r.complaint,
                    c.name AS customer_name, c.lang AS customer_lang,
                    dm.name AS model_name, db2.name AS brand_name,
                    d.serial_number, d.imei
             FROM rma_signatures s
             JOIN rma_requests r ON r.id = s.rma_id
             LEFT JOIN customers c ON c.id = r.customer_id
             LEFT JOIN devices d ON d.id = r.device_id
             LEFT JOIN device_models dm ON dm.id = d.model_id
             LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
             WHERE s.token = ?
               AND s.expires_at > NOW()
               AND s.deleted_at IS NULL',
            [$token]
        );

        if (!$sig) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
               . '<meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<title>' . htmlspecialchars(__('sign.expired_title')) . '</title>'
               . '<link href="/assets/css/fonts.css" rel="stylesheet">'
               . '<style>body{font-family:"Montserrat",system-ui,-apple-system,sans-serif;padding:2rem;text-align:center;background:#f4f4f0;color:#2c2c2a;}</style>'
               . '</head><body><h2>' . htmlspecialchars(__('sign.expired_title')) . '</h2>'
               . '<p>' . htmlspecialchars(__('sign.expired_text')) . '</p></body></html>';
            return;
        }

        if (!empty($sig['signed_at'])) {
            // Already signed — show the "thank you" screen, don't accept another signature.
            $this->render_done($sig['rma_number'], customer_lang($sig['customer_lang'] ?? null));
            return;
        }

        if (empty($sig['visited_at'])) {
            db_update('rma_signatures', ['visited_at' => date('Y-m-d H:i:s')], 'token = ?', [$token]);
        }

        // This page is read by the customer on their own phone, so it follows
        // THEIR language — the same rule the printed receipt uses — not the
        // language of whoever is logged in at the counter.
        $lang = customer_lang($sig['customer_lang'] ?? null);
        $st   = fn(string $k): string => htmlspecialchars(__in($lang, $k));

        $device  = trim(($sig['brand_name'] ?? '') . ' ' . ($sig['model_name'] ?? ''));
        $ident   = $sig['imei'] ?: ($sig['serial_number'] ?? '');
        $h_rma   = htmlspecialchars($sig['rma_number']);
        $h_cust  = htmlspecialchars($sig['customer_name'] ?? '');
        $h_dev   = htmlspecialchars($device);
        $h_ident = htmlspecialchars($ident);
        $h_comp  = htmlspecialchars($sig['complaint'] ?? '');
        $h_tok   = htmlspecialchars($token);
        ?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<title><?= $st('sign.title') ?> — <?= $h_rma ?></title>
<link href="/assets/css/fonts.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;overflow:hidden;}
body{font-family:'Montserrat',system-ui,sans-serif;background:#f4f4f0;color:#2c2c2a;
     display:flex;flex-direction:column;height:100dvh;-webkit-user-select:none;user-select:none;}

.top{padding:14px 20px;flex-shrink:0;background:#fff;border-bottom:0.5px solid #e8e6e0;}
.top .logo img{height:40px;vertical-align:middle;}
.summary{padding:14px 20px;font-size:13px;flex-shrink:0;}
.summary .row{display:flex;margin-bottom:4px;}
.summary .k{color:#888780;width:84px;flex-shrink:0;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;}
.summary .v{color:#2c2c2a;font-weight:500;}
.summary .note{font-size:12px;color:#5f5e5a;margin-top:10px;line-height:1.4;padding-top:10px;border-top:0.5px dashed #d3d1c7;}

.pad-wrap{flex:1;position:relative;margin:10px 16px;background:#fff;border-radius:12px;
          border:0.5px solid #d3d1c7;overflow:hidden;min-height:0;}
.pad-hint{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
          color:#b5b2a8;font-size:14px;pointer-events:none;transition:opacity .15s;}
.pad-hint.hide{opacity:0;}
canvas{position:absolute;inset:0;width:100%;height:100%;display:block;touch-action:none;}

.baseline{position:absolute;left:24px;right:24px;bottom:28px;border-bottom:1px solid #d3d1c7;pointer-events:none;}
.baseline::after{content:'<?= $st('sign.here') ?>';position:absolute;left:0;top:6px;font-size:11px;color:#b5b2a8;}

.bottom{background:#fff;border-top:0.5px solid #e8e6e0;padding:12px 16px;
        padding-bottom:max(12px,env(safe-area-inset-bottom));flex-shrink:0;
        display:flex;gap:8px;}
.btn{flex:1;padding:14px;font-size:15px;font-weight:600;border:none;border-radius:10px;
     font-family:inherit;cursor:pointer;}
.btn-clear{background:#fff;color:#5f5e5a;border:0.5px solid #d3d1c7;flex:0 0 120px;}
.btn-submit{background:#1D9E75;color:#fff;}
.btn-submit:disabled{background:#b5b2a8;}

.sig-error{display:none;margin:0 16px 8px;padding:10px 14px;border-radius:8px;
  background:#fcebeb;color:#791f1f;border:0.5px solid #f09595;font-size:14px;line-height:1.4;}
.sig-error.show{display:block;}
.done{position:fixed;inset:0;background:#1D9E75;color:#fff;display:none;
      flex-direction:column;align-items:center;justify-content:center;z-index:9999;
      gap:16px;padding:40px;text-align:center;}
.done.show{display:flex;}
.done svg{width:72px;height:72px;}
.done h2{font-size:22px;font-weight:700;}
.done p{font-size:14px;opacity:0.9;}
</style>
</head>
<body>

<div class="top">
  <span class="logo"><img src="/assets/integra.svg" alt="Integra"></span>
</div>

<div class="summary">
  <?php if ($h_rma):  ?><div class="row"><span class="k"><?= $st('sign.rma') ?></span><span class="v"><?= $h_rma ?></span></div><?php endif; ?>
  <?php if ($h_cust): ?><div class="row"><span class="k"><?= $st('sign.name') ?></span><span class="v"><?= $h_cust ?></span></div><?php endif; ?>
  <?php if ($h_dev):  ?><div class="row"><span class="k"><?= $st('sign.device') ?></span><span class="v"><?= $h_dev ?></span></div><?php endif; ?>
  <?php if ($h_ident):?><div class="row"><span class="k"><?= $st('pdf.sn_imei') ?></span><span class="v"><?= $h_ident ?></span></div><?php endif; ?>
  <div class="note"><?= $st('sign.consent') ?></div>
</div>

<div class="pad-wrap" id="pad-wrap">
  <div class="pad-hint" id="pad-hint"><?= $st('sign.hint') ?></div>
  <canvas id="pad"></canvas>
  <div class="baseline"></div>
</div>

<div class="sig-error" id="sig-error"></div>

<div class="bottom">
  <button type="button" class="btn btn-clear" id="btn-clear"><?= $st('sign.clear') ?></button>
  <button type="button" class="btn btn-submit" id="btn-submit" disabled><?= $st('sign.submit') ?></button>
</div>

<div class="done" id="done">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
    <circle cx="12" cy="12" r="10"/><polyline points="20 6 9 17 4 12" stroke-width="2"/>
  </svg>
  <h2><?= $st('sign.done_title') ?></h2>
  <p><?= $st('sign.done_text') ?></p>
</div>

<script>
(function () {
  var canvas  = document.getElementById('pad');
  var ctx     = canvas.getContext('2d');
  var hint    = document.getElementById('pad-hint');
  var btnClr  = document.getElementById('btn-clear');
  var btnSub  = document.getElementById('btn-submit');
  var done    = document.getElementById('done');
  var token   = <?= json_encode($h_tok) ?>;
  var dirty   = false;
  var drawing = false;
  var last    = null;

  function resize() {
    // HiDPI-aware canvas sizing. Preserve any strokes by rescaling.
    var ratio = window.devicePixelRatio || 1;
    var w = canvas.clientWidth, h = canvas.clientHeight;
    canvas.width = w * ratio; canvas.height = h * ratio;
    ctx.setTransform(1,0,0,1,0,0); ctx.scale(ratio, ratio);
    ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1a1a18';
  }
  window.addEventListener('resize', resize, {passive: true});
  resize();

  function pos(e) {
    var r = canvas.getBoundingClientRect();
    var p = e.touches ? e.touches[0] : e;
    return {x: p.clientX - r.left, y: p.clientY - r.top};
  }
  function start(e) {
    e.preventDefault(); drawing = true;
    last = pos(e);
    if (!dirty) { dirty = true; hint.classList.add('hide'); btnSub.disabled = false; }
  }
  function move(e) {
    if (!drawing) return;
    e.preventDefault();
    var p = pos(e);
    // Quadratic smoothing — midpoint control for a continuous Bézier feel.
    var mx = (last.x + p.x) / 2, my = (last.y + p.y) / 2;
    ctx.beginPath(); ctx.moveTo(last.x, last.y);
    ctx.quadraticCurveTo(last.x, last.y, mx, my);
    ctx.stroke();
    last = p;
  }
  function end() { drawing = false; last = null; }

  canvas.addEventListener('pointerdown', start);
  canvas.addEventListener('pointermove', move);
  canvas.addEventListener('pointerup',   end);
  canvas.addEventListener('pointercancel', end);
  canvas.addEventListener('pointerleave',  end);

  btnClr.addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    dirty = false; hint.classList.remove('hide'); btnSub.disabled = true;
  });

  // Inline banner instead of a native alert() — this page runs standalone on the
  // customer's phone, so the app's shared modal isn't available here.
  var errEl = document.getElementById('sig-error');
  function showSigError(msg) { errEl.textContent = msg; errEl.classList.add('show'); }

  btnSub.addEventListener('click', function () {
    if (!dirty) return;
    errEl.classList.remove('show');
    btnSub.disabled = true; btnSub.textContent = 'Uploading…';
    canvas.toBlob(function (blob) {
      var fd = new FormData();
      fd.append('signature', blob, 'signature.png');
      fetch(location.href + '/save', {method: 'POST', body: fd})
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            done.classList.add('show');
          } else {
            btnSub.disabled = false; btnSub.textContent = 'Submit';
            showSigError(res && res.error ? res.error : <?= json_encode(__('misc.upload_failed')) ?>);
          }
        })
        .catch(function () {
          btnSub.disabled = false; btnSub.textContent = 'Submit';
          showSigError(<?= json_encode(__('misc.network_error')) ?>);
        });
    }, 'image/png');
  });
})();
</script>
</body>
</html><?php
    }

    // ── Save endpoint — POST /sign/{token}/save ────────────────────────────
    public function save(string $token): void {
        header('Content-Type: application/json');

        $sig = db_row(
            'SELECT * FROM rma_signatures
             WHERE token = ? AND expires_at > NOW() AND deleted_at IS NULL
               AND signed_at IS NULL',
            [$token]
        );
        if (!$sig) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Link expired or already used']);
            return;
        }

        if (empty($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No signature received']);
            return;
        }

        $tmp = $_FILES['signature']['tmp_name'];
        if ($_FILES['signature']['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Signature too large']);
            return;
        }

        // Sanity check: must actually be a PNG.
        $head = @file_get_contents($tmp, false, null, 0, 8);
        if ($head !== "\x89PNG\r\n\x1a\n") {
            echo json_encode(['success' => false, 'error' => 'Invalid file format']);
            return;
        }

        $year  = date('Y'); $month = date('m');
        $dir   = ROOT . "/storage/signatures/{$year}/{$month}";
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $filename = 'sig_' . uniqid('', true) . '.png';
        $dest     = $dir . '/' . $filename;
        if (!@move_uploaded_file($tmp, $dest) && !@copy($tmp, $dest)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save']);
            return;
        }

        $stored = "signatures/{$year}/{$month}/{$filename}";
        db_update('rma_signatures', [
            'filename'   => $stored,
            'signed_at'  => date('Y-m-d H:i:s'),
            'signer_ip'  => client_ip(),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ], 'token = ?', [$token]);

        audit('signature_received', 'rma', (int)$sig['rma_id']);
        echo json_encode(['success' => true]);
    }

    // ── Desktop polling endpoint — has this token been signed yet? ────────
    public function status(): void {
        require_login();
        header('Content-Type: application/json');

        $token = $_GET['token'] ?? '';
        if (!$token) { echo json_encode(['signed' => false, 'visited' => false]); return; }

        $sig = db_row(
            'SELECT rma_id, filename, visited_at, signed_at
             FROM rma_signatures WHERE token = ? AND deleted_at IS NULL',
            [$token]
        );
        if (!$sig) { echo json_encode(['signed' => false, 'visited' => false, 'expired' => true]); return; }

        echo json_encode([
            'signed'  => !empty($sig['signed_at']),
            'visited' => !empty($sig['visited_at']),
            'url'     => !empty($sig['filename']) ? '/storage/' . $sig['filename'] : null,
        ]);
    }

    // ── Signing-station idle display (tablet permanently shows this) ──────
    public function station(string $key): void {
        $station = db_row(
            'SELECT s.*, l.name AS location_name FROM signing_stations s
             JOIN locations l ON l.id = s.location_id
             WHERE s.station_key = ? AND s.is_active = 1', [$key]
        );
        if (!$station) {
            http_response_code(404);
            echo '<!DOCTYPE html><meta charset="UTF-8"><title>Station not found</title>';
            echo '<link href="/assets/css/fonts.css" rel="stylesheet">';
            echo '<body style="font-family:\'Montserrat\',system-ui,-apple-system,sans-serif;padding:3rem;text-align:center;background:#f4f4f0;">';
            echo '<h2>Station not found or inactive</h2>';
            echo '<p>Ask an administrator to set up this device.</p></body>';
            return;
        }
        db_update('signing_stations', ['last_poll_at' => date('Y-m-d H:i:s')], 'id = ?', [$station['id']]);

        $h_key  = htmlspecialchars($key);
        $h_name = htmlspecialchars($station['name']);
        $h_loc  = htmlspecialchars($station['location_name']);
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#1A1A1F">
<title>Signing Station — <?= $h_name ?></title>
<link href="/assets/css/fonts.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;}
body{font-family:'Montserrat',system-ui,sans-serif;background:#1A1A1F;color:#fff;
     display:flex;flex-direction:column;align-items:center;justify-content:center;
     min-height:100dvh;padding:40px;text-align:center;-webkit-user-select:none;user-select:none;
     overflow:hidden;}
.logo img{height:46px;filter:brightness(0) invert(1);opacity:0.95;margin-bottom:40px;}
.clock{font-size:72px;font-weight:300;letter-spacing:-0.02em;line-height:1;margin-bottom:8px;
       font-variant-numeric:tabular-nums;}
.date{font-size:18px;opacity:0.55;font-weight:400;margin-bottom:48px;}
.state{font-size:22px;font-weight:500;}
.state .sub{font-size:14px;opacity:0.6;margin-top:10px;font-weight:400;}
.station-label{position:fixed;bottom:20px;font-size:12px;opacity:0.4;letter-spacing:0.06em;text-transform:uppercase;}
.loading-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#1D9E75;
             margin-right:8px;animation:pulse 2s infinite;vertical-align:middle;}
@keyframes pulse {
  0%,100% { opacity: 0.4; transform: scale(1); }
  50%     { opacity: 1;   transform: scale(1.2); }
}
</style>
</head>
<body>

<div class="logo"><img src="/assets/integra.svg" alt="Integra"></div>
<div class="clock" id="clock">—:—</div>
<div class="date"  id="date">&nbsp;</div>
<div class="state"><span class="loading-dot"></span>Ready for signature</div>
<div class="state"><span class="sub">When the counter staff requests one, this screen will change automatically.</span></div>

<div class="station-label"><?= $h_name ?> · <?= $h_loc ?></div>

<script>
(function () {
  // Clock
  function tick() {
    var d = new Date();
    var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
    document.getElementById('clock').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    document.getElementById('date').textContent  = d.toLocaleDateString(undefined,
      {weekday:'long', day:'numeric', month:'long', year:'numeric'});
  }
  tick();
  setInterval(tick, 15000);

  // Poll for a pending signature for this station's location.
  var key = <?= json_encode($h_key) ?>;
  setInterval(function () {
    fetch('/station/' + key + '/next', {cache: 'no-store'})
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.url) {
          // Navigate to the signing URL — becomes the customer-facing page.
          window.location.href = res.url;
        }
      })
      .catch(function () { /* transient, try again next tick */ });
  }, 2000);

  // Keep the device awake if supported (tablet screens tend to sleep).
  if ('wakeLock' in navigator) {
    var tryLock = function () {
      navigator.wakeLock.request('screen').catch(function () {});
    };
    tryLock();
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') tryLock();
    });
  }
})();
</script>
</body>
</html><?php
    }

    // ── Poll endpoint for a station: "any pending unsigned signature?" ───
    public function station_poll(string $key): void {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $station = db_row(
            'SELECT id, location_id FROM signing_stations
             WHERE station_key = ? AND is_active = 1',
            [$key]
        );
        if (!$station) { http_response_code(404); echo json_encode(['ok' => false]); return; }

        db_update('signing_stations', ['last_poll_at' => date('Y-m-d H:i:s')], 'id = ?', [$station['id']]);

        // Most recent unsigned, unvisited, unexpired token for any RMA at
        // this station's location. "Unvisited" means no device has picked
        // it up yet — once the tablet navigates, visited_at is set by the
        // /sign/{token} handler, and the next poll returns nothing.
        $row = db_row(
            "SELECT s.token FROM rma_signatures s
             JOIN rma_requests r ON r.id = s.rma_id
             WHERE r.location_id = ?
               AND s.signed_at IS NULL
               AND s.visited_at IS NULL
               AND s.expires_at > NOW()
               AND s.deleted_at IS NULL
             ORDER BY s.created_at DESC
             LIMIT 1",
            [(int)$station['location_id']]
        );

        if ($row) {
            echo json_encode(['ok' => true, 'url' => '/sign/' . $row['token']]);
        } else {
            echo json_encode(['ok' => true, 'url' => null]);
        }
    }

    // ── "Thank you" screen after the page auto-closes a signed token ──────
    private function render_done(string $rma_number, string $lang = 'me'): void {
        $st = fn(string $k): string => htmlspecialchars(__in($lang, $k));
        ?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $st('sign.done_title') ?></title>
<link href="/assets/css/fonts.css" rel="stylesheet">
<style>
body{margin:0;background:#1D9E75;color:#fff;font-family:Montserrat,system-ui,sans-serif;
     display:flex;flex-direction:column;align-items:center;justify-content:center;
     min-height:100dvh;text-align:center;padding:40px;gap:16px;}
svg{width:72px;height:72px;stroke:#fff;stroke-width:1.5;}
h2{font-size:22px;font-weight:700;margin:0;}
p{font-size:14px;opacity:0.9;margin:0;}
</style></head>
<body>
<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10"/><polyline points="20 6 9 17 4 12" stroke-width="2"/></svg>
<h2><?= $st('sign.done_title') ?></h2>
<p>RMA <?= htmlspecialchars($rma_number) ?></p>
<p><?= $st('sign.done_text') ?></p>
</body></html><?php
    }
}
