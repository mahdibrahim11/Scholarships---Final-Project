<?php
/** User accounts: create, change role, activate/deactivate, reset password, with the "last Super-admin" protection (FR-SA-01..03). */

function role_id_for(PDO $pdo, string $role): ?int
{
    $st = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
    $st->execute([$role]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int)$id;
}

/** Clean and check the fields shared by registration and user creation. Returns [clean, errors]. */
function validate_account_input(PDO $pdo, array $in, array $allowedRoles, ?int $ignoreUserId = null): array
{
    $clean = [
        'display_name' => trim(preg_replace('/\s+/u', ' ', (string)($in['display_name'] ?? ''))),
        'email' => normalize_email((string)($in['email'] ?? '')),
        'role' => (string)($in['role'] ?? ''),
    ];
    $err = [];
    $len = mb_strlen($clean['display_name']);
    if ($len < 2 || $len > 100) { $err['display_name'] = 'Enter your name (2 to 100 characters).'; }
    elseif (preg_match('/[\x00-\x1f<>]/u', $clean['display_name'])) { $err['display_name'] = 'The name cannot contain < or > or control characters.'; }
    if (!filter_var($clean['email'], FILTER_VALIDATE_EMAIL) || strlen($clean['email']) > 190) { $err['email'] = 'Enter a valid email address.'; }
    else {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$clean['email']]);
        $found = $st->fetchColumn();
        if ($found && (int)$found !== (int)$ignoreUserId) { $err['email'] = 'That email address already has an account.'; }
    }
    if (!in_array($clean['role'], $allowedRoles, true)) { $err['role'] = 'Choose a valid account type.'; }
    return [$clean, $err];
}

/** Insert a user. $clean comes from validate_account_input(). Returns the new id. */
function create_user(PDO $pdo, array $clean, string $password, bool $mustChange): int
{
    $pdo->prepare('INSERT INTO users (role_id, email, password_hash, display_name, is_active, must_change_password) VALUES (?, ?, ?, ?, 1, ?)')
        ->execute([role_id_for($pdo, $clean['role']), $clean['email'], password_hash($password, PASSWORD_DEFAULT), $clean['display_name'], $mustChange ? 1 : 0]);
    return (int)$pdo->lastInsertId();
}

/**
 * Change a user's role and/or active flag. Refuses to leave the system with no active Super-admin, and refuses to let
 * someone lock themselves out. Returns an error message, or null on success. Runs in a transaction with row locks.
 */
function update_user_access(PDO $pdo, int $actorId, int $targetId, ?string $newRole, ?bool $newActive): ?string
{
    $pdo->beginTransaction();
    try {
        // Lock every Super-admin row so two admins cannot each remove "the other" at the same moment.
        $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'super_admin' FOR UPDATE")->fetchAll();
        $st = $pdo->prepare('SELECT u.id, u.is_active, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? FOR UPDATE');
        $st->execute([$targetId]);
        $t = $st->fetch();
        if (!$t) { $pdo->rollBack(); return 'That user does not exist.'; }
        $role = $newRole ?? $t['role'];
        $active = $newActive ?? (bool)$t['is_active'];
        if ($newRole !== null && !in_array($newRole, ACCOUNT_ROLES, true)) { $pdo->rollBack(); return 'Choose a valid role.'; }

        if ($targetId === $actorId && ($role !== $t['role'] || !$active)) {
            $pdo->rollBack();
            return 'You cannot change your own role or deactivate yourself. Ask another Super-admin.';
        }
        $wasActiveSuper = $t['role'] === ROLE_SUPER_ADMIN && (bool)$t['is_active'];
        $staysActiveSuper = $role === ROLE_SUPER_ADMIN && $active;
        if ($wasActiveSuper && !$staysActiveSuper) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'super_admin' AND u.is_active = 1 AND u.id <> ?");
            $st->execute([$targetId]);
            if ((int)$st->fetchColumn() === 0) {
                $pdo->rollBack();
                return 'This is the last active Super-admin. Create or activate another Super-admin first.';
            }
        }
        $pdo->prepare('UPDATE users SET role_id = ?, is_active = ? WHERE id = ?')->execute([role_id_for($pdo, $role), $active ? 1 : 0, $targetId]);
        if ($role !== $t['role']) { audit_log($pdo, 'user.role_change', 'user', $targetId, ['from' => $t['role'], 'to' => $role]); }
        if ($active !== (bool)$t['is_active']) { audit_log($pdo, $active ? 'user.activate' : 'user.deactivate', 'user', $targetId); }
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

/** Set a new password. Bumps session_version so every existing session of that user ends. */
function set_user_password(PDO $pdo, int $userId, string $password, bool $mustChange): void
{
    $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = ?, session_version = session_version + 1 WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $userId]);
}

/** Show a one-time temporary password on the next page load (never written to the audit log or the database). */
function reveal_password_once(string $email, string $password): void
{
    $_SESSION['reveal'] = ['email' => $email, 'password' => $password];
}
