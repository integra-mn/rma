<?php
defined('RMS') or die('Direct access not permitted');

/**
 * CSRF protection.
 *
 * Strategy: one random token per session, embedded in forms via csrf_field()
 * and included by AJAX via the X-CSRF-Token header (see layout/header.php
 * for the global fetch() wrapper).
 *
 * The session cookie is already SameSite=Strict (see auth_start()), so most
 * CSRF risk is already blocked at the cookie layer. This helper adds a
 * defence-in-depth token check for traditional form POSTs and any AJAX call
 * that doesn't benefit from SameSite (older browsers, top-level navigation).
 */

function csrf_token(): string {
    auth_start();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="'
         . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_meta(): string {
    return '<meta name="csrf-token" content="'
         . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): bool {
    auth_start();
    $expected  = $_SESSION['_csrf'] ?? '';
    $submitted = $_POST['_csrf']
              ?? $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? '';
    return $expected !== ''
        && is_string($submitted)
        && strlen($submitted) === strlen($expected)
        && hash_equals($expected, $submitted);
}

/**
 * Enforce CSRF on all POST requests except routes in $whitelist.
 * Called from index.php before dispatch.
 *
 * $whitelist entries are regex patterns matched against the path.
 */
function csrf_require(array $whitelist = []): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

    $path = '/' . trim(strtok(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '?'), '/');
    foreach ($whitelist as $pattern) {
        if (preg_match('#^' . $pattern . '$#', $path)) return;
    }

    if (!csrf_verify()) {
        http_response_code(419); // "Page Expired" — Laravel convention
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'CSRF token mismatch. Please reload the page and try again.',
        ]);
        exit;
    }
}
