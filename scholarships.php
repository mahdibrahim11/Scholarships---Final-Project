<?php
/**
 * Old address of the Iteration 2 list. The real search now lives in search.php (Iteration 5);
 * this file only forwards old bookmarks and links, keeping any filters in the address.
 * (Note the spelling: scholarship.php, singular, is the detail page.)
 */
$qs = (string)($_SERVER['QUERY_STRING'] ?? '');
header('Location: search.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
