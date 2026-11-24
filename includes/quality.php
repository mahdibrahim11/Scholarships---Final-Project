<?php
/**
 * Iteration 6: data quality checks (FR-DQ-01), coverage report (FR-DQ-02), and the numbers behind the dashboards
 * (FR-DASH-01, FR-DASH-02). Read-only: nothing here changes a scholarship. Every query is a prepared statement
 * or uses only constants. The pages in admin/ and facts.php only format what these functions return.
 */
require_once __DIR__ . '/import/store.php';

/** A verification date older than this many days is "stale". The instructor can change it here (one place). */
const STALE_VERIFICATION_DAYS = 180;
/** A state or national theme with fewer active scholarships than this is flagged "thin"; zero is "none". */
const COVERAGE_THIN_BELOW = 5;
/** Rows shown per issue list on screen; the CSV download has every row. */
const QUALITY_LIST_LIMIT = 100;

/** The key fields every scholarship should have. [key => [label, SQL condition that is TRUE when the field is MISSING]] */
function missing_field_rules(): array
{
    return [
        'deadline'     => ['Deadline (or "Rolling")', "(s.deadline IS NULL AND s.is_rolling = 0)"],
        'award'        => ['Award amount', '(s.award_min IS NULL AND s.award_max IS NULL)'],
        'grades'       => ['Eligible grades', 'NOT EXISTS (SELECT 1 FROM scholarship_grades g WHERE g.scholarship_id = s.id)'],
        'categories'   => ['Category', 'NOT EXISTS (SELECT 1 FROM scholarship_categories c WHERE c.scholarship_id = s.id)'],
        'fields'       => ['Field of interest', 'NOT EXISTS (SELECT 1 FROM scholarship_fields f WHERE f.scholarship_id = s.id)'],
        'verification' => ['Verification date', 's.verification_date IS NULL'],
    ];
}

/** The issue types shown on the dashboard, in display order. [key => label] */
function quality_issue_types(): array
{
    return [
        'expired'    => 'Expired deadline',
        'stale'      => 'Stale verification',
        'missing'    => 'Missing key fields',
        'duplicates' => 'Possible duplicates',
    ];
}

/** Common SELECT for the issue lists: enough to identify the record, its owner, and the reason. */
function _quality_select(): string
{
    return "SELECT s.id, s.name, p.name AS provider, s.state_code, s.scope, s.deadline, s.is_rolling, s.verification_date,
                   CONCAT(a.last_name, ', ', a.first_name) AS student
            FROM scholarships s
            JOIN providers p ON p.id = s.provider_id
            LEFT JOIN student_assignments a ON a.id = s.contributing_student_id
            WHERE s.status = 'active'";
}

/** Active scholarships whose deadline has passed (rolling ones never expire). */
function quality_expired(PDO $pdo, ?int $limit = null): array
{
    $sql = _quality_select() . ' AND s.is_rolling = 0 AND s.deadline IS NOT NULL AND s.deadline < CURDATE() ORDER BY s.deadline, s.id';
    if ($limit !== null) { $sql .= ' LIMIT ' . (int)$limit; }
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['issue'] = 'Deadline passed on ' . $r['deadline'] . ' (' . (int)((time() - strtotime($r['deadline'])) / 86400) . ' days ago)';
    }
    return $rows;
}

/** Active scholarships never verified, or last verified more than STALE_VERIFICATION_DAYS ago. */
function quality_stale(PDO $pdo, ?int $limit = null): array
{
    $sql = _quality_select() . ' AND (s.verification_date IS NULL OR s.verification_date < DATE_SUB(CURDATE(), INTERVAL ' . STALE_VERIFICATION_DAYS . ' DAY))
            ORDER BY s.verification_date IS NULL DESC, s.verification_date, s.id';
    if ($limit !== null) { $sql .= ' LIMIT ' . (int)$limit; }
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $r['issue'] = $r['verification_date'] === null ? 'Never verified'
            : 'Last verified ' . $r['verification_date'] . ' (' . (int)((time() - strtotime($r['verification_date'])) / 86400) . ' days ago)';
    }
    return $rows;
}

