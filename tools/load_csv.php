<?php
/**
 * Command-line batch loader. Handy before the upload page exists, and for loading many files at once.
 *
 *   php tools/load_csv.php tests/sample_files/Young_Fuchee.csv            # validate + stage, print report
 *   php tools/load_csv.php tests/sample_files/*.csv --commit                # also commit Ready/Warning rows
 *
 * The file name must be LastName_FirstName.csv so the contributing student can be identified.
 * .csv and .xlsx files both work (the first sheet of a workbook is read).
 */
if (PHP_SAPI !== 'cli') { exit('Command line only.'); }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/import/store.php';
require_once __DIR__ . '/../includes/import/xlsx_reader.php';

$args = array_slice($argv, 1);
$commit = in_array('--commit', $args, true);
$files = array_values(array_filter($args, fn($a) => $a !== '--commit'));
if (!$files) { exit("Usage: php tools/load_csv.php file1.csv|xlsx [file2 ...] [--commit]\n"); }

$pdo = db();
$ctx = load_import_context($pdo);          // shared across files, so duplicates are caught ACROSS files

foreach ($files as $path) {
    $name = basename($path);
    [$headers, $rows] = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx' ? read_xlsx_rows($path) : read_csv_rows($path);
    $v = validate_file($rows, $headers, $name, $ctx);
    $c = $v['counts'];
    echo str_repeat('=', 72) . "\n$name  -> student: " . ($v['assignment'] ? $v['assignment']['first_name'] . ' ' . $v['assignment']['last_name'] : 'UNKNOWN') . "\n";
    foreach ($v['batch_messages'] as $m) { echo "  [{$m['level']}] {$m['text']}\n"; }
    foreach ($v['rows'] as $r) {
        if ($r['status'] === 'ready') { continue; }
        echo sprintf("  row %-3d %-9s", $r['row'], strtoupper($r['status'])) . ' ' . ($r['raw']['name'] ?? '') . "\n";
        foreach ($r['messages'] as $m) { echo "           - {$m['text']}\n"; }
    }
    echo "  Total {$c['total']}: ready {$c['ready']}, warning {$c['warning']}, duplicate {$c['duplicate']}, error {$c['error']}\n";
    if ($c['total'] === 0) { continue; }
    $id = stage_batch($pdo, $v);
    echo "  Staged as batch #$id\n";
    if ($commit) {
        $r = commit_batch($pdo, $id);
        echo "  COMMITTED: {$r['inserted']} inserted, {$r['skipped_duplicates']} skipped as duplicates\n";
    }
}
