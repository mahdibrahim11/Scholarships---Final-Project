<?php
/**
 * Data quality dashboard (FR-DQ-01): expired deadlines, stale verification dates, records missing key fields, possible duplicates.
 * ?type=expired|stale|missing|duplicates  shows one list in full (first 100 rows);  ?download=<type>|all  gives every row as CSV.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/quality.php';
require_once __DIR__ . '/../includes/ui.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$types = quality_issue_types();

// ---- CSV download of the issue list, for sending back to the instructor or the student who collected the data ----
$download = (string)($_GET['download'] ?? '');
if ($download !== '') {
    $which = $download === 'all' ? array_keys($types) : (isset($types[$download]) ? [$download] : []);
    if (!$which) { http_response_code(400); exit('Unknown issue type.'); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="data_quality_' . $download . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Issue type', 'Scholarship ID', 'Scholarship name', 'Provider', 'State', 'Contributing student', 'What is wrong', 'Edit link'], ',', '"', '');
    $n = 0;
    foreach ($which as $t) {
        foreach (quality_issue_rows($pdo, $t) as $r) {
            fputcsv($out, [$types[$t], $r['id'], csv_cell($r['name']), csv_cell($r['provider']), $r['state_code'], $r['student'] ?? '', csv_cell($r['issue']),
                           ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('admin/scholarships/edit.php?id=' . (int)$r['id'])], ',', '"', '');
            $n++;
        }
    }
    fclose($out);
    audit_log($pdo, 'quality.download', null, null, ['type' => $download, 'rows' => $n]);
    exit;
}

function csv_cell(string $v): string { return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v; }

$sum = quality_summary($pdo);
$type = (string)($_GET['type'] ?? '');
if (!isset($types[$type])) { $type = ''; }
$byStudent = quality_by_student($pdo);
$pageTitle = 'Data quality';
require __DIR__ . '/../includes/header.php';
$pct = $sum['active'] > 0 ? round(100 * $sum['clean'] / $sum['active']) : 0;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
  <div>
    <h1 class="mb-1">Data quality</h1>
    <p class="text-muted mb-0">Checks every active scholarship. A verification date older than <?= STALE_VERIFICATION_DAYS ?> days counts as stale.</p>
  </div>
  <a class="btn btn-outline-primary no-print" href="<?= e(url('admin/quality.php?download=all')) ?>">Download all issues (CSV)</a>
</div>

<?php if ($sum['active'] === 0): ?>
  <div class="empty-state">There are no active scholarships yet. Import some data first (Admin, Import data).</div>
<?php else: ?>
<div class="row g-3 mb-4">
  <?= stat_card((string)$sum['active'], 'Active scholarships') ?>
  <?= stat_card((string)$sum['clean'], 'Clean records', $pct . '% have no issue', $pct >= 80 ? 'good' : 'warn') ?>
  <?= stat_card((string)$sum['expired'], 'Expired deadlines', 'Hidden from public search', $sum['expired'] ? 'bad' : 'good') ?>
  <?= stat_card((string)$sum['stale'], 'Stale or never verified', 'Older than ' . STALE_VERIFICATION_DAYS . ' days', $sum['stale'] ? 'warn' : 'good') ?>
  <?= stat_card((string)$sum['missing'], 'Missing key fields', 'See the list below', $sum['missing'] ? 'warn' : 'good') ?>
  <?= stat_card((string)$sum['duplicates'], 'Possible duplicate pairs', $sum['duplicate_ids'] . ' records involved', $sum['duplicates'] ? 'warn' : 'good') ?>
</div>

<h2 class="h4">What is missing</h2>
<div class="table-responsive mb-4"><table class="table table-sm w-auto">
  <caption class="visually-hidden">Number of active scholarships missing each key field</caption>
  <thead><tr><th scope="col">Field</th><th scope="col" class="text-end">Records missing it</th></tr></thead>
  <tbody><?php foreach ($sum['missing_by'] as $m): ?><tr><td><?= e($m['label']) ?></td><td class="text-end"><?= (int)$m['count'] ?></td></tr><?php endforeach; ?></tbody>
</table></div>

<h2 class="h4">Issue lists</h2>
<ul class="nav nav-pills mb-3 no-print" aria-label="Issue type">
  <?php foreach ($types as $k => $label): $n = $k === 'duplicates' ? $sum['duplicates'] : $sum[$k]; ?>
    <li class="nav-item"><a class="nav-link<?= $type === $k || ($type === '' && $k === 'expired') ? ' active' : '' ?>" href="<?= e(url('admin/quality.php?type=' . $k)) ?>#list"><?= e($label) ?> (<?= (int)$n ?>)</a></li>
  <?php endforeach; ?>
</ul>
<?php
$shown = $type === '' ? 'expired' : $type;
$rows = quality_issue_rows($pdo, $shown, QUALITY_LIST_LIMIT);
$totalForType = $shown === 'duplicates' ? $sum['duplicates'] : $sum[$shown];
?>
<div id="list">
<?php if (!$rows): ?>
  <div class="empty-state">No <?= e(strtolower($types[$shown])) ?> found.</div>
<?php else: ?>
  <p class="small text-muted">Showing <?= count($rows) ?> of <?= (int)$totalForType ?>.
    <a href="<?= e(url('admin/quality.php?download=' . $shown)) ?>">Download all of them (CSV)</a></p>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <caption class="visually-hidden"><?= e($types[$shown]) ?></caption>
    <thead><tr><th scope="col">Scholarship</th><th scope="col">Provider</th><th scope="col">State</th><th scope="col">Collected by</th><th scope="col">What is wrong</th><th scope="col"><span class="visually-hidden">Action</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('admin/scholarships/view.php?id=' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a> <span class="text-muted small">#<?= (int)$r['id'] ?></span></td>
        <td><?= e($r['provider']) ?></td>
        <td><?= e($r['state_code'] === 'US' ? 'National' : $r['state_code']) ?></td>
        <td><?= e($r['student'] ?? 'Unknown') ?></td>
        <td><?= e($r['issue']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/scholarships/edit.php?id=' . (int)$r['id'])) ?>">Edit</a>
          <?php if (isset($r['other_id'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/scholarships/view.php?id=' . (int)$r['other_id'])) ?>">View other</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
</div>

<h2 class="h4 mt-4">By student</h2>
<p class="text-muted">Use this to report issues back to the person who collected the data.</p>
<?php if (!$byStudent): ?><div class="empty-state">No scholarship is linked to a student yet.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle">
  <caption class="visually-hidden">Issue counts per contributing student</caption>
  <thead><tr><th scope="col">Student</th><th scope="col" class="text-end">Active</th><th scope="col" class="text-end">Expired</th><th scope="col" class="text-end">Stale</th><th scope="col" class="text-end">Missing fields</th></tr></thead>
  <tbody><?php foreach ($byStudent as $s): ?>
    <tr><td><?= e($s['last_name'] . ', ' . $s['first_name']) ?></td><td class="text-end"><?= (int)$s['total'] ?></td>
        <td class="text-end"><?= (int)$s['expired'] ?></td><td class="text-end"><?= (int)$s['stale'] ?></td><td class="text-end"><?= (int)$s['missing'] ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
