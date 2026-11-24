<?php
/** Parent dashboard (FR-USER-02, 06): every child's saved scholarships and deadlines in one place. Parents only. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
require_role([ROLE_PARENT]);
$pdo = db();
$uid = (int)auth_user()['id'];
$kids = profiles_for_user($pdo, $uid);                         // only this parent's own children
$soon = upcoming_deadlines($pdo, $kids, 60);
$pageTitle = 'Family dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h1 class="mb-0">Family dashboard</h1>
  <a class="btn btn-primary" href="<?= e(url('my/profile_edit.php')) ?>">Add a child</a>
</div>
<?php if (!$kids): ?>
  <div class="empty-state mt-3"><p class="mb-1"><strong>Add your first child.</strong></p>
    <p class="mb-0">Give a name or nickname, a grade, a state and interests. Then we can show scholarships that fit and track the ones you save.</p></div>
<?php else: ?>
<div class="row g-3 mt-1">
  <?php foreach ($kids as $k): $c = status_counts($pdo, $k); $saved = saved_list($pdo, $k); ?>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
      <h2 class="h5"><?= e($k['display_name']) ?> <span class="text-muted fs-6">Grade <?= (int)$k['grade'] ?> · <?= e($k['state_name']) ?></span></h2>
      <p class="mb-2 small">
        <?php foreach (APP_STATUSES as $s): if ($c[$s]): ?><span class="badge text-bg-light border"><?= e($s) ?>: <?= $c[$s] ?></span> <?php endif; endforeach; ?>
        <?= array_sum($c) ? '' : '<span class="text-muted">Nothing saved yet.</span>' ?>
      </p>
      <?php if ($saved): ?>
        <ul class="list-unstyled mb-3">
          <?php foreach (array_slice($saved, 0, 5) as $r): ?>
            <li class="mb-1"><a href="<?= e(url('scholarship.php?id=' . (int)$r['scholarship_id'])) ?>"><?= e($r['name']) ?></a>
              <span class="text-muted small">· <?= e($r['app_status']) ?></span>
              <?= in_array($r['app_status'], APP_STATUSES_OPEN, true) ? '<br>' . due_badge($r['deadline'], $r['is_rolling']) : '' ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($saved) > 5): ?><p class="small text-muted"><?= count($saved) - 5 ?> more saved.</p><?php endif; ?>
      <?php endif; ?>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-primary" href="<?= e(url('my/matches.php?profile=' . (int)$k['id'])) ?>">Matches<span class="visually-hidden"> for <?= e($k['display_name']) ?></span></a>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('my/saved.php?profile=' . (int)$k['id'])) ?>">All saved<span class="visually-hidden"> for <?= e($k['display_name']) ?></span></a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('my/profile_edit.php?id=' . (int)$k['id'])) ?>">Edit<span class="visually-hidden"> <?= e($k['display_name']) ?></span></a>
      </div>
    </div></div></div>
  <?php endforeach; ?>
</div>
<h2 class="h4 mt-4">Next 60 days, all children</h2>
<?php if (!$soon['dated']): ?><p class="text-muted">No deadlines in the next 60 days for scholarships still to do.</p>
<?php else: ?>
<div class="table-responsive"><table class="table align-middle">
  <caption class="visually-hidden">Upcoming deadlines for all children</caption>
  <thead><tr><th scope="col">Deadline</th><th scope="col">Child</th><th scope="col">Scholarship</th><th scope="col">Status</th></tr></thead>
  <tbody>
    <?php foreach ($soon['dated'] as $r): ?>
      <tr><td class="text-nowrap"><?= due_badge($r['deadline'], false) ?></td><td><?= e($r['child']) ?></td>
        <td><a href="<?= e(url('scholarship.php?id=' . (int)$r['scholarship_id'])) ?>"><?= e($r['name']) ?></a></td><td><?= e($r['app_status']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
<p><a href="<?= e(url('my/deadlines.php?scope=all')) ?>">See every upcoming deadline</a></p>
<?php endif;
require __DIR__ . '/../includes/footer.php';
