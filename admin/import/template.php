<?php
/** FR-IMP-01: download the official import template. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/import/pipeline.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="scholarships_import_template.csv"');
echo template_csv();
