<?php
/**
 * Import issues report: everything the checks found in the uploaded files, grouped so it can be sent back to the instructor
 * and the students who collected the data. ?batch=N limits it to one file; ?download=1 gives every message as CSV.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/quality.php';
require_once __DIR__ . '/../../includes/ui.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$batch = (int)($_GET['batch'] ?? 0) ?: null;
$rows = import_issue_rows($pdo, $batch);

if (isset($_GET['download'])) {
    $safe = fn(string $v) => ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="import_issues_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['File', 'Student', 'Row', 'Row status', 'Level', 'Column', 'Issue', 'Scholarship name', 'Official URL'], ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, [$r['file'], $r['student'], $r['row'], $r['status'], $r['level'], $r['field'], $safe($r['message']), $safe($r['name']), $safe($r['url'])], ',', '"', '');
    }
    fclose($out);
    audit_log($pdo, 'import_issues.download', null, null, ['rows' => count($rows), 'batch' => $batch]);
    exit;
}

$files = import_file_summary($pdo);
$summary = import_issue_summary($rows);
$errors = count(array_filter($rows, fn($r) => $r['level'] === 'error'));
$warnings = count(array_filter($rows, fn($r) => $r['level'] === 'warning'));
$dups = count(array_filter($rows, fn($r) => $r['level'] === 'duplicate'));
$pageTitle = 'Import issues report';
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
  <div><h1 class="mb-1">Import issues report</h1>
    <p class="text-muted mb-0">Problems the checks found in the uploaded files (staged and committed batches). Send the CSV to the instructor and the students.</p></div>
  <a class="btn btn-outline-primary no-print<?= $rows ? '' : ' disabled' ?>" href="<?= e(url('admin/import/issues.php?download=1' . ($batch ? '&batch=' . $batch : ''))) ?>">Download every issue (CSV)</a>
</div>
<?php if ($batch): ?><p>Showing one file only. <a href="<?= e(url('admin/import/issues.php')) ?>">Show all files</a></p><?php endif; ?>

<div class="row g-3 mb-4">
  <?= stat_card((string)count($files), 'Files loaded') ?>
  <?= stat_card((string)$errors, 'Errors', 'Rows that cannot be imported', $errors ? 'bad' : 'good') ?>
  <?= stat_card((string)$warnings, 'Warnings', 'Imported, but worth fixing', $warnings ? 'warn' : 'good') ?>
  <?= stat_card((string)$dups, 'Duplicates', 'Same URL seen before') ?>
</div>

<?php if (!$files): ?><div class="empty-state">No files have been loaded yet. Upload some on the Import data page.</div><?php else: ?>
<h2 class="h4">By file</h2>
<div class="table-responsive mb-4"><table class="table table-sm align-middle">
  <caption class="visually-hidden">Result of each uploaded file</caption>
  <thead><tr><th scope="col">File</th><th scope="col">Student</th><th scope="col">Status</th><th scope="col" class="text-end">Rows</th><th scope="col" class="text-end">Ready</th>
    <th scope="col" class="text-end">Warning</th><th scope="col" class="text-end">Duplicate</th><th scope="col" class="text-end">Error</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead>
  <tbody><?php foreach ($files as $f): ?>
    <tr><td><?= e($f['source_file_name']) ?></td><td><?= e($f['student'] ?? 'Unknown / combined') ?></td><td><?= status_badge($f['status']) ?></td>
      <td class="text-end"><?= (int)$f['rows_total'] ?></td><td class="text-end"><?= (int)$f['rows_ready'] ?></td><td class="text-end"><?= (int)$f['rows_warning'] ?></td>
      <td class="text-end"><?= (int)$f['rows_duplicate'] ?></td><td class="text-end"><?= (int)$f['rows_error'] ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/import/issues.php?batch=' . (int)$f['id'])) ?>">Only this file</a></td></tr>
  <?php endforeach; ?></tbody></table></div>

<h2 class="h4">Most common problems</h2>
<?php if (!$summary): ?><div class="empty-state">No issues found. Nice.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle">
  <caption class="visually-hidden">Problems grouped by kind, most common first</caption>
  <thead><tr><th scope="col">Level</th><th scope="col">Column</th><th scope="col">Problem</th><th scope="col" class="text-end">Times</th><th scope="col" class="text-end">Files</th><th scope="col">Example</th></tr></thead>
  <tbody><?php foreach ($summary as $s): ?>
    <tr><td><?= status_badge($s['level'] === 'duplicate' ? 'duplicate' : ($s['level'] === 'error' ? 'error' : 'warning')) ?></td><td><?= e($s['field'] ?: 'General') ?></td>
      <td><?= e($s['pattern']) ?></td><td class="text-end"><strong><?= (int)$s['count'] ?></strong></td><td class="text-end"><?= (int)$s['files'] ?></td><td class="small text-muted"><?= e($s['example']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php';
