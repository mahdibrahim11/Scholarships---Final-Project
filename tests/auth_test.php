<?php
/**
 * Iteration 7 unit + database tests (no web server): passwords, safe redirects, role rules, last Super-admin protection.
 *   php tests/auth_test.php
 * Creates and removes its own users (emails end in @unit.invalid). Needs config/config.php and the schema + seeds.
 */
define('CLI_TEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/users.php';
require_once __DIR__ . '/../includes/reference.php';

$pdo = db();
$pass = $fail = 0;
function ok(string $label, $actual, $expected): void {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; } else { $fail++; echo "FAIL: $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n"; }
}
function cleanup(PDO $pdo): void {
    $pdo->exec("DELETE FROM audit_log WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@unit.invalid') OR details LIKE '%unit.invalid%'");
    $pdo->exec("DELETE FROM login_attempts WHERE email_key LIKE '%@unit.invalid'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@unit.invalid'");
    $pdo->exec("DELETE FROM provider_types WHERE name LIKE 'UnitTest%'");
}
cleanup($pdo);

// ---- passwords (FR-AUTH-01/03)
ok('short password rejected', count(password_problems('abc1')) > 0, true);
ok('no digit rejected', count(password_problems('abcdefghijkl')) > 0, true);
ok('no letter rejected', count(password_problems('1234567890123')) > 0, true);
ok('listed common password rejected (any case)', count(password_problems('Password123')) > 0, true);
ok('too long rejected', count(password_problems(str_repeat('a1', 40))) > 0, true);
ok('email name inside password rejected', count(password_problems('maryjones2026x', 'maryjones@x.org')) > 0, true);
ok('good password accepted', password_problems('Correct-Horse-9'), []);
$t = generate_temp_password();
ok('temp password passes the rules', password_problems($t), []);
ok('temp passwords differ', $t !== generate_temp_password(), true);
$h = password_hash('Correct-Horse-9', PASSWORD_DEFAULT);
ok('hash is not the password', $h !== 'Correct-Horse-9' && str_starts_with($h, '$2y$'), true);
ok('password_verify works', password_verify('Correct-Horse-9', $h), true);

// ---- safe redirect targets (no open redirect)
ok('same-site path ok', safe_next('/admin/dashboard.php?x=1'), '/admin/dashboard.php?x=1');
ok('//evil.com rejected', safe_next('//evil.com/x'), null);
ok('https://evil.com rejected', safe_next('https://evil.com'), null);
ok('/\\evil rejected', safe_next('/\\evil.com'), null);
ok('javascript: rejected', safe_next('javascript:alert(1)'), null);
ok('empty rejected', safe_next(''), null);
ok('header injection rejected', safe_next("/a\r\nLocation: http://x"), null);

// ---- validation
[$c, $e] = validate_account_input($pdo, ['display_name' => ' Ann   Lee ', 'email' => ' ANN@Unit.invalid ', 'role' => 'student'], SELF_REGISTER_ROLES);
ok('name and email normalised', [$c['display_name'], $c['email'], $e], ['Ann Lee', 'ann@unit.invalid', []]);
[, $e] = validate_account_input($pdo, ['display_name' => 'Ann', 'email' => 'a@unit.invalid', 'role' => 'super_admin'], SELF_REGISTER_ROLES);
ok('self-registration cannot pick super_admin', isset($e['role']), true);
[, $e] = validate_account_input($pdo, ['display_name' => 'Ann', 'email' => 'a@unit.invalid', 'role' => 'administrator'], SELF_REGISTER_ROLES);
ok('self-registration cannot pick administrator', isset($e['role']), true);
[, $e] = validate_account_input($pdo, ['display_name' => 'Ann', 'email' => 'a@unit.invalid', 'role' => 'visitor'], ACCOUNT_ROLES);
ok('visitor is not an account role', isset($e['role']), true);
[, $e] = validate_account_input($pdo, ['display_name' => 'A', 'email' => 'bad', 'role' => 'student'], SELF_REGISTER_ROLES);
ok('bad name and email flagged', isset($e['display_name'], $e['email']), true);
[, $e] = validate_account_input($pdo, ['display_name' => 'A<b>', 'email' => 'a@unit.invalid', 'role' => 'student'], SELF_REGISTER_ROLES);
ok('angle brackets in name rejected', isset($e['display_name']), true);

