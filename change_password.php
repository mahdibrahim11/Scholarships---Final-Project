<?php
/** FR-AUTH-07: change your own password. Also where a temporary password is replaced. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/users.php';
require_login();
$pageTitle = 'Change password';
$u = auth_user();
$forced = (bool)$u['must_change_password'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pdo = db();
    $cur = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$u['id']]);
    if (!password_verify($cur, (string)$st->fetchColumn())) { $errors['current_password'] = 'Your current password is not correct.'; }
    $problems = password_problems($new, $u['email'], $u['display_name']);
    if ($problems) { $errors['password'] = implode(' ', $problems); }
    elseif ($new === $cur) { $errors['password'] = 'Choose a password that is different from the current one.'; }
    if ($new !== $confirm) { $errors['password_confirm'] = 'The two passwords do not match.'; }
    if (!$errors) {
        set_user_password($pdo, (int)$u['id'], $new, false);
        audit_log($pdo, 'auth.password_change', 'user', (int)$u['id']);
        $fresh = auth_load_user((int)$u['id']);
        auth_sign_in($fresh);                 // new session id; other sessions of this user end (session_version changed)
        flash('success', 'Your password was changed.');
        redirect($forced ? home_for_role($fresh['role']) : 'profile.php');
    }
}
require __DIR__ . '/includes/header.php';
$cls = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
<div class="row justify-content-center"><div class="col-md-9 col-lg-6">
  <h1>Change password</h1>
  <?php if ($forced): ?><div class="alert alert-warning" role="alert">You are using a temporary password. Choose your own before you continue.</div><?php endif; ?>
  <form method="post" novalidate id="password-form">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="current_password">Current password</label>
      <input class="form-control<?= $cls('current_password') ?>" id="current_password" name="current_password" type="password" required autocomplete="current-password" maxlength="1000">
      <div class="invalid-feedback"><?= e($errors['current_password'] ?? 'Enter your current password.') ?></div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">New password</label>
      <input class="form-control<?= $cls('password') ?>" id="password" name="password" type="password" minlength="<?= PASSWORD_MIN ?>" maxlength="<?= PASSWORD_MAX ?>" required autocomplete="new-password" aria-describedby="pw-help">
      <div class="form-text" id="pw-help">At least <?= PASSWORD_MIN ?> characters, with a letter and a number.</div>
      <div class="invalid-feedback" id="password-err"><?= e($errors['password'] ?? 'Use at least ' . PASSWORD_MIN . ' characters, with a letter and a number.') ?></div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password_confirm">Confirm new password</label>
      <input class="form-control<?= $cls('password_confirm') ?>" id="password_confirm" name="password_confirm" type="password" maxlength="<?= PASSWORD_MAX ?>" required autocomplete="new-password">
      <div class="invalid-feedback"><?= e($errors['password_confirm'] ?? 'The two passwords must match.') ?></div>
    </div>
    <button class="btn btn-primary" type="submit">Change password</button>
    <?php if (!$forced): ?><a class="btn btn-outline-secondary" href="<?= e(url('profile.php')) ?>">Cancel</a><?php endif; ?>
  </form>
</div></div>
<?php
$extraScripts = '<script>window.PW_MIN=' . PASSWORD_MIN . ';</script><script src="' . e(url('assets/js/auth.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
