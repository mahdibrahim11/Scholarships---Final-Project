<?php
/**
 * Iteration 6: CSV export (FR-DQ-03). Writes the full or a filtered dataset using the SAME column names as the import template,
 * so an exported file can be uploaded again and shared with the Data Science class. Admin only: it includes the students'
 * notes. Every query is a prepared statement; user input never reaches the SQL text.
 */
require_once __DIR__ . '/import/pipeline.php';
require_once __DIR__ . '/audit.php';

/** Filters the export page accepts. Anything not in this list is ignored. */
function export_params(array $in): array
{
    $pick = fn(string $k, array $allowed, string $default) => in_array($in[$k] ?? $default, $allowed, true) ? ($in[$k] ?? $default) : $default;
    $state = strtoupper(trim((string)($in['state'] ?? '')));
    return [
        'status'          => $pick('status', ['active', 'archived', 'all'], 'active'),
        'scope'           => $pick('scope', ['all', 'national', 'state_local'], 'all'),
        'state'           => preg_match('/^[A-Z]{2}$/', $state) ? $state : '',
        'theme'           => max(0, (int)($in['theme'] ?? 0)),
        'student'         => max(0, (int)($in['student'] ?? 0)),
        'level'           => $pick('level', ['', 'middle', 'high', 'both'], ''),
        'expired'         => $pick('expired', ['include', 'exclude', 'only'], 'include'),
        'include_student' => !empty($in['include_student']) ? 1 : 0,
        // The form sends f=1; without it (first visit, or a plain link) notes are included.
        'include_notes'   => isset($in['f']) ? (!empty($in['include_notes']) ? 1 : 0) : 1,
    ];
}

/** [WHERE sql, params]. */
function export_where(array $p): array
{
    $w = ['1=1'];
    $a = [];
    if ($p['status'] !== 'all') { $w[] = 's.status = ?'; $a[] = $p['status']; }
    if ($p['scope'] !== 'all') { $w[] = 's.scope = ?'; $a[] = $p['scope']; }
    if ($p['state'] !== '') { $w[] = 's.state_code = ?'; $a[] = $p['state']; }
    if ($p['theme'] > 0) { $w[] = 's.national_theme_id = ?'; $a[] = $p['theme']; }
    if ($p['student'] > 0) { $w[] = 's.contributing_student_id = ?'; $a[] = $p['student']; }
    if ($p['level'] !== '') { $w[] = 's.school_level = ?'; $a[] = $p['level']; }
    if ($p['expired'] === 'exclude') { $w[] = '(s.is_rolling = 1 OR s.deadline IS NULL OR s.deadline >= CURDATE())'; }
    if ($p['expired'] === 'only') { $w[] = '(s.is_rolling = 0 AND s.deadline IS NOT NULL AND s.deadline < CURDATE())'; }
    return [implode(' AND ', $w), $a];
}

/** The header row: the template's own labels, plus "Contributing student" only when asked for (combined file for re-upload). */
function export_headers(array $p): array
{
    $h = array_values(template_columns());
    if (empty($p['include_notes'])) { $h = array_values(array_filter($h, fn($x) => $x !== template_columns()['notes'])); }
    if (!empty($p['include_student'])) { $h[] = 'Contributing student'; }
    return $h;
}

/** Stops spreadsheet formula injection: a text cell that starts with = + - @ (or tab/CR) gets a leading apostrophe. */
function csv_safe(string $v): string
{
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}

/** [6,7,8,9,10,11,12] -> "6-12"; [9,10,12] -> "9-10, 12". */
function grades_compact(array $grades): string
{
    sort($grades);
    $out = [];
    $start = $prev = null;
    foreach ($grades as $g) {
        $g = (int)$g;
        if ($start === null) { $start = $prev = $g; continue; }
        if ($g === $prev + 1) { $prev = $g; continue; }
        $out[] = $start === $prev ? (string)$start : "$start-$prev";
        $start = $prev = $g;
    }
    if ($start !== null) { $out[] = $start === $prev ? (string)$start : "$start-$prev"; }
    return implode(', ', $out);
}

function _num(?string $n): string
{
    if ($n === null || $n === '') { return ''; }
    $f = (float)$n;
    return $f == floor($f) ? (string)(int)$f : rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
}

