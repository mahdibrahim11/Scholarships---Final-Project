<?php
/** POST: archive, restore, or mark verified, for one scholarship or a whole selection (FR-ADM-03). */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/scholarships.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
require_csrf();

$back = safe_admin_return($_POST['return'] ?? '', 'admin/scholarships/index.php');
$action = (string)($_POST['action'] ?? '');
$ids = (array)($_POST['ids'] ?? []);
$labels = ['archive' => 'archived', 'restore' => 'restored', 'verify' => 'marked as verified'];

if (!isset($labels[$action]) || !$ids) {
    flash('warning', 'Nothing was changed. Select at least one scholarship and choose an action.');
    redirect($back);
}
try {
    $r = scholarship_bulk_action(db(), $action, $ids);
    if ($r['done'] > 0) {
        flash('success', $r['done'] . ' scholarship' . ($r['done'] === 1 ? '' : 's') . ' ' . $labels[$action] . '.');
    }
    foreach (array_slice($r['skipped'], 0, 5) as $msg) { flash('warning', $msg); }
    if (count($r['skipped']) > 5) { flash('warning', (count($r['skipped']) - 5) . ' more were skipped.'); }
    if ($r['done'] === 0 && !$r['skipped']) { flash('warning', 'Nothing was changed.'); }
} catch (Throwable $e) {
    error_log('bulk action failed: ' . $e->getMessage());
    flash('danger', 'The change could not be saved. Nothing was changed.');
}
redirect($back);
