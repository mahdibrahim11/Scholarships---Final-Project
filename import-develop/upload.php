<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Import Scholarships</title>
</head>

<body>

<h1>Import Scholarships - MULTI FILE VERSION</h1>

<form
    action="process_upload.php"
    method="POST"
    enctype="multipart/form-data"
>

    <label>Select scholarship files:</label>

    <br><br>

    <input
        type="file"
        name="scholarship_files[]"
        accept=".csv,.xlsx"
        multiple
        required
    >

    <br><br>

    <button type="submit">
        Upload Files
    </button>

</form>

</body>
</html>
