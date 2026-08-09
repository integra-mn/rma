<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Display-only date formatters. All DB writes stay on Y-m-d H:i:s.
 * Change the format strings here to retune the whole app.
 */

// Return dd-mm-yyyy, or $fallback if empty / unparseable.
function format_date($value, string $fallback = '—'): string {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return $fallback;
    }
    $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
    return $ts ? date('d-m-Y', $ts) : $fallback;
}

// Return dd-mm-yyyy · HH:ii, or $fallback if empty / unparseable.
function format_datetime($value, string $fallback = '—'): string {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return $fallback;
    }
    $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
    return $ts ? date('d-m-Y · H:i', $ts) : $fallback;
}