/** Active scholarships missing one or more key fields. Each row lists what is missing. */
function quality_missing(PDO $pdo, ?int $limit = null): array
{
    $rules = missing_field_rules();
    $flags = [];
    foreach ($rules as $key => [$label, $cond]) { $flags[] = "($cond) AS m_$key"; }
    $any = implode(' OR ', array_map(fn($r) => $r[1], $rules));
    $sql = str_replace('SELECT s.id,', 'SELECT ' . implode(', ', $flags) . ', s.id,', _quality_select())
         . " AND ($any) ORDER BY s.id";
    if ($limit !== null) { $sql .= ' LIMIT ' . (int)$limit; }
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        $miss = [];
        foreach ($rules as $key => [$label]) { if ((int)$r["m_$key"] === 1) { $miss[] = $label; } }
        $r['missing'] = $miss;
        $r['issue'] = 'Missing: ' . implode(', ', $miss);
    }
    return $rows;
}

/** Lowercase, letters and digits only, so "Pell Grant (2026)" and "pell grant 2026" compare equal. */
function quality_name_key(string $s): string
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower($s));
}

/**
 * Possible duplicates among ACTIVE scholarships. Exact URL duplicates cannot exist (the database prevents them), so we look for
 * the ways the same scholarship slips in twice:
 *  1. same provider, near-identical name (>= 88% similar);
 *  2. same name (ignoring case and punctuation) under different provider spellings;
 *  3. same web page address but a different query string (url_key differs only after "?").
 * Returns a list of pairs: [a => row, b => row, reason => string].
 */
function quality_duplicate_pairs(PDO $pdo): array
{
    $rows = $pdo->query("SELECT s.id, s.name, s.official_url, s.url_key, s.state_code, s.provider_id, p.name AS provider,
                                CONCAT(a.last_name, ', ', a.first_name) AS student
                         FROM scholarships s JOIN providers p ON p.id = s.provider_id
                         LEFT JOIN student_assignments a ON a.id = s.contributing_student_id
                         WHERE s.status = 'active' ORDER BY s.id")->fetchAll();
    $pairs = [];
    $seen = [];
    $add = function (array $a, array $b, string $reason) use (&$pairs, &$seen) {
        $k = min($a['id'], $b['id']) . '-' . max($a['id'], $b['id']);
        if (isset($seen[$k])) { return; }
        $seen[$k] = true;
        $pairs[] = ['a' => $a, 'b' => $b, 'reason' => $reason];
    };

    $byProvider = $byName = $byPath = [];
    foreach ($rows as $r) {
        $byProvider[$r['provider_id']][] = $r;
        $byName[quality_name_key($r['name'])][] = $r;
        $byPath[strtok($r['url_key'], '?')][] = $r;
    }
    foreach ($byProvider as $group) {
        $n = count($group);
        if ($n < 2 || $n > 200) { continue; }
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                similar_text(quality_name_key($group[$i]['name']), quality_name_key($group[$j]['name']), $pct);
                if ($pct >= 88) { $add($group[$i], $group[$j], 'Same provider, very similar name'); }
            }
        }
    }
    foreach ($byName as $key => $group) {
        if ($key === '' || count($group) < 2) { continue; }
        for ($i = 0; $i < count($group); $i++) {
            for ($j = $i + 1; $j < count($group); $j++) { $add($group[$i], $group[$j], 'Same name'); }
        }
    }
    foreach ($byPath as $path => $group) {
        if (count($group) < 2) { continue; }
        for ($i = 0; $i < count($group); $i++) {
            for ($j = $i + 1; $j < count($group); $j++) { $add($group[$i], $group[$j], 'Same web page, different link parameters'); }
        }
    }
    usort($pairs, fn($x, $y) => [$x['a']['id'], $x['b']['id']] <=> [$y['a']['id'], $y['b']['id']]);
    return $pairs;
}

/** One flat list of issue rows for a type (used by the dashboard lists and the CSV report). */
function quality_issue_rows(PDO $pdo, string $type, ?int $limit = null): array
{
    switch ($type) {
        case 'expired': return quality_expired($pdo, $limit);
        case 'stale':   return quality_stale($pdo, $limit);
        case 'missing': return quality_missing($pdo, $limit);
        case 'duplicates':
            $out = [];
            foreach (quality_duplicate_pairs($pdo) as $p) {
                $out[] = ['id' => $p['a']['id'], 'name' => $p['a']['name'], 'provider' => $p['a']['provider'], 'state_code' => $p['a']['state_code'],
                          'student' => $p['a']['student'], 'issue' => $p['reason'] . ' as #' . $p['b']['id'] . ' "' . $p['b']['name'] . '"',
                          'other_id' => $p['b']['id']];
            }
            return $limit === null ? $out : array_slice($out, 0, $limit);
    }
    throw new InvalidArgumentException('Unknown issue type.');
}

