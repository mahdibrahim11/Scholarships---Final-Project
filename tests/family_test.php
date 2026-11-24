<?php
/**
 * FAMILY FEATURES TEST (Iteration 8): profiles, My Matches, save / un-save, application status, deadlines, parent dashboard,
 * and the most important part (FR-USER-06): one family can never see or change another family's profiles or saved items,
 * even when ids in the address or in AJAX requests are changed.
 *
 *   php tests/family_test.php      (needs config/config.php, schema + seeds, curl)
 * Creates users ending in @fam.invalid and scholarships named "FAMTEST ..." and removes them at the end.
 */
define('CLI_TEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/users.php';
require_once __DIR__ . '/../includes/profiles.php';

$root = dirname(__DIR__);
$port = (int)(getenv('TEST_PORT') ?: 8097);
$base = "http://127.0.0.1:$port";
$pdo = db();
$pass = $fail = 0;
function ok(string $label, $actual, $expected): void {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; } else { $fail++; echo "FAIL: $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n"; }
}
const PW = 'Family-Pass-2026';
function cleanup(PDO $pdo): void {
    $pdo->exec("DELETE FROM audit_log WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@fam.invalid') OR details LIKE '%fam.invalid%'");
    $pdo->exec("DELETE FROM audit_log WHERE entity_type = 'profile' AND user_id IS NULL");
    $pdo->exec("DELETE FROM login_attempts WHERE email_key LIKE '%@fam.invalid'");
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@fam.invalid'");   // cascades to profiles and saved rows
    $pdo->exec("DELETE FROM scholarships WHERE name LIKE 'FAMTEST %'");
    $pdo->exec("DELETE FROM providers WHERE name = 'FAMTEST Provider'");
}
cleanup($pdo);

// ------------------------------------------------------------------ fixtures
$uid = [];
foreach (['parentA' => 'parent', 'parentB' => 'parent', 'studentS' => 'student', 'studentT' => 'student', 'admin' => 'administrator'] as $n => $role) {
    $uid[$n] = create_user($pdo, ['display_name' => "Fam $n", 'email' => "$n@fam.invalid", 'role' => $role], PW, false);
}
$pdo->prepare("INSERT INTO providers (name, name_key) VALUES ('FAMTEST Provider', 'famtest provider')")->execute();
$prov = (int)$pdo->lastInsertId();
$CS = 5; $ARTS = 3; $ANY = 1;
$today = date('Y-m-d');
$mk = function (string $name, string $state, ?string $deadline, array $grades, array $fields, bool $rolling = false, string $status = 'active') use ($pdo, $prov) {
    static $n = 0; $n++;
    $pdo->prepare("INSERT INTO scholarships (name, provider_id, official_url, url_key, state_code, scope, award_min, award_max, deadline, is_rolling, status)
                   VALUES (?, ?, ?, ?, ?, ?, 500, 500, ?, ?, ?)")
        ->execute(["FAMTEST $name", $prov, "https://famtest$n.example.org/x", "famtest$n.example.org/x", $state, $state === 'US' ? 'national' : 'state_local', $deadline, $rolling ? 1 : 0, $status]);
    $id = (int)$pdo->lastInsertId();
    foreach ($grades as $g) { $pdo->prepare('INSERT INTO scholarship_grades (scholarship_id, grade) VALUES (?, ?)')->execute([$id, $g]); }
    foreach ($fields as $f) { $pdo->prepare('INSERT INTO scholarship_fields (scholarship_id, field_id) VALUES (?, ?)')->execute([$id, $f]); }
    return $id;
};
$in = fn(int $d) => date('Y-m-d', strtotime("$d days"));
$S = [
    'soon'      => $mk('Soon CA CS',       'CA', $in(3),  [9, 10], [$CS]),
    'month'     => $mk('Month CA CS',      'CA', $in(20), [9],     [$CS]),
    'later'     => $mk('Later national CS', 'US', $in(60), [8, 9, 10], [$CS]),
    'anyfield'  => $mk('Any field CA',     'CA', $in(45), [9],     [$ANY]),
    'nofield'   => $mk('No field tag CA',  'CA', $in(50), [9],     []),
    'rolling'   => $mk('Rolling CA CS',    'CA', null,    [9],     [$CS], true),
    'past'      => $mk('Past CA CS',       'CA', $in(-5), [9],     [$CS]),
    'tx'        => $mk('Texas only CS',    'TX', $in(10), [9],     [$CS]),
    'grade12'   => $mk('Grade 12 CA CS',   'CA', $in(10), [12],    [$CS]),
    'arts'      => $mk('Arts only CA',     'CA', $in(10), [9],     [$ARTS]),
    'archived'  => $mk('Archived CA CS',   'CA', $in(10), [9],     [$CS], false, 'archived'),
];
$expectedMatchesAnnCS = [$S['soon'], $S['month'], $S['later'], $S['anyfield'], $S['nofield'], $S['rolling']];   // CA grade 9, interest CS

// ------------------------------------------------------------------ server + clients
$server = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $root], [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($server, $pdo) { if (is_resource($server)) { proc_terminate($server); } cleanup($pdo); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) { break; } usleep(100000); }

