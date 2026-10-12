<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php
        if (isset($pageTitle)) {
            echo htmlspecialchars($pageTitle);
        } else {
            echo "Scholarship Finder";
        }
        ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body>

<?php require_once __DIR__ . '/nav.php'; ?>
<?php require_once __DIR__ . '/dev-banner.php'; ?>

<main class="container py-4">
