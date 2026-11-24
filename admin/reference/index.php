<?php
/** FR-SA-04: Super-admin screens for reference data (states, provider types, categories, fields of study, national themes). */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/reference.php';
require_role([ROLE_SUPER_ADMIN]);

$pdo = db();
$tables = reference_tables();
$table = (string)($_REQUEST['table'] ?? 'states');
if (!isset($tables[$table])) { $table = 'states'; }
$cfg = $tables[$table];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $err = in_array($action, ['add', 'rename', 'delete'], true)
        ? reference_save($pdo, $table, $cfg, $action, (string)($_POST['id'] ?? ''), (string)($_POST['name'] ?? ''))
        : 'Unknown action.';
    $err ? flash('danger', $err) : flash('success', 'Saved.');
    redirect('admin/reference/index.php?table=' . urlencode($table));
}
$rows = reference_rows($pdo, $table, $cfg);
$pageTitle = 'Reference data';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Reference data</h1>
<ul class="nav nav-tabs mb-3">
  <?php foreach ($tables as $t => $c): ?>
    <li class="nav-item"><a class="nav-link<?= $t === $table ? ' active' : '' ?>" href="<?= e(url('admin/reference/index.php?table=' . $t)) ?>"<?= $t === $table ? ' aria-current="page"' : '' ?>><?= e($c['label']) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php if ($cfg['note']): ?><p class="text-muted"><?= e($cfg['note']) ?> Entries in use cannot be deleted; rename them instead.</p><?php endif; ?>
<form method="post" class="row g-2 align-items-end mb-4">
  <?= csrf_field() ?><input type="hidden" name="table" value="<?= e($table) ?>"><input type="hidden" name="action" value="add">
  <?php if ($cfg['key_manual']): ?>
    <div class="col-sm-2"><label class="form-label" for="new-id"><?= $cfg['key'] === 'code' ? 'Code' : 'Number' ?></label><input class="form-control" id="new-id" name="id" required maxlength="3"></div>
  <?php endif; ?>
  <div class="col-sm-5"><label class="form-label" for="new-name">New entry</label><input class="form-control" id="new-name" name="name" required maxlength="<?= (int)$cfg['name_max'] ?>"></div>
  <div class="col-auto"><button class="btn btn-primary" type="submit">Add</button></div>
</form>
<div class="table-responsive"><table class="table table-sm align-middle ref-table">
  <caption class="visually-hidden"><?= e($cfg['label']) ?></caption>
  <thead><tr><th scope="col"><?= $cfg['key'] === 'code' ? 'Code' : 'ID' ?></th><th scope="col">Name</th><th scope="col">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $locked = in_array($r['name'], $cfg['protected'], true) || in_array((string)$r['id'], $cfg['protected'], true); ?>
    <tr>
      <td><?= e((string)$r['id']) ?></td>
      <td>
        <form method="post" class="d-flex gap-2" aria-label="Rename <?= e($r['name']) ?>">
          <?= csrf_field() ?><input type="hidden" name="table" value="<?= e($table) ?>"><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
          <label class="visually-hidden" for="n-<?= e((string)$r['id']) ?>">Name for <?= e($r['name']) ?></label>
          <input class="form-control form-control-sm" id="n-<?= e((string)$r['id']) ?>" name="name" value="<?= e($r['name']) ?>" maxlength="<?= (int)$cfg['name_max'] ?>" required<?= $locked ? ' readonly' : '' ?>>
          <button class="btn btn-sm btn-outline-primary" type="submit"<?= $locked ? ' disabled' : '' ?>>Rename</button>
        </form>
      </td>
      <td>
        <form method="post" class="d-inline" data-confirm="Delete <?= e($r['name']) ?>?">
          <?= csrf_field() ?><input type="hidden" name="table" value="<?= e($table) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
          <button class="btn btn-sm btn-outline-danger" type="submit"<?= $locked ? ' disabled' : '' ?>>Delete</button>
        </form>
        <?= $locked ? '<span class="small text-muted ms-2">Used by the system</span>' : '' ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php
$extraScripts = <<<'JS'
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (ev) { if (!window.confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); } });
});
</script>
JS;
require __DIR__ . '/../../includes/footer.php';
