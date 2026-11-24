<?php
/**
 * Student and parent features (Iteration 8): profiles, saved scholarships, application status, matches, deadlines.
 *
 * THE RULE THAT PROTECTS FAMILIES FROM EACH OTHER (privacy; FR-AUTH-05):
 *   A profile id that came from a URL, a form or an AJAX call is NEVER trusted. Every page and action gets its profile
 *   from profile_owned($pdo, $id, $userId), which returns null unless that profile belongs to the signed-in user.
 *   A profile that does not exist and a profile that belongs to someone else look exactly the same (a "not found" answer),
 *   so nobody can learn which ids exist. Every saved-scholarship query below starts from an owned profile.
 */

const PROFILE_MAX_STUDENT = 1;     // a student has one profile: their own
const PROFILE_MAX_PARENT = 8;      // a parent can manage several children
const PROFILE_MAX_INTERESTS = 14;
const APP_STATUSES = ['Interested', 'In Progress', 'Submitted', 'Awarded', 'Not Awarded'];
/** Statuses where there is still something to do, so the deadline matters. */
const APP_STATUSES_OPEN = ['Interested', 'In Progress'];
const DUE_SOON_DAYS = 7;
const DUE_MONTH_DAYS = 30;
const MATCHES_PER_PAGE = 20;

// ---------------------------------------------------------------------------------------------
// Profiles
// ---------------------------------------------------------------------------------------------

function profile_limit_for(string $role): int
{
    return $role === ROLE_PARENT ? PROFILE_MAX_PARENT : PROFILE_MAX_STUDENT;
}

