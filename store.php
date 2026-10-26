<?php
/**
 * Database side of the import: staging (FR-IMP-03), commit in a transaction (FR-IMP-08),
 * batch bookkeeping (FR-IMP-09), and rollback (FR-IMP-10). Every query is a prepared statement.
 */
require_once __DIR__ . '/pipeline.php';
require_once __DIR__ . '/../includes/audit.php';

/** Lookup data that validate_file() needs. Build once per upload session and pass by reference. */
function load_import_context(PDO $pdo): array
{
    $existing = [];
    foreach ($pdo->query("SELECT id, url_key FROM scholarships WHERE status = 'active'") as $r) {
        $existing[$r['url_key']] = (int)$r['id'];
    }
    $assignments = [];
    foreach ($pdo->query('SELECT * FROM student_assignments') as $r) {
        $assignments[strtolower($r['last_name'] . '|' . $r['first_name'])] = $r;
    }
    return [
        'existing'    => $existing,
        'assignments' => $assignments,
        'categories'  => $pdo->query('SELECT name FROM categories')->fetchAll(PDO::FETCH_COLUMN),
        'fields'      => $pdo->query('SELECT name FROM fields_of_study')->fetchAll(PDO::FETCH_COLUMN),
    ];
}

/** Save a validated file to staging. Nothing touches the live table yet. Returns the batch id. */
function stage_batch(PDO $pdo, array $validated): int
{
    $c = $validated['counts'];
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO import_batches
            (source_file_name, student_assignment_id, uploaded_by, rows_total, rows_ready, rows_warning, rows_duplicate, rows_error)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$validated['file'], $validated['assignment']['id'] ?? null, current_user_id(),
                      $c['total'], $c['ready'], $c['warning'], $c['duplicate'], $c['error']]);
        $batchId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO import_staging_rows (batch_id, source_row, raw_json, cleaned_json, status, messages_json, url_key)
                              VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($validated['rows'] as $r) {
            $ins->execute([$batchId, $r['row'],
                json_encode($r['raw'], JSON_UNESCAPED_UNICODE),
                $r['clean'] ? json_encode($r['clean'], JSON_UNESCAPED_UNICODE) : null,
                $r['status'],
                json_encode($r['messages'], JSON_UNESCAPED_UNICODE),
                $r['url_key']]);
        }
        audit_log($pdo, 'import.stage', 'import_batch', $batchId, ['file' => $validated['file'], 'counts' => $c]);
        $pdo->commit();
        return $batchId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Commit a staged batch: insert every Ready and Warning row, in ONE transaction.
 * If anything fails, nothing is inserted. Returns ['inserted' => n, 'skipped_duplicates' => n].
 */
