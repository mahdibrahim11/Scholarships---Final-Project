<?php
/**
 * AUTHENTICATION AND ACCESS CONTROL (Iteration 7).
 *
 *  - Sessions: the signed-in user's id lives in $_SESSION['uid']. Role, active flag and "must change password" are
 *    read from the database on EVERY request, so a role change or a deactivation takes effect immediately (FR-AUTH-04/05).
 *  - require_role() is the ONE place that decides who may open a page or run an action (FR-AUTH-05).
 *    Not signed in -> redirect to the login page. Signed in with the wrong role -> 403 page. Hiding a menu link is NOT access control.
 *  - Passwords: password_hash() / password_verify() only (FR-AUTH-03). Never stored or logged in plain text.
 */

const ROLE_VISITOR = 'visitor';
const ROLE_STUDENT = 'student';
const ROLE_PARENT = 'parent';
const ROLE_ADMIN = 'administrator';
const ROLE_SUPER_ADMIN = 'super_admin';

/** Roles an account can have (a visitor is simply "not signed in"). */
const ACCOUNT_ROLES = [ROLE_STUDENT, ROLE_PARENT, ROLE_ADMIN, ROLE_SUPER_ADMIN];
/** Roles anyone may pick when registering themselves. Administrators are created by a Super-admin only. */
const SELF_REGISTER_ROLES = [ROLE_STUDENT, ROLE_PARENT];
const ADMIN_ROLES = [ROLE_ADMIN, ROLE_SUPER_ADMIN];

const PASSWORD_MIN = 10;
const PASSWORD_MAX = 72;                 // bcrypt only uses the first 72 bytes
const LOGIN_MAX_FAILS_PER_EMAIL = 5;
const LOGIN_MAX_FAILS_PER_IP = 30;
const LOGIN_WINDOW_MINUTES = 15;

function role_label(string $role): string
{
    return ['student' => 'Student', 'parent' => 'Parent', 'administrator' => 'Administrator', 'super_admin' => 'Super-admin', 'visitor' => 'Visitor'][$role] ?? $role;
}

// ---------------------------------------------------------------------------------------------
// Current user
// ---------------------------------------------------------------------------------------------

/** Load one user with the role name. Returns null if there is no such user. */
function auth_load_user(int $id): ?array
{
    $st = db()->prepare('SELECT u.id, u.email, u.display_name, u.is_active, u.must_change_password, u.session_version,
                                u.last_login_at, u.created_at, r.name AS role
                           FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** The signed-in user (array) or null. Result is cached for the request; auth_boot() fills it. */
function auth_user(?array $set = null, bool $reset = false): ?array
{
    static $user = null;
    if ($reset) { $user = null; }
    if ($set !== null) { $user = $set; }
    return $user;
}

function is_logged_in(): bool { return auth_user() !== null; }

function current_role(): string
{
    return auth_user()['role'] ?? ROLE_VISITOR;
}

/**
 * The acting user. Signed-in web requests: that user. Command-line tools and tests: the first active Super-admin.
 * A web request with nobody signed in is a bug, so this fails closed instead of guessing.
 */
function current_user_id(): int
{
    $u = auth_user();
    if ($u) { return (int)$u['id']; }
    if (PHP_SAPI === 'cli') {
        $id = db()->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'super_admin' AND u.is_active = 1 ORDER BY u.id LIMIT 1")->fetchColumn();
        if ($id) { return (int)$id; }
    }
    throw new RuntimeException('No signed-in user.');
}

function is_dev_mode(): bool
{
    return false;   // Iteration 7: the development banner is gone for good.
}

/** Run once per request from bootstrap.php, right after the session starts. */
function auth_boot(): void
{
    auth_user(null, true);
    if (empty($_SESSION['uid'])) { return; }

    $timeout = max(1, (int)config('app.session_timeout', 1800));
    $last = (int)($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > $timeout) {
        auth_clear_session();
        flash('warning', 'You were signed out because the page was idle for ' . (int)round($timeout / 60) . ' minutes. Please sign in again.');
        return;
    }
    $u = auth_load_user((int)$_SESSION['uid']);
    if (!$u || !$u['is_active'] || (int)$u['session_version'] !== (int)($_SESSION['sv'] ?? 0)) {
        $why = ($u && !$u['is_active']) ? 'This account is not active.' : 'Please sign in again.';
        auth_clear_session();
        flash('warning', $why);
        return;
    }
    $_SESSION['last_activity'] = time();
    auth_user($u);

    // A temporary password must be replaced before anything else (except the page that does it, and signing out).
    if ($u['must_change_password'] && PHP_SAPI !== 'cli') {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['change_password.php', 'logout.php'], true)) {
            redirect('change_password.php');
        }
    }
}

