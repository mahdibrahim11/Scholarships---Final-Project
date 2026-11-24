<?php
/** Validation report for one batch (FR-IMP-07): counts by status, a row table with reasons, Commit and Discard. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/import/store.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$batchId = (int)($_GET['batch'] ?? 0);
$st = $pdo->prepare('SELECT b.*, a.first_name, a.last_name FROM import_batches b
                     LEFT JOIN student_assignments a ON a.id = b.student_assignment_id WHERE b.id = ?');
$st->execute([$batchId]);
$batch = $st->fetch();
if (!$batch) {
    http_response_code(404);
    $pageTitle = 'Batch not found';
    require __DIR__ . '/../../includes/header.php';
    echo '<h1>Batch not found</h1><p><a href="' . e(url('admin/import/history.php')) . '">Back to import history</a></p>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

$allowed = ['ready', 'warning', 'duplicate', 'error'];
$filter = in_array($_GET['status'] ?? '', $allowed, true) ? $_GET['status'] : '';
$sql = 'SELECT source_row, status, raw_json, cleaned_json, messages_json FROM import_staging_rows WHERE batch_id = ?';
$params = [$batchId];
if ($filter !== '') {
    $sql .= ' AND status = ?';
    $params[] = $filter;
}
$rows = $pdo->prepare($sql . ' ORDER BY source_row');
$rows->execute($params);
$rows = $rows->fetchAll();

$counts = ['ready' => (int)$batch['rows_ready'], 'warning' => (int)$batch['rows_warning'],
           'duplicate' => (int)$batch['rows_duplicate'], 'error' => (int)$batch['rows_error']];
$importable = $counts['ready'] + $counts['warning'];
$isStaged = $batch['status'] === 'staged';

// How close is this student to the 20-scholarship target?
$have = null;
if ($batch['student_assignment_id']) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM scholarships WHERE contributing_student_id = ? AND status = 'active'");
    $q->execute([(int)$batch['student_assignment_id']]);
    $have = (int)$q->fetchColumn();
}

$pageTitle = 'Import report: batch ' . $batchId;
require __DIR__ . '/../../includes/header.php';
$self = 'admin/import/report.php?batch=' . $batchId;
?>
<nav aria-label="Breadcrumb"><a href="<?= e(url('admin/import/history.php')) ?>">Import history</a> / Batch <?= $batchId ?></nav>
<h1 class="mt-2">Import report: <?= e($batch['source_file_name']) ?></h1>
<p>
  <?= status_badge($batch['status']) ?>
  Student: <strong><?= $batch['last_name'] ? e($batch['first_name'] . ' ' . $batch['last_name']) : 'Unknown (file name was not LastName_FirstName)' ?></strong>
  · Uploaded <?= e(date('M j, Y g:i a', strtotime($batch['uploaded_at']))) ?>
</p>

<div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter rows by status">
  <a class="btn btn-sm <?= $filter === '' ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= e(url($self)) ?>">All <?= (int)$batch['rows_total'] ?></a>
  <?php foreach ($counts as $k => $n): ?>
    <a class="btn btn-sm <?= $filter === $k ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= e(url($self . '&status=' . $k)) ?>"><?= e(ucfirst($k)) ?> <?= $n ?></a>
  <?php endforeach; ?>
</div>

<?php if ($have !== null): ?>
  <p class="small text-muted">
    <?= e($batch['first_name']) ?> has <strong><?= $have ?></strong> active scholarship<?= $have === 1 ? '' : 's' ?> in the list now.
    <?php if ($isStaged): ?>Committing this batch adds up to <strong><?= $importable ?></strong>, for <?= $have + $importable ?> of <?= STUDENT_TARGET ?>.<?php endif; ?>
  </p>
<?php endif; ?>

<?php if ($isStaged): ?>
  <div class="d-flex flex-wrap gap-2 mb-4">
    <form method="post" action="<?= e(url('admin/import/commit.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= $batchId ?>">
      <button class="btn btn-primary" type="submit" <?= $importable === 0 ? 'disabled' : '' ?>>Commit <?= $importable ?> row<?= $importable === 1 ? '' : 's' ?> (Ready and Warning)</button>
    </form>
    <form method="post" action="<?= e(url('admin/import/discard.php')) ?>" onsubmit="return confirm('Discard this file? Nothing will be imported and the report is deleted.');">
      <?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= $batchId ?>">
      <button class="btn btn-outline-danger" type="submit">Discard this file</button>
    </form>
  </div>
  <p class="small text-muted">Duplicate and Error rows are never imported. Warnings are imported; check their reasons first.</p>
<?php elseif ($batch['status'] === 'committed'): ?>
  <p>This batch was committed. To undo it, use <a href="<?= e(url('admin/import/history.php')) ?>">Import history</a>.</p>
<?php endif; ?>

<?php if ($rows): ?>
<div class="table-responsive">
<table class="table table-sm align-middle table-results">
  <caption class="visually-hidden">Rows in this import with their status and reasons</caption>
  <thead><tr><th scope="col">Row</th><th scope="col">Status</th><th scope="col">Scholarship</th><th scope="col">Provider</th><th scope="col">Reasons</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $clean = $r['cleaned_json'] ? json_decode($r['cleaned_json'], true) : [];
      $raw = json_decode($r['raw_json'], true) ?: [];
      $msgs = json_decode($r['messages_json'] ?? '[]', true) ?: [];
  ?>
    <tr>
      <td><?= (int)$r['source_row'] ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td><?= e($clean['name'] ?? $raw['name'] ?? '') ?></td>
      <td><?= e($clean['provider'] ?? $raw['provider'] ?? '') ?></td>
      <td>
        <?php if (!$msgs): ?><span class="text-muted">No issues</span>
        <?php else: ?><ul class="mb-0 ps-3"><?php foreach ($msgs as $m): ?><li><?= e($m['text']) ?></li><?php endforeach; ?></ul><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php elseif ($batch['status'] === 'discarded'): ?>
  <div class="empty-state">This file was discarded, so its rows were deleted.</div>
<?php else: ?>
  <div class="empty-state">No rows match this filter.</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php';
