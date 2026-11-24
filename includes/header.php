<?php
/** Shared header + navigation (FR-PUB-03). Set $pageTitle before including. */
$pageTitle = $pageTitle ?? '';
$role = current_role();
$isAdmin = in_array($role, [ROLE_ADMIN, ROLE_SUPER_ADMIN], true);
$isSuper = $role === ROLE_SUPER_ADMIN;
$here = basename($_SERVER['SCRIPT_NAME']);
function nav_link(string $href, string $label, string $here, string $file): string
{
    $active = $here === $file ? ' active" aria-current="page' : '';
    return '<li class="nav-item"><a class="nav-link' . $active . '" href="' . e(url($href)) . '">' . e($label) . '</a></li>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ? $pageTitle . ' | ' : '') . e(config('app.name')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/css/site.css')) ?>" rel="stylesheet">
  <?= $extraHead ?? '' ?>
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main">Skip to main content</a>
<header>
  <nav class="navbar navbar-expand-lg site-nav" aria-label="Main navigation">
    <div class="container">
      <a class="navbar-brand" href="<?= e(url('index.php')) ?>"><?= e(config('app.name')) ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Show or hide the menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
          <?= nav_link('search.php', 'Find scholarships', $here, in_array($here, ['search.php', 'scholarship.php'], true) ? $here : 'search.php') ?>
          <?= nav_link('facts.php', 'Scholarship Facts', $here, 'facts.php') ?>
          <?= nav_link('about.php', 'About', $here, 'about.php') ?>
          <?= nav_link('faq.php', 'FAQ', $here, 'faq.php') ?>
          <?= nav_link('contact.php', 'Contact', $here, 'contact.php') ?>
          <?php if ($role === ROLE_STUDENT || $role === ROLE_PARENT): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">My scholarships</a>
              <ul class="dropdown-menu">
                <?php if ($role === ROLE_PARENT): ?><li><a class="dropdown-item" href="<?= e(url('my/dashboard.php')) ?>">Family dashboard</a></li><?php endif; ?>
                <li><a class="dropdown-item" href="<?= e(url('my/matches.php')) ?>">My Matches</a></li>
                <li><a class="dropdown-item" href="<?= e(url('my/saved.php')) ?>">Saved</a></li>
                <li><a class="dropdown-item" href="<?= e(url('my/deadlines.php')) ?>">Upcoming Deadlines</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e(url('my/profiles.php')) ?>"><?= $role === ROLE_PARENT ? 'My children' : 'My scholarship profile' ?></a></li>
              </ul>
            </li>
          <?php endif; ?>
          <?php if ($isAdmin): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Admin</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= e(url('admin/dashboard.php')) ?>">Dashboard</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/scholarships/index.php')) ?>">Manage scholarships</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/providers/index.php')) ?>">Providers</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/import/index.php')) ?>">Import data</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/import/history.php')) ?>">Import history</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/import/issues.php')) ?>">Import issues report</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/quality.php')) ?>">Data quality</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/coverage.php')) ?>">Coverage report</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/export.php')) ?>">Export CSV</a></li>
              </ul>
            </li>
          <?php endif; ?>
          <?php if ($isSuper): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Super-admin</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= e(url('admin/users/index.php')) ?>">Users</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/reference/index.php')) ?>">Reference data</a></li>
                <li><a class="dropdown-item" href="<?= e(url('admin/audit.php')) ?>">Audit log</a></li>
              </ul>
            </li>
          <?php endif; ?>
        </ul>
        <ul class="navbar-nav align-items-lg-center">
          <?php if (is_logged_in()): $me = auth_user(); ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?= e($me['display_name']) ?> <span class="badge text-bg-secondary"><?= e(role_label($me['role'])) ?></span></a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>">My account</a></li>
                <li><a class="dropdown-item" href="<?= e(url('change_password.php')) ?>">Change password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form method="post" action="<?= e(url('logout.php')) ?>" class="px-3"><?= csrf_field() ?><button class="btn btn-link p-0" type="submit">Sign out</button></form></li>
              </ul>
            </li>
          <?php else: ?>
            <?= nav_link('login.php', 'Sign in', $here, 'login.php') ?>
            <?= nav_link('register.php', 'Create account', $here, 'register.php') ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
</header>
<main id="main" class="container py-4">
<?= flash_render() ?>
