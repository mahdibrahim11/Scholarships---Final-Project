<?php
/**
 * Iteration 4 data-layer test (needs config/config.php + schema/seeds). EMPTIES scholarship tables: LOCAL database only.
 *   php tests/scholarship_admin_test.php
 */
define('CLI_TEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/scholarships.php';

$pdo = db();
$pass = $fail = 0;
function ok(string $label, $actual, $expected): void {
    global $pass, $fail;
    if ($actual == $expected) { $pass++; } else { $fail++; echo "FAIL: $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n"; }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['import_staging_rows', 'scholarship_grades', 'scholarship_categories', 'scholarship_fields', 'scholarships', 'import_batches', 'providers', 'audit_log'] as $t) { $pdo->exec("TRUNCATE TABLE $t"); }
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$ref = scholarship_ref($pdo);

$good = ['name' => 'Test Award', 'provider_name' => 'Acme Foundation', 'official_url' => 'https://acme.org/a', 'state_code' => 'US',
         'grades' => ['11', '12'], 'award_min' => '$1,000', 'award_max' => '2500', 'renewable' => 'Yes'];

// validation
[$e, $c] = validate_scholarship_input([], $ref, $pdo, null);
foreach (['name', 'provider_name', 'official_url', 'state_code', 'grades'] as $k) { ok("empty form flags $k", isset($e[$k]), true); }
[$e] = validate_scholarship_input($good, $ref, $pdo, null);
ok('good form has no errors', $e, []);
[$e] = validate_scholarship_input(array_merge($good, ['award_min' => '9000']), $ref, $pdo, null);
ok('min above max', isset($e['award_max']), true);
[$e] = validate_scholarship_input(array_merge($good, ['official_url' => 'not a url']), $ref, $pdo, null);
ok('bad url', isset($e['official_url']), true);
[$e] = validate_scholarship_input(array_merge($good, ['grades' => ['3']]), $ref, $pdo, null);
ok('grade out of range', isset($e['grades']), true);
[$e] = validate_scholarship_input(array_merge($good, ['min_gpa' => '9']), $ref, $pdo, null);
ok('gpa too high', isset($e['min_gpa']), true);

// create + audit
[, $clean] = validate_scholarship_input($good, $ref, $pdo, null);
$id = save_scholarship($pdo, null, $clean);
$s = load_scholarship($pdo, $id);
ok('created', $s['name'], 'Test Award');
ok('grades saved', $s['grades'], [11, 12]);
ok('national scope', $s['scope'], 'national');
ok('create audited', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='scholarship.create' AND entity_id=$id")->fetchColumn(), 1);

// duplicate URL among active rows
[$e] = validate_scholarship_input(array_merge($good, ['name' => 'Other']), $ref, $pdo, null);
ok('duplicate url refused', isset($e['official_url']), true);
[$e] = validate_scholarship_input($good, $ref, $pdo, $id);
ok('own url allowed when editing', $e, []);

// edit audit with diff
[, $clean] = validate_scholarship_input(array_merge($good, ['name' => 'Renamed']), $ref, $pdo, $id);
save_scholarship($pdo, $id, $clean);
ok('edit audited', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='scholarship.edit' AND entity_id=$id")->fetchColumn(), 1);

// bulk actions
$r = scholarship_bulk_action($pdo, 'verify', [$id]);
ok('verify done', $r['done'], 1);
ok('verification date set', load_scholarship($pdo, $id)['verification_date'], date('Y-m-d'));
$r = scholarship_bulk_action($pdo, 'archive', [$id]);
ok('archive done', $r['done'], 1);
ok('status archived', load_scholarship($pdo, $id)['status'], 'archived');
// archived row frees its URL
[$e] = validate_scholarship_input($good, $ref, $pdo, null);
ok('url free after archive', $e, []);
$id2 = save_scholarship($pdo, null, validate_scholarship_input($good, $ref, $pdo, null)[1]);
$r = scholarship_bulk_action($pdo, 'restore', [$id]);
ok('restore blocked by url conflict', $r['done'], 0);
ok('restore explains why', count($r['skipped']), 1);

// providers
$a = find_or_create_provider($pdo, 'Hope Fund', null);
ok('provider reuse', find_or_create_provider($pdo, '  hope  fund ', null), $a);
$b = find_or_create_provider($pdo, 'Hope Fund Inc.', null);
$pairs = provider_duplicate_pairs(list_providers($pdo));
ok('duplicate pair found', count($pairs) >= 1, true);
$pdo->prepare('UPDATE scholarships SET provider_id = ? WHERE id = ?')->execute([$b, $id2]);
ok('merge moves rows', merge_providers($pdo, $b, $a), 1);
ok('merged provider gone', (int)$pdo->query("SELECT COUNT(*) FROM providers WHERE id=$b")->fetchColumn(), 0);
ok('scholarship now under kept provider', (int)$pdo->query("SELECT provider_id FROM scholarships WHERE id=$id2")->fetchColumn(), $a);
ok('merge audited', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='provider.merge'")->fetchColumn(), 1);

echo "\nPassed $pass, failed $fail\n";
exit($fail ? 1 : 0);