/** The four counts for the dashboard cards, plus how many distinct scholarships have at least one issue. */
function quality_summary(PDO $pdo): array
{
    $count = fn(string $where) => (int)$pdo->query("SELECT COUNT(*) FROM scholarships s WHERE s.status = 'active' AND $where")->fetchColumn();
    $missing = [];
    foreach (missing_field_rules() as $key => [$label, $cond]) { $missing[$key] = ['label' => $label, 'count' => $count($cond)]; }
    $any = implode(' OR ', array_map(fn($r) => $r[1], missing_field_rules()));
    $dupIds = [];
    $dupPairs = quality_duplicate_pairs($pdo);
    foreach ($dupPairs as $p) { $dupIds[$p['a']['id']] = $dupIds[$p['b']['id']] = true; }
    $pairs = count($dupPairs);
    return [
        'active'        => $count('1=1'),
        'expired'       => $count('s.is_rolling = 0 AND s.deadline IS NOT NULL AND s.deadline < CURDATE()'),
        'stale'         => $count('(s.verification_date IS NULL OR s.verification_date < DATE_SUB(CURDATE(), INTERVAL ' . STALE_VERIFICATION_DAYS . ' DAY))'),
        'missing'       => $count("($any)"),
        'missing_by'    => $missing,
        'duplicates'    => $pairs,
        'duplicate_ids' => count($dupIds),
        'clean'         => $count("NOT (s.is_rolling = 0 AND s.deadline IS NOT NULL AND s.deadline < CURDATE())
                                   AND NOT (s.verification_date IS NULL OR s.verification_date < DATE_SUB(CURDATE(), INTERVAL " . STALE_VERIFICATION_DAYS . " DAY))
                                   AND NOT ($any)"),
    ];
}

/** Issue counts per contributing student, so problems can be reported back to the person who collected the data. */
function quality_by_student(PDO $pdo): array
{
    $any = implode(' OR ', array_map(fn($r) => $r[1], missing_field_rules()));
    $sql = "SELECT a.id, a.last_name, a.first_name, COUNT(s.id) AS total,
                   SUM(s.is_rolling = 0 AND s.deadline IS NOT NULL AND s.deadline < CURDATE()) AS expired,
                   SUM(s.verification_date IS NULL OR s.verification_date < DATE_SUB(CURDATE(), INTERVAL " . STALE_VERIFICATION_DAYS . " DAY)) AS stale,
                   SUM($any) AS missing
            FROM student_assignments a
            JOIN scholarships s ON s.contributing_student_id = a.id AND s.status = 'active'
            GROUP BY a.id, a.last_name, a.first_name ORDER BY a.last_name, a.first_name";
    return $pdo->query($sql)->fetchAll();
}

// ---------------------------------------------------------------------------
// Coverage (FR-DQ-02)
// ---------------------------------------------------------------------------

/** "none", "thin" or "ok" for a count of active scholarships. */
function coverage_flag(int $n): string
{
    return $n === 0 ? 'none' : ($n < COVERAGE_THIN_BELOW ? 'thin' : 'ok');
}

/**
 * One row per state (National excluded; it has its own table by theme). Columns: active state-level scholarships, how many are
 * still open (no deadline, rolling, or deadline today or later), middle-school and high-school counts, providers, who is
 * assigned to collect it, and the flag.
 */
function coverage_by_state(PDO $pdo): array
{
    $sql = "SELECT st.code, st.name,
                   COUNT(s.id) AS total,
                   COALESCE(SUM(s.id IS NOT NULL AND (s.is_rolling = 1 OR s.deadline IS NULL OR s.deadline >= CURDATE())), 0) AS open_now,
                   COALESCE(SUM(s.school_level IN ('middle','both')), 0) AS middle,
                   COALESCE(SUM(s.school_level IN ('high','both')), 0) AS high,
                   COUNT(DISTINCT s.provider_id) AS providers,
                   (SELECT GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name, IF(a.state_range = 'All', '', CONCAT(' (', a.state_range, ')'))) ORDER BY a.last_name SEPARATOR '; ')
                      FROM student_assignments a WHERE a.state_code = st.code) AS assigned
            FROM states st
            LEFT JOIN scholarships s ON s.state_code = st.code AND s.status = 'active' AND s.scope = 'state_local'
            WHERE st.code <> 'US'
            GROUP BY st.code, st.name ORDER BY st.name";
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        foreach (['total', 'open_now', 'middle', 'high', 'providers'] as $k) { $r[$k] = (int)$r[$k]; }
        $r['flag'] = coverage_flag($r['total']);
        $gaps = [];
        if ($r['total'] > 0 && $r['middle'] === 0) { $gaps[] = 'no middle school'; }
        if ($r['total'] > 0 && $r['high'] === 0) { $gaps[] = 'no high school'; }
        if ($r['total'] > 0 && $r['open_now'] === 0) { $gaps[] = 'all deadlines passed'; }
        $r['gaps'] = $gaps;
    }
    return $rows;
}