/** One profile (with interests) if, and only if, it belongs to $userId. Otherwise null. */
function profile_owned(PDO $pdo, int $profileId, int $userId): ?array
{
    if ($profileId <= 0 || $userId <= 0) { return null; }
    $st = $pdo->prepare('SELECT p.id, p.owner_user_id, p.display_name, p.grade, p.state_code, p.created_at, s.name AS state_name
                           FROM student_profiles p JOIN states s ON s.code = p.state_code
                          WHERE p.id = ? AND p.owner_user_id = ?');
    $st->execute([$profileId, $userId]);
    $p = $st->fetch();
    if (!$p) { return null; }
    $q = $pdo->prepare('SELECT f.id, f.name FROM profile_interests i JOIN fields_of_study f ON f.id = i.field_id WHERE i.profile_id = ? ORDER BY f.name');
    $q->execute([$profileId]);
    $p['interests'] = $q->fetchAll();
    $p['interest_ids'] = array_map(fn($r) => (int)$r['id'], $p['interests']);
    return $p;
}

/** All of a user's profiles, oldest first, each with its interests. */
function profiles_for_user(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT id FROM student_profiles WHERE owner_user_id = ? ORDER BY id');
    $st->execute([$userId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) { $out[] = profile_owned($pdo, (int)$id, $userId); }
    return $out;
}

/**
 * The profile the person is working with right now. Stored in the session, but re-checked against the database every
 * time, so a stale or tampered session value falls back to the first profile the user really owns.
 */
function active_profile(PDO $pdo): ?array
{
    $u = auth_user();
    if (!$u) { return null; }
    $uid = (int)$u['id'];
    $want = (int)($_SESSION['active_profile'] ?? 0);
    $p = $want ? profile_owned($pdo, $want, $uid) : null;
    if (!$p) {
        $st = $pdo->prepare('SELECT id FROM student_profiles WHERE owner_user_id = ? ORDER BY id LIMIT 1');
        $st->execute([$uid]);
        $first = (int)$st->fetchColumn();
        $p = $first ? profile_owned($pdo, $first, $uid) : null;
    }
    if ($p) { $_SESSION['active_profile'] = (int)$p['id']; } else { unset($_SESSION['active_profile']); }
    return $p;
}

/** Make a profile the active one. Returns false (and changes nothing) unless the user owns it. */
function set_active_profile(PDO $pdo, int $profileId): bool
{
    $u = auth_user();
    if (!$u || !profile_owned($pdo, $profileId, (int)$u['id'])) { return false; }
    $_SESSION['active_profile'] = $profileId;
    return true;
}

/** @return array{0:array,1:array} [clean values, errors] */
function validate_profile_input(PDO $pdo, array $in): array
{
    $clean = [
        'display_name' => trim(preg_replace('/\s+/u', ' ', (string)(is_array($in['display_name'] ?? '') ? '' : ($in['display_name'] ?? '')))),
        'grade' => ctype_digit((string)(is_array($in['grade'] ?? '') ? '' : ($in['grade'] ?? ''))) ? (int)$in['grade'] : 0,
        'state_code' => strtoupper(trim((string)(is_array($in['state_code'] ?? '') ? '' : ($in['state_code'] ?? '')))),
        'interests' => [],
    ];
    $err = [];
    $len = mb_strlen($clean['display_name']);
    if ($len < 1 || $len > 60) { $err['display_name'] = 'Enter a name or nickname (1 to 60 characters).'; }
    elseif (preg_match('/[\x00-\x1f<>]/u', $clean['display_name'])) { $err['display_name'] = 'The name cannot contain < or > or control characters.'; }
    if ($clean['grade'] < 6 || $clean['grade'] > 12) { $err['grade'] = 'Choose a grade from 6 to 12.'; }
    $st = $pdo->prepare("SELECT COUNT(*) FROM states WHERE code = ? AND code <> 'US'");
    $st->execute([$clean['state_code']]);
    if (!preg_match('/^[A-Z]{2}$/', $clean['state_code']) || !(int)$st->fetchColumn()) { $err['state_code'] = 'Choose a state.'; }
    $valid = array_map('intval', $pdo->query('SELECT id FROM fields_of_study')->fetchAll(PDO::FETCH_COLUMN));
    $raw = is_array($in['interests'] ?? null) ? $in['interests'] : [];
    foreach ($raw as $v) {
        if (is_scalar($v) && ctype_digit((string)$v) && in_array((int)$v, $valid, true)) { $clean['interests'][(int)$v] = (int)$v; }
        else { $err['interests'] = 'One of the interests is not valid.'; }
    }
    $clean['interests'] = array_values($clean['interests']);
    if (count($clean['interests']) > PROFILE_MAX_INTERESTS) { $err['interests'] = 'Choose up to ' . PROFILE_MAX_INTERESTS . ' interests.'; }
    return [$clean, $err];
}

/**
 * Create ($profileId null) or update a profile owned by $userId. Returns [id|null, error|null].
 * Updating goes through profile_owned(), so you can never overwrite someone else's profile by changing an id.
 */
function profile_save(PDO $pdo, int $userId, string $role, ?int $profileId, array $clean): array
{
    $pdo->beginTransaction();
    try {
        if ($profileId === null) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM student_profiles WHERE owner_user_id = ? FOR UPDATE');
            $st->execute([$userId]);
            if ((int)$st->fetchColumn() >= profile_limit_for($role)) {
                $pdo->rollBack();
                return [null, $role === ROLE_PARENT ? 'You can manage up to ' . PROFILE_MAX_PARENT . ' children.' : 'A student account has one profile. Edit it instead.'];
            }
            $pdo->prepare('INSERT INTO student_profiles (owner_user_id, display_name, grade, state_code) VALUES (?, ?, ?, ?)')
                ->execute([$userId, $clean['display_name'], $clean['grade'], $clean['state_code']]);
            $profileId = (int)$pdo->lastInsertId();
            $action = 'profile.create';
        } else {
            if (!profile_owned($pdo, $profileId, $userId)) { $pdo->rollBack(); return [null, 'not_found']; }
            $pdo->prepare('UPDATE student_profiles SET display_name = ?, grade = ?, state_code = ? WHERE id = ? AND owner_user_id = ?')
                ->execute([$clean['display_name'], $clean['grade'], $clean['state_code'], $profileId, $userId]);
            $pdo->prepare('DELETE FROM profile_interests WHERE profile_id = ?')->execute([$profileId]);
            $action = 'profile.update';
        }
        $ins = $pdo->prepare('INSERT INTO profile_interests (profile_id, field_id) VALUES (?, ?)');
        foreach ($clean['interests'] as $fid) { $ins->execute([$profileId, $fid]); }
        audit_log($pdo, $action, 'profile', $profileId);
        $pdo->commit();
        return [$profileId, null];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e->getCode() === '23000') { return [null, 'You already have a profile with that name.']; }
        throw $e;
    }
}

