<?php
/**
 * Public scholarship search (Iteration 5).
 *
 *   search_params()   turns raw $_GET into clean, typed filters (anything unexpected is dropped)
 *   search_run()      runs the search: filters, keyword, sort, paging; every value is a bound parameter
 *   search_log()      anonymous log of state / grade / field only (no user id, no IP, no keyword)
 *   search_options()  the lists that fill the filter drop-downs
 *
 * Only ACTIVE scholarships are ever returned. Archived ones never show on public pages.
 */

const SEARCH_PER_PAGE = 20;
const SEARCH_SORTS = [
    'deadline' => 'Deadline (soonest first)',
    'award'    => 'Award (largest first)',
    'name'     => 'Name (A to Z)',
    'verified' => 'Recently verified',
];
const SEARCH_MIN_AWARDS = [500, 1000, 2500, 5000, 10000];
const SEARCH_DEADLINE_WINDOWS = ['30' => 'Due in the next 30 days', '60' => 'Due in the next 60 days', '90' => 'Due in the next 90 days', 'rolling' => 'Rolling (no fixed deadline)'];

/** Clean, typed filters from a raw query array. Unknown keys and bad values are ignored. */
function search_params(array $in): array
{
    $str = fn(string $k, int $max = 100) => mb_substr(trim((string)(is_array($in[$k] ?? '') ? '' : ($in[$k] ?? ''))), 0, $max);
    $int = fn(string $k) => ctype_digit($str($k, 9)) ? (int)$str($k, 9) : 0;
    $flag = fn(string $k) => $str($k, 1) === '1';

    $state = strtoupper($str('state', 2));
    $grade = $int('grade');
    $sort = $str('sort', 12);
    $dl = $str('due', 7);
    $level = $str('level', 6);
    $gpa = str_replace(',', '.', $str('gpa', 4));

    $q = preg_split('/\s+/u', $str('q', 120), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $q = array_slice(array_map(fn($w) => mb_substr($w, 0, 40), $q), 0, 6);

    return [
        'q'         => implode(' ', $q),
        'state'     => preg_match('/^[A-Z]{2}$/', $state) ? $state : '',
        'grade'     => ($grade >= 6 && $grade <= 12) ? $grade : 0,
        'field'     => $int('field'),
        'category'  => $int('category'),
        'theme'     => $int('theme'),
        'level'     => in_array($level, ['middle', 'high'], true) ? $level : '',
        'min_award' => in_array($int('min_award'), SEARCH_MIN_AWARDS, true) ? $int('min_award') : 0,
        'due'       => isset(SEARCH_DEADLINE_WINDOWS[$dl]) ? $dl : '',
        'gpa'       => preg_match('/^\d(\.\d{1,2})?$/', $gpa) && (float)$gpa <= 4.5 ? $gpa : '',
        'no_essay'  => $flag('no_essay'),
        'no_need'   => $flag('no_need'),
        'no_rec'    => $flag('no_rec'),
        'renewable' => $flag('renewable'),
        'expired'   => $flag('expired'),
        'sort'      => isset(SEARCH_SORTS[$sort]) ? $sort : 'deadline',
        'page'      => max(1, min(100000, $int('page') ?: 1)),
    ];
}

/** The filters as a query string (defaults left out), for links, pagination and the browser address bar. */
function search_query_string(array $p, array $override = []): string
{
    $p = array_merge($p, $override);
    $defaults = ['sort' => 'deadline', 'page' => 1];
    $out = [];
    foreach (['q', 'state', 'grade', 'field', 'category', 'theme', 'level', 'min_award', 'due', 'gpa', 'sort', 'page'] as $k) {
        if (isset($p[$k]) && $p[$k] !== '' && $p[$k] !== 0 && ($defaults[$k] ?? null) !== $p[$k]) { $out[$k] = $p[$k]; }
    }
    foreach (['no_essay', 'no_need', 'no_rec', 'renewable', 'expired'] as $k) {
        if (!empty($p[$k])) { $out[$k] = '1'; }
    }
    return http_build_query($out);
}

/** Escape LIKE wildcards so a keyword like "100%" or "a_b" is matched literally. */
function search_like(string $s): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s) . '%';
}

