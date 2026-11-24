<?php
/** FR-USER-03: "My Matches". Scholarships that fit the profile (state + national, grade, interests, deadline not passed). */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
[$pdo, $uid, $role] = my_guard();
$profile = my_profile_for_page($pdo, $uid);
$all = profiles_for_user($pdo, $uid);
$page = is_string($_GET['page'] ?? null) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
$res = profile_matches($pdo, $profile, $page);
$ctx = save_context($pdo);
$pageTitle = 'My Matches';
$back = 'my/matches.php' . ($res['page'] > 1 ? '?page=' . $res['page'] : '');
require __DIR__ . '/../includes/header.php';
?>
<h1>My Matches<?= count($all) > 1 ? ' for ' . e($profile['display_name']) : '' ?></h1>
<?= child_switcher($all, $profile, $back) ?>
<p class="text-muted">
  Grade <?= (int)$profile['grade'] ?> · <?= e($profile['state_name']) ?> and national ·
  <?= $profile['interests'] ? e(implode(', ', array_column($profile['interests'], 'name'))) : 'all fields' ?>.
  Deadlines that have passed are hidden. <a href="<?= e(url('my/profile_edit.php?id=' . (int)$profile['id'])) ?>">Change profile</a>
</p>
<p id="result-count" role="status">
  <?php if ($res['total'] === 0): ?><strong>0</strong> matches.<?php else: ?>Showing <strong><?= $res['from'] ?> to <?= $res['to'] ?></strong> of <strong><?= $res['total'] ?></strong> match<?= $res['total'] === 1 ? '' : 'es' ?>.<?php endif; ?>
</p>
<?php if (!$res['rows']): ?>
  <div class="empty-state"><p class="mb-1"><strong>No matches yet.</strong></p>
    <p class="mb-0">Try fewer interests, or <a href="<?= e(url('search.php?state=' . urlencode($profile['state_code']) . '&grade=' . (int)$profile['grade'])) ?>">search the whole list</a>.</p></div>
<?php else: ?>
  <div id="results" data-csrf="<?= e(csrf_token()) ?>" data-save-url="<?= e(url('my/save.php')) ?>">
  <?php foreach ($res['rows'] as $r): ?>
    <article class="card-result d-flex flex-wrap justify-content-between gap-2">
      <div class="flex-grow-1">
        <h2 class="h5 mb-1"><a href="<?= e(url('scholarship.php?id=' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a></h2>
        <p class="mb-1 text-muted"><?= e($r['provider']) ?> · <?= e(state_label($r['state_code'], $r['state_name'])) ?></p>
        <p class="mb-1"><strong><?= e(fmt_money_range($r['award_min'], $r['award_max'])) ?></strong> · <?= due_badge($r['deadline'], $r['is_rolling']) ?></p>
        <p class="small mb-0 text-muted">Why it matches: <?= $r['state_code'] === 'US' ? 'national' : e($r['state_name']) ?>, grade <?= (int)$profile['grade'] ?>,
          <?= $r['matched_fields'] ? e(implode(', ', $r['matched_fields'])) : 'open to any field' ?></p>
      </div>
      <div class="align-self-start"><?= save_button($ctx, (int)$r['id'], $r['name']) ?></div>
    </article>
  <?php endforeach; ?>
  </div>
  <?php if ($res['pages'] > 1): ?>
  <nav aria-label="Match pages"><ul class="pagination">
    <li class="page-item<?= $res['page'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(url('my/matches.php?page=' . ($res['page'] - 1))) ?>">Previous</a></li>
    <li class="page-item disabled"><span class="page-link">Page <?= $res['page'] ?> of <?= $res['pages'] ?></span></li>
    <li class="page-item<?= $res['page'] >= $res['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(url('my/matches.php?page=' . ($res['page'] + 1))) ?>">Next</a></li>
  </ul></nav>
  <?php endif; ?>
<?php endif;
$extraScripts = '<script src="' . e(url('assets/js/save.js')) . '"></script>';
require __DIR__ . '/../includes/footer.php';
