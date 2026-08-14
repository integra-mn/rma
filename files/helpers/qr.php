<?php
defined('RMS') or die('Direct access not permitted');

require_once dirname(__DIR__) . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Output\QRGdImagePNG;

/**
 * QR codes are generated locally — never by an external service.
 *
 * The payloads encoded here are customer tracking URLs that contain a secret
 * token, so sending them to a third-party image API would both leak the token
 * and hand customer-linked data to another processor (GDPR). Generating them
 * on the server also means receipts keep working with no internet access.
 */
/**
 * QR as a PNG data URI, optionally on a background other than white.
 *
 * The encoder always paints white behind the modules, which shows as a white
 * square when the code sits on a coloured panel — on the PDF receipt the
 * tracking block is grey, so the QR arrived with a white card behind it.
 *
 * Transparency is not an option here: the library draws into a truecolor image
 * and GD only writes transparency for palette PNGs, so asking for it produces
 * an opaque white square anyway (measured). Recolouring the light pixels is
 * both reliable and something we can check.
 *
 * $bg is [r, g, b]; null keeps the white the encoder produced. Only pixels
 * that are already near-white are touched, so the dark modules — the part a
 * scanner reads — are left exactly as they were.
 */
function generate_qr_base64(string $data, int $size = 200, ?array $bg = null): string {
    try {
        // Module size is derived from the requested pixel size; the encoder
        // picks the QR version itself based on the payload length.
        $options = new QROptions([
            'version'         => Version::AUTO,
            'eccLevel'        => EccLevel::M,
            'outputInterface' => QRGdImagePNG::class,
            'scale'           => max(2, (int) round($size / 33)),
            'outputBase64'    => true,
            'addQuietzone'    => true,
            'quietzoneSize'   => 4,
        ]);
        $out = (new QRCode($options))->render($data);
        if (!is_string($out) || !str_starts_with($out, 'data:image')) return '';
        return $bg ? qr_recolour_background($out, $bg) : $out;
    } catch (Throwable $e) {
        error_log('QR generation failed: ' . $e->getMessage());
        return '';
    }
}

/**
 * Repaint the light background of a QR data URI, leaving the modules alone.
 *
 * Anything at or above 200 in all three channels is background: the encoder
 * draws pure white there and pure black modules, so the threshold has a wide
 * margin and no anti-aliasing to catch. Returns the original untouched if
 * anything goes wrong — a QR on the wrong background still scans, one that
 * failed to render does not.
 */
function qr_recolour_background(string $data_uri, array $bg): string {
    if (count($bg) < 3) return $data_uri;
    $raw = base64_decode(substr($data_uri, strpos($data_uri, ',') + 1) ?: '', true);
    if ($raw === false) return $data_uri;

    $im = @imagecreatefromstring($raw);
    if (!$im) return $data_uri;

    $target = imagecolorallocate($im, (int)$bg[0], (int)$bg[1], (int)$bg[2]);
    if ($target === false) { imagedestroy($im); return $data_uri; }

    $w = imagesx($im); $h = imagesy($im);
    for ($x = 0; $x < $w; $x++) {
        for ($y = 0; $y < $h; $y++) {
            $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
            if ($c['red'] >= 200 && $c['green'] >= 200 && $c['blue'] >= 200) {
                imagesetpixel($im, $x, $y, $target);
            }
        }
    }

    ob_start(); imagepng($im); $png = ob_get_clean();
    imagedestroy($im);
    return $png ? 'data:image/png;base64,' . base64_encode($png) : $data_uri;
}

function generate_qr_file(string $data, string $path, int $size = 200): bool {
    $base64 = generate_qr_base64($data, $size);
    if (!$base64) return false;
    $img = base64_decode(substr($base64, strpos($base64, ',') + 1));
    return file_put_contents($path, $img) !== false;
}
