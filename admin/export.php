<?php
/**
 * CSV export of the full or a filtered dataset, using the import template's column names (FR-DQ-03).
 * Admin only: the file can contain the students' notes. Every download is written to the audit log.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/scholarships.php';
require_once __DIR__ . '/../includes/ui.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$p = export_params($_GET);

if (isset($_GET['download'])) {
    $name = 'scholarships_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    $n = export_write($pdo, $p, $out);
    fclose($out);
    audit_log($pdo, 'export', null, null, ['rows' => $n, 'filters' => $p]);
    exit;
}

$ref = scholarship_ref($pdo);
$students = $pdo->query('SELECT id, last_name, first_name FROM student_assignments ORDER BY last_name, first_name')->fetchAll();
$count = export_count($pdo, $p);
$query = http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 0) + ['f' => 1]);
$pageTitle = 'Export CSV';
require __DIR__ . '/../includes/header.php';
?>
<h1>Export CSV</h1>
<p class="text-muted">Downloads the scholarships with the same column names as the import template, so the file can go back to the Data Science class or be uploaded again.</p>
<form method="get" class="search-form mb-3" aria-label="Export filters">
  <input type="hidden" name="f" value="1">
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label" for="x-status">Status</label>
      <select class="form-select" id="x-status" name="status"><?php foreach (['active' => 'Active only', 'archived' => 'Archived only', 'all' => 'Active and archived'] as $v => $l): ?>
        <option value="<?= $v ?>"<?= $p['status'] === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="x-scope">Level</label>
      <select class="form-select" id="x-scope" name="scope"><?php foreach (['all' => 'State and national', 'state_local' => 'State/local only', 'national' => 'National only'] as $v => $l): ?>
        <option value="<?= $v ?>"<?= $p['scope'] === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="x-state">State</label>
      <select class="form-select" id="x-state" name="state"><option value="">All</option>
        <?php foreach ($ref['states'] as $code => $n): ?><option value="<?= e($code) ?>"<?= $p['state'] === $code ? ' selected' : '' ?>><?= e($code === 'US' ? 'National' : $n) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="x-theme">National theme</label>
      <select class="form-select" id="x-theme" name="theme"><option value="0">All</option>
        <?php foreach ($ref['themes'] as $id => $n): ?><option value="<?= (int)$id ?>"<?= $p['theme'] === (int)$id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="x-student">Collected by</label>
      <select class="form-select" id="x-student" name="student"><option value="0">Anyone</option>
        <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $p['student'] === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['last_name'] . ', ' . $s['first_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="x-level">School level</label>
      <select class="form-select" id="x-level" name="level"><?php foreach (['' => 'Any', 'middle' => 'Middle only', 'high' => 'High only', 'both' => 'Both'] as $v => $l): ?>
        <option value="<?= $v ?>"<?= $p['level'] === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="x-exp">Past deadlines</label>
      <select class="form-select" id="x-exp" name="expired"><?php foreach (['include' => 'Include', 'exclude' => 'Leave out', 'only' => 'Only expired'] as $v => $l): ?>
        <option value="<?= $v ?>"<?= $p['expired'] === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3 d-flex flex-column justify-content-end">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="x-notes" name="include_notes" value="1"<?= $p['include_notes'] ? ' checked' : '' ?>><label class="form-check-label" for="x-notes">Include the Notes column</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="x-stu" name="include_student" value="1"<?= $p['include_student'] ? ' checked' : '' ?>><label class="form-check-label" for="x-stu">Add a "Contributing student" column (for a combined file)</label></div>
    </div>
  </div>
  <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
    <button class="btn btn-outline-primary" type="submit">Update count</button>
    <a class="btn btn-primary<?= $count === 0 ? ' disabled' : '' ?>" href="<?= e(url('admin/export.php?' . $query . '&download=1')) ?>"<?= $count === 0 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Download CSV</a>
    <span role="status"><strong><?= $count ?></strong> scholarship<?= $count === 1 ? '' : 's' ?> match<?= $count === 1 ? 'es' : '' ?>.</span>
  </div>
</form>
<p class="small text-muted">Opening the file in Excel: it is UTF-8 with a byte-order mark, so accents display correctly. Text that starts with = + - or @ gets a leading apostrophe so spreadsheets do not run it as a formula.</p>
<?php require __DIR__ . '/../includes/footer.php';
