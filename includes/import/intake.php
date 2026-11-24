<?php
/**
 * Upload intake (FR-IMP-02): check each uploaded file, read it (CSV or XLSX), validate it,
 * and stage it. Nothing reaches the live scholarships table until someone presses Commit.
 */
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/xlsx_reader.php';

/**
 * Returns an error message, or null when the file is acceptable.
 * Checks: PHP upload error, real upload, extension, size, and the file's actual content
 * (an .xlsx must be a zip; a .csv must be text), because a file name alone proves nothing.
 */
function check_upload(string $name, string $tmp, int $size, int $phpError, int $maxBytes, bool $requireUploaded = true): ?string
{
    if ($phpError === UPLOAD_ERR_NO_FILE) {
        return 'No file was chosen.';
    }
    if ($phpError === UPLOAD_ERR_INI_SIZE || $phpError === UPLOAD_ERR_FORM_SIZE) {
        return 'The file is larger than the server allows.';
    }
    if ($phpError !== UPLOAD_ERR_OK) {
        return 'The upload failed (error code ' . $phpError . '). Try again.';
    }
    if ($requireUploaded && !is_uploaded_file($tmp)) {
        return 'The uploaded file could not be found on the server.';
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true)) {
        return 'Only .csv and .xlsx files are accepted.';
    }
    if ($size <= 0) {
        return 'The file is empty.';
    }
    if ($size > $maxBytes) {
        return 'The file is larger than ' . round($maxBytes / 1048576, 1) . ' MB.';
    }
    $head = (string)file_get_contents($tmp, false, null, 0, 4096);
    $isZip = str_starts_with($head, "PK\x03\x04");
    if ($ext === 'xlsx' && !$isZip) {
        return 'This is not a real .xlsx file (its content is not an Excel workbook).';
    }
    if ($ext === 'csv' && ($isZip || str_contains($head, "\0"))) {
        return 'This is not a text CSV file (it contains binary data). If it is an Excel file, save it with the .xlsx extension.';
    }
    return null;
}

/**
 * Read, validate and stage ONE file. $ctx is shared between the files of one upload (passed by
 * reference) so duplicates are caught across files as well as within them.
 * Returns ['file', 'ok', 'message'] or, on success, ['file', 'ok' => true, 'batch_id', 'counts', 'messages', 'student'].
 */
function stage_upload(PDO $pdo, string $name, string $tmp, array &$ctx): array
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    try {
        [$headers, $rows] = $ext === 'xlsx' ? read_xlsx_rows($tmp) : read_csv_rows($tmp);
    } catch (Throwable $e) {
        return ['file' => $name, 'ok' => false, 'message' => 'Could not read the file: ' . $e->getMessage()];
    }
    if (!$headers) {
        return ['file' => $name, 'ok' => false, 'message' => 'The file has no header row. Start from the official template.'];
    }
    $v = validate_file($rows, $headers, $name, $ctx);
    if ($v['counts']['total'] === 0) {
        return ['file' => $name, 'ok' => false, 'message' => 'No data rows were found under the header row.'];
    }
    try {
        $batchId = stage_batch($pdo, $v);
    } catch (Throwable $e) {
        error_log('stage_batch failed: ' . $e->getMessage());
        return ['file' => $name, 'ok' => false, 'message' => 'The database could not save this file. Nothing was imported.'];
    }
    return [
        'file' => $name, 'ok' => true, 'batch_id' => $batchId, 'counts' => $v['counts'],
        'messages' => $v['batch_messages'], 'student' => $v['assignment'],
    ];
}