class Client {
    public string $jar;
    function __construct(string $label) { $this->jar = sys_get_temp_dir() . '/fam_' . getmypid() . "_$label.txt"; @unlink($this->jar); }
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
    function csrf(): string { $r = $this->req('GET', '/contact.php'); preg_match('/name="csrf" value="([a-f0-9]+)"/', $r['body'], $m); return $m[1] ?? ''; }
    function login(string $email): void { $this->req('POST', '/login.php', ['csrf' => $this->csrf(), 'email' => $email, 'password' => PW]); }
    /** AJAX POST with the CSRF header, like assets/js/save.js. @return array{status:int,json:?array,body:string} */
    function ajax(string $path, array $post, ?string $token = null): array {
        $token ??= $this->csrf();
        $r = $this->req('POST', $path, $post, ['X-CSRF-Token: ' . $token, 'X-Requested-With: XMLHttpRequest', 'Accept: application/json']);
        return ['status' => $r['status'], 'json' => json_decode($r['body'], true), 'body' => $r['body']];
    }
    function form(string $path, array $post): array { return $this->req('POST', $path, ['csrf' => $this->csrf()] + $post); }
}
$A = new Client('A'); $A->login('parentA@fam.invalid');
$B = new Client('B'); $B->login('parentB@fam.invalid');
$St = new Client('S'); $St->login('studentS@fam.invalid');
$Tt = new Client('T'); $Tt->login('studentT@fam.invalid');
$Ad = new Client('Ad'); $Ad->login('admin@fam.invalid');
$V = new Client('V');

// ------------------------------------------------------------------ FR-USER-01/02: profiles
$r = $A->req('GET', '/my/matches.php');
ok('no profile yet: My Matches sends a new parent to create one', [$r['status'], str_contains($r['location'], 'profile_edit.php')], [302, true]);
$r = $A->form('/my/profile_edit.php', ['display_name' => '', 'grade' => '3', 'state_code' => 'XX']);
ok('invalid profile is rejected (200, shows errors)', [$r['status'], str_contains($r['body'], 'is-invalid')], [200, true]);
ok('nothing was saved by the invalid form', (int)$pdo->query("SELECT COUNT(*) FROM student_profiles WHERE owner_user_id = {$uid['parentA']}")->fetchColumn(), 0);
$r = $A->form('/my/profile_edit.php', ['display_name' => '<script>alert(1)</script>', 'grade' => '9', 'state_code' => 'CA']);
ok('markup in a name is rejected', str_contains($r['body'], 'cannot contain'), true);
$r = $A->form('/my/profile_edit.php', ['display_name' => 'Ann', 'grade' => '9', 'state_code' => 'CA', 'interests' => [(string)$CS]]);
ok('parent creates first child, goes to My Matches', [$r['status'], $r['location']], [302, '/my/matches.php']);
$r = $A->form('/my/profile_edit.php', ['display_name' => 'Ben & Co', 'grade' => '7', 'state_code' => 'MN']);
ok('parent adds a second child', $r['status'], 302);
$r = $A->form('/my/profile_edit.php', ['display_name' => 'ann', 'grade' => '9', 'state_code' => 'CA']);
ok('duplicate child name (any case) is refused', str_contains($r['body'], 'already have a profile with that name'), true);
$annId = (int)$pdo->query("SELECT id FROM student_profiles WHERE owner_user_id = {$uid['parentA']} AND display_name = 'Ann'")->fetchColumn();
$benId = (int)$pdo->query("SELECT id FROM student_profiles WHERE owner_user_id = {$uid['parentA']} AND display_name = 'Ben & Co'")->fetchColumn();
ok('two profiles exist for parent A', [$annId > 0, $benId > 0], [true, true]);
ok('interests stored', array_map('intval', $pdo->query("SELECT field_id FROM profile_interests WHERE profile_id = $annId")->fetchAll(PDO::FETCH_COLUMN)), [$CS]);
$r = $A->req('GET', '/my/profiles.php');
ok('name is HTML-escaped on the list', [str_contains($r['body'], 'Ben &amp; Co'), str_contains($r['body'], 'Ben & Co<')], [true, false]);
$r = $B->form('/my/profile_edit.php', ['display_name' => 'Cara', 'grade' => '9', 'state_code' => 'CA', 'interests' => [(string)$CS]]);
$caraId = (int)$pdo->query("SELECT id FROM student_profiles WHERE owner_user_id = {$uid['parentB']}")->fetchColumn();
ok('parent B has a profile', $caraId > 0, true);

