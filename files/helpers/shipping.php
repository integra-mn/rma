<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Logistics helpers (Phase 1 — manual tracking, no courier API).
 */

// Build a clickable tracking URL from a courier's template + a tracking number.
// The template holds a {tracking} placeholder, e.g.
//   https://www.postacg.me/track?id={tracking}
// Returns null when there is no template or no tracking number.
function courier_tracking_url(?string $template, ?string $tracking): ?string {
    $tracking = trim((string) $tracking);
    $template = trim((string) $template);
    if ($template === '' || $tracking === '') return null;
    return str_replace('{tracking}', rawurlencode($tracking), $template);
}

// The shipment statuses a user can set by hand, in order.
const SHIPMENT_STATUSES = ['pending', 'shipped', 'in_transit', 'delivered', 'returned', 'cancelled'];

// Directions: inbound = device coming to the shop, outbound = going back out.
const SHIPMENT_DIRECTIONS = ['inbound', 'outbound'];

// Translated label for a shipment status code.
function shipment_status_label(string $code): string {
    return __('ship.status_' . $code);
}

// A soft colour for a status badge.
function shipment_status_color(string $code): string {
    return match ($code) {
        'delivered'  => '#1D9E75',
        'in_transit',
        'shipped'    => '#e8860a',
        'returned',
        'cancelled'  => '#a32d2d',
        default      => '#888780', // pending
    };
}
