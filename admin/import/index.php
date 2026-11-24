<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);
$pageTitle = 'Import data';
$maxMb = (int)config('app.max_upload_mb', 5);
require __DIR__ . '/../../includes/header.php';
?>
<h1>Import scholarship data</h1>
<p>Upload one or more files from the Data Science class. You review every row before anything is saved.</p>

<div class="row g-4">
  <div class="col-lg-7">
    <form class="card card-body" method="post" action="<?= e(url('admin/import/upload.php')) ?>" enctype="multipart/form-data" id="upload-form" novalidate>
      <?= csrf_field() ?>
      <label class="form-label fw-bold" for="files">Scholarship files (.csv or .xlsx)</label>
      <input class="form-control" type="file" id="files" name="files[]" accept=".csv,.xlsx" multiple required aria-describedby="files-help files-err">
      <div id="files-help" class="form-text">
        Choose several files at once if you like. Each file is up to <?= $maxMb ?> MB.
        Name each file <code>LastName_FirstName.xlsx</code> (or <code>.csv</code>) so we know who collected it.
      </div>
      <div class="invalid-feedback" id="files-err">Choose at least one .csv or .xlsx file.</div>
      <div><button class="btn btn-primary mt-3" type="submit">Upload and check files</button></div>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="card card-body">
      <h2 class="h5">Before you upload</h2>
      <p class="mb-2"><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/import/template.php')) ?>">Download the template (CSV)</a></p>
      <p class="mb-0 small text-muted">The template has all 29 columns. Delete its example row before uploading.</p>
    </div>
    <div class="card card-body mt-3">
      <h2 class="h5">What happens next</h2>
      <ol class="mb-0 ps-3">
        <li>Each row is cleaned and checked.</li>
        <li>You see a report: Ready, Warning, Duplicate, or Error, with the reason.</li>
        <li>You choose <strong>Commit</strong> to save the good rows, or <strong>Discard</strong> to throw the file away.</li>
        <li>A committed batch can be rolled back from the history page.</li>
      </ol>
    </div>
  </div>
</div>
<p class="mt-4">
  <a href="<?= e(url('admin/import/history.php')) ?>">Import history</a> ·
  <a href="<?= e(url('admin/coverage.php')) ?>">Coverage report (students, states, themes)</a>
</p>
<?php
$extraScripts = <<<'JS'
<script>
$('#upload-form').on('submit', function (ev) {
  var input = document.getElementById('files'), ok = input.files.length > 0;
  for (var i = 0; i < input.files.length; i++) {
    if (!/\.(csv|xlsx)$/i.test(input.files[i].name)) { ok = false; }
  }
  input.classList.toggle('is-invalid', !ok);
  if (!ok) { ev.preventDefault(); input.focus(); }
});
</script>
JS;
require __DIR__ . '/../../includes/footer.php';
