<?php
/**
 * Admin data layer for Iteration 4 (FR-ADM-01 .. FR-ADM-05): validate, create, edit, archive, restore, verify,
 * and merge providers. Every query is a prepared statement; every change writes an audit_log row (FR-SA-05).
 * The pages in admin/scholarships and admin/providers only collect input and show results.
 */
require_once __DIR__ . '/import/cleaner.php';
require_once __DIR__ . '/audit.php';

const YES_NO_UNKNOWN = ['Yes', 'No', 'Unknown'];
const APPLICATION_METHODS = ['Online form', 'Email', 'Mail', 'Other'];

/** Columns of `scholarships` that the edit form sets directly (provider_id and link tables are handled separately). */
const SCHOLARSHIP_SCALARS = [
    'name', 'official_url', 'url_key', 'discovery_source', 'state_code', 'scope', 'national_theme_id', 'school_level',
    'award_min', 'award_max', 'num_awards', 'renewable', 'max_potential_value', 'open_date', 'deadline', 'is_rolling',
    'residency_requirement', 'min_gpa', 'need_required', 'essay_required', 'recommendation_required',
    'transcript_required', 'community_service_required', 'leadership_required', 'other_requirements',
    'application_method', 'verification_date', 'notes',
];

/** Lookup lists for the form's dropdowns and for validation. */
function scholarship_ref(PDO $pdo): array
{
    $pairs = fn(string $sql) => array_column($pdo->query($sql)->fetchAll(PDO::FETCH_NUM), 1, 0);
    return [
        'states'         => $pairs('SELECT code, name FROM states ORDER BY (code = "US") DESC, name'),
        'themes'         => $pairs('SELECT id, name FROM national_themes ORDER BY name'),
        'categories'     => $pairs('SELECT id, name FROM categories ORDER BY name'),
        'fields'         => $pairs('SELECT id, name FROM fields_of_study ORDER BY name'),
        'provider_types' => $pairs('SELECT id, name FROM provider_types ORDER BY id'),
        'grades'         => range(6, 12),
    ];
}

/** Empty values for the "new scholarship" form. */
function blank_scholarship(): array
{
    $b = array_fill_keys(SCHOLARSHIP_SCALARS, '');
    return array_merge($b, [
        'id' => null, 'status' => 'active', 'provider_name' => '', 'provider_type_id' => '', 'provider_id' => null,
        'grades' => [], 'category_ids' => [], 'field_ids' => [],
        'renewable' => 'Unknown', 'need_required' => 'Unknown', 'essay_required' => 'Unknown',
        'recommendation_required' => 'Unknown', 'transcript_required' => 'Unknown',
        'community_service_required' => 'Unknown', 'leadership_required' => 'Unknown', 'is_rolling' => 0,
        'contributing_student' => null, 'import_batch_id' => null,
    ]);
}

