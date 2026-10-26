<?php
/**
 * Import pipeline: header mapping -> row cleaning -> validation -> duplicate detection.
 * Pure PHP (no DB). The caller (admin/import/upload.php, Iteration 3) loads the lookup data
 * into $ctx and, after the admin reviews the report, writes the rows to staging and commits.
 */
require_once __DIR__ . '/cleaners.php';

/** Template columns, in the exact order of the Data Science "Recommended Data Fields". */
function template_columns(): array
{
    return [
        'name'                 => 'Scholarship name',
        'provider'             => 'Scholarship provider/organization',
        'provider_type'        => 'Provider type',
        'url'                  => 'Official scholarship URL',
        'discovery_source'     => 'Discovery source',
        'state'                => 'State/region',
        'scope'                => 'National vs. state/local',
        'grades'               => 'Eligible grade levels',
        'school_level'         => 'Middle school / high school / both',
        'category'             => 'Scholarship category',
        'field'                => 'Field/area of interest',
        'award'                => 'Award amount',
        'num_awards'           => 'Number of awards',
        'renewable'            => 'Renewable (Yes/No/Unknown)',
        'max_value'            => 'Maximum potential value',
        'open_date'            => 'Application open date',
        'deadline'             => 'Application deadline',
        'residency'            => 'Residency requirement',
        'min_gpa'              => 'Minimum GPA',
        'need'                 => 'Financial need required',
        'essay'                => 'Essay required',
        'recommendation'       => 'Recommendation required',
        'transcript'           => 'Transcript/test score required',
        'community_service'    => 'Community service requirement',
        'leadership'           => 'Leadership requirement',
        'other_requirements'   => 'Other major eligibility requirements',
        'application_method'   => 'Application method',
        'verification_date'    => 'Verification date',
        'notes'                => 'Notes',
    ];
}

/** Columns a row cannot be imported without. */
function required_column_keys(): array
{
    return ['name', 'provider', 'url', 'state'];
}

/** Header text -> lowercase letters/digits only, so "Scholarship Name " and "scholarship_name" match. */
function _header_key(string $h): string
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower(clean_text($h)));
}

/** Extra spellings students are likely to use. Keys are _header_key() form. */
function header_aliases(): array
{
    // Every exact template label always works, plus the friendly extras below.
    $auto = [];
    foreach (template_columns() as $key => $label) {
        $auto[_header_key($label)] = $key;
    }
    return $auto + [
        'scholarshipname' => 'name', 'name' => 'name', 'scholarship' => 'name',
        'provider' => 'provider', 'organization' => 'provider', 'sponsor' => 'provider', 'scholarshipprovider' => 'provider',
        'providertype' => 'provider_type', 'sponsortype' => 'provider_type',
        'url' => 'url', 'officialurl' => 'url', 'link' => 'url', 'officialscholarshipurl' => 'url', 'website' => 'url',
        'discoverysource' => 'discovery_source', 'source' => 'discovery_source',
        'state' => 'state', 'stateregion' => 'state', 'region' => 'state',
        'nationalvsstatelocal' => 'scope', 'nationalorstatelocal' => 'scope', 'scope' => 'scope',
        'gradelevels' => 'grades', 'eligiblegradelevels' => 'grades', 'grades' => 'grades', 'grade' => 'grades',
        'middleschoolhighschoolboth' => 'school_level',
        'category' => 'category', 'scholarshipcategory' => 'category',
        'field' => 'field', 'fieldofinterest' => 'field', 'fieldareaofinterest' => 'field', 'fieldofstudy' => 'field',
        'awardamount' => 'award', 'award' => 'award', 'amount' => 'award',
        'numberofawards' => 'num_awards', 'numberofawardsifknown' => 'num_awards',
        'renewable' => 'renewable', 'renewableyesnounknown' => 'renewable',
        'maximumpotentialvalue' => 'max_value', 'maxvalue' => 'max_value',
        'applicationopendate' => 'open_date', 'opendate' => 'open_date', 'applicationopendateifknown' => 'open_date',
        'applicationdeadline' => 'deadline', 'deadline' => 'deadline', 'duedate' => 'deadline',
        'residencyrequirement' => 'residency', 'residency' => 'residency',
        'minimumgpa' => 'min_gpa', 'mingpa' => 'min_gpa', 'gpa' => 'min_gpa', 'minimumgpaifany' => 'min_gpa',
        'financialneedrequired' => 'need', 'financialneedrequiredyesnounknown' => 'need', 'need' => 'need',
        'essayrequired' => 'essay', 'essay' => 'essay',
        'recommendationrequired' => 'recommendation', 'recommendation' => 'recommendation',
        'transcripttestscorerequired' => 'transcript', 'transcriptrequired' => 'transcript',
        'communityservicerequirement' => 'community_service', 'communityservice' => 'community_service',
        'leadershiprequirement' => 'leadership', 'leadership' => 'leadership',
        'othermajoreligibilityrequirements' => 'other_requirements', 'othereligibility' => 'other_requirements',
        'applicationmethod' => 'application_method', 'howtoapply' => 'application_method',
        'verificationdate' => 'verification_date', 'verified' => 'verification_date', 'dateverified' => 'verification_date',
        'notes' => 'notes', 'comments' => 'notes',
    ];
}

