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

function find_customer_match(string $name, string $phone, string $email): array {
    $email = strtolower(trim($email));
    if ($email) {
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
    if ($name = trim($name)) {
        $c = db_row('SELECT * FROM customers WHERE LOWER(name) = LOWER(?) AND deleted_at IS NULL', [$name]);
        if ($c) return ['customer' => $c, 'match_type' => 'name', 'confidence' => 'exact'];
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