/** One scholarship with its provider and many-to-many selections, or null. */
function load_scholarship(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT s.*, p.name AS provider_name, p.provider_type_id,
            CONCAT(a.first_name, ' ', a.last_name) AS contributing_student, t.name AS theme_name, stt.name AS state_name,
            vu.display_name AS verified_by_name
        FROM scholarships s
        JOIN providers p ON p.id = s.provider_id
        JOIN states stt ON stt.code = s.state_code
        LEFT JOIN student_assignments a ON a.id = s.contributing_student_id
        LEFT JOIN national_themes t ON t.id = s.national_theme_id
        LEFT JOIN users vu ON vu.id = s.verified_by
        WHERE s.id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $col = function (string $sql) use ($pdo, $id): array {
        $q = $pdo->prepare($sql);
        $q->execute([$id]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    };
    $row['grades'] = $col('SELECT grade FROM scholarship_grades WHERE scholarship_id = ? ORDER BY grade');
    $row['category_ids'] = $col('SELECT category_id FROM scholarship_categories WHERE scholarship_id = ?');
    $row['field_ids'] = $col('SELECT field_id FROM scholarship_fields WHERE scholarship_id = ?');
    return $row;
}

// ---------------------------------------------------------------------------
// Validation (the server is the authority; the jQuery rules in edit.php only mirror these)
// ---------------------------------------------------------------------------

function _form_date(string $s): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

function _form_money(string $raw, string $label, string $key, array &$errors, float $max): ?string
{
    $s = trim(str_replace(['$', ','], '', $raw));
    if ($s === '') {
        return null;
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
        $errors[$key] = "$label must be a number such as 1000 or 2500.50.";
        return null;
    }
    if ((float)$s > $max) {
        $errors[$key] = "$label is too large.";
        return null;
    }
    return number_format((float)$s, 2, '.', '');
}

/**
 * Validate the submitted form. Returns [$errors, $clean]: $errors is field => message (empty when valid),
 * $clean is ready to pass to save_scholarship(). $id is null for a new scholarship.
 */
function validate_scholarship_input(array $in, array $ref, PDO $pdo, ?int $id, string $status = 'active'): array
{
    $errors = [];
    $t = fn(string $k, int $max = 5000): string => mb_substr(clean_text((string)($in[$k] ?? '')), 0, $max);
    $c = blank_scholarship();

    $c['name'] = $t('name', 255);
    if ($c['name'] === '') { $errors['name'] = 'Enter the scholarship name.'; }

    $c['provider_name'] = $t('provider_name', 255);
    if ($c['provider_name'] === '' || provider_key($c['provider_name']) === '') {
        $errors['provider_name'] = 'Enter the provider (the organization that offers the scholarship).';
    }
    $ptype = (int)($in['provider_type_id'] ?? 0);
    $c['provider_type_id'] = isset($ref['provider_types'][$ptype]) ? $ptype : null;

    $url = trim((string)($in['official_url'] ?? ''));
    if ($url !== '' && !preg_match('#^https?://#i', $url)) { $url = 'https://' . $url; }
    $key = $url === '' ? null : normalize_url($url);
    if ($key === null || mb_strlen($url) > 2048) {
        $errors['official_url'] = 'Enter a full web address for the official page, such as https://www.example.org/apply.';
    } else {
        $c['official_url'] = $url;
        $c['url_key'] = $key;
        if ($status === 'active') {
            $dup = $pdo->prepare("SELECT id, name FROM scholarships WHERE active_url_key = ? AND id <> ?");
            $dup->execute([$key, $id ?? 0]);
            if ($row = $dup->fetch()) {
                $errors['official_url'] = 'Another active scholarship already uses this web address: #' . $row['id'] . ' ' . $row['name'] . '.';
            }
        }
    }
    $c['discovery_source'] = $t('discovery_source', 255);

    $state = strtoupper(trim((string)($in['state_code'] ?? '')));
    if (!isset($ref['states'][$state])) {
        $errors['state_code'] = 'Choose a state, or National.';
    } else {
        $c['state_code'] = $state;
        $c['scope'] = $state === 'US' ? 'national' : 'state_local';
    }
    $theme = (int)($in['national_theme_id'] ?? 0);
    $c['national_theme_id'] = isset($ref['themes'][$theme]) ? $theme : null;

    $grades = array_values(array_unique(array_filter(array_map('intval', (array)($in['grades'] ?? [])), fn($g) => $g >= 6 && $g <= 12)));
    sort($grades);
    if (!$grades) { $errors['grades'] = 'Choose at least one grade from 6 to 12.'; }
    $c['grades'] = $grades;
    $c['school_level'] = derive_school_level($grades);
    $c['category_ids'] = array_values(array_filter(array_map('intval', (array)($in['category_ids'] ?? [])), fn($i) => isset($ref['categories'][$i])));
    $c['field_ids'] = array_values(array_filter(array_map('intval', (array)($in['field_ids'] ?? [])), fn($i) => isset($ref['fields'][$i])));

    $c['award_min'] = _form_money((string)($in['award_min'] ?? ''), 'The smallest award', 'award_min', $errors, 99999999.99);
    $c['award_max'] = _form_money((string)($in['award_max'] ?? ''), 'The largest award', 'award_max', $errors, 99999999.99);
    if ($c['award_min'] !== null && $c['award_max'] !== null && (float)$c['award_min'] > (float)$c['award_max']) {
        $errors['award_max'] = 'The largest award cannot be less than the smallest award.';
    }
    $c['max_potential_value'] = _form_money((string)($in['max_potential_value'] ?? ''), 'The maximum potential value', 'max_potential_value', $errors, 9999999999.99);
    $n = trim((string)($in['num_awards'] ?? ''));
    if ($n !== '') {
        if (!ctype_digit($n) || (int)$n < 1 || (int)$n > 1000000) { $errors['num_awards'] = 'Number of awards must be a whole number of 1 or more.'; }
        else { $c['num_awards'] = (int)$n; }
    }
    foreach (['renewable', 'need_required', 'essay_required', 'recommendation_required', 'transcript_required', 'community_service_required', 'leadership_required'] as $k) {
        $v = (string)($in[$k] ?? 'Unknown');
        $c[$k] = in_array($v, YES_NO_UNKNOWN, true) ? $v : 'Unknown';
    }

    $c['is_rolling'] = !empty($in['is_rolling']) ? 1 : 0;
    foreach (['open_date' => 'Open date', 'deadline' => 'Deadline', 'verification_date' => 'Verification date'] as $k => $label) {
        $s = trim((string)($in[$k] ?? ''));
        if ($s === '') { $c[$k] = null; continue; }
        $d = _form_date($s);
        if ($d === null) { $errors[$k] = "$label must be a date like 2027-03-15."; $c[$k] = null; }
        else { $c[$k] = $d; }
    }
    if ($c['is_rolling']) { $c['deadline'] = null; }   // a rolling scholarship has no fixed deadline
    if ($c['open_date'] && $c['deadline'] && $c['open_date'] > $c['deadline']) {
        $errors['deadline'] = 'The deadline cannot be before the open date.';
    }
    if ($c['verification_date'] && $c['verification_date'] > date('Y-m-d')) {
        $errors['verification_date'] = 'The verification date cannot be in the future.';
    }

    $g = trim((string)($in['min_gpa'] ?? ''));
    if ($g !== '') {
        if (!preg_match('/^\d(\.\d{1,2})?$/', $g) || (float)$g > 4.5) { $errors['min_gpa'] = 'Minimum GPA must be between 0 and 4.5.'; }
        else { $c['min_gpa'] = number_format((float)$g, 2, '.', ''); }
    }
    $c['residency_requirement'] = $t('residency_requirement');
    $c['other_requirements'] = $t('other_requirements');
    $c['notes'] = $t('notes');
    $m = $t('application_method', 100);
    $c['application_method'] = $m;

    // Empty text/number fields are stored as NULL, not ''.
    foreach (['discovery_source', 'residency_requirement', 'other_requirements', 'notes', 'application_method', 'award_min', 'award_max',
              'num_awards', 'max_potential_value', 'min_gpa', 'national_theme_id', 'school_level'] as $k) {
        if ($c[$k] === '' ) { $c[$k] = null; }
    }
    return [$errors, $c];
}

// ---------------------------------------------------------------------------
// Providers
// ---------------------------------------------------------------------------

/** Find a provider by its normalized name, or create it. Returns the provider id. */
function find_or_create_provider(PDO $pdo, string $name, ?int $typeId): int
{
    $key = provider_key($name);
    $find = $pdo->prepare('SELECT id, provider_type_id FROM providers WHERE name_key = ?');
    $find->execute([$key]);
    if ($row = $find->fetch()) {
        if ($row['provider_type_id'] === null && $typeId) {
            $pdo->prepare('UPDATE providers SET provider_type_id = ? WHERE id = ?')->execute([$typeId, $row['id']]);
        }
        return (int)$row['id'];
    }
    $pdo->prepare('INSERT INTO providers (name, name_key, provider_type_id) VALUES (?, ?, ?)')->execute([$name, $key, $typeId]);
    return (int)$pdo->lastInsertId();
}

/** Words that do not help tell two organizations apart ("X Foundation" vs "X Fund"). */
function provider_loose_key(string $nameKey): string
{
    static $stop = ['the', 'of', 'and', '&', 'for', 'foundation', 'fund', 'funds', 'inc', 'llc', 'corp', 'corporation', 'company', 'co',
                    'association', 'assn', 'society', 'council', 'trust', 'organization', 'org', 'institute', 'program', 'programs',
                    'scholarship', 'scholarships'];
    $w = array_values(array_diff(preg_split('/[\s&-]+/', $nameKey, -1, PREG_SPLIT_NO_EMPTY) ?: [], $stop));
    sort($w);
    return implode(' ', $w);
}

/** Every provider with how many scholarships it has. */
function list_providers(PDO $pdo): array
{
    return $pdo->query("SELECT p.id, p.name, p.name_key, p.website, pt.name AS type_name, COUNT(s.id) AS scholarships
        FROM providers p LEFT JOIN provider_types pt ON pt.id = p.provider_type_id
        LEFT JOIN scholarships s ON s.provider_id = p.id
        GROUP BY p.id, p.name, p.name_key, p.website, pt.name ORDER BY p.name")->fetchAll();
}

/**
 * Likely duplicate providers: the same name once filler words are ignored, or names that differ by
 * one or two letters (a typo). Returns [['a' => row, 'b' => row, 'why' => text], ...], smaller provider first.
 */
function provider_duplicate_pairs(array $providers): array
{
    $pairs = [];
    $seen = [];
    $add = function (array $x, array $y, string $why) use (&$pairs, &$seen) {
        if ((int)$x['id'] === (int)$y['id']) { return; }
        [$a, $b] = (int)$x['scholarships'] <= (int)$y['scholarships'] ? [$x, $y] : [$y, $x];
        $k = min($a['id'], $b['id']) . '-' . max($a['id'], $b['id']);
        if (!isset($seen[$k])) { $seen[$k] = true; $pairs[] = ['a' => $a, 'b' => $b, 'why' => $why]; }
    };
    $groups = [];
    foreach ($providers as $p) {
        $lk = provider_loose_key($p['name_key']);
        if ($lk !== '') { $groups[$lk][] = $p; }
    }
    foreach ($groups as $g) {
        for ($i = 0; $i < count($g); $i++) {
            for ($j = $i + 1; $j < count($g); $j++) { $add($g[$i], $g[$j], 'Same name apart from words like "Foundation" or "Inc"'); }
        }
    }
    if (count($providers) <= 1500) {
        for ($i = 0; $i < count($providers); $i++) {
            $x = substr($providers[$i]['name_key'], 0, 250);
            if (strlen($x) < 8) { continue; }
            for ($j = $i + 1; $j < count($providers); $j++) {
                $y = substr($providers[$j]['name_key'], 0, 250);
                if (strlen($y) >= 8 && abs(strlen($x) - strlen($y)) <= 2 && levenshtein($x, $y) <= 2) { $add($providers[$i], $providers[$j], 'Spelled almost the same'); }
            }
        }
    }
    return $pairs;
}

/** Move every scholarship from provider $from to provider $into, then delete $from. Returns scholarships moved. */
function merge_providers(PDO $pdo, int $from, int $into): int
{
    if ($from === $into) {
        throw new RuntimeException('Choose two different providers.');
    }
    $get = $pdo->prepare('SELECT * FROM providers WHERE id = ?');
    $get->execute([$from]); $a = $get->fetch();
    $get->execute([$into]); $b = $get->fetch();
    if (!$a || !$b) {
        throw new RuntimeException('One of those providers no longer exists.');
    }
    $pdo->beginTransaction();
    try {
        $mv = $pdo->prepare('UPDATE scholarships SET provider_id = ?, updated_by = ? WHERE provider_id = ?');
        $mv->execute([$into, current_user_id(), $from]);
        $moved = $mv->rowCount();
        // Keep any detail the surviving provider lacks.
        $pdo->prepare('UPDATE providers SET provider_type_id = COALESCE(provider_type_id, ?), website = COALESCE(website, ?) WHERE id = ?')
            ->execute([$a['provider_type_id'], $a['website'], $into]);
        $pdo->prepare('DELETE FROM providers WHERE id = ?')->execute([$from]);
        audit_log($pdo, 'provider.merge', 'provider', $into, ['merged_id' => $from, 'merged_name' => $a['name'], 'into_name' => $b['name'], 'scholarships_moved' => $moved]);
        $pdo->commit();
        return $moved;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Edit a provider's name, type and website. Returns an error message or null. */
function update_provider(PDO $pdo, int $id, string $name, ?int $typeId, string $website): ?string
{
    $name = mb_substr(clean_text($name), 0, 255);
    $key = provider_key($name);
    if ($key === '') { return 'Enter the provider name.'; }
    $website = trim($website);
    if ($website !== '') {
        if (!preg_match('#^https?://#i', $website)) { $website = 'https://' . $website; }
        if (normalize_url($website) === null || mb_strlen($website) > 500) { return 'Enter a full web address for the website, or leave it blank.'; }
    }
    $dup = $pdo->prepare('SELECT id, name FROM providers WHERE name_key = ? AND id <> ?');
    $dup->execute([$key, $id]);
    if ($row = $dup->fetch()) {
        return 'Another provider already has that name: ' . $row['name'] . '. Merge the two providers instead.';
    }
    $pdo->prepare('UPDATE providers SET name = ?, name_key = ?, provider_type_id = ?, website = ? WHERE id = ?')
        ->execute([$name, $key, $typeId ?: null, $website ?: null, $id]);
    audit_log($pdo, 'provider.edit', 'provider', $id, ['name' => $name]);
    return null;
}

// ---------------------------------------------------------------------------
// Saving
// ---------------------------------------------------------------------------

/** Insert ($id null) or update a scholarship from validated input. Returns the scholarship id. */
function save_scholarship(PDO $pdo, ?int $id, array $c): int
{
    $uid = current_user_id();
    $pdo->beginTransaction();
    try {
        $providerId = find_or_create_provider($pdo, $c['provider_name'], $c['provider_type_id']);
        $vals = array_map(fn($k) => $c[$k], SCHOLARSHIP_SCALARS);

        if ($id === null) {
            $cols = array_merge(['provider_id'], SCHOLARSHIP_SCALARS, ['created_by', 'updated_by']);
            $sql = 'INSERT INTO scholarships (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
            $pdo->prepare($sql)->execute(array_merge([$providerId], $vals, [$uid, $uid]));
            $id = (int)$pdo->lastInsertId();
            $action = 'scholarship.create';
            $details = ['name' => $c['name'], 'state' => $c['state_code']];
        } else {
            $old = load_scholarship($pdo, $id);
            if (!$old) { throw new RuntimeException('That scholarship no longer exists.'); }
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_merge(['provider_id'], SCHOLARSHIP_SCALARS, ['updated_by'])));
            $pdo->prepare("UPDATE scholarships SET $sets WHERE id = ?")->execute(array_merge([$providerId], $vals, [$uid, $id]));
            $changes = [];
            foreach (array_merge(['provider_id'], SCHOLARSHIP_SCALARS) as $k) {
                $new = $k === 'provider_id' ? $providerId : $c[$k];
                if ((string)($old[$k] ?? '') !== (string)($new ?? '')) {
                    $changes[$k] = ['from' => mb_substr((string)($old[$k] ?? ''), 0, 120), 'to' => mb_substr((string)($new ?? ''), 0, 120)];
                }
            }
            if ($old['grades'] !== $c['grades']) { $changes['grades'] = ['from' => implode(',', $old['grades']), 'to' => implode(',', $c['grades'])]; }
            $oc = $old['category_ids']; $nc = $c['category_ids']; sort($oc); sort($nc);
            if ($oc !== $nc) { $changes['categories'] = true; }
            $of = $old['field_ids']; $nf = $c['field_ids']; sort($of); sort($nf);
            if ($of !== $nf) { $changes['fields'] = true; }
            $action = 'scholarship.edit';
            $details = ['name' => $c['name'], 'changed' => $changes];
        }

        // Replace the many-to-many selections.
        foreach (['scholarship_grades', 'scholarship_categories', 'scholarship_fields'] as $table) {
            $pdo->prepare("DELETE FROM $table WHERE scholarship_id = ?")->execute([$id]);
        }
        $g = $pdo->prepare('INSERT INTO scholarship_grades (scholarship_id, grade) VALUES (?, ?)');
        foreach ($c['grades'] as $x) { $g->execute([$id, $x]); }
        $g = $pdo->prepare('INSERT INTO scholarship_categories (scholarship_id, category_id) VALUES (?, ?)');
        foreach ($c['category_ids'] as $x) { $g->execute([$id, $x]); }
        $g = $pdo->prepare('INSERT INTO scholarship_fields (scholarship_id, field_id) VALUES (?, ?)');
        foreach ($c['field_ids'] as $x) { $g->execute([$id, $x]); }

        audit_log($pdo, $action, 'scholarship', $id, $details);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Archive, restore, or mark verified for one or many scholarships (FR-ADM-02, FR-ADM-03).
 * Returns ['done' => n, 'skipped' => [messages]]. Each change is audited.
 */
function scholarship_bulk_action(PDO $pdo, string $action, array $ids): array
{
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0))), 0, 5000);
    $uid = current_user_id();
    $done = 0;
    $skipped = [];
    $pdo->beginTransaction();
    try {
        foreach ($ids as $id) {
            if ($action === 'archive') {
                $st = $pdo->prepare("UPDATE scholarships SET status = 'archived', updated_by = ? WHERE id = ? AND status = 'active'");
                $st->execute([$uid, $id]);
                if ($st->rowCount() === 1) { audit_log($pdo, 'scholarship.archive', 'scholarship', $id); $done++; }
                else { $skipped[] = "#$id was not active."; }
            } elseif ($action === 'verify') {
                $st = $pdo->prepare("UPDATE scholarships SET verification_date = CURDATE(), verified_by = ?, updated_by = ? WHERE id = ? AND status = 'active'");
                $st->execute([$uid, $uid, $id]);
                if ($st->rowCount() >= 1) { audit_log($pdo, 'scholarship.verify', 'scholarship', $id, ['date' => date('Y-m-d')]); $done++; }
                else { $skipped[] = "#$id is archived, so it was not verified."; }
            } elseif ($action === 'restore') {
                $q = $pdo->prepare("SELECT url_key FROM scholarships WHERE id = ? AND status = 'archived'");
                $q->execute([$id]);
                $key = $q->fetchColumn();
                if ($key === false) { $skipped[] = "#$id is not archived."; continue; }
                $dup = $pdo->prepare("SELECT id FROM scholarships WHERE active_url_key = ?");
                $dup->execute([$key]);
                if ($other = $dup->fetchColumn()) { $skipped[] = "#$id was not restored: active scholarship #$other already uses its web address."; continue; }
                $pdo->prepare("UPDATE scholarships SET status = 'active', updated_by = ? WHERE id = ?")->execute([$uid, $id]);
                audit_log($pdo, 'scholarship.restore', 'scholarship', $id);
                $done++;
            } else {
                throw new InvalidArgumentException('Unknown action.');
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['done' => $done, 'skipped' => $skipped];
}
