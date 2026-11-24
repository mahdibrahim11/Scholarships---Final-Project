<?php
/** Provider list with possible duplicates and a manual merge picker (FR-ADM-03). */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/scholarships.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$providers = list_providers($pdo);
$pairs = provider_duplicate_pairs($providers);

$pageTitle = 'Providers';
$extraHead = '<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Providers</h1>
<p class="text-muted"><?= count($providers) ?> provider<?= count($providers) === 1 ? '' : 's' ?>. Merging moves every scholarship to the provider you keep and deletes the other one.</p>

<h2 class="h5 mt-4">Possible duplicates</h2>
<?php if (!$pairs): ?>
  <div class="empty-state">No likely duplicates found.</div>
<?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle">
  <caption class="visually-hidden">Provider pairs that may be the same organization</caption>
  <thead><tr><th scope="col">Provider</th><th scope="col">Provider</th><th scope="col">Why</th><th scope="col">Action</th></tr></thead>
  <tbody>
  <?php foreach ($pairs as $p): ?>
    <tr>
      <td><?= e($p['a']['name']) ?> <span class="text-muted">(<?= (int)$p['a']['scholarships'] ?>)</span></td>
      <td><?= e($p['b']['name']) ?> <span class="text-muted">(<?= (int)$p['b']['scholarships'] ?>)</span></td>
      <td><?= e($p['why']) ?></td>
      <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/providers/merge.php?from=' . (int)$p['a']['id'] . '&into=' . (int)$p['b']['id'])) ?>">Review merge</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>

<h2 class="h5 mt-4">Merge two providers yourself</h2>
<form method="get" action="<?= e(url('admin/providers/merge.php')) ?>" class="row g-2 align-items-end">
  <div class="col-md-5"><label class="form-label" for="m-from">Merge this one away</label>
    <select class="form-select" id="m-from" name="from" required><option value="">Choose</option>
      <?php foreach ($providers as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-5"><label class="form-label" for="m-into">Into this one (kept)</label>
    <select class="form-select" id="m-into" name="into" required><option value="">Choose</option>
      <?php foreach ($providers as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><button class="btn btn-primary" type="submit">Review merge</button></div>
</form>

<h2 class="h5 mt-4">All providers</h2>
<div class="table-responsive"><table class="table table-sm table-hover align-middle" id="prov-table">
  <thead><tr><th scope="col">Name</th><th scope="col">Type</th><th scope="col">Website</th><th scope="col">Scholarships</th><th scope="col" class="no-sort">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($providers as $p): ?>
    <tr>
      <td><?= e($p['name']) ?></td><td><?= e($p['type_name'] ?? '') ?></td><td><?= e($p['website'] ?? '') ?></td><td><?= (int)$p['scholarships'] ?></td>
      <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/providers/edit.php?id=' . (int)$p['id'])) ?>">Edit<span class="visually-hidden"> <?= e($p['name']) ?></span></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php
$extraScripts = <<<'JS'
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>$('#prov-table').DataTable({ pageLength: 25, columnDefs: [{ targets: 'no-sort', orderable: false }] });</script>
JS;
require __DIR__ . '/../../includes/footer.php';
