<?php
/** Edit a provider's name, type and website. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/scholarships.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM providers WHERE id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { flash('danger', 'That provider was not found.'); redirect('admin/providers/index.php'); }
$types = $pdo->query('SELECT id, name FROM provider_types ORDER BY name')->fetchAll(PDO::FETCH_KEY_PAIR);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $typeId = (int)($_POST['provider_type_id'] ?? 0);
    if ($typeId && !isset($types[$typeId])) { $typeId = 0; }
    $error = update_provider($pdo, $id, (string)($_POST['name'] ?? ''), $typeId ?: null, (string)($_POST['website'] ?? '')) ?? '';
    if ($error === '') { flash('success', 'Provider saved.'); redirect('admin/providers/index.php'); }
    $p['name'] = (string)($_POST['name'] ?? ''); $p['website'] = (string)($_POST['website'] ?? ''); $p['provider_type_id'] = $typeId;
}
$pageTitle = 'Edit provider';
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="Breadcrumb"><a href="<?= e(url('admin/providers/index.php')) ?>">Providers</a> / Edit</nav>
<h1 class="mt-2">Edit provider</h1>
<form method="post" id="prov-form" novalidate style="max-width:36rem">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="mb-3"><label class="form-label" for="p-name">Name <span class="text-danger" aria-hidden="true">*</span></label>
    <input class="form-control<?= $error && stripos($error, 'name') !== false ? ' is-invalid' : '' ?>" id="p-name" name="name" maxlength="255" required value="<?= e($p['name']) ?>" aria-describedby="p-name-err">
    <div class="invalid-feedback" id="p-name-err"><?= e($error) ?></div></div>
  <div class="mb-3"><label class="form-label" for="p-type">Type</label>
    <select class="form-select" id="p-type" name="provider_type_id"><option value="">Not listed</option>
      <?php foreach ($types as $tid => $tn): ?><option value="<?= (int)$tid ?>"<?= (int)$p['provider_type_id'] === (int)$tid ? ' selected' : '' ?>><?= e($tn) ?></option><?php endforeach; ?></select></div>
  <div class="mb-3"><label class="form-label" for="p-web">Website</label>
    <input class="form-control<?= $error && stripos($error, 'web address') !== false ? ' is-invalid' : '' ?>" id="p-web" name="website" maxlength="500" inputmode="url" value="<?= e($p['website'] ?? '') ?>">
    <div class="invalid-feedback"><?= e($error) ?></div></div>
  <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/providers/index.php')) ?>">Cancel</a></div>
</form>
<?php
$extraScripts = <<<'JS'
<script>
$('#prov-form').on('submit', function (ev) {
  if (!$.trim($('#p-name').val())) {
    ev.preventDefault();
    $('#p-name').addClass('is-invalid'); $('#p-name-err').text('Enter the provider name.'); $('#p-name').trigger('focus');
  }
});
</script>
JS;
require __DIR__ . '/../../includes/footer.php';
