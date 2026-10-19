<?php
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Import Scholarships</h1>

<p>Upload a CSV or Excel file containing scholarship data.</p>

<form action="process_upload.php" method="POST" enctype="multipart/form-data">

    <label for="scholarship_file">Select scholarship file:</label>

    <br><br>

    <input
        type="file"
        name="scholarship_file"
        id="scholarship_file"
        accept=".csv,.xlsx"
        required
    >

    <br><br>

    <button type="submit">Upload File</button>

</form>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