/**
 * Map the spreadsheet's header cells to template keys.
 * Returns ['map' => [headerText => key], 'missing' => [required keys not found], 'unknown' => [headers we ignored]]
 */
function map_headers(array $headers): array
{
    $aliases = header_aliases();
    $map = [];
    $unknown = [];
    foreach ($headers as $h) {
        $hk = _header_key((string)$h);
        if ($hk === '') {
            continue;
        }
        if (isset($aliases[$hk]) && !in_array($aliases[$hk], $map, true)) {
            $map[$h] = $aliases[$hk];
        } else {
            $unknown[] = (string)$h;
        }
    }
    $missing = array_values(array_diff(required_column_keys(), array_values($map)));
    return ['map' => $map, 'missing' => $missing, 'unknown' => $unknown];
}

/** Hosts that are discovery sites, not official providers. A match is a Warning, never an Error. */
function aggregator_hosts(): array
{
    return ['fastweb.com', 'bold.org', 'scholarships.com', 'niche.com', 'cappex.com', 'goingmerry.com',
            'unigo.com', 'scholarshipowl.com', 'collegescholarships.org', 'studentscholarships.org',
            'bigfuture.collegeboard.org', 'scholarshipportal.com', 'petersons.com'];
}

function _split_multi(?string $s): array
{
    $parts = preg_split('/\s*(?:,|;|\||\/|\n)\s*/', clean_text($s), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_map('clean_text', $parts)));
}

/**
 * Clean and validate ONE row (already keyed by template keys, e.g. ['name' => ..., 'url' => ...]).
 *
 * $ctx keys (all optional):
 *   today        'YYYY-MM-DD'   (default: today)
 *   assignment   array|null     the contributing student's row from student_assignments
 *   categories   string[]       known category names (lowercase compare)
 *   fields       string[]       known field names
 *
 * Returns ['status' => ready|warning|error, 'messages' => [[level, field, text], ...], 'clean' => array, 'url_key' => ?string]
 * (Duplicate detection happens in validate_file because it needs to see other rows.)
 */
