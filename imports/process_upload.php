<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_FILES['scholarship_file'])) {

        $file = $_FILES['scholarship_file'];

        echo "<h2>File Received!</h2>";

        echo "File name: " . htmlspecialchars($file['name']) . "<br>";
        echo "File type: " . htmlspecialchars($file['type']) . "<br>";
        echo "File size: " . $file['size'] . " bytes<br>";
        echo "Temporary location: " . htmlspecialchars($file['tmp_name']) . "<br>";
        echo "Upload error code: " . $file['error'] . "<br>";

    } else {
        echo "No file was received.";
    }

} else {
    echo "Please upload a file first.";
}
?>