/** Forget the signed-in user and start a fresh session id (keeps the session so a flash message can be shown). */
function auth_clear_session(): void
{
    $_SESSION = [];
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
    auth_user(null, true);
}

/** Sign a user in. Regenerates the session id so a session id planted before login is useless (FR-AUTH-04). */
function auth_sign_in(array $user): void
{
    $_SESSION = [];
    if (PHP_SAPI !== 'cli') { session_regenerate_id(true); }
    $_SESSION['uid'] = (int)$user['id'];
    $_SESSION['sv'] = (int)$user['session_version'];
    $_SESSION['last_activity'] = time();
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    auth_user(auth_load_user((int)$user['id']), true);
}

// ---------------------------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------------------------

function request_wants_json(): bool
{
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

/** Only same-site paths are allowed as a "go back here after login" target (stops open redirects). */
function safe_next(?string $next): ?string
{
    $next = (string)$next;
    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')
        || preg_match('/[\x00-\x1f]/', $next) || preg_match('#^/[a-z][a-z0-9+.-]*:#i', $next)) {
        return null;
    }
    $base = rtrim((string)config('app.base_url', ''), '/');
    if ($base !== '' && !str_starts_with($next, $base . '/')) { return null; }
    return $next;
}

function deny_forbidden(): never
{
    http_response_code(403);
    if (request_wants_json()) { json_out(['error' => 'forbidden'], 403); }
    $pageTitle = 'Not allowed';
    require __DIR__ . '/header.php';
    echo '<h1>You do not have access to this page</h1>'
       . '<p>Your account (' . e(role_label(current_role())) . ') is not allowed to do that. If you think this is a mistake, contact a Super-admin.</p>'
       . '<p><a class="btn btn-primary" href="' . e(url('index.php')) . '">Back to the home page</a></p>';
    require __DIR__ . '/footer.php';
    exit;
}

/**
 * Call at the top of EVERY page and action that is not public.
 * @param string[] $roles roles allowed, e.g. [ROLE_ADMIN, ROLE_SUPER_ADMIN]. ROLE_VISITOR in the list means "public".
 */
function require_role(array $roles): void
{
    if (PHP_SAPI === 'cli') { return; }           // scripts and tests: not reachable from the web
    if (in_array(ROLE_VISITOR, $roles, true)) { return; }
    $u = auth_user();
    if (!$u) {
        if (request_wants_json()) { json_out(['error' => 'login_required'], 401); }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') { $_SESSION['next'] = safe_next($_SERVER['REQUEST_URI'] ?? ''); }
        flash('info', 'Please sign in to continue.');
        redirect('login.php');
    }
    if (!in_array($u['role'], $roles, true)) {
        deny_forbidden();
    }
}

/** Any signed-in user (profile, change password). */
function require_login(): void
{
    require_role(ACCOUNT_ROLES);
}

/** Where a user lands after signing in. */
function home_for_role(string $role): string
{
    if (in_array($role, ADMIN_ROLES, true)) { return 'admin/dashboard.php'; }
    return $role === ROLE_PARENT ? 'my/dashboard.php' : ($role === ROLE_STUDENT ? 'my/matches.php' : 'index.php');
}

// ---------------------------------------------------------------------------------------------
// Passwords and sign-in attempts
// ---------------------------------------------------------------------------------------------