/** One database row -> the cell values in export_headers() order. */
function export_row(array $r, array $p): array
{
    $level = ['middle' => 'Middle school', 'high' => 'High school', 'both' => 'Both'][$r['school_level'] ?? ''] ?? '';
    if ($r['award_min'] === null && $r['award_max'] === null) { $award = 'Varies'; }
    elseif ($r['award_min'] === null) { $award = 'Up to ' . _num($r['award_max']); }
    elseif ($r['award_max'] === null || (float)$r['award_min'] === (float)$r['award_max']) { $award = _num($r['award_min']); }
    else { $award = _num($r['award_min']) . ' - ' . _num($r['award_max']); }
    $deadline = (int)$r['is_rolling'] === 1 ? 'Rolling' : ($r['deadline'] ?? '');

    $text = [
        $r['name'], $r['provider'], $r['provider_type'] ?? '', $r['official_url'], $r['discovery_source'] ?? '',
        $r['scope'] === 'national' ? 'National' : $r['state_name'],
        $r['scope'] === 'national' ? 'National' : 'State/local',
        null,                                  // grades (index 7) set below
        $level, $r['categories'] ?? '', $r['fields'] ?? '',
    ];
    $row = array_map(fn($v) => $v === null ? '' : csv_safe((string)$v), $text);
    $row[7] = grades_compact(array_map('intval', array_filter(explode(',', (string)($r['grades'] ?? '')), 'strlen')));
    $row[] = $award;
    $row[] = _num($r['num_awards']);
    $row[] = $r['renewable'];
    $row[] = _num($r['max_potential_value']);
    $row[] = $r['open_date'] ?? '';
    $row[] = $deadline;
    $row[] = csv_safe((string)($r['residency_requirement'] ?? ''));
    $row[] = _num($r['min_gpa']);
    foreach (['need_required', 'essay_required', 'recommendation_required', 'transcript_required', 'community_service_required', 'leadership_required'] as $c) { $row[] = $r[$c]; }
    $row[] = csv_safe((string)($r['other_requirements'] ?? ''));
    $row[] = csv_safe((string)($r['application_method'] ?? ''));
    $row[] = $r['verification_date'] ?? '';
    if (!empty($p['include_notes'])) { $row[] = csv_safe((string)($r['notes'] ?? '')); }
    if (!empty($p['include_student'])) { $row[] = csv_safe((string)($r['student'] ?? '')); }
    return $row;
}

function export_sql(array $p): array
{
    [$where, $args] = export_where($p);
    $sql = "SELECT s.*, p.name AS provider, pt.name AS provider_type, st.name AS state_name,
                   CONCAT(a.last_name, '_', a.first_name) AS student,
                   (SELECT GROUP_CONCAT(g.grade ORDER BY g.grade) FROM scholarship_grades g WHERE g.scholarship_id = s.id) AS grades,
                   (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR '; ') FROM scholarship_categories sc JOIN categories c ON c.id = sc.category_id WHERE sc.scholarship_id = s.id) AS categories,
                   (SELECT GROUP_CONCAT(f.name ORDER BY f.name SEPARATOR '; ') FROM scholarship_fields sf JOIN fields_of_study f ON f.id = sf.field_id WHERE sf.scholarship_id = s.id) AS fields
            FROM scholarships s
            JOIN providers p ON p.id = s.provider_id
            LEFT JOIN provider_types pt ON pt.id = p.provider_type_id
            JOIN states st ON st.code = s.state_code
            LEFT JOIN student_assignments a ON a.id = s.contributing_student_id
            WHERE $where ORDER BY st.name, s.name, s.id";
    return [$sql, $args];
}

function export_count(PDO $pdo, array $p): int
{
    [$where, $args] = export_where($p);
    $st = $pdo->prepare("SELECT COUNT(*) FROM scholarships s WHERE $where");
    $st->execute($args);
    return (int)$st->fetchColumn();
}

/** Write the CSV (UTF-8 with BOM so Excel shows accents) to an open stream. Returns the number of data rows. */
function export_write(PDO $pdo, array $p, $out): int
{
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, export_headers($p), ',', '"', '');
    [$sql, $args] = export_sql($p);
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $n = 0;
    while ($r = $st->fetch()) {
        fputcsv($out, export_row($r, $p), ',', '"', '');
        $n++;
    }
    return $n;
}
