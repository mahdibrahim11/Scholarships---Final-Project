<?php
/** Read-only view of one scholarship with Edit, Verify, Archive / Restore (FR-ADM-02, FR-ADM-03). */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/scholarships.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$s = $id > 0 ? load_scholarship($pdo, $id) : null;
if (!$s) {
    http_response_code(404);
    $pageTitle = 'Scholarship not found';
    require __DIR__ . '/../../includes/header.php';
    echo '<h1>Scholarship not found</h1><p><a href="' . e(url('admin/scholarships/index.php')) . '">Back to the list</a></p>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}
$ref = scholarship_ref($pdo);
$names = fn(array $ids, array $lookup) => implode(', ', array_map(fn($i) => $lookup[$i] ?? '', $ids)) ?: 'None';
$yn = fn($v) => $v === 'Unknown' ? '<span class="text-muted">Unknown</span>' : e($v);
$self = 'admin/scholarships/view.php?id=' . $id;

$pageTitle = $s['name'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="Breadcrumb"><a href="<?= e(url('admin/scholarships/index.php')) ?>">Manage scholarships</a> / #<?= $id ?></nav>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2">
  <div>
    <h1 class="mb-1"><?= e($s['name']) ?></h1>
    <p class="mb-1"><?= status_badge($s['status']) ?> <?= e($s['provider_name']) ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="<?= e(url('admin/scholarships/edit.php?id=' . $id)) ?>">Edit</a>
    <?php if ($s['status'] === 'active'): ?>
      <form method="post" action="<?= e(url('admin/scholarships/actions.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($self) ?>">
        <button class="btn btn-success" type="submit" name="action" value="verify">Mark verified today</button>
      </form>
      <form method="post" action="<?= e(url('admin/scholarships/actions.php')) ?>" onsubmit="return confirm('Archive this scholarship? It disappears from public search.');">
        <?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($self) ?>">
        <button class="btn btn-outline-danger" type="submit" name="action" value="archive">Archive</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= e(url('admin/scholarships/actions.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($self) ?>">
        <button class="btn btn-outline-secondary" type="submit" name="action" value="restore">Restore</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<p class="my-3"><a class="btn btn-outline-primary btn-sm" href="<?= e($s['official_url']) ?>" target="_blank" rel="noopener noreferrer">Open the official page<span class="visually-hidden"> (opens in a new tab)</span></a>
   <span class="text-muted small ms-2"><?= e($s['official_url']) ?></span></p>

<div class="row g-4">
  <div class="col-lg-6">
    <h2 class="h5">Where and who</h2>
    <dl class="row">
      <dt class="col-sm-5">State</dt><dd class="col-sm-7"><?= e($s['state_code'] === 'US' ? 'National' : $s['state_name']) ?> (<?= e($s['scope'] === 'national' ? 'national' : 'state or local') ?>)</dd>
      <dt class="col-sm-5">National theme</dt><dd class="col-sm-7"><?= e($s['theme_name'] ?? 'None') ?></dd>
      <dt class="col-sm-5">Grades</dt><dd class="col-sm-7"><?= e(implode(', ', $s['grades']) ?: 'None') ?> <?= $s['school_level'] ? '(' . e($s['school_level']) . ' school)' : '' ?></dd>
      <dt class="col-sm-5">Categories</dt><dd class="col-sm-7"><?= e($names($s['category_ids'], $ref['categories'])) ?></dd>
      <dt class="col-sm-5">Fields of study</dt><dd class="col-sm-7"><?= e($names($s['field_ids'], $ref['fields'])) ?></dd>
      <dt class="col-sm-5">Residency</dt><dd class="col-sm-7"><?= e($s['residency_requirement'] ?? 'None') ?></dd>
      <dt class="col-sm-5">Minimum GPA</dt><dd class="col-sm-7"><?= e($s['min_gpa'] ?? 'None') ?></dd>
      <dt class="col-sm-5">Other requirements</dt><dd class="col-sm-7"><?= e($s['other_requirements'] ?? 'None') ?></dd>
    </dl>
    <h2 class="h5">Requirements</h2>
    <dl class="row">
      <dt class="col-sm-5">Financial need</dt><dd class="col-sm-7"><?= $yn($s['need_required']) ?></dd>
      <dt class="col-sm-5">Essay</dt><dd class="col-sm-7"><?= $yn($s['essay_required']) ?></dd>
      <dt class="col-sm-5">Recommendation</dt><dd class="col-sm-7"><?= $yn($s['recommendation_required']) ?></dd>
      <dt class="col-sm-5">Transcript or test score</dt><dd class="col-sm-7"><?= $yn($s['transcript_required']) ?></dd>
      <dt class="col-sm-5">Community service</dt><dd class="col-sm-7"><?= $yn($s['community_service_required']) ?></dd>
      <dt class="col-sm-5">Leadership</dt><dd class="col-sm-7"><?= $yn($s['leadership_required']) ?></dd>
    </dl>
  </div>
  <div class="col-lg-6">
    <h2 class="h5">Award and dates</h2>
    <dl class="row">
      <dt class="col-sm-5">Award</dt><dd class="col-sm-7"><?= e(fmt_money_range($s['award_min'], $s['award_max'])) ?></dd>
      <dt class="col-sm-5">Number of awards</dt><dd class="col-sm-7"><?= e($s['num_awards'] ?? 'Not listed') ?></dd>
      <dt class="col-sm-5">Renewable</dt><dd class="col-sm-7"><?= $yn($s['renewable']) ?></dd>
      <dt class="col-sm-5">Maximum potential value</dt><dd class="col-sm-7"><?= $s['max_potential_value'] !== null ? '$' . e(number_format((float)$s['max_potential_value'])) : 'Not listed' ?></dd>
      <dt class="col-sm-5">Opens</dt><dd class="col-sm-7"><?= fmt_date($s['open_date']) ?></dd>
      <dt class="col-sm-5">Deadline</dt><dd class="col-sm-7"><?= $s['is_rolling'] ? 'Rolling (no fixed deadline)' : fmt_date($s['deadline']) ?></dd>
      <dt class="col-sm-5">Application method</dt><dd class="col-sm-7"><?= e($s['application_method'] ?? 'Not listed') ?></dd>
    </dl>
    <h2 class="h5">Record</h2>
    <dl class="row">
      <dt class="col-sm-5">Last verified</dt><dd class="col-sm-7"><?= fmt_date($s['verification_date']) ?><?= $s['verified_by_name'] ? ' by ' . e($s['verified_by_name']) : '' ?></dd>
      <dt class="col-sm-5">Contributed by</dt><dd class="col-sm-7"><?= e($s['contributing_student'] ?? 'Entered by hand') ?></dd>
      <dt class="col-sm-5">Import batch</dt><dd class="col-sm-7"><?= $s['import_batch_id'] ? '<a href="' . e(url('admin/import/report.php?batch=' . (int)$s['import_batch_id'])) . '">#' . (int)$s['import_batch_id'] . '</a>' : 'None' ?></dd>
      <dt class="col-sm-5">Discovery source</dt><dd class="col-sm-7"><?= e($s['discovery_source'] ?? 'Not listed') ?></dd>
      <dt class="col-sm-5">Notes</dt><dd class="col-sm-7"><?= e($s['notes'] ?? 'None') ?></dd>
      <dt class="col-sm-5">Created / updated</dt><dd class="col-sm-7"><?= e(date('M j, Y', strtotime($s['created_at']))) ?> / <?= e(date('M j, Y', strtotime($s['updated_at']))) ?></dd>
    </dl>
    <p><a href="<?= e(url('admin/audit.php?entity=scholarship&id=' . $id)) ?>">See the change history for this scholarship</a></p>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php';
