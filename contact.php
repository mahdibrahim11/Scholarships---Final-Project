<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Contact';
$errors = [];
$old = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }
    // Server-side validation (the browser's checks are a convenience, not protection).
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) { $errors['name'] = 'Enter your name (up to 100 characters).'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) { $errors['email'] = 'Enter a valid email address.'; }
    if ($old['subject'] === '' || mb_strlen($old['subject']) > 150) { $errors['subject'] = 'Enter a subject (up to 150 characters).'; }
    if (mb_strlen($old['message']) < 10 || mb_strlen($old['message']) > 5000) { $errors['message'] = 'Write a message between 10 and 5000 characters.'; }

    if (!$errors) {
        $st = db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $st->execute([$old['name'], $old['email'], $old['subject'], $old['message']]);
        flash('success', 'Thanks! Your message was sent.');
        redirect('contact.php');
    }
}
require __DIR__ . '/includes/header.php';
function field_class(array $errors, string $k): string { return isset($errors[$k]) ? ' is-invalid' : ''; }
?>
<h1>Contact us</h1>
<p>Questions, corrections, or a scholarship we should add? Tell us.</p>
<form method="post" novalidate class="row g-3 col-lg-8" id="contact-form">
  <?= csrf_field() ?>
  <div class="col-md-6">
    <label class="form-label" for="name">Your name</label>
    <input class="form-control<?= field_class($errors, 'name') ?>" id="name" name="name" maxlength="100" required value="<?= e($old['name']) ?>" aria-describedby="name-err">
    <div class="invalid-feedback" id="name-err"><?= e($errors['name'] ?? 'Enter your name.') ?></div>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="email">Email</label>
    <input class="form-control<?= field_class($errors, 'email') ?>" id="email" name="email" type="email" maxlength="190" required value="<?= e($old['email']) ?>" aria-describedby="email-err">
    <div class="invalid-feedback" id="email-err"><?= e($errors['email'] ?? 'Enter a valid email address.') ?></div>
  </div>
  <div class="col-12">
    <label class="form-label" for="subject">Subject</label>
    <input class="form-control<?= field_class($errors, 'subject') ?>" id="subject" name="subject" maxlength="150" required value="<?= e($old['subject']) ?>" aria-describedby="subject-err">
    <div class="invalid-feedback" id="subject-err"><?= e($errors['subject'] ?? 'Enter a subject.') ?></div>
  </div>
  <div class="col-12">
    <label class="form-label" for="message">Message</label>
    <textarea class="form-control<?= field_class($errors, 'message') ?>" id="message" name="message" rows="6" maxlength="5000" required aria-describedby="message-err"><?= e($old['message']) ?></textarea>
    <div class="invalid-feedback" id="message-err"><?= e($errors['message'] ?? 'Write at least 10 characters.') ?></div>
  </div>
  <div class="col-12"><button class="btn btn-primary" type="submit">Send message</button></div>
</form>
<?php
// Client-side validation (jQuery). Mirrors the server rules above; the server is still the authority.
$extraScripts = <<<'JS'
<script>
$('#contact-form').on('submit', function (ev) {
  var ok = true;
  this.querySelectorAll('input, textarea').forEach(function (el) {
    if (el.name === 'csrf') { return; }
    var valid = el.checkValidity() && (el.name !== 'message' || el.value.trim().length >= 10);
    el.classList.toggle('is-invalid', !valid);
    if (!valid) { ok = false; }
  });
  if (!ok) { ev.preventDefault(); $('.is-invalid').first().trigger('focus'); }
});
</script>
JS;
require __DIR__ . '/includes/footer.php';