function validate_row(array $r, array $ctx = []): array
{
    $msgs = [];
    $add = function (string $level, string $field, string $text) use (&$msgs) {
        $msgs[] = ['level' => $level, 'field' => $field, 'text' => $text];
    };
    $today = $ctx['today'] ?? date('Y-m-d');
    $assign = $ctx['assignment'] ?? null;
    $c = [];

    // ---- Required text ----
    $c['name'] = clean_text($r['name'] ?? '');
    $c['provider'] = clean_text($r['provider'] ?? '');
    if ($c['name'] === '') { $add('error', 'name', 'Scholarship name is missing.'); }
    if ($c['provider'] === '') { $add('error', 'provider', 'Provider/organization is missing.'); }
    $c['provider_type'] = clean_text($r['provider_type'] ?? '');
    $c['discovery_source'] = clean_text($r['discovery_source'] ?? '');

    // ---- URL ----
    $rawUrl = clean_text($r['url'] ?? '');
    $c['url'] = $rawUrl;
    $urlKey = null;
    if ($rawUrl === '') {
        $add('error', 'url', 'Official URL is missing. It is the key used to detect duplicates.');
    } else {
        if (!preg_match('#^https?://#i', $rawUrl) && normalize_url($rawUrl) !== null) {
            $c['url'] = 'https://' . $rawUrl;
            $add('warning', 'url', 'URL had no http:// or https://, so https:// was added.');
        }
        if (!is_valid_url($c['url'])) {
            $add('error', 'url', 'Official URL is not a valid web address: "' . $rawUrl . '".');
        } else {
            $urlKey = normalize_url($c['url']);
            $host = explode('/', explode(':', $urlKey)[0])[0];
            foreach (aggregator_hosts() as $agg) {
                if ($host === $agg || str_ends_with($host, '.' . $agg)) {
                    $add('warning', 'url', "This looks like an aggregator site ($agg). Use the provider's official page.");
                    break;
                }
            }
        }
    }

    // ---- State and scope (FR-IMP-05) ----
    $stateInfo = parse_state_list($r['state'] ?? '');
    $codes = $stateInfo['codes'];
    if (count($codes) === 0) {
        $add('error', 'state', 'State/region is missing or not recognized: "' . clean_text($r['state'] ?? '') . '".');
    } elseif ($stateInfo['unknown']) {
        $add('warning', 'state', 'Ignored unrecognized state value(s): ' . implode(', ', $stateInfo['unknown']) . '.');
    }
    $computedScope = classify_scope($codes);
    $statedScope = parse_scope_label($r['scope'] ?? '');
    $c['scope'] = $computedScope;
    if ($computedScope !== null && $statedScope !== null && $statedScope !== $computedScope) {
        $add('warning', 'scope', "Spreadsheet says '" . $statedScope . "' but the states listed make it '" . $computedScope . "'. Using '" . $computedScope . "'.");
    }
    if ($computedScope === 'national') {
        $c['state_code'] = NATIONAL_CODE;
        $multi = array_values(array_diff($codes, [NATIONAL_CODE]));
        $c['open_to_states'] = $multi;
    } elseif ($computedScope === 'state_local') {
        $c['state_code'] = $codes[0];
        $c['open_to_states'] = [];
    } else {
        $c['state_code'] = null;
        $c['open_to_states'] = [];
    }

    // ---- Grades ----
    $g = parse_grades($r['grades'] ?? '');
    $c['grades'] = $g['grades'];
    $c['school_level'] = derive_school_level($g['grades']);
    if (clean_text($r['grades'] ?? '') === '') {
        $add('warning', 'grades', 'Eligible grade levels are missing, so this scholarship will not appear in grade searches.');
    } elseif (count($g['grades']) === 0) {
        $add('error', 'grades', 'No grade between 6 and 12 found in "' . clean_text($r['grades']) . '". This project covers middle and high school only.');
    } elseif ($g['dropped']) {
        $add('warning', 'grades', 'Ignored grades outside 6-12: ' . implode(', ', $g['dropped']) . '.');
    }

    // ---- Categories and fields ----
    foreach (['category' => 'categories', 'field' => 'fields'] as $key => $ctxKey) {
        $vals = _split_multi($r[$key] ?? '');
        $c[$key . '_list'] = $vals;
        if (isset($ctx[$ctxKey]) && $vals) {
            $known = array_map('strtolower', $ctx[$ctxKey]);
            $bad = array_values(array_filter($vals, fn($v) => !in_array(strtolower($v), $known, true)));
            if ($bad) {
                $add('warning', $key, 'Not in the reference list (will be skipped unless an admin adds it): ' . implode(', ', $bad) . '.');
            }
        }
    }

    // ---- Money ----
    $a = parse_money($r['award'] ?? null);
    if (!$a['ok']) { $add('warning', 'award', 'Could not read award amount "' . clean_text((string)$r['award']) . '".'); }
    $c['award_min'] = $a['min'];
    $c['award_max'] = $a['max'];
    if ($a['min'] === null && $a['max'] === null && $a['ok']) {
        $add('warning', 'award', 'Award amount is missing or varies.');
    }
    $mv = parse_money($r['max_value'] ?? null);
    if (!$mv['ok']) { $add('warning', 'max_value', 'Could not read maximum potential value.'); }
    $c['max_value'] = $mv['max'];
    if ($c['max_value'] !== null && $c['award_max'] !== null && $c['max_value'] < $c['award_max']) {
        $add('warning', 'max_value', 'Maximum potential value is lower than the award amount.');
    }
    $n = parse_int($r['num_awards'] ?? null);
    $c['num_awards'] = $n;
    if (clean_text((string)($r['num_awards'] ?? '')) !== '' && $n === null) {
        $add('warning', 'num_awards', 'Number of awards is not a whole number.');
    }
    $gpa = parse_gpa($r['min_gpa'] ?? null);
    $c['min_gpa'] = $gpa;
    if (clean_text((string)($r['min_gpa'] ?? '')) !== '' && $gpa === null) {
        $add('warning', 'min_gpa', 'Minimum GPA could not be read (expected 0-4.5).');
    }

    // ---- Dates ----
    $dl = parse_date($r['deadline'] ?? null);
    $c['deadline'] = $dl['date'];
    $c['is_rolling'] = $dl['rolling'];
    if (!$dl['ok']) {
        $add('warning', 'deadline', 'Could not read deadline "' . clean_text((string)$r['deadline']) . '".');
    } elseif ($dl['date'] === null && !$dl['rolling']) {
        $add('warning', 'deadline', 'Deadline is missing (enter a date or "Rolling").');
    } elseif ($dl['date'] !== null && $dl['date'] < $today) {
        $add('warning', 'deadline', 'Deadline ' . $dl['date'] . ' has already passed. It will be hidden from search until updated.');
    }
    $op = parse_date($r['open_date'] ?? null);
    $c['open_date'] = $op['date'];
    if (!$op['ok']) { $add('warning', 'open_date', 'Could not read open date.'); }
    $vd = parse_date($r['verification_date'] ?? null);
    $c['verification_date'] = $vd['date'];
    if (!$vd['ok']) {
        $add('warning', 'verification_date', 'Could not read verification date.');
    } elseif ($vd['date'] === null) {
        $add('warning', 'verification_date', 'Verification date is missing.');
    } elseif ($vd['date'] > $today) {
        $add('warning', 'verification_date', 'Verification date is in the future.');
    }

    // ---- Yes / No / Unknown ----
    $yn = ['renewable' => 'renewable', 'need' => 'need_required', 'essay' => 'essay_required',
           'recommendation' => 'recommendation_required', 'transcript' => 'transcript_required',
           'community_service' => 'community_service_required', 'leadership' => 'leadership_required'];
    foreach ($yn as $key => $col) {
        $v = parse_yes_no($r[$key] ?? '');
        if ($v === null) {
            $add('warning', $key, 'Expected Yes, No, or Unknown but found "' . clean_text((string)$r[$key]) . '". Using Unknown.');
            $v = 'Unknown';
        }
        $c[$col] = $v;
    }

    // ---- Free text ----
    $c['residency_requirement'] = clean_text($r['residency'] ?? '');
    if ($c['residency_requirement'] === '' && !empty($c['open_to_states'])) {
        $c['residency_requirement'] = 'Open to residents of: ' . implode(', ', $c['open_to_states']);
    }
    $c['other_requirements'] = clean_text($r['other_requirements'] ?? '');
    $c['application_method'] = clean_text($r['application_method'] ?? '');
    $c['notes'] = clean_text($r['notes'] ?? '');

    // ---- Does this row belong to the student who uploaded it? ----
    if ($assign && $c['provider'] !== '' && $computedScope !== null) {
        $letter = sponsor_letter($c['provider']);
        if ($computedScope === 'national') {
            $c['national_theme_id'] = (int)$assign['national_theme_id'];
            if (letter_in_range($letter, $assign['national_range']) === false) {
                $add('warning', 'provider', "Provider starts with '$letter' but this student's national range is " . $assign['national_range'] . '. The theme partner may own this one.');
            }
        } else {
            $c['national_theme_id'] = null;
            if ($c['state_code'] !== $assign['state_code']) {
                $add('warning', 'state', 'State ' . $c['state_code'] . " is not this student's assigned state (" . $assign['state_code'] . ').');
            } elseif (letter_in_range($letter, $assign['state_range']) === false) {
                $add('warning', 'provider', "Provider starts with '$letter' but this student's range for " . $assign['state_code'] . ' is ' . $assign['state_range'] . '.');
            }
        }
    }

    $hasError = (bool)array_filter($msgs, fn($m) => $m['level'] === 'error');
    $hasWarn = (bool)array_filter($msgs, fn($m) => $m['level'] === 'warning');
    return [
        'status'  => $hasError ? 'error' : ($hasWarn ? 'warning' : 'ready'),
        'messages' => $msgs,
        'clean'   => $c,
        'url_key' => $urlKey,
    ];
}

