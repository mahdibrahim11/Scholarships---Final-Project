<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Find scholarships for middle and high school';

$states = db()->query('SELECT code, name FROM states ORDER BY (code = "US") DESC, name')->fetchAll();
$fields = db()->query('SELECT id, name FROM fields_of_study ORDER BY name')->fetchAll();
$grades = db()->query('SELECT grade, label FROM grade_levels ORDER BY grade')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <h1>Scholarships you can apply for before college.</h1>
  <p class="lead mb-4">Tell us your state, your grade, and what you like to study. We list scholarships for grades 6 to 12, with the amount, the deadline, and a link to the official page.</p>

  <form class="quick-search" action="<?= e(url('search.php')) ?>" method="get" aria-labelledby="qs-title">
    <h2 class="h5 mb-3" id="qs-title">Quick search</h2>
    <div class="row g-3 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label" for="state">State</label>
        <select class="form-select" id="state" name="state">
          <option value="">Any state</option>
          <?php foreach ($states as $s): ?>
            <option value="<?= e($s['code']) ?>"><?= e($s['code'] === 'US' ? 'National only' : $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label" for="grade">Grade</label>
        <select class="form-select" id="grade" name="grade">
          <option value="">Any grade</option>
          <?php foreach ($grades as $g): ?>
            <option value="<?= (int)$g['grade'] ?>"><?= e($g['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label" for="field">Field of study</label>
        <select class="form-select" id="field" name="field">
          <option value="">Any field</option>
          <?php foreach ($fields as $f): ?>
            <option value="<?= (int)$f['id'] ?>"><?= e($f['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">Search</button>
      </div>
    </div>
    <p class="mt-3 mb-0 small"><a href="<?= e(url('search.php')) ?>">Browse all scholarships</a> or add a keyword and more filters on the next page.</p>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
