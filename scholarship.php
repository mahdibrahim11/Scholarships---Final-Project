<?php
/** Public scholarship detail page (Iteration 5): the full picture, the official URL, and when it was last verified. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/search.php';

$id = ctype_digit((string)($_GET['id'] ?? '')) ? (int)$_GET['id'] : 0;
$s = $id > 0 ? scholarship_public(db(), $id) : null;

// "Back to results" returns to the same filters. The saved query is re-cleaned, never trusted as typed.
parse_str((string)($_GET['from'] ?? ''), $fromRaw);
$backQs = search_query_string(search_params(is_array($fromRaw) ? $fromRaw : []));
$backUrl = url('search.php') . ($backQs !== '' ? '?' . $backQs : '');

if (!$s) {
    http_response_code(404);
    $pageTitle = 'Scholarship not found';
    require __DIR__ . '/includes/header.php';
    echo '<h1>We could not find that scholarship</h1><p>It may have been removed or is no longer listed.</p>'
       . '<p><a class="btn btn-primary" href="' . e($backUrl) . '">Back to search</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$official = safe_external_url($s['official_url']);
$expired = $s['deadline'] !== null && $s['deadline'] < date('Y-m-d');
$yn = fn($v) => $v === 'Yes' ? 'Yes' : ($v === 'No' ? 'No' : 'Not listed');
$list = fn(array $a) => $a ? implode(', ', array_map('e', $a)) : 'Not listed';

$pageTitle = $s['name'];
require __DIR__ . '/includes/header.php';
?>
<nav aria-label="Breadcrumb"><a href="<?= e($backUrl) ?>">&larr; Back to results</a></nav>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mt-2 mb-3">
  <div>
    <h1 class="mb-1"><?= e($s['name']) ?></h1>
    <p class="lead mb-1"><?= e($s['provider']) ?></p>
    <p class="mb-0">
      <span class="badge text-bg-secondary"><?= e(state_label($s['state_code'], $s['state_name'])) ?></span>
      <?php if ($s['school_level']): ?><span class="badge text-bg-light border"><?= e(['middle' => 'Middle school', 'high' => 'High school', 'both' => 'Middle and high school'][$s['school_level']] ?? '') ?></span><?php endif; ?>
      <?php if ($s['theme']): ?><span class="badge text-bg-light border"><?= e($s['theme']) ?></span><?php endif; ?>
    </p>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-start">
  <?php require_once __DIR__ . '/includes/profiles.php'; $saveCtx = save_context($pdo ?? db()); echo save_button($saveCtx, (int)$s['id'], $s['name']); ?>
  <?php if ($official): ?>
    <a class="btn btn-primary btn-lg" href="<?= e($official) ?>" target="_blank" rel="noopener noreferrer">Go to the official page<span class="visually-hidden"> for <?= e($s['name']) ?> (opens in a new tab)</span></a>
  <?php endif; ?>
  </div>
</div>

<?php if ($expired): ?>
  <div class="alert alert-warning" role="status"><strong>The deadline has passed.</strong> Check the official page to see whether it opens again.</div>
<?php endif; ?>

<div class="verify-box mb-4" role="note">
  <?php if ($s['verification_date']): ?>
    <strong>Last verified <?= e(date('F j, Y', strtotime($s['verification_date']))) ?>.</strong>
  <?php else: ?>
    <strong>We have not recorded when this was last checked.</strong>
  <?php endif; ?>
  Details change, so always confirm on the provider's official page before you apply.
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <h2 class="h5">Award and dates</h2>
    <dl class="row">
      <dt class="col-sm-5">Award</dt><dd class="col-sm-7"><?= e(fmt_money_range($s['award_min'], $s['award_max'])) ?></dd>
      <dt class="col-sm-5">Number of awards</dt><dd class="col-sm-7"><?= $s['num_awards'] !== null ? e((string)$s['num_awards']) : 'Not listed' ?></dd>
      <dt class="col-sm-5">Can be renewed</dt><dd class="col-sm-7"><?= e($yn($s['renewable'])) ?></dd>
      <?php if ($s['max_potential_value'] !== null): ?><dt class="col-sm-5">Total possible value</dt><dd class="col-sm-7">$<?= e(number_format((float)$s['max_potential_value'])) ?></dd><?php endif; ?>
      <dt class="col-sm-5">Opens</dt><dd class="col-sm-7"><?= $s['open_date'] ? e(date('M j, Y', strtotime($s['open_date']))) : 'Not listed' ?></dd>
      <dt class="col-sm-5">Deadline</dt><dd class="col-sm-7"><?= $s['is_rolling'] ? 'Rolling (no fixed deadline)' : deadline_html($s['deadline'], false) ?></dd>
      <dt class="col-sm-5">How to apply</dt><dd class="col-sm-7"><?= e($s['application_method'] ?? 'See the official page') ?></dd>
    </dl>

    <h2 class="h5">What applicants provide</h2>
    <dl class="row">
      <dt class="col-sm-5">Essay</dt><dd class="col-sm-7"><?= e($yn($s['essay_required'])) ?></dd>
      <dt class="col-sm-5">Recommendation</dt><dd class="col-sm-7"><?= e($yn($s['recommendation_required'])) ?></dd>
      <dt class="col-sm-5">Transcript or test score</dt><dd class="col-sm-7"><?= e($yn($s['transcript_required'])) ?></dd>
      <dt class="col-sm-5">Financial need required</dt><dd class="col-sm-7"><?= e($yn($s['need_required'])) ?></dd>
      <dt class="col-sm-5">Community service</dt><dd class="col-sm-7"><?= e($yn($s['community_service_required'])) ?></dd>
      <dt class="col-sm-5">Leadership</dt><dd class="col-sm-7"><?= e($yn($s['leadership_required'])) ?></dd>
    </dl>
  </div>
  <div class="col-lg-6">
    <h2 class="h5">Who can apply</h2>
    <dl class="row">
      <dt class="col-sm-5">Grades</dt><dd class="col-sm-7"><?= e(grades_label($s['grades'])) ?></dd>
      <dt class="col-sm-5">State</dt><dd class="col-sm-7"><?= e(state_label($s['state_code'], $s['state_name'])) ?></dd>
      <dt class="col-sm-5">Residency</dt><dd class="col-sm-7"><?= e($s['residency_requirement'] ?? 'None listed') ?></dd>
      <dt class="col-sm-5">Minimum GPA</dt><dd class="col-sm-7"><?= $s['min_gpa'] !== null ? e(rtrim(rtrim((string)$s['min_gpa'], '0'), '.')) : 'None listed' ?></dd>
      <dt class="col-sm-5">Categories</dt><dd class="col-sm-7"><?= $list($s['categories']) ?></dd>
      <dt class="col-sm-5">Fields of study</dt><dd class="col-sm-7"><?= $list($s['fields']) ?></dd>
      <?php if ($s['other_requirements']): ?><dt class="col-sm-5">Other requirements</dt><dd class="col-sm-7"><?= nl2br(e($s['other_requirements'])) ?></dd><?php endif; ?>
    </dl>
    <h2 class="h5">Provider</h2>
    <dl class="row">
      <dt class="col-sm-5">Organization</dt><dd class="col-sm-7"><?= e($s['provider']) ?></dd>
      <?php if ($s['provider_type']): ?><dt class="col-sm-5">Type</dt><dd class="col-sm-7"><?= e($s['provider_type']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($official): ?>
      <p class="text-muted small mb-0">Official page: <a href="<?= e($official) ?>" target="_blank" rel="noopener noreferrer"><?= e($official) ?><span class="visually-hidden"> (opens in a new tab)</span></a></p>
    <?php endif; ?>
  </div>
</div>
<?php $extraScripts = '<script src="' . e(url('assets/js/save.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