/**
 * Validate a whole file. $rows is a list of associative arrays keyed by the spreadsheet's header text
 * (what read_csv_rows() or SheetJS' sheet_to_json() gives you). Data starts at spreadsheet row 2.
 *
 * $ctx (by reference so duplicates are tracked ACROSS files in one upload session):
 *   existing     [url_key => scholarship id]            live records already in the database
 *   seen         [url_key => ['file' => ..., 'row' => ...]]   filled in by this function
 *   assignments  ["last|first" lowercase => assignment row]
 *   categories, fields, today                            (see validate_row)
 *
 * Returns ['file', 'student', 'header', 'rows' => [...], 'counts' => [total, ready, warning, duplicate, error], 'batch_messages' => []]
 */
function validate_file(array $rows, array $headers, string $fileName, array &$ctx): array
{
    $ctx['seen'] = $ctx['seen'] ?? [];
    $ctx['existing'] = $ctx['existing'] ?? [];
    $batchMsgs = [];
    $hm = map_headers($headers);
    if ($hm['missing']) {
        $labels = array_map(fn($k) => template_columns()[$k], $hm['missing']);
        $batchMsgs[] = ['level' => 'error', 'text' => 'Required column(s) not found: ' . implode(', ', $labels) . '. Use the official template.'];
    }
    if ($hm['unknown']) {
        $batchMsgs[] = ['level' => 'warning', 'text' => 'Ignored unrecognized column(s): ' . implode(', ', $hm['unknown']) . '.'];
    }

    $student = parse_student_from_filename($fileName);
    $assign = null;
    if ($student === null) {
        $batchMsgs[] = ['level' => 'warning', 'text' => 'File name is not LastName_FirstName.xlsx, so the contributing student is unknown.'];
    } else {
        $assign = $ctx['assignments'][strtolower($student['last'] . '|' . $student['first'])] ?? null;
        if ($assign === null && isset($ctx['assignments'])) {
            $batchMsgs[] = ['level' => 'warning', 'text' => "No student named {$student['first']} {$student['last']} in the assignment list."];
        }
    }

    $out = [];
    $counts = ['total' => 0, 'ready' => 0, 'warning' => 0, 'duplicate' => 0, 'error' => 0];
    $fatal = (bool)$hm['missing'];

    foreach ($rows as $i => $raw) {
        $rowNum = $i + 2;
        $keyed = [];
        foreach ($raw as $h => $val) {
            if (isset($hm['map'][$h])) {
                $keyed[$hm['map'][$h]] = is_string($val) ? $val : ($val === null ? '' : (string)$val);
            }
        }
        // Skip rows that are completely empty.
        if (count(array_filter($keyed, fn($v) => clean_text($v) !== '')) === 0) {
            continue;
        }
        $counts['total']++;

        if ($fatal) {
            $res = ['status' => 'error', 'messages' => [['level' => 'error', 'field' => '', 'text' => 'File is missing required columns.']], 'clean' => [], 'url_key' => null];
        } else {
            $res = validate_row($keyed, ['assignment' => $assign] + $ctx);
            if ($res['status'] !== 'error' && $res['url_key'] !== null) {
                $key = $res['url_key'];
                if (isset($ctx['existing'][$key])) {
                    $res['status'] = 'duplicate';
                    $res['messages'][] = ['level' => 'duplicate', 'field' => 'url', 'text' => 'Already in the database (scholarship #' . $ctx['existing'][$key] . ').'];
                } elseif (isset($ctx['seen'][$key])) {
                    $first = $ctx['seen'][$key];
                    $where = $first['file'] === $fileName ? 'earlier in this file' : 'in ' . $first['file'];
                    $res['status'] = 'duplicate';
                    $res['messages'][] = ['level' => 'duplicate', 'field' => 'url', 'text' => "Same official URL as row {$first['row']} $where."];
                } else {
                    $ctx['seen'][$key] = ['file' => $fileName, 'row' => $rowNum];
                }
            }
        }
        $counts[$res['status']]++;
        $out[] = ['row' => $rowNum, 'status' => $res['status'], 'messages' => $res['messages'], 'clean' => $res['clean'], 'url_key' => $res['url_key'], 'raw' => $keyed];
    }

    return ['file' => $fileName, 'student' => $student, 'assignment' => $assign, 'header' => $hm, 'rows' => $out, 'counts' => $counts, 'batch_messages' => $batchMsgs];
}