$r = $St->form('/my/profile_edit.php', ['display_name' => 'Sam', 'grade' => '10', 'state_code' => 'TX']);
ok('student creates their profile', $r['status'], 302);
$r = $St->form('/my/profile_edit.php', ['display_name' => 'Sam Two', 'grade' => '10', 'state_code' => 'TX']);
ok('a student cannot create a second profile', (int)$pdo->query("SELECT COUNT(*) FROM student_profiles WHERE owner_user_id = {$uid['studentS']}")->fetchColumn(), 1);
$samId = (int)$pdo->query("SELECT id FROM student_profiles WHERE owner_user_id = {$uid['studentS']}")->fetchColumn();
$r = $St->req('GET', '/my/profile_edit.php');
ok('student opening "new profile" is sent to edit their own', str_contains($r['location'], "profile_edit.php?id=$samId"), true);
$r = $St->req('GET', '/my/dashboard.php');
ok('students have no family dashboard (403)', $r['status'], 403);
$r = $St->form('/my/profiles.php', ['action' => 'delete', 'id' => (string)$samId]);
ok('a student cannot delete their profile through the parent action', (int)$pdo->query("SELECT COUNT(*) FROM student_profiles WHERE id = $samId")->fetchColumn(), 1);

// ------------------------------------------------------------------ FR-USER-03: My Matches
function matchIds(Client $c, string $path): array {
    $r = $c->req('GET', $path);
    preg_match_all('#scholarship\.php\?id=(\d+)#', $r['body'], $m);
    return array_values(array_unique(array_map('intval', $m[1])));
}
$mine = fn(array $ids) => array_values(array_intersect($ids, array_values($S)));   // ignore any other rows already in the database
$got = $mine(matchIds($A, "/my/matches.php?profile=$annId"));
sort($got); $want = $expectedMatchesAnnCS; sort($want);
ok('My Matches for Ann (CA, grade 9, CS) is exactly the expected set', $got, $want);
$r = $A->req('GET', "/my/matches.php?profile=$annId");
ok('matches page explains why', str_contains($r['body'], 'Why it matches'), true);
ok('excluded: other state, other grade, other field, passed, archived',
    array_intersect($got, [$S['tx'], $S['grade12'], $S['arts'], $S['past'], $S['archived']]), []);
$pdo->prepare('DELETE FROM profile_interests WHERE profile_id = ?')->execute([$annId]);
$got2 = $mine(matchIds($A, "/my/matches.php?profile=$annId"));
ok('no interests chosen = every field, so the Arts one appears', in_array($S['arts'], $got2, true), true);
$pdo->prepare('INSERT INTO profile_interests (profile_id, field_id) VALUES (?, ?)')->execute([$annId, $CS]);
$gotBen = matchIds($A, "/my/matches.php?profile=$benId");
ok('Ben (MN, grade 7) matches none of the CA test scholarships', array_intersect($gotBen, array_values($S)), []);
$r = $V->req('GET', '/my/matches.php');
ok('a visitor is sent to sign in', [$r['status'], str_contains($r['location'], 'login.php')], [302, true]);
$r = $Ad->req('GET', '/my/matches.php');
ok('an administrator cannot use family pages (403)', $r['status'], 403);