function commit_batch(PDO $pdo, int $batchId): array
{
    $b = $pdo->prepare('SELECT * FROM import_batches WHERE id = ?');
    $b->execute([$batchId]);
    $batch = $b->fetch();
    if (!$batch || $batch['status'] !== 'staged') {
        throw new RuntimeException('Batch not found or already committed/rolled back.');
    }

    $types = [];
    foreach ($pdo->query('SELECT id, name FROM provider_types') as $r) { $types[strtolower($r['name'])] = (int)$r['id']; }
    $cats = [];
    foreach ($pdo->query('SELECT id, name FROM categories') as $r) { $cats[strtolower($r['name'])] = (int)$r['id']; }
    $flds = [];
    foreach ($pdo->query('SELECT id, name FROM fields_of_study') as $r) { $flds[strtolower($r['name'])] = (int)$r['id']; }

    $rows = $pdo->prepare("SELECT * FROM import_staging_rows WHERE batch_id = ? AND status IN ('ready','warning') ORDER BY source_row");
    $rows->execute([$batchId]);

    $findProv = $pdo->prepare('SELECT id FROM providers WHERE name_key = ?');
    $newProv = $pdo->prepare('INSERT INTO providers (name, name_key, provider_type_id) VALUES (?, ?, ?)');
    $liveUrl = $pdo->prepare("SELECT id FROM scholarships WHERE active_url_key = ?");
    $insS = $pdo->prepare('INSERT INTO scholarships
        (name, provider_id, official_url, url_key, discovery_source, state_code, scope, national_theme_id, school_level,
         award_min, award_max, num_awards, renewable, max_potential_value, open_date, deadline, is_rolling,
         residency_requirement, min_gpa, need_required, essay_required, recommendation_required, transcript_required,
         community_service_required, leadership_required, other_requirements, application_method, verification_date,
         notes, contributing_student_id, import_batch_id, created_by, updated_by)
        VALUES (?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?)');
    $insG = $pdo->prepare('INSERT IGNORE INTO scholarship_grades (scholarship_id, grade) VALUES (?, ?)');
    $insC = $pdo->prepare('INSERT IGNORE INTO scholarship_categories (scholarship_id, category_id) VALUES (?, ?)');
    $insF = $pdo->prepare('INSERT IGNORE INTO scholarship_fields (scholarship_id, field_id) VALUES (?, ?)');

    $inserted = 0;
    $skipped = 0;
    $uid = current_user_id();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $c = json_decode($row['cleaned_json'], true);

            // Someone may have committed the same URL since this batch was staged.
            $liveUrl->execute([$row['url_key']]);
            if ($liveUrl->fetchColumn()) { $skipped++; continue; }

            $pkey = provider_key($c['provider']);
            $findProv->execute([$pkey]);
            $providerId = $findProv->fetchColumn();
            if (!$providerId) {
                $newProv->execute([$c['provider'], $pkey, $types[strtolower($c['provider_type'] ?? '')] ?? null]);
                $providerId = (int)$pdo->lastInsertId();
            }

            $insS->execute([
                $c['name'], $providerId, $c['url'], $row['url_key'], $c['discovery_source'] ?: null, $c['state_code'], $c['scope'],
                $c['national_theme_id'] ?? null, $c['school_level'],
                $c['award_min'], $c['award_max'], $c['num_awards'], $c['renewable'], $c['max_value'],
                $c['open_date'], $c['deadline'], $c['is_rolling'] ? 1 : 0,
                $c['residency_requirement'] ?: null, $c['min_gpa'], $c['need_required'], $c['essay_required'],
                $c['recommendation_required'], $c['transcript_required'], $c['community_service_required'],
                $c['leadership_required'], $c['other_requirements'] ?: null, $c['application_method'] ?: null,
                $c['verification_date'], $c['notes'] ?: null, $batch['student_assignment_id'], $batchId, $uid, $uid,
            ]);
            $sid = (int)$pdo->lastInsertId();

            foreach ($c['grades'] as $g) { $insG->execute([$sid, $g]); }
            foreach ($c['category_list'] ?? [] as $n) { if (isset($cats[strtolower($n)])) { $insC->execute([$sid, $cats[strtolower($n)]]); } }
            foreach ($c['field_list'] ?? [] as $n) { if (isset($flds[strtolower($n)])) { $insF->execute([$sid, $flds[strtolower($n)]]); } }
            $inserted++;
        }
        $pdo->prepare("UPDATE import_batches SET status = 'committed', committed_at = NOW() WHERE id = ?")->execute([$batchId]);
        audit_log($pdo, 'import.commit', 'import_batch', $batchId, ['inserted' => $inserted, 'skipped_duplicates' => $skipped]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['inserted' => $inserted, 'skipped_duplicates' => $skipped];
}

/** FR-IMP-10: remove every scholarship the batch created (link rows cascade). Returns the number removed. */
function rollback_batch(PDO $pdo, int $batchId): int
{
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM scholarships WHERE import_batch_id = ?');
        $del->execute([$batchId]);
        $n = $del->rowCount();
        $pdo->prepare("UPDATE import_batches SET status = 'rolled_back', rolled_back_at = NOW() WHERE id = ?")->execute([$batchId]);
        audit_log($pdo, 'import.rollback', 'import_batch', $batchId, ['removed' => $n]);
        $pdo->commit();
        return $n;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
