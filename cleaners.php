<?php
/**
 * Import cleaning rules (FR-IMP-04, FR-IMP-05, FR-IMP-06).
 *
 * Pure functions: no database, no HTTP, no globals. That makes them easy to unit test
 * (see tests/cleaner_test.php) and safe to call from the upload page, the CLI, or the
 * AJAX handler.
 */

// ---------------------------------------------------------------------------
// Text
// ---------------------------------------------------------------------------

/** Trim, convert non-breaking / zero-width spaces to normal spaces, collapse runs of whitespace. */
function clean_text(?string $s): string
{
    if ($s === null) {
        return '';
    }
    // U+00A0 NBSP, U+2007 figure space, U+202F narrow NBSP, U+200B/U+200C/U+200D/U+FEFF zero-width
    $s = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $s);
    $s = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

// ---------------------------------------------------------------------------
// States and scope
// ---------------------------------------------------------------------------

function state_names(): array
{
    return [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire',
        'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina',
        'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee',
        'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        // Not in the 50-state list but common in real data. Remove if the instructor says no.
        'DC' => 'District of Columbia',
    ];
}

/** The code we store for "National". Matches the seed row in 002_seed_reference.sql. */
const NATIONAL_CODE = 'US';

/**
 * One state token ("Minnesota", "mn", "MN.", "National") -> 2-letter code, 'US' for national,
 * or null if we cannot tell.
 */
function normalize_state(?string $s): ?string
{
    $t = strtolower(clean_text($s));
    $t = rtrim($t, '.');
    if ($t === '') {
        return null;
    }
    if (in_array($t, ['national', 'nationwide', 'usa', 'us', 'u.s.', 'united states', 'all states', 'all 50 states'], true)) {
        return NATIONAL_CODE;
    }
    $names = state_names();
    $upper = strtoupper($t);
    if (isset($names[$upper])) {
        return $upper;
    }
    foreach ($names as $code => $name) {
        if ($t === strtolower($name)) {
            return $code;
        }
    }
    if ($t === 'washington dc' || $t === 'washington, dc' || $t === 'd.c.') {
        return 'DC';
    }
    return null;
}

/**
 * "MN, WI; IA" or "Minnesota and Wisconsin" -> ['codes' => ['MN','WI','IA'], 'unknown' => []]
 * National counts as its own code ('US').
 */