// ------------------------------------------------------------------ FR-USER-04/05: save, un-save, status
$A->req('GET', "/my/matches.php?profile=$annId");   // makes Ann the active profile
$tok = $A->csrf();
$r = $A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['soon'], 'save' => '1'], $tok);
ok('save returns saved:true', [$r['status'], $r['json']['saved'] ?? null], [200, true]);
$A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['soon'], 'save' => '1'], $tok);
ok('saving twice keeps one row', (int)$pdo->query("SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['soon']}")->fetchColumn(), 1);
foreach (['month', 'later', 'rolling', 'past'] as $k) { $A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S[$k], 'save' => '1'], $tok); }
$r = $A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['archived'], 'save' => '1'], $tok);
ok('an archived scholarship cannot be saved', [$r['status'], (int)$pdo->query("SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['archived']}")->fetchColumn()], [404, 0]);
$r = $A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => 99999999, 'save' => '1'], $tok);
ok('a scholarship that does not exist cannot be saved', $r['status'], 404);
$r = $A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['month'], 'save' => '0'], $tok);
ok('un-save works', [$r['status'], $r['json']['saved'] ?? null, (int)$pdo->query("SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['month']}")->fetchColumn()], [200, false, 0]);
$A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['month'], 'save' => '1'], $tok);
ok('default status is Interested', $pdo->query("SELECT app_status FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['month']}")->fetchColumn(), 'Interested');
$r = $A->ajax('/my/status.php', ['profile_id' => $annId, 'scholarship_id' => $S['soon'], 'status' => 'In Progress'], $tok);
ok('status can be changed', [$r['status'], $pdo->query("SELECT app_status FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['soon']}")->fetchColumn()], [200, 'In Progress']);
$r = $A->ajax('/my/status.php', ['profile_id' => $annId, 'scholarship_id' => $S['soon'], 'status' => 'Won the lottery'], $tok);
ok('an invalid status is refused (422) and nothing changes', [$r['status'], $pdo->query("SELECT app_status FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['soon']}")->fetchColumn()], [422, 'In Progress']);
$r = $A->ajax('/my/status.php', ['profile_id' => $annId, 'scholarship_id' => $S['tx'], 'status' => 'Submitted'], $tok);
ok('changing the status of something not saved is 404', $r['status'], 404);
$A->ajax('/my/status.php', ['profile_id' => $annId, 'scholarship_id' => $S['later'], 'status' => 'Submitted'], $tok);
$A->ajax('/my/status.php', ['profile_id' => $annId, 'scholarship_id' => $S['rolling'], 'status' => 'Awarded'], $tok);
// no JavaScript fallback form
$r = $A->form('/my/saved.php?profile=' . $annId, ['profile_id' => (string)$annId, 'scholarship_id' => (string)$S['month'], 'status' => 'In Progress']);
ok('the no-JavaScript status form works', $pdo->query("SELECT app_status FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['month']}")->fetchColumn(), 'In Progress');
$r = $A->req('GET', "/my/saved.php?profile=$annId");
ok('saved page lists saved items and status filter counts', [str_contains($r['body'], 'FAMTEST Soon CA CS'), str_contains($r['body'], 'In Progress (2)'), str_contains($r['body'], 'Submitted (1)')], [true, true, true]);
$r = $A->req('GET', "/my/saved.php?profile=$annId&status=Submitted");
ok('status filter shows only that status', [str_contains($r['body'], 'FAMTEST Later national CS'), str_contains($r['body'], 'FAMTEST Soon CA CS')], [true, false]);
$r = $A->req('GET', "/search.php?state=CA&grade=9");
ok('search page shows Save buttons for a parent with a profile', str_contains($r['body'], 'js-save-toggle'), true);
$r = $A->req('GET', "/search_results.php?state=CA&grade=9");
ok('AJAX search results show saved state (aria-pressed true)', preg_match('/data-scholarship="' . $S['soon'] . '"[^>]*aria-pressed="true"/', $r['body']) === 1, true);
$r = $A->req('GET', "/scholarship.php?id={$S['soon']}");
ok('detail page has a pressed Save button for a saved item', preg_match('/data-scholarship="' . $S['soon'] . '"[^>]*aria-pressed="true"/', $r['body']) === 1, true);
$r = $V->req('GET', "/search_results.php?state=CA&grade=9");
ok('a visitor sees "Sign in to save"', [str_contains($r['body'], 'Sign in to save'), str_contains($r['body'], 'js-save-toggle')], [true, false]);
$r = $Ad->req('GET', "/search_results.php?state=CA&grade=9");
ok('an administrator sees no Save button', [str_contains($r['body'], 'js-save-toggle'), str_contains($r['body'], 'Sign in to save')], [false, false]);
$r = $A->req('POST', '/my/save.php', ['profile' => $annId, 'scholarship' => $S['arts'], 'save' => '1'], ['X-Requested-With: XMLHttpRequest']);
ok('save without a CSRF token is refused', [$r['status'], (int)$pdo->query("SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = $annId AND scholarship_id = {$S['arts']}")->fetchColumn()], [403, 0]);
$r = $A->req('GET', '/my/save.php');
ok('save endpoint refuses GET', $r['status'], 405);
$r = $V->req('POST', '/my/save.php', ['profile' => $annId, 'scholarship' => $S['arts'], 'save' => '1'], ['X-Requested-With: XMLHttpRequest', 'X-CSRF-Token: x']);
ok('a visitor calling save gets JSON 401', $r['status'], 401);

