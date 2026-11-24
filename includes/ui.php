<?php
/** Small display helpers shared by the admin pages. Everything returned is already HTML-escaped. */

/** A coloured Bootstrap badge for a row, batch, or scholarship status. */
function status_badge(string $status): string
{
    $map = [
        'ready' => 'success', 'warning' => 'warning text-dark', 'duplicate' => 'secondary', 'error' => 'danger',
        'staged' => 'info text-dark', 'committed' => 'success', 'rolled_back' => 'dark', 'discarded' => 'secondary',
        'active' => 'success', 'archived' => 'secondary',
    ];
    $cls = $map[$status] ?? 'light text-dark';
    return '<span class="badge text-bg-' . $cls . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function fmt_date(?string $d): string
{
    return $d ? e(date('M j, Y', strtotime($d))) : '<span class="text-muted">None</span>';
}

function fmt_money_range($min, $max): string
{
    if ($min === null && $max === null) { return 'Varies'; }
    if ($min === null) { return 'Up to $' . number_format((float)$max); }
    if ($max === null || (float)$min === (float)$max) { return '$' . number_format((float)$min); }
    return '$' . number_format((float)$min) . ' to $' . number_format((float)$max);
}

/** Only allow redirects back to our own admin pages (stops open-redirect tricks via a "return" field). */
function safe_admin_return(?string $path, string $fallback): string
{
    $path = (string)$path;
    if ($path !== '' && preg_match('#^admin/[A-Za-z0-9_./-]*(\?[A-Za-z0-9_=&%.,\[\]-]*)?$#', $path) && !str_contains($path, '..')) {
        return $path;
    }
    return $fallback;
}

// ---------------------------------------------------------------------------
// Iteration 6: summary cards and charts (shared by facts.php and the admin dashboards)
// ---------------------------------------------------------------------------

/** A summary card: big number, label, optional small note. Colours never carry the meaning alone (the label says it). */
function stat_card(string $value, string $label, string $note = '', string $tone = ''): string
{
    $cls = $tone !== '' ? ' stat-card--' . e($tone) : '';
    return '<div class="col-6 col-lg-3"><div class="stat-card' . $cls . '"><div class="stat-value">' . e($value) . '</div>'
        . '<div class="stat-label">' . e($label) . '</div>' . ($note !== '' ? '<div class="stat-note">' . e($note) . '</div>' : '') . '</div></div>';
}

/**
 * A chart card. $data is [label => number]. assets/js/charts.js draws it with Chart.js; the same numbers are also in a
 * table under "Show the numbers" so the chart is usable with a screen reader and without JavaScript.
 * $type: bar | hbar | doughnut | line.
 */
function chart_block(string $id, string $title, string $type, array $data, string $unit = 'scholarships', string $colClass = 'col-lg-6'): string
{
    $labels = array_map('strval', array_keys($data));
    $values = array_map(fn($v) => is_numeric($v) ? $v + 0 : 0, array_values($data));
    $total = array_sum($values);
    $summary = $title . '. ' . ($total === 0 ? 'No data yet.' : implode(', ', array_map(fn($l, $v) => "$l: $v", array_slice($labels, 0, 6), array_slice($values, 0, 6))) . (count($labels) > 6 ? ', and more.' : '.'));
    $cfg = e(json_encode(['type' => $type, 'labels' => $labels, 'values' => $values, 'unit' => $unit], JSON_UNESCAPED_UNICODE));
    $h = '<div class="' . e($colClass) . '"><section class="chart-card" aria-labelledby="' . e($id) . '-t"><h2 class="h5" id="' . e($id) . '-t">' . e($title) . '</h2>';
    if ($total === 0) {
        return $h . '<p class="text-muted mb-0">No data yet.</p></section></div>';
    }
    $h .= '<div class="chart-box' . ($type === 'hbar' ? ' chart-box--tall' : '') . '"><canvas id="' . e($id) . '" class="js-chart" data-chart="' . $cfg . '" role="img" aria-label="' . e($summary) . '"></canvas></div>';
    $h .= '<details class="mt-2"><summary>Show the numbers</summary><table class="table table-sm mt-2 mb-0"><caption class="visually-hidden">' . e($title) . '</caption>'
        . '<thead><tr><th scope="col">Item</th><th scope="col" class="text-end">' . e(ucfirst($unit)) . '</th></tr></thead><tbody>';
    foreach ($labels as $i => $l) { $h .= '<tr><td>' . e($l) . '</td><td class="text-end">' . e((string)$values[$i]) . '</td></tr>'; }
    return $h . '</tbody></table></details></section></div>';
}

/** Script tags for pages that draw charts. Put the result in $extraScripts before including footer.php. */
function chart_scripts(): string
{
    return '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>'
         . '<script src="' . e(url('assets/js/charts.js')) . '"></script>';
}