function parse_state_list(?string $s): array
{
    $s = clean_text($s);
    $parts = preg_split('/\s*(?:,|;|\/|&|\band\b|\|)\s*/i', $s, -1, PREG_SPLIT_NO_EMPTY);
    $codes = [];
    $unknown = [];
    foreach ($parts as $p) {
        $code = normalize_state($p);
        if ($code === null) {
            $unknown[] = $p;
        } elseif (!in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }
    return ['codes' => $codes, 'unknown' => $unknown];
}

/**
 * FR-IMP-05: national if open to residents of two or more states (or explicitly National),
 * otherwise state/local. Regional (e.g. several Midwest states) is therefore national.
 * Returns 'national', 'state_local', or null when there are no usable state codes.
 */
function classify_scope(array $stateCodes): ?string
{
    if (count($stateCodes) === 0) {
        return null;
    }
    if (in_array(NATIONAL_CODE, $stateCodes, true) || count($stateCodes) >= 2) {
        return 'national';
    }
    return 'state_local';
}

/** Cleans the free-text "National vs. state/local" column. Returns 'national', 'state_local', or null. */
function parse_scope_label(?string $s): ?string
{
    $t = strtolower(clean_text($s));
    if ($t === '') {
        return null;
    }
    if (str_contains($t, 'national') || str_contains($t, 'regional')) {
        return 'national';
    }
    if (str_contains($t, 'state') || str_contains($t, 'local')) {
        return 'state_local';
    }
    return null;
}

// ---------------------------------------------------------------------------
// Yes / No / Unknown
// ---------------------------------------------------------------------------

/** Returns 'Yes', 'No', 'Unknown', or null when the value is not recognizable (caller adds a warning). */
function parse_yes_no(?string $s): ?string
{
    $t = strtolower(clean_text($s));
    $t = trim($t, " .!");
    if ($t === '' || in_array($t, ['unknown', 'n/a', 'na', 'tbd', '?', 'unsure', 'not stated', 'unclear', '-', '--'], true)) {
        return 'Unknown';
    }
    if (in_array($t, ['yes', 'y', 'true', '1', 'required', 'yes - required'], true)) {
        return 'Yes';
    }
    if (in_array($t, ['no', 'n', 'false', '0', 'not required', 'none', 'optional'], true)) {
        return 'No';
    }
    return null;
}

// ---------------------------------------------------------------------------
// Money
// ---------------------------------------------------------------------------

function _money_number(string $s): ?float
{
    $s = strtolower(str_replace([',', '$', ' '], '', $s));
    if (preg_match('/^(\d+(?:\.\d+)?)(k)?$/', $s, $m)) {
        return (float)$m[1] * (isset($m[2]) && $m[2] === 'k' ? 1000 : 1);
    }
    return null;
}

/**
 * "$1,000" -> min 1000, max 1000.   "$500 - $2,000" -> 500 / 2000.   "Up to $5,000" -> null / 5000.
 * "Varies", "", "TBD" -> both null, ok=true (not an error; just no amount).
 * Anything with digits we cannot interpret -> ok=false (caller adds a warning).
 */
function parse_money(string|int|float|null $v): array
{
    $out = ['min' => null, 'max' => null, 'ok' => true];
    if ($v === null || $v === '') {
        return $out;
    }
    if (is_int($v) || is_float($v)) {
        $out['min'] = $out['max'] = (float)$v;
        return $out;
    }
    $s = strtolower(clean_text($v));
    if ($s === '' || in_array($s, ['varies', 'varied', 'variable', 'tbd', 'n/a', 'na', 'unknown', 'unspecified', '-'], true)) {
        return $out;
    }
    // "Up to $5,000" must be recognized BEFORE " to " is treated as a range separator.
    if (preg_match('/^(?:up to|max(?:imum)?(?: of)?)\s*(.+)$/', $s, $m)) {
        $n = _money_number($m[1]);
        if ($n === null) { $out['ok'] = false; return $out; }
        $out['max'] = $n;
        return $out;
    }
    $s = str_replace(['–', '—', ' to '], '-', $s);
    if (str_contains($s, '-')) {
        [$a, $b] = array_pad(explode('-', $s, 2), 2, '');
        $lo = _money_number(trim($a));
        $hi = _money_number(trim($b));
        if ($lo === null || $hi === null) { $out['ok'] = false; return $out; }
        $out['min'] = min($lo, $hi);
        $out['max'] = max($lo, $hi);
        return $out;
    }
    $n = _money_number(trim($s));
    if ($n === null) {
        // Has digits we could not interpret (e.g. "$1,000 per year") -> flag it.
        $out['ok'] = !preg_match('/\d/', $s);
        return $out;
    }
    $out['min'] = $out['max'] = $n;
    return $out;
}

/** Whole-number parse for "Number of awards": "10", "10 awards" -> 10, otherwise null. */
function parse_int(string|int|float|null $v): ?int
{
    if (is_int($v)) {
        return $v;
    }
    if (is_float($v)) {
        return (int)round($v);
    }
    $s = str_replace(',', '', clean_text($v));
    return preg_match('/^(\d+)(?:\s*\w+)?$/', $s, $m) ? (int)$m[1] : null;
}

/** Minimum GPA, "3.0" / "3" / "3.0 GPA" -> 3.0. Out of 0..4.5 range or unparseable -> null. */
function parse_gpa(string|int|float|null $v): ?float
{
    if (is_int($v) || is_float($v)) {
        $g = (float)$v;
    } else {
        $s = clean_text($v);
        if (!preg_match('/(\d+(?:\.\d+)?)/', $s, $m)) {
            return null;
        }
        $g = (float)$m[1];
    }
    return ($g >= 0 && $g <= 4.5) ? $g : null;
}

// ---------------------------------------------------------------------------
// Dates
// ---------------------------------------------------------------------------

/**
 * Returns ['date' => 'YYYY-MM-DD'|null, 'rolling' => bool, 'ok' => bool].
 * - "Rolling", "Ongoing", "Year-round", "Varies" -> rolling = true, date null.
 * - Excel serial numbers (e.g. 46082) are converted.
 * - Blank -> date null, ok true (the caller decides whether a blank deadline is a warning).
 * - Unparseable text -> ok false.
 * Two-digit years are read as 20xx. Slash dates are read US style (month/day/year).
 */
function parse_date(string|int|float|null $v): array
{
    $out = ['date' => null, 'rolling' => false, 'ok' => true];
    if ($v === null || $v === '') {
        return $out;
    }
    if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^\d{5}(\.\d+)?$/', trim($v)))) {
        $serial = (int)floor((float)$v);
        if ($serial >= 36526 && $serial <= 60000) {          // roughly 2000..2064
            $out['date'] = gmdate('Y-m-d', ($serial - 25569) * 86400);
            return $out;
        }
        $out['ok'] = false;
        return $out;
    }
    $s = strtolower(clean_text($v));
    if ($s === '') {
        return $out;
    }
    if (preg_match('/^(rolling|ongoing|year[- ]?round|varies|open|continuous|n\/a|tbd|unknown)/', $s)) {
        $out['rolling'] = in_array(explode(' ', $s)[0], ['rolling', 'ongoing', 'year-round', 'year', 'continuous', 'open', 'varies'], true);
        return $out;
    }
    $s = preg_replace('/(\d)(st|nd|rd|th)\b/', '$1', $s);

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
        [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$#', $s, $m)) {
        [$mo, $d, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        if ($y < 100) { $y += 2000; }
    } else {
        $ts = strtotime($s . ' UTC');
        if ($ts === false) {
            $out['ok'] = false;
            return $out;
        }
        [$y, $mo, $d] = [(int)gmdate('Y', $ts), (int)gmdate('n', $ts), (int)gmdate('j', $ts)];
    }
    if (!checkdate($mo, $d, $y)) {
        $out['ok'] = false;
        return $out;
    }
    $out['date'] = sprintf('%04d-%02d-%02d', $y, $mo, $d);
    return $out;
}

// ---------------------------------------------------------------------------
// Grades
// ---------------------------------------------------------------------------

/**
 * "9-12", "9–12", "6, 7, 8", "9th through 12th", "Grades 9 to 12", "middle school", "high school", "K-12"
 * -> ['grades' => [9,10,11,12], 'dropped' => [...grades outside 6..12 that were ignored...]]
 */
function parse_grades(?string $s): array
{
    $s = strtolower(clean_text($s));
    $out = ['grades' => [], 'dropped' => []];
    if ($s === '') {
        return $out;
    }
    $set = [];

    // Words first, then numbers.
    if (str_contains($s, 'middle')) { array_push($set, 6, 7, 8); }
    if (preg_match('/\bhigh\b/', $s)) { array_push($set, 9, 10, 11, 12); }
    if (preg_match('/\bfreshm[ae]n\b/', $s)) { $set[] = 9; }
    if (preg_match('/\bsophomores?\b/', $s)) { $set[] = 10; }
    if (preg_match('/\bjuniors?\b/', $s)) { $set[] = 11; }
    if (preg_match('/\bseniors?\b/', $s)) { $set[] = 12; }

    $s = str_replace(['–', '—'], '-', $s);
    $s = preg_replace('/\b(\d{1,2})(st|nd|rd|th)\b/', '$1', $s);
    $s = preg_replace('/\b(through|thru|to)\b/', '-', $s);
    $s = preg_replace('/\bk\b|\bkindergarten\b/', '0', $s);

    if (preg_match_all('/(\d{1,2})\s*-\s*(\d{1,2})/', $s, $ranges, PREG_SET_ORDER)) {
        foreach ($ranges as $r) {
            $a = (int)$r[1];
            $b = (int)$r[2];
            if ($a > $b) { [$a, $b] = [$b, $a]; }
            for ($g = $a; $g <= $b; $g++) { $set[] = $g; }
        }
        $s = preg_replace('/(\d{1,2})\s*-\s*(\d{1,2})/', ' ', $s);
    }
    if (preg_match_all('/\b(\d{1,2})\b/', $s, $singles)) {
        foreach ($singles[1] as $g) { $set[] = (int)$g; }
    }
    foreach (array_unique($set) as $g) {
        if ($g >= 6 && $g <= 12) { $out['grades'][] = $g; } else { $out['dropped'][] = $g; }
    }
    sort($out['grades']);
    sort($out['dropped']);
    return $out;
}

/** 'middle', 'high', 'both', or null if there are no grades. */
function derive_school_level(array $grades): ?string
{
    $mid = count(array_filter($grades, fn($g) => $g >= 6 && $g <= 8)) > 0;
    $high = count(array_filter($grades, fn($g) => $g >= 9 && $g <= 12)) > 0;
    return match (true) {
        $mid && $high => 'both',
        $mid => 'middle',
        $high => 'high',
        default => null,
    };
}

// ---------------------------------------------------------------------------
// URLs (the duplicate-detection key, FR-IMP-06)
// ---------------------------------------------------------------------------

/**
 * Normalization the team agreed on. Spec minimum: lowercase host, no trailing slash, no tracking params.
 * We ALSO treat http/https as the same and ignore a leading "www." and any #fragment, because those
 * produce false "different" URLs for the same page. Change here (one place) if the instructor disagrees.
 *
 * Returns null if the URL is not a usable web address.
 * Example: "HTTPS://www.Example.org/Apply/?utm_source=x&id=7#top"  ->  "example.org/Apply?id=7"
 * (Path case is preserved because paths are case-sensitive on many servers.)
 */
function normalize_url(?string $url): ?string
{
    $url = clean_text($url);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $url)) {
            $url = 'https://' . $url;          // "example.org/apply" typed without a scheme
        } else {
            return null;
        }
    }
    $p = parse_url($url);
    if ($p === false || empty($p['host']) || !str_contains($p['host'], '.')) {
        return null;
    }
    $host = strtolower($p['host']);
    $host = preg_replace('/^www\./', '', $host);
    $port = (isset($p['port']) && !in_array($p['port'], [80, 443], true)) ? ':' . $p['port'] : '';
    $path = $p['path'] ?? '';
    $path = rtrim($path, '/');

    $query = '';
    if (!empty($p['query'])) {
        parse_str($p['query'], $params);
        foreach (array_keys($params) as $k) {
            $lk = strtolower((string)$k);
            if (str_starts_with($lk, 'utm_') || in_array($lk, ['fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid', '_hsenc', '_hsmi', 'igshid'], true)) {
                unset($params[$k]);
            }
        }
        ksort($params);
        $query = $params ? '?' . http_build_query($params) : '';
    }
    return $host . $port . $path . $query;
}