// ------------------------------------------------------------------ upcoming deadlines (FR-USER-05)
$r = $A->req('GET', "/my/deadlines.php?profile=$annId");
$body = $r['body'];
ok('deadline in 3 days is highlighted due-soon with words', [str_contains($body, 'due-soon-row'), str_contains($body, 'Due in 3 days')], [true, true]);
ok('deadline in 20 days is highlighted due-month', [str_contains($body, 'due-month-row'), str_contains($body, 'Due in 20 days')], [true, true]);
$r60 = $A->req('GET', "/my/deadlines.php?profile=$annId&within=90")['body'];   // Later is Submitted, so use Anyfield-free check: add one at 60 days
$A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['anyfield'], 'save' => '1'], $tok);
$r60 = $A->req('GET', "/my/deadlines.php?profile=$annId")['body'];
ok('deadline in 45 days has no highlight', (bool)preg_match('#<tr>\s*<td class="text-nowrap"><span class="due-badge[^"]*"><span class="due-date">[^<]*</span> <span class="due-words">Due in 45 days#', $r60), true);
$A->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['anyfield'], 'save' => '0'], $tok);
ok('Submitted items are not listed as upcoming', str_contains($body, 'FAMTEST Later national CS'), false);
ok('passed deadlines are not listed', str_contains($body, 'FAMTEST Past CA CS'), false);
ok('rolling item that is Awarded is not listed', str_contains($body, 'FAMTEST Rolling CA CS'), false);
$p1 = strpos($body, 'FAMTEST Soon'); $p2 = strpos($body, 'FAMTEST Month');
ok('soonest first', $p1 !== false && $p2 !== false && $p1 < $p2, true);
$r = $A->req('GET', "/my/deadlines.php?profile=$annId&within=7");
ok('"due within 7 days" filter hides the 20-day one', [str_contains($r['body'], 'FAMTEST Soon'), str_contains($r['body'], 'FAMTEST Month')], [true, false]);
ok('due_class boundaries', [due_class(-1), due_class(0), due_class(7), due_class(8), due_class(30), due_class(31), due_class(null)], ['due-past', 'due-soon', 'due-soon', 'due-month', 'due-month', '', '']);
ok('due_words', [due_words(0), due_words(1), due_words(5), due_words(-1), due_words(null)], ['Due today', 'Due tomorrow', 'Due in 5 days', 'Passed 1 day ago', 'No fixed deadline']);

// ------------------------------------------------------------------ parent dashboard
$A->ajax('/my/save.php', ['profile' => $benId, 'scholarship' => $S['tx'], 'save' => '1'], $tok);
$r = $A->req('GET', '/my/dashboard.php');
ok('dashboard shows both children and their saved scholarships', [str_contains($r['body'], 'Ann'), str_contains($r['body'], 'Ben &amp; Co'), str_contains($r['body'], 'FAMTEST Soon CA CS'), str_contains($r['body'], 'FAMTEST Texas only CS')], [true, true, true, true]);
ok('dashboard shows the next-60-days table with the child name', str_contains($r['body'], 'Next 60 days'), true);
$r = $A->req('GET', '/my/deadlines.php?scope=all');
ok('"all children" deadlines include both', [str_contains($r['body'], 'FAMTEST Soon'), str_contains($r['body'], 'FAMTEST Texas only')], [true, true]);

