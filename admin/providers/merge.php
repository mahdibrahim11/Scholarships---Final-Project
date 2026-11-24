<?php
/** Confirm (GET) and perform (POST) a provider merge. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/scholarships.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost) { require_csrf(); }
$src = $isPost ? $_POST : $_GET;
$from = (int)($src['from'] ?? 0);
$into = (int)($src['into'] ?? 0);

$get = $pdo->prepare('SELECT p.*, pt.name AS type_name, (SELECT COUNT(*) FROM scholarships s WHERE s.provider_id = p.id) AS n
                      FROM providers p LEFT JOIN provider_types pt ON pt.id = p.provider_type_id WHERE p.id = ?');
$get->execute([$from]); $a = $get->fetch();
$get->execute([$into]); $b = $get->fetch();
if (!$a || !$b || $from === $into) {
    flash('danger', 'Choose two different providers to merge.');
    redirect('admin/providers/index.php');
}

if ($isPost) {
    try {
        $moved = merge_providers($pdo, $from, $into);
        flash('success', 'Merged "' . $a['name'] . '" into "' . $b['name'] . '". ' . $moved . ' scholarship' . ($moved === 1 ? '' : 's') . ' moved.');
    } catch (Throwable $e) {
        error_log('merge_providers failed: ' . $e->getMessage());
        flash('danger', 'The merge failed and nothing was changed.');
    }
    redirect('admin/providers/index.php');
}

$pageTitle = 'Merge providers';
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="Breadcrumb"><a href="<?= e(url('admin/providers/index.php')) ?>">Providers</a> / Merge</nav>
<h1 class="mt-2">Merge providers</h1>
<div class="row g-3 my-2">
  <div class="col-md-6"><div class="card h-100 border-danger"><div class="card-body">
    <h2 class="h6 text-danger">Will be removed</h2>
    <p class="mb-1"><strong><?= e($a['name']) ?></strong></p>
    <p class="mb-0 small"><?= (int)$a['n'] ?> scholarship<?= (int)$a['n'] === 1 ? '' : 's' ?> · <?= e($a['type_name'] ?? 'No type') ?> · <?= e($a['website'] ?? 'No website') ?></p>
  </div></div></div>
  <div class="col-md-6"><div class="card h-100 border-success"><div class="card-body">
    <h2 class="h6 text-success">Will be kept</h2>
    <p class="mb-1"><strong><?= e($b['name']) ?></strong></p>
    <p class="mb-0 small"><?= (int)$b['n'] ?> scholarship<?= (int)$b['n'] === 1 ? '' : 's' ?> · <?= e($b['type_name'] ?? 'No type') ?> · <?= e($b['website'] ?? 'No website') ?></p>
  </div></div></div>
</div>
<p>All <?= (int)$a['n'] ?> scholarship<?= (int)$a['n'] === 1 ? '' : 's' ?> from "<?= e($a['name']) ?>" move to "<?= e($b['name']) ?>". This is recorded in the audit log and cannot be undone automatically.</p>
<form method="post" class="d-flex gap-2">
  <?= csrf_field() ?><input type="hidden" name="from" value="<?= $from ?>"><input type="hidden" name="into" value="<?= $into ?>">
  <button class="btn btn-danger" type="submit">Merge providers</button>
  <a class="btn btn-outline-secondary" href="<?= e(url('admin/providers/merge.php?from=' . $into . '&into=' . $from)) ?>">Swap which one is kept</a>
  <a class="btn btn-outline-secondary" href="<?= e(url('admin/providers/index.php')) ?>">Cancel</a>
</form>
<?php require __DIR__ . '/../../includes/footer.php';