/** One row per national theme: active national scholarships, open ones, providers, who is assigned. */
function coverage_by_theme(PDO $pdo): array
{
    $sql = "SELECT t.id, t.name,
                   COUNT(s.id) AS total,
                   COALESCE(SUM(s.id IS NOT NULL AND (s.is_rolling = 1 OR s.deadline IS NULL OR s.deadline >= CURDATE())), 0) AS open_now,
                   COALESCE(SUM(s.school_level IN ('middle','both')), 0) AS middle,
                   COALESCE(SUM(s.school_level IN ('high','both')), 0) AS high,
                   COUNT(DISTINCT s.provider_id) AS providers,
                   (SELECT GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name, ' (', a.national_range, ')') ORDER BY a.last_name SEPARATOR '; ')
                      FROM student_assignments a WHERE a.national_theme_id = t.id) AS assigned
            FROM national_themes t
            LEFT JOIN scholarships s ON s.national_theme_id = t.id AND s.status = 'active' AND s.scope = 'national'
            GROUP BY t.id, t.name ORDER BY t.id";
    $rows = $pdo->query($sql)->fetchAll();
    foreach ($rows as &$r) {
        foreach (['total', 'open_now', 'middle', 'high', 'providers'] as $k) { $r[$k] = (int)$r[$k]; }
        $r['flag'] = coverage_flag($r['total']);
        $gaps = [];
        if ($r['total'] > 0 && $r['middle'] === 0) { $gaps[] = 'no middle school'; }
        if ($r['total'] > 0 && $r['open_now'] === 0) { $gaps[] = 'all deadlines passed'; }
        $r['gaps'] = $gaps;
    }
    return $rows;
}

/** National scholarships that have no theme (cannot be counted under any theme in the coverage table). */
function coverage_unthemed_national(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'active' AND scope = 'national' AND national_theme_id IS NULL")->fetchColumn();
}

/**
 * Per-student progress for a target (20 for one round, 40 after two). Adds what is still missing and whether the student's
 * state-level / national mix matches their assignment (a student assigned a state should deliver state rows; every student has a theme).
 */
function student_progress(PDO $pdo, int $target = STUDENT_TARGET): array
{
    $rows = student_coverage($pdo);
    foreach ($rows as &$r) {
        $r['total'] = (int)$r['total'];
        $r['state_level'] = (int)$r['state_level'];
        $r['national'] = (int)$r['national'];
        $r['staged'] = (int)$r['staged'];
        $r['short'] = max(0, $target - $r['total']);
        $r['short_after_staged'] = max(0, $target - $r['total'] - $r['staged']);
    }
    return $rows;
}

// ---------------------------------------------------------------------------
// Numbers for the dashboards (FR-DASH-01 public, FR-DASH-02 admin)
// ---------------------------------------------------------------------------

/** [label => count] helper for charts. */
function _pairs(PDO $pdo, string $sql, array $params = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return array_column($st->fetchAll(PDO::FETCH_NUM), 1, 0);
}

/**
 * Public statistics for the Scholarship Facts page. Only counts over ACTIVE scholarships. Nothing personal: no notes, no
 * student names, no batch information.
 */
