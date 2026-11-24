<?php
/** POST handler for the upload form (FR-IMP-02, FR-IMP-03). Stages every file; commits nothing. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/import/intake.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

// If the whole request was bigger than post_max_size, PHP drops $_POST and $_FILES (and the CSRF token with them).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('danger', 'That upload was too large for the server. Try fewer or smaller files.');
    redirect('admin/import/index.php');
}
require_csrf();

$pdo = db();
$maxBytes = (int)config('app.max_upload_mb', 5) * 1024 * 1024;
$f = $_FILES['files'] ?? null;
if (!$f || !is_array($f['name'])) {
    flash('danger', 'No files were received.');
    redirect('admin/import/index.php');
}

$ctx = load_import_context($pdo);   // shared across the files below, so duplicates are caught ACROSS files
$results = [];
foreach ($f['name'] as $i => $origName) {
    $name = basename((string)$origName);
    $err = check_upload($name, (string)$f['tmp_name'][$i], (int)$f['size'][$i], (int)$f['error'][$i], $maxBytes);
    if ($err !== null) {
        $results[] = ['file' => $name, 'ok' => false, 'message' => $err];
        continue;
    }
    $results[] = stage_upload($pdo, $name, (string)$f['tmp_name'][$i], $ctx);
}

$staged = count(array_filter($results, fn($r) => $r['ok']));
if ($staged === 1 && count($results) === 1) {
    redirect('admin/import/report.php?batch=' . (int)$results[0]['batch_id']);   // one file: go straight to its report
}

$pageTitle = 'Upload results';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Upload results</h1>
<p><?= $staged ?> of <?= count($results) ?> file<?= count($results) === 1 ? '' : 's' ?> checked and staged. Nothing is saved to the scholarship list until you commit a batch.</p>
<div class="table-responsive">
<table class="table align-middle">
  <caption class="visually-hidden">Result for each uploaded file</caption>
  <thead><tr><th scope="col">File</th><th scope="col">Student</th><th scope="col">Result</th><th scope="col">Rows</th><th scope="col"></th></tr></thead>
  <tbody>
  <?php foreach ($results as $r): ?>
    <tr>
      <td><?= e($r['file']) ?></td>
      <?php if ($r['ok']): $c = $r['counts']; ?>
        <td><?= $r['student'] ? e($r['student']['first_name'] . ' ' . $r['student']['last_name']) : '<span class="text-muted">Unknown</span>' ?></td>
        <td><?= status_badge('staged') ?></td>
        <td>
          <?= (int)$c['total'] ?> total:
          <?= (int)$c['ready'] ?> ready, <?= (int)$c['warning'] ?> warning,
          <?= (int)$c['duplicate'] ?> duplicate, <?= (int)$c['error'] ?> error
        </td>
        <td><a class="btn btn-sm btn-primary" href="<?= e(url('admin/import/report.php?batch=' . (int)$r['batch_id'])) ?>">Review report</a></td>
      <?php else: ?>
        <td></td>
        <td><?= status_badge('error') ?></td>
        <td colspan="2" class="text-danger"><?= e($r['message']) ?></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p><a href="<?= e(url('admin/import/index.php')) ?>">Upload more files</a> · <a href="<?= e(url('admin/import/history.php')) ?>">Import history</a></p>
<?php require __DIR__ . '/../../includes/footer.php';
