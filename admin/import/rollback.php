<?php
/** POST: undo a committed batch, removing every scholarship it created (FR-IMP-10). */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/import/store.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
require_csrf();

$batchId = (int)($_POST['batch_id'] ?? 0);
try {
    $n = rollback_batch(db(), $batchId);
    flash('success', "Batch $batchId was rolled back: $n scholarship(s) removed.");
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect('admin/import/history.php');