/** WHERE clause (always starts with active-only) plus its positional parameters. */
function search_where(array $p): array
{
    $w = ["s.status = 'active'"];
    $a = [];

    // State: a family in MN sees MN scholarships AND national ones. "US" means national only.
    if ($p['state'] !== '') {
        if ($p['state'] === 'US') { $w[] = "s.state_code = 'US'"; }
        else { $w[] = "s.state_code IN (?, 'US')"; $a[] = $p['state']; }
    }
    if ($p['grade'] > 0) {
        $w[] = 'EXISTS (SELECT 1 FROM scholarship_grades g WHERE g.scholarship_id = s.id AND g.grade = ?)';
        $a[] = $p['grade'];
    }
    // Field: a scholarship open to any field ("Any / undecided", or no field listed) matches every field choice.
    if ($p['field'] > 0) {
        $w[] = '(EXISTS (SELECT 1 FROM scholarship_fields f WHERE f.scholarship_id = s.id AND f.field_id = ?)
                 OR EXISTS (SELECT 1 FROM scholarship_fields f JOIN fields_of_study fo ON fo.id = f.field_id
                            WHERE f.scholarship_id = s.id AND fo.name = \'Any / undecided\')
                 OR NOT EXISTS (SELECT 1 FROM scholarship_fields f WHERE f.scholarship_id = s.id))';
        $a[] = $p['field'];
    }
    if ($p['category'] > 0) {
        $w[] = 'EXISTS (SELECT 1 FROM scholarship_categories c WHERE c.scholarship_id = s.id AND c.category_id = ?)';
        $a[] = $p['category'];
    }
    if ($p['theme'] > 0) { $w[] = 's.national_theme_id = ?'; $a[] = $p['theme']; }
    if ($p['level'] !== '') { $w[] = "s.school_level IN (?, 'both')"; $a[] = $p['level']; }
    if ($p['min_award'] > 0) { $w[] = 'COALESCE(s.award_max, s.award_min) >= ?'; $a[] = $p['min_award']; }
    if ($p['gpa'] !== '') { $w[] = '(s.min_gpa IS NULL OR s.min_gpa <= ?)'; $a[] = $p['gpa']; }
    if ($p['no_essay']) { $w[] = "s.essay_required = 'No'"; }
    if ($p['no_need']) { $w[] = "s.need_required = 'No'"; }
    if ($p['no_rec']) { $w[] = "s.recommendation_required = 'No'"; }
    if ($p['renewable']) { $w[] = "s.renewable = 'Yes'"; }

    if ($p['due'] === 'rolling') { $w[] = 's.is_rolling = 1'; }
    elseif ($p['due'] !== '') { $w[] = 's.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . (int)$p['due'] . ' DAY)'; }

    // Past deadlines are hidden unless the visitor asks for them.
    if (!$p['expired']) { $w[] = '(s.deadline IS NULL OR s.deadline >= CURDATE())'; }

    // Keyword: every word must appear somewhere in the name, provider, residency, requirements, a field or a category.
    foreach (($p['q'] === '' ? [] : explode(' ', $p['q'])) as $word) {
        $like = search_like($word);
        $w[] = '(s.name LIKE ? OR pr.name LIKE ? OR s.residency_requirement LIKE ? OR s.other_requirements LIKE ?
                 OR EXISTS (SELECT 1 FROM scholarship_fields f JOIN fields_of_study fo ON fo.id = f.field_id WHERE f.scholarship_id = s.id AND fo.name LIKE ?)
                 OR EXISTS (SELECT 1 FROM scholarship_categories c JOIN categories ca ON ca.id = c.category_id WHERE c.scholarship_id = s.id AND ca.name LIKE ?))';
        array_push($a, $like, $like, $like, $like, $like, $like);
    }
    return [implode(' AND ', $w), $a];
}