/** Delete a profile (and, by cascade, its saved scholarships). Only the owner can; returns false if it is not theirs. */
function profile_delete(PDO $pdo, int $userId, int $profileId): bool
{
    if (!profile_owned($pdo, $profileId, $userId)) { return false; }
    $pdo->prepare('DELETE FROM student_profiles WHERE id = ? AND owner_user_id = ?')->execute([$profileId, $userId]);
    audit_log($pdo, 'profile.delete', 'profile', $profileId);
    return true;
}

// ---------------------------------------------------------------------------------------------
// Saved scholarships and application status
// ---------------------------------------------------------------------------------------------

/** Scholarship ids saved by an OWNED profile, as [id => status]. $profile must come from profile_owned(). */
function saved_map(PDO $pdo, array $profile): array
{
    $st = $pdo->prepare('SELECT scholarship_id, app_status FROM saved_scholarships WHERE profile_id = ?');
    $st->execute([(int)$profile['id']]);
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Save or un-save one scholarship for one of the user's profiles. Returns [ok, code]. code: saved, removed, not_found.
 * A profile that is not the user's, and a scholarship that does not exist or is archived, both answer "not_found".
 */
function saved_set(PDO $pdo, int $userId, int $profileId, int $scholarshipId, bool $save): array
{
    $p = profile_owned($pdo, $profileId, $userId);
    if (!$p) { return [false, 'not_found']; }
    if ($save) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM scholarships WHERE id = ? AND status = 'active'");
        $st->execute([$scholarshipId]);
        if (!(int)$st->fetchColumn()) { return [false, 'not_found']; }
        $pdo->prepare('INSERT IGNORE INTO saved_scholarships (profile_id, scholarship_id) VALUES (?, ?)')->execute([$profileId, $scholarshipId]);
        return [true, 'saved'];
    }
    $pdo->prepare('DELETE FROM saved_scholarships WHERE profile_id = ? AND scholarship_id = ?')->execute([$profileId, $scholarshipId]);
    return [true, 'removed'];
}

/** Change the application status of a saved scholarship. Returns [ok, code]: updated, not_found, bad_status. */
function saved_set_status(PDO $pdo, int $userId, int $profileId, int $scholarshipId, string $status): array
{
    if (!in_array($status, APP_STATUSES, true)) { return [false, 'bad_status']; }
    if (!profile_owned($pdo, $profileId, $userId)) { return [false, 'not_found']; }
    $st = $pdo->prepare('UPDATE saved_scholarships SET app_status = ?, status_updated_at = NOW() WHERE profile_id = ? AND scholarship_id = ?');
    $st->execute([$status, $profileId, $scholarshipId]);
    // rowCount is 0 when the status did not change, so check the row exists instead
    $chk = $pdo->prepare('SELECT COUNT(*) FROM saved_scholarships WHERE profile_id = ? AND scholarship_id = ?');
    $chk->execute([$profileId, $scholarshipId]);
    if (!(int)$chk->fetchColumn()) { return [false, 'not_found']; }
    audit_log($pdo, 'saved.status', 'profile', $profileId, ['scholarship_id' => $scholarshipId, 'status' => $status]);
    return [true, 'updated'];
}