/** Read a CSV file into [headers, rows]. Handles a UTF-8 BOM and Excel's habit of ragged rows. */
function read_csv_rows(string $path): array
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return [[], []];
    }
    // Skip a UTF-8 BOM BEFORE parsing; otherwise it sits in front of the opening quote
    // and the first header keeps its quote characters.
    if (fread($fh, 3) !== "\xEF\xBB\xBF") {
        rewind($fh);
    }
    $headers = fgetcsv($fh, 0, ',', '"', '');
    if ($headers === false) {
        fclose($fh);
        return [[], []];
    }
    $headers = array_map(fn($h) => clean_text((string)$h), $headers);
    $rows = [];
    while (($line = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if ($line === [null]) {
            continue;
        }
        $line = array_pad($line, count($headers), '');
        $rows[] = array_combine($headers, array_slice($line, 0, count($headers)));
    }
    fclose($fh);
    return [$headers, $rows];
}

/** The downloadable template as CSV text (FR-IMP-01). */
function template_csv(): string
{
    $fh = fopen('php://temp', 'w+');
    fwrite($fh, "\xEF\xBB\xBF");                                  // BOM so Excel opens it as UTF-8
    fputcsv($fh, array_values(template_columns()), ',', '"', '');
    $example = [
        'Example Scholarship', 'Example Foundation', 'Foundation/nonprofit', 'https://www.example.org/scholarship',
        'Fastweb', 'MN', 'State/local', '9-12', 'High school', 'Merit', 'Engineering', '$1,000', '5', 'No', '',
        '2026-10-15', '2027-03-01', 'Minnesota resident', '3.0', 'No', 'Yes', 'Yes', 'No', 'No', 'No', '', 'Online form',
        date('Y-m-d'), 'DELETE THIS EXAMPLE ROW BEFORE UPLOADING',
    ];
    fputcsv($fh, $example, ',', '"', '');
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return $csv;
}
