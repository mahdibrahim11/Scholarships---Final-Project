<?php
/**
 * Public scholarship search (Iteration 5). Works with or without JavaScript:
 * the form is a plain GET form, and assets/js/search.js upgrades it to update the results in place.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/search.php';

$pdo = db();
$p = search_params($_GET);
$opt = search_options($pdo);
$res = search_run($pdo, $p);
if ($p['page'] === 1 && ($_GET['nolog'] ?? '') !== '1') { search_log($pdo, $p); }

$extraActive = $p['category'] || $p['theme'] || $p['level'] || $p['min_award'] || $p['due'] || $p['gpa'] !== ''
    || $p['no_essay'] || $p['no_need'] || $p['no_rec'] || $p['renewable'];

function sel(array $options, string $valueKey, string $labelKey, $current, string $any, ?callable $label = null): string
{
    $html = '<option value="">' . e($any) . '</option>';
    foreach ($options as $o) {
        $v = (string)$o[$valueKey];
        $html .= '<option value="' . e($v) . '"' . ((string)$current === $v && $current !== 0 && $current !== '' ? ' selected' : '') . '>'
            . e($label ? $label($o) : (string)$o[$labelKey]) . '</option>';
    }
    return $html;
}

$pageTitle = 'Find scholarships';
require __DIR__ . '/includes/header.php';
?>
<h1>Find scholarships</h1>
<p class="text-muted">Choose your state, your grade, and a field of study. Results update as you choose, and a state search also includes national scholarships. Every scholarship links to the provider's own page.</p>

<form method="get" action="<?= e(url('search.php')) ?>" id="search-form" class="search-form mb-3" aria-label="Search scholarships">
  <div class="row g-3 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label" for="f-state">State</label>
      <select class="form-select" id="f-state" name="state" data-mandatory="1">
        <?= sel($opt['states'], 'code', 'name', $p['state'], 'Any state', fn($s) => $s['code'] === 'US' ? 'National only' : $s['name']) ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label" for="f-grade">Grade</label>
      <select class="form-select" id="f-grade" name="grade" data-mandatory="1">
        <?= sel($opt['grades'], 'grade', 'label', $p['grade'], 'Any grade') ?>
      </select>
    </div>
    <div class="col-6 col-md-5">
      <label class="form-label" for="f-field">Field of study</label>
      <select class="form-select" id="f-field" name="field" data-mandatory="1">
        <?= sel($opt['fields'], 'id', 'name', $p['field'], 'Any field') ?>
      </select>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label" for="f-q">Keyword</label>
      <input class="form-control" type="search" id="f-q" name="q" value="<?= e($p['q']) ?>" maxlength="120" placeholder="For example robotics, nursing, or a provider name" autocomplete="off">
    </div>
    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label" for="f-sort">Sort by</label>
      <select class="form-select" id="f-sort" name="sort">
        <?php foreach (SEARCH_SORTS as $k => $label): ?><option value="<?= e($k) ?>"<?= $p['sort'] === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-sm-6 col-md-3 d-grid">
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
  </div>

  <p class="mt-3 mb-2">
    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#more-filters"
            aria-expanded="<?= $extraActive ? 'true' : 'false' ?>" aria-controls="more-filters">More filters</button>
    <a class="btn btn-link btn-sm" href="<?= e(url('search.php')) ?>" id="clear-filters">Clear all</a>
  </p>
  <div class="collapse<?= $extraActive ? ' show' : '' ?>" id="more-filters">
    <div class="row g-3 border rounded p-3 bg-white">
      <div class="col-12 col-md-4">
        <label class="form-label" for="f-category">Category</label>
        <select class="form-select" id="f-category" name="category"><?= sel($opt['categories'], 'id', 'name', $p['category'], 'Any category') ?></select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="f-level">School level</label>
        <select class="form-select" id="f-level" name="level">
          <option value="">Any</option>
          <option value="middle"<?= $p['level'] === 'middle' ? ' selected' : '' ?>>Middle school</option>
          <option value="high"<?= $p['level'] === 'high' ? ' selected' : '' ?>>High school</option>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="f-theme">National theme</label>
        <select class="form-select" id="f-theme" name="theme"><?= sel($opt['themes'], 'id', 'name', $p['theme'], 'Any theme') ?></select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label" for="f-min_award">Award of at least</label>
        <select class="form-select" id="f-min_award" name="min_award">
          <option value="">Any amount</option>
          <?php foreach (SEARCH_MIN_AWARDS as $m): ?><option value="<?= $m ?>"<?= $p['min_award'] === $m ? ' selected' : '' ?>>$<?= number_format($m) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label" for="f-due">Deadline</label>
        <select class="form-select" id="f-due" name="due">
          <option value="">Any time</option>
          <?php foreach (SEARCH_DEADLINE_WINDOWS as $k => $label): ?><option value="<?= e((string)$k) ?>"<?= $p['due'] === (string)$k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label" for="f-gpa">My GPA</label>
        <input class="form-control" id="f-gpa" name="gpa" inputmode="decimal" maxlength="4" value="<?= e($p['gpa']) ?>" placeholder="For example 3.4">
        <div class="form-text">Hides scholarships that need a higher GPA.</div>
      </div>
      <div class="col-12">
        <fieldset>
          <legend class="form-label fs-6">Only show scholarships that</legend>
          <div class="d-flex flex-wrap gap-3">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="f-no_essay" name="no_essay" value="1"<?= $p['no_essay'] ? ' checked' : '' ?>><label class="form-check-label" for="f-no_essay">Do not need an essay</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="f-no_need" name="no_need" value="1"<?= $p['no_need'] ? ' checked' : '' ?>><label class="form-check-label" for="f-no_need">Do not require financial need</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="f-no_rec" name="no_rec" value="1"<?= $p['no_rec'] ? ' checked' : '' ?>><label class="form-check-label" for="f-no_rec">Do not need a recommendation</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="f-renewable" name="renewable" value="1"<?= $p['renewable'] ? ' checked' : '' ?>><label class="form-check-label" for="f-renewable">Can be renewed</label></div>
          </div>
        </fieldset>
      </div>
    </div>
  </div>

  <div class="form-check mt-3">
    <input class="form-check-input" type="checkbox" id="f-expired" name="expired" value="1"<?= $p['expired'] ? ' checked' : '' ?>>
    <label class="form-check-label" for="f-expired">Include scholarships whose deadline has passed</label>
  </div>
</form>

<div id="results" aria-live="polite" aria-busy="false">
  <?= search_render_results($res, $p) ?>
</div>
<?php
$extraScripts = '<script src="' . e(url('assets/js/search.js')) . '"></script><script src="' . e(url('assets/js/save.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
