<?php
/**
 * Iteration 5 search test (needs config/config.php + schema/seeds). EMPTIES the scholarship tables: LOCAL database only.
 *   php tests/search_test.php
 */
define('CLI_TEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/search.php';

$pdo = db();
$pass = $fail = 0;
function ok(string $label, $actual, $expected): void {
    global $pass, $fail;
    if ($actual == $expected) { $pass++; } else { $fail++; echo "FAIL: $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n"; }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['search_log', 'import_staging_rows', 'scholarship_grades', 'scholarship_categories', 'scholarship_fields', 'scholarships', 'import_batches', 'providers', 'audit_log'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$field = fn(string $n) => (int)$pdo->query('SELECT id FROM fields_of_study WHERE name = ' . $pdo->quote($n))->fetchColumn();
$cat = fn(string $n) => (int)$pdo->query('SELECT id FROM categories WHERE name = ' . $pdo->quote($n))->fetchColumn();
$ENG = $field('Engineering'); $MATH = $field('Math'); $ANY = $field('Any / undecided'); $STEM = $cat('STEM');
$day = fn(int $n) => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));

$prov = $pdo->prepare('INSERT INTO providers (name, name_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
$ins = $pdo->prepare("INSERT INTO scholarships (name, provider_id, official_url, url_key, state_code, scope, school_level, award_min, award_max,
    renewable, deadline, is_rolling, min_gpa, need_required, essay_required, recommendation_required, verification_date, other_requirements, notes, status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
function add(PDO $pdo, $prov, $ins, array $o): int {
    static $n = 0; $n++;
    $o += ['provider' => 'Acme Fund', 'state' => 'MN', 'level' => 'high', 'min' => 1000, 'max' => 1000, 'renew' => 'No', 'deadline' => null, 'rolling' => 0,
           'gpa' => null, 'need' => 'Unknown', 'essay' => 'Unknown', 'rec' => 'Unknown', 'verified' => null, 'other' => null, 'notes' => 'internal note', 'status' => 'active',
           'grades' => [9, 10, 11, 12], 'fields' => [], 'cats' => []];
    $prov->execute([$o['provider'], strtolower($o['provider'])]);
    $pid = (int)$pdo->lastInsertId();
    $ins->execute([$o['name'], $pid, "https://t$n.example.org/x", "t$n.example.org/x", $o['state'], $o['state'] === 'US' ? 'national' : 'state_local', $o['level'],
        $o['min'], $o['max'], $o['renew'], $o['deadline'], $o['rolling'], $o['gpa'], $o['need'], $o['essay'], $o['rec'], $o['verified'], $o['other'], $o['notes'], $o['status']]);
    $id = (int)$pdo->lastInsertId();
    foreach ($o['grades'] as $g) { $pdo->prepare('INSERT INTO scholarship_grades VALUES (?,?)')->execute([$id, $g]); }
    foreach ($o['fields'] as $f) { $pdo->prepare('INSERT INTO scholarship_fields VALUES (?,?)')->execute([$id, $f]); }
    foreach ($o['cats'] as $c) { $pdo->prepare('INSERT INTO scholarship_categories VALUES (?,?)')->execute([$id, $c]); }
    return $id;
}
$A = fn(array $o) => add($pdo, $prov, $ins, $o);

$soon   = $A(['name' => 'Soon Robotics Grant', 'deadline' => $day(10), 'fields' => [$ENG], 'cats' => [$STEM], 'max' => 2500, 'min' => 500, 'verified' => $day(-3), 'essay' => 'No', 'need' => 'No', 'rec' => 'No']);
$later  = $A(['name' => 'Later Math Prize', 'deadline' => $day(80), 'fields' => [$MATH], 'max' => 5000, 'min' => 5000, 'verified' => $day(-30), 'gpa' => '3.5']);
$roll   = $A(['name' => 'Rolling Service Award', 'rolling' => 1, 'max' => 300, 'min' => 300, 'renew' => 'Yes', 'verified' => $day(-1), 'provider' => 'Helping Hands Trust']);
$nodl   = $A(['name' => 'No Deadline Listed', 'max' => 750, 'min' => 750]);
$past   = $A(['name' => 'Past Deadline Award', 'deadline' => $day(-20), 'fields' => [$ENG]]);
$nat    = $A(['name' => 'National Engineering Award', 'state' => 'US', 'deadline' => $day(40), 'fields' => [$ENG], 'max' => 10000, 'min' => 10000, 'grades' => [11, 12]]);
$tx     = $A(['name' => 'Texas Only Award', 'state' => 'TX', 'deadline' => $day(15), 'fields' => [$ENG]]);
$anyF   = $A(['name' => 'Open To Any Field Award', 'deadline' => $day(25), 'fields' => [$ANY]]);
$noF    = $A(['name' => 'No Field Listed Award', 'deadline' => $day(26)]);
$mid    = $A(['name' => 'Middle School Science Fair', 'level' => 'middle', 'grades' => [6, 7, 8], 'deadline' => $day(30), 'fields' => [$MATH]]);
$pct    = $A(['name' => 'Save 100% Award', 'deadline' => $day(31), 'other' => 'Must live in Hennepin County']);
$arch   = $A(['name' => 'Archived Robotics Award', 'deadline' => $day(10), 'status' => 'archived', 'fields' => [$ENG]]);
$xss    = $A(['name' => '<script>alert(1)</script> Scholarship', 'deadline' => $day(33), 'provider' => '<b>Bad</b> Org']);
$noaward = $A(['name' => 'Award Amount Varies', 'min' => null, 'max' => null, 'deadline' => $day(34)]);

$ids = fn(array $p) => array_map(fn($r) => (int)$r['id'], search_run($pdo, search_params($p))['rows']);
$sortedIds = fn(array $p) => (function () use ($ids, $p) { $x = $ids($p); sort($x); return $x; })();
$has = fn(array $p, int $id) => in_array($id, $ids($p), true);

// ---- search_params sanitising ----
$sp = search_params(['grade' => '13', 'state' => 'mn', 'field' => 'abc', 'sort' => 'bogus', 'page' => '0']);
ok('grade out of range dropped', $sp['grade'], 0);
ok('state upper-cased', $sp['state'], 'MN');
ok('non-numeric field dropped', $sp['field'], 0);
ok('bad sort falls back', $sp['sort'], 'deadline');
ok('page minimum 1', $sp['page'], 1);
ok('state with digits dropped', search_params(['state' => 'M1'])['state'], '');
ok('array value ignored', search_params(['state' => ['MN'], 'q' => ['x']])['state'], '');
ok('sql in grade dropped', search_params(['grade' => '9 OR 1=1'])['grade'], 0);
ok('gpa above 4.5 dropped', search_params(['gpa' => '9'])['gpa'], '');
ok('keyword capped at 6 words', count(explode(' ', search_params(['q' => 'a b c d e f g h'])['q'])), 6);

// ---- expired, archived ----
ok('expired hidden by default', $has([], $past), false);
ok('expired shown when asked', $has(['expired' => '1'], $past), true);
ok('no-deadline row shown by default', $has([], $nodl), true);
ok('rolling row shown by default', $has([], $roll), true);
ok('archived never shown', $has(['expired' => '1'], $arch), false);
ok('archived never shown by keyword', $has(['q' => 'Archived'], $arch), false);

// ---- mandatory filters ----
ok('state MN includes national', $has(['state' => 'MN'], $nat), true);
ok('state MN includes MN rows', $has(['state' => 'MN'], $soon), true);
ok('state MN excludes TX', $has(['state' => 'MN'], $tx), false);
ok('state US is national only', $sortedIds(['state' => 'US']), [$nat]);
ok('grade 6 finds middle school one only', $ids(['grade' => '6']), [$mid]);
ok('grade 9 excludes grade 11-12 only row', $has(['grade' => '9'], $nat), false);
ok('grade 12 includes it', $has(['grade' => '12'], $nat), true);
ok('field Engineering finds tagged row', $has(['field' => (string)$ENG], $soon), true);
ok('field Engineering excludes Math-only row', $has(['field' => (string)$ENG], $later), false);
ok('field filter keeps Any-field row', $has(['field' => (string)$ENG], $anyF), true);
ok('field filter keeps no-field row', $has(['field' => (string)$ENG], $noF), true);
$want = [$later, $roll, $nodl, $anyF, $noF, $pct, $xss, $noaward]; sort($want);
ok('all three together (AND)', $sortedIds(['state' => 'MN', 'grade' => '12', 'field' => (string)$MATH]), $want);
ok('MN+12+Math excludes Engineering-only', $has(['state' => 'MN', 'grade' => '12', 'field' => (string)$MATH], $soon), false);

// ---- keyword ----
ok('keyword in name', $has(['q' => 'robotics'], $soon), true);
ok('keyword is case-insensitive', $has(['q' => 'ROBOTICS'], $soon), true);
ok('keyword in provider', $ids(['q' => 'helping hands']), [$roll]);
ok('keyword in other requirements', $ids(['q' => 'hennepin']), [$pct]);
ok('keyword matches field name', $has(['q' => 'engineering'], $soon), true);
ok('keyword matches category name', $has(['q' => 'stem'], $soon), true);
ok('two words must both match', $ids(['q' => 'robotics math']), []);
ok('percent sign is literal', $ids(['q' => '100%']), [$pct]);
ok('underscore is literal', $ids(['q' => 'Award_']), []);
$inj = $ids(['q' => "' OR 1=1 --"]);
ok('injection keyword matches nothing', $inj, []);
ok('injection keyword leaves table intact', (int)$pdo->query('SELECT COUNT(*) FROM scholarships')->fetchColumn(), 14);
ok('injection in state ignored', count($ids(['state' => "MN' OR '1'='1"])) > 1, true);

// ---- additional filters ----
ok('min award 5000', $sortedIds(['min_award' => '5000']), [$later, $nat]);
ok('award filter skips unknown amounts', $has(['min_award' => '500'], $noaward), false);
ok('due in 30 days', $sortedIds(['due' => '30']), [$soon, $tx, $anyF, $noF, $mid]);
ok('due rolling', $ids(['due' => 'rolling']), [$roll]);
ok('gpa 3.0 hides GPA 3.5 row', $has(['gpa' => '3.0'], $later), false);
ok('gpa 3.5 shows it', $has(['gpa' => '3.5'], $later), true);
ok('gpa keeps rows with no minimum', $has(['gpa' => '2.0'], $soon), true);
ok('no essay only confirmed No', $ids(['no_essay' => '1']), [$soon]);
ok('no need required', $ids(['no_need' => '1']), [$soon]);
ok('no recommendation', $ids(['no_rec' => '1']), [$soon]);
ok('renewable', $ids(['renewable' => '1']), [$roll]);
ok('school level middle includes middle only', $ids(['level' => 'middle']), [$mid]);
ok('school level high excludes middle', $has(['level' => 'high'], $mid), false);
ok('category filter', $ids(['category' => (string)$STEM]), [$soon]);

// ---- sorting ----
$order = $ids(['sort' => 'deadline']);
ok('deadline sort: soonest first', $order[0], $soon);
ok('deadline sort: rolling/no-date after dated', array_search($roll, $order) > array_search($later, $order), true);
$withPast = $ids(['sort' => 'deadline', 'expired' => '1']);
ok('deadline sort: expired last', end($withPast), $past);
$byAward = array_map(fn($r) => $r['award_max'] ?? $r['award_min'], search_run($pdo, search_params(['sort' => 'award']))['rows']);
ok('award sort: largest first', (float)$byAward[0], 10000.0);
ok('award sort: unknown amount last', end($byAward), null);
$names = array_map(fn($r) => $r['name'], search_run($pdo, search_params(['sort' => 'name']))['rows']);
$sorted = $names; usort($sorted, 'strcasecmp');
ok('name sort A to Z', $names, $sorted);
$ver = $ids(['sort' => 'verified']);
ok('verified sort: newest first', $ver[0], $roll);
ok('verified sort: never-verified last', in_array(end($ver), [$later], true) === false, true);

// ---- paging ----
for ($i = 1; $i <= 31; $i++) { $A(['name' => "Bulk Award $i", 'deadline' => $day(100 + $i)]); }
$r1 = search_run($pdo, search_params(['page' => '1']));
ok('total counts active, not expired', $r1['total'], 13 - 1 + 31 - 0);   // 14 rows minus archived minus past + 31
ok('page size 20', count($r1['rows']), 20);
$r2 = search_run($pdo, search_params(['page' => '2']));
ok('three pages for 43 results', $r1['pages'], 3);
ok('page 2 is full', count($r2['rows']), 20);
$r3 = search_run($pdo, search_params(['page' => '3']));
ok('page 3 holds the last 3', count($r3['rows']), 3);
ok('no overlap between pages 2 and 3', array_intersect(array_column($r2['rows'], 'id'), array_column($r3['rows'], 'id')), []);
ok('no overlap between pages', array_intersect(array_column($r1['rows'], 'id'), array_column($r2['rows'], 'id')), []);
$rFar = search_run($pdo, search_params(['page' => '999']));
ok('page past the end clamps to last', $rFar['page'], $rFar['pages']);
ok('from/to numbers', [$r2['from'], $r2['to'], $r3['from'], $r3['to']], [21, 40, 41, 43]);

// ---- anonymous logging ----
$pdo->exec('TRUNCATE TABLE search_log');
search_log($pdo, search_params([]));
search_log($pdo, search_params(['q' => 'secret keyword']));
ok('nothing logged without state/grade/field', (int)$pdo->query('SELECT COUNT(*) FROM search_log')->fetchColumn(), 0);
search_log($pdo, search_params(['state' => 'MN', 'grade' => '9', 'field' => (string)$ENG, 'q' => 'secret keyword']));
$row = $pdo->query('SELECT * FROM search_log')->fetch();
ok('log stores state, grade, field', [$row['state_code'], (int)$row['grade'], (int)$row['field_id']], ['MN', 9, $ENG]);
ok('log has no keyword or identity columns', array_keys($row), ['id', 'state_code', 'grade', 'field_id', 'searched_at']);

// ---- detail page data ----
$d = scholarship_public($pdo, $soon);
ok('detail loads active', $d['name'], 'Soon Robotics Grant');
ok('detail grades', $d['grades'], [9, 10, 11, 12]);
ok('detail fields and categories', [$d['fields'], $d['categories']], [['Engineering'], ['STEM']]);
ok('archived detail hidden', scholarship_public($pdo, $arch), null);
ok('missing detail hidden', scholarship_public($pdo, 987654), null);
foreach (['notes', 'contributing_student_id', 'import_batch_id', 'created_by', 'status'] as $secret) { ok("detail does not expose $secret", array_key_exists($secret, $d), false); }
ok('grades label run', grades_label([9, 10, 11, 12]), 'Grades 9 to 12');
ok('grades label gap', grades_label([9, 11]), 'Grades 9, 11');
ok('safe url accepts https', safe_external_url('https://example.org/a'), 'https://example.org/a');
ok('safe url rejects javascript:', safe_external_url('javascript:alert(1)'), null);

// ---- output escaping ----
$html = search_render_results(search_run($pdo, search_params(['q' => 'script'])), search_params(['q' => 'script']));
ok('script tag in a name is escaped', str_contains($html, '<script>alert'), false);
ok('escaped text still shown', str_contains($html, '&lt;script&gt;'), true);

echo "\nPassed $pass, failed $fail\n";
exit($fail ? 1 : 0);