// ---- users, roles, last Super-admin (FR-SA-01..03)
$mk = fn(string $name, string $role) => create_user($pdo, ['display_name' => $name, 'email' => strtolower($name) . '@unit.invalid', 'role' => $role], 'Correct-Horse-9', false);
$stu = $mk('stu', 'student'); $adm = $mk('adm', 'administrator'); $sup = $mk('sup', 'super_admin');
ok('role stored', auth_load_user($stu)['role'], 'student');
[, $e] = validate_account_input($pdo, ['display_name' => 'Other', 'email' => 'STU@unit.invalid', 'role' => 'student'], SELF_REGISTER_ROLES);
ok('duplicate email (any case) rejected', isset($e['email']), true);

$seed = $pdo->query("SELECT is_active, must_change_password FROM users WHERE email = 'superadmin@pathways.local'")->fetch();   // read before the test switches other Super-admins off
// Count the other active Super-admins (the seeded one exists), so make the test independent of them.
$others = $pdo->prepare("SELECT id FROM users WHERE role_id = 4 AND is_active = 1 AND id <> ?");
$others->execute([$sup]);
$otherIds = array_map('intval', $others->fetchAll(PDO::FETCH_COLUMN));
$pdo->exec('UPDATE users SET is_active = 0 WHERE id IN (' . implode(',', array_merge([0], $otherIds)) . ')');   // temporarily: $sup is the only active one

ok('last Super-admin cannot be deactivated by another admin', str_contains((string)update_user_access($pdo, $adm, $sup, null, false), 'last active Super-admin'), true);
ok('last Super-admin cannot be demoted', str_contains((string)update_user_access($pdo, $adm, $sup, ROLE_ADMIN, null), 'last active Super-admin'), true);
ok('still a Super-admin', auth_load_user($sup)['role'] . '/' . auth_load_user($sup)['is_active'], 'super_admin/1');
ok('cannot change own role', str_contains((string)update_user_access($pdo, $sup, $sup, ROLE_STUDENT, null), 'your own role'), true);
ok('cannot deactivate yourself', str_contains((string)update_user_access($pdo, $sup, $sup, null, false), 'your own role'), true);

$sup2 = $mk('sup2', 'super_admin');
ok('demoting one of two Super-admins works', update_user_access($pdo, $sup2, $sup, ROLE_ADMIN, null), null);
ok('role changed', auth_load_user($sup)['role'], 'administrator');
ok('now the other is the last one', str_contains((string)update_user_access($pdo, $adm, $sup2, null, false), 'last active Super-admin'), true);
ok('promote student to administrator', update_user_access($pdo, $sup2, $stu, ROLE_ADMIN, null), null);
ok('deactivate student', update_user_access($pdo, $sup2, $stu, null, false), null);
ok('deactivated flag set', (int)auth_load_user($stu)['is_active'], 0);
ok('reactivate', update_user_access($pdo, $sup2, $stu, null, true), null);
ok('invalid role refused', update_user_access($pdo, $sup2, $stu, 'visitor', null) !== null, true);
ok('missing user refused', update_user_access($pdo, $sup2, 999999, ROLE_STUDENT, null) !== null, true);

$before = (int)auth_load_user($stu)['session_version'];
set_user_password($pdo, $stu, 'Another-Pass-77', true);
$after = auth_load_user($stu);
ok('password reset bumps session_version', (int)$after['session_version'], $before + 1);
ok('password reset forces change', (int)$after['must_change_password'], 1);
$ph = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?'); $ph->execute([$stu]);
ok('new password verifies', password_verify('Another-Pass-77', (string)$ph->fetchColumn()), true);

// ---- sign-in throttling (FR-AUTH-02)
$key = 'throttle@unit.invalid';
for ($i = 0; $i < LOGIN_MAX_FAILS_PER_EMAIL - 1; $i++) { login_record($pdo, $key, '10.9.9.9', false); }
ok('4 failures: not throttled', login_throttled($pdo, $key, '10.9.9.9'), false);
login_record($pdo, $key, '10.9.9.9', false);
ok('5 failures: throttled', login_throttled($pdo, $key, '10.9.9.9'), true);
ok('other email not throttled', login_throttled($pdo, 'other@unit.invalid', '10.1.1.1'), false);
login_record($pdo, $key, '10.9.9.9', true);
ok('a good sign-in clears the count', login_throttled($pdo, $key, '10.9.9.9'), false);

