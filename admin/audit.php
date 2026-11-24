<?php
/** Audit log viewer (FR-SA-05): Super-admin only. Filters: user, action, record type, record id, date range, text in the details. 50 per page. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([ROLE_SUPER_ADMIN]);

$pdo = db();
$action = trim((string)($_GET['action'] ?? ''));
$entity = preg_replace('/[^a-z_]/', '', strtolower((string)($_GET['entity'] ?? '')));
$entityId = (int)($_GET['id'] ?? 0);
$userF = (int)($_GET['user'] ?? 0);
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? $_GET['from'] : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? $_GET['to'] : '';
$text = trim((string)($_GET['text'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 50;

$where = []; $params = [];
if ($action !== '')   { $where[] = 'l.action = ?';      $params[] = $action; }
if ($entity !== '')   { $where[] = 'l.entity_type = ?'; $params[] = $entity; }
if ($entityId > 0)    { $where[] = 'l.entity_id = ?';   $params[] = $entityId; }
if ($userF > 0)       { $where[] = 'l.user_id = ?';     $params[] = $userF; }
if ($from !== '')     { $where[] = 'l.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to !== '')       { $where[] = 'l.created_at < ? + INTERVAL 1 DAY'; $params[] = $to; }
if ($text !== '')     { $where[] = 'l.details LIKE ?';  $params[] = '%' . addcslashes($text, '%_\\') . '%'; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM audit_log l $w");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$st = $pdo->prepare("SELECT l.*, u.email FROM audit_log l LEFT JOIN users u ON u.id = l.user_id $w ORDER BY l.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$entities = $pdo->query('SELECT DISTINCT entity_type FROM audit_log WHERE entity_type IS NOT NULL ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN);
$userList = $pdo->query('SELECT id, email FROM users ORDER BY email')->fetchAll();

$link = function (?string $type, $id): string {
    if ($id === null) { return e((string)$type); }
    $label = e(($type ?? '') . ' #' . $id);
    return match ($type) {
        'scholarship' => '<a href="' . e(url('admin/scholarships/view.php?id=' . (int)$id)) . '">' . $label . '</a>',
        'import_batch' => '<a href="' . e(url('admin/import/report.php?batch=' . (int)$id)) . '">' . $label . '</a>',
        default => $label,
    };
};
$qs = fn(array $over = []) => http_build_query(array_filter(array_merge(['action' => $action, 'entity' => $entity, 'id' => $entityId ?: '', 'user' => $userF ?: '', 'from' => $from, 'to' => $to, 'text' => $text], $over), fn($v) => $v !== '' && $v !== null));
$pageTitle = 'Audit log';
require __DIR__ . '/../includes/header.php';
?>
<h1>Audit log</h1>
<p class="text-muted">Who did what, newest first. Passwords are never recorded. <?= number_format($total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?> match.</p>
<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-md-3"><label class="form-label" for="a-user">User</label>
    <select class="form-select" id="a-user" name="user"><option value="">Anyone</option>
      <?php foreach ($userList as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === $userF ? ' selected' : '' ?>><?= e($u['email']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label" for="a-action">Action</label>
    <select class="form-select" id="a-action" name="action"><option value="">All</option>
      <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>"<?= $a === $action ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label" for="a-entity">Record type</label>
    <select class="form-select" id="a-entity" name="entity"><option value="">All</option>
      <?php foreach ($entities as $a): ?><option value="<?= e($a) ?>"<?= $a === $entity ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label" for="a-text">Text in details</label><input class="form-control" id="a-text" name="text" value="<?= e($text) ?>"></div>
  <div class="col-md-3"><label class="form-label" for="a-from">From date</label><input class="form-control" type="date" id="a-from" name="from" value="<?= e($from) ?>"></div>
  <div class="col-md-3"><label class="form-label" for="a-to">To date</label><input class="form-control" type="date" id="a-to" name="to" value="<?= e($to) ?>"></div>
  <?php if ($entityId): ?><input type="hidden" name="id" value="<?= $entityId ?>"><?php endif; ?>
  <div class="col-auto"><button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin/audit.php')) ?>">Clear</a></div>
</form>
<?php if (!$rows): ?><div class="empty-state">No audit entries match.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle">
  <caption class="visually-hidden">Audit log entries, newest first</caption>
  <thead><tr><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Details</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="text-nowrap"><?= e(date('M j, Y g:i a', strtotime($r['created_at']))) ?></td>
      <td><?= e($r['email'] ?? ($r['user_id'] ? 'user #' . $r['user_id'] : 'not signed in')) ?></td>
      <td><code><?= e($r['action']) ?></code></td>
      <td><?= $link($r['entity_type'], $r['entity_id']) ?></td>
      <td class="small"><code class="text-break"><?= e((string)$r['details']) ?></code></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php if ($pages > 1): ?>
<nav aria-label="Audit log pages"><ul class="pagination">
  <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="?<?= e($qs(['page' => $page - 1])) ?>">Previous</a></li>
  <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $pages ?></span></li>
  <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="?<?= e($qs(['page' => $page + 1])) ?>">Next</a></li>
</ul></nav>
<?php endif; endif;
require __DIR__ . '/../includes/footer.php';
