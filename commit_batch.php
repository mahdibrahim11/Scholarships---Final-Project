<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/../includes/header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo '<h2>Invalid Request</h2>';
    echo '<p>This page must be submitted from the import results page.</p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$batchId = isset($_POST['batch_id'])
    ? (int)$_POST['batch_id']
    : 0;

if ($batchId <= 0) {

    echo '<h2>Invalid Batch</h2>';
    echo '<p>No valid batch ID was provided.</p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

try {

    $result = commit_batch(
        $pdo,
        $batchId
    );

    echo '<h1>Import Committed</h1>';

    echo '<p style="
        color: green;
        font-weight: bold;
        font-size: 18px;
    ">';

    echo 'Batch ' .
        (int)$batchId .
        ' was committed successfully.';

    echo '</p>';

    echo '<h2>Results</h2>';

    echo '<ul>';

    echo '<li>Scholarships inserted: ' .
        (int)$result['inserted'] .
        '</li>';

    echo '<li>Duplicates skipped: ' .
        (int)$result['skipped_duplicates'] .
        '</li>';

    echo '</ul>';

    echo '<p>
        The committed scholarships should now be in the live scholarships table.
    </p>';

} catch (Throwable $e) {

    echo '<h2>Commit Error</h2>';

    echo '<p>' .
        htmlspecialchars($e->getMessage()) .
        '</p>';
}

require_once __DIR__ . '/../includes/footer.php';

?>
