<?php
/** FR-AUTH-01: Student and Parent registration. Administrators are never created here. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/users.php';
if (is_logged_in()) { redirect(home_for_role(current_role())); }
$pageTitle = 'Create an account';
$errors = [];
$old = ['display_name' => '', 'email' => '', 'role' => ROLE_STUDENT];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pdo = db();
    [$clean, $errors] = validate_account_input($pdo, $_POST, SELF_REGISTER_ROLES);   // role must be student or parent, whatever the form says
    $old = $clean;
    $pw = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');
    $problems = password_problems($pw, $clean['email'], $clean['display_name']);
    if ($problems) { $errors['password'] = implode(' ', $problems); }
    if ($pw !== $confirm) { $errors['password_confirm'] = 'The two passwords do not match.'; }
    if (!$errors) {
        try {
            $id = create_user($pdo, $clean, $pw, false);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { $errors['email'] = 'That email address already has an account.'; $id = 0; } else { throw $e; }   // two sign-ups at once
        }
        if (!$errors) {
            audit_log($pdo, 'user.register', 'user', $id, ['role' => $clean['role']], $id);
            auth_sign_in(['id' => $id, 'session_version' => 1]);
            flash('success', 'Welcome! Your account is ready. Start by adding a profile so we can find scholarships that fit.');
            redirect(home_for_role((string)$clean['role']));
        }
    }
}
require __DIR__ . '/includes/header.php';
$cls = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
<div class="row justify-content-center"><div class="col-md-9 col-lg-6">
  <h1>Create an account</h1>
  <p>Accounts are for students and parents. Staff accounts are created by a Super-admin.</p>
  <form method="post" novalidate id="register-form">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="display_name">Your name</label>
      <input class="form-control<?= $cls('display_name') ?>" id="display_name" name="display_name" maxlength="100" minlength="2" required autocomplete="name" value="<?= e($old['display_name']) ?>">
      <div class="invalid-feedback"><?= e($errors['display_name'] ?? 'Enter your name (2 to 100 characters).') ?></div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input class="form-control<?= $cls('email') ?>" id="email" name="email" type="email" maxlength="190" required autocomplete="username" value="<?= e($old['email']) ?>">
      <div class="invalid-feedback"><?= e($errors['email'] ?? 'Enter a valid email address.') ?></div>
    </div>
    <fieldset class="mb-3">
      <legend class="form-label fs-6">I am a</legend>
      <?php foreach (SELF_REGISTER_ROLES as $r): ?>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="role" id="role-<?= e($r) ?>" value="<?= e($r) ?>" required<?= $old['role'] === $r ? ' checked' : '' ?>>
          <label class="form-check-label" for="role-<?= e($r) ?>"><?= e(role_label($r)) ?></label>
        </div>
      <?php endforeach; ?>
      <?php if (isset($errors['role'])): ?><div class="text-danger small"><?= e($errors['role']) ?></div><?php endif; ?>
    </fieldset>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <input class="form-control<?= $cls('password') ?>" id="password" name="password" type="password" minlength="<?= PASSWORD_MIN ?>" maxlength="<?= PASSWORD_MAX ?>" required autocomplete="new-password" aria-describedby="pw-help">
      <div class="form-text" id="pw-help">At least <?= PASSWORD_MIN ?> characters, with a letter and a number.</div>
      <div class="invalid-feedback" id="password-err"><?= e($errors['password'] ?? 'Use at least ' . PASSWORD_MIN . ' characters, with a letter and a number.') ?></div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password_confirm">Confirm password</label>
      <input class="form-control<?= $cls('password_confirm') ?>" id="password_confirm" name="password_confirm" type="password" maxlength="<?= PASSWORD_MAX ?>" required autocomplete="new-password">
      <div class="invalid-feedback"><?= e($errors['password_confirm'] ?? 'The two passwords must match.') ?></div>
    </div>
    <button class="btn btn-primary" type="submit">Create account</button>
  </form>
  <p class="mt-3">Already have an account? <a href="<?= e(url('login.php')) ?>">Sign in</a>.</p>
</div></div>
<?php
$extraScripts = '<script>window.PW_MIN=' . PASSWORD_MIN . ';</script><script src="' . e(url('assets/js/auth.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