/** ORDER BY text from a fixed list (never from user text). Ties end on id so paging is stable. */
function search_order(string $sort): string
{
    $award = 'COALESCE(s.award_max, s.award_min)';
    return match ($sort) {
        'award'    => "($award IS NULL), $award DESC, s.name ASC, s.id ASC",
        'name'     => 's.name ASC, s.id ASC',
        'verified' => '(s.verification_date IS NULL), s.verification_date DESC, s.name ASC, s.id ASC',
        // Upcoming deadlines first (soonest), then rolling / none, then past ones (most recent first).
        default    => 'CASE WHEN s.deadline >= CURDATE() THEN 0 WHEN s.deadline IS NULL THEN 1 ELSE 2 END,
                       IF(s.deadline IS NULL, 0, ABS(DATEDIFF(s.deadline, CURDATE()))), s.name ASC, s.id ASC',
    };
}

/** Run a search. Returns rows, total, page, pages, per_page, from, to. */
function search_run(PDO $pdo, array $p, int $perPage = SEARCH_PER_PAGE): array
{
    [$where, $args] = search_where($p);
    $from = 'FROM scholarships s JOIN providers pr ON pr.id = s.provider_id WHERE ' . $where;

    $st = $pdo->prepare('SELECT COUNT(*) ' . $from);
    $st->execute($args);
    $total = (int)$st->fetchColumn();

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($p['page'], $pages);
    $offset = ($page - 1) * $perPage;          // both ints, so safe in the SQL text

    $st = $pdo->prepare('SELECT s.id, s.name, s.official_url, s.state_code, st.name AS state_name, s.scope, s.school_level,
            s.award_min, s.award_max, s.deadline, s.is_rolling, s.verification_date, pr.name AS provider,
            s.essay_required, s.need_required, s.min_gpa
        FROM scholarships s JOIN providers pr ON pr.id = s.provider_id JOIN states st ON st.code = s.state_code
        WHERE ' . $where . ' ORDER BY ' . search_order($p['sort']) . " LIMIT $perPage OFFSET $offset");
    $st->execute($args);
    $rows = $st->fetchAll();

    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage,
            'from' => $total ? $offset + 1 : 0, 'to' => $offset + count($rows)];
}

/**
 * Anonymous statistics for later reporting. Stores only state, grade and field, only when at least one of them was chosen.
 * Never stores who searched, an IP address, or the keyword. A logging failure must never break a search.
 */
function search_log(PDO $pdo, array $p): void
{
    if ($p['state'] === '' && $p['grade'] === 0 && $p['field'] === 0) { return; }
    try {
        $pdo->prepare('INSERT INTO search_log (state_code, grade, field_id) VALUES (?, ?, ?)')
            ->execute([$p['state'] ?: null, $p['grade'] ?: null, $p['field'] ?: null]);
    } catch (Throwable $e) {
        error_log('search_log failed: ' . $e->getMessage());
    }
}

/** Reference lists for the filter drop-downs. */
function search_options(PDO $pdo): array
{
    return [
        'states'     => $pdo->query('SELECT code, name FROM states ORDER BY (code = "US") DESC, name')->fetchAll(),
        'grades'     => $pdo->query('SELECT grade, label FROM grade_levels ORDER BY grade')->fetchAll(),
        'fields'     => $pdo->query('SELECT id, name FROM fields_of_study ORDER BY (name = "Any / undecided"), name')->fetchAll(),
        'categories' => $pdo->query('SELECT id, name FROM categories ORDER BY (name = "Other"), name')->fetchAll(),
        'themes'     => $pdo->query('SELECT id, name FROM national_themes ORDER BY name')->fetchAll(),
    ];
}

// ---- display helpers (public pages) -------------------------------------------------------------

function deadline_html(?string $d, $rolling): string
{
    if ($d === null) { return $rolling ? '<span class="deadline">Rolling</span>' : '<span class="text-muted">Not listed</span>'; }
    $days = (int)floor((strtotime($d) - strtotime('today')) / 86400);
    $cls = $days < 0 ? 'past' : ($days <= 30 ? 'soon' : '');
    $label = e(date('M j, Y', strtotime($d)));
    return '<span class="deadline ' . $cls . '">' . $label . '</span>' . ($days < 0 ? ' <span class="text-muted">(passed)</span>' : '');
}