// ------------------------------------------------------------------ FR-USER-06: isolation (change ids in URLs and AJAX requests)
$pdo->prepare('INSERT INTO saved_scholarships (profile_id, scholarship_id) VALUES (?, ?)')->execute([$caraId, $S['later']]);
$snap = function () use ($pdo, $annId, $benId, $caraId): string {
    return json_encode([
        $pdo->query("SELECT id, owner_user_id, display_name, grade, state_code FROM student_profiles WHERE id IN ($annId, $benId, $caraId) ORDER BY id")->fetchAll(),
        $pdo->query("SELECT profile_id, scholarship_id, app_status FROM saved_scholarships WHERE profile_id IN ($annId, $benId, $caraId) ORDER BY profile_id, scholarship_id")->fetchAll(),
        $pdo->query("SELECT profile_id, field_id FROM profile_interests WHERE profile_id IN ($annId, $benId, $caraId) ORDER BY 1, 2")->fetchAll(),
    ]);
};
$before = $snap();
$btok = $B->csrf();
// A page that does not exist for anyone, to compare "someone else's" against "no such thing"
$missing = 987654321;
foreach ([
    'matches' => '/my/matches.php?profile=%d', 'saved' => '/my/saved.php?profile=%d', 'deadlines' => '/my/deadlines.php?profile=%d', 'edit form' => '/my/profile_edit.php?id=%d',
] as $label => $tpl) {
    $other = $B->req('GET', sprintf($tpl, $annId));
    $none = $B->req('GET', sprintf($tpl, $missing));
    ok("B opening A's profile ($label) is 404", $other['status'], 404);
    ok("B opening A's profile ($label) reveals nothing", [str_contains($other['body'], 'Ann'), str_contains($other['body'], 'FAMTEST Soon'), str_contains($other['body'], 'Ben &amp;')], [false, false, false]);
    ok("someone else's profile and a missing one look the same ($label)", $other['status'] === $none['status'] && strlen($other['body']) === strlen($none['body']) , true);
}
foreach (['0', '-1', 'abc', '1e3', "$annId OR 1=1", "$annId; DROP TABLE users", "$annId.0", '9999999999999999999999'] as $bad) {
    $r = $B->req('GET', '/my/matches.php?profile=' . rawurlencode($bad));
    ok("odd profile id '$bad' is a 404, not an error page", [$r['status'], str_contains($r['body'], 'Fatal'), str_contains($r['body'], 'SQL')], [404, false, false]);
}
$r = $B->req('GET', '/my/matches.php?profile[]=' . $annId);
ok('profile[]= array tampering is a 404', $r['status'], 404);
$r = $B->req('GET', '/my/matches.php');
ok('after the failed tries, B still sees only Cara', [str_contains($r['body'], 'Cara'), str_contains($r['body'], 'Ann')], [true, false]);
$r = $B->req('GET', '/my/dashboard.php');
ok("B's dashboard never lists A's children or saved items", [str_contains($r['body'], 'Cara'), str_contains($r['body'], 'Ann'), str_contains($r['body'], 'FAMTEST Soon CA CS'), str_contains($r['body'], 'Ben')], [true, false, false, false]);
$r = $B->req('GET', '/my/deadlines.php?scope=all');
ok("B's 'all children' deadlines contain only B's", [str_contains($r['body'], 'FAMTEST Soon'), str_contains($r['body'], 'FAMTEST Texas only')], [false, false]);
$r = $B->req('GET', '/my/profiles.php');
ok("B's children list does not include A's", [str_contains($r['body'], 'Cara'), str_contains($r['body'], 'Ann'), str_contains($r['body'], 'Ben')], [true, false, false]);

