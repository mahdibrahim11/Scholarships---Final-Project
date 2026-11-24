<?php
/** FR-SA-01: create a user of any role. A temporary password is generated and shown once. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/users.php';
require_role([ROLE_SUPER_ADMIN]);

$pdo = db();
$errors = [];
$old = ['display_name' => '', 'email' => '', 'role' => ROLE_ADMIN];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$clean, $errors] = validate_account_input($pdo, $_POST, ACCOUNT_ROLES);
    $old = $clean;
    if (!$errors) {
        $pw = generate_temp_password();
        try {
            $id = create_user($pdo, $clean, $pw, true);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { $errors['email'] = 'That email address already has an account.'; } else { throw $e; }
        }
        if (!$errors) {
            audit_log($pdo, 'user.create', 'user', $id, ['role' => $clean['role'], 'email' => $clean['email']]);
            reveal_password_once($clean['email'], $pw);
            flash('success', 'Account created. Give the temporary password to ' . $clean['display_name'] . ' privately.');
            redirect('admin/users/index.php');
        }
    }
}
$pageTitle = 'Create user';
require __DIR__ . '/../../includes/header.php';
$cls = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
<h1>Create user</h1>
<p>Pick any role. A temporary password is generated and shown to you once; the person must replace it when they first sign in.</p>
<form method="post" novalidate class="col-lg-6" id="create-user-form">
  <?= csrf_field() ?>
  <div class="mb-3">
    <label class="form-label" for="display_name">Name</label>
    <input class="form-control<?= $cls('display_name') ?>" id="display_name" name="display_name" maxlength="100" minlength="2" required value="<?= e($old['display_name']) ?>">
    <div class="invalid-feedback"><?= e($errors['display_name'] ?? 'Enter a name (2 to 100 characters).') ?></div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input class="form-control<?= $cls('email') ?>" id="email" name="email" type="email" maxlength="190" required value="<?= e($old['email']) ?>">
    <div class="invalid-feedback"><?= e($errors['email'] ?? 'Enter a valid email address.') ?></div>
  </div>
  <div class="mb-3">
    <label class="form-label" for="role">Role</label>
    <select class="form-select<?= $cls('role') ?>" id="role" name="role" required>
      <?php foreach (ACCOUNT_ROLES as $r): ?><option value="<?= e($r) ?>"<?= $old['role'] === $r ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
    </select>
    <div class="invalid-feedback"><?= e($errors['role'] ?? 'Choose a role.') ?></div>
  </div>
  <button class="btn btn-primary" type="submit">Create user</button>
  <a class="btn btn-outline-secondary" href="<?= e(url('admin/users/index.php')) ?>">Cancel</a>
</form>
<?php
$extraScripts = '<script src="' . e(url('assets/js/auth.js')) . '"></script><script>document.getElementById("create-user-form").addEventListener("submit",function(ev){var ok=true;this.querySelectorAll("[required]").forEach(function(el){var v=el.checkValidity()&&el.value.trim()!=="";el.classList.toggle("is-invalid",!v);if(!v){ok=false;}});if(!ok){ev.preventDefault();}});</script>';
require __DIR__ . '/../../includes/footer.php';
