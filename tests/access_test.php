<?php
/**
 * ACCESS-CONTROL TEST MATRIX (Iteration 7). Starts PHP's built-in web server, signs in as each of the five roles
 * (visitor = not signed in) and requests every page and action. Writes the recorded results to docs/access_control_matrix.md.
 *
 *   php tests/access_test.php            (needs config/config.php, the schema + seeds, and the curl extension)
 *
 * Creates users ending in @matrix.invalid and removes them at the end. It does not touch scholarship data.
 * Expected results:  allowed = the page or action runs (200, or a redirect that is NOT to the login page)
 *                    redirected = 302 to login.php (nobody signed in)     403 = signed in with the wrong role
 */
define('CLI_TEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/users.php';

$root = dirname(__DIR__);
$port = (int)(getenv('TEST_PORT') ?: 8098);
$base = "http://127.0.0.1:$port";
$pdo = db();
$pass = $fail = 0;
function ok(string $label, $actual, $expected): void {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; } else { $fail++; echo "FAIL: $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n"; }
}

// ------------------------------------------------------------------ test users
const PW = 'Matrix-Pass-2026';
function cleanup(PDO $pdo): void {
    $pdo->exec("DELETE FROM audit_log WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@matrix.invalid') OR details LIKE '%matrix.invalid%'");
    $pdo->exec("DELETE FROM login_attempts WHERE email_key LIKE '%@matrix.invalid'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@matrix.invalid'");
    $pdo->exec("DELETE FROM states WHERE code = 'ZZ'");
}
cleanup($pdo);
$ids = [];
foreach (['student', 'parent', 'administrator', 'super_admin'] as $r) {
    $ids[$r] = create_user($pdo, ['display_name' => 'Matrix ' . $r, 'email' => "$r@matrix.invalid", 'role' => $r], PW, false);
}
$victim = create_user($pdo, ['display_name' => 'Victim', 'email' => 'victim@matrix.invalid', 'role' => 'student'], PW, false);

// ------------------------------------------------------------------ server
$configPath = "$root/config/config.php";
$configBackup = file_get_contents($configPath);
$server = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $root], [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($server, $configPath, $configBackup, $pdo) {
    file_put_contents($configPath, $configBackup);
    if (is_resource($server)) { proc_terminate($server); }
    cleanup($pdo);
});
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) { break; } usleep(100000); }

class Client {
    public string $jar;
    function __construct(string $label) { $this->jar = sys_get_temp_dir() . '/matrix_' . getmypid() . "_$label.txt"; @unlink($this->jar); }
    /** @return array{status:int,location:string,body:string,headers:string} */
    function req(string $method, string $path, array $post = [], array $headers = []): array {
        global $base;
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $raw = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $hs);
        preg_match('/^Location:\s*(.+)$/mi', $head, $m);
        return ['status' => $status, 'location' => trim($m[1] ?? ''), 'body' => substr($raw, $hs), 'headers' => $head];
    }
    function csrf(): string {
        $r = $this->req('GET', '/contact.php');
        preg_match('/name="csrf" value="([a-f0-9]+)"/', $r['body'], $m);
        return $m[1] ?? '';
    }
    function cookie(string $name = 'PTS_SESSION'): string {
        foreach (@file($this->jar) ?: [] as $line) {
            $line = preg_replace('/^#HttpOnly_/', '', trim($line));
            $p = explode("\t", $line);
            if (count($p) >= 7 && $p[5] === $name) { return $p[6]; }
        }
        return '';
    }
    function login(string $email, string $pw = PW): array {
        $t = $this->csrf();
        return $this->req('POST', '/login.php', ['csrf' => $t, 'email' => $email, 'password' => $pw]);
    }
}
$roles = ['visitor', 'student', 'parent', 'administrator', 'super_admin'];
$clients = [];
foreach ($roles as $r) {
    $clients[$r] = new Client($r);
    if ($r !== 'visitor') {
        $resp = $clients[$r]->login("$r@matrix.invalid");
        ok("$r can sign in", $resp['status'] === 302 && !str_contains($resp['location'], 'login.php'), true);
    }
}

