<?php
/** AJAX endpoint: returns only the results block for the current filters. No layout, no scripts. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/search.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
try {
    $pdo = db();
    $p = search_params($_GET);
    $res = search_run($pdo, $p);
    // Only changes to state / grade / field count as a new search; keyword, sort and paging do not (they send nolog=1).
    if ($p['page'] === 1 && ($_GET['nolog'] ?? '') !== '1') { search_log($pdo, $p); }
    echo search_render_results($res, $p);
} catch (Throwable $e) {
    error_log('search_results failed: ' . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger" role="alert">The search could not be completed. Please try again.</div>';
}
