<?php
/** Moved in Iteration 6: the student coverage table is now the first tab of the full coverage report. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
redirect('admin/coverage.php');
