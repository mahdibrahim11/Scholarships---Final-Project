<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../database/db.php';

// ----------------------------------------------------
// 1. Make sure form was submitted
// ----------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Please upload a file first.");
}

// ----------------------------------------------------
// 2. Make sure files were received
// ----------------------------------------------------

if (!isset($_FILES['scholarship_files'])) {
    die("No files were received.");
}

$files = $_FILES['scholarship_files'];

$fileCount = count($files['name']);

if ($fileCount === 0) {
    die("No files were selected.");
}

// ----------------------------------------------------
// 3. Loop through every uploaded file
// ----------------------------------------------------

for ($i = 0; $i < $fileCount; $i++) {

    $fileName = $files['name'][$i];
    $fileSize = $files['size'][$i];
    $fileTmp = $files['tmp_name'][$i];
    $fileError = $files['error'][$i];

    echo "<hr>";
    echo "<h2>Processing: " . htmlspecialchars($fileName) . "</h2>";

    // ------------------------------------------------
    // 4. Check upload errors
    // ------------------------------------------------

    if ($fileError !== UPLOAD_ERR_OK) {
        echo "<p>Upload failed. Error code: "
            . htmlspecialchars($fileError)
            . "</p>";

        continue;
    }

    // ------------------------------------------------
    // 5. Get file extension
    // ------------------------------------------------

    $fileExtension = strtolower(
        pathinfo($fileName, PATHINFO_EXTENSION)
    );

    // ------------------------------------------------
    // 6. Only allow CSV and XLSX
    // ------------------------------------------------

    $allowedExtensions = ['csv', 'xlsx'];

    if (!in_array($fileExtension, $allowedExtensions)) {

        echo "<p>Skipped: invalid file type.</p>";
        echo "<p>Only CSV and XLSX files are allowed.</p>";

        continue;
    }

    // ------------------------------------------------
    // 7. Maximum file size = 5 MB
    // ------------------------------------------------

    $maxFileSize = 5 * 1024 * 1024;

    if ($fileSize > $maxFileSize) {

        echo "<p>Skipped: file is too large.</p>";
        echo "<p>Maximum size is 5 MB.</p>";

        continue;
    }

    // ------------------------------------------------
    // 8. XLSX support will be added later
    // ------------------------------------------------

    if ($fileExtension === 'xlsx') {

        echo "<p>Skipped: XLSX support has not been added yet.</p>";

        continue;
    }

    // ------------------------------------------------
    // 9. Open CSV file
    // ------------------------------------------------

    $handle = fopen($fileTmp, 'r');

    if ($handle === false) {

        echo "<p>Could not open this CSV file.</p>";

        continue;
    }

    try {

        // --------------------------------------------
        // 10. Start transaction
        // --------------------------------------------

        $pdo->beginTransaction();

        // --------------------------------------------
        // 11. Create batch
        // --------------------------------------------

        $batchStatement = $pdo->prepare("
            INSERT INTO import_batches (
                source_file_name,
                status,
                rows_total
            )
            VALUES (
                :source_file_name,
                'staged',
                0
            )
        ");

        $batchStatement->execute([
            ':source_file_name' => $fileName
        ]);

        $batchId = $pdo->lastInsertId();

        // --------------------------------------------
        // 12. Read CSV header
        // --------------------------------------------

        $headers = fgetcsv($handle);

        if ($headers === false) {
            throw new Exception("CSV file is empty.");
        }

        // Clean header names
        foreach ($headers as $index => $header) {

            if ($index === 0) {
                $header = preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    $header
                );
            }

            $headers[$index] = trim($header);
        }

        // --------------------------------------------
        // 13. Prepare staging row insert
        // --------------------------------------------

        $rowStatement = $pdo->prepare("
            INSERT INTO import_staging_rows (
                batch_id,
                source_row,
                raw_json,
                status
            )
            VALUES (
                :batch_id,
                :source_row,
                :raw_json,
                'ready'
            )
        ");

        // --------------------------------------------
        // 14. Display file information
        // --------------------------------------------

        echo "<p>";
        echo "File type: "
            . strtoupper($fileExtension)
            . "<br>";

        echo "File size: "
            . round($fileSize / 1024, 2)
            . " KB<br>";

        echo "Batch ID: "
            . htmlspecialchars($batchId)
            . "<br>";
        echo "</p>";

        // --------------------------------------------
        // 15. Display CSV table
        // --------------------------------------------

        echo "<table border='1' cellpadding='8' cellspacing='0'>";

        echo "<tr>";

        foreach ($headers as $header) {

            echo "<th>"
                . htmlspecialchars($header)
                . "</th>";
        }

        echo "</tr>";

        // --------------------------------------------
        // 16. Process CSV rows
        // --------------------------------------------

        $sourceRow = 1;
        $totalRows = 0;

        while (($row = fgetcsv($handle)) !== false) {

            // Skip blank rows
            if (
                count($row) === 1 &&
                trim($row[0]) === ''
            ) {
                continue;
            }

            $sourceRow++;
            $totalRows++;

            $rowData = [];

            // Match each value to its header
            foreach ($headers as $index => $header) {

                $value = $row[$index] ?? null;

                if ($value !== null) {
                    $value = trim($value);
                }

                $rowData[$header] = $value;
            }

            // Convert row into JSON
            $rawJson = json_encode(
                $rowData,
                JSON_UNESCAPED_UNICODE
            );

            if ($rawJson === false) {
                throw new Exception(
                    "Could not convert row "
                    . $sourceRow
                    . " to JSON."
                );
            }

            // ----------------------------------------
            // 17. Insert row into staging table
            // ----------------------------------------

            $rowStatement->execute([
                ':batch_id' => $batchId,
                ':source_row' => $sourceRow,
                ':raw_json' => $rawJson
            ]);

            // ----------------------------------------
            // 18. Display row
            // ----------------------------------------

            echo "<tr>";

            foreach ($headers as $index => $header) {

                $cell = $row[$index] ?? '';

                echo "<td>"
                    . htmlspecialchars($cell)
                    . "</td>";
            }

            echo "</tr>";
        }

        echo "</table>";

        fclose($handle);

        // --------------------------------------------
        // 19. Update batch counts
        // --------------------------------------------

        $updateBatch = $pdo->prepare("
            UPDATE import_batches
            SET
                rows_total = :rows_total,
                rows_ready = :rows_ready
            WHERE id = :id
        ");

        $updateBatch->execute([
            ':rows_total' => $totalRows,
            ':rows_ready' => $totalRows,
            ':id' => $batchId
        ]);

        // --------------------------------------------
        // 20. Commit transaction
        // --------------------------------------------

        $pdo->commit();

        // --------------------------------------------
        // 21. Success message
        // --------------------------------------------

        echo "<h3>Import Successful!</h3>";

        echo "<p>";
        echo "File: "
            . htmlspecialchars($fileName)
            . "<br>";

        echo "Rows added to staging: "
            . $totalRows
            . "<br>";

        echo "Batch ID: "
            . htmlspecialchars($batchId)
            . "<br>";

        echo "</p>";

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }

        echo "<p>";
        echo "Import failed for "
            . htmlspecialchars($fileName)
            . ": "
            . htmlspecialchars($e->getMessage());
        echo "</p>";
    }
}

?>
