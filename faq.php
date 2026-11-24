<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'FAQ';
$faqs = [
  ['Who is this for?', 'Students in grades 6 to 12 and their parents or guardians.'],
  ['Does it cost anything?', 'No. Searching is free. We never ask for payment, and you should never pay to apply for a scholarship you find here.'],
  ['What does "National" mean?', 'A scholarship is national if students from two or more states can apply. State and local scholarships require you to live in one state, city, or county.'],
  ['How current is the information?', 'Each scholarship shows the date it was last verified. If the date is old, check the official page.'],
  ['Why is a scholarship missing?', 'Our list is still growing. Use the Contact page to suggest one.'],
];
require __DIR__ . '/includes/header.php';
?>
<h1>Frequently asked questions</h1>
<div class="accordion mt-3" id="faq">
<?php foreach ($faqs as $i => [$q, $a]): ?>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>" aria-controls="faq<?= $i ?>"><?= e($q) ?></button>
    </h2>
    <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faq">
      <div class="accordion-body"><?= e($a) ?></div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
