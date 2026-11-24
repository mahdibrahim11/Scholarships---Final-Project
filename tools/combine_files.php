<?php
/**
 * Merge every student's file (LastName_FirstName.csv / .xlsx) in a folder into ONE combined CSV that the Import page accepts.
 *
 *   php tools/combine_files.php path/to/folder [--out=combined.csv] [--target=20]
 *
 * Each row is checked with the same rules as the upload page. Rows that are Ready or Warning go into the combined file with a
 * "Contributing student" column, so one upload credits every row to the right student. Rows with an Error, and duplicates of a
 * URL already used, are NOT combined; they are listed in <out>_skipped.csv with the reason, ready to send back to the student.
 * The screen report shows each student's usable rows against the target (20 per collection round).
 */
if (PHP_SAPI !== 'cli') { exit('Command line only.'); }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/import/store.php';
require_once __DIR__ . '/../includes/import/xlsx_reader.php';

$folder = null; $outPath = 'combined.csv'; $target = STUDENT_TARGET;
foreach (array_slice($argv, 1) as $a) {
    if (str_starts_with($a, '--out=')) { $outPath = substr($a, 6); }
    elseif (str_starts_with($a, '--target=')) { $target = max(1, (int)substr($a, 9)); }
    elseif ($folder === null) { $folder = $a; }
}
if ($folder === null || !is_dir($folder)) { exit("Usage: php tools/combine_files.php <folder> [--out=combined.csv] [--target=20]\n"); }

$pdo = db();
$ctx = load_import_context($pdo);
$ctx['existing'] = [];                       // combine for a fresh load: only duplicates ACROSS the files matter here
$labels = template_columns();
$files = array_merge(glob(rtrim($folder, '/\\') . '/*.csv') ?: [], glob(rtrim($folder, '/\\') . '/*.xlsx') ?: []);
sort($files);

$out = fopen($outPath, 'wb');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, array_merge(array_values($labels), ['Contributing student']), ',', '"', '');
$skippedPath = preg_replace('/\.csv$/i', '', $outPath) . '_skipped.csv';
$skip = fopen($skippedPath, 'wb');
fwrite($skip, "\xEF\xBB\xBF");
fputcsv($skip, ['File', 'Row', 'Student', 'Status', 'Scholarship name', 'Official URL', 'Why it was left out'], ',', '"', '');

$report = []; $combined = 0; $left = 0;
foreach ($files as $path) {
    $name = basename($path);
    if (str_starts_with($name, '~') || stripos($name, 'combined') === 0) { continue; }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    try { [$headers, $rows] = $ext === 'xlsx' ? read_xlsx_rows($path) : read_csv_rows($path); }
    catch (Throwable $e) { fwrite(STDERR, "SKIPPED FILE $name: " . $e->getMessage() . "\n"); continue; }
    $v = validate_file($rows, $headers, $name, $ctx);
    $a = $v['assignment'];
    $who = $a ? $a['last_name'] . '_' . $a['first_name'] : null;
    if ($who === null) { fwrite(STDERR, "SKIPPED FILE $name: file name is not LastName_FirstName of a student in the assignment list.\n"); continue; }
    $inv = array_flip($v['header']['map']);                 // template key => the file's own header text
    $usable = 0; $c = ['ready' => 0, 'warning' => 0, 'duplicate' => 0, 'error' => 0];
    foreach ($v['rows'] as $r) {
        $c[$r['status']]++;
        if (in_array($r['status'], ['ready', 'warning'], true)) {
            $line = [];
            foreach ($labels as $key => $label) { $line[] = $r['raw'][$key] ?? ''; }
            $line[] = $who;
            fputcsv($out, $line, ',', '"', '');
            $usable++; $combined++;
        } else {
            $why = implode(' ', array_map(fn($m) => $m['text'], array_filter($r['messages'], fn($m) => in_array($m['level'], ['error', 'duplicate'], true))));
            fputcsv($skip, [$name, $r['row'], $who, $r['status'], $r['raw']['name'] ?? '', $r['raw']['url'] ?? '', $why], ',', '"', '');
            $left++;
        }
    }
    $report[] = [$who, count($v['rows']), $c, $usable];
}
fclose($out); fclose($skip);

echo str_pad('Student', 28) . str_pad('Rows', 6) . str_pad('Ready', 7) . str_pad('Warn', 6) . str_pad('Dup', 5) . str_pad('Error', 7) . 'Usable / target' . "\n" . str_repeat('-', 78) . "\n";
$atTarget = 0;
foreach ($report as [$who, $n, $c, $usable]) {
    echo str_pad($who, 28) . str_pad((string)$n, 6) . str_pad((string)$c['ready'], 7) . str_pad((string)$c['warning'], 6) . str_pad((string)$c['duplicate'], 5) . str_pad((string)$c['error'], 7)
        . "$usable / $target" . ($usable >= $target ? '  complete' : '  short by ' . ($target - $usable)) . "\n";
    if ($usable >= $target) { $atTarget++; }
}
echo str_repeat('-', 78) . "\n" . count($report) . " student file(s); $atTarget at target. $combined row(s) combined into $outPath; $left row(s) left out (see $skippedPath).\n";
$assigned = $pdo->query('SELECT last_name, first_name FROM student_assignments ORDER BY last_name')->fetchAll();
$got = array_flip(array_map(fn($r) => $r[0], $report));
$missing = array_filter($assigned, fn($s) => !isset($got[$s['last_name'] . '_' . $s['first_name']]));
echo count($missing) . " assigned student(s) have no file in this folder" . ($missing && count($missing) <= 12 ? ': ' . implode(', ', array_map(fn($s) => $s['last_name'] . '_' . $s['first_name'], $missing)) : '') . ".\n";
