<?php
/** FR-USER-01/02: create or edit a profile (grade, state, interests). An id in the address must be one of your own. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/my.php';
[$pdo, $uid, $role] = my_guard();

$editId = null;
if (isset($_REQUEST['id'])) {
    $raw = $_REQUEST['id'];
    $editId = is_string($raw) && ctype_digit($raw) ? (int)$raw : 0;
    if (!profile_owned($pdo, $editId, $uid)) { my_not_found(); }
}
// A student with a profile always edits that one (there is only one).
if ($editId === null && $role === ROLE_STUDENT && ($mine = profiles_for_user($pdo, $uid))) { redirect('my/profile_edit.php?id=' . (int)$mine[0]['id']); }

$old = ['display_name' => $role === ROLE_STUDENT ? (string)auth_user()['display_name'] : '', 'grade' => 0, 'state_code' => '', 'interests' => []];
if ($editId !== null) {
    $cur = profile_owned($pdo, $editId, $uid);
    $old = ['display_name' => $cur['display_name'], 'grade' => (int)$cur['grade'], 'state_code' => $cur['state_code'], 'interests' => $cur['interest_ids']];
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$clean, $errors] = validate_profile_input($pdo, $_POST);
    $old = $clean;
    if (!$errors) {
        [$id, $err] = profile_save($pdo, $uid, $role, $editId, $clean);
        if ($err === 'not_found') { my_not_found(); }
        if ($err) { $errors['display_name'] = $err; }
        else {
            set_active_profile($pdo, $id);
            flash('success', $editId === null ? 'Profile created. Here are scholarships that fit.' : 'Profile saved.');
            redirect($editId === null ? 'my/matches.php' : 'my/profiles.php');
        }
    }
}
$states = $pdo->query("SELECT code, name FROM states WHERE code <> 'US' ORDER BY name")->fetchAll();
$grades = $pdo->query('SELECT grade, label FROM grade_levels ORDER BY grade')->fetchAll();
$fields = $pdo->query("SELECT id, name FROM fields_of_study WHERE name <> 'Any / undecided' ORDER BY name")->fetchAll();
$pageTitle = $editId === null ? ($role === ROLE_PARENT ? 'Add a child' : 'Create my profile') : 'Edit profile';
require __DIR__ . '/../includes/header.php';
$cls = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
<h1><?= e($pageTitle) ?></h1>
<p class="text-muted">Only a name or nickname, grade, state and interests. Please do not enter a birth date, school, address or phone number.</p>
<form method="post" novalidate class="col-lg-7" id="profile-edit-form">
  <?= csrf_field() ?>
  <?php if ($editId !== null): ?><input type="hidden" name="id" value="<?= (int)$editId ?>"><?php endif; ?>
  <div class="mb-3">
    <label class="form-label" for="display_name"><?= $role === ROLE_PARENT ? 'Child\'s name or nickname' : 'Your name or nickname' ?></label>
    <input class="form-control<?= $cls('display_name') ?>" id="display_name" name="display_name" maxlength="60" required value="<?= e($old['display_name']) ?>">
    <div class="invalid-feedback"><?= e($errors['display_name'] ?? 'Enter a name or nickname (up to 60 characters).') ?></div>
  </div>
  <div class="row g-3 mb-3">
    <div class="col-sm-6">
      <label class="form-label" for="grade">Grade</label>
      <select class="form-select<?= $cls('grade') ?>" id="grade" name="grade" required>
        <option value="">Choose a grade</option>
        <?php foreach ($grades as $g): ?><option value="<?= (int)$g['grade'] ?>"<?= (int)$old['grade'] === (int)$g['grade'] ? ' selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?>
      </select>
      <div class="invalid-feedback"><?= e($errors['grade'] ?? 'Choose a grade.') ?></div>
    </div>
    <div class="col-sm-6">
      <label class="form-label" for="state_code">State</label>
      <select class="form-select<?= $cls('state_code') ?>" id="state_code" name="state_code" required>
        <option value="">Choose a state</option>
        <?php foreach ($states as $s): ?><option value="<?= e($s['code']) ?>"<?= $old['state_code'] === $s['code'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
      <div class="invalid-feedback"><?= e($errors['state_code'] ?? 'Choose a state.') ?></div>
    </div>
  </div>
  <fieldset class="mb-3">
    <legend class="form-label fs-6">Interests (choose any, or none to see every field)</legend>
    <?php if (isset($errors['interests'])): ?><div class="text-danger small mb-1"><?= e($errors['interests']) ?></div><?php endif; ?>
    <div class="row row-cols-1 row-cols-sm-2 g-1">
      <?php foreach ($fields as $f): ?>
        <div class="col"><div class="form-check">
          <input class="form-check-input" type="checkbox" name="interests[]" id="int-<?= (int)$f['id'] ?>" value="<?= (int)$f['id'] ?>"<?= in_array((int)$f['id'], $old['interests'], true) ? ' checked' : '' ?>>
          <label class="form-check-label" for="int-<?= (int)$f['id'] ?>"><?= e($f['name']) ?></label>
        </div></div>
      <?php endforeach; ?>
    </div>
  </fieldset>
  <button class="btn btn-primary" type="submit">Save profile</button>
  <a class="btn btn-outline-secondary" href="<?= e(url('my/profiles.php')) ?>">Cancel</a>
</form>
<?php
$extraScripts = '<script>document.getElementById("profile-edit-form").addEventListener("submit",function(ev){var ok=true;this.querySelectorAll("[required]").forEach(function(el){var v=el.value.trim()!=="";el.classList.toggle("is-invalid",!v);if(!v){ok=false;}});if(!ok){ev.preventDefault();var b=this.querySelector(".is-invalid");if(b){b.focus();}}});</script>';
require __DIR__ . '/../includes/footer.php';
