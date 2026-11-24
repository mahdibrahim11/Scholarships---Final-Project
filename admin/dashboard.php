<?php
/** Admin dashboard (FR-DASH-02): summary cards and Chart.js charts about the data, the import, quality and anonymous searches. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/quality.php';
require_once __DIR__ . '/../includes/ui.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$target = (int)($_GET['target'] ?? STUDENT_TARGET);
if (!in_array($target, [20, 40, 60], true)) { $target = STUDENT_TARGET; }
$d = admin_dashboard_data($pdo, $target);
$f = public_facts($pdo);
$q = $d['quality'];
$totalStates = (int)$pdo->query("SELECT COUNT(*) FROM states WHERE code <> 'US'")->fetchColumn();
$totalThemes = (int)$pdo->query('SELECT COUNT(*) FROM national_themes')->fetchColumn();
$staged = $d['batches']['staged'] ?? 0;
$pageTitle = 'Admin dashboard';
$extraScripts = chart_scripts();
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
  <div><h1 class="mb-1">Admin dashboard</h1><p class="text-muted mb-0">Live numbers from the database. Updated every time you open the page.</p></div>
  <form method="get" class="no-print d-flex align-items-end gap-2">
    <div><label class="form-label mb-0" for="target">Target per student</label>
      <select class="form-select" id="target" name="target"><?php foreach ([20, 40, 60] as $v): ?><option value="<?= $v ?>"<?= $v === $target ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Update</button>
  </form>
</div>

<div class="row g-3 mb-4">
  <?= stat_card((string)$q['active'], 'Active scholarships', $d['archived'] . ' archived') ?>
  <?= stat_card($d['states_with'] . ' of ' . $totalStates, 'States with data', 'State-level scholarships', $d['states_with'] < $totalStates ? 'warn' : 'good') ?>
  <?= stat_card($d['themes_with'] . ' of ' . $totalThemes, 'National themes with data', '', $d['themes_with'] < $totalThemes ? 'warn' : 'good') ?>
  <?= stat_card($d['students_done'] . ' of ' . $d['students_total'], 'Students at ' . $target, 'Coverage report has details', $d['students_done'] < $d['students_total'] ? 'warn' : 'good') ?>
  <?= stat_card((string)($q['expired'] + 0), 'Expired deadlines', '', $q['expired'] ? 'bad' : 'good') ?>
  <?= stat_card((string)$q['stale'], 'Stale verification', 'Older than ' . STALE_VERIFICATION_DAYS . ' days', $q['stale'] ? 'warn' : 'good') ?>
  <?= stat_card((string)$q['missing'], 'Missing key fields', '', $q['missing'] ? 'warn' : 'good') ?>
  <?= stat_card((string)$q['duplicates'], 'Possible duplicate pairs', '', $q['duplicates'] ? 'warn' : 'good') ?>
  <?= stat_card((string)$staged, 'Batches waiting', 'Staged, not yet committed', $staged ? 'warn' : '') ?>
  <?= stat_card((string)$d['searches30'], 'Searches, last 30 days', 'Anonymous: state, grade, field') ?>
</div>
<p class="no-print">
  <a class="btn btn-outline-primary btn-sm" href="<?= e(url('admin/quality.php')) ?>">Data quality</a>
  <a class="btn btn-outline-primary btn-sm" href="<?= e(url('admin/coverage.php')) ?>">Coverage report</a>
  <a class="btn btn-outline-primary btn-sm" href="<?= e(url('admin/export.php')) ?>">Export CSV</a>
  <a class="btn btn-outline-primary btn-sm" href="<?= e(url('admin/import/issues.php')) ?>">Import issues report</a>
</p>

<div class="row g-3">
  <?= chart_block('c-quality', 'Records by data quality issue', 'bar', ['Expired deadline' => $q['expired'], 'Stale verification' => $q['stale'], 'Missing key fields' => $q['missing'], 'In a duplicate pair' => $q['duplicate_ids'], 'No issue' => $q['clean']], 'records') ?>
  <?= chart_block('c-states', 'State-level scholarships by state (top 15)', 'hbar', $f['by_state']) ?>
  <?= chart_block('c-students', 'Students by progress toward ' . $target, 'bar', $d['student_buckets'], 'students') ?>
  <?= chart_block('c-batches', 'Import batches by status', 'doughnut', $d['batches'], 'batches') ?>
  <?= chart_block('c-level', 'School level', 'doughnut', $f['level']) ?>
  <?= chart_block('c-sstate', 'Most searched states (90 days)', 'hbar', $d['top_states'], 'searches') ?>
  <?= chart_block('c-sfield', 'Most searched fields (90 days)', 'hbar', $d['top_fields'], 'searches') ?>
  <?= chart_block('c-sgrade', 'Searches by grade (90 days)', 'bar', $d['top_grades'], 'searches') ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
