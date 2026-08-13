<?php
defined('RMS') or die('Direct access not permitted');

function normalize_phone(string $phone, string $default_country_code = '382'): string {
    $clean = preg_replace('/[^\d+]/', '', $phone);
    if (empty($clean)) return '';
    $digits = ltrim($clean, '+');
    if (substr($clean, 0, 1) === '+') return '+' . $digits;
    if (substr($digits, 0, 2) === '00') return '+' . substr($digits, 2);
    if ($default_country_code === '382' && substr($digits, 0, 1) === '0' && strlen($digits) >= 8)
        return '+382' . substr($digits, 1);
    if (substr($digits, 0, 1) === '0') return '+' . $default_country_code . substr($digits, 1);
    if (strlen($digits) > 9) return '+' . $digits;
    return '+' . $default_country_code . $digits;
}

function format_phone(string $phone): string {
    if (empty($phone)) return '';
    $n = normalize_phone($phone);
    if (preg_match('/^\+382(\d{2})(\d{3})(\d{3})$/', $n, $m))
        return "+382 {$m[1]} {$m[2]} {$m[3]}";
    if (preg_match('/^\+(\d{1,3})(\d{3})(\d{3})(\d{1,5})$/', $n, $m))
        return "+{$m[1]} {$m[2]} {$m[3]} {$m[4]}";
    return $n;
}

function phone_last_digits(string $phone, int $n = 8): string {
    return substr(preg_replace('/\D/', '', $phone), -$n);
}

/**
 * Is this a stand-in address rather than a real one?
 *
 * When a customer will not or cannot give an email, reception records
 * refused@integra.rma. It is a marker, not an address: the same one sits on
 * dozens of unrelated people, so matching on it would file strangers under one
 * customer — which is how a new customer on 50152 ended up as the customer
 * from 50151.
 *
 * .rma is not a real top-level domain, deliberately. The older refused@apple.com
 * and refused@tcl.com pointed at real companies, so a slip anywhere in the send
 * path posted a customer's repair details to Apple or TCL.
 *
 * Matched on the local part, so every form of it is covered with no list to
 * maintain.
 */
function is_placeholder_email(?string $email): bool {
    $email = strtolower(trim((string) $email));
    if ($email === '') return true;
    return str_starts_with($email, 'refused@');
}

/**
 * Every customer resembling these details, for the warning shown while
 * reception types. The singular find_customer_match() below decides who the
 * RMA is filed under; this one decides nothing — it feeds a banner a person
 * reads and acts on.
 *
 * customersController::check_duplicate() has called this since the form was
 * written, but it was never defined and no route reached it, so the fetch
 * 404ed and the warning never appeared. That is why a silent auto-link was the
 * first anyone knew of a duplicate.
 */
function find_customer_matches(string $name, string $phone, string $email): array {
    $out = $seen = [];
    $push = function (?array $c, string $type) use (&$out, &$seen): void {
        if (!$c || isset($seen[$c['id']])) return;
        $seen[$c['id']] = true;
        $out[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'phone' => $c['phone'],
                  'email' => $c['email'], 'match_type' => $type];
    };

    $email = strtolower(trim($email));
    if ($email !== '' && !is_placeholder_email($email)) {
        $push(db_row('SELECT * FROM customers WHERE LOWER(email) = ? AND deleted_at IS NULL',
                     [$email]), 'email');
    }

    if (trim($phone) !== '') {
        $last8 = phone_last_digits(normalize_phone($phone));
        if (strlen($last8) >= 6) {
            foreach (db_rows('SELECT * FROM customers WHERE phone IS NOT NULL AND deleted_at IS NULL') as $c) {
                if (phone_last_digits($c['phone']) === $last8) $push($c, 'phone');
            }
        }
    }

    // Names are worth showing even though they never decide anything: two
    // people share a name often enough that the operator has to be the judge.
    if (($name = trim($name)) !== '') {
        foreach (db_rows('SELECT * FROM customers WHERE name LIKE ? AND deleted_at IS NULL LIMIT 5',
                         ['%' . $name . '%']) as $c) {
            $push($c, 'name');
        }
    }

    return array_slice($out, 0, 5);
}

function find_customer_match(string $name, string $phone, string $email): array {
    $email = strtolower(trim($email));
    // A refused@ address says "no email given". Treating it as one would match
    // every other customer who also declined.
    if ($email && !is_placeholder_email($email)) {
        $c = db_row('SELECT * FROM customers WHERE LOWER(email) = ? AND deleted_at IS NULL', [$email]);
        if ($c) return ['customer' => $c, 'match_type' => 'email', 'confidence' => 'exact'];
    }
    if ($phone) {
        $last8 = phone_last_digits(normalize_phone($phone));
        if (strlen($last8) >= 6) {
            foreach (db_rows('SELECT * FROM customers WHERE phone IS NOT NULL AND deleted_at IS NULL') as $c) {
                if (phone_last_digits($c['phone']) === $last8)
                    return ['customer' => $c, 'match_type' => 'phone', 'confidence' => 'exact'];
            }
        }
    }
    // A name is not an identifier. Two people called Marko Petrovic are two
    // people, and treating an exact name as an exact match silently filed the
    // second one under the first — one customer row, two strangers, and any
    // later correction to the name changing it for both.
    //
    // Only a phone number or an email address above can be 'exact', because
    // only those pick out one person. A name match is reported so the operator
    // can be asked, never acted on by itself.
    if ($name = trim($name)) {
        $c = db_row('SELECT * FROM customers WHERE LOWER(name) = LOWER(?) AND deleted_at IS NULL', [$name]);
        if ($c) return ['customer' => $c, 'match_type' => 'name', 'confidence' => 'partial'];
        $c = db_row('SELECT * FROM customers WHERE name LIKE ? AND deleted_at IS NULL LIMIT 1', ['%'.$name.'%']);
        if ($c) return ['customer' => $c, 'match_type' => 'name', 'confidence' => 'partial'];
    }
    return ['customer' => null, 'match_type' => 'none', 'confidence' => 'none'];
}

