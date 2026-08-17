<?php
defined('RMS') or die('Direct access not permitted');

/**
 * Warranty state — three states held in two columns.
 *
 *   pod garancijom   (yes)      the device is inside its warranty period
 *   van garancije    (out)      the period has expired
 *   garancija odbijena (refused) the period is still valid, but something about
 *                               the device voids the cover
 *
 * `is_warranty` carries yes/no as it always has. The two "no" states are told
 * apart by `warranty_refusal`: the lone marker `out_of_warranty` means the
 * period expired, anything else is a refusal and lists its reasons. Storing it
 * this way is what the app already did, so old records keep their meaning.
 */

/** Work out which of the three states a row is in. */
function warranty_mode($is_warranty, $refusal): string {
    if ((int)$is_warranty === 1) return 'yes';

    $list = is_array($refusal)
        ? $refusal
        : (($refusal ?? '') !== '' ? json_decode((string)$refusal, true) : []);
    if (!is_array($list)) $list = [];

    // A "no" with nothing recorded is treated as a refusal, which is how the
    // badges have always read it. Only the lone marker means expired.
    return $list === ['out_of_warranty'] ? 'out' : 'refused';
}

/**
 * Which states may follow which, once a case is under way (Rajo, 2026-08-15).
 *
 *   yes     -> refused   a device inside its period can be found to have voided
 *                        its cover. It cannot become "expired": the period is a
 *                        fact established when the device was taken in.
 *   out     -> nothing   an expired period cannot become unexpired.
 *   refused -> yes       kept open so a refusal made in error can be undone.
 *                        Rajo did not specify this one; if it should be closed,
 *                        or admin-only, remove 'yes' from the row below.
 *
 * Every state may stay where it is — saving the card without changing the state
 * is not a transition.
 */
const WARRANTY_TRANSITIONS = [
    'yes'     => ['yes', 'refused'],
    'out'     => ['out'],
    'refused' => ['refused', 'yes'],
];

/** May this case move from one state to another? */
function warranty_can_change(string $from, string $to): bool {
    return in_array($to, WARRANTY_TRANSITIONS[$from] ?? [], true);
}

/** Is this state the end of the line — nothing it can become? */
function warranty_is_locked(string $mode): bool {
    return (WARRANTY_TRANSITIONS[$mode] ?? []) === [$mode];
}
