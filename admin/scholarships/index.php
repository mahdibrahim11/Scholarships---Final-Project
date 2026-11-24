<?php
/**
 * Admin scholarship list (FR-ADM-05): DataTables for sorting and searching, server-side filters for
 * state / student / import batch / national theme, and bulk actions (archive, mark verified).
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/scholarships.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$state = strtoupper(trim((string)($_GET['state'] ?? '')));
$student = (int)($_GET['student'] ?? 0);
$batch = (int)($_GET['batch'] ?? 0);
$theme = (int)($_GET['theme'] ?? 0);
$status = in_array($_GET['status'] ?? 'active', ['active', 'archived', 'all'], true) ? ($_GET['status'] ?? 'active') : 'active';

$where = [];
$params = [];
if ($state !== '')  { $where[] = 's.state_code = ?';              $params[] = $state; }
if ($student > 0)   { $where[] = 's.contributing_student_id = ?'; $params[] = $student; }
if ($batch > 0)     { $where[] = 's.import_batch_id = ?';         $params[] = $batch; }
if ($theme > 0)     { $where[] = 's.national_theme_id = ?';       $params[] = $theme; }
if ($status !== 'all') { $where[] = 's.status = ?';               $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$limit = 5000;
$st = $pdo->prepare("SELECT s.id, s.name, s.state_code, s.import_batch_id, s.deadline, s.is_rolling, s.verification_date, s.status,
        s.award_min, s.award_max, p.name AS provider, t.name AS theme,
        CONCAT(a.first_name, ' ', a.last_name) AS student
    FROM scholarships s
    JOIN providers p ON p.id = s.provider_id
    LEFT JOIN national_themes t ON t.id = s.national_theme_id
    LEFT JOIN student_assignments a ON a.id = s.contributing_student_id
    $whereSql ORDER BY s.id DESC LIMIT $limit");
$st->execute($params);
$rows = $st->fetchAll();

$states = $pdo->query('SELECT code, name FROM states ORDER BY (code = "US") DESC, name')->fetchAll();
$students = $pdo->query('SELECT id, last_name, first_name FROM student_assignments ORDER BY last_name, first_name')->fetchAll();
$batches = $pdo->query('SELECT id, source_file_name FROM import_batches WHERE status IN ("committed","rolled_back") ORDER BY id DESC')->fetchAll();
$themes = $pdo->query('SELECT id, name FROM national_themes ORDER BY name')->fetchAll();

$here = 'admin/scholarships/index.php' . ($_GET ? '?' . http_build_query($_GET) : '');
$pageTitle = 'Manage scholarships';
$extraHead = '<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">';
require __DIR__ . '/../../includes/header.php';

function opt(string $value, string $label, bool $selected): string
{
    return '<option value="' . e($value) . '"' . ($selected ? ' selected' : '') . '>' . e($label) . '</option>';
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
  <h1 class="mb-0">Manage scholarships</h1>
  <a class="btn btn-primary" href="<?= e(url('admin/scholarships/edit.php')) ?>">Add a scholarship</a>
</div>

<form method="get" class="row g-2 my-3 align-items-end" aria-label="Filter the scholarship list">
  <div class="col-6 col-md-2"><label class="form-label" for="f-state">State</label>
    <select class="form-select" id="f-state" name="state"><option value="">All</option>
      <?php foreach ($states as $s) { echo opt($s['code'], $s['code'] === 'US' ? 'National' : $s['name'], $state === $s['code']); } ?>
    </select></div>
  <div class="col-6 col-md-2"><label class="form-label" for="f-student">Student</label>
    <select class="form-select" id="f-student" name="student"><option value="">All</option>
      <?php foreach ($students as $s) { echo opt((string)$s['id'], $s['last_name'] . ', ' . $s['first_name'], $student === (int)$s['id']); } ?>
    </select></div>
  <div class="col-6 col-md-2"><label class="form-label" for="f-batch">Import batch</label>
    <select class="form-select" id="f-batch" name="batch"><option value="">All</option>
      <?php foreach ($batches as $b) { echo opt((string)$b['id'], '#' . $b['id'] . ' ' . $b['source_file_name'], $batch === (int)$b['id']); } ?>
    </select></div>
  <div class="col-6 col-md-3"><label class="form-label" for="f-theme">National theme</label>
    <select class="form-select" id="f-theme" name="theme"><option value="">All</option>
      <?php foreach ($themes as $t) { echo opt((string)$t['id'], $t['name'], $theme === (int)$t['id']); } ?>
    </select></div>
  <div class="col-6 col-md-1"><label class="form-label" for="f-status">Show</label>
    <select class="form-select" id="f-status" name="status">
      <?= opt('active', 'Active', $status === 'active') . opt('archived', 'Archived', $status === 'archived') . opt('all', 'All', $status === 'all') ?>
    </select></div>
  <div class="col-6 col-md-2 d-flex gap-2">
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/scholarships/index.php')) ?>">Clear</a>
  </div>
</form>

<?php if (count($rows) === $limit): ?>
  <div class="alert alert-warning">Showing the newest <?= $limit ?> matches only. Use the filters to narrow the list.</div>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="empty-state">No scholarships match these filters. <a href="<?= e(url('admin/import/index.php')) ?>">Import a file</a> or <a href="<?= e(url('admin/scholarships/edit.php')) ?>">add one by hand</a>.</div>
<?php else: ?>
<form method="post" action="<?= e(url('admin/scholarships/actions.php')) ?>" id="bulk-form">
  <?= csrf_field() ?>
  <input type="hidden" name="return" value="<?= e($here) ?>">
  <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
    <span class="me-1">With the selected rows:</span>
    <button class="btn btn-sm btn-success" type="submit" name="action" value="verify">Mark verified</button>
    <?php if ($status !== 'archived'): ?>
      <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="archive" data-confirm="Archive the selected scholarships? They disappear from public search.">Archive</button>
    <?php endif; ?>
    <?php if ($status !== 'active'): ?>
      <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="restore">Restore</button>
    <?php endif; ?>
    <span class="text-muted small" id="selected-count" aria-live="polite">0 selected</span>
  </div>
  <div class="table-responsive">
  <table class="table table-sm table-hover align-middle" id="schol-table">
    <caption class="visually-hidden">Scholarships. Use the checkboxes to select rows for a bulk action.</caption>
    <thead><tr>
      <th scope="col" class="no-sort"><input type="checkbox" id="check-all" aria-label="Select every row on the list"></th>
      <th scope="col">ID</th><th scope="col">Scholarship</th><th scope="col">Provider</th><th scope="col">State</th>
      <th scope="col">Student</th><th scope="col">Batch</th><th scope="col">Theme</th><th scope="col">Award</th>
      <th scope="col">Deadline</th><th scope="col">Verified</th><th scope="col">Status</th><th scope="col" class="no-sort">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="Select <?= e($r['name']) ?>"></td>
        <td><?= (int)$r['id'] ?></td>
        <td><a href="<?= e(url('admin/scholarships/view.php?id=' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a></td>
        <td><?= e($r['provider']) ?></td>
        <td><?= e($r['state_code'] === 'US' ? 'National' : $r['state_code']) ?></td>
        <td><?= e($r['student'] ?? '') ?></td>
        <td><?= $r['import_batch_id'] ? '#' . (int)$r['import_batch_id'] : '' ?></td>
        <td><?= e($r['theme'] ?? '') ?></td>
        <td data-order="<?= e((string)($r['award_max'] ?? $r['award_min'] ?? 0)) ?>"><?= e(fmt_money_range($r['award_min'], $r['award_max'])) ?></td>
        <td data-order="<?= e($r['deadline'] ?? ($r['is_rolling'] ? '9998-12-31' : '9999-12-31')) ?>"><?= $r['deadline'] ? fmt_date($r['deadline']) : ($r['is_rolling'] ? 'Rolling' : '<span class="text-muted">None</span>') ?></td>
        <td data-order="<?= e($r['verification_date'] ?? '') ?>"><?= fmt_date($r['verification_date']) ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/scholarships/edit.php?id=' . (int)$r['id'])) ?>">Edit<span class="visually-hidden"> <?= e($r['name']) ?></span></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</form>
<?php endif; ?>
<?php
$extraScripts = <<<'JS'
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
(function () {
  var table = $('#schol-table');
  if (!table.length) { return; }
  var dt = table.DataTable({
    pageLength: 25, lengthMenu: [10, 25, 50, 100],
    order: [[1, 'desc']], columnDefs: [{ targets: 'no-sort', orderable: false }]
  });
  // DataTables removes rows on other pages from the page, so read checkboxes through its API.
  function checked() { return dt.$('input.row-check:checked'); }
  function update() { $('#selected-count').text(checked().length + ' selected'); }
  $('#check-all').on('change', function () {
    dt.$('input.row-check', { page: 'current' }).prop('checked', this.checked); update();
  });
  table.on('change', 'input.row-check', update);
  $('#bulk-form').on('submit', function (ev) {
    var btn = ev.originalEvent && ev.originalEvent.submitter;
    var rows = checked();
    if (!rows.length) { ev.preventDefault(); alert('Select at least one scholarship first.'); return; }
    if (btn && btn.dataset.confirm && !confirm(btn.dataset.confirm + ' (' + rows.length + ' selected)')) { ev.preventDefault(); return; }
    // Rows on other pages are not in the form, so add them as hidden fields.
    var form = this;
    $(form).find('input.extra-id').remove();
    rows.each(function () {
      if (!$.contains(form, this)) { $('<input type="hidden" class="extra-id" name="ids[]">').val(this.value).appendTo(form); }
    });
  });
})();
</script>
JS;
require __DIR__ . '/../../includes/footer.php';