function public_facts(PDO $pdo): array
{
    $one = fn(string $sql) => $pdo->query($sql)->fetch();
    $tot = $one("SELECT COUNT(*) AS n,
                        COUNT(DISTINCT provider_id) AS providers,
                        COUNT(DISTINCT CASE WHEN scope = 'state_local' THEN state_code END) AS states,
                        COALESCE(SUM(scope = 'national'), 0) AS national,
                        COALESCE(SUM(is_rolling = 1 OR deadline IS NULL OR deadline >= CURDATE()), 0) AS open_now,
                        COALESCE(SUM(essay_required = 'No'), 0) AS no_essay,
                        COALESCE(SUM(need_required = 'No'), 0) AS no_need,
                        COALESCE(SUM(renewable = 'Yes'), 0) AS renewable,
                        AVG(award_max) AS avg_award, MAX(award_max) AS max_award,
                        MAX(verification_date) AS last_verified
                 FROM scholarships WHERE status = 'active'");
    $buckets = $one("SELECT COALESCE(SUM(award_max IS NOT NULL AND award_max < 1000), 0) AS b1,
                            COALESCE(SUM(award_max >= 1000 AND award_max < 2500), 0) AS b2,
                            COALESCE(SUM(award_max >= 2500 AND award_max < 5000), 0) AS b3,
                            COALESCE(SUM(award_max >= 5000 AND award_max < 10000), 0) AS b4,
                            COALESCE(SUM(award_max >= 10000), 0) AS b5,
                            COALESCE(SUM(award_max IS NULL), 0) AS b0
                     FROM scholarships WHERE status = 'active'");
    $level = _pairs($pdo, "SELECT school_level, COUNT(*) FROM scholarships WHERE status = 'active' AND school_level IS NOT NULL GROUP BY school_level");
    $byState = _pairs($pdo, "SELECT st.name, COUNT(*) FROM scholarships s JOIN states st ON st.code = s.state_code
                             WHERE s.status = 'active' AND s.scope = 'state_local' GROUP BY st.name ORDER BY COUNT(*) DESC, st.name LIMIT 15");
    $byCat = _pairs($pdo, "SELECT c.name, COUNT(*) FROM scholarship_categories sc JOIN categories c ON c.id = sc.category_id
                           JOIN scholarships s ON s.id = sc.scholarship_id WHERE s.status = 'active' GROUP BY c.name ORDER BY COUNT(*) DESC, c.name");
    $byField = _pairs($pdo, "SELECT f.name, COUNT(*) FROM scholarship_fields sf JOIN fields_of_study f ON f.id = sf.field_id
                             JOIN scholarships s ON s.id = sf.scholarship_id WHERE s.status = 'active' GROUP BY f.name ORDER BY COUNT(*) DESC, f.name");
    $byGrade = _pairs($pdo, "SELECT g.grade, COUNT(*) FROM scholarship_grades g JOIN scholarships s ON s.id = g.scholarship_id
                             WHERE s.status = 'active' GROUP BY g.grade ORDER BY g.grade");
    // Upcoming deadlines: the next 12 calendar months, so the chart answers "when should I apply?".
    $months = [];
    for ($i = 0; $i < 12; $i++) { $months[date('Y-m', strtotime("first day of +$i month"))] = 0; }
    foreach (_pairs($pdo, "SELECT DATE_FORMAT(deadline, '%Y-%m'), COUNT(*) FROM scholarships
                           WHERE status = 'active' AND is_rolling = 0 AND deadline >= CURDATE() GROUP BY 1") as $m => $n) {
        if (isset($months[$m])) { $months[$m] = (int)$n; }
    }
    $monthLabels = [];
    foreach ($months as $m => $n) { $monthLabels[date('M Y', strtotime($m . '-01'))] = $n; }
    $reqs = $one("SELECT COALESCE(SUM(essay_required = 'Yes'), 0) AS essay, COALESCE(SUM(recommendation_required = 'Yes'), 0) AS rec,
                         COALESCE(SUM(need_required = 'Yes'), 0) AS need, COALESCE(SUM(transcript_required = 'Yes'), 0) AS transcript,
                         COALESCE(SUM(community_service_required = 'Yes'), 0) AS service, COALESCE(SUM(leadership_required = 'Yes'), 0) AS leadership,
                         COALESCE(SUM(min_gpa IS NOT NULL), 0) AS gpa
                  FROM scholarships WHERE status = 'active'");
    $rolling = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'active' AND is_rolling = 1")->fetchColumn();

    $int = fn($v) => (int)$v;
    return [
        'total' => $int($tot['n']), 'providers' => $int($tot['providers']), 'states' => $int($tot['states']),
        'national' => $int($tot['national']), 'open_now' => $int($tot['open_now']), 'rolling' => $rolling,
        'no_essay' => $int($tot['no_essay']), 'no_need' => $int($tot['no_need']), 'renewable' => $int($tot['renewable']),
        'avg_award' => $tot['avg_award'] === null ? null : (float)$tot['avg_award'], 'max_award' => $tot['max_award'] === null ? null : (float)$tot['max_award'],
        'last_verified' => $tot['last_verified'],
        'award_buckets' => ['Under $1,000' => $int($buckets['b1']), '$1,000 to $2,499' => $int($buckets['b2']), '$2,500 to $4,999' => $int($buckets['b3']),
                            '$5,000 to $9,999' => $int($buckets['b4']), '$10,000 or more' => $int($buckets['b5']), 'Varies / not listed' => $int($buckets['b0'])],
        'level' => ['Middle school only' => $int($level['middle'] ?? 0), 'High school only' => $int($level['high'] ?? 0), 'Both' => $int($level['both'] ?? 0)],
        'by_state' => array_map($int, $byState), 'by_category' => array_map($int, $byCat), 'by_field' => array_map($int, $byField),
        'by_grade' => array_map($int, $byGrade), 'deadline_months' => $monthLabels,
        'requirements' => array_map($int, $reqs),
    ];
}

/** Extra numbers for the admin dashboard (FR-DASH-02). */
function admin_dashboard_data(PDO $pdo, int $target = STUDENT_TARGET): array
{
    $quality = quality_summary($pdo);
    $students = student_progress($pdo, $target);
    $done = count(array_filter($students, fn($r) => $r['total'] >= $target));
    $statesWith = (int)$pdo->query("SELECT COUNT(DISTINCT state_code) FROM scholarships WHERE status = 'active' AND scope = 'state_local'")->fetchColumn();
    $themesWith = (int)$pdo->query("SELECT COUNT(DISTINCT national_theme_id) FROM scholarships WHERE status = 'active' AND scope = 'national'")->fetchColumn();
    $batches = _pairs($pdo, 'SELECT status, COUNT(*) FROM import_batches GROUP BY status');
    $searches30 = (int)$pdo->query('SELECT COUNT(*) FROM search_log WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
    $topStates = _pairs($pdo, "SELECT st.name, COUNT(*) FROM search_log l JOIN states st ON st.code = l.state_code
                               WHERE l.searched_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY st.name ORDER BY COUNT(*) DESC, st.name LIMIT 10");
    $topFields = _pairs($pdo, "SELECT f.name, COUNT(*) FROM search_log l JOIN fields_of_study f ON f.id = l.field_id
                               WHERE l.searched_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY f.name ORDER BY COUNT(*) DESC, f.name LIMIT 10");
    $topGrades = _pairs($pdo, "SELECT CONCAT('Grade ', grade), COUNT(*) FROM search_log WHERE grade IS NOT NULL
                               AND searched_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY grade ORDER BY grade");
    $perStudent = [];
    foreach ($students as $s) { $perStudent[$s['last_name'] . ', ' . $s['first_name']] = $s['total']; }
    $archived = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'archived'")->fetchColumn();
    // With ~58 students a bar per student is unreadable, so group them by how close they are to the target.
    $buckets = ['None yet' => 0, 'Under half of target' => 0, 'Half or more, not at target' => 0, 'At or above target' => 0];
    foreach ($students as $s) {
        $t = $s['total'];
        if ($t >= $target) { $buckets['At or above target']++; }
        elseif ($t === 0) { $buckets['None yet']++; }
        elseif ($t * 2 < $target) { $buckets['Under half of target']++; }
        else { $buckets['Half or more, not at target']++; }
    }
    return [
        'student_buckets' => $buckets,
        'quality' => $quality, 'target' => $target, 'students_total' => count($students), 'students_done' => $done,
        'archived' => $archived, 'states_with' => $statesWith, 'themes_with' => $themesWith,
        'batches' => array_map('intval', $batches), 'searches30' => $searches30,
        'top_states' => array_map('intval', $topStates), 'top_fields' => array_map('intval', $topFields), 'top_grades' => array_map('intval', $topGrades),
        'per_student' => $perStudent,
    ];
}

// ---------------------------------------------------------------------------
// Import issues: what the checks found in the uploaded Data Science files (report back to the instructor)
// ---------------------------------------------------------------------------

/**
 * One row per message found in staged or committed batches (discarded and rolled-back batches are ignored).
 * Each row: file, student, source_row, status (of the row), level, field, message, name, url, batch_id.
 * Pass a batch id to look at one file only.
 */
function import_issue_rows(PDO $pdo, ?int $batchId = null, bool $includeInfoStatuses = false): array
{
    $sql = "SELECT r.batch_id, r.source_row, r.status, r.messages_json, r.raw_json, b.source_file_name,
                   CONCAT(a.last_name, ', ', a.first_name) AS student
            FROM import_staging_rows r
            JOIN import_batches b ON b.id = r.batch_id
            LEFT JOIN student_assignments a ON a.id = b.student_assignment_id
            WHERE b.status IN ('staged', 'committed')" . ($batchId ? ' AND r.batch_id = ?' : '') . '
            ORDER BY b.source_file_name, r.source_row';
    $st = $pdo->prepare($sql);
    $st->execute($batchId ? [$batchId] : []);
    $out = [];
    while ($r = $st->fetch()) {
        $raw = json_decode((string)$r['raw_json'], true) ?: [];
        foreach (json_decode((string)$r['messages_json'], true) ?: [] as $m) {
            $out[] = [
                'batch_id' => (int)$r['batch_id'], 'file' => $r['source_file_name'], 'student' => $r['student'] ?? 'Unknown',
                'row' => (int)$r['source_row'], 'status' => $r['status'], 'level' => $m['level'] ?? '', 'field' => $m['field'] ?? '',
                'message' => $m['text'] ?? '', 'name' => $raw['name'] ?? '', 'url' => $raw['url'] ?? '',
            ];
        }
    }
    return $out;
}

/** "Deadline 2026-02-06 has already passed." and "Deadline 2027-01-01 has already passed." count as the same kind of issue. */
function issue_pattern(string $message): string
{
    $m = preg_replace('/"[^"]*"/u', '"..."', $message);
    $m = preg_replace('/\d{4}-\d{2}-\d{2}/', 'DATE', $m);
    $m = preg_replace('/#\d+/', '#N', $m);
    $m = preg_replace('/\brow \d+/i', 'row N', $m);
    return preg_replace('/\b\d+(\.\d+)?\b/', 'N', $m);
}

/** Group issue rows by kind: [['level','field','pattern','count','files','example'], ...] most common first. */
function import_issue_summary(array $rows): array
{
    $g = [];
    foreach ($rows as $r) {
        $k = $r['level'] . '|' . $r['field'] . '|' . issue_pattern($r['message']);
        if (!isset($g[$k])) { $g[$k] = ['level' => $r['level'], 'field' => $r['field'], 'pattern' => issue_pattern($r['message']), 'count' => 0, 'files' => [], 'example' => $r['message']]; }
        $g[$k]['count']++;
        $g[$k]['files'][$r['file']] = true;
    }
    foreach ($g as &$x) { $x['files'] = count($x['files']); }
    usort($g, fn($a, $b) => [$b['count'], $a['pattern']] <=> [$a['count'], $b['pattern']]);
    return array_values($g);
}

/** Per-file totals for the import issues report. */
function import_file_summary(PDO $pdo): array
{
    return $pdo->query("SELECT b.id, b.source_file_name, b.status, b.uploaded_at, b.rows_total, b.rows_ready, b.rows_warning, b.rows_duplicate, b.rows_error,
                               CONCAT(a.last_name, ', ', a.first_name) AS student
                        FROM import_batches b LEFT JOIN student_assignments a ON a.id = b.student_assignment_id
                        WHERE b.status IN ('staged', 'committed') ORDER BY b.source_file_name, b.id")->fetchAll();
}
