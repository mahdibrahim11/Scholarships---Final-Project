<?php
/** POST: commit a staged batch in one transaction (FR-IMP-08). */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/import/store.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
require_csrf();

$batchId = (int)($_POST['batch_id'] ?? 0);
try {
    $r = commit_batch(db(), $batchId);
    flash('success', "Batch $batchId committed: {$r['inserted']} scholarship(s) added" .
        ($r['skipped_duplicates'] ? ", {$r['skipped_duplicates']} skipped as duplicates." : '.'));
} catch (Throwable $e) {
    flash('danger', 'The batch was not committed. ' . $e->getMessage());
}
redirect('admin/import/report.php?batch=' . $batchId);
