<?php
defined('RMS') or die('Direct access not permitted');

function process_uploaded_image(array $file, string $dest_dir, string $file_type): array|false {
    $max_mb = (int) setting('img_max_upload_mb', 20);
    if ($file['size'] > $max_mb * 1024 * 1024) return false;

    $info = @getimagesize($file['tmp_name']);
    if (!$info) return false;

    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($info['mime'], $allowed_mime, true)) return false;

    $original_size = $file['size'];
    $max_w  = (int) setting('img_max_width',  1920);
    $max_h  = (int) setting('img_max_height', 1920);
    $quality = (int) setting('img_quality',   85);
    $thumb_size = (int) setting('img_thumb_size', 400);

    $src = match($info['mime']) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        'image/gif'  => imagecreatefromgif($file['tmp_name']),
        default      => false,
    };
    if (!$src) return false;

    [$orig_w, $orig_h] = [$info[0], $info[1]];
    $ratio  = min($max_w / $orig_w, $max_h / $orig_h, 1.0);
    $new_w  = (int) round($orig_w * $ratio);
    $new_h  = (int) round($orig_h * $ratio);

    $dst = imagecreatetruecolor($new_w, $new_h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    imagedestroy($src);

    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);

    $filename  = uniqid('img_', true) . '.webp';
    $dest_path = rtrim($dest_dir, '/') . '/' . $filename;
    imagewebp($dst, $dest_path, $quality);
    imagedestroy($dst);

    // Generate thumbnail
    $thumb_path = null;
    $thumb_dir  = str_replace('/rma/', '/thumbnails/rma/', $dest_dir);
    $thumb_path = generate_thumbnail($dest_path, $thumb_dir, $thumb_size);

    return [
        'file_path'      => relative_upload_path($dest_path),
        'thumbnail_path' => $thumb_path ? relative_upload_path($thumb_path) : null,
        'original_name'  => $file['name'],
        'original_size'  => $original_size,
        'processed_size' => filesize($dest_path),
        'mime_type'      => 'image/webp',
        'width'          => $new_w,
        'height'         => $new_h,
        'is_processed'   => 1,
        'processed_at'   => date('Y-m-d H:i:s'),
    ];
}

/**
 * Generic: downscale an image to fit max_w × max_h (preserves aspect
 * ratio, never upscales), save as WebP at the given quality.
 *
 * $src_path is typically $_FILES['file']['tmp_name'].
 * $dest_path should end in `.webp` — directory is created if missing.
 *
 * Returns true on success; false if GD is unavailable, the source isn't
 * a supported image, or the write fails. Caller should fall back to
 * copying the original on false.
 */
function resize_image_to(string $src_path, string $dest_path, int $max_w, int $max_h, int $quality = 85): bool {
    if (!function_exists('imagecreatefromjpeg')) return false;   // GD not installed

    $info = @getimagesize($src_path);
    if (!$info) return false;

    $src = match ($info['mime']) {
        'image/jpeg' => @imagecreatefromjpeg($src_path),
        'image/png'  => @imagecreatefrompng($src_path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src_path) : false,
        'image/gif'  => @imagecreatefromgif($src_path),
        default      => false,
    };
    if (!$src) return false;

    [$w, $h] = [$info[0], $info[1]];
    $ratio = min($max_w / $w, $max_h / $h, 1.0);
    $nw = (int) round($w * $ratio);
    $nh = (int) round($h * $ratio);

    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);

    // Respect JPEG EXIF orientation so portrait photos aren't rotated wrong.
    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src_path);
        $orient = $exif['Orientation'] ?? 1;
        if ($orient === 3) { $src = imagerotate($src, 180, 0); }
        elseif ($orient === 6) { $src = imagerotate($src, -90, 0); [$w,$h] = [$h,$w]; [$nw,$nh] = [$nh,$nw]; $dst = imagecreatetruecolor($nw,$nh); imagealphablending($dst,false); imagesavealpha($dst,true); }
        elseif ($orient === 8) { $src = imagerotate($src,  90, 0); [$w,$h] = [$h,$w]; [$nw,$nh] = [$nh,$nw]; $dst = imagecreatetruecolor($nw,$nh); imagealphablending($dst,false); imagesavealpha($dst,true); }
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $dir = dirname($dest_path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $ok = function_exists('imagewebp')
        ? @imagewebp($dst, $dest_path, max(1, min(100, $quality)))
        : @imagejpeg($dst, $dest_path, max(1, min(100, $quality)));
    imagedestroy($dst);

    return (bool) $ok;
}