/** Everything a profile saved, soonest deadline first (rolling and none after dated ones). */
function saved_list(PDO $pdo, array $profile, ?string $status = null): array
{
    $sql = 'SELECT sv.scholarship_id, sv.app_status, sv.saved_at, sv.status_updated_at, s.name, s.deadline, s.is_rolling, s.status AS listing_status,
                   s.award_min, s.award_max, s.state_code, pr.name AS provider
              FROM saved_scholarships sv
              JOIN scholarships s ON s.id = sv.scholarship_id
              JOIN providers pr ON pr.id = s.provider_id
             WHERE sv.profile_id = ?';
    $args = [(int)$profile['id']];
    if ($status !== null && in_array($status, APP_STATUSES, true)) { $sql .= ' AND sv.app_status = ?'; $args[] = $status; }
    $sql .= ' ORDER BY (s.deadline IS NULL), s.deadline, s.name, s.id';
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

// ---------------------------------------------------------------------------------------------
// My Matches
// ---------------------------------------------------------------------------------------------

/**
 * Scholarships that fit a profile: its state (plus national), its grade, any of its interests (or open to any field),
 * deadline not passed. Built on the same search_where() as public search, so both agree on what "matches" means.
 */
function profile_matches(PDO $pdo, array $profile, int $page = 1, int $perPage = MATCHES_PER_PAGE): array
{
    $p = search_params(['state' => $profile['state_code'], 'grade' => $profile['grade']]);
    [$where, $args] = search_where($p);
    if ($profile['interest_ids']) {
        $in = implode(',', array_fill(0, count($profile['interest_ids']), '?'));
        $where .= " AND (EXISTS (SELECT 1 FROM scholarship_fields f WHERE f.scholarship_id = s.id AND f.field_id IN ($in))
                    OR EXISTS (SELECT 1 FROM scholarship_fields f JOIN fields_of_study fo ON fo.id = f.field_id WHERE f.scholarship_id = s.id AND fo.name = 'Any / undecided')
                    OR NOT EXISTS (SELECT 1 FROM scholarship_fields f WHERE f.scholarship_id = s.id))";
        $args = array_merge($args, $profile['interest_ids']);
    }
    $from = 'FROM scholarships s JOIN providers pr ON pr.id = s.provider_id JOIN states st ON st.code = s.state_code WHERE ' . $where;
    $st = $pdo->prepare('SELECT COUNT(*) ' . $from);
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $st = $pdo->prepare('SELECT s.id, s.name, s.state_code, st.name AS state_name, s.award_min, s.award_max, s.deadline, s.is_rolling,
            s.verification_date, s.essay_required, s.min_gpa, pr.name AS provider ' . $from
        . ' ORDER BY ' . search_order('deadline') . " LIMIT $perPage OFFSET $offset");
    $st->execute($args);
    $rows = $st->fetchAll();

    // Why each one matches: which of the profile's interests it is tagged with (empty = open to any field).
    if ($rows && $profile['interest_ids']) {
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $inS = implode(',', array_fill(0, count($ids), '?'));
        $inF = implode(',', array_fill(0, count($profile['interest_ids']), '?'));
        $q = $pdo->prepare("SELECT f.scholarship_id, fo.name FROM scholarship_fields f JOIN fields_of_study fo ON fo.id = f.field_id
                             WHERE f.scholarship_id IN ($inS) AND f.field_id IN ($inF) ORDER BY fo.name");
        $q->execute(array_merge($ids, $profile['interest_ids']));
        $by = [];
        foreach ($q->fetchAll() as $r) { $by[(int)$r['scholarship_id']][] = $r['name']; }
        foreach ($rows as &$r) { $r['matched_fields'] = $by[(int)$r['id']] ?? []; }
        unset($r);
    } else {
        foreach ($rows as &$r) { $r['matched_fields'] = []; }
        unset($r);
    }
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'from' => $total ? $offset + 1 : 0, 'to' => $offset + count($rows)];
}

// ---------------------------------------------------------------------------------------------
// Deadlines
// ---------------------------------------------------------------------------------------------

/** Days from today to a date (negative = passed). */
function days_until(?string $date): ?int
{
    return $date === null ? null : (int)floor((strtotime($date) - strtotime('today')) / 86400);
}

