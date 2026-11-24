<?php
/** FR-SA-04: which lookup tables a Super-admin may edit, and the rules for each. The table name is looked up here, never taken from the request. */
function reference_tables(): array
{
    return [
        'states' => ['label' => 'States and regions', 'key' => 'code', 'key_manual' => true, 'name_max' => 40, 'key_pattern' => '/^[A-Z]{2}$/',
                     'protected' => ['US'], 'note' => 'Two-letter code. "US" means National and cannot be changed.'],
        'provider_types' => ['label' => 'Provider types', 'key' => 'id', 'key_manual' => false, 'name_max' => 60, 'protected' => [], 'note' => ''],
        'categories' => ['label' => 'Scholarship categories', 'key' => 'id', 'key_manual' => false, 'name_max' => 80, 'protected' => ['Other'], 'note' => '"Other" is used by the importer and cannot be removed.'],
        'fields_of_study' => ['label' => 'Fields of study', 'key' => 'id', 'key_manual' => false, 'name_max' => 80, 'protected' => ['Any / undecided'], 'note' => '"Any / undecided" is used by the importer and search and cannot be removed.'],
        'national_themes' => ['label' => 'National themes', 'key' => 'id', 'key_manual' => true, 'name_max' => 120, 'key_pattern' => '/^\d{1,3}$/', 'protected' => [], 'note' => 'Numbered 1 to 255.'],
    ];
}

function reference_rows(PDO $pdo, string $table, array $cfg): array
{
    $k = $cfg['key'];
    // $table and $k come from reference_tables() above, never from the request.
    $sql = "SELECT t.$k AS id, t.name FROM $table t ORDER BY " . ($k === 'code' ? 't.name' : 't.' . $k);
    $rows = $pdo->query($sql)->fetchAll();
    return $rows;
}

/** @return string|null error message */
function reference_save(PDO $pdo, string $table, array $cfg, string $action, string $id, string $name): ?string
{
    $k = $cfg['key'];
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($action !== 'delete') {
        if ($name === '' || mb_strlen($name) > $cfg['name_max']) { return 'Enter a name (1 to ' . $cfg['name_max'] . ' characters).'; }
        if (preg_match('/[\x00-\x1f<>]/u', $name)) { return 'The name cannot contain < or > or control characters.'; }
    }
    try {
        if ($action === 'add') {
            if ($cfg['key_manual']) {
                $id = strtoupper(trim($id));
                if (!preg_match($cfg['key_pattern'], $id) || ($k === 'id' && ((int)$id < 1 || (int)$id > 255))) { return $k === 'code' ? 'Enter a two-letter code, such as PR.' : 'Enter a number from 1 to 255.'; }
                $pdo->prepare("INSERT INTO $table ($k, name) VALUES (?, ?)")->execute([$id, $name]);
            } else {
                $pdo->prepare("INSERT INTO $table (name) VALUES (?)")->execute([$name]);
                $id = (string)$pdo->lastInsertId();
            }
            audit_log($pdo, 'reference.add', $table, ctype_digit($id) ? (int)$id : null, ['key' => $id, 'name' => $name]);
            return null;
        }
        $st = $pdo->prepare("SELECT name FROM $table WHERE $k = ?");
        $st->execute([$id]);
        $cur = $st->fetchColumn();
        if ($cur === false) { return 'That entry does not exist.'; }
        if (in_array($cur, $cfg['protected'], true) || in_array($id, $cfg['protected'], true)) { return '"' . $cur . '" is used by the system and cannot be changed or removed.'; }
        if ($action === 'rename') {
            $pdo->prepare("UPDATE $table SET name = ? WHERE $k = ?")->execute([$name, $id]);
            audit_log($pdo, 'reference.rename', $table, ctype_digit($id) ? (int)$id : null, ['key' => $id, 'from' => $cur, 'to' => $name]);
            return null;
        }
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM $table WHERE $k = ?")->execute([$id]);
            audit_log($pdo, 'reference.delete', $table, ctype_digit($id) ? (int)$id : null, ['key' => $id, 'name' => $cur]);
            return null;
        }
        return 'Unknown action.';
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return $action === 'delete' ? '"' . ($cur ?? $name) . '" is in use by scholarships or students, so it cannot be deleted. Rename it instead.' : 'An entry with that code or name already exists.';
        }
        throw $e;
    }
}
