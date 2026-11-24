<?php
/**
 * End-to-end import test against a real database (needs config/config.php and the schema + seeds loaded).
 * WARNING: it empties scholarships/providers/import tables first. Use your LOCAL database only.
 *   php tests/import_db_test.php
 */
define('CLI_TEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/import/store.php';

$pdo = db();
$pass = $fail = 0;
function ok(string $label, $actual, $expected): void {
    global $pass, $fail;
    if ($actual == $expected) { $pass++; } else { $fail++; echo "FAIL: $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n"; }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['import_staging_rows', 'scholarship_grades', 'scholarship_categories', 'scholarship_fields', 'scholarships', 'import_batches', 'providers', 'audit_log'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$headers = array_values(template_columns());
$mk = function (array $o) use ($headers) {
    $b = array_fill_keys($headers, '');
    $b = array_merge($b, ['Scholarship name' => 'S', 'Scholarship provider/organization' => 'Kind Foundation', 'Official scholarship URL' => 'https://kind.org/a',
        'State/region' => 'MN', 'Eligible grade levels' => '9-12', 'Award amount' => '$1,000', 'Application deadline' => date('Y-m-d', strtotime('+60 days')),
        'Verification date' => date('Y-m-d'), 'Scholarship category' => 'STEM; Merit', 'Field/area of interest' => 'Engineering, Math', 'Provider type' => 'Foundation/nonprofit']);
    foreach (['Renewable (Yes/No/Unknown)', 'Financial need required', 'Essay required', 'Recommendation required', 'Transcript/test score required', 'Community service requirement', 'Leadership requirement'] as $h) { $b[$h] = 'No'; }
    return array_merge($b, $o);
};
$rows = [
    $mk(['Scholarship name' => 'Alpha']),
    $mk(['Scholarship name' => 'Beta', 'Official scholarship URL' => 'https://kind.org/b', 'State/region' => 'Minnesota', 'Eligible grade levels' => '6-8']),
    $mk(['Scholarship name' => 'Dup of Alpha', 'Official scholarship URL' => 'http://www.kind.org/a/?utm_source=x']),
    $mk(['Scholarship name' => 'No URL', 'Official scholarship URL' => '']),
    $mk(['Scholarship name' => 'Midwest', 'Official scholarship URL' => 'https://kind.org/mw', 'State/region' => 'MN, WI', 'Scholarship provider/organization' => 'The Kind Foundation']),
];

$ctx = load_import_context($pdo);
$v = validate_file($rows, $headers, 'Kadiyala_Arjun.csv', $ctx);
ok('counts', $v['counts'], ['total' => 5, 'ready' => 3, 'warning' => 0, 'duplicate' => 1, 'error' => 1]);

$batch = stage_batch($pdo, $v);
ok('nothing live after staging (FR-IMP-03)', (int)$pdo->query('SELECT COUNT(*) FROM scholarships')->fetchColumn(), 0);
ok('staging rows saved', (int)$pdo->query("SELECT COUNT(*) FROM import_staging_rows WHERE batch_id = $batch")->fetchColumn(), 4 + 0 + 1);

$res = commit_batch($pdo, $batch);
ok('inserted ready rows only', $res['inserted'], 3);
ok('live count', (int)$pdo->query('SELECT COUNT(*) FROM scholarships')->fetchColumn(), 3);
ok('providers merged ("The Kind Foundation" = "Kind Foundation")', (int)$pdo->query('SELECT COUNT(*) FROM providers')->fetchColumn(), 1);
ok('grades saved for Alpha', (int)$pdo->query("SELECT COUNT(*) FROM scholarship_grades g JOIN scholarships s ON s.id=g.scholarship_id WHERE s.name='Alpha'")->fetchColumn(), 4);
ok('categories + fields saved', (int)$pdo->query("SELECT COUNT(*) FROM scholarship_categories")->fetchColumn(), 6);
ok('fields saved', (int)$pdo->query("SELECT COUNT(*) FROM scholarship_fields")->fetchColumn(), 6);
ok('regional stored as National', $pdo->query("SELECT state_code FROM scholarships WHERE name='Midwest'")->fetchColumn(), 'US');
ok('national theme from student assignment', (int)$pdo->query("SELECT national_theme_id FROM scholarships WHERE name='Midwest'")->fetchColumn(), 24);
ok('contributing student recorded', $pdo->query("SELECT a.last_name FROM scholarships s JOIN student_assignments a ON a.id=s.contributing_student_id WHERE s.name='Alpha'")->fetchColumn(), 'Kadiyala');
ok('batch committed', $pdo->query("SELECT status FROM import_batches WHERE id=$batch")->fetchColumn(), 'committed');
ok('audit entries', (int)$pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn(), 2);

// The DB itself refuses a second ACTIVE row with the same URL key.
try {
    $pdo->exec("INSERT INTO scholarships (name, provider_id, official_url, url_key, state_code, scope) SELECT 'X', provider_id, official_url, url_key, 'MN', 'state_local' FROM scholarships LIMIT 1");
    ok('unique active URL enforced by DB', 'allowed', 'blocked');
} catch (PDOException $e) {
    ok('unique active URL enforced by DB', 'blocked', 'blocked');
}

// Second upload of the same file -> everything is a duplicate against the database.
$ctx2 = load_import_context($pdo);
$v2 = validate_file($rows, $headers, 'Kadiyala_Arjun_again.csv', $ctx2);
ok('re-upload: all valid rows are duplicates', $v2['counts']['duplicate'], 4);

// Rollback.
$removed = rollback_batch($pdo, $batch);
ok('rollback removed 3', $removed, 3);
ok('nothing live after rollback', (int)$pdo->query('SELECT COUNT(*) FROM scholarships')->fetchColumn(), 0);
ok('link rows cascaded', (int)$pdo->query('SELECT COUNT(*) FROM scholarship_grades')->fetchColumn(), 0);
ok('batch rolled_back', $pdo->query("SELECT status FROM import_batches WHERE id=$batch")->fetchColumn(), 'rolled_back');

// Commit a failing batch: transaction must leave nothing behind.
$ctx3 = load_import_context($pdo);
$v3 = validate_file([$mk(['Scholarship name' => 'Tx test', 'Official scholarship URL' => 'https://tx.org/1'])], $headers, 'Kadiyala_Arjun.csv', $ctx3);
$b3 = stage_batch($pdo, $v3);
$pdo->exec("UPDATE import_staging_rows SET cleaned_json = JSON_SET(cleaned_json, '$.state_code', 'ZZ') WHERE batch_id = $b3");   // violates the states FK
try { commit_batch($pdo, $b3); ok('bad commit throws', 'no', 'yes'); } catch (Throwable $e) { ok('bad commit throws', 'yes', 'yes'); }
ok('failed commit leaves nothing live', (int)$pdo->query('SELECT COUNT(*) FROM scholarships')->fetchColumn(), 0);
ok('failed commit leaves batch staged', $pdo->query("SELECT status FROM import_batches WHERE id=$b3")->fetchColumn(), 'staged');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
