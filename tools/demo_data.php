<?php
/**
 * Fictional scholarships so you can try search, filters and paging before the real class data is loaded.
 *   php tools/demo_data.php add      adds 72 rows, every name starts with "DEMO:"
 *   php tools/demo_data.php remove   deletes exactly those rows (and any provider only they used)
 * Nothing here is a real scholarship. Do not leave demo rows in the database you demo or hand in.
 */
if (PHP_SAPI !== 'cli') { exit('Command line only.'); }
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$cmd = $argv[1] ?? '';

if ($cmd === 'remove') {
    $pdo->beginTransaction();
    $n = $pdo->exec("DELETE FROM scholarships WHERE discovery_source = 'DEMO DATA' AND name LIKE 'DEMO: %'");
    $pdo->exec("DELETE FROM providers WHERE name LIKE 'Demo %' AND id NOT IN (SELECT provider_id FROM scholarships)");
    $pdo->commit();
    exit("Removed $n demo scholarships.\n");
}
if ($cmd !== 'add') { exit("Usage: php tools/demo_data.php add|remove\n"); }

mt_srand(325);   // same data every time
$states = ['MN', 'CA', 'TX', 'NY', 'IA', 'WI', 'FL', 'WA'];
$fields = $pdo->query('SELECT id, name FROM fields_of_study')->fetchAll(PDO::FETCH_KEY_PAIR);
$cats = $pdo->query('SELECT id FROM categories')->fetchAll(PDO::FETCH_COLUMN);
$themes = $pdo->query('SELECT id FROM national_themes')->fetchAll(PDO::FETCH_COLUMN);
$topics = ['Robotics', 'Nursing', 'Writing', 'Leadership', 'Coding', 'Art', 'Music', 'Farming', 'Community Service', 'Math', 'Biology', 'Business', 'Teaching'];
$yn = ['Yes', 'No', 'Unknown'];

$prov = $pdo->prepare('INSERT INTO providers (name, name_key) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
$ins = $pdo->prepare("INSERT INTO scholarships (name, provider_id, official_url, url_key, discovery_source, state_code, scope, school_level,
    award_min, award_max, renewable, deadline, is_rolling, min_gpa, need_required, essay_required, recommendation_required, verification_date, status)
    VALUES (?, ?, ?, ?, 'DEMO DATA', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
$g = $pdo->prepare('INSERT INTO scholarship_grades (scholarship_id, grade) VALUES (?, ?)');
$f = $pdo->prepare('INSERT IGNORE INTO scholarship_fields (scholarship_id, field_id) VALUES (?, ?)');
$c = $pdo->prepare('INSERT IGNORE INTO scholarship_categories (scholarship_id, category_id) VALUES (?, ?)');

$pdo->beginTransaction();
for ($i = 1; $i <= 72; $i++) {
    $topic = $topics[$i % count($topics)];
    $national = $i % 5 === 0;
    $state = $national ? 'US' : $states[$i % count($states)];
    $pname = 'Demo ' . $topic . ' Fund ' . (1 + intdiv($i, 4));
    $prov->execute([$pname, strtolower($pname)]);
    $pid = (int)$pdo->lastInsertId();
    $url = 'https://demo' . $i . '.example.org/apply';
    $lo = 6 + ($i % 5);
    $hi = min(12, $lo + 2 + ($i % 4));
    $level = $hi <= 8 ? 'middle' : ($lo >= 9 ? 'high' : 'both');
    $award = [250, 500, 1000, 2000, 5000, 10000][$i % 6];
    // A spread of deadlines: past, soon, later, rolling, none.
    $mode = $i % 9;
    $deadline = match (true) {
        $mode === 0 => date('Y-m-d', strtotime('-' . (10 + $i) . ' days')),     // expired
        $mode === 1 => null,                                                    // none and not rolling
        $mode === 2 => null,                                                    // rolling
        default => date('Y-m-d', strtotime('+' . (5 + $i * 3) . ' days')),
    };
    $ins->execute(['DEMO: ' . $topic . ' Award ' . $i, $pid, $url, 'demo' . $i . '.example.org/apply', $state, $national ? 'national' : 'state_local',
        $level, $award, $i % 4 === 0 ? $award * 2 : $award, $yn[$i % 3], $deadline, $mode === 2 ? 1 : 0,
        $i % 3 === 0 ? '3.0' : null, $yn[($i + 1) % 3], $yn[($i + 2) % 3], $yn[$i % 3],
        $i % 7 === 0 ? null : date('Y-m-d', strtotime('-' . ($i % 40) . ' days'))]);
    $sid = (int)$pdo->lastInsertId();
    for ($gr = $lo; $gr <= $hi; $gr++) { $g->execute([$sid, $gr]); }
    $fid = array_keys($fields)[$i % count($fields)];
    $f->execute([$sid, $fid]);
    if ($i % 6 === 0) { $f->execute([$sid, array_keys($fields)[($i + 3) % count($fields)]]); }
    $c->execute([$sid, $cats[$i % count($cats)]]);
    if ($national) { $pdo->prepare('UPDATE scholarships SET national_theme_id = ? WHERE id = ?')->execute([$themes[$i % count($themes)], $sid]); }
}
$pdo->commit();
echo "Added 72 demo scholarships. Remove them with: php tools/demo_data.php remove\n";
