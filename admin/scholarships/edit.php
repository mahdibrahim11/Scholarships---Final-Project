<?php
/**
 * Create or edit a scholarship (FR-ADM-02, FR-ADM-04). Leave ?id off to create.
 * Server-side validation is the authority (includes/scholarships.php); the jQuery below only mirrors it
 * so people get quick feedback without a round trip.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/scholarships.php';
require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$isNew = $id === 0;
$existing = $isNew ? null : load_scholarship($pdo, $id);
if (!$isNew && !$existing) {
    http_response_code(404);
    $pageTitle = 'Scholarship not found';
    require __DIR__ . '/../../includes/header.php';
    echo '<h1>Scholarship not found</h1><p><a href="' . e(url('admin/scholarships/index.php')) . '">Back to the list</a></p>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}
$ref = scholarship_ref($pdo);
$form = $existing ?? blank_scholarship();
$errors = [];
$saveError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$errors, $clean] = validate_scholarship_input($_POST, $ref, $pdo, $isNew ? null : $id, $existing['status'] ?? 'active');
    if (!$errors) {
        try {
            $savedId = save_scholarship($pdo, $isNew ? null : $id, $clean);
            flash('success', $isNew ? 'The scholarship was added.' : 'Your changes were saved.');
            redirect('admin/scholarships/view.php?id=' . $savedId);
        } catch (PDOException $e) {
            error_log('save_scholarship failed: ' . $e->getMessage());
            $saveError = ($e->errorInfo[1] ?? 0) === 1062
                ? 'Another active scholarship already uses that web address.'
                : 'The scholarship could not be saved. Nothing was changed.';
        } catch (Throwable $e) {
            error_log('save_scholarship failed: ' . $e->getMessage());
            $saveError = 'The scholarship could not be saved. Nothing was changed.';
        }
    }
    // Show exactly what the person typed, including values that failed validation.
    foreach (array_merge(SCHOLARSHIP_SCALARS, ['provider_name', 'provider_type_id']) as $k) {
        $form[$k] = (string)($_POST[$k] ?? '');
    }
    $form['is_rolling'] = !empty($_POST['is_rolling']) ? 1 : 0;
    $form['grades'] = array_map('intval', (array)($_POST['grades'] ?? []));
    $form['category_ids'] = array_map('intval', (array)($_POST['category_ids'] ?? []));
    $form['field_ids'] = array_map('intval', (array)($_POST['field_ids'] ?? []));
}

$providerNames = $pdo->query('SELECT name FROM providers ORDER BY name LIMIT 3000')->fetchAll(PDO::FETCH_COLUMN);

// ---- small rendering helpers ----------------------------------------------------------------
function v(array $form, string $k): string { return e((string)($form[$k] ?? '')); }
function msg(array $errors, string $k, string $default = ''): string { return e($errors[$k] ?? $default); }
function bad(array $errors, string $k): string { return isset($errors[$k]) ? ' is-invalid' : ''; }
function input(array $form, array $errors, string $k, string $label, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $req = !empty($o['required']);
    $help = $o['help'] ?? '';
    $attrs = ($req ? ' required' : '') . (isset($o['maxlength']) ? ' maxlength="' . (int)$o['maxlength'] . '"' : '')
        . (isset($o['list']) ? ' list="' . e($o['list']) . '"' : '') . (isset($o['inputmode']) ? ' inputmode="' . e($o['inputmode']) . '"' : '')
        . (isset($o['autocomplete']) ? ' autocomplete="' . e($o['autocomplete']) . '"' : '');
    return '<div class="' . e($o['col'] ?? 'col-md-6') . '"><label class="form-label" for="f-' . $k . '">' . e($label) . ($req ? ' <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>' : '') . '</label>'
        . '<input class="form-control' . bad($errors, $k) . '" id="f-' . $k . '" name="' . $k . '" type="' . e($type) . '" value="' . v($form, $k) . '"' . $attrs
        . ' aria-describedby="err-' . $k . ($help ? ' help-' . $k : '') . '">'
        . '<div class="invalid-feedback" id="err-' . $k . '">' . msg($errors, $k) . '</div>'
        . ($help ? '<div class="form-text" id="help-' . $k . '">' . e($help) . '</div>' : '') . '</div>';
}
function select(array $form, array $errors, string $k, string $label, array $options, array $o = []): string
{
    $cur = (string)($form[$k] ?? '');
    $html = '<div class="' . e($o['col'] ?? 'col-md-6') . '"><label class="form-label" for="f-' . $k . '">' . e($label) . (!empty($o['required']) ? ' <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden">(required)</span>' : '') . '</label>'
        . '<select class="form-select' . bad($errors, $k) . '" id="f-' . $k . '" name="' . $k . '"' . (!empty($o['required']) ? ' required' : '') . ' aria-describedby="err-' . $k . '">';
    if (isset($o['blank'])) { $html .= '<option value="">' . e($o['blank']) . '</option>'; }
    if ($cur !== '' && !isset($options[$cur]) && !in_array($cur, array_keys($options), true) && empty($o['strict'])) { $options = [$cur => $cur] + $options; }
    foreach ($options as $val => $text) {
        $html .= '<option value="' . e((string)$val) . '"' . ((string)$val === $cur ? ' selected' : '') . '>' . e((string)$text) . '</option>';
    }
    return $html . '</select><div class="invalid-feedback" id="err-' . $k . '">' . msg($errors, $k) . '</div></div>';
}
function checks(string $name, string $legend, array $options, array $chosen, string $err = '', bool $inline = false, string $errKey = ''): string
{
    $errKey = $errKey ?: $name;
    $html = '<fieldset class="mb-0"><legend class="form-label fs-6">' . e($legend) . '</legend><div class="' . ($inline ? 'd-flex flex-wrap gap-3' : 'border rounded p-2 checklist') . '">';
    foreach ($options as $val => $text) {
        $cid = 'c-' . $name . '-' . $val;
        $html .= '<div class="form-check' . ($inline ? '' : ' mb-1') . '"><input class="form-check-input" type="checkbox" id="' . e($cid) . '" name="' . e($name) . '[]" value="' . e((string)$val) . '"'
            . (in_array((int)$val, $chosen, true) ? ' checked' : '') . '><label class="form-check-label" for="' . e($cid) . '">' . e((string)$text) . '</label></div>';
    }
    return $html . '</div><div class="invalid-feedback' . ($err ? ' d-block' : '') . '" id="err-' . e($errKey) . '">' . e($err) . '</div></fieldset>';
}
$yn = array_combine(YES_NO_UNKNOWN, YES_NO_UNKNOWN);
$methods = array_combine(APPLICATION_METHODS, APPLICATION_METHODS);
$stateOpts = [];
foreach ($ref['states'] as $code => $name) { $stateOpts[$code] = $code === 'US' ? 'National (any state)' : $name; }
$gradeOpts = array_combine($ref['grades'], array_map(fn($g) => (string)$g, $ref['grades']));

$pageTitle = $isNew ? 'Add a scholarship' : 'Edit scholarship #' . $id;
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="Breadcrumb"><a href="<?= e(url('admin/scholarships/index.php')) ?>">Manage scholarships</a> / <?= $isNew ? 'Add' : '<a href="' . e(url('admin/scholarships/view.php?id=' . $id)) . '">#' . $id . '</a> / Edit' ?></nav>
<h1 class="mt-2"><?= $isNew ? 'Add a scholarship' : 'Edit scholarship' ?></h1>
<?php if ($errors): ?>
  <div class="alert alert-danger" role="alert" id="error-summary">Please fix the <?= count($errors) ?> problem<?= count($errors) === 1 ? '' : 's' ?> marked below. Nothing was saved.</div>
<?php endif; ?>
<?php if ($saveError): ?><div class="alert alert-danger" role="alert"><?= e($saveError) ?></div><?php endif; ?>
<?php if (!$isNew && $existing['status'] === 'archived'): ?><div class="alert alert-secondary">This scholarship is archived. Restore it from its page to show it in public search again.</div><?php endif; ?>

<form method="post" id="sch-form" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">

  <h2 class="h5 mt-4">Basics</h2>
  <div class="row g-3">
    <?= input($form, $errors, 'name', 'Scholarship name', ['required' => 1, 'maxlength' => 255, 'col' => 'col-12']) ?>
    <?= input($form, $errors, 'provider_name', 'Provider', ['required' => 1, 'maxlength' => 255, 'list' => 'provider-list', 'autocomplete' => 'off',
        'help' => 'Pick an existing provider as you type, or enter a new one. Spelling a provider the same way keeps the list clean.']) ?>
    <?= select($form, $errors, 'provider_type_id', 'Provider type', $ref['provider_types'], ['blank' => 'Not listed', 'strict' => 1]) ?>
    <?= input($form, $errors, 'official_url', 'Official web address', ['required' => 1, 'maxlength' => 2048, 'type' => 'text', 'inputmode' => 'url', 'col' => 'col-12',
        'help' => 'The provider\'s own page for this scholarship, for example https://www.example.org/apply.']) ?>
    <?= input($form, $errors, 'discovery_source', 'Where it was found', ['maxlength' => 255, 'help' => 'For example Fastweb or a teacher.']) ?>
  </div>
  <datalist id="provider-list"><?php foreach ($providerNames as $pn): ?><option value="<?= e($pn) ?>"><?php endforeach; ?></datalist>

  <h2 class="h5 mt-4">Who can apply</h2>
  <div class="row g-3">
    <?= select($form, $errors, 'state_code', 'State', $stateOpts, ['required' => 1, 'blank' => 'Choose a state', 'strict' => 1]) ?>
    <?= select($form, $errors, 'national_theme_id', 'National theme (national scholarships)', $ref['themes'], ['blank' => 'None', 'strict' => 1]) ?>
    <div class="col-12"><?= checks('grades', 'Grades (choose at least one) *', $gradeOpts, $form['grades'], $errors['grades'] ?? '', true) ?></div>
    <div class="col-md-6"><?= checks('category_ids', 'Categories', $ref['categories'], $form['category_ids']) ?></div>
    <div class="col-md-6"><?= checks('field_ids', 'Fields of study', $ref['fields'], $form['field_ids']) ?></div>
    <?= input($form, $errors, 'min_gpa', 'Minimum GPA', ['inputmode' => 'decimal', 'maxlength' => 4, 'help' => 'Leave blank if there is none.']) ?>
    <?= input($form, $errors, 'residency_requirement', 'Residency requirement', ['maxlength' => 500]) ?>
    <div class="col-12"><label class="form-label" for="f-other_requirements">Other eligibility requirements</label>
      <textarea class="form-control" id="f-other_requirements" name="other_requirements" rows="2" maxlength="5000"><?= v($form, 'other_requirements') ?></textarea></div>
  </div>

  <h2 class="h5 mt-4">Award and dates</h2>
  <div class="row g-3">
    <?= input($form, $errors, 'award_min', 'Smallest award ($)', ['inputmode' => 'decimal', 'col' => 'col-6 col-md-3']) ?>
    <?= input($form, $errors, 'award_max', 'Largest award ($)', ['inputmode' => 'decimal', 'col' => 'col-6 col-md-3', 'help' => 'Same as the smallest if there is one amount.']) ?>
    <?= input($form, $errors, 'num_awards', 'Number of awards', ['inputmode' => 'numeric', 'col' => 'col-6 col-md-3']) ?>
    <?= select($form, $errors, 'renewable', 'Renewable', $yn, ['col' => 'col-6 col-md-3', 'strict' => 1]) ?>
    <?= input($form, $errors, 'max_potential_value', 'Maximum potential value ($)', ['inputmode' => 'decimal', 'col' => 'col-md-4']) ?>
    <?= input($form, $errors, 'open_date', 'Application opens', ['type' => 'date', 'col' => 'col-6 col-md-3']) ?>
    <?= input($form, $errors, 'deadline', 'Application deadline', ['type' => 'date', 'col' => 'col-6 col-md-3']) ?>
    <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="f-is_rolling" name="is_rolling" value="1" <?= !empty($form['is_rolling']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="f-is_rolling">Rolling (no fixed deadline)</label></div></div>
  </div>

  <h2 class="h5 mt-4">What applicants must provide</h2>
  <div class="row g-3">
    <?= select($form, $errors, 'need_required', 'Financial need required', $yn, ['col' => 'col-6 col-md-4', 'strict' => 1]) ?>
    <?= select($form, $errors, 'essay_required', 'Essay required', $yn, ['col' => 'col-6 col-md-4', 'strict' => 1]) ?>
    <?= select($form, $errors, 'recommendation_required', 'Recommendation required', $yn, ['col' => 'col-6 col-md-4', 'strict' => 1]) ?>
    <?= select($form, $errors, 'transcript_required', 'Transcript or test score', $yn, ['col' => 'col-6 col-md-4', 'strict' => 1]) ?>
    <?= select($form, $errors, 'community_service_required', 'Community service', $yn, ['col' => 'col-6 col-md-4', 'strict' => 1]) ?>
    <?= select($form, $errors, 'leadership_required', 'Leadership', $yn, ['col' => 'col-6 col-md-4', 'strict' => 1]) ?>
    <?= select($form, $errors, 'application_method', 'How to apply', $methods, ['blank' => 'Not listed', 'col' => 'col-md-6']) ?>
  </div>

  <h2 class="h5 mt-4">Verification and notes</h2>
  <div class="row g-3">
    <?= input($form, $errors, 'verification_date', 'Last verified on', ['type' => 'date', 'help' => 'The day someone confirmed the details on the official page. Use "Mark verified today" on the scholarship page to set today.']) ?>
    <div class="col-12"><label class="form-label" for="f-notes">Notes</label>
      <textarea class="form-control" id="f-notes" name="notes" rows="3" maxlength="5000"><?= v($form, 'notes') ?></textarea></div>
  </div>
  <?php if (!$isNew): ?>
    <p class="text-muted small mt-3 mb-0">Contributed by <?= e($existing['contributing_student'] ?? 'nobody (entered by hand)') ?>
      <?= $existing['import_batch_id'] ? ' · import batch #' . (int)$existing['import_batch_id'] : '' ?></p>
  <?php endif; ?>

  <div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit"><?= $isNew ? 'Add scholarship' : 'Save changes' ?></button>
    <a class="btn btn-outline-secondary" href="<?= e(url($isNew ? 'admin/scholarships/index.php' : 'admin/scholarships/view.php?id=' . $id)) ?>">Cancel</a>
  </div>
</form>
<?php
$js = <<<'JS'
<script>
(function () {
  var today = '__TODAY__';
  var $form = $('#sch-form');
  var num = /^\d+(\.\d{1,2})?$/;
  function val(name) { return $.trim($form.find('[name="' + name + '"]').val() || ''); }
  function money(raw) { return $.trim(raw.replace(/[$,]/g, '')); }

  // Same rules as validate_scholarship_input() in includes/scholarships.php.
  function check() {
    var e = {};
    if (!val('name')) { e.name = 'Enter the scholarship name.'; }
    if (!val('provider_name')) { e.provider_name = 'Enter the provider (the organization that offers the scholarship).'; }
    var u = val('official_url');
    if (!/^(https?:\/\/)?[a-z0-9-]+(\.[a-z0-9-]+)+([\/?#]\S*)?$/i.test(u)) { e.official_url = 'Enter a full web address for the official page, such as https://www.example.org/apply.'; }
    if (!val('state_code')) { e.state_code = 'Choose a state, or National.'; }
    if ($form.find('input[name="grades[]"]:checked').length === 0) { e.grades = 'Choose at least one grade from 6 to 12.'; }
    var amin = money(val('award_min')), amax = money(val('award_max'));
    if (amin && !num.test(amin)) { e.award_min = 'The smallest award must be a number such as 1000 or 2500.50.'; }
    if (amax && !num.test(amax)) { e.award_max = 'The largest award must be a number such as 1000 or 2500.50.'; }
    if (!e.award_min && !e.award_max && amin && amax && parseFloat(amin) > parseFloat(amax)) { e.award_max = 'The largest award cannot be less than the smallest award.'; }
    var mpv = money(val('max_potential_value'));
    if (mpv && !num.test(mpv)) { e.max_potential_value = 'The maximum potential value must be a number such as 1000 or 2500.50.'; }
    var n = val('num_awards');
    if (n && (!/^\d+$/.test(n) || parseInt(n, 10) < 1)) { e.num_awards = 'Number of awards must be a whole number of 1 or more.'; }
    var g = val('min_gpa');
    if (g && (!/^\d(\.\d{1,2})?$/.test(g) || parseFloat(g) > 4.5)) { e.min_gpa = 'Minimum GPA must be between 0 and 4.5.'; }
    var rolling = $('#f-is_rolling').is(':checked');
    var open = val('open_date'), dead = rolling ? '' : val('deadline'), ver = val('verification_date');
    if (open && dead && open > dead) { e.deadline = 'The deadline cannot be before the open date.'; }
    if (ver && ver > today) { e.verification_date = 'The verification date cannot be in the future.'; }
    return e;
  }

  function show(errors) {
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.invalid-feedback').each(function () { if (!$(this).data('server')) { $(this).text('').removeClass('d-block'); } });
    $.each(errors, function (key, text) {
      var $err = $('#err-' + key).text(text);
      if (key === 'grades') { $err.addClass('d-block'); }
      else { $form.find('[name="' + key + '"]').addClass('is-invalid'); }
    });
  }

  $('#f-is_rolling').on('change', function () {
    $('#f-deadline').prop('disabled', this.checked);
    if (this.checked) { $('#f-deadline').val(''); }
  }).trigger('change');

  $form.on('submit', function (ev) {
    var errors = check();
    show(errors);
    var first = Object.keys(errors)[0];
    if (first) {
      ev.preventDefault();
      var $t = first === 'grades' ? $form.find('input[name="grades[]"]').first() : $form.find('[name="' + first + '"]').first();
      $t.trigger('focus');
    }
  });
})();
</script>
JS;
$extraScripts = str_replace('__TODAY__', date('Y-m-d'), $js);
require __DIR__ . '/../../includes/footer.php';
