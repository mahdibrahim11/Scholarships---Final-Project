<?php
/**
 * Coverage report (FR-DQ-02): by student (target), by state, by national theme. Gaps are flagged with a word AND a colour.
 * ?target=20|40 changes the per-student target (20 for one collection round, 40 after two). ?download=students|states|themes gives CSV.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/quality.php';
require_once __DIR__ . '/../includes/ui.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$target = (int)($_GET['target'] ?? STUDENT_TARGET);
if (!in_array($target, [20, 40, 60], true)) { $target = STUDENT_TARGET; }
$students = student_progress($pdo, $target);
$states = coverage_by_state($pdo);
$themes = coverage_by_theme($pdo);

$download = (string)($_GET['download'] ?? '');
if ($download !== '') {
    $sets = [
        'students' => [['Last name', 'First name', 'State-level', 'National', 'Total', 'Target', 'Short by', 'Waiting in staging'],
                       array_map(fn($r) => [$r['last_name'], $r['first_name'], $r['state_level'], $r['national'], $r['total'], $target, $r['short'], $r['staged']], $students)],
        'states'   => [['State', 'Active scholarships', 'Open now', 'Middle school', 'High school', 'Providers', 'Flag', 'Other gaps', 'Assigned students'],
                       array_map(fn($r) => [$r['name'], $r['total'], $r['open_now'], $r['middle'], $r['high'], $r['providers'], $r['flag'], implode('; ', $r['gaps']), $r['assigned'] ?? ''], $states)],
        'themes'   => [['Theme #', 'National theme', 'Active scholarships', 'Open now', 'Middle school', 'High school', 'Providers', 'Flag', 'Other gaps', 'Assigned students'],
                       array_map(fn($r) => [$r['id'], $r['name'], $r['total'], $r['open_now'], $r['middle'], $r['high'], $r['providers'], $r['flag'], implode('; ', $r['gaps']), $r['assigned'] ?? ''], $themes)],
    ];
    if (!isset($sets[$download])) { http_response_code(400); exit('Unknown report.'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="coverage_' . $download . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $sets[$download][0], ',', '"', '');
    foreach ($sets[$download][1] as $row) { fputcsv($out, $row, ',', '"', ''); }
    fclose($out);
    exit;
}

$done = count(array_filter($students, fn($r) => $r['total'] >= $target));
$statesNone = count(array_filter($states, fn($r) => $r['flag'] === 'none'));
$statesThin = count(array_filter($states, fn($r) => $r['flag'] === 'thin'));
$themesNone = count(array_filter($themes, fn($r) => $r['flag'] === 'none'));
$themesThin = count(array_filter($themes, fn($r) => $r['flag'] === 'thin'));
$unthemed = coverage_unthemed_national($pdo);
$flagBadge = fn(string $f) => $f === 'none' ? '<span class="badge text-bg-danger">None</span>' : ($f === 'thin' ? '<span class="badge text-bg-warning text-dark">Thin</span>' : '<span class="badge text-bg-success">OK</span>');
$pageTitle = 'Coverage report';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
  <div><h1 class="mb-1">Coverage report</h1>
    <p class="text-muted mb-0">Counts include only active (committed, not archived) scholarships. "Thin" means fewer than <?= COVERAGE_THIN_BELOW ?>.</p></div>
  <form method="get" class="no-print d-flex align-items-end gap-2">
    <div><label class="form-label mb-0" for="target">Target per student</label>
      <select class="form-select" id="target" name="target">
        <?php foreach ([20 => '20 (one round)', 40 => '40 (two rounds)', 60 => '60 (three rounds)'] as $v => $l): ?>
          <option value="<?= $v ?>"<?= $v === $target ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></div>
    <button class="btn btn-primary" type="submit">Update</button>
  </form>
</div>

<div class="row g-3 my-2">
  <?= stat_card($done . ' of ' . count($students), 'Students at target', 'Target: ' . $target . ' each', $done === count($students) ? 'good' : 'warn') ?>
  <?= stat_card((string)($statesNone), 'States with no scholarships', $statesThin . ' more are thin', $statesNone ? 'bad' : 'good') ?>
  <?= stat_card((string)$themesNone, 'National themes with none', $themesThin . ' more are thin', $themesNone ? 'bad' : 'good') ?>
  <?= stat_card((string)$unthemed, 'National with no theme', 'Cannot be counted by theme', $unthemed ? 'warn' : 'good') ?>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-students" type="button" role="tab" aria-selected="true">By student</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-states" type="button" role="tab" aria-selected="false">By state</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-themes" type="button" role="tab" aria-selected="false">By national theme</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="tab-students" role="tabpanel">
  <p><a href="<?= e(url('admin/coverage.php?download=students&target=' . $target)) ?>">Download as CSV</a></p>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <caption class="visually-hidden">Scholarships delivered by each student against a target of <?= $target ?></caption>
    <thead><tr><th scope="col">Student</th><th scope="col">State</th><th scope="col" class="text-end">State-level</th><th scope="col" class="text-end">National</th>
      <th scope="col" class="text-end">Total</th><th scope="col" class="text-end">In staging</th><th scope="col">Progress</th></tr></thead>
    <tbody><?php foreach ($students as $r): $p = min(100, (int)round(100 * $r['total'] / max(1, $target))); ?>
      <tr<?= $r['total'] === 0 ? ' class="flag-none"' : ($r['short'] > 0 ? ' class="flag-thin"' : '') ?>>
        <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td><td><?= e($r['state_name']) ?></td>
        <td class="text-end"><?= $r['state_level'] ?></td><td class="text-end"><?= $r['national'] ?></td>
        <td class="text-end"><strong><?= $r['total'] ?></strong> / <?= $target ?></td><td class="text-end"><?= $r['staged'] ?></td>
        <td style="min-width:11rem"><?= $r['short'] === 0 ? '<span class="badge text-bg-success">Complete</span>' : '<span class="badge text-bg-warning text-dark">' . $r['short'] . ' short</span>' ?>
          <div class="progress progress-sm mt-1" role="progressbar" aria-label="Progress for <?= e($r['first_name'] . ' ' . $r['last_name']) ?>" aria-valuenow="<?= $p ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= $p ?>%"></div></div></td>
      </tr><?php endforeach; ?></tbody></table></div>
</div>

<div class="tab-pane fade" id="tab-states" role="tabpanel">
  <p><a href="<?= e(url('admin/coverage.php?download=states')) ?>">Download as CSV</a> &middot; National scholarships are in the next tab.</p>
  <div class="table-responsive"><table class="table table-sm align-middle table-flag">
    <caption class="visually-hidden">State-level scholarships per state</caption>
    <thead><tr><th scope="col">State</th><th scope="col">Status</th><th scope="col" class="text-end">Active</th><th scope="col" class="text-end">Open now</th>
      <th scope="col" class="text-end">Middle</th><th scope="col" class="text-end">High</th><th scope="col" class="text-end">Providers</th><th scope="col">Other gaps</th><th scope="col">Assigned to</th></tr></thead>
    <tbody><?php foreach ($states as $r): ?>
      <tr class="flag-<?= e($r['flag']) ?>"><td><?= e($r['name']) ?></td><td><?= $flagBadge($r['flag']) ?></td>
        <td class="text-end"><?= $r['total'] ?></td><td class="text-end"><?= $r['open_now'] ?></td><td class="text-end"><?= $r['middle'] ?></td><td class="text-end"><?= $r['high'] ?></td>
        <td class="text-end"><?= $r['providers'] ?></td><td class="small"><?= e(implode(', ', $r['gaps'])) ?></td><td class="small"><?= e($r['assigned'] ?? 'No one yet') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>

<div class="tab-pane fade" id="tab-themes" role="tabpanel">
  <p><a href="<?= e(url('admin/coverage.php?download=themes')) ?>">Download as CSV</a></p>
  <div class="table-responsive"><table class="table table-sm align-middle table-flag">
    <caption class="visually-hidden">National scholarships per theme</caption>
    <thead><tr><th scope="col">#</th><th scope="col">National theme</th><th scope="col">Status</th><th scope="col" class="text-end">Active</th><th scope="col" class="text-end">Open now</th>
      <th scope="col" class="text-end">Middle</th><th scope="col" class="text-end">Providers</th><th scope="col">Other gaps</th><th scope="col">Assigned to</th></tr></thead>
    <tbody><?php foreach ($themes as $r): ?>
      <tr class="flag-<?= e($r['flag']) ?>"><td><?= (int)$r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= $flagBadge($r['flag']) ?></td>
        <td class="text-end"><?= $r['total'] ?></td><td class="text-end"><?= $r['open_now'] ?></td><td class="text-end"><?= $r['middle'] ?></td>
        <td class="text-end"><?= $r['providers'] ?></td><td class="small"><?= e(implode(', ', $r['gaps'])) ?></td><td class="small"><?= e($r['assigned'] ?? 'No one yet') ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php';
