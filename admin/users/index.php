<?php
/** FR-SA-01..03: Super-admin user management. List with filters, plus the actions (POST, CSRF, Super-admin only). */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/users.php';
require_role([ROLE_SUPER_ADMIN]);

$pdo = db();
$me = (int)auth_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT u.id, u.email, u.display_name, u.is_active, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $st->execute([$id]);
    $target = $st->fetch();
    if (!$target) { flash('danger', 'That user does not exist.'); redirect('admin/users/index.php'); }

    if ($action === 'set_role') {
        $role = (string)($_POST['role'] ?? '');
        $err = in_array($role, ACCOUNT_ROLES, true) ? update_user_access($pdo, $me, $id, $role, null) : 'Choose a valid role.';
        $err ? flash('danger', $err) : flash('success', $target['email'] . ' is now ' . role_label($role) . '.');
    } elseif ($action === 'activate' || $action === 'deactivate') {
        $err = update_user_access($pdo, $me, $id, null, $action === 'activate');
        $err ? flash('danger', $err) : flash('success', $target['email'] . ($action === 'activate' ? ' is active again.' : ' was deactivated and is signed out.'));
    } elseif ($action === 'reset_password') {
        $pw = generate_temp_password();
        set_user_password($pdo, $id, $pw, true);
        audit_log($pdo, 'user.password_reset', 'user', $id);
        reveal_password_once($target['email'], $pw);
        flash('success', 'Password reset. The person must choose a new one at their next sign-in.');
    } else {
        flash('danger', 'Unknown action.');
    }
    redirect('admin/users/index.php' . (($_POST['back'] ?? '') !== '' ? '?' . preg_replace('/[^A-Za-z0-9_=&%.+-]/', '', (string)$_POST['back']) : ''));
}

$q = trim((string)($_GET['q'] ?? ''));
$roleF = (string)($_GET['role'] ?? '');
$statusF = (string)($_GET['status'] ?? '');
$where = []; $params = [];
if ($q !== '') { $where[] = '(u.email LIKE ? OR u.display_name LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like); }
if (in_array($roleF, ACCOUNT_ROLES, true)) { $where[] = 'r.name = ?'; $params[] = $roleF; }
if ($statusF === 'active') { $where[] = 'u.is_active = 1'; } elseif ($statusF === 'inactive') { $where[] = 'u.is_active = 0'; }
$st = $pdo->prepare('SELECT u.id, u.email, u.display_name, u.is_active, u.must_change_password, u.last_login_at, u.created_at, r.name AS role
                       FROM users u JOIN roles r ON r.id = u.role_id ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY u.id LIMIT 500');
$st->execute($params);
$users = $st->fetchAll();
$activeSupers = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'super_admin' AND u.is_active = 1")->fetchColumn();
$back = http_build_query(array_filter(['q' => $q, 'role' => $roleF, 'status' => $statusF]));

$reveal = $_SESSION['reveal'] ?? null;
unset($_SESSION['reveal']);

$pageTitle = 'Users';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="mb-0">Users</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/users/edit.php')) ?>">Create user</a>
</div>
<?php if ($reveal): ?>
  <div class="alert alert-warning mt-3" role="alert">
    <strong>Temporary password for <?= e($reveal['email']) ?>:</strong> <code class="user-select-all fs-5"><?= e($reveal['password']) ?></code><br>
    Copy it now and give it to the person privately. It is not saved anywhere and will not be shown again.
  </div>
<?php endif; ?>
<form method="get" class="row g-2 align-items-end my-3">
  <div class="col-md-4"><label class="form-label" for="q">Name or email</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><label class="form-label" for="role">Role</label>
    <select class="form-select" id="role" name="role"><option value="">All roles</option>
      <?php foreach (ACCOUNT_ROLES as $r): ?><option value="<?= e($r) ?>"<?= $r === $roleF ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label" for="status">Status</label>
    <select class="form-select" id="status" name="status"><option value="">Any</option>
      <option value="active"<?= $statusF === 'active' ? ' selected' : '' ?>>Active</option><option value="inactive"<?= $statusF === 'inactive' ? ' selected' : '' ?>>Inactive</option></select></div>
  <div class="col-auto"><button class="btn btn-primary" type="submit">Filter</button> <a class="btn btn-outline-secondary" href="<?= e(url('admin/users/index.php')) ?>">Clear</a></div>
</form>
<?php if (!$users): ?><div class="empty-state">No users match.</div><?php else: ?>
<div class="table-responsive"><table class="table align-middle users-table">
  <caption class="visually-hidden">User accounts</caption>
  <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last sign-in</th><th scope="col">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u):
      $isMe = (int)$u['id'] === $me;
      $lastSuper = $u['role'] === ROLE_SUPER_ADMIN && $u['is_active'] && $activeSupers <= 1; ?>
    <tr>
      <td><?= e($u['display_name']) ?><?= $isMe ? ' <span class="badge text-bg-info">you</span>' : '' ?></td>
      <td class="text-break"><?= e($u['email']) ?></td>
      <td>
        <form method="post" class="d-flex gap-1" aria-label="Change role for <?= e($u['email']) ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="set_role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
          <label class="visually-hidden" for="role-<?= (int)$u['id'] ?>">Role for <?= e($u['email']) ?></label>
          <select class="form-select form-select-sm role-select" id="role-<?= (int)$u['id'] ?>" name="role"<?= $isMe ? ' disabled' : '' ?>>
            <?php foreach (ACCOUNT_ROLES as $r): ?><option value="<?= e($r) ?>"<?= $r === $u['role'] ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary" type="submit"<?= $isMe ? ' disabled' : '' ?>>Save</button>
        </form>
        <?php if ($lastSuper): ?><div class="small text-muted">Last active Super-admin: protected.</div><?php endif; ?>
      </td>
      <td><?= $u['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?>
        <?= $u['must_change_password'] ? '<span class="badge text-bg-warning">Temp password</span>' : '' ?></td>
      <td class="text-nowrap small"><?= $u['last_login_at'] ? e(date('M j, Y g:i a', strtotime($u['last_login_at']))) : 'Never' ?></td>
      <td class="text-nowrap">
        <form method="post" class="d-inline" data-confirm="Reset the password for <?= e($u['email']) ?>? They will be signed out.">
          <?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
          <button class="btn btn-sm btn-outline-secondary" type="submit">Reset password</button>
        </form>
        <form method="post" class="d-inline"<?= $u['is_active'] ? ' data-confirm="Deactivate ' . e($u['email']) . '? They are signed out right away."' : '' ?>>
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
          <?php if ($u['is_active']): ?>
            <input type="hidden" name="action" value="deactivate">
            <button class="btn btn-sm btn-outline-danger" type="submit"<?= ($isMe || $lastSuper) ? ' disabled' : '' ?>>Deactivate</button>
          <?php else: ?>
            <input type="hidden" name="action" value="activate">
            <button class="btn btn-sm btn-outline-success" type="submit">Activate</button>
          <?php endif; ?>
        </form>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/audit.php?user=' . (int)$u['id'])) ?>">History</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif;
$extraScripts = <<<'JS'
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (ev) { if (!window.confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); } });
});
</script>
JS;
require __DIR__ . '/../../includes/footer.php';
