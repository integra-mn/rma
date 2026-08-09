<?php
// Router for `php -S` local development.
// Emulates the production .htaccess: static files served directly,
// everything else dispatched through files/index.php.

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = __DIR__ . '/files' . $uri;

// Block access to source dirs and dotfiles (mirror .htaccess).
if (preg_match('#^/(config|helpers|controllers|models|views|lang|adapters|cron|migrations)/#', $uri)
    || preg_match('#(^|/)\.#', $uri)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Serve existing static files directly (the built-in server's own
// "return false" lookup uses the CWD/document-root, not files/, so
// do it ourselves).
if ($uri !== '/' && is_file($file)) {
    $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'mjs'  => 'application/javascript',
        'json' => 'application/json',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'htm'  => 'text/html; charset=utf-8',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

// Prevent the browser from caching dynamic HTML during dev (the built-in
// server ignores .htaccess, so the production no-cache rules don't apply).
header('Cache-Control: no-store, no-cache, must-revalidate');

require __DIR__ . '/files/index.php';