function generate_thumbnail(string $src_path, string $thumb_dir, int $size): ?string {
    $info = @getimagesize($src_path);
    if (!$info) return null;

    $src = imagecreatefromwebp($src_path);
    if (!$src) return null;

    [$w, $h] = [$info[0], $info[1]];
    $crop  = min($w, $h);
    $off_x = (int)(($w - $crop) / 2);
    $off_y = (int)(($h - $crop) / 2);

    $thumb = imagecreatetruecolor($size, $size);
    imagecopyresampled($thumb, $src, 0, 0, $off_x, $off_y, $size, $size, $crop, $crop);
    imagedestroy($src);

    if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);

    $path = rtrim($thumb_dir, '/') . '/' . basename($src_path);
    imagewebp($thumb, $path, 85);
    imagedestroy($thumb);

    return $path;
}

function upload_dir(string $rma_id, string $type): string {
    $root = dirname(dirname(__FILE__)) . '/uploads';
    return "{$root}/rma/{$rma_id}/{$type}";
}

function relative_upload_path(string $abs_path): string {
    $root = dirname(dirname(__FILE__));
    return str_replace($root, '', $abs_path);
}

// JavaScript for client-side pre-processing (output in upload forms)
function image_upload_js(): string {
    $max_w   = (int) setting('img_max_width',  1920);
    $max_h   = (int) setting('img_max_height', 1920);
    $quality = (int) setting('img_quality', 85) / 100;
    $max_mb  = (int) setting('img_max_upload_mb', 20);

    return <<<JS
<script>
(function() {
  const MAX_W = {$max_w}, MAX_H = {$max_h}, Q = {$quality}, MAX_MB = {$max_mb};

  async function processImage(file) {
    if (!file.type.startsWith('image/')) return file;
    return new Promise(resolve => {
      const img = new Image(), url = URL.createObjectURL(file);
      img.onload = () => {
        URL.revokeObjectURL(url);
        let w = img.naturalWidth, h = img.naturalHeight;
        const ratio = Math.min(MAX_W / w, MAX_H / h, 1.0);
        w = Math.round(w * ratio); h = Math.round(h * ratio);
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
        canvas.toBlob(blob => resolve(new File([blob], file.name.replace(/\.[^.]+$/, '.webp'), {type:'image/webp'})), 'image/webp', Q);
      };
      img.onerror = () => resolve(file);
      img.src = url;
    });
  }

  document.addEventListener('change', async (e) => {
    const input = e.target;
    if (input.type !== 'file' || !input.dataset.imageUpload) return;

    const status = document.getElementById(input.dataset.status);
    const files  = Array.from(input.files);
    const dt     = new DataTransfer();

    for (const file of files) {
      if (file.size > MAX_MB * 1024 * 1024) {
        if (status) status.textContent = file.name + ' exceeds ' + MAX_MB + 'MB limit';
        continue;
      }
      const processed = await processImage(file);
      dt.items.add(processed);
      if (status) {
        const before = (file.size / 1024).toFixed(0);
        const after  = (processed.size / 1024).toFixed(0);
        status.textContent = before + ' KB → ' + after + ' KB';
      }
    }
    input.files = dt.files;
  });
})();
</script>
JS;
}
