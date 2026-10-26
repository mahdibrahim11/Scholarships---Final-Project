<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| LOAD PROJECT FILES
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/xlsx_reader.php';
require_once __DIR__ . '/../includes/header.php';
$pdo = db();

/*
|--------------------------------------------------------------------------
| CHECK REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo '<h2>No File Submitted</h2>';
    echo '<p>Please return to the upload page and choose a file.</p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK THAT FILE EXISTS
|--------------------------------------------------------------------------
*/

if (!isset($_FILES['scholarship_file'])) {

    echo '<h2>Upload Error</h2>';
    echo '<p>No file was received.</p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$file = $_FILES['scholarship_file'];


/*
|--------------------------------------------------------------------------
| CHECK PHP UPLOAD ERROR
|--------------------------------------------------------------------------
*/

if ($file['error'] !== UPLOAD_ERR_OK) {

    echo '<h2>Upload Error</h2>';

    echo '<p>Upload failed with error code: ' .
        (int)$file['error'] .
        '</p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}


/*
|--------------------------------------------------------------------------
| FILE INFORMATION
|--------------------------------------------------------------------------
*/

$originalName = basename($file['name']);

$extension = strtolower(
    pathinfo($originalName, PATHINFO_EXTENSION)
);


/*
|--------------------------------------------------------------------------
| ALLOWED FILE TYPES
|--------------------------------------------------------------------------
*/

$allowedExtensions = [
    'csv',
    'xlsx'
];

if (!in_array($extension, $allowedExtensions, true)) {

    echo '<h2>Invalid File Type</h2>';

    echo '<p>Only CSV and XLSX files are allowed.</p>';

    echo '<p>You uploaded: <strong>' .
        htmlspecialchars($originalName) .
        '</strong></p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}


/*
|--------------------------------------------------------------------------
| MAXIMUM FILE SIZE - 5 MB
|--------------------------------------------------------------------------
*/

$maxFileSize = 5 * 1024 * 1024;

if ($file['size'] > $maxFileSize) {

    echo '<h2>File Too Large</h2>';

    echo '<p>The maximum upload size is 5 MB.</p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}


/*
|--------------------------------------------------------------------------
| MAKE SURE TEMPORARY FILE EXISTS
|--------------------------------------------------------------------------
*/

if (
    empty($file['tmp_name']) ||
    !is_uploaded_file($file['tmp_name'])
) {

    echo '<h2>Upload Error</h2>';

    echo '<p>The temporary uploaded file could not be found.</p>';

    require_once __DIR__ . '/../includes/footer.php';
    exit;
}


/*
|--------------------------------------------------------------------------
| CSV PROCESSING
|--------------------------------------------------------------------------
*/

if ($extension === 'csv') {

    /*
    |--------------------------------------------------------------------------
    | READ CSV
    |--------------------------------------------------------------------------
    */

    [$headers, $rows] = read_csv_rows(
        $file['tmp_name']
    );


    /*
    |--------------------------------------------------------------------------
    | MAKE SURE CSV HAS HEADERS
    |--------------------------------------------------------------------------
    */

    if (empty($headers)) {

        echo '<h2>CSV Error</h2>';

        echo '<p>
                The CSV could not be read or
                does not contain a header row.
              </p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD REAL DATABASE CONTEXT
    |--------------------------------------------------------------------------
    |
    | This loads:
    | - existing scholarships
    | - student assignments
    | - categories
    | - fields of study
    |
    */

    try {

        $ctx = load_import_context($pdo);

        $ctx['today'] = date('Y-m-d');

        $ctx['seen'] = [];

    } catch (Throwable $e) {

        echo '<h2>Database Context Error</h2>';

        echo '<p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE THE FILE
    |--------------------------------------------------------------------------
    */

    try {

        $report = validate_file(
            $rows,
            $headers,
            $originalName,
            $ctx
        );

    } catch (Throwable $e) {

        echo '<h2>Validation Error</h2>';

        echo '<p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE RESULTS TO STAGING
    |--------------------------------------------------------------------------
    |
    | Nothing is inserted into the live scholarships table yet.
    |
    */

    try {

        $batchId = stage_batch(
            $pdo,
            $report
        );

    } catch (Throwable $e) {

        echo '<h2>Staging Error</h2>';

        echo '<p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    */

    echo '<h1>Import Validation Results</h1>';

    echo '<p style="
        color: green;
        font-weight: bold;
        font-size: 18px;
    ">';

    echo 'File successfully validated and saved to staging.';

    echo '</p>';


    echo '<p>';

    echo '<strong>File:</strong> ' .
        htmlspecialchars($originalName);

    echo '</p>';


    echo '<p>';

    echo '<strong>Batch ID:</strong> ' .
        (int)$batchId;

    echo '</p>';
    
    echo '
<form action="commit_batch.php" method="POST" style="margin-top:20px; margin-bottom:20px;">
    <input type="hidden" name="batch_id" value="' . (int)$batchId . '">

    <button type="submit">
        Commit Batch
    </button>
</form>
';


    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    echo '<h2>Summary</h2>';

    echo '<ul>';

    echo '<li>Total rows: ' .
        (int)$report['counts']['total'] .
        '</li>';

    echo '<li>Ready: ' .
        (int)$report['counts']['ready'] .
        '</li>';

    echo '<li>Warnings: ' .
        (int)$report['counts']['warning'] .
        '</li>';

    echo '<li>Duplicates: ' .
        (int)$report['counts']['duplicate'] .
        '</li>';

    echo '<li>Errors: ' .
        (int)$report['counts']['error'] .
        '</li>';

    echo '</ul>';


    /*
    |--------------------------------------------------------------------------
    | FILE-LEVEL MESSAGES
    |--------------------------------------------------------------------------
    */

    if (!empty($report['batch_messages'])) {

        echo '<h2>File Messages</h2>';

        echo '<ul>';

        foreach ($report['batch_messages'] as $message) {

            echo '<li>';

            echo '<strong>' .
                htmlspecialchars(
                    strtoupper($message['level'])
                ) .
                ':</strong> ';

            echo htmlspecialchars(
                $message['text']
            );

            echo '</li>';
        }

        echo '</ul>';
    }


    /*
    |--------------------------------------------------------------------------
    | ROW RESULTS
    |--------------------------------------------------------------------------
    */

    echo '<h2>Rows</h2>';


    if (empty($report['rows'])) {

        echo '<p>No scholarship rows were found.</p>';

    } else {

        echo '
        <table
            border="1"
            cellpadding="8"
            cellspacing="0"
            style="
                width:100%;
                border-collapse:collapse;
            "
        >';


        /*
        |--------------------------------------------------------------------------
        | TABLE HEADER
        |--------------------------------------------------------------------------
        */

        echo '<tr>';

        echo '<th>Row</th>';

        echo '<th>Status</th>';

        echo '<th>Scholarship</th>';

        echo '<th>Provider</th>';

        echo '<th>Messages</th>';

        echo '</tr>';


        /*
        |--------------------------------------------------------------------------
        | TABLE ROWS
        |--------------------------------------------------------------------------
        */

        foreach ($report['rows'] as $row) {

            $clean = $row['clean'];

            echo '<tr>';


            /*
             * Spreadsheet row number
             */
            echo '<td>' .
                (int)$row['row'] .
                '</td>';


            /*
             * Status
             */
            echo '<td>';

            echo '<strong>' .
                htmlspecialchars(
                    strtoupper($row['status'])
                ) .
                '</strong>';

            echo '</td>';


            /*
             * Scholarship name
             */
            echo '<td>' .
                htmlspecialchars(
                    $clean['name'] ?? ''
                ) .
                '</td>';


            /*
             * Provider
             */
            echo '<td>' .
                htmlspecialchars(
                    $clean['provider'] ?? ''
                ) .
                '</td>';


            /*
             * Messages
             */
            echo '<td>';


            if (empty($row['messages'])) {

                echo 'No issues';

            } else {

                echo '<ul>';

                foreach ($row['messages'] as $message) {

                    echo '<li>';

                    echo '<strong>' .
                        htmlspecialchars(
                            strtoupper($message['level'])
                        ) .
                        ':</strong> ';

                    echo htmlspecialchars(
                        $message['text']
                    );

                    echo '</li>';
                }

                echo '</ul>';
            }


            echo '</td>';

            echo '</tr>';
        }


        echo '</table>';
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    */

    echo '
        <p style="
            margin-top:25px;
            font-weight:bold;
        ">
            These rows are currently staged.
            They have NOT been committed to the live
            scholarships table yet.
        </p>
    ';
}


/*
|--------------------------------------------------------------------------
| XLSX PROCESSING
|--------------------------------------------------------------------------
*/

elseif ($extension === 'xlsx') {

    /*
    |--------------------------------------------------------------------------
    | READ XLSX
    |--------------------------------------------------------------------------
    */

    try {

        [$headers, $rows] = read_xlsx_rows(
            $file['tmp_name']
        );

    } catch (Throwable $e) {

        echo '<h2>XLSX Error</h2>';

        echo '<p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK HEADERS
    |--------------------------------------------------------------------------
    */

    if (empty($headers)) {

        echo '<h2>XLSX Error</h2>';

        echo '<p>
            The XLSX file could not be read or
            does not contain a header row.
        </p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | LOAD DATABASE CONTEXT
    |--------------------------------------------------------------------------
    */

    try {

        $ctx = load_import_context($pdo);

        $ctx['today'] = date('Y-m-d');

        $ctx['seen'] = [];

    } catch (Throwable $e) {

        echo '<h2>Database Context Error</h2>';

        echo '<p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE XLSX
    |--------------------------------------------------------------------------
    */

    try {

        $report = validate_file(
            $rows,
            $headers,
            $originalName,
            $ctx
        );

    } catch (Throwable $e) {

        echo '<h2>Validation Error</h2>';

        echo '<p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE XLSX RESULTS TO STAGING
    |--------------------------------------------------------------------------
    */

    try {

        $batchId = stage_batch(
            $pdo,
            $report
        );

    } catch (Throwable $e) {

        echo '<h2>Staging Error</h2>';

        echo '<p>' .
            htmlspecialchars($e->getMessage()) .
            '</p>';

        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | RESULTS
    |--------------------------------------------------------------------------
    */

    echo '<h1>Import Validation Results</h1>';

    echo '<p style="
        color: green;
        font-weight: bold;
        font-size: 18px;
    ">
        XLSX successfully validated and saved to staging.
    </p>';

    echo '<p><strong>File:</strong> ' .
        htmlspecialchars($originalName) .
        '</p>';

    echo '<p><strong>Batch ID:</strong> ' .
        (int)$batchId .
        '</p>';


    /*
    |--------------------------------------------------------------------------
    | COMMIT BUTTON
    |--------------------------------------------------------------------------
    */

    echo '
    <form
        action="commit_batch.php"
        method="POST"
        style="margin-top:20px; margin-bottom:20px;"
    >

        <input
            type="hidden"
            name="batch_id"
            value="' . (int)$batchId . '"
        >

        <button type="submit">
            Commit Batch
        </button>

    </form>
    ';


    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    echo '<h2>Summary</h2>';

    echo '<ul>';

    echo '<li>Total rows: ' .
        (int)$report['counts']['total'] .
        '</li>';

    echo '<li>Ready: ' .
        (int)$report['counts']['ready'] .
        '</li>';

    echo '<li>Warnings: ' .
        (int)$report['counts']['warning'] .
        '</li>';

    echo '<li>Duplicates: ' .
        (int)$report['counts']['duplicate'] .
        '</li>';

    echo '<li>Errors: ' .
        (int)$report['counts']['error'] .
        '</li>';

    echo '</ul>';


    /*
    |--------------------------------------------------------------------------
    | FILE MESSAGES
    |--------------------------------------------------------------------------
    */

    if (!empty($report['batch_messages'])) {

        echo '<h2>File Messages</h2>';

        echo '<ul>';

        foreach ($report['batch_messages'] as $message) {

            echo '<li>';

            echo '<strong>' .
                htmlspecialchars(
                    strtoupper($message['level'])
                ) .
                ':</strong> ';

            echo htmlspecialchars(
                $message['text']
            );

            echo '</li>';
        }

        echo '</ul>';
    }


    /*
    |--------------------------------------------------------------------------
    | ROW RESULTS
    |--------------------------------------------------------------------------
    */

    echo '<h2>Rows</h2>';

    if (empty($report['rows'])) {

        echo '<p>No scholarship rows were found.</p>';

    } else {

        echo '
        <table
            border="1"
            cellpadding="8"
            cellspacing="0"
            style="
                width:100%;
                border-collapse:collapse;
            "
        >';

        echo '<tr>';

        echo '<th>Row</th>';
        echo '<th>Status</th>';
        echo '<th>Scholarship</th>';
        echo '<th>Provider</th>';
        echo '<th>Messages</th>';

        echo '</tr>';

        foreach ($report['rows'] as $row) {

            $clean = $row['clean'];

            echo '<tr>';

            echo '<td>' .
                (int)$row['row'] .
                '</td>';

            echo '<td><strong>' .
                htmlspecialchars(
                    strtoupper($row['status'])
                ) .
                '</strong></td>';

            echo '<td>' .
                htmlspecialchars(
                    $clean['name'] ?? ''
                ) .
                '</td>';

            echo '<td>' .
                htmlspecialchars(
                    $clean['provider'] ?? ''
                ) .
                '</td>';

            echo '<td>';

            if (empty($row['messages'])) {

                echo 'No issues';

            } else {

                echo '<ul>';

                foreach ($row['messages'] as $message) {

                    echo '<li>';

                    echo '<strong>' .
                        htmlspecialchars(
                            strtoupper($message['level'])
                        ) .
                        ':</strong> ';

                    echo htmlspecialchars(
                        $message['text']
                    );

                    echo '</li>';
                }

                echo '</ul>';
            }

            echo '</td>';

            echo '</tr>';
        }

        echo '</table>';
    }

    echo '
        <p style="
            margin-top:25px;
            font-weight:bold;
        ">
            These XLSX rows are staged only.
            They have NOT been committed to the live
            scholarships table yet.
        </p>
    ';
}


/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/footer.php';

?>
