<?php
/** POST: throw a staged batch away without importing it. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/import/store.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
require_csrf();

$batchId = (int)($_POST['batch_id'] ?? 0);
try {
    discard_batch(db(), $batchId);
    flash('success', "Batch $batchId was discarded. Nothing was imported.");
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}
redirect('admin/import/history.php');