/** True if the string is an http(s) URL with a dotted host. */
function is_valid_url(?string $url): bool
{
    $url = clean_text($url);
    return $url !== ''
        && preg_match('#^https?://#i', $url) === 1
        && normalize_url($url) !== null
        && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// ---------------------------------------------------------------------------
// Providers and file names
// ---------------------------------------------------------------------------

/** "The Coca-Cola Scholars Foundation" -> "coca-cola scholars foundation". Used to find duplicate providers. */
function provider_key(?string $name): string
{
    $t = strtolower(clean_text($name));
    $t = preg_replace('/^the\s+/', '', $t);
    $t = preg_replace('/[^\p{L}\p{N}\s&-]+/u', '', $t);
    return trim(preg_replace('/\s+/', ' ', $t));
}

/**
 * First letter that decides a sponsor-name range (Data Science rules):
 *  - ignore a leading "The"; names starting with a digit count as 'A' (A-M);
 *  - individual named funds use the first word as written.
 */
function sponsor_letter(?string $provider): ?string
{
    $t = clean_text($provider);
    $t = preg_replace('/^the\s+/i', '', $t);
    $t = preg_replace('/^[^\p{L}\p{N}]+/u', '', $t);
    if ($t === '') {
        return null;
    }
    $c = mb_strtoupper(mb_substr($t, 0, 1));
    if (ctype_digit($c)) {
        return 'A';
    }
    $c = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $c) ?: $c);
    return preg_match('/^[A-Z]$/', $c) ? $c : null;
}

/** "A-M", "A–H", "I-P", "Q-Z", "All" -> is $letter inside? A null letter is treated as "unknown", not outside. */
function letter_in_range(?string $letter, ?string $range): ?bool
{
    $range = strtoupper(str_replace(['–', '—'], '-', clean_text($range)));
    if ($range === '' || $range === 'ALL') {
        return true;
    }
    if ($letter === null || !preg_match('/^([A-Z])-([A-Z])$/', $range, $m)) {
        return null;
    }
    return $letter >= $m[1] && $letter <= $m[2];
}

/** "Jasthi_Siva.xlsx" -> ['last' => 'Jasthi', 'first' => 'Siva']. Extra suffixes ("_v2", "_round2") are ignored. */
function parse_student_from_filename(string $fileName): ?array
{
    $base = pathinfo(basename($fileName), PATHINFO_FILENAME);
    $parts = explode('_', $base);
    if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
        return null;
    }
    return ['last' => $parts[0], 'first' => $parts[1]];
}