// changing things
$r = $B->form('/my/profile_edit.php', ['id' => (string)$annId, 'display_name' => 'Hacked', 'grade' => '12', 'state_code' => 'NY', 'interests' => ['3']]);
ok("B cannot edit A's profile (404)", $r['status'], 404);
$r = $B->form('/my/profiles.php', ['action' => 'delete', 'id' => (string)$annId]);
ok("B cannot delete A's profile (404)", $r['status'], 404);
$r = $B->form('/my/switch.php', ['profile' => (string)$annId, 'return' => 'my/saved.php']);
ok("B cannot switch to A's profile (404)", $r['status'], 404);
foreach ([['save', '1'], ['save', '0']] as [$k, $v]) {
    $r = $B->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['soon'], 'save' => $v], $btok);
    ok("B cannot " . ($v === '1' ? 'save to' : 'un-save from') . " A's profile via AJAX (404, no body leak)", [$r['status'], $r['json'], str_contains($r['body'], 'Ann')], [404, ['error' => 'not_found'], false]);
}
$r = $B->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['arts'], 'save' => '1'], $btok);
$r2 = $B->ajax('/my/save.php', ['profile' => $missing, 'scholarship' => $S['arts'], 'save' => '1'], $btok);
ok("A's profile and a missing one answer the same over AJAX", $r['body'] === $r2['body'] && $r['status'] === $r2['status'], true);
$r = $B->ajax('/my/status.php', ['profile_id' => $annId, 'scholarship_id' => $S['soon'], 'status' => 'Not Awarded'], $btok);
ok("B cannot change A's application status via AJAX (404)", $r['status'], 404);
$r = $B->form("/my/saved.php?profile=$caraId", ['profile_id' => (string)$annId, 'scholarship_id' => (string)$S['soon'], 'status' => 'Awarded']);
ok("B cannot change A's status via the plain form either", $r['status'], 404);
$r = $B->form("/my/saved.php?profile=$caraId", ['action' => 'remove', 'profile_id' => (string)$annId, 'scholarship_id' => (string)$S['soon']]);
ok("B cannot remove A's saved item via the plain form", $r['status'], 404);
// mixing: B's own profile id but A's scholarship row is harmless; and B's own saved row cannot be reached by A
$r = $A->ajax('/my/status.php', ['profile_id' => $caraId, 'scholarship_id' => $S['later'], 'status' => 'Awarded'], $tok);
ok("A cannot change B's saved item (404)", $r['status'], 404);
$r = $A->ajax('/my/save.php', ['profile' => $caraId, 'scholarship' => $S['later'], 'save' => '0'], $tok);
ok("A cannot un-save B's saved item (404)", $r['status'], 404);
$r = $St->ajax('/my/save.php', ['profile' => $annId, 'scholarship' => $S['soon'], 'save' => '0']);
ok("a student cannot touch a parent's profile either (404)", $r['status'], 404);
$r = $Tt->req('GET', "/my/matches.php?profile=$samId");
ok("student T cannot open student S's profile (404)", $r['status'], 404);
ok('after all of that, nothing in the database changed', $snap(), $before);
// session tampering: the active profile stored in the session is re-checked on every request
$cookieSid = (function () use ($B) { foreach (file($B->jar) as $l) { $p = explode("\t", preg_replace('/^#HttpOnly_/', '', trim($l))); if (count($p) >= 7 && $p[5] === 'PTS_SESSION') { return $p[6]; } } return ''; })();
ok('(sanity) B has a session cookie', $cookieSid !== '', true);
$sessFile = session_save_path() ?: sys_get_temp_dir();
$f = rtrim($sessFile, '/') . '/sess_' . $cookieSid;
if (is_file($f)) {
    $data = file_get_contents($f);
    file_put_contents($f, preg_replace('/active_profile\|i:\d+;/', 'active_profile|i:' . $annId . ';', $data));
    $r = $B->req('GET', '/my/matches.php');
    ok("even a forged active_profile in the session cannot show A's profile", [str_contains($r['body'], 'Cara'), str_contains($r['body'], 'Ann')], [true, false]);
}

// ------------------------------------------------------------------ deleting
$r = $A->form('/my/profiles.php', ['action' => 'delete', 'id' => (string)$benId]);
ok('parent removes their own child; saved rows go with it', [$r['status'], (int)$pdo->query("SELECT COUNT(*) FROM student_profiles WHERE id = $benId")->fetchColumn(), (int)$pdo->query("SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = $benId")->fetchColumn()], [302, 0, 0]);
ok("the other family's data survived", (int)$pdo->query("SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = $caraId")->fetchColumn(), 1);
ok('audit trail records profile changes', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'profile' AND action IN ('profile.create','profile.update','profile.delete','saved.status')")->fetchColumn() > 0, true);
$pdo->exec("DELETE FROM audit_log WHERE user_id = {$uid['parentB']}");
$pdo->exec("DELETE FROM users WHERE id = {$uid['parentB']}");
ok('deleting an account removes its profiles and saved items (cascade)', [(int)$pdo->query("SELECT COUNT(*) FROM student_profiles WHERE id = $caraId")->fetchColumn(), (int)$pdo->query("SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = $caraId")->fetchColumn()], [0, 0]);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