const COMMON_PASSWORDS = ['password', 'password1', 'password123', 'passw0rd', '1234567890', '12345678910', 'qwertyuiop', 'qwerty12345',
    'letmein123', 'welcome123', 'iloveyou123', 'admin12345', 'pathways123', 'scholarship1', 'changeme123'];

/** @return string[] reasons the password is not acceptable (empty = fine). Mirrored in assets/js/auth.js. */
function password_problems(string $pw, string $email = '', string $name = ''): array
{
    $p = [];
    if (strlen($pw) < PASSWORD_MIN) { $p[] = 'Use at least ' . PASSWORD_MIN . ' characters.'; }
    if (strlen($pw) > PASSWORD_MAX) { $p[] = 'Use at most ' . PASSWORD_MAX . ' characters.'; }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) { $p[] = 'Include at least one letter and one number.'; }
    $lower = strtolower($pw);
    if (in_array($lower, COMMON_PASSWORDS, true)) { $p[] = 'That password is too common.'; }
    $local = strtolower(strstr($email, '@', true) ?: '');
    if (strlen($local) >= 4 && str_contains($lower, $local)) { $p[] = 'Do not use your email name inside your password.'; }
    return $p;
}

/** A random temporary password that always passes password_problems(). Shown to a Super-admin once. */
function generate_temp_password(): string
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $pw = '';
    for ($i = 0; $i < 10; $i++) { $pw .= $alpha[random_int(0, strlen($alpha) - 1)]; }
    for ($i = 0; $i < 4; $i++) { $pw .= $digits[random_int(0, strlen($digits) - 1)]; }
    return str_shuffle($pw);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function login_throttled(PDO $pdo, string $emailKey, string $ip): bool
{
    $st = $pdo->prepare('SELECT SUM(email_key = ?) AS by_email, SUM(ip = ?) AS by_ip FROM login_attempts
                          WHERE succeeded = 0 AND attempted_at > (NOW() - INTERVAL ' . (int)LOGIN_WINDOW_MINUTES . ' MINUTE)');
    $st->execute([$emailKey, $ip]);
    $r = $st->fetch();
    return (int)$r['by_email'] >= LOGIN_MAX_FAILS_PER_EMAIL || (int)$r['by_ip'] >= LOGIN_MAX_FAILS_PER_IP;
}

function login_record(PDO $pdo, string $emailKey, string $ip, bool $ok): void
{
    $pdo->prepare('INSERT INTO login_attempts (email_key, ip, succeeded) VALUES (?, ?, ?)')->execute([substr($emailKey, 0, 190), $ip, $ok ? 1 : 0]);
    if ($ok) {   // a good sign-in clears that email's failure count
        $pdo->prepare('DELETE FROM login_attempts WHERE email_key = ? AND succeeded = 0')->execute([substr($emailKey, 0, 190)]);
    }
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 2 DAY)');
}

/**
 * Check an email + password. Returns [user|null, message]. The message is the same for "no such email" and "wrong password",
 * and a password check always runs, so the answer and the timing do not reveal which emails are registered.
 */
function authenticate(PDO $pdo, string $email, string $password): array
{
    $key = normalize_email($email);
    $ip = client_ip();
    if (login_throttled($pdo, $key, $ip)) {
        return [null, 'Too many failed attempts. Wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.'];
    }
    $st = $pdo->prepare('SELECT u.id, u.password_hash, u.is_active, u.session_version FROM users u WHERE u.email = ?');
    $st->execute([$key]);
    $row = $st->fetch();
    static $dummy = null;
    $dummy ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
    $good = password_verify($password, $row['password_hash'] ?? $dummy) && $row && $row['password_hash'] !== '!';
    if (!$good || !$row['is_active']) {
        login_record($pdo, $key, $ip, false);
        return [null, $row && $good && !$row['is_active'] ? 'This account is not active. Contact a Super-admin.' : 'Email or password is not correct.'];
    }
    login_record($pdo, $key, $ip, true);
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    return [$row, ''];
}