function phone_country_codes(): array {
    return [
        ['code'=>'382','name'=>'Montenegro',     'flag'=>'🇲🇪'],
        ['code'=>'381','name'=>'Serbia',          'flag'=>'🇷🇸'],
        ['code'=>'387','name'=>'Bosnia',          'flag'=>'🇧🇦'],
        ['code'=>'385','name'=>'Croatia',         'flag'=>'🇭🇷'],
        ['code'=>'389','name'=>'North Macedonia', 'flag'=>'🇲🇰'],
        ['code'=>'355','name'=>'Albania',         'flag'=>'🇦🇱'],
        ['code'=>'386','name'=>'Slovenia',        'flag'=>'🇸🇮'],
        ['code'=>'43', 'name'=>'Austria',         'flag'=>'🇦🇹'],
        ['code'=>'32', 'name'=>'Belgium',         'flag'=>'🇧🇪'],
        ['code'=>'359','name'=>'Bulgaria',        'flag'=>'🇧🇬'],
        ['code'=>'420','name'=>'Czech Republic',  'flag'=>'🇨🇿'],
        ['code'=>'45', 'name'=>'Denmark',         'flag'=>'🇩🇰'],
        ['code'=>'358','name'=>'Finland',         'flag'=>'🇫🇮'],
        ['code'=>'33', 'name'=>'France',          'flag'=>'🇫🇷'],
        ['code'=>'49', 'name'=>'Germany',         'flag'=>'🇩🇪'],
        ['code'=>'30', 'name'=>'Greece',          'flag'=>'🇬🇷'],
        ['code'=>'36', 'name'=>'Hungary',         'flag'=>'🇭🇺'],
        ['code'=>'353','name'=>'Ireland',         'flag'=>'🇮🇪'],
        ['code'=>'39', 'name'=>'Italy',           'flag'=>'🇮🇹'],
        ['code'=>'31', 'name'=>'Netherlands',     'flag'=>'🇳🇱'],
        ['code'=>'47', 'name'=>'Norway',          'flag'=>'🇳🇴'],
        ['code'=>'48', 'name'=>'Poland',          'flag'=>'🇵🇱'],
        ['code'=>'351','name'=>'Portugal',        'flag'=>'🇵🇹'],
        ['code'=>'40', 'name'=>'Romania',         'flag'=>'🇷🇴'],
        ['code'=>'7',  'name'=>'Russia',          'flag'=>'🇷🇺'],
        ['code'=>'34', 'name'=>'Spain',           'flag'=>'🇪🇸'],
        ['code'=>'46', 'name'=>'Sweden',          'flag'=>'🇸🇪'],
        ['code'=>'41', 'name'=>'Switzerland',     'flag'=>'🇨🇭'],
        ['code'=>'90', 'name'=>'Turkey',          'flag'=>'🇹🇷'],
        ['code'=>'380','name'=>'Ukraine',         'flag'=>'🇺🇦'],
        ['code'=>'44', 'name'=>'United Kingdom',  'flag'=>'🇬🇧'],
        ['code'=>'1',  'name'=>'USA / Canada',    'flag'=>'🇺🇸'],
        ['code'=>'971','name'=>'UAE',             'flag'=>'🇦🇪'],
        ['code'=>'966','name'=>'Saudi Arabia',    'flag'=>'🇸🇦'],
    ];
}

function phone_input(string $field_name = 'phone', string $value = '', string $default_code = '382'): string {
    $codes = phone_country_codes();
    $selected_code = $default_code;
    $number_only = $value;
    if (preg_match('/^\+(\d{1,3})(.*)$/', $value, $m)) {
        $selected_code = $m[1];
        $number_only = ltrim($m[2]);
    }
    $options = '';
    foreach ($codes as $c) {
        $sel = $c['code'] === $selected_code ? ' selected' : '';
        $options .= "<option value=\"{$c['code']}\"{$sel}>{$c['flag']} +{$c['code']} {$c['name']}</option>";
    }
    return "<div style=\"display:flex;gap:6px;\">
      <select name=\"{$field_name}_country_code\" style=\"width:190px;flex-shrink:0;\">{$options}</select>
      <input type=\"text\" name=\"{$field_name}\" value=\"" . htmlspecialchars($number_only) . "\" placeholder=\"67 123 456\" style=\"flex:1;\">
    </div>";
}
