<?php defined('RMS') or die('Direct access not permitted');

class evidenceController {

    public function mobile(string $token): void {
        $row = db_row('SELECT * FROM repair_evidence_tokens WHERE token = ? AND expires_at > NOW()', [$token]);

        if (!$row) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Link Expired</title>
<link href="/assets/css/fonts.css" rel="stylesheet">
<style>body{font-family:"Montserrat",system-ui,-apple-system,sans-serif;padding:2rem;text-align:center;background:#f5f4f0;}h2{margin-bottom:1rem;}p{color:#888;}</style>
</head><body><h2>Link expired or invalid</h2><p>Please ask staff to generate a new QR code.</p></body></html>';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            $result = $this->_saveFile(
                $_FILES['file'] ?? null,
                $row['repair_job_id'] ? (int)$row['repair_job_id'] : null,
                $row['rma_id'] ? (int)$row['rma_id'] : null,
                $row['stage']
            );
            echo json_encode($result);
            return;
        }

        // Mark as visited on first GET
        if (!$row['visited_at']) {
            db_update('repair_evidence_tokens', ['visited_at' => date('Y-m-d H:i:s')], 'token = ?', [$token]);
        }

        // Fetch device info for filename prefix
        $device_info = null;
        $imei = null;
        $serial = null;
        $rma_number = null;
        $customer_name = null;
        $model_label = null;

        if ($row['repair_job_id']) {
            $device_info = db_row(
                "SELECT r.rma_number, c.name as customer_name, dm.name as model_name, db2.name as brand_name,
                        d.imei, d.serial_number
                 FROM repair_jobs j
                 JOIN rma_requests r ON r.id = j.rma_id
                 LEFT JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE j.id = ?", [(int)$row['repair_job_id']]
            );
        } elseif ($row['rma_id']) {
            $device_info = db_row(
                "SELECT r.rma_number, c.name as customer_name, dm.name as model_name, db2.name as brand_name,
                        d.imei, d.serial_number
                 FROM rma_requests r
                 LEFT JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN devices d ON d.id = r.device_id
                 LEFT JOIN device_models dm ON dm.id = d.model_id
                 LEFT JOIN device_brands db2 ON db2.id = dm.brand_id
                 WHERE r.id = ?", [(int)$row['rma_id']]
            );
        }

        if ($device_info) {
            $imei        = $device_info['imei'] ?? null;
            $serial      = $device_info['serial_number'] ?? null;
            $rma_number  = $device_info['rma_number'] ?? null;
            $customer_name = $device_info['customer_name'] ?? null;
            $model_label = trim(($device_info['brand_name'] ?? '') . ' ' . ($device_info['model_name'] ?? ''));
        }

        // Filename prefix: prefer IMEI, then SN, then RMA number
        $file_prefix = $rma_number ? str_replace('-', '', $rma_number) : 'IMG';

        // Existing photos count for numbering
        $existing_count = (int)db_val(
            'SELECT COUNT(*) FROM repair_evidence WHERE '
            . ($row['repair_job_id'] ? 'repair_job_id = ?' : 'rma_id = ?')
            . ' AND stage = ? AND deleted_at IS NULL',
            [$row['repair_job_id'] ?: $row['rma_id'], $row['stage']]
        );

        // Existing photos for gallery
        $existing_photos = db_rows(
            'SELECT id, filename, original_name, created_at FROM repair_evidence WHERE '
            . ($row['repair_job_id'] ? 'repair_job_id = ?' : 'rma_id = ?')
            . ' AND stage = ? AND deleted_at IS NULL ORDER BY created_at ASC',
            [$row['repair_job_id'] ?: $row['rma_id'], $row['stage']]
        );

