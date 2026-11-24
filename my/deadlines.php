<?php
/** FR-USER-06: "Upcoming Deadlines". Saved scholarships you still have to do, soonest first, due-soon highlighted. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
[$pdo, $uid, $role] = my_guard();
$profile = my_profile_for_page($pdo, $uid);
$all = profiles_for_user($pdo, $uid);
$scope = ($role === ROLE_PARENT && count($all) > 1 && ($_GET['scope'] ?? '') === 'all') ? 'all' : 'one';
$window = (string)($_GET['within'] ?? '');
$within = ctype_digit($window) && in_array((int)$window, [7, 30, 60, 90], true) ? (int)$window : null;
$d = upcoming_deadlines($pdo, $scope === 'all' ? $all : [$profile], $within);
$pageTitle = 'Upcoming Deadlines';
$self = 'my/deadlines.php';
require __DIR__ . '/../includes/header.php';
?>
<h1>Upcoming Deadlines<?= $scope === 'one' && count($all) > 1 ? ' for ' . e($profile['display_name']) : '' ?></h1>
<?php if ($scope === 'one'): echo child_switcher($all, $profile, $self); endif; ?>
<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-auto"><label class="form-label mb-0" for="within">Due within</label>
    <select class="form-select" id="within" name="within">
      <option value="">Any time</option>
      <?php foreach ([7 => '7 days', 30 => '30 days', 60 => '60 days', 90 => '90 days'] as $n => $l): ?><option value="<?= $n ?>"<?= $within === $n ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select></div>
  <?php if ($role === ROLE_PARENT && count($all) > 1): ?>
    <div class="col-auto"><label class="form-label mb-0" for="scope">Show</label>
      <select class="form-select" id="scope" name="scope"><option value="one"<?= $scope === 'one' ? ' selected' : '' ?>>This child</option><option value="all"<?= $scope === 'all' ? ' selected' : '' ?>>All children</option></select></div>
  <?php endif; ?>
  <div class="col-auto"><button class="btn btn-primary" type="submit">Show</button></div>
</form>
<p class="text-muted small">Shows saved scholarships marked Interested or In Progress. <span class="due-badge due-soon">Red</span> = due within <?= DUE_SOON_DAYS ?> days, <span class="due-badge due-month">amber</span> = within <?= DUE_MONTH_DAYS ?> days. The words always say how many days are left.</p>
<?php if (!$d['dated'] && !$d['rolling']): ?>
  <div class="empty-state">No upcoming deadlines. Save scholarships from <a href="<?= e(url('my/matches.php')) ?>">My Matches</a> to track them here.</div>
<?php endif; ?>
<?php if ($d['dated']): ?>
<div class="table-responsive"><table class="table align-middle deadlines-table">
  <caption class="visually-hidden">Upcoming deadlines, soonest first</caption>
  <thead><tr><th scope="col">Deadline</th><?php if ($scope === 'all'): ?><th scope="col">Child</th><?php endif; ?><th scope="col">Scholarship</th><th scope="col">Status</th></tr></thead>
  <tbody>
  <?php foreach ($d['dated'] as $r): ?>
    <tr<?= due_class($r['days']) !== '' ? ' class="' . e(due_class($r['days'])) . '-row"' : '' ?>>
      <td class="text-nowrap"><?= due_badge($r['deadline'], false) ?></td>
      <?php if ($scope === 'all'): ?><td><?= e($r['child']) ?></td><?php endif; ?>
      <td><a href="<?= e(url('scholarship.php?id=' . (int)$r['scholarship_id'])) ?>"><?= e($r['name']) ?></a><div class="small text-muted"><?= e($r['provider']) ?></div></td>
      <td><?= e($r['app_status']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<?php if ($d['rolling']): ?>
  <h2 class="h5 mt-4">Rolling or no fixed deadline</h2>
  <ul class="list-group mb-3">
    <?php foreach ($d['rolling'] as $r): ?>
      <li class="list-group-item"><a href="<?= e(url('scholarship.php?id=' . (int)$r['scholarship_id'])) ?>"><?= e($r['name']) ?></a>
        <?= $scope === 'all' ? ' <span class="text-muted">(' . e($r['child']) . ')</span>' : '' ?> <span class="text-muted small">· <?= e($r['app_status']) ?></span></li>
    <?php endforeach; ?>
  </ul>
<?php endif;
require __DIR__ . '/../includes/footer.php';
