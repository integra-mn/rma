<?php
define('RMS', true);
define('ROOT', __DIR__);

// Montenegro, everywhere. PHP does not follow the system clock's zone — it
// reads date.timezone from php.ini and falls back to UTC when that is unset,
// which is how every timestamp came to be recorded two hours behind local time.
// Setting it here rather than in php.ini keeps it with the code, so a rebuilt
// container cannot quietly go back to UTC. Postgres follows the container's own
// zone, which is set to the same.
date_default_timezone_set('Europe/Podgorica');

require_once ROOT . '/config/db.php';
require_once ROOT . '/config/settings.php';
require_once ROOT . '/helpers/auth.php';
require_once ROOT . '/helpers/permissions.php';
require_once ROOT . '/helpers/lang.php';
require_once ROOT . '/helpers/date.php';
require_once ROOT . '/helpers/portal.php';
require_once ROOT . '/helpers/audit.php';
require_once ROOT . '/helpers/image.php';
require_once ROOT . '/helpers/sku.php';
require_once ROOT . '/helpers/phone.php';
require_once ROOT . '/helpers/warranty.php';
require_once ROOT . '/helpers/device_history.php';
require_once ROOT . '/helpers/insurance.php';
require_once ROOT . '/helpers/qr.php';
require_once ROOT . '/helpers/totp.php';   // needs qr.php for the enrolment QR
require_once ROOT . '/helpers/shipping.php';
require_once ROOT . '/helpers/email.php';
require_once ROOT . '/helpers/sms.php';
require_once ROOT . '/helpers/whatsapp.php';
require_once ROOT . '/helpers/pdf.php';
require_once ROOT . '/helpers/csrf.php';
require_once ROOT . '/helpers/vendor.php';

auth_start();

// CSRF: require a valid token on every POST, except routes that use
// their own token-based auth (mobile evidence upload) or are intended
// to be called from other origins with their own auth (API v1).
csrf_require([
    '/upload/[a-zA-Z0-9]+(?:/done)?',
    '/sign/[a-zA-Z0-9]+/save',      // mobile signing — token-based auth, no session
    '/api/v1/.+',
]);

$uri    = strtok(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '?');
$uri    = '/' . trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

function views_path(string $file): string {
    return ROOT . '/views/' . ltrim($file, '/');
}