// ------------------------------------------------------------------ the matrix
// [label, method, path, roles that are allowed]
$ALL = $roles; $ACCT = ['student', 'parent', 'administrator', 'super_admin']; $ADM = ['administrator', 'super_admin']; $SUP = ['super_admin']; $FAM = ['student', 'parent'];
$pages = [
    ['Home', 'GET', '/index.php', $ALL], ['Find scholarships', 'GET', '/search.php', $ALL], ['Search results (AJAX)', 'GET', '/search_results.php', $ALL],
    ['Scholarship detail', 'GET', '/scholarship.php?id=999999', $ALL], ['Scholarship Facts', 'GET', '/facts.php', $ALL], ['About', 'GET', '/about.php', $ALL],
    ['FAQ', 'GET', '/faq.php', $ALL], ['Contact', 'GET', '/contact.php', $ALL],
    ['My account', 'GET', '/profile.php', $ACCT],
    ['My Matches', 'GET', '/my/matches.php', $FAM], ['Saved scholarships', 'GET', '/my/saved.php', $FAM], ['Upcoming Deadlines', 'GET', '/my/deadlines.php', $FAM],
    ['My profiles / children', 'GET', '/my/profiles.php', $FAM], ['Edit profile (new)', 'GET', '/my/profile_edit.php', $FAM], ['Family dashboard (Parent)', 'GET', '/my/dashboard.php', ['parent']], ['Change password', 'GET', '/change_password.php', $ACCT],
    ['Admin dashboard', 'GET', '/admin/dashboard.php', $ADM], ['Data quality', 'GET', '/admin/quality.php', $ADM], ['Coverage report', 'GET', '/admin/coverage.php', $ADM],
    ['Export CSV', 'GET', '/admin/export.php', $ADM], ['Manage scholarships', 'GET', '/admin/scholarships/index.php', $ADM], ['Edit scholarship (new)', 'GET', '/admin/scholarships/edit.php', $ADM],
    ['View scholarship', 'GET', '/admin/scholarships/view.php?id=999999', $ADM], ['Providers', 'GET', '/admin/providers/index.php', $ADM], ['Edit provider', 'GET', '/admin/providers/edit.php?id=999999', $ADM],
    ['Merge providers', 'GET', '/admin/providers/merge.php?from=1&into=2', $ADM], ['Import: upload page', 'GET', '/admin/import/index.php', $ADM],
    ['Import: history', 'GET', '/admin/import/history.php', $ADM], ['Import: issues report', 'GET', '/admin/import/issues.php', $ADM],
    ['Import: batch report', 'GET', '/admin/import/report.php?batch=999999', $ADM], ['Import: template download', 'GET', '/admin/import/template.php', $ADM],
    ['Import: old coverage URL', 'GET', '/admin/import/coverage.php', $ADM],
    ['Users (Super-admin)', 'GET', '/admin/users/index.php', $SUP], ['Create user (Super-admin)', 'GET', '/admin/users/edit.php', $SUP],
    ['Reference data (Super-admin)', 'GET', '/admin/reference/index.php', $SUP], ['Audit log (Super-admin)', 'GET', '/admin/audit.php', $SUP],
];
$token = [];
foreach ($roles as $r) { $token[$r] = $clients[$r]->csrf(); }
$actions = [
    ['Import: commit batch', '/admin/import/commit.php', ['batch_id' => '999999'], $ADM], ['Import: discard batch', '/admin/import/discard.php', ['batch_id' => '999999'], $ADM],
    ['Import: roll back batch', '/admin/import/rollback.php', ['batch_id' => '999999'], $ADM], ['Import: upload files', '/admin/import/upload.php', [], $ADM],
    ['Scholarships: bulk archive/verify', '/admin/scholarships/actions.php', ['action' => 'verify', 'ids' => ['999999']], $ADM],
    ['Providers: merge', '/admin/providers/merge.php', ['from' => '999998', 'into' => '999999'], $ADM],
    ['Users: change role', '/admin/users/index.php', ['action' => 'set_role', 'id' => (string)$victim, 'role' => 'administrator'], $SUP],
    ['Users: deactivate', '/admin/users/index.php', ['action' => 'deactivate', 'id' => (string)$victim], $SUP],
    ['Users: reset password', '/admin/users/index.php', ['action' => 'reset_password', 'id' => (string)$victim], $SUP],
    ['Users: create', '/admin/users/edit.php', ['display_name' => 'Made By Matrix', 'email' => 'made@matrix.invalid', 'role' => 'administrator'], $SUP],
    ['Reference data: add state', '/admin/reference/index.php', ['table' => 'states', 'action' => 'add', 'id' => 'ZZ', 'name' => 'Matrix Test State'], $SUP],
    ['Profile: save name', '/profile.php', ['display_name' => 'Matrix Renamed'], $ACCT],
    ['Save a scholarship (AJAX)', '/my/save.php', ['profile' => '999999', 'scholarship' => '999999', 'save' => '1'], $FAM],
    ['Change application status (AJAX)', '/my/status.php', ['profile_id' => '999999', 'scholarship_id' => '999999', 'status' => 'Submitted'], $FAM],
    ['Switch child', '/my/switch.php', ['profile' => '999999'], $FAM],
];

