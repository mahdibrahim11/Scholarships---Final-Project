<?php
/** FR-USER-01/02: your profile (student) or your children's profiles (parent). */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
[$pdo, $uid, $role] = my_guard();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = is_string($_POST['id'] ?? null) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($role !== ROLE_PARENT) { deny_forbidden(); }
        if (!profile_delete($pdo, $uid, $id)) { my_not_found(); }
        flash('success', 'The profile and everything saved for it were removed.');
    }
    redirect('my/profiles.php');
}
$profiles = profiles_for_user($pdo, $uid);
$active = active_profile($pdo);
$canAdd = count($profiles) < profile_limit_for($role);
$pageTitle = $role === ROLE_PARENT ? 'My children' : 'My profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="mb-0"><?= $role === ROLE_PARENT ? 'My children' : 'My scholarship profile' ?></h1>
  <?php if ($canAdd): ?><a class="btn btn-primary" href="<?= e(url('my/profile_edit.php')) ?>"><?= $role === ROLE_PARENT ? 'Add a child' : 'Create my profile' ?></a><?php endif; ?>
</div>
<p class="text-muted mt-2">A profile holds only a name or nickname, a grade, a state and interests. We never ask for a birth date, school, address or phone number.</p>
<?php if (!$profiles): ?>
  <div class="empty-state">No profile yet. <?= $role === ROLE_PARENT ? 'Add your first child' : 'Create your profile' ?> to see scholarships that fit.</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($profiles as $p): $counts = status_counts($pdo, $p); $isActive = $active && (int)$active['id'] === (int)$p['id']; ?>
    <div class="col-md-6"><div class="card h-100"><div class="card-body">
      <h2 class="h5 card-title"><?= e($p['display_name']) ?><?= $isActive && count($profiles) > 1 ? ' <span class="badge text-bg-info">showing</span>' : '' ?></h2>
      <p class="mb-1">Grade <?= (int)$p['grade'] ?> · <?= e($p['state_name']) ?></p>
      <p class="mb-2 small text-muted">Interests: <?= $p['interests'] ? e(implode(', ', array_column($p['interests'], 'name'))) : 'none chosen (we show every field)' ?></p>
      <p class="mb-3 small"><?= array_sum($counts) ?> saved<?= $counts['In Progress'] ? ' · ' . $counts['In Progress'] . ' in progress' : '' ?><?= $counts['Submitted'] ? ' · ' . $counts['Submitted'] . ' submitted' : '' ?></p>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-primary" href="<?= e(url('my/matches.php?profile=' . (int)$p['id'])) ?>">Matches<span class="visually-hidden"> for <?= e($p['display_name']) ?></span></a>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('my/saved.php?profile=' . (int)$p['id'])) ?>">Saved<span class="visually-hidden"> for <?= e($p['display_name']) ?></span></a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('my/profile_edit.php?id=' . (int)$p['id'])) ?>">Edit<span class="visually-hidden"> <?= e($p['display_name']) ?></span></a>
        <?php if ($role === ROLE_PARENT): ?>
          <form method="post" class="d-inline" data-confirm="Remove <?= e($p['display_name']) ?> and everything saved for them?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Remove<span class="visually-hidden"> <?= e($p['display_name']) ?></span></button>
          </form>
        <?php endif; ?>
      </div>
    </div></div></div>
  <?php endforeach; ?>
</div>
<?php endif;
$extraScripts = '<script>document.querySelectorAll("form[data-confirm]").forEach(function(f){f.addEventListener("submit",function(ev){if(!window.confirm(f.getAttribute("data-confirm"))){ev.preventDefault();}});});</script>';
require __DIR__ . '/../includes/footer.php';
