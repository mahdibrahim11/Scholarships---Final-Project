<?php
/** FR-USER-04, 05: saved scholarships for one profile, with the application status of each. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
[$pdo, $uid, $role] = my_guard();
$profile = my_profile_for_page($pdo, $uid);
$all = profiles_for_user($pdo, $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {          // no-JavaScript fallback for the status form and the remove button
    require_csrf();
    $pid = is_string($_POST['profile_id'] ?? null) && ctype_digit($_POST['profile_id']) ? (int)$_POST['profile_id'] : 0;
    $sid = is_string($_POST['scholarship_id'] ?? null) && ctype_digit($_POST['scholarship_id']) ? (int)$_POST['scholarship_id'] : 0;
    if (($_POST['action'] ?? '') === 'remove') { [$ok, $code] = saved_set($pdo, $uid, $pid, $sid, false); }
    else { [$ok, $code] = saved_set_status($pdo, $uid, $pid, $sid, (string)($_POST['status'] ?? '')); }
    if ($code === 'not_found') { my_not_found('saved scholarship'); }
    $ok ? flash('success', $code === 'removed' ? 'Removed from your saved list.' : 'Status updated.') : flash('danger', 'That status is not valid.');
    redirect('my/saved.php?profile=' . $pid);
}
$filter = (string)($_GET['status'] ?? '');
if (!in_array($filter, APP_STATUSES, true)) { $filter = ''; }
$rows = saved_list($pdo, $profile, $filter ?: null);
$counts = status_counts($pdo, $profile);
$pageTitle = 'Saved scholarships';
require __DIR__ . '/../includes/header.php';
?>
<h1>Saved scholarships<?= count($all) > 1 ? ' for ' . e($profile['display_name']) : '' ?></h1>
<?= child_switcher($all, $profile, 'my/saved.php') ?>
<ul class="nav nav-pills mb-3" aria-label="Filter by status">
  <li class="nav-item"><a class="nav-link<?= $filter === '' ? ' active' : '' ?>" href="<?= e(url('my/saved.php')) ?>">All (<?= array_sum($counts) ?>)</a></li>
  <?php foreach (APP_STATUSES as $s): ?>
    <li class="nav-item"><a class="nav-link<?= $filter === $s ? ' active' : '' ?>" href="<?= e(url('my/saved.php?status=' . urlencode($s))) ?>"><?= e($s) ?> (<?= $counts[$s] ?>)</a></li>
  <?php endforeach; ?>
</ul>
<div id="status-live" class="visually-hidden" role="status" aria-live="polite"></div>
<?php if (!$rows): ?>
  <div class="empty-state"><?= $filter ? 'Nothing is marked ' . e($filter) . '.' : 'Nothing saved yet.' ?> Save scholarships from <a href="<?= e(url('my/matches.php')) ?>">My Matches</a> or <a href="<?= e(url('search.php')) ?>">search</a>.</div>
<?php else: ?>
<div class="table-responsive"><table class="table align-middle saved-table" id="saved-table" data-csrf="<?= e(csrf_token()) ?>" data-status-url="<?= e(url('my/status.php')) ?>" data-save-url="<?= e(url('my/save.php')) ?>">
  <caption class="visually-hidden">Saved scholarships</caption>
  <thead><tr><th scope="col">Scholarship</th><th scope="col">Deadline</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $gone = $r['listing_status'] !== 'active'; $open = in_array($r['app_status'], APP_STATUSES_OPEN, true); ?>
    <tr>
      <td>
        <?php if ($gone): ?><span><?= e($r['name']) ?></span> <span class="badge text-bg-secondary">No longer listed</span>
        <?php else: ?><a href="<?= e(url('scholarship.php?id=' . (int)$r['scholarship_id'])) ?>"><?= e($r['name']) ?></a><?php endif; ?>
        <div class="small text-muted"><?= e($r['provider']) ?> · <?= e(fmt_money_range($r['award_min'], $r['award_max'])) ?></div>
      </td>
      <td><?= $open && !$gone ? due_badge($r['deadline'], $r['is_rolling']) : ($r['deadline'] ? e(date('M j, Y', strtotime($r['deadline']))) : ($r['is_rolling'] ? 'Rolling' : 'No fixed deadline')) ?></td>
      <td>
        <form method="post" class="d-flex gap-1 status-form">
          <?= csrf_field() ?><input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>"><input type="hidden" name="scholarship_id" value="<?= (int)$r['scholarship_id'] ?>">
          <label class="visually-hidden" for="st-<?= (int)$r['scholarship_id'] ?>">Status of <?= e($r['name']) ?></label>
          <select class="form-select form-select-sm js-status-select" id="st-<?= (int)$r['scholarship_id'] ?>" name="status">
            <?php foreach (APP_STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $r['app_status'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary js-status-button" type="submit">Update</button>
        </form>
      </td>
      <td>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>"><input type="hidden" name="scholarship_id" value="<?= (int)$r['scholarship_id'] ?>">
          <button class="btn btn-sm btn-outline-danger" type="submit">Remove<span class="visually-hidden"> <?= e($r['name']) ?></span></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif;
$extraScripts = '<script src="' . e(url('assets/js/save.js')) . '"></script>';
require __DIR__ . '/../includes/footer.php';
