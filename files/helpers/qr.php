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
function generate_qr_base64(string $data, int $size = 200): string {
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
        return is_string($out) && str_starts_with($out, 'data:image') ? $out : '';
    } catch (Throwable $e) {
        error_log('QR generation failed: ' . $e->getMessage());
        return '';
    }
}

function generate_qr_file(string $data, string $path, int $size = 200): bool {
    $base64 = generate_qr_base64($data, $size);
    if (!$base64) return false;
    $img = base64_decode(substr($base64, strpos($base64, ',') + 1));
    return file_put_contents($path, $img) !== false;
}