$routes = [
    'GET' => [
        '/'                            => 'dashboard/index',
        '/auth/login'                  => 'auth/login',
        '/auth/logout'                 => 'auth/logout',
        '/auth/2fa'                    => 'auth/twofa',
        '/profile'                     => 'profile/index',
        '/profile/totp-cancel'         => 'profile/totp_cancel',
        '/rma'                         => 'rma/index',
        '/rma/create'                  => 'rma/create',
        '/rma/device-search'           => 'rma/device_search',
        '/device/([A-Za-z0-9-]+)'      => 'rma/device_history',
        '/rma/customer-search'         => 'rma/customer_search',
        '/rma/([0-9]+)'                => 'rma/view',
        '/rma/([0-9]+)/edit'           => 'rma/edit',
        '/rma/([0-9]+)/receipt'        => 'rma/receipt',
        '/rma/([0-9]+)/shipment/([0-9]+)/label' => 'rma/shipment_label',
        '/repair'                      => 'repair/index',
        '/repair/([0-9]+)'             => 'repair/view',
        '/parts'                       => 'parts/index',
        '/parts/receipts'                       => 'goodsReceipt/index',
        '/parts/receipts/([0-9]+)'              => 'goodsReceipt/view',
        '/parts/receipts/([0-9]+)/template'     => 'goodsReceipt/template',
        '/invoices'                    => 'invoices/index',
        '/invoices/([0-9]+)'           => 'invoices/view',
        '/customers'                   => 'customers/index',
        '/customers/([0-9]+)/edit'     => 'customers/edit',
        '/customers/check-duplicate'   => 'customers/check_duplicate',
        '/suppliers'                   => 'suppliers/index',
        '/partners'                    => 'partners/index',
        '/partners/([0-9]+)/edit'      => 'partners/edit',
        '/reports'                     => 'reports/index',
        '/reports/export'              => 'reports/export',
        '/shipments'                   => 'shipments/index',
        '/devices'                     => 'devices/index',
        '/administration'              => 'admin/index',
        '/settings'                    => 'admin/settings',
        '/admin'                       => 'admin/index',
        '/admin/theme'                 => 'admin/save_theme',
        '/admin/lang'                  => 'admin/save_lang',
        '/admin/device-catalog'        => 'admin/device_catalog',
        '/admin/users'                 => 'admin/users',
        '/admin/locations'             => 'admin/locations',
        '/admin/settings'              => 'settings/index',
        '/admin/permissions'           => 'admin/permissions',
        '/track/([a-f0-9]+)'           => 'track/view',
        '/upload/([a-zA-Z0-9]+)'       => 'evidence/mobile',
        '/upload/([a-zA-Z0-9]+)/done'   => 'evidence/mobile_done',
        '/evidence/poll'                  => 'evidence/poll',
        '/evidence/token-status'          => 'evidence/token_status',
        '/sign/([a-zA-Z0-9]+)'            => 'signature/mobile',
        '/signature/status'               => 'signature/status',
        '/station/([a-zA-Z0-9]+)'         => 'signature/station',
        '/station/([a-zA-Z0-9]+)/next'    => 'signature/station_poll',
        '/portal/login'                   => 'portal/login',
        '/portal'                         => 'portal/dashboard',
        '/portal/rma'                     => 'portal/rma_list',
        '/portal/rma/new'                 => 'portal/rma_new',
        '/portal/rma/([0-9]+)'            => 'portal/rma_view',
        '/portal/rma/([0-9]+)/receipt'    => 'portal/rma_receipt',
        '/portal/logout'                  => 'portal/logout',
    ],
    'POST' => [
        '/auth/login'                          => 'auth/login_post',
        '/portal/login'                        => 'portal/login_post',
        '/portal/rma/store'                    => 'portal/rma_store',
        '/portal/rma/([0-9]+)/comment'         => 'portal/rma_comment',
        '/portal/rma/([0-9]+)/dispatch'        => 'portal/rma_dispatch',
        '/portal/rma/([0-9]+)/received'        => 'portal/rma_received',
        '/auth/2fa'                            => 'auth/twofa_post',
        '/profile/save'                        => 'profile/save',
        '/profile/totp-start'                  => 'profile/totp_start',
        '/profile/totp-confirm'                => 'profile/totp_confirm',
        '/profile/totp-disable'                => 'profile/totp_disable',
        '/rma/store'                           => 'rma/store',
        '/rma/([0-9]+)/update'                 => 'rma/update',
        '/rma/([0-9]+)/comment'                => 'rma/comment',
        '/rma/([0-9]+)/upload'                 => 'rma/upload',
        '/rma/([0-9]+)/send-receipt'           => 'rma/send_receipt',
        '/rma/([0-9]+)/warranty-check'         => 'rma/warranty_check',
        '/rma/([0-9]+)/shipment/store'         => 'rma/shipment_store',
        '/rma/([0-9]+)/shipment/update'        => 'rma/shipment_update',
        '/rma/([0-9]+)/shipment/delete'        => 'rma/shipment_delete',
        '/repair/rma/([0-9]+)/create'          => 'repair/create',
        '/repair/([0-9]+)/update'              => 'repair/update',
        '/repair/([0-9]+)/time'                => 'repair/log_time',
        '/repair/([0-9]+)/part'                => 'repair/log_part',
        '/repair/([0-9]+)/submit-to-gsx'       => 'repair/submit_to_gsx',
        '/parts/receipts/store'                => 'goodsReceipt/store',
        '/parts/receipts/([0-9]+)/import'      => 'goodsReceipt/import',
        '/parts/receipts/([0-9]+)/items'       => 'goodsReceipt/update_items',
        '/parts/receipts/([0-9]+)/confirm'     => 'goodsReceipt/confirm',
        '/parts/store'                         => 'parts/store',
        '/parts/([0-9]+)/update'               => 'parts/update',
        '/parts/([0-9]+)/delete'               => 'parts/delete',
        '/parts/supplier/store'                => 'parts/supplier_store',
        '/parts/supplier/update'               => 'parts/supplier_update',
        '/parts/stock/update'                  => 'parts/stock_update',
        '/inventory/start'                     => 'inventoryCount/start',
        '/inventory/([0-9]+)/save'             => 'inventoryCount/save',
        '/inventory/([0-9]+)/confirm'          => 'inventoryCount/confirm',
        '/inventory/([0-9]+)/cancel'           => 'inventoryCount/cancel',
        '/invoices/store'                      => 'invoices/store',
        '/customers/store'                     => 'customers/store',
        '/customers/([0-9]+)/update'           => 'customers/update',
        '/customers/([0-9]+)/delete'           => 'customers/delete',
        '/devices/category/store'              => 'devices/category_store',
        '/devices/category/update'             => 'devices/category_update',
        '/devices/category/delete'             => 'devices/category_delete',
        '/devices/brand/store'                 => 'devices/brand_store',
        '/devices/brand/update'                => 'devices/brand_update',
        '/devices/brand/delete'                => 'devices/brand_delete',
        '/devices/model/store'                 => 'devices/model_store',
        '/devices/model/update'                => 'devices/model_update',
        '/devices/model/delete'                => 'devices/model_delete',
        '/devices/part-group/store'            => 'devices/part_group_store',
        '/devices/part-group/update'           => 'devices/part_group_update',
        '/devices/part-group/delete'           => 'devices/part_group_delete',
        '/suppliers/store'                     => 'suppliers/store',
        '/suppliers/update'                    => 'suppliers/update',
        '/partners/store'                      => 'partners/store',
        '/partners/([0-9]+)/update'            => 'partners/update',
        '/partners/([0-9]+)/branches/store'     => 'partners/branch_store',
        '/partners/([0-9]+)/branches/update'    => 'partners/branch_update',
        '/partners/([0-9]+)/branches/delete'    => 'partners/branch_delete',
        '/partners/([0-9]+)/delete'            => 'partners/delete',
        '/admin/location/store'                => 'admin/location_store',
        '/admin/location/toggle'               => 'admin/location_toggle',
        '/admin/location/update'               => 'admin/location_update',
        '/admin/location/delete'               => 'admin/location_delete',
        '/admin/courier/store'                 => 'admin/courier_store',
        '/admin/courier/toggle'                => 'admin/courier_toggle',
        '/admin/courier/update'                => 'admin/courier_update',
        '/admin/courier/delete'                => 'admin/courier_delete',
        '/admin/user/store'                    => 'admin/user_store',
        '/admin/user/update'                   => 'admin/user_update',
        '/admin/user/toggle'                   => 'admin/user_toggle',
        '/admin/user/delete'                   => 'admin/user_delete',
        '/admin/status/store'                  => 'admin/status_store',
        '/admin/status/update'                 => 'admin/status_update',
        '/admin/permissions/save'              => 'admin/permissions_save',
        '/admin/users/totp-reset'              => 'admin/user_totp_reset',
        '/admin/settings/save'                 => 'settings/save',
        '/admin/settings/smtp-test'            => 'settings/smtp_test',
        '/admin/settings/sms-test'             => 'settings/sms_test',
        '/admin/settings/gsx-test'             => 'settings/gsx_test',
        '/evidence/upload'                     => 'evidence/upload',
        '/upload/([a-zA-Z0-9]+)'               => 'evidence/mobile',
        '/upload/([a-zA-Z0-9]+)/done'           => 'evidence/mobile_done',
        '/evidence/([0-9]+)/delete'            => 'evidence/delete',
        '/evidence/token'                      => 'evidence/token',
        '/evidence/token'                      => 'evidence/token',
        '/evidence/poll'                       => 'evidence/poll',
        '/signature/token'                     => 'signature/token',
        '/sign/([a-zA-Z0-9]+)/save'            => 'signature/save',
        '/track/([a-f0-9]+)'                   => 'track/verify',
        '/api/v1/(.+)'                         => 'api/dispatch',
    ],
];

function dispatch(string $uri, string $method, array $routes): void {
    $method_routes = $routes[$method] ?? [];
    foreach ($method_routes as $pattern => $handler) {
        $regex = '#^' . $pattern . '$#';
        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches);
            [$controller, $action] = explode('/', $handler);
            $file = ROOT . '/controllers/' . $controller . 'Controller.php';
            if (!file_exists($file)) {
                http_response_code(404);
                include views_path('errors/404.php');
                return;
            }
            require_once $file;
            $class = ucfirst($controller) . 'Controller';
            // A route pointing at a method that doesn't exist used to be a
            // fatal error rather than a 404 — only the controller file was
            // checked. /rma/{id}/edit and /rma/{id}/upload are both in that
            // state today, and removing a method would silently create more.
            if (!method_exists($class, $action)) {
                error_log("Route {$uri} -> {$class}::{$action}() which does not exist");
                http_response_code(404);
                include views_path('errors/404.php');
                return;
            }
            (new $class())->$action(...$matches);
            return;
        }
    }
    http_response_code(404);
    include views_path('errors/404.php');
}

dispatch($uri, $method, $routes);