        $stage_label = $row['stage'] === 'reception' ? 'Reception' : 'Repair';
        $max_photos = 9;
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Upload Photos</title>
<link href="/assets/css/fonts.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;overflow:hidden;}
body{font-family:'Montserrat',system-ui,-apple-system,sans-serif;background:#f0efe9;display:flex;flex-direction:column;height:100dvh;}

.top{padding:0 20px 12px;flex-shrink:0;}
.logo{margin-top:0;margin-bottom:10px;}
.logo img{height:40px;width:auto;margin-top:20px;}
.meta{font-size:14px;color:#5f5e5a;line-height:1.4;}
.meta strong{color:#1a1a18;font-weight:600;}

.gallery{flex:1;overflow-y:auto;padding:10px 16px;min-height:0;}
.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#b5b2a8;gap:8px;text-align:center;}
.empty-state svg{opacity:0.4;}
.empty-state p{font-size:13px;}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;}
.thumb{position:relative;aspect-ratio:1;border-radius:8px;overflow:hidden;background:#e5e3dc;}
.thumb img{width:100%;height:100%;object-fit:cover;}
.thumb .num{position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.5);color:#fff;font-size:10px;font-weight:600;padding:2px 5px;border-radius:4px;}
.thumb .check{position:absolute;bottom:4px;right:4px;background:#1D9E75;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;}
.thumb.uploading img{opacity:0.6;}

.bottom{background:#fff;border-top:0.5px solid #e5e3dc;padding:12px 20px;padding-bottom:max(12px,env(safe-area-inset-bottom));flex-shrink:0;display:flex;flex-direction:column;gap:8px;}
.btn-photo{display:flex;width:100%;background:#1D9E75;color:#fff;border:none;border-radius:12px;padding:14px;font-size:16px;font-weight:600;cursor:pointer;align-items:center;justify-content:center;gap:10px;}
.btn-photo:active{background:#0F6E56;transform:scale(0.98);}
.btn-photo:disabled{background:#b5b2a8;cursor:not-allowed;transform:none;}
.btn-done{display:flex;width:100%;background:#f0efe9;color:#5f5e5a;border:0.5px solid #d3d1c7;border-radius:12px;padding:14px;font-size:16px;font-weight:600;cursor:pointer;align-items:center;justify-content:center;gap:10px;}
.btn-done:active{background:#e5e3dc;}
.btn-done.sent{background:#1D9E75;color:#fff;border-color:#1D9E75;pointer-events:none;}
input[type=file]{display:none;}
</style>
</head>
<body>

<div class="top">
  <div class="logo">
    <img src="/assets/integra.svg" alt="Integra">
  </div>
  <div class="meta">
    <strong>Evidence Photos</strong><?php if ($rma_number): ?> &nbsp;·&nbsp; <strong><?= htmlspecialchars($rma_number) ?></strong><?php endif; ?>
  </div>
</div>

</div>
<div class="gallery" id="gallery">
  <?php if (empty($existing_photos)): ?>
  <div class="empty-state" id="empty">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
    <p>No photos yet<br><span style="font-size:12px;opacity:0.7;">Tap Take Photo to start</span></p>
  </div>
  <div class="grid" id="grid"></div>
  <?php else: ?>
  <div class="empty-state" id="empty" style="display:none;"></div>
  <div class="grid" id="grid">
    <?php foreach ($existing_photos as $i => $p): ?>
    <div class="thumb">
      <img src="/storage/<?= htmlspecialchars($p['filename']) ?>" loading="lazy">
      <div class="num"><?= str_pad($i+1,3,'0',STR_PAD_LEFT) ?></div>
      <div class="check"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="bottom">
  <label for="cam" class="btn-photo" id="btn-photo">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
    Take Photo
  </label>
  <input type="file" id="cam" accept="image/*" capture="environment" style="display:none;">
  <button class="btn-done" id="btn-done" onclick="markDone()">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    Upload Photos
  </button>
</div>

<script>
const cam    = document.getElementById('cam');
const grid   = document.getElementById('grid');
const empty  = document.getElementById('empty');
const btnPhoto = document.getElementById('btn-photo');
const btnDone  = document.getElementById('btn-done');
const prefix = <?= json_encode($file_prefix) ?>;
const MAX    = <?= $max_photos ?>;
const MAX_W  = <?= (int) setting('img_max_width', 1920) ?>;
const MAX_H  = <?= (int) setting('img_max_height', 1920) ?>;
const QUALITY = <?= max(1, min(100, (int) setting('img_quality', 85))) ?> / 100;
let n = <?= $existing_count ?>;

// Client-side resize before upload: saves bandwidth on cellular and
// speeds up the round trip. Server still re-validates and re-encodes,
// so this is purely an optimisation — on failure we fall back to the
// original file so upload never breaks.
async function resizeBeforeUpload(file) {
  if (!file || !file.type.startsWith('image/')) return file;
  if (typeof createImageBitmap !== 'function' || !HTMLCanvasElement.prototype.toBlob) {
    return file;
  }
  try {
    // imageOrientation:'from-image' applies EXIF rotation automatically
    // (iOS 14+, Chrome 81+, modern Firefox). Older browsers just ignore it.
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    let w = bitmap.width, h = bitmap.height;
    const ratio = Math.min(MAX_W / w, MAX_H / h, 1.0);
    if (ratio >= 1.0) { bitmap.close?.(); return file; }  // already small enough
    w = Math.round(w * ratio);
    h = Math.round(h * ratio);

    const canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    canvas.getContext('2d').drawImage(bitmap, 0, 0, w, h);
    bitmap.close?.();

    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', QUALITY));
    if (!blob) return file;
    return new File([blob], file.name.replace(/\.[^.]+$/, '.webp'), {type: 'image/webp'});
  } catch (e) {
    return file; // any error → upload original, server will resize
  }
}

function updatePhotoBtn() {
  if (n >= MAX) {
    btnPhoto.style.pointerEvents = "none"; btnPhoto.style.opacity = "0.5";
    btnPhoto.style.opacity = '0.5';
    status.className = 'status-bar warn';
    status.textContent = 'Maximum ' + MAX + ' photos reached';
  } else {
    btnPhoto.style.pointerEvents = ""; btnPhoto.style.opacity = "";
  }
}
updatePhotoBtn();

cam.addEventListener('change', async function() {
  if (n >= MAX) return;
  const raw = cam.files[0];
  if (!raw) return;
  n++;
  const num = String(n).padStart(3, '0');

  // Preview the picked photo immediately (from the original File — it
  // renders fast; resize happens in parallel before upload).
  empty.style.display = 'none';
  const thumb = document.createElement('div');
  thumb.className = 'thumb uploading';
  thumb.innerHTML = '<img src="' + URL.createObjectURL(raw) + '"><div class="num">' + num + '</div>';
  grid.appendChild(thumb);
  document.getElementById('gallery').scrollTop = 9999;

  // Resize on-device, then upload.
  const processed = await resizeBeforeUpload(raw);
  const outExt = (processed.type === 'image/webp') ? 'webp'
               : (processed.name.split('.').pop() || 'jpg').toLowerCase();
  const name = prefix + '_' + num + '.' + outExt;
  const renamed = new File([processed], name, {type: processed.type});

  const fd = new FormData();
  fd.append('file', renamed, name);
  fetch(location.href, {method: 'POST', body: fd})
    .then(function(r) { return r.json(); })
    .then(function(res) {
      thumb.classList.remove('uploading');
      if (res.success) {
        thumb.innerHTML += '<div class="check"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>';
      } else {
        n--; thumb.remove();
      }
      updatePhotoBtn();
    })
    .catch(function() {
      n--; thumb.remove();
      updatePhotoBtn();
    });
  cam.value = '';
});

function markDone() {
  if (btnDone.classList.contains('sent')) return;
  btnPhoto.style.pointerEvents = "none"; btnPhoto.style.opacity = "0.5";
  btnPhoto.style.opacity = '0.4';
  fetch(location.href + '/done', {method:'POST'})
    .then(function() {
      btnDone.classList.add('sent');
      btnDone.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Upload Photos';
      // Show full-screen success overlay
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:#1D9E75;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;color:#fff;gap:16px;padding:40px;text-align:center;';
      overlay.innerHTML = '<img src="/assets/integra.svg" style="height:40px;filter:brightness(0) invert(1);margin-bottom:16px;margin-top:-20px;">'
        + '<svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="20 6 9 17 4 12" stroke-width="2"/></svg>'
        + '<h2 style="font-size:22px;font-weight:700;margin:0;">Photos Uploaded</h2>'
        + '<p style="font-size:15px;opacity:0.85;margin:0;">You can close this page.</p>';
      document.body.appendChild(overlay);
      status.className = 'status-bar ok';
      status.textContent = 'Desktop will refresh automatically';
    })
    .catch(function() {});
}

// Block back navigation
history.replaceState(null, '', location.href);
window.addEventListener('popstate', function() {
  history.pushState(null, '', location.href);
});
</script>
</body>
</html><?php
    }


    public function token(): void {
        require_login();
        header('Content-Type: application/json');

        $repair_job_id = isset($_POST['repair_job_id']) && $_POST['repair_job_id'] !== '' ? (int)$_POST['repair_job_id'] : null;
        $rma_id        = isset($_POST['rma_id']) && $_POST['rma_id'] !== '' ? (int)$_POST['rma_id'] : null;
        $stage         = in_array($_POST['stage'] ?? '', ['reception','repair']) ? $_POST['stage'] : 'repair';

        if (!$repair_job_id && !$rma_id) {
            echo json_encode(['success' => false, 'error' => 'Missing reference']);
            return;
        }

        // Authorization: only let a user mint a mobile-upload token for an
        // RMA inside their location scope — otherwise an attacker could
        // generate a token for any RMA and then upload via /upload/{token}
        // (which intentionally has no login requirement).
        $target_rma_id = $rma_id;
        if (!$target_rma_id && $repair_job_id) {
            $target_rma_id = (int) db_val('SELECT rma_id FROM repair_jobs WHERE id = ? AND deleted_at IS NULL', [$repair_job_id]);
        }
        if (!$target_rma_id) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Reference not found']);
            return;
        }
        $rma_row = db_row('SELECT location_id FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [$target_rma_id]);
        if (!$rma_row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'RMA not found']);
            return;
        }
        $allowed_locs = allowed_location_ids();
        if ($allowed_locs !== null && !in_array((int)$rma_row['location_id'], array_map('intval', $allowed_locs), true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        $token      = bin2hex(random_bytes(20));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        db_insert('repair_evidence_tokens', [
            'token'         => $token,
            'repair_job_id' => $repair_job_id ?: null,
            'rma_id'        => $rma_id ?: null,
            'stage'         => $stage,
            'expires_at'    => $expires_at,
        ]);

        $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/upload/' . $token;

        echo json_encode(['success' => true, 'token' => $token, 'url' => $url]);
    }

    public function mobile_done(string $token): void {
        header('Content-Type: application/json');
        $row = db_row('SELECT id FROM repair_evidence_tokens WHERE token = ? AND expires_at > NOW()', [$token]);
        if (!$row) { echo json_encode(['success' => false]); return; }
        db_update('repair_evidence_tokens', ['completed_at' => date('Y-m-d H:i:s')], 'token = ?', [$token]);
        echo json_encode(['success' => true]);
    }

    public function token_status(): void {
        require_login();
        header('Content-Type: application/json');
        $token = $_GET['token'] ?? '';
        if (!$token) { echo json_encode(['visited' => false, 'completed' => false]); return; }
        $row = db_row('SELECT visited_at, completed_at FROM repair_evidence_tokens WHERE token = ?', [$token]);
        echo json_encode([
            'visited'   => !empty($row['visited_at']),
            'completed' => !empty($row['completed_at']),
        ]);
    }

    public function poll(): void {
        require_login();
        header('Content-Type: application/json');

        $repair_job_id = isset($_GET['repair_job_id']) ? (int)$_GET['repair_job_id'] : null;
        $rma_id        = isset($_GET['rma_id'])        ? (int)$_GET['rma_id']        : null;
        $stage         = in_array($_GET['stage'] ?? '', ['reception','repair']) ? $_GET['stage'] : 'repair';

        // Authorization: only return photos for an RMA inside the user's
        // location scope. Prevents IDOR — enumerating repair_job_id/rma_id to
        // read evidence photo paths for RMAs in other locations.
        $target_rma_id = $rma_id;
        if (!$target_rma_id && $repair_job_id) {
            $target_rma_id = (int) db_val('SELECT rma_id FROM repair_jobs WHERE id = ? AND deleted_at IS NULL', [$repair_job_id]);
        }
        $rma_row = $target_rma_id
            ? db_row('SELECT location_id FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [$target_rma_id])
            : null;
        $allowed_locs = allowed_location_ids();
        if (!$rma_row || ($allowed_locs !== null && !in_array((int)$rma_row['location_id'], array_map('intval', $allowed_locs), true))) {
            http_response_code(403);
            echo json_encode(['photos' => [], 'error' => 'Forbidden']);
            return;
        }

        $photos = db_rows(
            'SELECT id, filename, original_name FROM repair_evidence WHERE '
            . ($repair_job_id ? 'repair_job_id = ?' : 'rma_id = ?')
            . ' AND stage = ? AND deleted_at IS NULL ORDER BY created_at ASC',
            [$repair_job_id ?: $rma_id, $stage]
        );

        echo json_encode([
            'photos' => array_map(fn($p) => [
                'id'   => $p['id'],
                'url'  => '/storage/' . $p['filename'],
                'name' => $p['original_name'],
            ], $photos)
        ]);
    }

    public function upload(): void {
        require_login();
        header('Content-Type: application/json');

        $repair_job_id = isset($_POST['repair_job_id']) ? (int)$_POST['repair_job_id'] : null;
        $rma_id        = isset($_POST['rma_id'])        ? (int)$_POST['rma_id']        : null;
        $stage         = in_array($_POST['stage'] ?? '', ['reception','repair']) ? $_POST['stage'] : 'repair';

        if (!$repair_job_id && !$rma_id) {
            echo json_encode(['success' => false, 'error' => 'Missing reference']);
            return;
        }

        // Authorization: resolve the owning RMA and verify the user's
        // location scope before we accept the file. Prevents IDOR — a
        // logged-in user forging rma_id/repair_job_id to upload evidence
        // onto any record.
        $target_rma_id = $rma_id;
        if (!$target_rma_id && $repair_job_id) {
            $target_rma_id = (int) db_val('SELECT rma_id FROM repair_jobs WHERE id = ? AND deleted_at IS NULL', [$repair_job_id]);
        }
        if (!$target_rma_id) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Reference not found']);
            return;
        }
        $rma_row = db_row('SELECT location_id FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [$target_rma_id]);
        if (!$rma_row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'RMA not found']);
            return;
        }
        $allowed_locs = allowed_location_ids();
        if ($allowed_locs !== null && !in_array((int)$rma_row['location_id'], array_map('intval', $allowed_locs), true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Upload error']);
            return;
        }

        $file = $_FILES['file'];
        $mime = $this->detect_image_mime($file['tmp_name']);
        if ($mime === null) {
            echo json_encode(['success' => false, 'error' => 'Only image files allowed']);
            return;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Max file size is 10MB']);
            return;
        }

        $saved = $this->save_resized_evidence($file);
        if (!$saved) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
            return;
        }

        $id = db_insert('repair_evidence', [
            'repair_job_id' => $repair_job_id,
            'rma_id'        => $rma_id,
            'stage'         => $stage,
            'filename'      => $saved['stored_path'],
            'original_name' => $file['name'],
            'file_size'     => $saved['size'],
            'uploaded_by'   => current_user_id(),
        ]);

        echo json_encode([
            'success'  => true,
            'id'       => $id,
            'url'      => '/storage/' . $saved['stored_path'],
            'filename' => $file['name'],
            'size'     => $saved['size'],
        ]);
    }

    /**
     * Take $_FILES[...] entry, resize to settings' max dimensions, save to
     * storage/evidence/YYYY/MM/. Returns ['stored_path'=>..., 'size'=>...]
     * or null on total failure. Falls back to saving the original bytes if
     * GD resize isn't possible.
     */
    private function save_resized_evidence(array $file): ?array {
        $year = date('Y'); $month = date('m');
        $abs_dir = ROOT . "/storage/evidence/{$year}/{$month}";
        if (!is_dir($abs_dir)) @mkdir($abs_dir, 0755, true);

        $max_w   = (int) setting('img_max_width',  1920);
        $max_h   = (int) setting('img_max_height', 1920);
        $quality = (int) setting('img_quality',    85);

        // Try resize → WebP first. Fall back to raw copy if GD isn't usable.
        $basename = uniqid('ev_', true);
        $webp_path = $abs_dir . '/' . $basename . '.webp';

        if (resize_image_to($file['tmp_name'], $webp_path, $max_w, $max_h, $quality)) {
            return [
                'stored_path' => "evidence/{$year}/{$month}/{$basename}.webp",
                'size'        => (int) filesize($webp_path),
            ];
        }

        // Fallback: keep original file format.
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $dest = $abs_dir . '/' . $basename . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $dest)
            && !@copy($file['tmp_name'], $dest)) {
            return null;
        }
        return [
            'stored_path' => "evidence/{$year}/{$month}/{$basename}.{$ext}",
            'size'        => (int) filesize($dest),
        ];
    }

    public function delete(string $id): void {
        require_login();
        header('Content-Type: application/json');

        $photo = db_row('SELECT * FROM repair_evidence WHERE id = ? AND deleted_at IS NULL', [(int)$id]);
        if (!$photo) {
            echo json_encode(['success' => false, 'error' => 'Not found']);
            return;
        }

        // Authorization: verify user's location scope allows access to the
        // RMA this photo belongs to. Without this, any logged-in user can
        // delete any evidence photo by ID.
        $owner_rma_id = (int)($photo['rma_id'] ?? 0);
        if (!$owner_rma_id && !empty($photo['repair_job_id'])) {
            $owner_rma_id = (int) db_val('SELECT rma_id FROM repair_jobs WHERE id = ?', [(int)$photo['repair_job_id']]);
        }
        if ($owner_rma_id) {
            $rma_row = db_row('SELECT location_id FROM rma_requests WHERE id = ? AND deleted_at IS NULL', [$owner_rma_id]);
            $allowed_locs = allowed_location_ids();
            if (!$rma_row || ($allowed_locs !== null && !in_array((int)$rma_row['location_id'], array_map('intval', $allowed_locs), true))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                return;
            }
        }

        db_update('repair_evidence', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [(int)$id]);

        $path = ROOT . '/storage/' . $photo['filename'];
        if (file_exists($path)) unlink($path);

        echo json_encode(['success' => true]);
    }

    /**
     * Return the image MIME type of $path, or null if it's not a permitted
     * image format. Tries `finfo` first (most accurate), falls back to
     * `getimagesize` (always available), then to a magic-byte sniff.
     */
    private function detect_image_mime(string $path): ?string {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $m     = finfo_file($finfo, $path);
            finfo_close($finfo);
            return in_array($m, $allowed, true) ? $m : null;
        }

        if (function_exists('getimagesize')) {
            $info = @getimagesize($path);
            if ($info && !empty($info['mime']) && in_array($info['mime'], $allowed, true)) {
                return $info['mime'];
            }
        }

        // Last resort: sniff the first bytes.
        $h = @file_get_contents($path, false, null, 0, 12);
        if ($h === false) return null;
        if (str_starts_with($h, "\xFF\xD8\xFF"))                       return 'image/jpeg';
        if (str_starts_with($h, "\x89PNG\r\n\x1a\n"))                  return 'image/png';
        if (str_starts_with($h, 'GIF87a') || str_starts_with($h, 'GIF89a')) return 'image/gif';
        if (str_starts_with($h, 'RIFF') && substr($h, 8, 4) === 'WEBP')return 'image/webp';
        return null;
    }

    private function _saveFile(?array $file, ?int $repair_job_id, ?int $rma_id, string $stage): array {
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload error: ' . ($file['error'] ?? 'no file')];
        }

        $mime = $this->detect_image_mime($file['tmp_name']);
        if ($mime === null) {
            return ['success' => false, 'error' => 'Only image files allowed'];
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Max file size is 10MB'];
        }

        $saved = $this->save_resized_evidence($file);
        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }

        $uid = function_exists('current_user_id') ? current_user_id() : null;

        $id = db_insert('repair_evidence', [
            'repair_job_id' => $repair_job_id,
            'rma_id'        => $rma_id,
            'stage'         => $stage,
            'filename'      => $saved['stored_path'],
            'original_name' => $file['name'],
            'file_size'     => $saved['size'],
            'uploaded_by'   => $uid,
        ]);

        return [
            'success'       => true,
            'id'            => $id,
            'url'           => '/storage/' . $saved['stored_path'],
            'filename'      => $file['name'],
            'original_name' => $file['name'],
            'size'          => $saved['size'],
        ];
    }
}
