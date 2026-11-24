<?php
/**
 * Run from the repo root:   php tests/cleaner_test.php
 * No frameworks needed. Exit code is 0 when everything passes.
 */
require_once __DIR__ . '/../includes/import/pipeline.php';

$pass = 0;
$fail = 0;
function check(string $label, $actual, $expected): void
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL: $label\n   expected: " . json_encode($expected) . "\n   actual:   " . json_encode($actual) . "\n";
    }
}

// ---- text ----
check('nbsp becomes space and trims', clean_text("\u{00A0} Hello\u{00A0}World \u{200B}"), 'Hello World');
check('collapses whitespace', clean_text("a \t\n  b"), 'a b');

// ---- states / scope ----
check('full state name', normalize_state('Minnesota'), 'MN');
check('lowercase abbreviation', normalize_state('mn'), 'MN');
check('abbreviation with period', normalize_state('MN.'), 'MN');
check('national word', normalize_state('Nationwide'), 'US');
check('unknown state', normalize_state('Atlantis'), null);
check('state list', parse_state_list('Minnesota, WI and Iowa')['codes'], ['MN', 'WI', 'IA']);
check('one state = state_local', classify_scope(['MN']), 'state_local');
check('two states = national (regional)', classify_scope(['MN', 'WI']), 'national');
check('National code = national', classify_scope(['US']), 'national');
check('no states = null', classify_scope([]), null);

// ---- yes/no ----
check('yes', parse_yes_no(' YES '), 'Yes');
check('true', parse_yes_no('true'), 'Yes');
check('no', parse_yes_no('No'), 'No');
check('blank is Unknown', parse_yes_no(''), 'Unknown');
check('n/a is Unknown', parse_yes_no('N/A'), 'Unknown');
check('garbage is null', parse_yes_no('maybe?'), null);

// ---- money ----
check('$1,000', parse_money('$1,000'), ['min' => 1000.0, 'max' => 1000.0, 'ok' => true]);
check('range', parse_money('$500 - $2,000'), ['min' => 500.0, 'max' => 2000.0, 'ok' => true]);
check('en dash range', parse_money('$500–$2,000'), ['min' => 500.0, 'max' => 2000.0, 'ok' => true]);
check('up to', parse_money('Up to $5,000'), ['min' => null, 'max' => 5000.0, 'ok' => true]);
check('5k', parse_money('5k'), ['min' => 5000.0, 'max' => 5000.0, 'ok' => true]);
check('varies is ok but empty', parse_money('Varies'), ['min' => null, 'max' => null, 'ok' => true]);
check('numeric cell', parse_money(2500), ['min' => 2500.0, 'max' => 2500.0, 'ok' => true]);
check('uninterpretable digits not ok', parse_money('$1,000 per year, plus travel')['ok'], false);

// ---- dates ----
check('iso', parse_date('2027-03-01')['date'], '2027-03-01');
check('us slash', parse_date('3/1/2027')['date'], '2027-03-01');
check('two digit year', parse_date('3/1/27')['date'], '2027-03-01');
check('long form', parse_date('March 1, 2027')['date'], '2027-03-01');
check('ordinal', parse_date('March 1st, 2027')['date'], '2027-03-01');
check('excel serial', parse_date(46082)['date'], '2026-03-01');
check('rolling', parse_date('Rolling')['rolling'], true);
check('blank ok no date', parse_date(''), ['date' => null, 'rolling' => false, 'ok' => true]);
check('impossible date not ok', parse_date('2/30/2027')['ok'], false);
check('nonsense not ok', parse_date('sometime in spring')['ok'], false);

// ---- grades ----
check('9-12', parse_grades('9-12')['grades'], [9, 10, 11, 12]);
check('en dash', parse_grades('9–12')['grades'], [9, 10, 11, 12]);
check('list', parse_grades('6, 7, 8')['grades'], [6, 7, 8]);
check('ordinals through', parse_grades('9th through 12th')['grades'], [9, 10, 11, 12]);
check('middle school', parse_grades('Middle school')['grades'], [6, 7, 8]);
check('high school', parse_grades('high school students')['grades'], [9, 10, 11, 12]);
check('K-12 clipped', parse_grades('K-12')['grades'], [6, 7, 8, 9, 10, 11, 12]);
check('seniors', parse_grades('High school seniors')['grades'], [9, 10, 11, 12]);
check('college only has none', parse_grades('13-16')['grades'], []);
check('level both', derive_school_level([7, 8, 9]), 'both');
check('level middle', derive_school_level([6, 7]), 'middle');
check('level high', derive_school_level([10]), 'high');

