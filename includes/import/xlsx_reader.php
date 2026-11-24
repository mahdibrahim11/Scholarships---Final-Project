<?php
/**
 * Minimal .xlsx reader (no Composer needed): first worksheet -> [headers, rows].
 * Written by the team for Iteration 3; this copy finds the first sheet through the workbook
 * instead of assuming xl/worksheets/sheet1.xml, and ignores phonetic (furigana) text.
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
    | SHARED STRINGS
    |--------------------------------------------------------------------------
    */

    $sharedStrings = [];

    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedXml !== false) {

        $xml = simplexml_load_string($sharedXml);

        if ($xml !== false) {

            $xml->registerXPathNamespace(
                'main',
                'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
            );

            $items = $xml->xpath('//main:si');

            if ($items !== false) {

                foreach ($items as $si) {

                    $text = '';

                    $si->registerXPathNamespace(
                        'main',
                        'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
                    );

                    $texts = $si->xpath('.//main:t[not(ancestor::main:rPh)]');

                    if ($texts !== false) {

                        foreach ($texts as $t) {
                            $text .= (string)$t;
                        }
                    }

                    $sharedStrings[] = $text;
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FIRST WORKSHEET
    |--------------------------------------------------------------------------
    */

    $sheetXml = $zip->getFromName(xlsx_first_sheet_path($zip));

    if ($sheetXml === false) {

        $zip->close();

        throw new RuntimeException(
            'Could not find the first worksheet.'
        );
    }

    $sheet = simplexml_load_string($sheetXml);

    if ($sheet === false) {

        $zip->close();

        throw new RuntimeException(
            'Could not parse the XLSX worksheet.'
        );
    }

    $sheet->registerXPathNamespace(
        'main',
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
    );

    $xmlRows = $sheet->xpath(
        '//main:sheetData/main:row'
    );

    if ($xmlRows === false || empty($xmlRows)) {

        $zip->close();

        return [[], []];
    }

    /*
    |--------------------------------------------------------------------------
    | COLUMN REFERENCE -> INDEX
    |--------------------------------------------------------------------------
    */

    $columnIndex = function (string $reference): int {

        preg_match(
            '/^[A-Z]+/',
            strtoupper($reference),
            $match
        );

        $letters = $match[0] ?? 'A';

        $number = 0;

        for ($i = 0; $i < strlen($letters); $i++) {

            $number =
                ($number * 26) +
                (ord($letters[$i]) - 64);
        }

        return $number - 1;
    };

    /*
    |--------------------------------------------------------------------------
    | READ ROWS
    |--------------------------------------------------------------------------
    */

    $allRows = [];

    foreach ($xmlRows as $row) {

        $row->registerXPathNamespace(
            'main',
            'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
        );

        $cells = $row->xpath('./main:c');

        if ($cells === false) {
            continue;
        }

        $values = [];

        foreach ($cells as $cell) {

            $cell->registerXPathNamespace(
                'main',
                'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
            );

            $reference = (string)$cell['r'];

            $index = $columnIndex(
                $reference
            );

            $type = (string)$cell['t'];

            $value = '';

            /*
             * Shared String
             */
            if ($type === 's') {

                $v = $cell->xpath('./main:v');

                if ($v !== false && isset($v[0])) {

                    $sharedIndex = (int)$v[0];

                    $value =
                        $sharedStrings[$sharedIndex]
                        ?? '';
                }
            }

            /*
             * Inline String
             */
            elseif ($type === 'inlineStr') {

                $texts = $cell->xpath(
                    './/main:t'
                );

                if ($texts !== false) {

                    foreach ($texts as $t) {
                        $value .= (string)$t;
                    }
                }
            }

            /*
             * Boolean
             */
            elseif ($type === 'b') {

                $v = $cell->xpath('./main:v');

                if ($v !== false && isset($v[0])) {

                    $value =
                        ((string)$v[0] === '1')
                        ? 'TRUE'
                        : 'FALSE';
                }
            }

            /*
             * Normal Value
             */
            else {

                $v = $cell->xpath('./main:v');

                if ($v !== false && isset($v[0])) {
                    $value = (string)$v[0];
                }
            }

            $values[$index] = trim(
                (string)$value
            );
        }

        if (empty($values)) {
            continue;
        }

        $maxIndex = max(
            array_keys($values)
        );

        $completeRow = [];

        for ($i = 0; $i <= $maxIndex; $i++) {

            $completeRow[] =
                $values[$i] ?? '';
        }

        $allRows[] = $completeRow;
    }

    $zip->close();

    /*
    |--------------------------------------------------------------------------
    | NO ROWS
    |--------------------------------------------------------------------------
    */

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

        $combined = array_combine(
            $headers,
            $line
        );

        if ($combined !== false) {
            $rows[] = $combined;
        }
    }

    return [$headers, $rows];
}

/** Path of the first worksheet inside the .xlsx zip (workbook order), with a safe fallback. */
function xlsx_first_sheet_path(ZipArchive $zip): string
{
    $rels = [];
    $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($relXml !== false && ($r = simplexml_load_string($relXml)) !== false) {
        foreach ($r->Relationship as $rel) {
            $rels[(string)$rel['Id']] = (string)$rel['Target'];
        }
    }
    $wbXml = $zip->getFromName('xl/workbook.xml');
    if ($wbXml !== false && ($wb = simplexml_load_string($wbXml)) !== false) {
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $first = $wb->xpath('//m:sheets/m:sheet[1]');
        if ($first) {
            $attrs = $first[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $target = $rels[(string)($attrs['id'] ?? '')] ?? '';
            if ($target !== '') {
                $target = ltrim($target, '/');
                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
    }
    return 'xl/worksheets/sheet1.xml';
}
