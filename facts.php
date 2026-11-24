<?php
/** Public "Scholarship Facts" page (FR-DASH-01): summary cards and Chart.js charts. Aggregates only; no notes, names or batch data. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/quality.php';
require_once __DIR__ . '/includes/ui.php';

$f = public_facts(db());
$pageTitle = 'Scholarship Facts';
$extraScripts = chart_scripts();
require __DIR__ . '/includes/header.php';
$money = fn($v) => $v === null ? 'N/A' : '$' . number_format($v);
?>
<h1>Scholarship Facts</h1>
<p class="lead text-muted">What is in our collection of middle and high school scholarships, in numbers.
  <?php if ($f['last_verified']): ?>Most recently verified: <?= e(date('M j, Y', strtotime($f['last_verified']))) ?>.<?php endif; ?></p>

<?php if ($f['total'] === 0): ?>
  <div class="empty-state">No scholarships have been added yet. Please check back soon.</div>
<?php else: ?>
<div class="row g-3 mb-4">
  <?= stat_card(number_format($f['total']), 'Scholarships') ?>
  <?= stat_card((string)$f['states'], 'States covered', $f['national'] . ' national scholarships') ?>
  <?= stat_card(number_format($f['open_now']), 'Open for applications', 'Deadline not passed, or rolling', 'good') ?>
  <?= stat_card((string)$f['providers'], 'Providers') ?>
  <?= stat_card($money($f['avg_award']), 'Average top award', 'Among scholarships that list an amount') ?>
  <?= stat_card($money($f['max_award']), 'Largest award listed') ?>
  <?= stat_card(number_format($f['no_essay']), 'No essay required', 'Provider says no essay') ?>
  <?= stat_card(number_format($f['no_need']), 'No financial need required', 'Open to any income') ?>
</div>
<div class="row g-3">
  <?= chart_block('f-state', 'Scholarships by state (top 15)', 'hbar', $f['by_state']) ?>
  <?= chart_block('f-level', 'Middle school, high school, or both', 'doughnut', $f['level']) ?>
  <?= chart_block('f-grade', 'Scholarships open to each grade', 'bar', array_combine(array_map(fn($g) => 'Grade ' . $g, array_keys($f['by_grade'])), array_values($f['by_grade']))) ?>
  <?= chart_block('f-award', 'Award sizes (top award)', 'bar', $f['award_buckets']) ?>
  <?= chart_block('f-deadline', 'Deadlines in the next 12 months', 'line', $f['deadline_months'], 'deadlines') ?>
  <?= chart_block('f-category', 'Categories', 'hbar', array_slice($f['by_category'], 0, 12, true)) ?>
  <?= chart_block('f-field', 'Fields of interest', 'hbar', array_slice($f['by_field'], 0, 12, true)) ?>
  <?= chart_block('f-req', 'What applicants are asked for', 'bar', [
      'Essay' => $f['requirements']['essay'], 'Recommendation' => $f['requirements']['rec'], 'Financial need' => $f['requirements']['need'],
      'Transcript' => $f['requirements']['transcript'], 'Community service' => $f['requirements']['service'],
      'Leadership' => $f['requirements']['leadership'], 'Minimum GPA' => $f['requirements']['gpa']]) ?>
</div>
<p class="small text-muted mt-3">Counts include only active scholarships. "Not required" or "unknown" is not counted as required. Always confirm details on the provider's official page before applying.</p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
