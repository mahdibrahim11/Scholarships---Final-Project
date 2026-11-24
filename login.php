<?php
/** FR-AUTH-02: sign in. */
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) { redirect(home_for_role(current_role())); }
$pageTitle = 'Sign in';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '' || strlen($password) > 1000 || strlen($email) > 190) {
        $error = 'Enter your email and password.';
    } else {
        $pdo = db();
        [$row, $msg] = authenticate($pdo, $email, $password);
        if ($row) {
            $next = safe_next($_SESSION['next'] ?? null);
            auth_sign_in($row);
            audit_log($pdo, 'auth.login', 'user', (int)$row['id']);
            $dest = $next ?? url(home_for_role(current_role()));
            header('Location: ' . $dest);
            exit;
        }
        audit_log($pdo, 'auth.login_failed', null, null, ['email' => mb_substr(normalize_email($email), 0, 190)], null);
        $error = $msg;
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-8 col-lg-5">
  <h1>Sign in</h1>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" novalidate id="login-form" autocomplete="on">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input class="form-control" id="email" name="email" type="email" maxlength="190" required autocomplete="username" value="<?= e($email) ?>">
      <div class="invalid-feedback">Enter a valid email address.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <input class="form-control" id="password" name="password" type="password" maxlength="1000" required autocomplete="current-password">
      <div class="invalid-feedback">Enter your password.</div>
    </div>
    <button class="btn btn-primary" type="submit">Sign in</button>
  </form>
  <p class="mt-3">New here? <a href="<?= e(url('register.php')) ?>">Create a student or parent account</a>.</p>
</div></div>
<?php
$extraScripts = '<script src="' . e(url('assets/js/auth.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
