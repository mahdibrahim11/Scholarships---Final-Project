<?php
/** Import history (FR-IMP-09): every batch, who it belongs to, its counts, and the actions that still apply. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$batches = db()->query("SELECT b.*, a.first_name, a.last_name, u.display_name,
        (SELECT COUNT(*) FROM scholarships s WHERE s.import_batch_id = b.id) AS live_count
    FROM import_batches b
    LEFT JOIN student_assignments a ON a.id = b.student_assignment_id
    LEFT JOIN users u ON u.id = b.uploaded_by
    ORDER BY b.id DESC")->fetchAll();

$pageTitle = 'Import history';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Import history</h1>
<p><a class="btn btn-primary btn-sm" href="<?= e(url('admin/import/index.php')) ?>">Upload files</a>
   <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/coverage.php')) ?>">Coverage report</a></p>
<?php if (!$batches): ?>
  <div class="empty-state">Nothing has been imported yet. <a href="<?= e(url('admin/import/index.php')) ?>">Upload the first file.</a></div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm align-middle">
  <caption class="visually-hidden">All import batches, newest first</caption>
  <thead><tr>
    <th scope="col">Batch</th><th scope="col">File</th><th scope="col">Student</th><th scope="col">Uploaded</th>
    <th scope="col">Status</th><th scope="col">Rows (ready / warning / duplicate / error)</th><th scope="col">In list now</th><th scope="col">Actions</th>
  </tr></thead>
  <tbody>
  <?php foreach ($batches as $b): ?>
    <tr>
      <td>#<?= (int)$b['id'] ?></td>
      <td><?= e($b['source_file_name']) ?></td>
      <td><?= $b['last_name'] ? e($b['first_name'] . ' ' . $b['last_name']) : '<span class="text-muted">Unknown</span>' ?></td>
      <td><?= e(date('M j, Y g:i a', strtotime($b['uploaded_at']))) ?><br><small class="text-muted"><?= e($b['display_name'] ?? '') ?></small></td>
      <td><?= status_badge($b['status']) ?></td>
      <td><?= (int)$b['rows_ready'] ?> / <?= (int)$b['rows_warning'] ?> / <?= (int)$b['rows_duplicate'] ?> / <?= (int)$b['rows_error'] ?></td>
      <td><?= (int)$b['live_count'] ?></td>
      <td class="text-nowrap">
        <?php if ($b['status'] !== 'discarded'): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/import/report.php?batch=' . (int)$b['id'])) ?>">Report</a>
        <?php endif; ?>
        <?php if ($b['status'] === 'committed'): ?>
          <form class="d-inline" method="post" action="<?= e(url('admin/import/rollback.php')) ?>"
                onsubmit="return confirm('Roll back batch <?= (int)$b['id'] ?>? This deletes the scholarships it created.');">
            <?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Roll back</button>
          </form>
        <?php elseif ($b['status'] === 'staged'): ?>
          <form class="d-inline" method="post" action="<?= e(url('admin/import/discard.php')) ?>"
                onsubmit="return confirm('Discard batch <?= (int)$b['id'] ?>?');">
            <?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Discard</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php';
