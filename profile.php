<?php
/** FR-AUTH-07: your own profile. Everyone signed in can open it; nobody can open someone else's. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/users.php';
require_login();
$pageTitle = 'My account';
$u = auth_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim(preg_replace('/\s+/u', ' ', (string)($_POST['display_name'] ?? '')));
    $len = mb_strlen($name);
    if ($len < 2 || $len > 100) { $errors['display_name'] = 'Enter your name (2 to 100 characters).'; }
    elseif (preg_match('/[\x00-\x1f<>]/u', $name)) { $errors['display_name'] = 'The name cannot contain < or > or control characters.'; }
    if (!$errors) {
        db()->prepare('UPDATE users SET display_name = ? WHERE id = ?')->execute([$name, $u['id']]);
        audit_log(db(), 'user.profile_update', 'user', (int)$u['id']);
        flash('success', 'Profile saved.');
        redirect('profile.php');
    }
    $u['display_name'] = $name;
}
require __DIR__ . '/includes/header.php';
?>
<h1>My account</h1>
<div class="row g-4">
  <div class="col-lg-6">
    <form method="post" novalidate id="profile-form">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="display_name">Name</label>
        <input class="form-control<?= isset($errors['display_name']) ? ' is-invalid' : '' ?>" id="display_name" name="display_name" maxlength="100" minlength="2" required value="<?= e($u['display_name']) ?>">
        <div class="invalid-feedback"><?= e($errors['display_name'] ?? 'Enter your name (2 to 100 characters).') ?></div>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-outline-secondary" href="<?= e(url('change_password.php')) ?>">Change password</a>
    </form>
  </div>
  <div class="col-lg-6">
    <dl class="row">
      <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($u['email']) ?></dd>
      <dt class="col-sm-4">Account type</dt><dd class="col-sm-8"><?= e(role_label($u['role'])) ?></dd>
      <dt class="col-sm-4">Member since</dt><dd class="col-sm-8"><?= e(date('M j, Y', strtotime($u['created_at']))) ?></dd>
      <dt class="col-sm-4">Last sign-in</dt><dd class="col-sm-8"><?= $u['last_login_at'] ? e(date('M j, Y g:i a', strtotime($u['last_login_at']))) : 'This is your first visit' ?></dd>
    </dl>
    <p class="text-muted small">To change your email address or account type, ask a Super-admin.</p>
  </div>
</div>
<?php
$extraScripts = '<script src="' . e(url('assets/js/auth.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
