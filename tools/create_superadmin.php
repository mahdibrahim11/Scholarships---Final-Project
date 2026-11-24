<?php
/**
 * Command line only. Creates a Super-admin, or resets the password of an existing account and makes it active.
 * Use it if every Super-admin is locked out.
 *   php tools/create_superadmin.php you@example.org "Your Name"
 * Prints a temporary password once; the account must change it at the next sign-in.
 */
if (PHP_SAPI !== 'cli') { exit('Command line only.'); }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/users.php';

[$script, $email, $name] = array_pad($argv, 3, '');
$email = normalize_email($email);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { exit("Usage: php tools/create_superadmin.php email@example.org \"Display Name\"\n"); }
$pdo = db();
$pw = generate_temp_password();
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
$id = $st->fetchColumn();
if ($id) {
    $pdo->prepare('UPDATE users SET role_id = ?, is_active = 1 WHERE id = ?')->execute([role_id_for($pdo, ROLE_SUPER_ADMIN), $id]);
    set_user_password($pdo, (int)$id, $pw, true);
    audit_log($pdo, 'user.cli_reset', 'user', (int)$id, ['email' => $email], null);
    echo "Existing account $email is now an active Super-admin.\n";
} else {
    if (mb_strlen(trim($name)) < 2) { exit("A new account needs a name: php tools/create_superadmin.php $email \"Display Name\"\n"); }
    $id = create_user($pdo, ['display_name' => trim($name), 'email' => $email, 'role' => ROLE_SUPER_ADMIN], $pw, true);
    audit_log($pdo, 'user.cli_create', 'user', $id, ['email' => $email], null);
    echo "Created Super-admin $email.\n";
}
echo "Temporary password: $pw\nIt must be changed at the first sign-in.\n";
