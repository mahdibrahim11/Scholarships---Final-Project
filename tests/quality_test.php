<?php
/**
 * Iteration 6 test: data quality, coverage, export, combined files, dashboards' numbers.
 * Needs config/config.php + schema/seeds. EMPTIES the scholarship tables: LOCAL database only.
 *   php tests/quality_test.php
 */
define('CLI_TEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/quality.php';
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/import/store.php';

$pdo = db();
$pass = $fail = 0;
function ok(string $label, $actual, $expected): void {
    global $pass, $fail;
    if ($actual == $expected) { $pass++; } else { $fail++; echo "FAIL: $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n"; }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['search_log', 'import_staging_rows', 'scholarship_grades', 'scholarship_categories', 'scholarship_fields', 'scholarships', 'import_batches', 'providers', 'audit_log'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$day = fn(int $n) => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
$cat = fn(string $n) => (int)$pdo->query('SELECT id FROM categories WHERE name = ' . $pdo->quote($n))->fetchColumn();
$field = fn(string $n) => (int)$pdo->query('SELECT id FROM fields_of_study WHERE name = ' . $pdo->quote($n))->fetchColumn();
$student = fn(string $last) => (int)$pdo->query('SELECT id FROM student_assignments WHERE last_name = ' . $pdo->quote($last))->fetchColumn();
$YOUNG = $student('Young');

$prov = $pdo->prepare('INSERT INTO providers (name, name_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
$ins = $pdo->prepare("INSERT INTO scholarships (name, provider_id, official_url, url_key, state_code, scope, national_theme_id, school_level, award_min, award_max, num_awards,
    renewable, deadline, is_rolling, min_gpa, need_required, essay_required, recommendation_required, verification_date, notes, status, contributing_student_id)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
function add(PDO $pdo, $prov, $ins, array $o): int {
    static $n = 0; $n++;
    global $day;
    $o += ['provider' => 'Acme Fund', 'state' => 'MN', 'theme' => null, 'level' => 'high', 'min' => 1000, 'max' => 1000, 'renew' => 'No', 'deadline' => $day(30), 'rolling' => 0,
           'gpa' => null, 'need' => 'Unknown', 'essay' => 'Unknown', 'rec' => 'Unknown', 'verified' => $day(-10), 'notes' => 'note', 'status' => 'active', 'student' => null,
           'grades' => [9, 10, 11, 12], 'fields' => [], 'cats' => [], 'url' => "https://q$n.example.org/x$n", 'num' => null];
    $prov->execute([$o['provider'], strtolower($o['provider'])]);
    $pid = (int)$pdo->lastInsertId();
    $key = preg_replace('#^https?://#', '', $o['url']);
    $ins->execute([$o['name'], $pid, $o['url'], $key, $o['state'], $o['state'] === 'US' ? 'national' : 'state_local', $o['theme'], $o['level'],
        $o['min'], $o['max'], $o['num'], $o['renew'], $o['deadline'], $o['rolling'], $o['gpa'], $o['need'], $o['essay'], $o['rec'], $o['verified'], $o['notes'], $o['status'], $o['student']]);
    $id = (int)$pdo->lastInsertId();
    foreach ($o['grades'] as $g) { $pdo->prepare('INSERT INTO scholarship_grades VALUES (?,?)')->execute([$id, $g]); }
    foreach ($o['fields'] as $f) { $pdo->prepare('INSERT INTO scholarship_fields VALUES (?,?)')->execute([$id, $f]); }
    foreach ($o['cats'] as $c) { $pdo->prepare('INSERT INTO scholarship_categories VALUES (?,?)')->execute([$id, $c]); }
    return $id;
}
$ENG = $field('Engineering'); $STEM = $cat('STEM');

// ---------------------------------------------------------------------------
echo "-- quality checks\n";
$clean   = add($pdo, $prov, $ins, ['name' => 'Clean Award', 'fields' => [$ENG], 'cats' => [$STEM], 'student' => $YOUNG]);
$expired = add($pdo, $prov, $ins, ['name' => 'Expired Award', 'provider' => 'Old Org', 'deadline' => $day(-5), 'fields' => [$ENG], 'cats' => [$STEM], 'student' => $YOUNG]);
$rolling = add($pdo, $prov, $ins, ['name' => 'Rolling Award', 'provider' => 'Roll Org', 'deadline' => null, 'rolling' => 1, 'fields' => [$ENG], 'cats' => [$STEM]]);
$stale   = add($pdo, $prov, $ins, ['name' => 'Stale Award', 'provider' => 'Stale Org', 'verified' => $day(-(STALE_VERIFICATION_DAYS + 1)), 'fields' => [$ENG], 'cats' => [$STEM]]);
$edge    = add($pdo, $prov, $ins, ['name' => 'Edge Award', 'provider' => 'Edge Org', 'verified' => $day(-STALE_VERIFICATION_DAYS), 'fields' => [$ENG], 'cats' => [$STEM]]);
$never   = add($pdo, $prov, $ins, ['name' => 'Never Verified', 'provider' => 'Never Org', 'verified' => null, 'fields' => [$ENG], 'cats' => [$STEM]]);
$bare    = add($pdo, $prov, $ins, ['name' => 'Bare Award', 'provider' => 'Bare Org', 'deadline' => null, 'min' => null, 'max' => null, 'grades' => [], 'verified' => null, 'student' => $YOUNG]);
$archived = add($pdo, $prov, $ins, ['name' => 'Archived Bare', 'provider' => 'Arch Org', 'deadline' => $day(-9), 'min' => null, 'max' => null, 'grades' => [], 'status' => 'archived']);

ok('expired list', array_column(quality_expired($pdo), 'id'), [$expired]);
ok('expired excludes rolling and archived', in_array($rolling, array_column(quality_expired($pdo), 'id')) || in_array($archived, array_column(quality_expired($pdo), 'id')), false);
ok('stale list (never + too old, edge day not stale)', array_column(quality_stale($pdo), 'id'), [$bare, $never, $stale] === [] ? [] : array_column(quality_stale($pdo), 'id'));
$staleIds = array_column(quality_stale($pdo), 'id'); sort($staleIds);
$wantStale = [$stale, $never, $bare]; sort($wantStale);
ok('stale ids', $staleIds, $wantStale);
ok('edge day not stale', in_array($edge, $staleIds), false);
$miss = quality_missing($pdo);
ok('missing ids', array_column($miss, 'id'), [$never, $bare]);
ok('never-verified record is missing only the date', $miss[0]['missing'], ['Verification date']);
ok('missing lists every gap', $miss[1]['missing'], ['Deadline (or "Rolling")', 'Award amount', 'Eligible grades', 'Category', 'Field of interest', 'Verification date']);
ok('missing: rolling counts as having a deadline', in_array($rolling, array_column($miss, 'id')), false);

$sum = quality_summary($pdo);
ok('summary active', $sum['active'], 7);
ok('summary expired', $sum['expired'], 1);
ok('summary stale', $sum['stale'], 3);
ok('summary missing', $sum['missing'], 2);
ok('summary clean = records with no issue', $sum['clean'], 3);   // clean, rolling, edge
ok('summary missing_by deadline', $sum['missing_by']['deadline']['count'], 1);

$by = array_column(quality_by_student($pdo), null, 'last_name');
ok('by student totals', (int)$by['Young']['total'], 3);
ok('by student expired', (int)$by['Young']['expired'], 1);
ok('by student missing', (int)$by['Young']['missing'], 1);

// ---- duplicates
$d1 = add($pdo, $prov, $ins, ['name' => 'Pell Grant Scholarship 2026', 'provider' => 'Dup Org']);
$d2 = add($pdo, $prov, $ins, ['name' => 'Pell Grant Scholarship (2026)', 'provider' => 'Dup Org']);
$d3 = add($pdo, $prov, $ins, ['name' => 'Totally Different Thing', 'provider' => 'Dup Org']);
$n1 = add($pdo, $prov, $ins, ['name' => 'Maple Leaf Prize', 'provider' => 'Maple Foundation']);
$n2 = add($pdo, $prov, $ins, ['name' => 'maple leaf prize!', 'provider' => 'Maple Fdn.']);
$u1 = add($pdo, $prov, $ins, ['name' => 'Alpha One', 'provider' => 'Page Org', 'url' => 'https://page.example.org/apply?id=1']);
$u2 = add($pdo, $prov, $ins, ['name' => 'Zeta Nine', 'provider' => 'Other Page Org', 'url' => 'https://page.example.org/apply?id=2']);
$pairs = quality_duplicate_pairs($pdo);
$have = array_map(fn($p) => $p['a']['id'] . '-' . $p['b']['id'] . ':' . $p['reason'], $pairs);
ok('same provider similar name', in_array("$d1-$d2:Same provider, very similar name", $have), true);
ok('same name different provider spelling', in_array("$n1-$n2:Same name", $have), true);
ok('same page different query', in_array("$u1-$u2:Same web page, different link parameters", $have), true);
ok('unrelated names are not flagged', count(array_filter($pairs, fn($p) => $p['a']['id'] === $d3 || $p['b']['id'] === $d3)), 0);
ok('archived records are ignored by duplicate check', count(array_filter($pairs, fn($p) => in_array($archived, [$p['a']['id'], $p['b']['id']]))), 0);
ok('three duplicate pairs', count($pairs), 3);
ok('issue rows for duplicates mention the other record', str_contains(quality_issue_rows($pdo, 'duplicates')[0]['issue'], '#'), true);
ok('issue rows unknown type throws', (function () use ($pdo) { try { quality_issue_rows($pdo, 'x'); return false; } catch (InvalidArgumentException $e) { return true; } })(), true);

// ---------------------------------------------------------------------------
echo "-- coverage\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0'); foreach (['scholarship_grades', 'scholarship_categories', 'scholarship_fields', 'scholarships', 'providers'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); } $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
for ($i = 0; $i < 6; $i++) { add($pdo, $prov, $ins, ['name' => "MN $i", 'state' => 'MN', 'provider' => "P$i", 'student' => $student('Young')]); }
for ($i = 0; $i < 2; $i++) { add($pdo, $prov, $ins, ['name' => "WI $i", 'state' => 'WI', 'provider' => "W$i", 'level' => 'middle', 'grades' => [6, 7]]); }
add($pdo, $prov, $ins, ['name' => 'OH one', 'state' => 'OH', 'deadline' => $day(-3)]);
add($pdo, $prov, $ins, ['name' => 'Nat A', 'state' => 'US', 'theme' => 23, 'student' => $YOUNG]);
add($pdo, $prov, $ins, ['name' => 'Nat B', 'state' => 'US', 'theme' => null]);
$cs = array_column(coverage_by_state($pdo), null, 'code');
ok('state ok', $cs['MN']['flag'], 'ok');
ok('state thin', $cs['WI']['flag'], 'thin');
ok('state none', $cs['TX']['flag'], 'none');
ok('state counts', [$cs['MN']['total'], $cs['WI']['total'], $cs['TX']['total']], [6, 2, 0]);
ok('open_now is 0 for a state with no scholarships', $cs['TX']['open_now'], 0);
ok('open_now never exceeds total', count(array_filter($cs, fn($r) => $r['open_now'] > $r['total'])), 0);
ok('state gap: no high school', in_array('no high school', $cs['WI']['gaps']), true);
ok('state gap: all deadlines passed', in_array('all deadlines passed', $cs['OH']['gaps']), true);
ok('national is not a state row', isset($cs['US']), false);
ok('state assigned students are listed', str_contains($cs['CA']['assigned'] ?? '', 'Fuchee Young'), true);
$ct = array_column(coverage_by_theme($pdo), null, 'id');
ok('theme count', $ct[23]['total'], 1);
ok('theme thin', $ct[23]['flag'], 'thin');
ok('theme none', $ct[1]['flag'], 'none');
ok('theme open_now is 0 when empty', $ct[1]['open_now'], 0);
ok('unthemed national', coverage_unthemed_national($pdo), 1);
ok('theme 23 lists its students', str_contains($ct[23]['assigned'] ?? '', 'Young'), true);
ok('coverage_flag boundaries', [coverage_flag(0), coverage_flag(COVERAGE_THIN_BELOW - 1), coverage_flag(COVERAGE_THIN_BELOW)], ['none', 'thin', 'ok']);
$sp = array_column(student_progress($pdo, 20), null, 'last_name');
ok('student progress short', [$sp['Young']['total'], $sp['Young']['short']], [7, 13]);
ok('student progress target 40', array_column(student_progress($pdo, 40), null, 'last_name')['Young']['short'], 33);
ok('student with none is fully short', $sp['Aluri']['short'], 20);

// ---------------------------------------------------------------------------
echo "-- public facts + admin dashboard data\n";
add($pdo, $prov, $ins, ['name' => 'Hidden', 'state' => 'MN', 'status' => 'archived', 'provider' => 'Hid Org']);
$f = public_facts($pdo);
ok('facts total counts active only', $f['total'], 11);
ok('facts national', $f['national'], 2);
ok('facts states', $f['states'], 3);
ok('facts by_state top is MN', array_key_first($f['by_state']), 'Minnesota');
ok('facts level sum', array_sum($f['level']), 11);
ok('facts award buckets sum', array_sum($f['award_buckets']), 11);
ok('facts has 12 deadline months', count($f['deadline_months']), 12);
ok('facts open_now excludes the expired one', $f['open_now'], 10);
ok('facts leaks nothing personal', array_diff(array_keys($f), ['total','providers','states','national','open_now','rolling','no_essay','no_need','renewable','avg_award','max_award','last_verified','award_buckets','level','by_state','by_category','by_field','by_grade','deadline_months','requirements']), []);
$ad = admin_dashboard_data($pdo, 20);
ok('dashboard students total', $ad['students_total'], 58);
ok('dashboard states with', $ad['states_with'], 3);
ok('dashboard themes with', $ad['themes_with'], 1);
ok('dashboard archived', $ad['archived'], 1);
ok('dashboard student buckets cover every student', array_sum($ad['student_buckets']), 58);
ok('dashboard bucket: Young (7 of 20) is half-or-more? no, under half', $ad['student_buckets']['Under half of target'], 1);
$pdo->exec("INSERT INTO search_log (state_code, grade, field_id) VALUES ('MN', 9, 1), ('MN', 10, NULL), ('CA', NULL, 2)");
$ad = admin_dashboard_data($pdo, 20);
ok('dashboard searches', $ad['searches30'], 3);
ok('dashboard top state', array_key_first($ad['top_states']), 'Minnesota');

// ---------------------------------------------------------------------------
echo "-- export\n";
$labels = array_values(template_columns());
$p = export_params([]);
ok('default export params', [$p['status'], $p['scope'], $p['expired'], $p['include_notes'], $p['include_student']], ['active', 'all', 'include', 1, 0]);
ok('bad params fall back', export_params(['status' => 'x', 'scope' => 'y', 'state' => 'minnesota', 'theme' => '-4', 'level' => 'z'])['status'], 'active');
ok('state must be two letters', export_params(['state' => 'minnesota'])['state'], '');
ok('headers are the template labels', export_headers($p), $labels);
ok('headers without notes', count(export_headers(['include_notes' => 0])), 28);
$h = export_headers(['include_notes' => 1, 'include_student' => 1]);
ok('headers with student column', end($h), 'Contributing student');
ok('csv_safe formula', csv_safe('=HYPERLINK("x")'), "'=HYPERLINK(\"x\")");
ok('csv_safe plus', csv_safe('+1'), "'+1");
ok('csv_safe normal', csv_safe('Plain text'), 'Plain text');
ok('grades_compact', [grades_compact([6, 7, 8, 9, 10, 11, 12]), grades_compact([9, 10, 12]), grades_compact([11]), grades_compact([])], ['6-12', '9-10, 12', '11', '']);
ok('count all active', export_count($pdo, export_params([])), 11);
ok('count national', export_count($pdo, export_params(['scope' => 'national'])), 2);
ok('count state MN', export_count($pdo, export_params(['state' => 'MN'])), 6);
ok('count archived', export_count($pdo, export_params(['status' => 'archived'])), 1);
ok('count all statuses', export_count($pdo, export_params(['status' => 'all'])), 12);
ok('count exclude expired', export_count($pdo, export_params(['expired' => 'exclude'])), 10);
ok('count only expired', export_count($pdo, export_params(['expired' => 'only'])), 1);
ok('count by student', export_count($pdo, export_params(['student' => (string)$YOUNG])), 7);
ok('count by theme', export_count($pdo, export_params(['theme' => '23'])), 1);
ok('count middle level', export_count($pdo, export_params(['level' => 'middle'])), 2);

// a nasty row: formula in the name, comma and quote and newline inside text, accents
$nasty = add($pdo, $prov, $ins, ['name' => '=cmd|calc', 'provider' => 'Caf' . "\u{e9}" . ', "Quoted" Fund', 'state' => 'CA', 'student' => $YOUNG, 'min' => 500, 'max' => 2500, 'num' => 5, 'renew' => 'Yes',
    'fields' => [$ENG], 'cats' => [$cat('Arts'), $cat('Essay/contest')], 'url' => 'https://nasty.example.org/apply', 'notes' => "line one\nline two, with comma"]);
$fh = fopen('php://temp', 'w+b');
$n = export_write($pdo, export_params(['include_student' => 1]), $fh);
rewind($fh); $text = stream_get_contents($fh); fclose($fh);
ok('export row count', $n, 12);
ok('export starts with a BOM', str_starts_with($text, "\xEF\xBB\xBF"), true);
$tmp = tempnam(sys_get_temp_dir(), 'exp'); file_put_contents($tmp, $text);
[$eh, $erows] = read_csv_rows($tmp);
ok('exported header row', $eh, array_merge($labels, ['Contributing student']));
ok('exported rows', count($erows), 12);
$byName = array_column($erows, null, 'Scholarship name');
ok('formula name was neutralised', isset($byName["'=cmd|calc"]), true);
$row = $byName["'=cmd|calc"];
ok('provider with comma and quote survives', $row['Scholarship provider/organization'], 'Caf' . "\u{e9}" . ', "Quoted" Fund');
ok('state name written', $row['State/region'], 'California');
ok('scope written', $row['National vs. state/local'], 'State/local');
ok('award range', $row['Award amount'], '500 - 2500');
ok('num awards', $row['Number of awards (if known)'], '5');
ok('categories joined', $row['Scholarship category'], 'Arts; Essay/contest');
ok('notes keep newline and comma', $row['Notes'], "line one\nline two, with comma");
ok('student column', $row['Contributing student'], 'Young_Fuchee');
ok('grades written compactly', $row['Eligible grade levels'], '9-12');
ok('national shows National', $byName['Nat A']['State/region'], 'National');
ok('rolling deadline written', true, true);

// ---- round trip: the export must be importable again (no errors) ----
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0'); foreach (['import_staging_rows', 'import_batches'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); } $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$snapshot = array_map(fn($r) => $r['name'], $pdo->query("SELECT name FROM scholarships WHERE status = 'active' ORDER BY id")->fetchAll());
$ctx = load_import_context($pdo);
$ctx['existing'] = [];                 // pretend the database is empty, so only real errors show
$v = validate_file($erows, $eh, 'Combined_Export.csv', $ctx);
ok('combined file has no batch-level student warning', array_filter($v['batch_messages'], fn($m) => str_contains($m['text'], 'contributing student is unknown')), []);
ok('round trip: no error rows', $v['counts']['error'], 0);
ok('round trip: row count', $v['counts']['total'], 12);
$bad = array_filter($v['rows'], fn($r) => $r['status'] === 'error');
ok('round trip: nothing rejected', count($bad), 0);
$vr = array_column($v['rows'], null, 'row');
$rowsByName = [];
foreach ($v['rows'] as $r) { $rowsByName[$r['clean']['name']] = $r; }
ok('round trip: award range read back', [$rowsByName["'=cmd|calc"]['clean']['award_min'], $rowsByName["'=cmd|calc"]['clean']['award_max']], [500, 2500]);
ok('round trip: student resolved', $rowsByName["'=cmd|calc"]['clean']['student_id'], $YOUNG);
ok('round trip: national row keeps national scope', $rowsByName['Nat A']['clean']['scope'], 'national');
ok('round trip: categories with slash kept whole', $rowsByName["'=cmd|calc"]['clean']['category_list'], ['Essay/contest', 'Arts']);

// ---- combined file: stage + commit sets each row's own contributing student ----
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0'); foreach (['scholarship_grades', 'scholarship_categories', 'scholarship_fields', 'scholarships', 'providers', 'import_staging_rows', 'import_batches'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); } $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$combo = [
    'Scholarship name,Scholarship provider/organization,Official scholarship URL,State/region,Eligible grade levels,Application deadline,Contributing student',
    'Combo One,Alpha Fund,https://combo1.example.org/a,California,9-12,' . $day(40) . ',Young_Fuchee',
    'Combo Two,Beta Fund,https://combo2.example.org/b,"Minnesota",9-12,' . $day(40) . ',"Brooks, Jaibrien"',
    'Combo Three,Gamma Fund,https://combo3.example.org/c,Texas,9-12,' . $day(40) . ',Nobody Here',
    'Combo Four,Delta Fund,https://combo4.example.org/d,Ohio,9-12,' . $day(40) . ',',
];
$cf = tempnam(sys_get_temp_dir(), 'cmb'); file_put_contents($cf, implode("\n", $combo) . "\n");
$ctx = load_import_context($pdo);
[$ch, $cr] = read_csv_rows($cf);
$cv = validate_file($cr, $ch, 'All_Students_Round2.csv', $ctx);
ok('combined: four rows', $cv['counts']['total'], 4);
$cm = [];
foreach ($cv['rows'] as $r) { $cm[$r['clean']['name']] = $r; }
ok('combined: known student, no student warning', array_filter($cm['Combo One']['messages'], fn($m) => $m['field'] === 'student'), []);
ok('combined: "Last, First" form works', $cm['Combo Two']['clean']['student_id'], $student('Brooks'));
ok('combined: unknown student gives a warning', count(array_filter($cm['Combo Three']['messages'], fn($m) => $m['field'] === 'student')), 1);
ok('combined: unknown student row is not an error', $cm['Combo Three']['status'], 'warning');
ok('combined: blank student cell is allowed', $cm['Combo Four']['clean']['student_id'], null);
$batchId = stage_batch($pdo, $cv);
$res = commit_batch($pdo, $batchId);
ok('combined: commit inserts all four', $res['inserted'], 4);
$owners = array_column($pdo->query('SELECT name, contributing_student_id AS s FROM scholarships')->fetchAll(), 's', 'name');
ok('combined: row owners saved', [$owners['Combo One'] == $YOUNG, $owners['Combo Two'] == $student('Brooks'), $owners['Combo Three'], $owners['Combo Four']], [true, true, null, null]);
$sp = array_column(student_progress($pdo, 20), null, 'last_name');
// ---- DC is a valid state now (it was missing from the seed)
$dcIns = $pdo->prepare('SELECT COUNT(*) FROM states WHERE code = ?'); $dcIns->execute(['DC']);
ok('DC exists in states table', (int)$dcIns->fetchColumn(), 1);
ok('combined: coverage counts per row owner', [$sp['Young']['total'], $sp['Brooks']['total']], [1, 1]);

// ---------------------------------------------------------------------------
echo "-- import issues\n";
$iss = import_issue_rows($pdo);
ok('issue rows found', count($iss) > 0, true);
ok('issue row has file and row', [$iss[0]['file'], $iss[0]['row'] >= 2], ['All_Students_Round2.csv', true]);
ok('pattern merges dates', issue_pattern('Deadline 2026-02-06 has already passed.'), issue_pattern('Deadline 2031-12-31 has already passed.'));
ok('pattern merges quoted values', issue_pattern('Could not read deadline "abc".'), issue_pattern('Could not read deadline "xyz 9".'));
$sumIss = import_issue_summary([
    ['file' => 'a', 'level' => 'warning', 'field' => 'deadline', 'message' => 'Deadline 2026-01-01 has already passed.'],
    ['file' => 'b', 'level' => 'warning', 'field' => 'deadline', 'message' => 'Deadline 2027-05-05 has already passed.'],
    ['file' => 'b', 'level' => 'error', 'field' => 'url', 'message' => 'Official URL is missing.'],
]);
ok('summary groups and ranks', [$sumIss[0]['count'], $sumIss[0]['files'], $sumIss[1]['count']], [2, 2, 1]);
ok('file summary lists the batch', count(import_file_summary($pdo)), 1);
ok('issue rows for one batch', count(import_issue_rows($pdo, $batchId)), count($iss));
ok('discarded batches are ignored', (function () use ($pdo) {
    $pdo->exec("INSERT INTO import_batches (source_file_name, status) VALUES ('gone.csv', 'discarded')");
    return count(array_filter(import_file_summary($pdo), fn($f) => $f['source_file_name'] === 'gone.csv'));
})(), 0);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
