<?php

/**
 * Read the first worksheet of an XLSX file.
 *
 * Returns:
 * [
 *     $headers,
 *     $rows
 * ]
 */
function read_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException(
            'PHP ZipArchive is not enabled. XLSX files cannot be read.'
        );
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not open XLSX file.');
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD SHARED STRINGS
    |--------------------------------------------------------------------------
    */

    $sharedStrings = [];

    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedXml !== false) {

        $xml = simplexml_load_string($sharedXml);

        if ($xml !== false) {

            foreach ($xml->si as $si) {

                // Normal string
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                    continue;
                }

                // Rich-text string
                $text = '';

                if (isset($si->r)) {
                    foreach ($si->r as $run) {
                        $text .= (string)$run->t;
                    }
                }

                $sharedStrings[] = $text;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FIND FIRST WORKSHEET
    |--------------------------------------------------------------------------
    */

    $sheetPath = 'xl/worksheets/sheet1.xml';

    $sheetXml = $zip->getFromName($sheetPath);

    if ($sheetXml === false) {
        $zip->close();

        throw new RuntimeException(
            'Could not find the first worksheet in the XLSX file.'
        );
    }

    $sheet = simplexml_load_string($sheetXml);

    if ($sheet === false) {
        $zip->close();

        throw new RuntimeException(
            'Could not read the XLSX worksheet.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | COLUMN LETTER TO NUMBER
    |--------------------------------------------------------------------------
    */

    $columnIndex = function (string $cellReference): int {

        preg_match('/^[A-Z]+/', strtoupper($cellReference), $match);

        $letters = $match[0] ?? 'A';

        $number = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $number =
                ($number * 26) +
                (ord($letters[$i]) - ord('A') + 1);
        }

        return $number - 1;
    };

    /*
    |--------------------------------------------------------------------------
    | READ WORKSHEET ROWS
    |--------------------------------------------------------------------------
    */

    $allRows = [];

    foreach ($sheet->sheetData->row as $row) {

        $values = [];

        foreach ($row->c as $cell) {

            $reference = (string)$cell['r'];

            $index = $columnIndex($reference);

            $type = (string)$cell['t'];

            $value = '';

            /*
             * Shared string
             */
            if ($type === 's') {

                $sharedIndex = isset($cell->v)
                    ? (int)$cell->v
                    : -1;

                $value = $sharedStrings[$sharedIndex] ?? '';
            }

            /*
             * Inline string
             */
            elseif ($type === 'inlineStr') {

                if (isset($cell->is->t)) {

                    $value = (string)$cell->is->t;

                } elseif (isset($cell->is->r)) {

                    $parts = [];

                    foreach ($cell->is->r as $run) {
                        $parts[] = (string)$run->t;
                    }

                    $value = implode('', $parts);
                }
            }

            /*
             * Boolean
             */
            elseif ($type === 'b') {

                $value =
                    ((string)$cell->v === '1')
                    ? 'TRUE'
                    : 'FALSE';
            }

            /*
             * Number, formula result, or plain value
             */
            else {

                $value = isset($cell->v)
                    ? (string)$cell->v
                    : '';
            }

            $values[$index] = trim($value);
        }

        if (empty($values)) {
            continue;
        }

        $maxIndex = max(array_keys($values));

        $completeRow = [];

        for ($i = 0; $i <= $maxIndex; $i++) {
            $completeRow[] = $values[$i] ?? '';
        }

        $allRows[] = $completeRow;
    }

    $zip->close();

    if (empty($allRows)) {
        return [[], []];
    }

    /*
    |--------------------------------------------------------------------------
    | FIRST ROW = HEADERS
    |--------------------------------------------------------------------------
    */

    $headers = array_map(
        fn($value) => trim((string)$value),
        array_shift($allRows)
    );

    $rows = [];

    foreach ($allRows as $line) {

        /*
         * Ignore completely blank rows
         */
        $hasData = false;

        foreach ($line as $value) {
            if (trim((string)$value) !== '') {
                $hasData = true;
                break;
            }
        }

        if (!$hasData) {
            continue;
        }

        /*
         * Make row same length as headers
         */
        $line = array_pad(
            $line,
            count($headers),
            ''
        );

        $line = array_slice(
            $line,
            0,
            count($headers)
        );

        $rows[] = array_combine(
            $headers,
            $line
        );
    }

    return [$headers, $rows];
}