// ---- urls ----
check('normalize', normalize_url('HTTPS://www.Example.org/Apply/?utm_source=x&id=7#top'), 'example.org/Apply?id=7');
check('http == https', normalize_url('http://example.org/a/'), normalize_url('https://example.org/a'));
check('param order', normalize_url('https://e.org/a?b=2&a=1'), normalize_url('https://e.org/a?a=1&b=2'));
check('no scheme', normalize_url('example.org/apply'), 'example.org/apply');
check('not a url', normalize_url('see website'), null);
check('valid', is_valid_url('https://example.org'), true);
check('invalid (no scheme)', is_valid_url('example.org'), false);

// ---- sponsor letter & range ----
check('leading The', sponsor_letter('The Coca-Cola Scholars Foundation'), 'C');
check('digit goes to A', sponsor_letter('100 Black Men of America'), 'A');
check('named fund', sponsor_letter('Jane Smith Memorial Fund'), 'J');
check('in A-M', letter_in_range('C', 'A–M'), true);
check('out of A-M', letter_in_range('N', 'A-M'), false);
check('Texas I-P', letter_in_range('K', 'I-P'), true);
check('All', letter_in_range('Z', 'All'), true);
check('provider key', provider_key('The Coca-Cola Scholars Foundation'), 'coca-cola scholars foundation');

// ---- file name ----
check('file name', parse_student_from_filename('Jasthi_Siva.xlsx'), ['last' => 'Jasthi', 'first' => 'Siva']);
check('file name with suffix', parse_student_from_filename('Tony-Uduhirinwa_Sophie_v2.csv'), ['last' => 'Tony-Uduhirinwa', 'first' => 'Sophie']);
check('bad file name', parse_student_from_filename('scholarships.xlsx'), null);

// ---- whole-file pipeline with deliberate problems (Iteration 3 test file) ----
$headers = array_values(template_columns());
$mk = function (array $over) use ($headers) {
    $base = array_fill_keys($headers, '');
    $base['Scholarship name'] = 'Good Scholarship';
    $base['Scholarship provider/organization'] = 'Kind Foundation';
    $base['Official scholarship URL'] = 'https://kind.org/apply';
    $base['State/region'] = 'MN';
    $base['Eligible grade levels'] = '9-12';
    $base['Award amount'] = '$1,000';
    $base['Application deadline'] = '2027-03-01';
    $base['Verification date'] = '2026-10-01';
    foreach (['Renewable (Yes/No/Unknown)', 'Financial need required', 'Essay required', 'Recommendation required', 'Transcript/test score required', 'Community service requirement', 'Leadership requirement'] as $h) {
        $base[$h] = 'No';
    }
    foreach ($over as $k => $v) { $base[$k] = $v; }
    return $base;
};
$rows = [
    $mk([]),                                                                                 // 2  ready
    $mk(['Official scholarship URL' => '', 'Scholarship name' => 'No URL']),                // 3  error
    $mk(['Scholarship name' => "Space\u{00A0}Case\u{00A0}", 'Official scholarship URL' => 'https://space.org/', 'Award amount' => '$2,500', 'State/region' => 'Minnesota']), // 4 ready
    $mk(['Official scholarship URL' => 'https://www.kind.org/apply/?utm_campaign=z']),       // 5  duplicate of row 2 (within file)
    $mk(['Scholarship name' => 'Midwest Award', 'Official scholarship URL' => 'https://mw.org/a', 'State/region' => 'MN, WI, IA', 'National vs. state/local' => 'State/local']), // 6 warning (scope fixed)
    $mk(['Official scholarship URL' => 'https://live.org/x', 'Scholarship name' => 'Already Live']), // 7  duplicate (database)
    $mk(['Scholarship name' => 'College Only', 'Official scholarship URL' => 'https://col.org', 'Eligible grade levels' => '13-16']), // 8 error
    $mk(['Scholarship name' => 'Bad Dates', 'Official scholarship URL' => 'https://bd.org', 'Application deadline' => 'spring-ish', 'Essay required' => 'maybe?']), // 9 warning
    array_fill_keys($headers, ''),                                                           // 10 blank (skipped)
];
$ctx = [
    'today' => '2026-10-05',
    'existing' => ['live.org/x' => 42],
    'assignments' => ['kadiyala|arjun' => ['id' => 24, 'state_code' => 'MN', 'state_range' => 'A-M', 'national_theme_id' => 24, 'national_range' => 'A-M']],
];
$res = validate_file($rows, $headers, 'Kadiyala_Arjun.xlsx', $ctx);
$byRow = [];
foreach ($res['rows'] as $r) { $byRow[$r['row']] = $r; }