/** "Due today", "Due tomorrow", "Due in 5 days", "Passed 3 days ago": always words, never colour alone. */
function due_words(?int $days): string
{
    if ($days === null) { return 'No fixed deadline'; }
    if ($days < 0) { return 'Passed ' . abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago'; }
    if ($days === 0) { return 'Due today'; }
    if ($days === 1) { return 'Due tomorrow'; }
    return 'Due in ' . $days . ' days';
}

/** Highlight class for a deadline: due-soon (a week or less), due-month (30 days or less), due-past, or ''. */
function due_class(?int $days): string
{
    if ($days === null) { return ''; }
    if ($days < 0) { return 'due-past'; }
    if ($days <= DUE_SOON_DAYS) { return 'due-soon'; }
    if ($days <= DUE_MONTH_DAYS) { return 'due-month'; }
    return '';
}

/** A deadline badge: the date, the words, and a class for colour. */
function due_badge(?string $deadline, $rolling): string
{
    if ($deadline === null) { return '<span class="due-badge">' . ($rolling ? 'Rolling' : 'No fixed deadline') . '</span>'; }
    $d = days_until($deadline);
    return '<span class="due-badge ' . due_class($d) . '"><span class="due-date">' . e(date('M j, Y', strtotime($deadline))) . '</span> '
         . '<span class="due-words">' . e(due_words($d)) . '</span></span>';
}

/**
 * Saved scholarships still to do (Interested or In Progress) with a deadline from today on, for a set of OWNED profiles.
 * $profiles must come from profile_owned() / profiles_for_user(). Returns rows with the child's name, soonest first,
 * plus rolling / no-deadline ones in $rolling.
 */
function upcoming_deadlines(PDO $pdo, array $profiles, ?int $withinDays = null): array
{
    if (!$profiles) { return ['dated' => [], 'rolling' => []]; }
    $ids = array_map(fn($p) => (int)$p['id'], $profiles);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $open = implode(',', array_fill(0, count(APP_STATUSES_OPEN), '?'));
    $st = $pdo->prepare("SELECT sv.profile_id, p.display_name AS child, sv.scholarship_id, sv.app_status, s.name, s.deadline, s.is_rolling, pr.name AS provider,
                                s.award_min, s.award_max
                           FROM saved_scholarships sv
                           JOIN student_profiles p ON p.id = sv.profile_id
                           JOIN scholarships s ON s.id = sv.scholarship_id AND s.status = 'active'
                           JOIN providers pr ON pr.id = s.provider_id
                          WHERE sv.profile_id IN ($in) AND sv.app_status IN ($open)
                          ORDER BY (s.deadline IS NULL), s.deadline, p.display_name, s.name, s.id");
    $st->execute(array_merge($ids, APP_STATUSES_OPEN));
    $dated = []; $rolling = [];
    foreach ($st->fetchAll() as $r) {
        if ($r['deadline'] === null) { $rolling[] = $r; continue; }
        $d = days_until($r['deadline']);
        if ($d < 0) { continue; }                                  // passed deadlines are not "upcoming"
        if ($withinDays !== null && $d > $withinDays) { continue; }
        $r['days'] = $d;
        $dated[] = $r;
    }
    return ['dated' => $dated, 'rolling' => $rolling];
}

// ---------------------------------------------------------------------------------------------
// Save buttons on public pages
// ---------------------------------------------------------------------------------------------

/**
 * What the save button on search results and the detail page should do for the current visitor.
 *   mode 'toggle'  a student or parent with a profile: a working Save / Saved button (profile + saved map included)
 *   mode 'profile' a student or parent with no profile yet: link to create one
 *   mode 'login'   not signed in: link to sign in
 *   mode 'none'    administrators: no button
 */
function save_context(PDO $pdo): array
{
    $u = auth_user();
    if (!$u) { return ['mode' => 'login']; }
    if (!in_array($u['role'], [ROLE_STUDENT, ROLE_PARENT], true)) { return ['mode' => 'none']; }
    $p = active_profile($pdo);
    if (!$p) { return ['mode' => 'profile']; }
    return ['mode' => 'toggle', 'profile' => $p, 'saved' => saved_map($pdo, $p), 'profiles' => $u['role'] === ROLE_PARENT ? profiles_for_user($pdo, (int)$u['id']) : [$p]];
}

/** The Save / Saved button (or link) for one scholarship. All text is escaped. */
function save_button(array $ctx, int $scholarshipId, string $name, string $return = ''): string
{
    $label = e($name);
    switch ($ctx['mode']) {
        case 'toggle':
            $saved = isset($ctx['saved'][$scholarshipId]);
            return '<button type="button" class="btn btn-sm ' . ($saved ? 'btn-success' : 'btn-outline-success') . ' js-save-toggle" data-scholarship="' . $scholarshipId
                . '" data-profile="' . (int)$ctx['profile']['id'] . '" data-name="' . $label . '" data-url="' . e(url('my/save.php')) . '" data-csrf="' . e(csrf_token()) . '" aria-pressed="' . ($saved ? 'true' : 'false') . '">'
                . '<span class="js-save-label">' . ($saved ? 'Saved' : 'Save') . '</span><span class="visually-hidden"> ' . $label . ' for ' . e($ctx['profile']['display_name']) . '</span></button>';
        case 'profile':
            return '<a class="btn btn-sm btn-outline-success" href="' . e(url('my/profile_edit.php')) . '">Create a profile to save<span class="visually-hidden"> ' . $label . '</span></a>';
        case 'login':
            return '<a class="btn btn-sm btn-outline-success" href="' . e(url('login.php')) . '">Sign in to save<span class="visually-hidden"> ' . $label . '</span></a>';
        default:
            return '';
    }
}