[$row, $msg] = authenticate($pdo, 'adm@unit.invalid', 'Correct-Horse-9');
ok('authenticate accepts the right password', (int)($row['id'] ?? 0), $adm);
[$row, $msgBad] = authenticate($pdo, 'adm@unit.invalid', 'wrong-password-1');
[$row2, $msgNone] = authenticate($pdo, 'nobody@unit.invalid', 'wrong-password-1');
ok('wrong password: no user', $row, null);
ok('wrong password and unknown email give the same message', $msgBad === $msgNone && $msgBad !== '', true);
$pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$adm]);
[$row] = authenticate($pdo, 'adm@unit.invalid', 'Correct-Horse-9');
ok('inactive account cannot sign in', $row, null);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute(['!', $adm]);
$pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$adm]);
[$row] = authenticate($pdo, 'adm@unit.invalid', '!');
ok('the unusable "!" hash never matches', $row, null);

// ---- placeholder user and seeded Super-admin
ok('seeded Super-admin exists and is active', $seed ? (int)$seed['is_active'] : null, 1);
ok('no active placeholder development user', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email = 'dev-placeholder@localhost.invalid' AND is_active = 1")->fetchColumn(), 0);
ok('nothing still points at an inactive placeholder', (int)$pdo->query("SELECT COUNT(*) FROM audit_log l JOIN users u ON u.id = l.user_id WHERE u.email = 'dev-placeholder@localhost.invalid'")->fetchColumn(), 0);

// ---- reference data (FR-SA-04)
$cfg = reference_tables()['provider_types'];
ok('add a provider type', reference_save($pdo, 'provider_types', $cfg, 'add', '', 'UnitTest Type'), null);
$pid = (string)$pdo->query("SELECT id FROM provider_types WHERE name = 'UnitTest Type'")->fetchColumn();
ok('duplicate name refused', reference_save($pdo, 'provider_types', $cfg, 'add', '', 'UnitTest Type') !== null, true);
ok('empty name refused', reference_save($pdo, 'provider_types', $cfg, 'add', '', '   ') !== null, true);
ok('rename', reference_save($pdo, 'provider_types', $cfg, 'rename', $pid, 'UnitTest Renamed'), null);
ok('delete unused', reference_save($pdo, 'provider_types', $cfg, 'delete', $pid, ''), null);
$sc = reference_tables()['states'];
ok('US cannot be deleted', reference_save($pdo, 'states', $sc, 'delete', 'US', '') !== null, true);
ok('US cannot be renamed', reference_save($pdo, 'states', $sc, 'rename', 'US', 'Nowhere') !== null, true);
ok('state code must be two letters', reference_save($pdo, 'states', $sc, 'add', 'ABC', 'Test') !== null, true);
$fc = reference_tables()['fields_of_study'];
$anyId = (string)$pdo->query("SELECT id FROM fields_of_study WHERE name = 'Any / undecided'")->fetchColumn();
ok('"Any / undecided" is protected', reference_save($pdo, 'fields_of_study', $fc, 'delete', $anyId, '') !== null, true);
$tc = reference_tables()['national_themes'];
ok('theme number out of range refused', reference_save($pdo, 'national_themes', $tc, 'add', '999', 'Too big') !== null, true);
ok('a used theme cannot be deleted', str_contains((string)reference_save($pdo, 'national_themes', $tc, 'delete', '1', ''), 'in use'), true);

// ---- audit
audit_log($pdo, 'unit.test', 'user', $stu, ['note' => 'unit.invalid']);
ok('audit row written for the acting user', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'unit.test' AND user_id IS NOT NULL")->fetchColumn(), 1);
audit_log($pdo, 'unit.test2', null, null, ['note' => 'unit.invalid'], null);
ok('audit row with no user', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'unit.test2' AND user_id IS NULL")->fetchColumn(), 1);
$pdo->exec("DELETE FROM audit_log WHERE action LIKE 'unit.test%'");

// ---- every guarded page: static check that each admin page asks require_role()
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/admin', FilesystemIterator::SKIP_DOTS));
$missing = [];
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') { continue; }
    $src = file_get_contents($f->getPathname());
    if (!preg_match('/require_role\s*\(/', $src)) { $missing[] = substr($f->getPathname(), strlen($root) + 1); }
}
ok('every file under admin/ calls require_role()', $missing, []);
foreach (['profile.php', 'change_password.php'] as $p) {
    ok("$p requires a signed-in user", (bool)preg_match('/require_(login|role)\s*\(/', file_get_contents("$root/$p")), true);
}
$src = file_get_contents($root . '/includes/header.php');
ok('development banner removed from the header', str_contains($src, 'dev-banner') || str_contains($src, 'Development mode'), false);
ok('is_dev_mode() is false', is_dev_mode(), false);

cleanup($pdo);
$pdo->exec('UPDATE users SET is_active = 1 WHERE email = \'superadmin@pathways.local\'');
foreach ($otherIds as $oid) { $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$oid]); }
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