check('row 2 ready', $byRow[2]['status'], 'ready');
check('row 3 missing url is error', $byRow[3]['status'], 'error');
check('row 4 nbsp + full state name ready', $byRow[4]['status'], 'ready');
check('row 4 name cleaned', $byRow[4]['clean']['name'], 'Space Case');
check('row 4 state code', $byRow[4]['clean']['state_code'], 'MN');
check('row 4 award', $byRow[4]['clean']['award_max'], 2500.0);
check('row 5 duplicate within file', $byRow[5]['status'], 'duplicate');
check('row 6 regional is national', $byRow[6]['clean']['scope'], 'national');
check('row 6 stored as US', $byRow[6]['clean']['state_code'], 'US');
check('row 6 gets assigned theme', $byRow[6]['clean']['national_theme_id'], 24);
check('row 6 warns about scope', $byRow[6]['status'], 'warning');
check('row 7 duplicate against database', $byRow[7]['status'], 'duplicate');
check('row 8 college-only is error', $byRow[8]['status'], 'error');
check('row 9 bad date + yes/no is warning', $byRow[9]['status'], 'warning');
check('row 9 deadline nulled', $byRow[9]['clean']['deadline'], null);
check('blank row skipped', isset($byRow[10]), false);
check('counts', $res['counts'], ['total' => 8, 'ready' => 2, 'warning' => 2, 'duplicate' => 2, 'error' => 2]);

// duplicate across files uses the same $ctx
$rows2 = [$mk(['Official scholarship URL' => 'https://kind.org/apply'])];
$res2 = validate_file($rows2, $headers, 'Other_Student.xlsx', $ctx);
check('duplicate across files', $res2['rows'][0]['status'], 'duplicate');

// missing required columns
$bad = validate_file([['Foo' => 'bar']], ['Foo'], 'x.csv', $ctx);
check('missing columns reported at file level', $bad['batch_messages'][0]['level'] ?? null, 'error');
check('missing columns lists the required ones', str_contains($bad['batch_messages'][0]['text'] ?? '', 'Scholarship name'), true);

// sponsor range check: Kadiyala national range is A-M, provider "Zeta Fund" -> warning
$rows3 = [$mk(['Scholarship provider/organization' => 'Zeta Fund', 'State/region' => 'National', 'Official scholarship URL' => 'https://zeta.org'])];
$res3 = validate_file($rows3, $headers, 'Kadiyala_Arjun.xlsx', $ctx);
check('out-of-range sponsor warns', $res3['rows'][0]['status'], 'warning');

// aggregator warning
$agg = validate_row($mk([]) ? ['name' => 'A', 'provider' => 'B', 'url' => 'https://www.fastweb.com/x', 'state' => 'MN', 'grades' => '9-12', 'deadline' => '2027-01-01', 'verification_date' => '2026-10-01', 'award' => '100', 'renewable' => 'No', 'need' => 'No', 'essay' => 'No', 'recommendation' => 'No', 'transcript' => 'No', 'community_service' => 'No', 'leadership' => 'No'] : [], ['today' => '2026-10-05']);
check('aggregator url warns', $agg['status'], 'warning');

// template round trip
$tmp = tempnam(sys_get_temp_dir(), 'tpl');
file_put_contents($tmp, template_csv());
[$h, $r] = read_csv_rows($tmp);
unlink($tmp);
check('template headers round-trip', $h, array_values(template_columns()));
check('template has example row', count($r), 1);
check('template headers all map', map_headers($h)['missing'], []);
check('template maps all 29 columns', count(map_headers($h)['map']), 29);


// ---- reference names that contain commas or slashes stay whole ----
$refFields = ['Arts, music, and design', 'Engineering', 'Writing, journalism, and communications'];
check('comma name kept whole', _split_multi('Engineering; Writing, journalism, and communications', $refFields), ['Writing, journalism, and communications', 'Engineering']);
check('comma name matched ignoring case', _split_multi('arts, MUSIC, and design', $refFields), ['Arts, music, and design']);
check('slash name kept whole', _split_multi('STEM, Essay/contest', ['Essay/contest']), ['Essay/contest', 'STEM']);
check('plain lists still split', _split_multi('STEM, Merit; Leadership'), ['STEM', 'Merit', 'Leadership']);

// ---- official Data Science template: example cells carry notes in brackets ----
check('state with region note', parse_state_list('Tennessee (South)')['codes'], ['TN']);
check('national with note', parse_state_list('National (all U.S. states)')['codes'], ['US']);
check('yes/no with note: No', parse_yes_no('No (FAFSA required)'), 'No');
check('yes/no with note: Yes', parse_yes_no('Yes (2 online teacher forms)'), 'Yes');
check('yes/no with note: Unknown', parse_yes_no('Unknown (aggregator lists a 300-word essay)'), 'Unknown');
check('"Not required" still No', parse_yes_no('Not required'), 'No');
check('"None stated" GPA is blank', parse_gpa('None stated'), null);
check('two GPAs: first one', parse_gpa('2.0 (Cal Grant B) / 3.0 (Cal Grant A)'), 2.0);
check('open date "Unknown (...)" is blank', parse_date('Unknown (already open as of 2026-08-11)')['date'], null);
check('"Not posted" deadline is missing, not rolling', parse_date('Not posted on the provider page'), ['date' => null, 'rolling' => false, 'ok' => true]);
check('"Varies" deadline still rolling', parse_date('Varies')['rolling'], true);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