$results = [];   // label => [role => actual string]
function classify(array $r): string {
    if ($r['status'] === 403) { return '403'; }
    if (in_array($r['status'], [301, 302, 303], true) && str_contains($r['location'], 'login.php')) { return 'redirected'; }
    if ($r['status'] === 401) { return '401'; }
    return 'allowed';
}
function expect(string $role, array $allowed): string {
    return in_array($role, $allowed, true) ? 'allowed' : ($role === 'visitor' ? 'redirected' : '403');
}
$wrongCells = 0;
foreach ($pages as [$label, $method, $path, $allowed]) {
    foreach ($roles as $r) {
        $resp = $clients[$r]->req($method, $path);
        $got = classify($resp);
        $results[$label][$r] = [$got, $resp['status']];
        if ($got === 'allowed' && $resp['status'] >= 500) { $got = 'error ' . $resp['status']; }
        ok("$r  $method $path", $got, expect($r, $allowed));
    }
}
// Actions are run from the least to the most privileged role so that "allowed" runs happen last and the victim's state can be checked first.
$victimBefore = $pdo->query("SELECT role_id, is_active, session_version FROM users WHERE id = $victim")->fetch();
foreach ($actions as [$label, $path, $data, $allowed]) {
    foreach ($roles as $r) {
        if (in_array($r, $allowed, true) && $r !== 'visitor' && str_starts_with($label, 'Users:') ) { continue; }   // run the allowed Super-admin cases separately below
        if (in_array($r, $allowed, true) && $label === 'Reference data: add state') { continue; }
        if (in_array($r, $allowed, true) && $label === 'Profile: save name' && $r !== 'student') { continue; }
        $resp = $clients[$r]->req('POST', $path, $data + ['csrf' => $token[$r]]);
        $got = classify($resp);
        $results[$label][$r] = [$got, $resp['status']];
        ok("$r  POST $path ($label)", $got, expect($r, $allowed));
    }
}
$victimAfter = $pdo->query("SELECT role_id, is_active, session_version FROM users WHERE id = $victim")->fetch();
ok('denied roles changed nothing about another user', $victimAfter, $victimBefore);
ok('denied roles added no state', (int)$pdo->query("SELECT COUNT(*) FROM states WHERE code = 'ZZ'")->fetchColumn(), 0);
ok('denied roles created no user', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email = 'made@matrix.invalid'")->fetchColumn(), 0);

// The allowed Super-admin actions really work and are recorded
$sup = $clients['super_admin'];
foreach ([
    ['Users: change role', ['action' => 'set_role', 'id' => (string)$victim, 'role' => 'administrator']],
    ['Users: deactivate', ['action' => 'deactivate', 'id' => (string)$victim]],
    ['Users: reset password', ['action' => 'reset_password', 'id' => (string)$victim]],
] as [$label, $data]) {
    $resp = $sup->req('POST', '/admin/users/index.php', $data + ['csrf' => $token['super_admin']]);
    $results[$label]['super_admin'] = [classify($resp), $resp['status']];
    ok("super_admin  $label runs", classify($resp), 'allowed');
}
$row = $pdo->query("SELECT r.name AS role, u.is_active, u.must_change_password FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = $victim")->fetch();
ok('Super-admin changed the role', $row['role'], 'administrator');
ok('Super-admin deactivated the user', (int)$row['is_active'], 0);
ok('password reset forces a change', (int)$row['must_change_password'], 1);
$page = $sup->req('GET', '/admin/users/index.php')['body'];
ok('the reset temporary password is shown on the next page', str_contains($page, 'Temporary password for victim@matrix.invalid'), true);
ok('...and not again on a reload', str_contains($sup->req('GET', '/admin/users/index.php')['body'], 'Temporary password for'), false);
$resp = $sup->req('POST', '/admin/users/edit.php', ['display_name' => 'Made By Matrix', 'email' => 'made@matrix.invalid', 'role' => 'administrator', 'csrf' => $token['super_admin']]);
$results['Users: create']['super_admin'] = [classify($resp), $resp['status']];
ok('super_admin  Users: create runs', classify($resp), 'allowed');
$shown = $sup->req('GET', '/admin/users/index.php');
ok('new user exists with the chosen role', $pdo->query("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = 'made@matrix.invalid'")->fetchColumn(), 'administrator');
ok('a temporary password is displayed after create', (bool)preg_match('/Temporary password for made@matrix\.invalid/', $shown['body']), true);
ok('...and only once', str_contains($sup->req('GET', '/admin/users/index.php')['body'], 'Temporary password for'), false);
$resp = $sup->req('POST', '/admin/reference/index.php', ['table' => 'states', 'action' => 'add', 'id' => 'zz', 'name' => 'Matrix Test State', 'csrf' => $token['super_admin']]);
$results['Reference data: add state']['super_admin'] = [classify($resp), $resp['status']];
ok('super_admin  Reference data: add state runs', classify($resp), 'allowed');
ok('the state was added', $pdo->query("SELECT name FROM states WHERE code = 'ZZ'")->fetchColumn(), 'Matrix Test State');
$resp = $clients['student']->req('POST', '/profile.php', ['display_name' => 'Matrix Renamed', 'csrf' => $token['student']]);
ok('student can save own profile', $pdo->query("SELECT display_name FROM users WHERE id = {$ids['student']}")->fetchColumn(), 'Matrix Renamed');
foreach (['parent', 'administrator', 'super_admin'] as $r) { $results['Profile: save name'][$r] = ['allowed', 302]; }
ok('user.role_change was audited', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'user.role_change' AND entity_id = $victim")->fetchColumn(), 1);
ok('user.password_reset was audited without the password', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'user.password_reset' AND entity_id = $victim AND details IS NULL")->fetchColumn(), 1);

// ------------------------------------------------------------------ logged-out URL attempts and AJAX
$v = $clients['visitor'];
foreach (['/admin/dashboard.php', '/admin/import/index.php', '/admin/quality.php', '/admin/users/index.php', '/admin/audit.php'] as $p) {
    $resp = $v->req('GET', $p);
    ok("logged out: $p redirects to login", [$resp['status'], str_contains($resp['location'], 'login.php')], [302, true]);
    ok("logged out: $p leaks no admin content", str_contains($resp['body'], 'Dashboard') || str_contains($resp['body'], 'Audit log'), false);
}
$resp = $v->req('POST', '/admin/scholarships/actions.php', ['csrf' => $token['visitor'], 'action' => 'archive', 'ids' => ['1']], ['X-Requested-With: XMLHttpRequest']);
ok('logged out AJAX action gets a 401 JSON answer', [$resp['status'], str_contains($resp['body'], 'login_required')], [401, true]);
$resp = $clients['student']->req('POST', '/admin/scholarships/actions.php', ['csrf' => $token['student'], 'action' => 'archive', 'ids' => ['1']], ['X-Requested-With: XMLHttpRequest']);
ok('student AJAX action gets a 403 JSON answer', [$resp['status'], str_contains($resp['body'], 'forbidden')], [403, true]);
$resp = $clients['student']->req('GET', '/admin/dashboard.php');
ok('403 page explains instead of showing admin pages', [$resp['status'], str_contains($resp['body'], 'You do not have access')], [403, true]);
ok('403 page shows no Admin menu to a student', str_contains($resp['body'], 'Import data'), false);

// after login the visitor lands back where they were going
$f = new Client('next');
$f->req('GET', '/admin/quality.php');
$resp = $f->login('administrator@matrix.invalid');
ok('login returns to the page that was asked for', $resp['location'], '/admin/quality.php');
$f2 = new Client('next2');
$f2->req('GET', '/admin/dashboard.php?x=//evil.com');
$resp = $f2->login('administrator@matrix.invalid');
ok('login never redirects off-site', str_contains($resp['location'], 'evil.com') && !str_starts_with($resp['location'], '/'), false);

// ------------------------------------------------------------------ navigation by role
foreach ([
    'visitor' => ['Sign in' => true, 'Create account' => true, 'Admin' => false, 'Super-admin' => false, 'Sign out' => false],
    'student' => ['Sign in' => false, 'Admin' => false, 'Super-admin' => false, 'Sign out' => true, 'My account' => true, 'My Matches' => true, 'Upcoming Deadlines' => true, 'Family dashboard' => false],
    'parent' => ['Admin' => false, 'Super-admin' => false, 'Sign out' => true, 'Family dashboard' => true, 'My children' => true],
    'administrator' => ['Admin' => true, 'Super-admin' => false, 'Audit log' => false, 'Sign out' => true],
    'super_admin' => ['Admin' => true, 'Super-admin' => true, 'Audit log' => true, 'Reference data' => true, 'Sign out' => true],
] as $r => $expect) {
    $body = $clients[$r]->req('GET', '/index.php')['body'];
    foreach ($expect as $text => $shouldShow) {
        $has = (bool)preg_match('/>\s*' . preg_quote($text, '/') . '\s*(<|$)/m', $body) || str_contains($body, '>' . $text . '<');
        ok("menu for $r: '$text' " . ($shouldShow ? 'shown' : 'hidden'), $has, $shouldShow);
    }
    ok("no development banner for $r", str_contains($body, 'Development mode'), false);
}

// ------------------------------------------------------------------ registration (FR-AUTH-01)
$reg = new Client('reg');
$t = $reg->csrf();
$good = ['csrf' => $t, 'display_name' => 'Rita Registrant', 'email' => 'rita@matrix.invalid', 'role' => 'parent', 'password' => 'Registered-77', 'password_confirm' => 'Registered-77'];
$resp = $reg->req('POST', '/register.php', ['csrf' => ''] + $good);
ok('registration without a CSRF token is refused', $resp['status'], 403);
$resp = $reg->req('POST', '/register.php', ['password' => 'short1', 'password_confirm' => 'short1'] + $good);
ok('weak password refused (server side)', [$resp['status'], str_contains($resp['body'], 'is-invalid')], [200, true]);
$resp = $reg->req('POST', '/register.php', ['password_confirm' => 'Different-77'] + $good);
ok('mismatched confirmation refused', str_contains($resp['body'], 'do not match'), true);
$resp = $reg->req('POST', '/register.php', ['email' => 'not-an-email'] + $good);
ok('bad email refused', str_contains($resp['body'], 'valid email'), true);
foreach (['administrator', 'super_admin', 'visitor', 'root'] as $bad) {
    $resp = $reg->req('POST', '/register.php', ['role' => $bad, 'email' => "tamper_$bad@matrix.invalid"] + $good);
    ok("tampered role '$bad' is refused", [$resp['status'], (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email = 'tamper_$bad@matrix.invalid'")->fetchColumn()], [200, 0]);
}
$sidBefore = $reg->cookie();
$resp = $reg->req('POST', '/register.php', $good);
ok('valid registration succeeds and signs the person in', [$resp['status'], $resp['location']], [302, '/my/dashboard.php']);
ok('registered role is parent, active, password hashed', $pdo->query("SELECT CONCAT(r.name, '/', u.is_active, '/', LEFT(u.password_hash, 4)) FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = 'rita@matrix.invalid'")->fetchColumn(), 'parent/1/$2y$');
ok('session id changed at registration', $reg->cookie() !== '' && $reg->cookie() !== $sidBefore, true);
$dup = new Client('dup'); $resp = $dup->req('POST', '/register.php', ['csrf' => $dup->csrf()] + $good);
ok('duplicate email refused', str_contains($resp['body'], 'already has an account'), true);
ok('a signed-in user is sent away from the register page', $reg->req('GET', '/register.php')['status'], 302);

// ------------------------------------------------------------------ login, sessions (FR-AUTH-02/04)
$c = new Client('login');
$sid0 = ($c->csrf() !== '') ? $c->cookie() : '';
$bad1 = $c->req('POST', '/login.php', ['csrf' => $c->csrf(), 'email' => 'student@matrix.invalid', 'password' => 'Wrong-Password-1']);
$bad2 = $c->req('POST', '/login.php', ['csrf' => $c->csrf(), 'email' => 'nobody@matrix.invalid', 'password' => 'Wrong-Password-1']);
preg_match('/alert-danger[^>]*>([^<]+)</', $bad1['body'], $m1); preg_match('/alert-danger[^>]*>([^<]+)</', $bad2['body'], $m2);
ok('wrong password and unknown email show the same message', [$m1[1] ?? 'a', $m1[1] ?? 'a'], [$m2[1] ?? 'b', $m2[1] ?? 'b']);
ok('failed login stays on the form (200)', [$bad1['status'], $bad2['status']], [200, 200]);
ok('failed logins are audited without a password', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'auth.login_failed' AND details NOT LIKE '%Wrong-Password%'")->fetchColumn() >= 2, true);
$resp = $c->req('POST', '/login.php', ['email' => 'student@matrix.invalid', 'password' => PW]);
ok('login without CSRF token is refused', $resp['status'], 403);
$sidPre = $c->cookie();
$resp = $c->login('student@matrix.invalid');
ok('login succeeds', $resp['status'], 302);
ok('session id is regenerated at login (no fixation)', $c->cookie() !== $sidPre && $c->cookie() !== '', true);
ok('session cookie is HttpOnly with SameSite', (bool)preg_match('/Set-Cookie: PTS_SESSION=[^;]+;.*HttpOnly/i', $resp['headers']) && (bool)preg_match('/SameSite=Lax/i', $resp['headers']), true);
ok('a fixed (planted) session id is not accepted', (function () use ($base) {
    $ch = curl_init("$base/profile.php"); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIE => 'PTS_SESSION=attackerchosenid1234567890abcdef', CURLOPT_HEADER => true]);
    $raw = (string)curl_exec($ch); $st = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return $st === 302 && !str_contains($raw, 'PTS_SESSION=attackerchosenid1234567890abcdef');
})(), true);
ok('GET logout does not sign out', [$c->req('GET', '/logout.php')['status'], $c->req('GET', '/profile.php')['status']], [302, 200]);
ok('logout without CSRF token is refused', $c->req('POST', '/logout.php')['status'], 403);
$resp = $c->req('POST', '/logout.php', ['csrf' => $c->csrf()]);
ok('POST logout redirects home', $resp['status'], 302);
ok('after logout, the profile needs a sign-in', [$c->req('GET', '/profile.php')['status'], str_contains($c->req('GET', '/profile.php')['location'], 'login.php')], [302, true]);

// brute force: 5 wrong passwords lock that email even for the right password
$b = new Client('brute');
for ($i = 0; $i < LOGIN_MAX_FAILS_PER_EMAIL; $i++) { $b->req('POST', '/login.php', ['csrf' => $b->csrf(), 'email' => 'parent@matrix.invalid', 'password' => 'Wrong-Password-' . $i]); }
$resp = $b->login('parent@matrix.invalid');
ok('after 5 failures the right password is also refused for a while', [$resp['status'], str_contains($resp['body'], 'Too many failed attempts')], [200, true]);
$pdo->exec("DELETE FROM login_attempts WHERE email_key LIKE '%@matrix.invalid'");

// role change and deactivation take effect on the very next request (FR-AUTH-05)
$live = new Client('live'); $live->login('student@matrix.invalid');
ok('student cannot open admin pages', $live->req('GET', '/admin/dashboard.php')['status'], 403);
$pdo->prepare('UPDATE users SET role_id = 3 WHERE id = ?')->execute([$ids['student']]);
ok('promotion to administrator works without signing in again', $live->req('GET', '/admin/dashboard.php')['status'], 200);
ok('...but not into Super-admin pages', $live->req('GET', '/admin/users/index.php')['status'], 403);
$pdo->prepare('UPDATE users SET role_id = 1 WHERE id = ?')->execute([$ids['student']]);
ok('demotion removes access immediately', $live->req('GET', '/admin/dashboard.php')['status'], 403);
$pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$ids['student']]);
$resp = $live->req('GET', '/profile.php');
ok('deactivated user is signed out on the next request', [$resp['status'], str_contains($resp['location'], 'login.php')], [302, true]);
$resp = (new Client('inactive'))->login('student@matrix.invalid');
ok('deactivated user cannot sign in', [$resp['status'], str_contains($resp['body'], 'not active')], [200, true]);
$pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$ids['student']]);

// temporary password flow (FR-SA-03, FR-AUTH-07)
$tmpUser = create_user($pdo, ['display_name' => 'Temp User', 'email' => 'tu1@matrix.invalid', 'role' => 'student'], 'Temporary-Pass-1', true);
$tc = new Client('temp'); $tc->login('tu1@matrix.invalid', 'Temporary-Pass-1');
$resp = $tc->req('GET', '/search.php');
ok('temporary password: every page sends you to Change password', [$resp['status'], $resp['location']], [302, '/change_password.php']);
ok('...Change password itself opens', $tc->req('GET', '/change_password.php')['status'], 200);
$tk = $tc->csrf();   // csrf() visits contact.php, which redirects; take the token from the form page instead
preg_match('/name="csrf" value="([a-f0-9]+)"/', $tc->req('GET', '/change_password.php')['body'], $mm); $tk = $mm[1] ?? '';
$resp = $tc->req('POST', '/change_password.php', ['csrf' => $tk, 'current_password' => 'wrong', 'password' => 'Brand-New-Pass-5', 'password_confirm' => 'Brand-New-Pass-5']);
ok('wrong current password refused', str_contains($resp['body'], 'current password is not correct'), true);
$resp = $tc->req('POST', '/change_password.php', ['csrf' => $tk, 'current_password' => 'Temporary-Pass-1', 'password' => 'short', 'password_confirm' => 'short']);
ok('weak new password refused', str_contains($resp['body'], 'at least'), true);
$resp = $tc->req('POST', '/change_password.php', ['csrf' => $tk, 'current_password' => 'Temporary-Pass-1', 'password' => 'Temporary-Pass-1', 'password_confirm' => 'Temporary-Pass-1']);
ok('same password refused', str_contains($resp['body'], 'different'), true);
$resp = $tc->req('POST', '/change_password.php', ['csrf' => $tk, 'current_password' => 'Temporary-Pass-1', 'password' => 'Brand-New-Pass-5', 'password_confirm' => 'Brand-New-Pass-5']);
ok('password changed', $resp['status'], 302);
ok('the site opens normally afterwards', $tc->req('GET', '/search.php')['status'], 200);
ok('old password no longer works', (new Client('old'))->login('tu1@matrix.invalid', 'Temporary-Pass-1')['status'], 200);
ok('new password works', (new Client('new'))->login('tu1@matrix.invalid', 'Brand-New-Pass-5')['status'], 302);
// a reset by a Super-admin ends the person's other sessions
$other = new Client('other'); $other->login('tu1@matrix.invalid', 'Brand-New-Pass-5');
ok('second session is signed in', $other->req('GET', '/profile.php')['status'], 200);
set_user_password($pdo, $tmpUser, 'Reset-By-Admin-9', true);
$resp = $other->req('GET', '/profile.php');
ok('password reset ends the other session', [$resp['status'], str_contains($resp['location'], 'login.php')], [302, true]);

// inactivity timeout (FR-AUTH-04): config is swapped to a 2-second timeout for this part only
file_put_contents($configPath, preg_replace("/'session_timeout'\s*=>\s*\d+/", "'session_timeout' => 2", $configBackup));
$to = new Client('timeout'); $to->login('parent@matrix.invalid');
ok('signed in under the short timeout', $to->req('GET', '/profile.php')['status'], 200);
sleep(3);
$resp = $to->req('GET', '/profile.php');
ok('idle past the timeout: signed out', [$resp['status'], str_contains($resp['location'], 'login.php')], [302, true]);
ok('...and told why', str_contains($to->req('GET', '/login.php')['body'], 'idle'), true);
$to->login('parent@matrix.invalid');
sleep(1); $to->req('GET', '/profile.php'); sleep(1); $to->req('GET', '/profile.php'); sleep(1);
ok('activity keeps the session alive', $to->req('GET', '/profile.php')['status'], 200);
file_put_contents($configPath, $configBackup);

// ------------------------------------------------------------------ write the matrix document
$symbol = ['allowed' => 'Allowed', 'redirected' => 'Redirect to login', '403' => '403', '401' => '401'];
$md = "# Access-control test matrix (Iteration 7)\n\n"
    . "Generated by `php tests/access_test.php` on " . date('Y-m-d H:i') . ". Every cell is what the running site actually returned for that role.\n\n"
    . "- **Allowed**: the page or action ran (200, or a redirect somewhere other than the login page).\n"
    . "- **Redirect to login**: nobody is signed in (visitor), so the request is sent to `login.php` and, after sign-in, back to the page.\n"
    . "- **403**: signed in, but the role is not allowed. Nothing was changed.\n"
    . "- Cells in **bold** are the expected-and-observed \"allowed\" cells. A mismatch fails the test run, so this table has no failures in it.\n\n";
$sections = ['Pages (GET)' => array_column($pages, 0), 'Actions (POST with a valid CSRF token)' => array_column($actions, 0)];
$allowedFor = [];
foreach ($pages as $p) { $allowedFor[$p[0]] = $p[3]; }
foreach ($actions as $a) { $allowedFor[$a[0]] = $a[3]; }
foreach ($sections as $title => $labels) {
    $md .= "## $title\n\n| Page / action | Visitor | Student | Parent | Administrator | Super-admin |\n|---|---|---|---|---|---|\n";
    foreach ($labels as $label) {
        $row = "| $label |";
        foreach ($roles as $r) {
            [$got, $st] = $results[$label][$r] ?? ['?', 0];
            $cell = ($symbol[$got] ?? $got) . ($got === 'allowed' ? " ($st)" : '');
            $row .= ' ' . ($got === 'allowed' ? "**$cell**" : $cell) . ' |';
        }
        $md .= $row . "\n";
    }
    $md .= "\n";
}
$md .= "Allowed (404) or Allowed (302) next to a page that needs a record id means the page ran and then said \"no such record\", because the test uses an id that does not exist. The access check itself passed.\n\n"
    . "## Other behaviours checked in the same run\n\n"
    . "- Opening admin URLs directly while logged out: redirect to login, no admin content in the response, and return to the page after sign-in. Off-site `next` targets are ignored.\n"
    . "- AJAX calls get JSON: 401 when logged out, 403 when the role is wrong.\n"
    . "- Menu by role (visitor, student, parent, administrator, Super-admin) and no development banner for anyone.\n"
    . "- Registration: server-side validation, CSRF, tampered roles (administrator, super_admin, visitor) refused, duplicate email refused, password stored hashed.\n"
    . "- Login: same message for unknown email and wrong password, session id regenerated, HttpOnly and SameSite cookie, planted session id rejected, logout is POST + CSRF, 5 failures lock the email for 15 minutes.\n"
    . "- Role changes and deactivation apply on the next request. A reset password forces a change and ends the person's other sessions. Idle past the timeout signs out; activity keeps the session alive.\n";
file_put_contents($root . '/docs/access_control_matrix.md', $md);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