/** Only ever link to http(s) addresses. */
function safe_external_url(?string $u): ?string
{
    return $u !== null && preg_match('#^https?://[^\s<>"]+$#i', $u) ? $u : null;
}

function state_label(string $code, ?string $name): string
{
    return $code === 'US' ? 'National' : (string)($name ?: $code);
}

/**
 * The results block: count line, cards (phones and tablets), table (large screens), pager.
 * Used by search.php on first load and by search_results.php for AJAX updates, so both always match.
 */
function search_render_results(array $res, array $p): string
{
    ob_start();
    $from = search_query_string($p);   // handed to the detail page so its "Back to results" link works
    $detail = fn(int $id) => url('scholarship.php?id=' . $id . ($from !== '' ? '&from=' . rawurlencode($from) : ''));
    require_once __DIR__ . '/profiles.php';
    $ctx = save_context(db());   // what the Save button does for this visitor (FR-USER-04)
    ?>
    <p class="mb-2" id="result-count" role="status">
      <?php if ($res['total'] === 0): ?>
        <strong>0</strong> scholarships found.
      <?php else: ?>
        Showing <strong><?= number_format($res['from']) ?> to <?= number_format($res['to']) ?></strong> of <strong><?= number_format($res['total']) ?></strong> scholarship<?= $res['total'] === 1 ? '' : 's' ?>.
      <?php endif; ?>
    </p>
    <?php if (!$res['rows']): ?>
      <div class="empty-state">
        <p class="mb-1"><strong>No scholarships match those choices.</strong></p>
        <p class="mb-0">Try choosing "Any" for the grade or field, removing a keyword, or turning off some of the extra filters.
          <?php if (!$p['expired']): ?>You can also include scholarships whose deadline has passed.<?php endif; ?></p>
      </div>
    <?php else: ?>
      <div class="d-lg-none" aria-label="Scholarship results">
        <?php foreach ($res['rows'] as $r): ?>
          <article class="card-result">
            <h2 class="h5 mb-1"><a href="<?= e($detail((int)$r['id'])) ?>"><?= e($r['name']) ?></a></h2>
            <p class="mb-1 text-muted"><?= e($r['provider']) ?> · <?= e(state_label($r['state_code'], $r['state_name'])) ?></p>
            <p class="mb-0"><strong><?= e(fmt_money_range($r['award_min'], $r['award_max'])) ?></strong> · Due <?= deadline_html($r['deadline'], $r['is_rolling']) ?></p>
            <?php if ($r['verification_date']): ?><p class="small text-muted mb-0">Verified <?= e(date('M j, Y', strtotime($r['verification_date']))) ?></p><?php endif; ?>
            <?php if ($ctx['mode'] !== 'none'): ?><p class="mt-2 mb-0"><?= save_button($ctx, (int)$r['id'], $r['name']) ?></p><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="table-responsive d-none d-lg-block">
        <table class="table table-hover align-middle table-results">
          <caption class="visually-hidden">Scholarships matching your choices</caption>
          <thead><tr><th scope="col">Scholarship</th><th scope="col">Provider</th><th scope="col">State</th><th scope="col">Amount</th><th scope="col">Deadline</th><th scope="col">Verified</th><?php if ($ctx['mode'] !== 'none'): ?><th scope="col"><span class="visually-hidden">Save</span></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($res['rows'] as $r): ?>
            <tr>
              <td><a href="<?= e($detail((int)$r['id'])) ?>"><?= e($r['name']) ?></a></td>
              <td><?= e($r['provider']) ?></td>
              <td><?= e(state_label($r['state_code'], $r['state_name'])) ?></td>
              <td><?= e(fmt_money_range($r['award_min'], $r['award_max'])) ?></td>
              <td><?= deadline_html($r['deadline'], $r['is_rolling']) ?></td>
              <td><?= $r['verification_date'] ? e(date('M j, Y', strtotime($r['verification_date']))) : '<span class="text-muted">Not listed</span>' ?></td>
              <?php if ($ctx['mode'] !== 'none'): ?><td class="text-nowrap"><?= save_button($ctx, (int)$r['id'], $r['name']) ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($res['pages'] > 1):
          $href = fn(int $pg) => url('search.php') . (($qs = search_query_string($p, ['page' => $pg])) !== '' ? '?' . $qs : '');
          $lo = max(1, $res['page'] - 2); $hi = min($res['pages'], $res['page'] + 2);
      ?>
      <nav aria-label="Result pages"><ul class="pagination flex-wrap">
        <li class="page-item <?= $res['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($href($res['page'] - 1)) ?>" <?= $res['page'] <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a></li>
        <?php if ($lo > 1): ?><li class="page-item"><a class="page-link" href="<?= e($href(1)) ?>">1</a></li><?php if ($lo > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endif; ?>
        <?php for ($i = $lo; $i <= $hi; $i++): ?>
          <li class="page-item <?= $i === $res['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e($href($i)) ?>" <?= $i === $res['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a></li>
        <?php endfor; ?>
        <?php if ($hi < $res['pages']): if ($hi < $res['pages'] - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= e($href($res['pages'])) ?>"><?= $res['pages'] ?></a></li><?php endif; ?>
        <li class="page-item <?= $res['page'] >= $res['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($href($res['page'] + 1)) ?>" <?= $res['page'] >= $res['pages'] ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a></li>
      </ul></nav>
      <?php endif; ?>
    <?php endif;
    return (string)ob_get_clean();
}

/**
 * One scholarship for the public detail page, or null if it does not exist or is archived.
 * Only fields that are safe to show the public: no contributing student, import batch, internal notes or audit data.
 */
function scholarship_public(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT s.id, s.name, s.official_url, s.state_code, st.name AS state_name, s.scope, s.school_level,
            s.award_min, s.award_max, s.num_awards, s.renewable, s.max_potential_value, s.open_date, s.deadline, s.is_rolling,
            s.residency_requirement, s.min_gpa, s.need_required, s.essay_required, s.recommendation_required,
            s.transcript_required, s.community_service_required, s.leadership_required, s.other_requirements,
            s.application_method, s.verification_date, pr.name AS provider, pr.website AS provider_website,
            pt.name AS provider_type, th.name AS theme
        FROM scholarships s
        JOIN providers pr ON pr.id = s.provider_id
        JOIN states st ON st.code = s.state_code
        LEFT JOIN provider_types pt ON pt.id = pr.provider_type_id
        LEFT JOIN national_themes th ON th.id = s.national_theme_id
        WHERE s.id = ? AND s.status = 'active'");
    $st->execute([$id]);
    $s = $st->fetch();
    if (!$s) { return null; }

    $q = $pdo->prepare('SELECT grade FROM scholarship_grades WHERE scholarship_id = ? ORDER BY grade');
    $q->execute([$id]);
    $s['grades'] = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));

    $q = $pdo->prepare('SELECT c.name FROM scholarship_categories sc JOIN categories c ON c.id = sc.category_id WHERE sc.scholarship_id = ? ORDER BY c.name');
    $q->execute([$id]);
    $s['categories'] = $q->fetchAll(PDO::FETCH_COLUMN);

    $q = $pdo->prepare('SELECT f.name FROM scholarship_fields sf JOIN fields_of_study f ON f.id = sf.field_id WHERE sf.scholarship_id = ? ORDER BY f.name');
    $q->execute([$id]);
    $s['fields'] = $q->fetchAll(PDO::FETCH_COLUMN);
    return $s;
}

/** "9, 10, 11, 12" -> "9 to 12" when the grades are one unbroken run. */
function grades_label(array $grades): string
{
    if (!$grades) { return 'Not listed'; }
    sort($grades);
    $run = count($grades) > 1 && ($grades[count($grades) - 1] - $grades[0] === count($grades) - 1);
    return $run ? 'Grades ' . $grades[0] . ' to ' . $grades[count($grades) - 1] : 'Grade' . (count($grades) > 1 ? 's ' : ' ') . implode(', ', $grades);
}
